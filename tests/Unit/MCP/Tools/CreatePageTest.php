<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\CreatePage;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class CreatePageTest extends TestCase {

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
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_input_schema_requires_title_and_content_doc(): void {
		$schema = CreatePage::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'title', $schema['required'] );
		$this->assertContains( 'content_doc', $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_input_schema_title_has_min_length_one(): void {
		$schema = CreatePage::input_schema();
		$this->assertSame( 'string', $schema['properties']['title']['type'] );
		$this->assertSame( 1, $schema['properties']['title']['minLength'] );
	}

	public function test_input_schema_status_enum_and_default(): void {
		$schema = CreatePage::input_schema();
		$this->assertSame( array( 'draft', 'publish' ), $schema['properties']['status']['enum'] );
		$this->assertSame( 'draft', $schema['properties']['status']['default'] );
	}

	public function test_input_schema_content_doc_requires_title_and_blocks(): void {
		$schema = CreatePage::input_schema();
		$doc    = $schema['properties']['content_doc'];
		$this->assertSame( 'object', $doc['type'] );
		$this->assertContains( 'title', $doc['required'] );
		$this->assertContains( 'blocks', $doc['required'] );
	}

	public function test_output_schema_exposes_post_id_and_url(): void {
		$schema = CreatePage::output_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'url', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['post_id']['type'] );
		$this->assertSame( 'string', $schema['properties']['url']['type'] );
	}

	public function test_permission_returns_true_when_user_can_publish_pages(): void {
		// current_user_can is stubbed to return true in setUp.
		$this->assertTrue( CreatePage::permission() );
	}

	public function test_execute_returns_error_for_empty_title(): void {
		$result = CreatePage::execute(
			array( 'title' => '', 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_missing_title(): void {
		$result = CreatePage::execute(
			array( 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_non_string_title(): void {
		$result = CreatePage::execute(
			array( 'title' => 123, 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_execute_returns_wp_error_from_wp_insert_post(): void {
		Functions\expect( 'wp_insert_post' )->once()->andReturn( new WP_Error( 'db_error', 'insert failed' ) );

		$result = CreatePage::execute(
			array( 'title' => 'My Page', 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'db_error', $result->get_error_code() );
	}

	public function test_execute_happy_path_returns_post_id_and_url(): void {
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 100 );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\expect( 'get_permalink' )->once()->with( 100 )->andReturn( 'https://example.test/my-page' );

		$result = CreatePage::execute(
			array( 'title' => 'My Page', 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 100, $result['post_id'] );
		$this->assertSame( 'https://example.test/my-page', $result['url'] );
	}

	public function test_execute_sets_elementor_meta_keys(): void {
		$meta_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 200 );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$meta_updates ): bool {
				$meta_updates[ $key ] = array( 'post_id' => $post_id, 'value' => $value );
				return true;
			}
		);
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page' );

		CreatePage::execute(
			array( 'title' => 'Test', 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);

		$this->assertArrayHasKey( '_elementor_edit_mode', $meta_updates );
		$this->assertSame( 'builder', $meta_updates['_elementor_edit_mode']['value'] );
		$this->assertArrayHasKey( '_elementor_version', $meta_updates );
		$this->assertSame( 200, $meta_updates['_elementor_edit_mode']['post_id'] );
	}

	public function test_execute_inserts_as_page_post_type(): void {
		$captured_args = null;
		Functions\when( 'wp_insert_post' )->alias(
			static function ( array $args ) use ( &$captured_args ): int {
				$captured_args = $args;
				return 300;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page' );

		CreatePage::execute(
			array( 'title' => 'Test', 'status' => 'publish', 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);

		$this->assertSame( 'page', $captured_args['post_type'] );
		$this->assertSame( 'publish', $captured_args['post_status'] );
		$this->assertSame( 'Test', $captured_args['post_title'] );
	}

	public function test_execute_defaults_status_to_draft(): void {
		$captured_args = null;
		Functions\when( 'wp_insert_post' )->alias(
			static function ( array $args ) use ( &$captured_args ): int {
				$captured_args = $args;
				return 310;
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page' );

		CreatePage::execute(
			array( 'title' => 'Defaults', 'content_doc' => array( 'title' => 'Doc', 'blocks' => array() ) )
		);

		$this->assertSame( 'draft', $captured_args['post_status'] );
	}

	// Gate delegation tests (read_only, page_only) are covered
	// comprehensively in ToolsGateDelegationTest for all tools.
}
