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

// $wpdb stub — only declares the surface SliderRepository depends on so unit
// tests can instantiate the repository against a fake. Real WP integration
// tests use the real wpdb via wp-env. Constants ARRAY_A / OBJECT mirror WP.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! class_exists( 'wpdb' ) ) {
	class wpdb { // phpcs:ignore Generic.Classes.OpeningBraceSameLine
		/** @var string */
		public $prefix = 'wp_';
		/** @var string */
		public $last_error = '';
		/** @var int */
		public $insert_id = 0;
		/** @var array<int, array{table:string, data:array, formats:array}> */
		public $inserts = array();
		/** @var array<int, array{table:string, data:array, where:array, formats:array, where_formats:array}> */
		public $updates = array();
		/** @var array<int, array{table:string, where:array, formats:array}> */
		public $deletes = array();
		/** @var array<int, string> */
		public $queries = array();
		/** @var int|false */
		public $next_insert_id = 1;
		/** @var int|false */
		public $insert_return = 1;
		/** @var int|false */
		public $update_return = 1;
		/** @var int|false */
		public $delete_return = 1;
		/** @var array<string, mixed>|null */
		public $row_return = null;
		/** @var array<int, array<string, mixed>>|null */
		public $results_return = null;
		/** @var mixed */
		public $var_return = 0;

		public function prepare( string $query, ...$args ): string {
			return $query . '|' . implode( ',', array_map( 'strval', $args ) );
		}

		public function insert( string $table, array $data, $formats = null ) {
			$this->inserts[] = array( 'table' => $table, 'data' => $data, 'formats' => is_array( $formats ) ? $formats : array() );
			if ( false === $this->insert_return ) {
				return false;
			}
			$this->insert_id = is_int( $this->next_insert_id ) ? $this->next_insert_id : 1;
			++$this->next_insert_id;
			return $this->insert_return;
		}

		public function update( string $table, array $data, array $where, $formats = null, $where_formats = null ) {
			$this->updates[] = array(
				'table'         => $table,
				'data'          => $data,
				'where'         => $where,
				'formats'       => is_array( $formats ) ? $formats : array(),
				'where_formats' => is_array( $where_formats ) ? $where_formats : array(),
			);
			return $this->update_return;
		}

		public function delete( string $table, array $where, $formats = null ) {
			$this->deletes[] = array( 'table' => $table, 'where' => $where, 'formats' => is_array( $formats ) ? $formats : array() );
			return $this->delete_return;
		}

		public function get_row( string $query, $output = ARRAY_A ) {
			$this->queries[] = $query;
			return $this->row_return;
		}

		public function get_results( string $query, $output = ARRAY_A ) {
			$this->queries[] = $query;
			return $this->results_return;
		}

		public function get_var( string $query ) {
			$this->queries[] = $query;
			return $this->var_return;
		}
	}
}
