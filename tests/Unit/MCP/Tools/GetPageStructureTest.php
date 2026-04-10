<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\GetPageStructure;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class GetPageStructureTest extends TestCase {

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
						'settings'   => array( 'editor' => '<p>Some text</p>' ),
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

	public function test_input_schema_requires_post_id(): void {
		$schema = GetPageStructure::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'post_id', $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_input_schema_post_id_has_minimum_one(): void {
		$schema = GetPageStructure::input_schema();
		$this->assertSame( 'integer', $schema['properties']['post_id']['type'] );
		$this->assertSame( 1, $schema['properties']['post_id']['minimum'] );
	}

	public function test_output_schema_has_required_properties(): void {
		$schema = GetPageStructure::output_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'title', $schema['properties'] );
		$this->assertArrayHasKey( 'section_count', $schema['properties'] );
		$this->assertArrayHasKey( 'sections', $schema['properties'] );
	}

	public function test_output_schema_types_are_correct(): void {
		$schema = GetPageStructure::output_schema();
		$this->assertSame( 'integer', $schema['properties']['post_id']['type'] );
		$this->assertSame( 'integer', $schema['properties']['section_count']['type'] );
		$this->assertSame( 'array', $schema['properties']['sections']['type'] );
	}

	public function test_permission_requires_edit_pages(): void {
		$this->assertTrue( GetPageStructure::permission() );
	}

	public function test_execute_returns_error_for_invalid_post_id(): void {
		$result = GetPageStructure::execute( array( 'post_id' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_missing_post_id(): void {
		$result = GetPageStructure::execute( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_no_elementor_data(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$result = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_no_elementor_data', $result->get_error_code() );
	}

	public function test_execute_returns_annotated_tree_with_correct_section_count(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['post_id'] );
		$this->assertSame( 3, $result['section_count'] );
		$this->assertCount( 3, $result['sections'] );
	}

	public function test_execute_returns_post_title(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'Test Page', $result['title'] );
	}

	public function test_annotated_sections_have_index_id_type_fields(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result   = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$sections = $result['sections'];

		$this->assertSame( 0, $sections[0]['index'] );
		$this->assertSame( 'sec001', $sections[0]['id'] );
		$this->assertSame( 'container', $sections[0]['type'] );

		$this->assertSame( 1, $sections[1]['index'] );
		$this->assertSame( 'sec002', $sections[1]['id'] );
		$this->assertSame( 2, $sections[2]['index'] );
		$this->assertSame( 'sec003', $sections[2]['id'] );
	}

	public function test_widgets_include_widget_type_field(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result   = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$children = $result['sections'][0]['children'];

		$this->assertSame( 'widget', $children[0]['type'] );
		$this->assertSame( 'heading', $children[0]['widget_type'] );
	}

	public function test_heading_widgets_include_preview_from_title(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result   = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$children = $result['sections'][0]['children'];

		$this->assertArrayHasKey( 'preview', $children[0] );
		$this->assertSame( 'Hello', $children[0]['preview'] );
	}

	public function test_text_editor_widgets_include_preview_stripped_of_html(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result   = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$children = $result['sections'][1]['children'];

		$this->assertArrayHasKey( 'preview', $children[0] );
		$this->assertSame( 'Some text', $children[0]['preview'] );
	}

	public function test_nested_children_are_recursively_annotated(): void {
		$sections = array(
			array(
				'id'       => 'outer001',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(
					array(
						'id'       => 'inner001',
						'elType'   => 'container',
						'settings' => new \stdClass(),
						'elements' => array(
							array(
								'id'         => 'wid001',
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => array( 'title' => 'Nested' ),
								'elements'   => array(),
								'isInner'    => true,
							),
						),
						'isInner'  => true,
					),
				),
				'isInner'  => false,
			),
		);
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $sections ) );

		$result       = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$outer        = $result['sections'][0];
		$inner        = $outer['children'][0];
		$deep_widget  = $inner['children'][0];

		$this->assertSame( 'inner001', $inner['id'] );
		$this->assertSame( 'container', $inner['type'] );
		$this->assertSame( 'wid001', $deep_widget['id'] );
		$this->assertSame( 'heading', $deep_widget['widget_type'] );
		$this->assertSame( 'Nested', $deep_widget['preview'] );
	}

	public function test_empty_section_has_no_children_key(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$this->assertArrayNotHasKey( 'children', $result['sections'][2] );
	}

	public function test_section_with_children_has_child_count(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = GetPageStructure::execute( array( 'post_id' => 42 ) );
		$this->assertSame( 1, $result['sections'][0]['child_count'] );
	}
}
