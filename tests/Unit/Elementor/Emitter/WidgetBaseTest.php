<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use Brain\Monkey;
use ElementorForge\Elementor\Emitter\Widget;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the abstract Widget base class. Uses an anonymous concrete subclass
 * since Widget itself is abstract.
 */
final class WidgetBaseTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_widget( array $settings = array(), ?string $id = null, string $type = 'test-widget' ): Widget {
		return new class( $settings, $id, $type ) extends Widget {
			private string $type;

			public function __construct( array $settings, ?string $id, string $type ) {
				parent::__construct( $settings, $id );
				$this->type = $type;
			}

			public function widget_type(): string {
				return $this->type;
			}
		};
	}

	public function test_to_array_returns_v04_widget_shape(): void {
		$widget = $this->make_widget( array( 'title' => 'Hello' ), 'abc00001' );
		$arr    = $widget->to_array();

		$this->assertSame( 'abc00001', $arr['id'] );
		$this->assertSame( 'widget', $arr['elType'] );
		$this->assertSame( 'test-widget', $arr['widgetType'] );
		$this->assertFalse( $arr['isInner'] );
		$this->assertSame( array(), $arr['elements'] );
		$this->assertIsObject( $arr['settings'] );
		$this->assertSame( 'Hello', $arr['settings']->title );
	}

	public function test_to_array_settings_cast_to_object(): void {
		$widget = $this->make_widget( array( 'key' => 'value' ) );
		$arr    = $widget->to_array();

		$this->assertInstanceOf( \stdClass::class, $arr['settings'] );
	}

	public function test_to_array_empty_settings_encodes_as_json_object(): void {
		$widget = $this->make_widget( array() );
		$arr    = $widget->to_array();

		$json = json_encode( $arr['settings'] );
		$this->assertSame( '{}', $json );
	}

	public function test_to_array_elements_always_empty(): void {
		$widget = $this->make_widget( array( 'a' => 1 ) );
		$arr    = $widget->to_array();

		$this->assertSame( array(), $arr['elements'] );
	}

	public function test_widget_type_reflected_in_output(): void {
		$widget = $this->make_widget( array(), null, 'icon-box' );
		$arr    = $widget->to_array();

		$this->assertSame( 'icon-box', $arr['widgetType'] );
	}

	public function test_inner_flag_reflected_in_to_array(): void {
		$widget = $this->make_widget();
		$this->assertFalse( $widget->to_array()['isInner'] );

		$widget->mark_inner();
		$this->assertTrue( $widget->to_array()['isInner'] );
	}

	public function test_widget_inherits_node_id_generation(): void {
		$widget = $this->make_widget();
		$id     = $widget->get_id();

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', $id );
		$this->assertSame( $id, $widget->to_array()['id'] );
	}

	public function test_widget_preserves_custom_id(): void {
		$widget = $this->make_widget( array(), 'myid1234' );

		$this->assertSame( 'myid1234', $widget->get_id() );
		$this->assertSame( 'myid1234', $widget->to_array()['id'] );
	}

	public function test_to_array_has_all_required_keys(): void {
		$widget = $this->make_widget();
		$arr    = $widget->to_array();

		$this->assertArrayHasKey( 'id', $arr );
		$this->assertArrayHasKey( 'settings', $arr );
		$this->assertArrayHasKey( 'elements', $arr );
		$this->assertArrayHasKey( 'isInner', $arr );
		$this->assertArrayHasKey( 'widgetType', $arr );
		$this->assertArrayHasKey( 'elType', $arr );
		$this->assertCount( 6, $arr );
	}

	public function test_settings_with_complex_values(): void {
		$settings = array(
			'padding' => array( 'unit' => 'px', 'top' => '10' ),
			'color'   => '#ff0000',
			'nested'  => array( 'a' => array( 'b' => 'c' ) ),
		);
		$widget = $this->make_widget( $settings );
		$arr    = $widget->to_array();

		$this->assertSame( '#ff0000', $arr['settings']->color );
		$this->assertSame( array( 'unit' => 'px', 'top' => '10' ), $arr['settings']->padding );
		$this->assertSame( array( 'a' => array( 'b' => 'c' ) ), $arr['settings']->nested );
	}
}
