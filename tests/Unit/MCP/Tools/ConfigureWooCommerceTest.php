<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ConfigureWooCommerce;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ConfigureWooCommerceTest extends TestCase {

	/** @var array<string, mixed> */
	private array $option_store = array();

	/** @var int */
	private int $next_insert_id = 3000;

	/** @var array<int, array<string, mixed>> */
	private array $meta_store = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->option_store   = array();
		$this->meta_store     = array();
		$this->next_insert_id = 3000;

		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'current_user_can' )->justReturn( true );

		Functions\when( 'get_option' )->alias(
			function ( string $key, $fallback = false ) {
				return array_key_exists( $key, $this->option_store ) ? $this->option_store[ $key ] : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, $value ) {
				$this->option_store[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'wp_json_encode' )->alias(
			static fn ( $data, int $options = 0, int $depth = 512 ) => json_encode( $data, $options, $depth )
		);
		Functions\when( 'wp_slash' )->alias(
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return array_map( 'wp_slash', $value );
				}
				return is_string( $value ) ? addslashes( $value ) : $value;
			}
		);

		Functions\when( 'get_posts' )->alias(
			function ( array $args ) {
				$ids        = array();
				$meta_query = $args['meta_query'] ?? array();
				$first      = is_array( $meta_query ) ? ( $meta_query[0] ?? array() ) : array();
				$compare    = is_array( $first ) ? ( $first['compare'] ?? '' ) : '';
				$key        = is_array( $first ) ? ( $first['key'] ?? '' ) : '';
				$value      = is_array( $first ) ? ( $first['value'] ?? null ) : null;

				foreach ( $this->meta_store as $post_id => $meta ) {
					if ( 'EXISTS' === $compare ) {
						if ( isset( $meta[ $key ] ) ) {
							$ids[] = $post_id;
						}
					} elseif ( isset( $meta[ $key ] ) && $meta[ $key ] === $value ) {
						$ids[] = $post_id;
					}
				}
				return $ids;
			}
		);

		Functions\when( 'wp_insert_post' )->alias(
			function ( array $postarr ) {
				$post_id                      = $this->next_insert_id++;
				$this->meta_store[ $post_id ] = array( '_ef_title' => (string) ( $postarr['post_title'] ?? '' ) );
				return $post_id;
			}
		);
		Functions\when( 'wp_update_post' )->alias(
			static fn ( array $postarr ): int => (int) ( $postarr['ID'] ?? 0 )
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, $value ) {
				if ( ! isset( $this->meta_store[ $post_id ] ) ) {
					$this->meta_store[ $post_id ] = array();
				}
				$this->meta_store[ $post_id ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			function ( int $post_id, string $key = '' ) {
				if ( '' === $key ) {
					return $this->meta_store[ $post_id ] ?? array();
				}
				return $this->meta_store[ $post_id ][ $key ] ?? '';
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_input_schema_is_valid_object(): void {
		$schema = ConfigureWooCommerce::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'install_templates', $schema['properties'] );
		$this->assertArrayHasKey( 'apply_fibosearch', $schema['properties'] );
		$this->assertArrayHasKey( 'switch_header', $schema['properties'] );
	}

	public function test_output_schema_exposes_expected_keys(): void {
		$schema = ConfigureWooCommerce::output_schema();
		$this->assertArrayHasKey( 'wc_active', $schema['properties'] );
		$this->assertArrayHasKey( 'templates', $schema['properties'] );
		$this->assertArrayHasKey( 'fibosearch', $schema['properties'] );
		$this->assertArrayHasKey( 'header', $schema['properties'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_execute_returns_wp_error_when_wc_missing(): void {
		// Deliberately do not load wc-stub.php — wc-less execution path.
		$result = ConfigureWooCommerce::execute( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_wc_missing', $result->get_error_code() );
	}

	public function test_execute_with_wc_returns_structured_report(): void {
		require_once __DIR__ . '/../../WooCommerce/wc-stub.php';

		$result = ConfigureWooCommerce::execute( array() );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['wc_active'] );
		$this->assertArrayHasKey( 'templates', $result );
		$this->assertArrayHasKey( 'fibosearch', $result );
		$this->assertArrayHasKey( 'header', $result );
		$this->assertSame( 'installed', $result['templates']['status'] );
		$this->assertNotEmpty( $result['templates']['installed'] );
	}

	public function test_execute_respects_install_templates_false_flag(): void {
		require_once __DIR__ . '/../../WooCommerce/wc-stub.php';

		$result = ConfigureWooCommerce::execute(
			array(
				'install_templates' => false,
				'apply_fibosearch'  => false,
				'switch_header'     => false,
			)
		);

		$this->assertSame( 'skipped', $result['templates']['status'] );
		$this->assertSame( 'skipped', $result['fibosearch']['status'] );
		$this->assertSame( 'skipped', $result['header']['status'] );
	}

	public function test_permission_falls_back_to_manage_options(): void {
		// current_user_can is stubbed to return true in setUp.
		$this->assertTrue( ConfigureWooCommerce::permission() );
	}
}
