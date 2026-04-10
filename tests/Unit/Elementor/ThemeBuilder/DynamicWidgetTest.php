<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\ThemeBuilder;

use Brain\Monkey;
use ElementorForge\Elementor\Emitter\Widget;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\Image;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\ThemeBuilder\DynamicWidget;
use PHPUnit\Framework\TestCase;

final class DynamicWidgetTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_inner_returns_wrapped_widget(): void {
		$heading = Heading::create( 'Test', 'h1' );
		$dw      = new DynamicWidget( $heading, array( 'title' => array( 'field' => 'suburb_name' ) ) );

		$this->assertSame( $heading, $dw->inner() );
	}

	public function test_dynamic_map_returns_binding_array(): void {
		$map = array(
			'title' => array( 'field' => 'suburb_name' ),
			'image' => array( 'field' => 'hero_image' ),
		);
		$dw = new DynamicWidget( Heading::create( 'Test' ), $map );

		$this->assertSame( $map, $dw->dynamic_map() );
	}

	public function test_id_matches_inner_widget_id(): void {
		$heading = Heading::create( 'Test', 'h2', '', array() );
		$dw      = new DynamicWidget( $heading, array() );

		$this->assertSame( $heading->get_id(), $dw->get_id() );
	}

	public function test_to_array_injects_dynamic_keys_into_settings(): void {
		$heading = Heading::create( 'Placeholder', 'h1', 'center' );
		$dw      = new DynamicWidget(
			$heading,
			array(
				'title' => array( 'field' => 'suburb_name' ),
			)
		);

		$arr = $dw->to_array();

		$settings = (array) $arr['settings'];
		$this->assertArrayHasKey( '__dynamic__', $settings );
		$this->assertArrayHasKey( 'title', $settings['__dynamic__'] );
	}

	public function test_to_array_preserves_inner_widget_shape(): void {
		$heading = Heading::create( 'Test', 'h2', 'left' );
		$dw      = new DynamicWidget(
			$heading,
			array(
				'title' => array( 'field' => 'name' ),
			)
		);

		$arr = $dw->to_array();

		$this->assertSame( 'widget', $arr['elType'] );
		$this->assertSame( 'heading', $arr['widgetType'] );
		$this->assertSame( $heading->get_id(), $arr['id'] );
		$this->assertSame( array(), $arr['elements'] );
	}

	public function test_dynamic_tag_format_contains_acf_field_key(): void {
		$heading = Heading::create( 'X' );
		$dw      = new DynamicWidget(
			$heading,
			array(
				'title' => array( 'field' => 'suburb_name' ),
			)
		);

		$arr     = $dw->to_array();
		$dynamic = ( (array) $arr['settings'] )['__dynamic__'];
		$tag     = $dynamic['title'];

		$this->assertStringContainsString( 'field_ef_suburb_name', $tag );
		$this->assertStringContainsString( 'name="acf"', $tag );
		$this->assertStringContainsString( '[elementor-tag', $tag );
	}

	public function test_dynamic_tag_id_is_7_char_hex_from_md5(): void {
		$dw = new DynamicWidget(
			Heading::create( 'X' ),
			array( 'title' => array( 'field' => 'suburb_name' ) )
		);

		$arr     = $dw->to_array();
		$dynamic = ( (array) $arr['settings'] )['__dynamic__'];
		$tag     = $dynamic['title'];

		$expected_id = substr( md5( 'suburb_name' ), 0, 7 );
		$this->assertStringContainsString( 'id="' . $expected_id . '"', $tag );
	}

	public function test_multiple_dynamic_bindings(): void {
		$heading = Heading::create( 'X' );
		$dw      = new DynamicWidget(
			$heading,
			array(
				'title' => array( 'field' => 'suburb_name' ),
				'link'  => array( 'field' => 'page_url' ),
			)
		);

		$arr     = $dw->to_array();
		$dynamic = ( (array) $arr['settings'] )['__dynamic__'];

		$this->assertCount( 2, $dynamic );
		$this->assertArrayHasKey( 'title', $dynamic );
		$this->assertArrayHasKey( 'link', $dynamic );
		$this->assertStringContainsString( 'field_ef_suburb_name', $dynamic['title'] );
		$this->assertStringContainsString( 'field_ef_page_url', $dynamic['link'] );
	}

	public function test_empty_field_name_produces_empty_field_key(): void {
		$dw = new DynamicWidget(
			Heading::create( 'X' ),
			array( 'title' => array( 'field' => '' ) )
		);

		$arr     = $dw->to_array();
		$dynamic = ( (array) $arr['settings'] )['__dynamic__'];

		$this->assertStringContainsString( 'field_ef_', $dynamic['title'] );
	}

	public function test_missing_field_key_in_binding_defaults_to_empty(): void {
		$dw = new DynamicWidget(
			Heading::create( 'X' ),
			array( 'title' => array() )
		);

		$arr     = $dw->to_array();
		$dynamic = ( (array) $arr['settings'] )['__dynamic__'];

		// When 'field' key is missing, it defaults to empty string.
		$this->assertStringContainsString( 'field_ef_', $dynamic['title'] );
	}

	public function test_to_array_original_widget_settings_preserved(): void {
		$heading = Heading::create( 'My Title', 'h1', 'center' );
		$dw      = new DynamicWidget(
			$heading,
			array(
				'title' => array( 'field' => 'suburb_name' ),
			)
		);

		$arr      = $dw->to_array();
		$settings = (array) $arr['settings'];

		// Original heading settings survive alongside __dynamic__.
		$this->assertSame( 'My Title', $settings['title'] );
		$this->assertSame( 'h1', $settings['header_size'] );
		$this->assertSame( 'center', $settings['align'] );
	}

	public function test_settings_cast_back_to_object(): void {
		$dw = new DynamicWidget(
			Heading::create( 'X' ),
			array( 'title' => array( 'field' => 'test' ) )
		);

		$arr = $dw->to_array();
		$this->assertIsObject( $arr['settings'] );
	}

	public function test_empty_dynamic_map_produces_no_dynamic_key(): void {
		$dw = new DynamicWidget(
			Heading::create( 'X' ),
			array()
		);

		$arr      = $dw->to_array();
		$settings = (array) $arr['settings'];

		// Empty map still injects __dynamic__ key, just empty.
		$this->assertArrayHasKey( '__dynamic__', $settings );
		$this->assertSame( array(), $settings['__dynamic__'] );
	}

	public function test_is_inner_defaults_false(): void {
		$dw = new DynamicWidget( Heading::create( 'X' ), array() );

		$this->assertFalse( $dw->is_inner() );
	}
}
