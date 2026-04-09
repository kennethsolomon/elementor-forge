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

// Unit mode — declare only the classes Brain\Monkey cannot stub. Function
// stubs live inside individual test classes via Brain\Monkey's Functions\when().
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		public $code = '';
		/** @var string */
		public $message = '';
		/** @var mixed */
		public $data;
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_message(): string {
			return $this->message;
		}
		public function get_error_code(): string {
			return $this->code;
		}
	}
}
