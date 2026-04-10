<?php
/**
 * Elementor cache clearing utility.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor;

/**
 * Clears Elementor's multi-layer cache after any programmatic write to
 * `_elementor_data`. Without this, the front-end renders stale content
 * until the user manually clears the cache via the Elementor dashboard.
 *
 * Three cache layers must be invalidated:
 *   1. Global CSS — compiled stylesheet cache managed by files_manager.
 *   2. Per-post CSS — individual post CSS file reference in `_elementor_css` meta.
 *   3. Theme Builder conditions — cached condition-to-template mapping in
 *      `elementor_pro_theme_builder_conditions_cache` option.
 */
final class CacheClearer {

	/**
	 * Clear all Elementor cache layers for the given post.
	 *
	 * @param int $post_id The post whose cache should be cleared. Pass 0 for
	 *                     global-only clearing (e.g. after template installs).
	 */
	public static function clear( int $post_id = 0 ): void {
		// 1. Global CSS cache.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$instance = \Elementor\Plugin::$instance; // @phpstan-ignore-line
			if ( null !== $instance && isset( $instance->files_manager ) ) {
				$instance->files_manager->clear_cache(); // @phpstan-ignore-line
			}
		}

		// 2. Per-post CSS file reference.
		if ( $post_id > 0 ) {
			delete_post_meta( $post_id, '_elementor_css' );
		}

		// 3. Theme Builder conditions cache (Elementor Pro).
		delete_option( 'elementor_pro_theme_builder_conditions_cache' );
	}
}
