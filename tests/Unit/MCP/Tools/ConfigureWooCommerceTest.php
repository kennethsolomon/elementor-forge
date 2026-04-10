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
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
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

	/**
	 * Regression guard for the MCP idempotency invariant: calling execute()
	 * twice in a row must not create duplicate posts, must not re-apply
	 * Fibosearch defaults that are already present, and must return the
	 * same header post id both times. Covers the Testa HIGH finding from
	 * the Phase 2 Layer 2 review.
	 */
	public function test_execute_twice_is_idempotent(): void {
		require_once __DIR__ . '/../../WooCommerce/wc-stub.php';

		// Fibosearch presence stub — Configurator::is_available() feature-detects
		// via function_exists('dgwt_wcas'), so we declare it via Brain Monkey to
		// exercise the "Fibosearch present" code path on both runs.
		if ( ! function_exists( 'dgwt_wcas' ) ) {
			Functions\when( 'dgwt_wcas' )->justReturn( null );
		}

		// First run — fresh install.
		$insert_id_before_first = $this->next_insert_id;
		$first                  = ConfigureWooCommerce::execute( array() );
		$inserts_on_first_run   = $this->next_insert_id - $insert_id_before_first;

		$this->assertIsArray( $first );
		$this->assertSame( 'installed', $first['templates']['status'] );
		$this->assertNotEmpty( $first['templates']['installed'] );
		$this->assertSame( 'applied', $first['fibosearch']['status'] );
		$this->assertNotEmpty( $first['fibosearch']['keys_updated'] );
		$this->assertSame( 'installed', $first['header']['status'] );
		$this->assertGreaterThan( 0, $first['header']['post_id'] );
		$this->assertGreaterThan( 0, $inserts_on_first_run, 'First run must insert at least one post.' );

		$first_template_ids = $first['templates']['installed'];
		$first_header_id    = $first['header']['post_id'];

		// Second run — everything already in place.
		$insert_id_before_second = $this->next_insert_id;
		$second                  = ConfigureWooCommerce::execute( array() );
		$inserts_on_second_run   = $this->next_insert_id - $insert_id_before_second;

		// ZERO new inserts — this is the idempotency contract.
		$this->assertSame(
			0,
			$inserts_on_second_run,
			'Second run must not create duplicate posts — insert delta was ' . $inserts_on_second_run . '.'
		);

		// Templates: same post ids, no duplicates.
		$this->assertSame(
			$first_template_ids,
			$second['templates']['installed'],
			'Second run must return the same template post ids as the first run.'
		);

		// Fibosearch: all default keys already present → keys_updated is empty.
		$this->assertSame( 'applied', $second['fibosearch']['status'] );
		$this->assertSame(
			array(),
			$second['fibosearch']['keys_updated'],
			'Second run must not rewrite any Fibosearch keys — all defaults already present.'
		);
		$this->assertNotEmpty(
			$second['fibosearch']['keys_preserved'],
			'Second run must report all default keys as preserved.'
		);

		// Header: same post id, slot reused.
		$this->assertSame(
			$first_header_id,
			$second['header']['post_id'],
			'Second run must return the same header post id as the first run (slot reused).'
		);
	}
}
