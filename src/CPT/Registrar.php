<?php
/**
 * Custom post type registrar.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\CPT;

/**
 * Wires the CPT catalog into WordPress on `init`. Kept as a thin hook-shim
 * around the pure data in {@see PostTypes} so the data can be tested without
 * WordPress loaded.
 */
final class Registrar {

	/**
	 * Register the `init` action. Intended to be called once from the plugin
	 * bootstrap after feature detects pass.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_all' ), 0 );
	}

	/**
	 * Register every CPT declared in {@see PostTypes::all()}.
	 */
	public function register_all(): void {
		foreach ( PostTypes::all() as $slug => $args ) {
			register_post_type( $slug, $args );
		}
	}
}
