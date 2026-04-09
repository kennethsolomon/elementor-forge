<?php
/**
 * Declarative catalog of the plugins Elementor Forge's wizard auto-installs.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Onboarding;

/**
 * Pure data for the wizard's dependency step. Each row captures:
 *
 *   - slug:        wp.org plugin slug (used by `plugins_api`)
 *   - file:        main plugin file, relative to wp-content/plugins/
 *   - label:       human-readable name in the wizard UI
 *   - required:    true = install unconditionally; false = ask the user
 *   - auto_install: true = can be pulled from wp.org; false = needs manual upload
 *   - conditional_on: optional slug — install only if this plugin was chosen earlier
 *
 * Elementor Pro is the only `auto_install = false` entry because it is a paid
 * plugin and not distributed via wp.org; the wizard shows the upload/license
 * dialog instead of running `Plugin_Upgrader::install()` on it.
 */
final class Dependencies {

	/**
	 * Look up an allowlist entry by wp.org slug. Returns null when the slug is
	 * not in the curated catalog — callers MUST treat that as a hard rejection
	 * and refuse the install. Also rejects when the caller-supplied plugin
	 * file path does not match the allowlist row exactly; this prevents a
	 * `manage_options` user from aiming the installer at an arbitrary file
	 * name even for a valid slug.
	 *
	 * @return array{slug:string, file:string, label:string, required:bool, auto_install:bool, conditional_on?:string}|null
	 */
	public static function find( string $slug, string $file ): ?array {
		foreach ( self::all() as $row ) {
			if ( $row['slug'] === $slug && $row['file'] === $file ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @return list<array{slug:string, file:string, label:string, required:bool, auto_install:bool, conditional_on?:string}>
	 */
	public static function all(): array {
		return array(
			array(
				'slug'         => 'elementor',
				'file'         => 'elementor/elementor.php',
				'label'        => 'Elementor',
				'required'     => true,
				'auto_install' => true,
			),
			array(
				'slug'         => 'advanced-custom-fields',
				'file'         => 'advanced-custom-fields/acf.php',
				'label'        => 'Advanced Custom Fields',
				'required'     => true,
				'auto_install' => true,
			),
			array(
				'slug'         => 'contact-form-7',
				'file'         => 'contact-form-7/wp-contact-form-7.php',
				'label'        => 'Contact Form 7',
				'required'     => true,
				'auto_install' => true,
			),
			array(
				'slug'         => 'smart-slider-3',
				'file'         => 'smart-slider-3/smart-slider-3.php',
				'label'        => 'Smart Slider 3',
				'required'     => true,
				'auto_install' => true,
			),
			array(
				'slug'         => 'woocommerce',
				'file'         => 'woocommerce/woocommerce.php',
				'label'        => 'WooCommerce (optional)',
				'required'     => false,
				'auto_install' => true,
			),
			array(
				// wp.org slug is `ajax-search-for-woocommerce` — verified against
				// https://wordpress.org/plugins/ajax-search-for-woocommerce/ and the
				// plugin's own headers. The author-facing name "FiboSearch" is a
				// rebrand; the wp.org slug was never renamed. Using `fibosearch`
				// or `fibo-search` returns a 404 from plugins_api() and the wizard
				// install step silently no-ops.
				'slug'           => 'ajax-search-for-woocommerce',
				'file'           => 'ajax-search-for-woocommerce/ajax-search-for-woocommerce.php',
				'label'          => 'FiboSearch',
				'required'       => false,
				'auto_install'   => true,
				'conditional_on' => 'woocommerce',
			),
			array(
				'slug'         => 'elementor-pro',
				'file'         => 'elementor-pro/elementor-pro.php',
				'label'        => 'Elementor Pro',
				'required'     => true,
				'auto_install' => false,
			),
		);
	}
}
