<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ApplyTemplate;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ApplyTemplateTest extends TestCase {

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

	public function test_input_schema_requires_cpt_and_post_data(): void {
		$schema = ApplyTemplate::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'cpt', $schema['required'] );
		$this->assertContains( 'post_data', $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_input_schema_cpt_enum_restricts_to_location_and_service(): void {
		$schema = ApplyTemplate::input_schema();
		$enum   = $schema['properties']['cpt']['enum'];
		$this->assertContains( 'ef_location', $enum );
		$this->assertContains( 'ef_service', $enum );
		$this->assertCount( 2, $enum );
	}

	public function test_input_schema_post_data_requires_title(): void {
		$schema = ApplyTemplate::input_schema();
		$this->assertContains( 'title', $schema['properties']['post_data']['required'] );
	}

	public function test_input_schema_post_data_status_defaults_to_draft(): void {
		$schema = ApplyTemplate::input_schema();
		$this->assertSame( 'draft', $schema['properties']['post_data']['properties']['status']['default'] );
	}

	public function test_output_schema_exposes_post_id_and_url(): void {
		$schema = ApplyTemplate::output_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'url', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['post_id']['type'] );
		$this->assertSame( 'string', $schema['properties']['url']['type'] );
	}

	public function test_permission_returns_true_when_user_can_manage_options(): void {
		// current_user_can is stubbed to return true in setUp.
		$this->assertTrue( ApplyTemplate::permission() );
	}

	public function test_execute_rejects_invalid_cpt(): void {
		$result = ApplyTemplate::execute( array( 'cpt' => 'bogus', 'post_data' => array( 'title' => 'X' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_cpt', $result->get_error_code() );
	}

	public function test_execute_rejects_empty_title(): void {
		$result = ApplyTemplate::execute( array( 'cpt' => 'ef_location', 'post_data' => array( 'title' => '' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_execute_rejects_missing_title_key(): void {
		$result = ApplyTemplate::execute( array( 'cpt' => 'ef_location', 'post_data' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_execute_rejects_non_array_post_data(): void {
		$result = ApplyTemplate::execute( array( 'cpt' => 'ef_location', 'post_data' => 'not-array' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_execute_returns_wp_error_from_wp_insert_post(): void {
		Functions\expect( 'wp_insert_post' )->once()->andReturn( new WP_Error( 'db_error', 'insert failed' ) );

		$result = ApplyTemplate::execute( array( 'cpt' => 'ef_service', 'post_data' => array( 'title' => 'My Service' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'db_error', $result->get_error_code() );
	}

	public function test_execute_happy_path_returns_post_id_and_url(): void {
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 42 );
		Functions\expect( 'get_permalink' )->once()->with( 42 )->andReturn( 'https://example.test/location/my-loc' );

		$result = ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array( 'title' => 'My Location' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['post_id'] );
		$this->assertSame( 'https://example.test/location/my-loc', $result['url'] );
	}

	public function test_execute_uses_custom_status_when_provided(): void {
		$captured_args = null;
		Functions\when( 'wp_insert_post' )->alias(
			static function ( array $args ) use ( &$captured_args ): int {
				$captured_args = $args;
				return 50;
			}
		);
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post' );

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_service',
				'post_data' => array( 'title' => 'Test', 'status' => 'publish' ),
			)
		);

		$this->assertSame( 'publish', $captured_args['post_status'] );
		$this->assertSame( 'ef_service', $captured_args['post_type'] );
	}

	public function test_execute_defaults_status_to_draft(): void {
		$captured_args = null;
		Functions\when( 'wp_insert_post' )->alias(
			static function ( array $args ) use ( &$captured_args ): int {
				$captured_args = $args;
				return 55;
			}
		);
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post' );

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array( 'title' => 'No Status' ),
			)
		);

		$this->assertSame( 'draft', $captured_args['post_status'] );
	}

	public function test_execute_populates_acf_fields_when_update_field_exists(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 60 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post' );
		// Declare update_field via Brain\Monkey so function_exists() returns
		// true naturally — never stub function_exists itself as that breaks
		// PHP's own autoloader.
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value, 'post_id' => $post_id );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'phone'   => '123',
						'address' => '1 Main St',
					),
				),
			)
		);

		$this->assertCount( 2, $acf_updates );
		$this->assertSame( 'phone', $acf_updates[0]['key'] );
		$this->assertSame( '123', $acf_updates[0]['value'] );
		$this->assertSame( 60, $acf_updates[0]['post_id'] );
	}

	// Gate delegation tests (read_only, page_only) are covered
	// comprehensively in ToolsGateDelegationTest for all tools.
}
