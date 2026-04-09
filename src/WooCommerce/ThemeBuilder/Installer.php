<?php
/**
 * WooCommerce Theme Builder template installer.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\WooCommerce\ThemeBuilder;

use ElementorForge\Elementor\ThemeBuilder\Installer as BaseInstaller;

/**
 * Materializes the four WooCommerce Theme Builder {@see Templates} as
 * `elementor_library` post-type entries. Delegates the actual wp_insert_post /
 * wp_update_post + `_elementor_data` encoding dance to the Phase 1
 * {@see BaseInstaller}, so both installers use the same idempotency protocol
 * (the `_ef_template_type` meta key, the single prime_type_map scan, and the
 * in-memory type cache).
 *
 * Pure idempotency contract, identical to the Phase 1 installer:
 *
 *   - First run  → four wp_insert_post calls, no wp_update_post calls.
 *   - Second run → four wp_update_post calls, no wp_insert_post calls.
 *   - Two runs produce the same four post IDs.
 *   - get_posts is called exactly once per install_all invocation.
 *
 * The class is intentionally gateless — the caller must check
 * `class_exists('WooCommerce')` before invoking it. That keeps the unit tests
 * usable without a WooCommerce bootstrap and keeps the gate in one place
 * (the plugin bootstrap + the MCP configure_woocommerce tool).
 */
final class Installer {

	/** @var BaseInstaller */
	private BaseInstaller $base;

	public function __construct( ?BaseInstaller $base = null ) {
		$this->base = $base ?? new BaseInstaller();
	}

	/**
	 * Install every WooCommerce Theme Builder template. Returns a map of
	 * template type slug → post ID for the settings page and the
	 * configure_woocommerce MCP tool result payload.
	 *
	 * @return array<string, int>
	 */
	public function install_all(): array {
		$this->base->prime_type_map();
		$result = array();
		foreach ( Templates::all() as $spec ) {
			$post_id = $this->base->install_one( $spec );
			if ( $post_id > 0 ) {
				$result[ $spec->type() ] = $post_id;
			}
		}
		return $result;
	}

	/**
	 * Return the current install map without creating anything. Used by the
	 * settings page debug panel so the admin can see which WC templates are
	 * already registered before running the installer again.
	 *
	 * @return array<string, int>
	 */
	public function existing(): array {
		$this->base->prime_type_map();
		$existing = array();
		foreach ( Templates::all() as $spec ) {
			$id = $this->base->find_existing( $spec->type() );
			if ( $id > 0 ) {
				$existing[ $spec->type() ] = $id;
			}
		}
		return $existing;
	}

	/**
	 * Whether every WC template in the catalog is already installed.
	 */
	public function is_fully_installed(): bool {
		return count( $this->existing() ) === count( Templates::all() );
	}
}
