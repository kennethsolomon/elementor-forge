<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\DuplicateSection;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class DuplicateSectionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Store::flush_cache();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ): int => abs( (int) $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => 'full',
				'safety_allowed_post_ids' => '',
			)
		);
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( static fn ( $data, $options = 0 ) => json_encode( $data, $options ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( $s ) => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'get_the_title' )->justReturn( 'Test Page' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_elementor_data( array $sections ): string {
		return json_encode( $sections, JSON_UNESCAPED_SLASHES );
	}

	private function sample_sections(): array {
		return array(
			array(
				'id'       => 'sec001',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(
					array(
						'id'         => 'wid001',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'Hello' ),
						'elements'   => array(),
						'isInner'    => true,
					),
				),
				'isInner'  => false,
			),
			array(
				'id'       => 'sec002',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(
					array(
						'id'         => 'wid002',
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => 'Some text' ),
						'elements'   => array(),
						'isInner'    => true,
					),
				),
				'isInner'  => false,
			),
			array(
				'id'       => 'sec003',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(),
				'isInner'  => false,
			),
		);
	}

	public function test_execute_returns_error_for_invalid_post_id(): void {
		$result = DuplicateSection::execute( array( 'post_id' => 0, 'section_index' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_missing_post_id(): void {
		$result = DuplicateSection::execute( array( 'section_index' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_section_not_found(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = DuplicateSection::execute( array( 'post_id' => 42, 'section_index' => 99 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_section_not_found', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_section_id_not_found(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = DuplicateSection::execute( array( 'post_id' => 42, 'section_id' => 'bogus' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_section_not_found', $result->get_error_code() );
	}

	public function test_execute_clones_section_with_new_id(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		$result = DuplicateSection::execute( array( 'post_id' => 42, 'section_index' => 0 ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['duplicated'] );

		$decoded = json_decode( $written, true );
		// Original id should still exist.
		$ids = array_column( $decoded, 'id' );
		$this->assertContains( 'sec001', $ids );
		// The clone must have a different id.
		$clone_id = null;
		foreach ( $decoded as $section ) {
			if ( 'sec001' !== $section['id'] && $section['elType'] === 'container' && isset( $section['elements'] ) ) {
				// Likely the clone — check it is not identical to the original.
				if ( $section['id'] !== 'sec002' && $section['id'] !== 'sec003' ) {
					$clone_id = $section['id'];
				}
			}
		}
		$this->assertNotNull( $clone_id );
		$this->assertNotSame( 'sec001', $clone_id );
	}

	public function test_execute_inserts_clone_after_original_by_default(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		$result = DuplicateSection::execute( array( 'post_id' => 42, 'section_index' => 0 ) );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['new_index'] );

		$decoded = json_decode( $written, true );
		// index 0 = original, index 1 = clone (not sec002).
		$this->assertSame( 'sec001', $decoded[0]['id'] );
		$this->assertNotSame( 'sec002', $decoded[1]['id'] );
		$this->assertSame( 'sec002', $decoded[2]['id'] );
	}

	public function test_execute_inserts_at_custom_insert_after_position(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		// Duplicate sec001 (index 0) but insert after index 2 (the last).
		$result = DuplicateSection::execute(
			array(
				'post_id'       => 42,
				'section_index' => 0,
				'insert_after'  => 2,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 3, $result['new_index'] );

		$decoded = json_decode( $written, true );
		$this->assertCount( 4, $decoded );
		// Clone is at the end.
		$this->assertNotSame( 'sec001', $decoded[3]['id'] );
		$this->assertNotSame( 'sec002', $decoded[3]['id'] );
		$this->assertNotSame( 'sec003', $decoded[3]['id'] );
	}

	public function test_execute_deeply_clones_nested_element_ids(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		DuplicateSection::execute( array( 'post_id' => 42, 'section_index' => 0 ) );

		$decoded  = json_decode( $written, true );
		$original = $decoded[0]; // sec001
		$clone    = $decoded[1]; // the duplicate inserted at index 1

		// Top-level IDs must differ.
		$this->assertNotSame( $original['id'], $clone['id'] );

		// Nested widget IDs must also differ.
		$original_child_id = $original['elements'][0]['id'] ?? null;
		$clone_child_id    = $clone['elements'][0]['id'] ?? null;

		$this->assertNotNull( $original_child_id );
		$this->assertNotNull( $clone_child_id );
		$this->assertNotSame( $original_child_id, $clone_child_id );
	}

	public function test_content_has_one_more_section_after_duplication(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		DuplicateSection::execute( array( 'post_id' => 42, 'section_index' => 1 ) );

		$decoded = json_decode( $written, true );
		$this->assertCount( 4, $decoded );
	}

	public function test_execute_respects_gate_in_read_only_mode(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::READ_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = DuplicateSection::execute( array( 'post_id' => 42, 'section_index' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_execute_respects_gate_in_page_only_mode_with_non_allowlisted_post(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::PAGE_ONLY,
				'safety_allowed_post_ids' => '52',
			)
		);

		$result = DuplicateSection::execute( array( 'post_id' => 99, 'section_index' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}
}
