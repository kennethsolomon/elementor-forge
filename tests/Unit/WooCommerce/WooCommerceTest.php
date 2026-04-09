<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\WooCommerce\WooCommerce;
use PHPUnit\Framework\TestCase;

final class WooCommerceTest extends TestCase {

	/** @var array<string, mixed> */
	private array $option_store = array();

	/** @var int */
	private int $next_insert_id = 4000;

	/** @var array<int, array<string, mixed>> */
	private array $meta_store = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->option_store   = array();
		$this->meta_store     = array();
		$this->next_insert_id = 4000;

		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );

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

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_configure_all_skips_everything_when_wc_missing(): void {
		$result = ( new WooCommerce() )->configure_all();
		$this->assertFalse( $result['wc_active'] );
		$this->assertSame( 'skipped', $result['templates']['status'] );
		$this->assertSame( 'skipped', $result['fibosearch']['status'] );
		$this->assertSame( 'skipped', $result['header']['status'] );
	}

	public function test_configure_all_installs_when_wc_present(): void {
		require_once __DIR__ . '/wc-stub.php';

		$result = ( new WooCommerce() )->configure_all();

		$this->assertTrue( $result['wc_active'] );
		$this->assertSame( 'installed', $result['templates']['status'] );
		$this->assertNotEmpty( $result['templates']['installed'] );
		$this->assertSame( 'installed', $result['header']['status'] );
		$this->assertGreaterThan( 0, $result['header']['post_id'] );
	}

	public function test_report_reflects_wc_presence(): void {
		require_once __DIR__ . '/wc-stub.php';
		$report = ( new WooCommerce() )->report();
		$this->assertTrue( $report['wc_active'] );
		$this->assertSame( 4, $report['wc_templates_total'] );
	}

	public function test_switch_to_ecommerce_header_writes_header_pattern_setting(): void {
		require_once __DIR__ . '/wc-stub.php';

		$post_id = ( new WooCommerce() )->switch_to_ecommerce_header();
		$this->assertGreaterThan( 0, $post_id );

		$settings = $this->option_store['elementor_forge_settings'] ?? array();
		$this->assertSame( 'ecommerce', $settings['header_pattern'] ?? '' );
	}

	public function test_install_templates_is_idempotent(): void {
		require_once __DIR__ . '/wc-stub.php';

		$wc    = new WooCommerce();
		$first = $wc->install_templates();
		$second = $wc->install_templates();

		$this->assertSame( $first, $second );
		$this->assertCount( 4, $first );
	}
}
