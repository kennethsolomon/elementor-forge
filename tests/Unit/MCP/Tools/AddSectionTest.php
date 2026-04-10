<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\AddSection;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class AddSectionTest extends TestCase {

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
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_input_schema_requires_page_id_and_block(): void {
		$schema = AddSection::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'page_id', $schema['required'] );
		$this->assertContains( 'block', $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_input_schema_page_id_has_minimum_one(): void {
		$schema = AddSection::input_schema();
		$this->assertSame( 'integer', $schema['properties']['page_id']['type'] );
		$this->assertSame( 1, $schema['properties']['page_id']['minimum'] );
	}

	public function test_input_schema_block_is_object(): void {
		$schema = AddSection::input_schema();
		$this->assertSame( 'object', $schema['properties']['block']['type'] );
	}

	public function test_output_schema_exposes_post_id_and_appended(): void {
		$schema = AddSection::output_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'appended', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['post_id']['type'] );
		$this->assertSame( 'boolean', $schema['properties']['appended']['type'] );
	}

	public function test_permission_returns_true_when_user_can_edit_pages(): void {
		// current_user_can is stubbed to return true in setUp.
		$this->assertTrue( AddSection::permission() );
	}

	public function test_execute_returns_error_for_zero_page_id(): void {
		$result = AddSection::execute( array( 'page_id' => 0, 'block' => array( 'type' => 'heading' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_page', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_missing_page_id(): void {
		$result = AddSection::execute( array( 'block' => array( 'type' => 'heading' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_page', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_empty_block(): void {
		$result = AddSection::execute( array( 'page_id' => 42, 'block' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_block', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_block_is_not_array(): void {
		$result = AddSection::execute( array( 'page_id' => 42, 'block' => 'not-array' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_block', $result->get_error_code() );
	}

	// Gate delegation tests (read_only, page_only, allowlist) are covered
	// comprehensively in ToolsGateDelegationTest for all tools.
}
