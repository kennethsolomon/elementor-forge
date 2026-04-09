<?php
/**
 * PHPUnit bootstrap. Supports two modes:
 *
 *   - Unit suite: loads Composer autoload + Brain\Monkey so pure classes can be tested
 *     without WP loaded.
 *   - Integration suite: loads WordPress core test bootstrap via the WP_PHPUNIT__DIR env
 *     var set by wp-env tests-cli.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

// Always load Composer autoload first so PSR-4 class discovery works.
$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $composer_autoload ) ) {
	fwrite( STDERR, "Composer autoload missing — run `composer install` first.\n" );
	exit( 1 );
}
require_once $composer_autoload;

// Detect mode. Integration runs under wp-env tests-cli which sets WP_TESTS_DIR or WP_PHPUNIT__DIR.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( false === $wp_tests_dir || '' === $wp_tests_dir ) {
	$wp_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( false !== $wp_tests_dir && '' !== $wp_tests_dir && is_dir( $wp_tests_dir ) ) {
	// Integration mode — load WP test framework.
	require_once $wp_tests_dir . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			require dirname( __DIR__ ) . '/elementor-forge.php';
		}
	);

	require $wp_tests_dir . '/includes/bootstrap.php';
}
// Unit mode (no WP_TESTS_DIR set) — Composer autoload + Brain\Monkey is all the tests need.
// Individual tests call Brain\Monkey\setUp() / tearDown() in their own setUp/tearDown hooks.
