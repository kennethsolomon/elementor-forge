<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\WooCommerce\ThemeBuilder;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\ThemeBuilder\Installer as BaseInstaller;
use ElementorForge\WooCommerce\ThemeBuilder\Installer;
use ElementorForge\WooCommerce\ThemeBuilder\Templates;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the WooCommerce ThemeBuilder Installer — focuses on the
 * delegation contract (prime_type_map, install_one, find_existing) rather
 * than idempotency (covered by InstallerIdempotencyTest).
 *
 * Since BaseInstaller is final and cannot be extended or mocked via
 * Doctrine Instantiator on PHP 8.0, we stub the WP functions it calls
 * and let it execute against the fakes.
 */
final class InstallerTest extends TestCase {

	/** @var int */
	private int $next_insert_id = 500;

	/** @var array<int, array<string, mixed>> */
	private array $meta_store = array();

	/** @var int */
	private int $insert_calls = 0;

	/** @var int */
	private int $get_posts_calls = 0;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

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
		Functions\when( 'is_wp_error' )->alias(
			static fn ( $thing ): bool => $thing instanceof \WP_Error
		);
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_install_all_returns_type_to_post_id_map(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$result    = $installer->install_all();

		$this->assertCount( count( Templates::all() ), $result );
		foreach ( $result as $type => $post_id ) {
			$this->assertIsString( $type );
			$this->assertGreaterThan( 0, $post_id );
		}
	}

	public function test_install_all_installs_all_four_wc_template_types(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$result    = $installer->install_all();

		$this->assertArrayHasKey( Templates::TEMPLATE_TYPE_SHOP_ARCHIVE, $result );
		$this->assertArrayHasKey( Templates::TEMPLATE_TYPE_SINGLE_PRODUCT, $result );
		$this->assertArrayHasKey( Templates::TEMPLATE_TYPE_CART, $result );
		$this->assertArrayHasKey( Templates::TEMPLATE_TYPE_CHECKOUT, $result );
	}

	public function test_install_all_calls_insert_for_each_template_on_fresh_install(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$installer->install_all();

		$this->assertSame( count( Templates::all() ), $this->insert_calls );
	}

	public function test_install_all_skips_zero_post_ids_from_result(): void {
		$this->wire_fake_wordpress_with_insert_failure_on_first();

		$installer = new Installer();
		$result    = $installer->install_all();

		// One template fails (returns 0) — excluded from the result map.
		$this->assertCount( count( Templates::all() ) - 1, $result );
		$first_type = Templates::all()[0]->type();
		$this->assertArrayNotHasKey( $first_type, $result );
	}

	public function test_existing_returns_empty_map_when_nothing_installed(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$existing  = $installer->existing();

		$this->assertSame( array(), $existing );
	}

	public function test_existing_returns_installed_templates(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$installer->install_all();

		$existing = ( new Installer() )->existing();

		$this->assertCount( count( Templates::all() ), $existing );
		foreach ( Templates::all() as $spec ) {
			$this->assertArrayHasKey( $spec->type(), $existing );
			$this->assertGreaterThan( 0, $existing[ $spec->type() ] );
		}
	}

	public function test_is_fully_installed_returns_false_when_nothing_installed(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$this->assertFalse( $installer->is_fully_installed() );
	}

	public function test_is_fully_installed_returns_true_after_full_install(): void {
		$this->wire_fake_wordpress();

		( new Installer() )->install_all();
		$this->assertTrue( ( new Installer() )->is_fully_installed() );
	}

	public function test_constructor_defaults_to_new_base_installer(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$this->assertInstanceOf( Installer::class, $installer );
	}

	public function test_constructor_accepts_explicit_base_installer(): void {
		$base      = new BaseInstaller();
		$installer = new Installer( $base );
		$this->assertInstanceOf( Installer::class, $installer );
	}

	private function wire_fake_wordpress(): void {
		$this->meta_store     = array();
		$this->insert_calls   = 0;
		$this->get_posts_calls = 0;

		Functions\when( 'get_posts' )->alias(
			function ( array $args ) {
				++$this->get_posts_calls;
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
			function ( array $_postarr ) {
				++$this->insert_calls;
				$post_id                      = $this->next_insert_id++;
				$this->meta_store[ $post_id ] = array();
				return $post_id;
			}
		);

		Functions\when( 'wp_update_post' )->alias(
			function ( array $postarr ) {
				return (int) ( $postarr['ID'] ?? 0 );
			}
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

	private function wire_fake_wordpress_with_insert_failure_on_first(): void {
		$this->wire_fake_wordpress();

		$first_type   = Templates::all()[0]->type();
		$call_index   = 0;

		// Override wp_insert_post to return WP_Error for the first call.
		Functions\when( 'wp_insert_post' )->alias(
			function ( array $_postarr ) use ( &$call_index, $first_type ) {
				++$this->insert_calls;
				++$call_index;
				if ( 1 === $call_index ) {
					return new \WP_Error( 'insert_failed', 'Simulated failure' );
				}
				$post_id                      = $this->next_insert_id++;
				$this->meta_store[ $post_id ] = array();
				return $post_id;
			}
		);
	}
}
