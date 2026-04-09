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
				'slug'           => 'fibosearch',
				'file'           => 'fibosearch/fibosearch.php',
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
