<?php
/**
 * PHPStan bootstrap — defines plugin constants so static analysis doesn't flag them.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

define( 'ELEMENTOR_FORGE_VERSION', '0.1.0' );
define( 'ELEMENTOR_FORGE_PLUGIN_FILE', __DIR__ . '/../elementor-forge.php' );
define( 'ELEMENTOR_FORGE_PLUGIN_DIR', __DIR__ . '/../' );
define( 'ELEMENTOR_FORGE_PLUGIN_URL', 'https://example.test/wp-content/plugins/elementor-forge/' );
define( 'ELEMENTOR_FORGE_MIN_PHP', '8.0' );
define( 'ELEMENTOR_FORGE_MIN_WP', '6.4' );
define( 'ELEMENTOR_FORGE_MIN_ELEMENTOR', '3.20.0' );
