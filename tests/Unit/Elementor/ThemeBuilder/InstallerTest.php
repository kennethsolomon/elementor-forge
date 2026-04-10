<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\ThemeBuilder;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\ThemeBuilder\Installer;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase {

	/** @var int */
	private int $next_id = 100;

	/** @var array<int, array<string, mixed>> */
	private array $meta_store = array();

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

	private function make_spec( string $type = 'ef_test', string $title = 'Test Template' ): TemplateSpec {
		return new TemplateSpec(
			$type,
			$title,
			new Document( $title, 'page' ),
			array( '_elementor_template_type' => 'single-post' )
		);
	}

	private function wire_wp( array $existing_ids = array() ): void {
		$this->meta_store = array();

		Functions\when( 'get_posts' )->alias(
			function ( array $args ) use ( $existing_ids ) {
				$meta_query = $args['meta_query'] ?? array();
				$first      = is_array( $meta_query ) ? ( $meta_query[0] ?? array() ) : array();
				$compare    = $first['compare'] ?? '';
				$key        = $first['key'] ?? '';
				$value      = $first['value'] ?? null;

				$ids = array();
				foreach ( $this->meta_store as $post_id => $meta ) {
					if ( 'EXISTS' === $compare && isset( $meta[ $key ] ) ) {
						$ids[] = $post_id;
					} elseif ( isset( $meta[ $key ] ) && $meta[ $key ] === $value ) {
						$ids[] = $post_id;
					}
				}
				return $ids;
			}
		);

		Functions\when( 'wp_insert_post' )->alias(
			function () {
				$id                      = $this->next_id++;
				$this->meta_store[ $id ] = array();
				return $id;
			}
		);

		Functions\when( 'wp_update_post' )->alias(
			static fn ( array $postarr ) => (int) ( $postarr['ID'] ?? 0 )
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

	public function test_meta_template_type_constant(): void {
		$this->assertSame( '_ef_template_type', Installer::META_TEMPLATE_TYPE );
	}

	public function test_type_map_null_before_prime(): void {
		$installer = new Installer();

		$this->assertNull( $installer->type_map() );
	}

	public function test_prime_type_map_initializes_empty_map(): void {
		$this->wire_wp();
		$installer = new Installer();
		$installer->prime_type_map();

		$this->assertIsArray( $installer->type_map() );
		$this->assertSame( array(), $installer->type_map() );
	}

	public function test_install_one_returns_post_id(): void {
		$this->wire_wp();
		$installer = new Installer();
		$spec      = $this->make_spec( 'ef_test', 'Test' );

		$post_id = $installer->install_one( $spec );

		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_install_one_writes_template_type_meta(): void {
		$this->wire_wp();
		$installer = new Installer();
		$spec      = $this->make_spec( 'ef_custom', 'Custom' );

		$post_id = $installer->install_one( $spec );

		$this->assertSame( 'ef_custom', $this->meta_store[ $post_id ][ Installer::META_TEMPLATE_TYPE ] );
	}

	public function test_install_one_writes_spec_meta(): void {
		$this->wire_wp();
		$installer = new Installer();
		$spec      = $this->make_spec( 'ef_foo', 'Foo' );

		$post_id = $installer->install_one( $spec );

		$this->assertSame( 'single-post', $this->meta_store[ $post_id ]['_elementor_template_type'] );
	}

	public function test_install_one_writes_edit_mode_meta(): void {
		$this->wire_wp();
		$installer = new Installer();
		$spec      = $this->make_spec();

		$post_id = $installer->install_one( $spec );

		$this->assertSame( 'builder', $this->meta_store[ $post_id ]['_elementor_edit_mode'] );
	}

	public function test_install_one_writes_version_meta(): void {
		$this->wire_wp();
		$installer = new Installer();
		$spec      = $this->make_spec();

		$post_id = $installer->install_one( $spec );

		$this->assertArrayHasKey( '_elementor_version', $this->meta_store[ $post_id ] );
	}

	public function test_install_one_returns_zero_on_wp_error(): void {
		$this->wire_wp();
		Functions\when( 'wp_insert_post' )->justReturn( new \WP_Error( 'fail', 'Insert failed' ) );

		$installer = new Installer();
		$post_id   = $installer->install_one( $this->make_spec() );

		$this->assertSame( 0, $post_id );
	}

	public function test_install_one_returns_zero_on_zero_post_id(): void {
		$this->wire_wp();
		Functions\when( 'wp_insert_post' )->justReturn( 0 );

		$installer = new Installer();
		$post_id   = $installer->install_one( $this->make_spec() );

		$this->assertSame( 0, $post_id );
	}

	public function test_install_one_updates_type_map_on_success(): void {
		$this->wire_wp();
		$installer = new Installer();
		$installer->prime_type_map();

		$spec    = $this->make_spec( 'ef_mapped', 'Mapped' );
		$post_id = $installer->install_one( $spec );

		$map = $installer->type_map();
		$this->assertArrayHasKey( 'ef_mapped', $map );
		$this->assertSame( $post_id, $map['ef_mapped'] );
	}

	public function test_find_existing_returns_zero_for_unknown_type(): void {
		$this->wire_wp();
		$installer = new Installer();
		$installer->prime_type_map();

		$this->assertSame( 0, $installer->find_existing( 'nonexistent_type' ) );
	}

	public function test_find_existing_uses_type_map_when_primed(): void {
		$this->wire_wp();
		$installer = new Installer();

		// Install a spec so it's in the meta_store.
		$spec    = $this->make_spec( 'ef_findme', 'Find Me' );
		$post_id = $installer->install_one( $spec );

		// Prime loads from meta_store.
		$installer->prime_type_map();

		$this->assertSame( $post_id, $installer->find_existing( 'ef_findme' ) );
	}

	public function test_find_existing_falls_back_to_get_posts_without_prime(): void {
		$this->wire_wp();
		$installer = new Installer();

		// Install to populate meta_store but don't prime.
		$spec    = $this->make_spec( 'ef_fallback', 'Fallback' );
		$post_id = $installer->install_one( $spec );

		// type_map is null, so find_existing will query via get_posts.
		$this->assertNull( $installer->type_map() );
		$result = $installer->find_existing( 'ef_fallback' );

		$this->assertSame( $post_id, $result );
	}

	public function test_find_existing_returns_zero_for_empty_library(): void {
		$this->wire_wp();
		$installer = new Installer();

		// No prime, empty store.
		$this->assertSame( 0, $installer->find_existing( 'ef_nothing' ) );
	}

	public function test_install_one_updates_existing_post(): void {
		$this->wire_wp();
		$installer = new Installer();

		$spec     = $this->make_spec( 'ef_update', 'Update Me' );
		$first_id = $installer->install_one( $spec );

		// Prime so the second install finds the existing post.
		$installer->prime_type_map();
		$second_id = $installer->install_one( $spec );

		$this->assertSame( $first_id, $second_id );
	}

	public function test_prime_type_map_skips_non_integer_ids(): void {
		$this->wire_wp();
		$installer = new Installer();

		// Manually inject a non-numeric entry into meta_store.
		$this->meta_store['not_an_int'] = array( Installer::META_TEMPLATE_TYPE => 'ef_bad' );

		// get_posts returns whatever is in meta_store keys matching the query.
		// Since our wire_wp uses int keys normally, add a numeric one too.
		$spec    = $this->make_spec( 'ef_good', 'Good' );
		$post_id = $installer->install_one( $spec );

		$installer->prime_type_map();
		$map = $installer->type_map();

		$this->assertArrayHasKey( 'ef_good', $map );
		$this->assertSame( $post_id, $map['ef_good'] );
	}

	public function test_prime_type_map_handles_get_posts_returning_non_array(): void {
		Functions\when( 'get_posts' )->justReturn( false );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$installer = new Installer();
		$installer->prime_type_map();

		$this->assertSame( array(), $installer->type_map() );
	}
}
