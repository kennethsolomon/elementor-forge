<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use Brain\Monkey;
use ElementorForge\Elementor\Emitter\RawNode;
use PHPUnit\Framework\TestCase;

final class RawNodeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_to_array_returns_raw_data_unchanged(): void {
		$raw = array(
			'id'         => 'abc12345',
			'elType'     => 'widget',
			'widgetType' => 'ucaddon_custom',
			'settings'   => array( 'color' => '#ff0000' ),
			'elements'   => array(),
			'isInner'    => false,
		);

		$node = new RawNode( $raw );
		$this->assertSame( $raw, $node->to_array() );
	}

	public function test_constructor_extracts_id_from_raw(): void {
		$raw  = array( 'id' => 'deadbeef', 'elType' => 'widget' );
		$node = new RawNode( $raw );

		$this->assertSame( 'deadbeef', $node->get_id() );
	}

	public function test_constructor_generates_id_when_raw_has_no_id(): void {
		$raw  = array( 'elType' => 'widget', 'settings' => array() );
		$node = new RawNode( $raw );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', $node->get_id() );
	}

	public function test_constructor_ignores_non_string_id(): void {
		$raw  = array( 'id' => 12345, 'elType' => 'widget' );
		$node = new RawNode( $raw );

		// Non-string id is ignored, so a generated id is used.
		$this->assertNotSame( '12345', $node->get_id() );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', $node->get_id() );
	}

	public function test_constructor_sets_inner_flag_from_raw(): void {
		$raw  = array( 'id' => 'aaa00001', 'isInner' => true );
		$node = new RawNode( $raw );

		$this->assertTrue( $node->is_inner() );
	}

	public function test_constructor_does_not_set_inner_when_false(): void {
		$raw  = array( 'id' => 'bbb00002', 'isInner' => false );
		$node = new RawNode( $raw );

		$this->assertFalse( $node->is_inner() );
	}

	public function test_constructor_does_not_set_inner_when_missing(): void {
		$raw  = array( 'id' => 'ccc00003' );
		$node = new RawNode( $raw );

		$this->assertFalse( $node->is_inner() );
	}

	public function test_constructor_does_not_set_inner_for_truthy_non_true(): void {
		$raw  = array( 'id' => 'ddd00004', 'isInner' => 1 );
		$node = new RawNode( $raw );

		// Only strict `true` sets is_inner, not truthy values.
		$this->assertFalse( $node->is_inner() );
	}

	public function test_raw_widget_returns_minimal_elementor_shape(): void {
		$result = RawNode::raw_widget( 'fibosearch' );

		$this->assertSame( '', $result['id'] );
		$this->assertIsObject( $result['settings'] );
		$this->assertSame( array(), $result['elements'] );
		$this->assertFalse( $result['isInner'] );
		$this->assertSame( 'fibosearch', $result['widgetType'] );
		$this->assertSame( 'widget', $result['elType'] );
	}

	public function test_raw_widget_with_custom_id(): void {
		$result = RawNode::raw_widget( 'nav-menu', array(), 'custom42' );

		$this->assertSame( 'custom42', $result['id'] );
	}

	public function test_raw_widget_with_settings(): void {
		$settings = array( 'style' => 'dark', 'size' => 'lg' );
		$result   = RawNode::raw_widget( 'wc-cart', $settings );

		$obj = $result['settings'];
		$this->assertIsObject( $obj );
		$this->assertSame( 'dark', $obj->style );
		$this->assertSame( 'lg', $obj->size );
	}

	public function test_raw_widget_empty_settings_is_object_not_array(): void {
		$result = RawNode::raw_widget( 'test-widget' );

		// Empty settings must encode as {} (object) not [] (array).
		$json = json_encode( $result['settings'] );
		$this->assertSame( '{}', $json );
	}

	public function test_round_trip_preserves_complex_raw_data(): void {
		$complex = array(
			'id'         => 'round001',
			'elType'     => 'widget',
			'widgetType' => 'ucaddon_hero',
			'settings'   => array(
				'nested' => array(
					'deep' => array( 'value' => 42 ),
				),
				'list'   => array( 'a', 'b', 'c' ),
			),
			'elements'   => array(
				array(
					'id'       => 'child001',
					'elType'   => 'widget',
					'settings' => array(),
				),
			),
			'isInner'    => true,
			'custom'     => 'extra_field',
		);

		$node   = new RawNode( $complex );
		$output = $node->to_array();

		$this->assertSame( $complex, $output );
	}
}
