<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\SetKitGlobals;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class SetKitGlobalsTest extends TestCase {

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


	public function test_input_schema_has_colors_typography_button_properties(): void {
		$schema = SetKitGlobals::input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'colors', $schema['properties'] );
		$this->assertArrayHasKey( 'typography', $schema['properties'] );
		$this->assertArrayHasKey( 'button', $schema['properties'] );
	}

	public function test_input_schema_has_no_required_fields(): void {
		$schema = SetKitGlobals::input_schema();

		$this->assertArrayNotHasKey( 'required', $schema );
	}


	public function test_output_schema_has_kit_id_and_updated(): void {
		$schema = SetKitGlobals::output_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'kit_id', $schema['properties'] );
		$this->assertArrayHasKey( 'updated', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['kit_id']['type'] );
	}


	public function test_permission_returns_true_when_user_can_manage_options(): void {
		$this->assertTrue( SetKitGlobals::permission() );
	}


	public function test_execute_returns_error_when_no_settings_provided(): void {
		$result = SetKitGlobals::execute( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_empty_kit_settings', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_only_unrecognized_keys_provided(): void {
		$result = SetKitGlobals::execute( array( 'unknown_key' => 'value' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_empty_kit_settings', $result->get_error_code() );
	}


	public function test_execute_returns_error_in_read_only_mode(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::READ_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = SetKitGlobals::execute( array( 'colors' => array( 'primary' => '#ff0000' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_execute_returns_error_in_page_only_mode_for_site_wide_action(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::PAGE_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = SetKitGlobals::execute( array( 'colors' => array( 'primary' => '#ff0000' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_SITE_WIDE_IN_PAGE_ONLY, $result->get_error_code() );
	}


	public function test_execute_calls_kit_writer_and_returns_kit_id_and_updated_map(): void {
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->alias( static fn ( $v ): string => (string) $v );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );

		// Override get_option to return kit ID 5 for 'elementor_active_kit' and
		// safety full for everything else.
		Functions\when( 'get_option' )->alias(
			static fn ( $key, $default = false ) => 'elementor_active_kit' === $key
				? 5
				: array( 'safety_mode' => 'full', 'safety_allowed_post_ids' => '' )
		);

		$result = SetKitGlobals::execute(
			array(
				'colors' => array( 'primary' => '#1a73e8' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 5, $result['kit_id'] );
		$this->assertTrue( $result['updated']['colors'] );
	}

	public function test_execute_returns_error_when_no_active_kit_found(): void {
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->justReturn( '#aabbcc' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );

		// No active kit — get_option returns 0 for kit, full safety mode otherwise.
		Functions\when( 'get_option' )->alias(
			static fn ( $key, $default = false ) => 'elementor_active_kit' === $key
				? 0
				: array( 'safety_mode' => 'full', 'safety_allowed_post_ids' => '' )
		);

		$result = SetKitGlobals::execute(
			array(
				'colors' => array( 'primary' => '#aabbcc' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_no_active_kit', $result->get_error_code() );
	}
}
