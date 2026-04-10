<?php
/**
 * Installer that materializes TemplateSpecs into elementor_library CPT posts.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\ThemeBuilder;

use ElementorForge\Elementor\CacheClearer;
use ElementorForge\Elementor\Emitter\Encoder;

/**
 * Writes each {@see TemplateSpec} to the WordPress database as an
 * `elementor_library` post with the correct meta flags so Elementor picks it
 * up as a Theme Builder template.
 *
 * Idempotent — on subsequent runs the installer detects existing templates by
 * the `_ef_template_type` meta key and updates them in place rather than
 * creating duplicates.
 */
final class Installer {

	public const META_TEMPLATE_TYPE = '_ef_template_type';

	/**
	 * Map of `_ef_template_type` meta value → existing post ID, populated
	 * lazily on first call to {@see find_existing()}. Using a single scan
	 * instead of 15 per-template meta_query round-trips during the wizard's
	 * install phase, which is the main hot spot on first-run onboarding.
	 *
	 * `null` means "not yet populated".
	 *
	 * @var array<string, int>|null
	 */
	private ?array $type_map = null;

	/**
	 * Install every template in the catalog. Returns a map of template type
	 * slug → post ID for use in the wizard's success summary.
	 *
	 * @return array<string, int>
	 */
	public function install_all(): array {
		$this->prime_type_map();
		$result = array();
		foreach ( Templates::all() as $spec ) {
			$post_id = $this->install_one( $spec );
			if ( $post_id > 0 ) {
				$result[ $spec->type() ] = $post_id;
			}
		}
		return $result;
	}

	/**
	 * Install or update a single template.
	 */
	public function install_one( TemplateSpec $spec ): int {
		$existing = $this->find_existing( $spec->type() );

		$postarr = array(
			'post_title'   => $spec->title(),
			'post_type'    => 'elementor_library',
			'post_status'  => 'publish',
			'post_content' => '',
		);

		if ( $existing > 0 ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) || 0 === $post_id ) {
			return 0;
		}
		$post_id = (int) $post_id;

		// Write the Elementor tree into _elementor_data using the encoding dance.
		Encoder::write_document( $post_id, $spec->document() );

		// Persist Elementor template flags + our own discovery key.
		update_post_meta( $post_id, self::META_TEMPLATE_TYPE, $spec->type() );
		foreach ( $spec->meta() as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.20.0' );

		CacheClearer::clear( $post_id );

		// Keep the in-memory map coherent for any subsequent install_one calls.
		if ( null !== $this->type_map ) {
			$this->type_map[ $spec->type() ] = $post_id;
		}

		return $post_id;
	}

	/**
	 * Look up an existing template by its Forge type slug. Uses the cached
	 * in-memory map when available (populated by {@see prime_type_map()} or
	 * previous install_one calls); falls back to a single targeted get_posts
	 * call for standalone invocations.
	 */
	public function find_existing( string $type ): int {
		if ( null !== $this->type_map ) {
			return $this->type_map[ $type ] ?? 0;
		}

		$posts = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => array( 'publish', 'draft' ),
				'numberposts'      => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => array(
					array(
						'key'   => self::META_TEMPLATE_TYPE,
						'value' => $type,
					),
				),
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		return self::first_int( $posts );
	}

	/**
	 * Build the in-memory type_map with a single query that fetches every
	 * existing ef_template_type meta row at once. Subsequent find_existing
	 * calls read from the map instead of running their own meta queries.
	 */
	public function prime_type_map(): void {
		$this->type_map = array();

		$posts = get_posts(
			array(
				'post_type'        => 'elementor_library',
				'post_status'      => array( 'publish', 'draft' ),
				'numberposts'      => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => array(
					array(
						'key'     => self::META_TEMPLATE_TYPE,
						'compare' => 'EXISTS',
					),
				),
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		if ( ! is_array( $posts ) ) {
			return;
		}

		foreach ( $posts as $raw_id ) {
			$post_id = self::coerce_int( $raw_id );
			if ( 0 === $post_id ) {
				continue;
			}
			$meta = get_post_meta( $post_id, self::META_TEMPLATE_TYPE, true );
			if ( is_string( $meta ) && '' !== $meta ) {
				$this->type_map[ $meta ] = $post_id;
			}
		}
	}

	/**
	 * @return array<string, int>|null
	 */
	public function type_map(): ?array {
		return $this->type_map;
	}

	/**
	 * @param mixed $posts
	 */
	private static function first_int( $posts ): int {
		if ( ! is_array( $posts ) || empty( $posts ) ) {
			return 0;
		}
		return self::coerce_int( $posts[0] );
	}

	/**
	 * @param mixed $value
	 */
	private static function coerce_int( $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		return 0;
	}
}
