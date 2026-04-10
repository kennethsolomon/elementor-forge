<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\ThemeBuilder;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\ThemeBuilder\Installer;
use ElementorForge\Elementor\ThemeBuilder\Templates;
use PHPUnit\Framework\TestCase;

/**
 * Idempotency contract for {@see Installer}:
 *
 *   1. First run with an empty library calls wp_insert_post for every spec.
 *   2. Second run with the library populated calls wp_update_post for every
 *      spec and never calls wp_insert_post.
 *   3. The `_ef_template_type` meta key survives the round trip so the map
 *      lookup continues to work after a reinstall.
 *   4. install_all primes the type_map with a single get_posts scan instead
 *      of one meta_query per template.
 */
final class InstallerIdempotencyTest extends TestCase {

	/** @var int */
	private int $next_insert_id = 1000;

	/** @var array<int, array{title:string, type:string}> */
	private array $inserted = array();

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

		// Encoder shims — re-used from EncoderTest.
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

	public function test_first_run_inserts_every_template_once(): void {
		$this->wire_fake_wordpress( array() );

		$installer = new Installer();
		$result    = $installer->install_all();

		$this->assertCount( count( Templates::all() ), $result );
		$this->assertSame( count( Templates::all() ), $this->insert_calls );
		$this->assertSame( 0, $this->update_calls );

		// Every known Theme Builder type landed in the store with the right
		// `_ef_template_type` meta row.
		$expected_types = array(
			Templates::TEMPLATE_TYPE_LOCATION_SINGLE,
			Templates::TEMPLATE_TYPE_SERVICE_SINGLE,
			Templates::TEMPLATE_TYPE_HEADER,
			Templates::TEMPLATE_TYPE_FOOTER,
		);
		foreach ( $expected_types as $type ) {
			$this->assertArrayHasKey( $type, $result );
			$post_id = $result[ $type ];
			$this->assertSame(
				$type,
				$this->meta_store[ $post_id ][ Installer::META_TEMPLATE_TYPE ] ?? null,
				'Expected _ef_template_type meta to match spec type for post ' . $post_id
			);
		}
	}

	public function test_second_run_updates_instead_of_inserting(): void {
		// First pass: populate the store.
		$this->wire_fake_wordpress( array() );
		$first = ( new Installer() )->install_all();

		$insert_after_first = $this->insert_calls;
		$update_after_first = $this->update_calls;

		// Second pass: the meta_store is already populated so the installer
		// MUST find the existing post IDs via prime_type_map and call
		// wp_update_post instead of wp_insert_post for every template.
		$second = ( new Installer() )->install_all();

		$insert_delta = $this->insert_calls - $insert_after_first;
		$update_delta = $this->update_calls - $update_after_first;

		$this->assertSame( 0, $insert_delta, 'Second run must never insert new posts.' );
		$this->assertSame( count( Templates::all() ), $update_delta );

		// And the post IDs must be stable across reinstalls.
		$this->assertSame( $first, $second );
	}

	public function test_second_run_does_not_create_duplicate_library_posts(): void {
		$this->wire_fake_wordpress( array() );
		( new Installer() )->install_all();
		( new Installer() )->install_all();

		// Count unique _ef_template_type values in the meta_store.
		$types = array();
		foreach ( $this->meta_store as $meta ) {
			if ( isset( $meta[ Installer::META_TEMPLATE_TYPE ] ) ) {
				$types[] = $meta[ Installer::META_TEMPLATE_TYPE ];
			}
		}
		$unique = array_unique( $types );

		$this->assertCount(
			count( $types ),
			$unique,
			'Every template must live in exactly one library post after two install_all runs.'
		);
		$this->assertCount( count( Templates::all() ), $unique );
	}

	public function test_install_all_scans_library_only_once_per_run(): void {
		$this->wire_fake_wordpress( array() );
		$this->get_posts_calls = 0;

		( new Installer() )->install_all();

		$this->assertSame(
			1,
			$this->get_posts_calls,
			'install_all must call get_posts exactly once (prime_type_map) — one query, not one per template.'
		);
	}

	public function test_install_one_section_template_round_trips_meta_key(): void {
		$this->wire_fake_wordpress( array() );

		$installer = new Installer();
		$specs     = Templates::all();
		$first     = $specs[0];

		$post_id = $installer->install_one( $first );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( $first->type(), $this->meta_store[ $post_id ][ Installer::META_TEMPLATE_TYPE ] );

		// Second run against same installer instance (same prime cache) updates.
		$inserts_before = $this->insert_calls;
		$updates_before = $this->update_calls;
		$installer->prime_type_map();
		$second_id = $installer->install_one( $first );

		$this->assertSame( $post_id, $second_id );
		$this->assertSame( $inserts_before, $this->insert_calls );
		$this->assertSame( $updates_before + 1, $this->update_calls );
	}

	/**
	 * Install a fake WordPress surface area behind Brain Monkey. Keeps a
	 * simple in-memory post store so the Installer can walk its idempotency
	 * path without a real database.
	 *
	 * @param array<int, array<string, mixed>> $initial_meta
	 */
	private function wire_fake_wordpress( array $initial_meta ): void {
		$this->meta_store = $initial_meta;

		Functions\when( 'get_posts' )->alias(
			function ( array $args ) {
				++$this->get_posts_calls;
				$ids = array();
				// prime_type_map passes `compare => EXISTS`; find_existing (pre-prime
				// fallback) passes a specific key/value pair.
				$meta_query = $args['meta_query'] ?? array();
				$first      = is_array( $meta_query ) ? ( $meta_query[0] ?? array() ) : array();

				$compare = is_array( $first ) ? ( $first['compare'] ?? '' ) : '';
				$key     = is_array( $first ) ? ( $first['key'] ?? '' ) : '';
				$value   = is_array( $first ) ? ( $first['value'] ?? null ) : null;

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
				$this->inserted[ $post_id ]   = array( 'title' => (string) ( $postarr['post_title'] ?? '' ), 'type' => (string) ( $postarr['post_type'] ?? '' ) );
				$this->meta_store[ $post_id ] = array();
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
