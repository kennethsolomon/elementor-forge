<?php
/**
 * Installer that materializes TemplateSpecs into elementor_library CPT posts.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\ThemeBuilder;

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
	 * Install every template in the catalog. Returns a map of template type
	 * slug → post ID for use in the wizard's success summary.
	 *
	 * @return array<string, int>
	 */
	public function install_all(): array {
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

		return $post_id;
	}

	/**
	 * Look up an existing template by its Forge type slug.
	 */
	public function find_existing( string $type ): int {
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

		if ( ! is_array( $posts ) || empty( $posts ) ) {
			return 0;
		}
		$first = $posts[0];
		if ( is_int( $first ) ) {
			return $first;
		}
		if ( is_numeric( $first ) ) {
			return (int) $first;
		}
		return 0;
	}
}
