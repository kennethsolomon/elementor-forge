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
 * Idempotency contract for the WC Installer — mirror of the Phase 1 base
 * installer test but walking the WooCommerce template catalog.
 */
final class InstallerIdempotencyTest extends TestCase {

	/** @var int */
	private int $next_insert_id = 2000;

	/** @var array<int, array<string, mixed>> */
	private array $meta_store = array();

	/** @var int */
	private int $insert_calls = 0;

	/** @var int */
	private int $update_calls = 0;

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
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_first_run_inserts_every_wc_template_once(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$result    = $installer->install_all();

		$this->assertCount( count( Templates::all() ), $result );
		$this->assertSame( count( Templates::all() ), $this->insert_calls );
		$this->assertSame( 0, $this->update_calls );

		$expected_types = array(
			Templates::TEMPLATE_TYPE_SHOP_ARCHIVE,
			Templates::TEMPLATE_TYPE_SINGLE_PRODUCT,
			Templates::TEMPLATE_TYPE_CART,
			Templates::TEMPLATE_TYPE_CHECKOUT,
		);
		foreach ( $expected_types as $type ) {
			$this->assertArrayHasKey( $type, $result );
			$post_id = $result[ $type ];
			$this->assertSame(
				$type,
				$this->meta_store[ $post_id ][ BaseInstaller::META_TEMPLATE_TYPE ] ?? null
			);
		}
	}

	public function test_second_run_updates_instead_of_inserting(): void {
		$this->wire_fake_wordpress();

		$first_ids = ( new Installer() )->install_all();

		$insert_after_first = $this->insert_calls;
		$update_after_first = $this->update_calls;

		$second_ids = ( new Installer() )->install_all();

		$insert_delta = $this->insert_calls - $insert_after_first;
		$update_delta = $this->update_calls - $update_after_first;

		$this->assertSame( 0, $insert_delta, 'Second run must not insert new posts.' );
		$this->assertSame( count( Templates::all() ), $update_delta );
		$this->assertSame( $first_ids, $second_ids );
	}

	public function test_install_all_scans_library_only_once_per_run(): void {
		$this->wire_fake_wordpress();
		$this->get_posts_calls = 0;

		( new Installer() )->install_all();

		$this->assertSame( 1, $this->get_posts_calls );
	}

	public function test_existing_returns_empty_when_nothing_installed(): void {
		$this->wire_fake_wordpress();
		$installer = new Installer();
		$this->assertSame( array(), $installer->existing() );
		$this->assertFalse( $installer->is_fully_installed() );
	}

	public function test_existing_returns_all_after_install(): void {
		$this->wire_fake_wordpress();

		$installer = new Installer();
		$installer->install_all();

		$existing = ( new Installer() )->existing();
		$this->assertCount( count( Templates::all() ), $existing );
		$this->assertTrue( ( new Installer() )->is_fully_installed() );
	}

	private function wire_fake_wordpress(): void {
		$this->meta_store = array();

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
			function ( array $postarr ) {
				++$this->insert_calls;
				$post_id                      = $this->next_insert_id++;
				$this->meta_store[ $post_id ] = array( '_ef_title' => (string) ( $postarr['post_title'] ?? '' ) );
				return $post_id;
			}
		);

		Functions\when( 'wp_update_post' )->alias(
			function ( array $postarr ) {
				++$this->update_calls;
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
}
