<?php
/**
 * Plugin Name:       Elementor Forge
 * Plugin URI:        https://github.com/kennethsolomon/elementor-forge
 * Description:       Emitter, CPTs, Theme Builder, WooCommerce, Intelligence Layer, Smart Slider 3 CRUD, bulk generation, MCP server. Turns structured content docs into fully-built Elementor Pro pages and exposes the builder as an MCP tool surface for Claude Code.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Kenneth Solomon
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elementor-forge
 * Domain Path:       /languages
 *
 * @package ElementorForge
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'ELEMENTOR_FORGE_VERSION', '0.4.0' );
define( 'ELEMENTOR_FORGE_PLUGIN_FILE', __FILE__ );
define( 'ELEMENTOR_FORGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTOR_FORGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELEMENTOR_FORGE_MIN_PHP', '8.0' );
define( 'ELEMENTOR_FORGE_MIN_WP', '6.4' );
define( 'ELEMENTOR_FORGE_MIN_ELEMENTOR', '3.20.0' );

// PHP version gate — fail fast, no partial load.
if ( version_compare( PHP_VERSION, ELEMENTOR_FORGE_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Elementor Forge requires PHP 8.0 or higher.', 'elementor-forge' )
			);
		}
	);
	return;
}

// Composer autoload — required, not optional.
$autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Elementor Forge: composer dependencies are missing. Run composer install.', 'elementor-forge' )
			);
		}
	);
	return;
}
require_once $autoload;

/**
 * Bootstrap the plugin on plugins_loaded so Elementor / ACF / WC feature detects run against loaded classes.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\ElementorForge\Plugin::instance()->boot();
	},
	20
);

// Activation / deactivation — tiny shells only, all heavy lifting lives in classes.
register_activation_hook(
	__FILE__,
	static function (): void {
		\ElementorForge\Lifecycle\Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		\ElementorForge\Lifecycle\Deactivator::deactivate();
	}
);
