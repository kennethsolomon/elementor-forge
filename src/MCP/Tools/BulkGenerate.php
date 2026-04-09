<?php
/**
 * MCP tool: bulk_generate_pages — batched + transactional matrix builder.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\CPT\PostTypes;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Phase 3 implementation of bulk page generation. Replaces the Phase 1 unbatched
 * loop that Perry flagged as HIGH (no cache suspension, no deferred term
 * counting, no transaction, ~400 DB writes for a 50-item run).
 *
 * Optimizations vs Phase 1:
 *
 *   - Wraps the entire bulk loop in `wp_suspend_cache_addition(true)` and
 *     `wp_defer_term_counting(true)` so the cache layer doesn't flush per row
 *     and term counting only runs once at the end.
 *   - Wraps the loop in a single `$wpdb` transaction (START TRANSACTION /
 *     COMMIT / ROLLBACK on first failure when the caller opts in via
 *     `transactional: true`). Default is true.
 *   - Uses `meta_input` in `wp_insert_post` to fold every meta field into a
 *     single DB write per post — eliminates the N x update_post_meta /
 *     update_field round-trips.
 *   - Records progress to a transient keyed by job ID for polling.
 *
 * New scenarios:
 *
 *   - Matrix generation: pass `cpt='ef_location'`, `multiply_by='ef_service'`,
 *     `items` as the suburb list, and `service_items` as the service list.
 *     Output is a |suburbs| x |services| product (each combination becomes
 *     one ef_location post with the service title appended).
 *   - Dry run: `dry_run: true` validates inputs and returns the planned
 *     post count + per-post field map without writing anything.
 *
 * Cache invalidation: the cache addition is suspended for the duration of the
 * bulk run, then re-enabled at the end. Term counting is deferred and flushed
 * once on completion.
 */
final class BulkGenerate {

	public const TRANSIENT_PREFIX = 'elementor_forge_bulk_';

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'cpt', 'items' ),
			'additionalProperties' => false,
			'properties'           => array(
				'cpt'            => array( 'type' => 'string', 'enum' => array( PostTypes::LOCATION, PostTypes::SERVICE ) ),
				'items'          => array(
					'type'     => 'array',
					'minItems' => 1,
					'items'    => array(
						'type'       => 'object',
						'required'   => array( 'title' ),
						'properties' => array(
							'title'      => array( 'type' => 'string' ),
							'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ),
							'acf_fields' => array( 'type' => 'object' ),
						),
					),
				),
				'multiply_by'    => array(
					'type'        => 'string',
					'enum'        => array( PostTypes::LOCATION, PostTypes::SERVICE ),
					'description' => 'Optional. When provided, items are crossed with service_items to produce a matrix.',
				),
				'service_items'  => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array( 'title' ),
						'properties' => array(
							'title'      => array( 'type' => 'string' ),
							'acf_fields' => array( 'type' => 'object' ),
						),
					),
				),
				'transactional'  => array( 'type' => 'boolean', 'default' => true ),
				'dry_run'        => array( 'type' => 'boolean', 'default' => false ),
				'job_id'         => array( 'type' => 'string', 'description' => 'Optional caller-supplied job id for progress polling.' ),
				'allowed_fields' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Hard allowlist of ACF field keys. When present, any key in acf_fields NOT in this list is dropped before write. When absent, the underscore-prefix filter runs alone.',
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'job_id'        => array( 'type' => 'string' ),
				'planned'       => array( 'type' => 'integer' ),
				'created'       => array( 'type' => 'array' ),
				'failed'        => array( 'type' => 'array' ),
				'dry_run'       => array( 'type' => 'boolean' ),
				'rolled_back'   => array( 'type' => 'boolean' ),
				'transactional' => array( 'type' => 'boolean' ),
			),
		);
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input ) {
		$gate = Gate::check( 'bulk_generate_pages', Gate::ACTION_CREATE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$cpt = isset( $input['cpt'] ) && is_string( $input['cpt'] ) ? $input['cpt'] : '';
		if ( ! in_array( $cpt, array( PostTypes::LOCATION, PostTypes::SERVICE ), true ) ) {
			return new WP_Error( 'elementor_forge_invalid_cpt', 'Invalid cpt.' );
		}

		$items = isset( $input['items'] ) && is_array( $input['items'] ) ? $input['items'] : array();
		if ( array() === $items ) {
			return new WP_Error( 'elementor_forge_empty_items', 'items[] cannot be empty.' );
		}

		$multiply_by    = isset( $input['multiply_by'] ) && is_string( $input['multiply_by'] ) ? $input['multiply_by'] : '';
		$service_items  = isset( $input['service_items'] ) && is_array( $input['service_items'] ) ? $input['service_items'] : array();
		$transactional  = ! isset( $input['transactional'] ) || (bool) $input['transactional'];
		$dry_run        = isset( $input['dry_run'] ) && (bool) $input['dry_run'];
		$job_id         = isset( $input['job_id'] ) && is_string( $input['job_id'] ) ? $input['job_id'] : self::generate_job_id();
		$allowed_fields = null;
		if ( isset( $input['allowed_fields'] ) && is_array( $input['allowed_fields'] ) ) {
			$allowed_fields = array();
			foreach ( $input['allowed_fields'] as $field ) {
				if ( is_string( $field ) && '' !== $field ) {
					$allowed_fields[] = $field;
				}
			}
		}

		// Build the planned task list. Matrix mode crosses items × service_items.
		$plan          = self::build_plan( $cpt, $items, $multiply_by, $service_items );
		$planned_count = count( $plan );

		if ( $dry_run ) {
			return array(
				'job_id'        => $job_id,
				'planned'       => $planned_count,
				'created'       => array(),
				'failed'        => array(),
				'dry_run'       => true,
				'rolled_back'   => false,
				'transactional' => $transactional,
				'plan'          => $plan,
			);
		}

		self::progress_init( $job_id, $planned_count );

		// Suspend cache addition + defer term counting for the entire run.
		$prev_suspend = function_exists( 'wp_suspend_cache_addition' ) ? wp_suspend_cache_addition( true ) : false;
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( true );
		}

		global $wpdb;
		$transaction_active = false;
		if ( $transactional && isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'START TRANSACTION' );
			$transaction_active = true;
		}

		$created        = array();
		$failed         = array();
		$rejected_keys  = array();
		$rolled_back    = false;

		try {
			foreach ( $plan as $idx => $task ) {
				$filter_result         = self::filter_meta_input( $task['acf_fields'], $allowed_fields );
				$task['acf_fields']    = $filter_result['fields'];
				if ( array() !== $filter_result['rejected'] ) {
					foreach ( $filter_result['rejected'] as $bad_key ) {
						$rejected_keys[] = $task['title'] . ':' . $bad_key;
					}
				}

				$result = self::insert_one( $task );
				if ( is_wp_error( $result ) ) {
					$failed[] = array(
						'title' => $task['title'],
						'error' => $result->get_error_message(),
					);
					if ( $transactional ) {
						if ( $transaction_active && isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->query( 'ROLLBACK' );
							$transaction_active = false;
						}
						$rolled_back = true;
						break;
					}
				} else {
					$created[] = $result;
				}
				self::progress_advance( $job_id, $idx + 1 );
			}

			if ( $transaction_active && ! $rolled_back && isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'COMMIT' );
				$transaction_active = false;
			}
		} catch ( \Throwable $e ) {
			// Any uncaught throwable from wp_insert_post or beneath — roll back
			// explicitly so a half-written transaction never lingers, then
			// re-throw so the caller sees the real failure.
			if ( $transaction_active && isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				$transaction_active = false;
			}
			throw $e;
		} finally {
			// Restore prior cache-suspension state (NOT hardcoded false — the
			// caller may already have had it suspended for other reasons).
			if ( function_exists( 'wp_suspend_cache_addition' ) ) {
				wp_suspend_cache_addition( (bool) $prev_suspend );
			}
			if ( function_exists( 'wp_defer_term_counting' ) ) {
				wp_defer_term_counting( false );
			}
			self::progress_complete( $job_id );
		}

		$out = array(
			'job_id'        => $job_id,
			'planned'       => $planned_count,
			'created'       => $created,
			'failed'        => $failed,
			'dry_run'       => false,
			'rolled_back'   => $rolled_back,
			'transactional' => $transactional,
		);
		if ( array() !== $rejected_keys ) {
			$out['rejected_meta_keys'] = $rejected_keys;
		}
		return $out;
	}

	/**
	 * Filter acf_fields to block `_`-prefixed meta keys (internal WP meta) and,
	 * when an explicit `allowed_fields` param is supplied, to keep only keys in
	 * the allowlist. Returns the filtered map + the list of rejected keys so the
	 * caller (and the progress transient) can see what was stripped.
	 *
	 * @param array<string, mixed> $acf_fields
	 * @param list<string>|null    $allowed_fields
	 * @return array{fields: array<string, mixed>, rejected: list<string>}
	 */
	private static function filter_meta_input( array $acf_fields, ?array $allowed_fields ): array {
		$fields   = array();
		$rejected = array();
		foreach ( $acf_fields as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			// Reject WP-internal meta convention (`_edit_lock`, `_elementor_data`,
			// `_ef_template_type`, `_wp_page_template`, etc). These keys are never
			// safe to set via a bulk MCP tool.
			if ( '_' === $key[0] ) {
				$rejected[] = $key;
				continue;
			}
			if ( null !== $allowed_fields && ! in_array( $key, $allowed_fields, true ) ) {
				$rejected[] = $key;
				continue;
			}
			$fields[ $key ] = $value;
		}
		return array( 'fields' => $fields, 'rejected' => $rejected );
	}

	/**
	 * Read the progress transient for a job.
	 *
	 * @return array{planned:int, completed:int, status:string}|null
	 */
	public static function get_progress( string $job_id ): ?array {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$value = get_transient( self::TRANSIENT_PREFIX . $job_id );
		if ( ! is_array( $value ) ) {
			return null;
		}
		return array(
			'planned'   => isset( $value['planned'] ) && is_int( $value['planned'] ) ? $value['planned'] : 0,
			'completed' => isset( $value['completed'] ) && is_int( $value['completed'] ) ? $value['completed'] : 0,
			'status'    => isset( $value['status'] ) && is_string( $value['status'] ) ? $value['status'] : 'unknown',
		);
	}

	/**
	 * Build the flat task list for either a single-cpt run or a matrix run.
	 *
	 * @param array<int, mixed> $items
	 * @param array<int, mixed> $service_items
	 * @return list<array{cpt:string, title:string, status:string, acf_fields:array<string, mixed>}>
	 */
	private static function build_plan( string $cpt, array $items, string $multiply_by, array $service_items ): array {
		$plan = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['title'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}
			$base_title  = $item['title'];
			$base_status = isset( $item['status'] ) && is_string( $item['status'] ) ? $item['status'] : 'draft';
			$base_acf    = isset( $item['acf_fields'] ) && is_array( $item['acf_fields'] ) ? $item['acf_fields'] : array();

			if ( '' === $multiply_by || array() === $service_items ) {
				$plan[] = array(
					'cpt'        => $cpt,
					'title'      => $base_title,
					'status'     => $base_status,
					'acf_fields' => $base_acf,
				);
				continue;
			}

			foreach ( $service_items as $service ) {
				if ( ! is_array( $service ) || ! isset( $service['title'] ) || ! is_string( $service['title'] ) ) {
					continue;
				}
				$service_acf = isset( $service['acf_fields'] ) && is_array( $service['acf_fields'] ) ? $service['acf_fields'] : array();
				$plan[]      = array(
					'cpt'        => $cpt,
					'title'      => $base_title . ' — ' . $service['title'],
					'status'     => $base_status,
					'acf_fields' => array_merge( $base_acf, $service_acf ),
				);
			}
		}
		return $plan;
	}

	/**
	 * Insert one post with all its meta in a single wp_insert_post call. Folds
	 * the ACF fields into meta_input rather than calling update_field N times.
	 * Filtering of `_`-prefixed or out-of-allowlist keys happens in the caller
	 * via {@see self::filter_meta_input()} — by the time insert_one() runs,
	 * every key is already safe.
	 *
	 * @param array{cpt:string, title:string, status:string, acf_fields:array<string, mixed>} $task
	 * @return array{post_id:int, url:string}|WP_Error
	 */
	private static function insert_one( array $task ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => $task['cpt'],
				'post_title'  => $task['title'],
				'post_status' => $task['status'],
				'meta_input'  => $task['acf_fields'],
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		return array(
			'post_id' => (int) $post_id,
			'url'     => (string) get_permalink( (int) $post_id ),
		);
	}

	private static function generate_job_id(): string {
		return 'job_' . dechex( (int) ( microtime( true ) * 1000 ) ) . '_' . dechex( random_int( 0, 0xFFFF ) );
	}

	private static function progress_init( string $job_id, int $planned ): void {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			self::TRANSIENT_PREFIX . $job_id,
			array(
				'planned'   => $planned,
				'completed' => 0,
				'status'    => 'running',
			),
			HOUR_IN_SECONDS
		);
	}

	private static function progress_advance( string $job_id, int $completed ): void {
		if ( ! function_exists( 'set_transient' ) || ! function_exists( 'get_transient' ) ) {
			return;
		}
		$current = get_transient( self::TRANSIENT_PREFIX . $job_id );
		if ( ! is_array( $current ) ) {
			return;
		}
		$current['completed'] = $completed;
		set_transient( self::TRANSIENT_PREFIX . $job_id, $current, HOUR_IN_SECONDS );
	}

	private static function progress_complete( string $job_id ): void {
		if ( ! function_exists( 'set_transient' ) || ! function_exists( 'get_transient' ) ) {
			return;
		}
		$current = get_transient( self::TRANSIENT_PREFIX . $job_id );
		if ( ! is_array( $current ) ) {
			return;
		}
		$current['status'] = 'complete';
		set_transient( self::TRANSIENT_PREFIX . $job_id, $current, HOUR_IN_SECONDS );
	}
}
