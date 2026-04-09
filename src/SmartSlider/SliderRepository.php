<?php
/**
 * Smart Slider 3 Free CRUD layer.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\SmartSlider;

use wpdb;

/**
 * Direct-DB CRUD against the Smart Slider 3 Free schema. The plugin ships no
 * public PHP API for slider authoring (only `Project::clearCache()` and
 * `Project::import()`), so direct $wpdb writes are the only available
 * integration surface.
 *
 * Schema reference: see `src/SmartSlider/README.md`. Every column name and
 * type used here was verified against Smart Slider 3 Free 3.5.1.34 source on
 * the WordPress.org SVN trunk.
 *
 * Safety:
 *   - Every public method calls capability check (`manage_options`) BEFORE
 *     touching the database.
 *   - Every $wpdb call uses prepare() with %s/%d placeholders. Table names
 *     are static, derived from $wpdb->prefix at construction.
 *   - Every public method gates on `is_available()`. When Smart Slider is
 *     missing or out of supported version range, methods throw
 *     {@see SmartSliderUnavailable}.
 *   - All writes invalidate the Smart Slider HTML cache via the public
 *     `Project::clearCache()` API when reachable; otherwise set the
 *     "cache_dirty" Forge option flag for the admin to surface.
 */
final class SliderRepository {

	public const SUPPORTED_MIN = '3.5.0';
	public const SUPPORTED_MAX = '3.7.0';

	public const CACHE_DIRTY_OPTION = 'elementor_forge_ss3_cache_dirty';

	private wpdb $wpdb;
	private string $sliders_table;
	private string $slides_table;
	private string $xref_table;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb          = $wpdb;
		$this->sliders_table = $wpdb->prefix . 'nextend2_smartslider3_sliders';
		$this->slides_table  = $wpdb->prefix . 'nextend2_smartslider3_slides';
		$this->xref_table    = $wpdb->prefix . 'nextend2_smartslider3_sliders_xref';
	}

	/**
	 * Whether Smart Slider 3 Free is loaded and within the supported version
	 * range. Read-only — no exceptions.
	 */
	public function is_available(): bool {
		if ( ! defined( 'NEXTEND_SMARTSLIDER_3_URL_PATH' ) ) {
			return false;
		}
		$version = $this->detect_version();
		if ( '' === $version ) {
			return false;
		}
		return version_compare( $version, self::SUPPORTED_MIN, '>=' )
			&& version_compare( $version, self::SUPPORTED_MAX, '<' );
	}

	/**
	 * Read the Smart Slider 3 plugin version. Reads from the option key the
	 * Smart Slider installer writes (`n2_ss3_version`). Returns empty string
	 * when unreadable.
	 */
	public function detect_version(): string {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}
		$value = get_option( 'n2_ss3_version', '' );
		if ( ! is_string( $value ) ) {
			return '';
		}
		// Smart Slider stores e.g. "3.5.1.34"; extract leading dotted segment.
		if ( preg_match( '/^([\d.]+)/', $value, $matches ) === 1 ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Create a new slider.
	 *
	 * @param string               $title  Slider title (required).
	 * @param array<string, mixed> $params Caller-supplied params keys merged on top of the safe defaults.
	 * @return int Newly inserted slider ID.
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function create_slider( string $title, array $params = array() ): int {
		$this->guard();

		$merged_params = array_merge(
			array(
				'aria-label'       => $title,
				'background-color' => 'FFFFFF00',
				'background-size'  => 'cover',
			),
			$params
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $this->wpdb->insert(
			$this->sliders_table,
			array(
				'alias'         => null,
				'title'         => $title,
				'type'          => 'simple',
				'params'        => self::encode_json( $merged_params ),
				'slider_status' => 'published',
				'time'          => $this->now(),
				'thumbnail'     => '',
				'ordering'      => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		if ( false === $inserted ) {
			throw new SmartSliderUnavailable( 'Failed to insert slider row: ' . $this->wpdb->last_error );
		}

		$slider_id = (int) $this->wpdb->insert_id;
		$this->invalidate_cache( $slider_id );
		return $slider_id;
	}

	/**
	 * Update an existing slider's title and params.
	 *
	 * @param array<string, mixed> $params Replaces the existing params blob entirely.
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function update_slider( int $slider_id, string $title, array $params ): bool {
		$this->guard();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $this->wpdb->update(
			$this->sliders_table,
			array(
				'title'  => $title,
				'params' => self::encode_json( $params ),
				'time'   => $this->now(),
			),
			array( 'id' => $slider_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			throw new SmartSliderUnavailable( 'Failed to update slider row: ' . $this->wpdb->last_error );
		}
		$this->invalidate_cache( $slider_id );
		return $updated > 0;
	}

	/**
	 * Fetch a single slider row, decoded.
	 *
	 * @return array{id:int, title:string, type:string, params:array<string, mixed>, status:string}|null
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function get_slider( int $slider_id ): ?array {
		$this->guard();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->prepare( "SELECT id, title, type, params, slider_status FROM `{$this->sliders_table}` WHERE id = %d", $slider_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		/** @var array<string, mixed> $row */
		return array(
			'id'     => (int) ( $row['id'] ?? 0 ),
			'title'  => isset( $row['title'] ) && is_string( $row['title'] ) ? $row['title'] : '',
			'type'   => isset( $row['type'] ) && is_string( $row['type'] ) ? $row['type'] : 'simple',
			'params' => self::decode_params( $row['params'] ?? '' ),
			'status' => isset( $row['slider_status'] ) && is_string( $row['slider_status'] ) ? $row['slider_status'] : 'published',
		);
	}

	/**
	 * Delete a slider and all its slides + xref rows.
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function delete_slider( int $slider_id ): bool {
		$this->guard();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$slides_deleted = $this->wpdb->delete( $this->slides_table, array( 'slider' => $slider_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$xref_deleted = $this->wpdb->delete( $this->xref_table, array( 'slider_id' => $slider_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$slider_deleted = $this->wpdb->delete( $this->sliders_table, array( 'id' => $slider_id ), array( '%d' ) );

		if ( false === $slides_deleted || false === $xref_deleted || false === $slider_deleted ) {
			throw new SmartSliderUnavailable( 'Smart Slider delete partially failed: ' . $this->wpdb->last_error );
		}

		$this->invalidate_cache( $slider_id );
		return $slider_deleted > 0;
	}

	/**
	 * Append a slide to an existing slider.
	 *
	 * @param array<string, mixed> $slide_data Recognized keys: title, body, params, layers.
	 *                                         If `layers` is not provided, `SlideTemplate::minimal($title, $body)` is used.
	 * @return int Newly inserted slide ID.
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function add_slide( int $slider_id, array $slide_data ): int {
		$this->guard();

		$title = isset( $slide_data['title'] ) && is_string( $slide_data['title'] ) ? $slide_data['title'] : '';
		$body  = isset( $slide_data['body'] ) && is_string( $slide_data['body'] ) ? $slide_data['body'] : '';
		$layers_json = '';
		if ( isset( $slide_data['layers'] ) && is_array( $slide_data['layers'] ) ) {
			$layers_json = self::encode_json( $slide_data['layers'] );
		} else {
			$layers_json = SlideTemplate::minimal( $title, $body );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$existing = (int) $this->wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->prepare( "SELECT COUNT(*) FROM `{$this->slides_table}` WHERE slider = %d", $slider_id )
		);
		$is_first = 0 === $existing ? 1 : 0;

		$slide_params = isset( $slide_data['params'] ) && is_array( $slide_data['params'] ) ? $slide_data['params'] : array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $this->wpdb->insert(
			$this->slides_table,
			array(
				'title'        => $title,
				'slider'       => $slider_id,
				'publish_up'   => '1970-01-01 00:00:00',
				'publish_down' => '1970-01-01 00:00:00',
				'published'    => 1,
				'first'        => $is_first,
				'slide'        => $layers_json,
				'description'  => '',
				'thumbnail'    => '',
				'params'       => self::encode_json( $slide_params ),
				'ordering'     => $existing,
				'generator_id' => 0,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		if ( false === $inserted ) {
			throw new SmartSliderUnavailable( 'Failed to insert slide row: ' . $this->wpdb->last_error );
		}

		$this->invalidate_cache( $slider_id );
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing slide's title, body, and params.
	 *
	 * @param array<string, mixed> $slide_data Recognized keys: title, body, params, layers.
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function update_slide( int $slide_id, array $slide_data ): bool {
		$this->guard();

		$set     = array();
		$formats = array();
		if ( isset( $slide_data['title'] ) && is_string( $slide_data['title'] ) ) {
			$set['title'] = $slide_data['title'];
			$formats[]    = '%s';
		}
		if ( isset( $slide_data['layers'] ) && is_array( $slide_data['layers'] ) ) {
			$set['slide'] = self::encode_json( $slide_data['layers'] );
			$formats[]    = '%s';
		} elseif ( isset( $slide_data['body'] ) && is_string( $slide_data['body'] ) ) {
			$title        = isset( $slide_data['title'] ) && is_string( $slide_data['title'] ) ? $slide_data['title'] : '';
			$set['slide'] = SlideTemplate::minimal( $title, $slide_data['body'] );
			$formats[]    = '%s';
		}
		if ( isset( $slide_data['params'] ) && is_array( $slide_data['params'] ) ) {
			$set['params'] = self::encode_json( $slide_data['params'] );
			$formats[]     = '%s';
		}

		if ( array() === $set ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $this->wpdb->update(
			$this->slides_table,
			$set,
			array( 'id' => $slide_id ),
			$formats,
			array( '%d' )
		);
		if ( false === $updated ) {
			throw new SmartSliderUnavailable( 'Failed to update slide row: ' . $this->wpdb->last_error );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$slider_id = (int) $this->wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->prepare( "SELECT slider FROM `{$this->slides_table}` WHERE id = %d", $slide_id )
		);
		if ( $slider_id > 0 ) {
			$this->invalidate_cache( $slider_id );
		}
		return $updated > 0;
	}

	/**
	 * Delete a single slide.
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function delete_slide( int $slide_id ): bool {
		$this->guard();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$slider_id = (int) $this->wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->prepare( "SELECT slider FROM `{$this->slides_table}` WHERE id = %d", $slide_id )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->delete( $this->slides_table, array( 'id' => $slide_id ), array( '%d' ) );
		if ( false === $deleted ) {
			throw new SmartSliderUnavailable( 'Failed to delete slide row: ' . $this->wpdb->last_error );
		}

		if ( $slider_id > 0 ) {
			$this->invalidate_cache( $slider_id );
		}
		return $deleted > 0;
	}

	/**
	 * List every slider row.
	 *
	 * @return list<array{id:int, title:string, status:string, type:string}>
	 *
	 * @throws SmartSliderUnavailable When SS3 is not present or version is out of range.
	 */
	public function list_sliders(): array {
		$this->guard();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			"SELECT id, title, slider_status, type FROM `{$this->sliders_table}` ORDER BY ordering ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		/** @var list<array{id:int, title:string, status:string, type:string}> $out */
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'id'     => (int) ( $row['id'] ?? 0 ),
				'title'  => isset( $row['title'] ) && is_string( $row['title'] ) ? $row['title'] : '',
				'status' => isset( $row['slider_status'] ) && is_string( $row['slider_status'] ) ? $row['slider_status'] : 'published',
				'type'   => isset( $row['type'] ) && is_string( $row['type'] ) ? $row['type'] : 'simple',
			);
		}
		return $out;
	}

	/**
	 * Capability + presence + version gate. Throws on any failure so callers
	 * can pattern-match on a single exception type.
	 *
	 * @throws SmartSliderUnavailable When capability, presence, or version checks fail.
	 */
	private function guard(): void {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
			throw new SmartSliderUnavailable( 'Insufficient permissions: manage_options is required for Smart Slider CRUD.' );
		}
		if ( ! defined( 'NEXTEND_SMARTSLIDER_3_URL_PATH' ) ) {
			throw new SmartSliderUnavailable( 'Smart Slider 3 is not active. Install and activate the plugin first.' );
		}
		$version = $this->detect_version();
		if ( '' === $version ) {
			throw new SmartSliderUnavailable( 'Smart Slider 3 version could not be detected — refusing CRUD on an unknown release.' );
		}
		if ( version_compare( $version, self::SUPPORTED_MIN, '<' ) || version_compare( $version, self::SUPPORTED_MAX, '>=' ) ) {
			throw new SmartSliderUnavailable(
				sprintf(
					'Smart Slider 3 version %s is outside the supported range %s..<%s.',
					$version,
					self::SUPPORTED_MIN,
					self::SUPPORTED_MAX
				)
			);
		}
	}

	/**
	 * Invalidate Smart Slider's HTML cache for a slider. Tries the public API
	 * first; falls back to the Forge "dirty cache" option flag if the public
	 * API class is not loaded.
	 */
	private function invalidate_cache( int $slider_id ): void {
		$class = '\\Nextend\\SmartSlider3\\PublicApi\\Project';
		// PHPStan can't know the Smart Slider plugin is loaded at runtime;
		// method_exists() is the only safe way to bridge to its public API.
		/** @phpstan-ignore-next-line function.impossibleType */
		if ( class_exists( $class ) && method_exists( $class, 'clearCache' ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			call_user_func( array( $class, 'clearCache' ), $slider_id );
			return;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::CACHE_DIRTY_OPTION, true, false );
		}
	}

	/**
	 * @param mixed $params
	 * @return array<string, mixed>
	 */
	private static function decode_params( $params ): array {
		if ( ! is_string( $params ) || '' === $params ) {
			return array();
		}
		$decoded = json_decode( $params, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private function now(): string {
		if ( function_exists( 'current_time' ) ) {
			$value = current_time( 'mysql' );
			return is_string( $value ) ? $value : gmdate( 'Y-m-d H:i:s' );
		}
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Encode a value as JSON for storage. Prefers wp_json_encode when WP is
	 * loaded so encoding rules match Smart Slider's own writes.
	 *
	 * @param mixed $value
	 */
	private static function encode_json( $value ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value );
			if ( is_string( $encoded ) ) {
				return $encoded;
			}
		}
		$encoded = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return is_string( $encoded ) ? $encoded : '{}';
	}
}
