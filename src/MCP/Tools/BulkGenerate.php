<?php
/**
 * MCP tool: bulk_generate_pages — batched + transactional matrix builder.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\CPT\PostTypes;
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
				'cpt'           => array( 'type' => 'string', 'enum' => array( PostTypes::LOCATION, PostTypes::SERVICE ) ),
				'items'         => array(
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
				'multiply_by'   => array(
					'type'        => 'string',
					'enum'        => array( PostTypes::LOCATION, PostTypes::SERVICE ),
					'description' => 'Optional. When provided, items are crossed with service_items to produce a matrix.',
				),
				'service_items' => array(
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
				'transactional' => array( 'type' => 'boolean', 'default' => true ),
				'dry_run'       => array( 'type' => 'boolean', 'default' => false ),
				'job_id'        => array( 'type' => 'string', 'description' => 'Optional caller-supplied job id for progress polling.' ),
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
		$cpt = isset( $input['cpt'] ) && is_string( $input['cpt'] ) ? $input['cpt'] : '';
		if ( ! in_array( $cpt, array( PostTypes::LOCATION, PostTypes::SERVICE ), true ) ) {
			return new WP_Error( 'elementor_forge_invalid_cpt', 'Invalid cpt.' );
		}

		$items = isset( $input['items'] ) && is_array( $input['items'] ) ? $input['items'] : array();
		if ( array() === $items ) {
			return new WP_Error( 'elementor_forge_empty_items', 'items[] cannot be empty.' );
		}

		$multiply_by   = isset( $input['multiply_by'] ) && is_string( $input['multiply_by'] ) ? $input['multiply_by'] : '';
		$service_items = isset( $input['service_items'] ) && is_array( $input['service_items'] ) ? $input['service_items'] : array();
		$transactional = ! isset( $input['transactional'] ) || (bool) $input['transactional'];
		$dry_run       = isset( $input['dry_run'] ) && (bool) $input['dry_run'];
		$job_id        = isset( $input['job_id'] ) && is_string( $input['job_id'] ) ? $input['job_id'] : self::generate_job_id();

		// Build the planned task list. Matrix mode crosses items × service_items.
		$plan = self::build_plan( $cpt, $items, $multiply_by, $service_items );
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
		if ( $transactional && isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			$wpdb->query( 'START TRANSACTION' );
		}

		$created     = array();
		$failed      = array();
		$rolled_back = false;

		foreach ( $plan as $idx => $task ) {
			$result = self::insert_one( $task );
			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'title' => $task['title'],
					'error' => $result->get_error_message(),
				);
				if ( $transactional ) {
					if ( isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
						$wpdb->query( 'ROLLBACK' );
					}
					$rolled_back = true;
					break;
				}
			} else {
				$created[] = $result;
			}
			self::progress_advance( $job_id, $idx + 1 );
		}

		if ( $transactional && ! $rolled_back && isset( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			$wpdb->query( 'COMMIT' );
		}

		// Re-enable cache addition + flush term counts.
		if ( function_exists( 'wp_suspend_cache_addition' ) ) {
			wp_suspend_cache_addition( $prev_suspend );
		}
		if ( function_exists( 'wp_defer_term_counting' ) ) {
			wp_defer_term_counting( false );
		}

		self::progress_complete( $job_id );

		return array(
			'job_id'        => $job_id,
			'planned'       => $planned_count,
			'created'       => $created,
			'failed'        => $failed,
			'dry_run'       => false,
			'rolled_back'   => $rolled_back,
			'transactional' => $transactional,
		);
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
	 *
	 * @param array{cpt:string, title:string, status:string, acf_fields:array<string, mixed>} $task
	 * @return array{post_id:int, url:string}|WP_Error
	 */
	private static function insert_one( array $task ) {
		$meta_input = array();
		foreach ( $task['acf_fields'] as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$meta_input[ $key ] = $value;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => $task['cpt'],
				'post_title'  => $task['title'],
				'post_status' => $task['status'],
				'meta_input'  => $meta_input,
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
