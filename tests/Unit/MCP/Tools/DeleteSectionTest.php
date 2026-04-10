<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\DeleteSection;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class DeleteSectionTest extends TestCase {

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
		$result = DeleteSection::execute( array( 'post_id' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_missing_post_id(): void {
		$result = DeleteSection::execute( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_deletes_section_at_correct_index(): void {
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

		$result = DeleteSection::execute( array( 'post_id' => 42, 'section_index' => 1 ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertNotNull( $written );
		$decoded = json_decode( $written, true );
		$this->assertCount( 2, $decoded );
		// sec002 (index 1) should be gone; sec001 and sec003 remain.
		$ids = array_column( $decoded, 'id' );
		$this->assertContains( 'sec001', $ids );
		$this->assertContains( 'sec003', $ids );
		$this->assertNotContains( 'sec002', $ids );
	}

	public function test_execute_deletes_section_by_element_id(): void {
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

		$result = DeleteSection::execute( array( 'post_id' => 42, 'section_id' => 'sec001' ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['deleted'] );
		$decoded = json_decode( $written, true );
		$ids     = array_column( $decoded, 'id' );
		$this->assertNotContains( 'sec001', $ids );
	}

	public function test_remaining_count_is_correct_after_deletion(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$result = DeleteSection::execute( array( 'post_id' => 42, 'section_index' => 0 ) );

		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['remaining_count'] );
	}

	public function test_execute_returns_error_when_section_not_found(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = DeleteSection::execute( array( 'post_id' => 42, 'section_index' => 99 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_section_not_found', $result->get_error_code() );
	}

	public function test_execute_respects_gate_check_in_read_only_mode(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::READ_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = DeleteSection::execute( array( 'post_id' => 42, 'section_index' => 0 ) );
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

		$result = DeleteSection::execute( array( 'post_id' => 99, 'section_index' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}

	public function test_output_schema_has_correct_properties(): void {
		$schema = DeleteSection::output_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'deleted', $schema['properties'] );
		$this->assertArrayHasKey( 'remaining_count', $schema['properties'] );
		$this->assertSame( 'boolean', $schema['properties']['deleted']['type'] );
		$this->assertSame( 'integer', $schema['properties']['remaining_count']['type'] );
	}
}
