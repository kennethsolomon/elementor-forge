<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use Brain\Monkey;
use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Node;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use PHPUnit\Framework\TestCase;

final class NodeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_generate_id_returns_8_char_hex_string(): void {
		$id = Node::generate_id();

		$this->assertSame( 8, strlen( $id ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', $id );
	}

	public function test_generate_id_produces_unique_values(): void {
		$ids = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$ids[] = Node::generate_id();
		}
		$this->assertCount( 100, array_unique( $ids ), 'generate_id should produce unique values' );
	}

	public function test_constructor_assigns_custom_id(): void {
		$widget = Heading::create( 'Test', 'h2', '', array() );
		$node   = new Container( array(), 'custom123' );

		$this->assertSame( 'custom123', $node->get_id() );
	}

	public function test_constructor_generates_id_when_null(): void {
		$node = new Container();

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}$/', $node->get_id() );
	}

	public function test_get_settings_returns_constructor_settings(): void {
		$settings = array( 'content_width' => 'boxed', 'padding' => '10px' );
		$node     = new Container( $settings );

		$this->assertSame( $settings, $node->get_settings() );
	}

	public function test_get_settings_returns_empty_array_by_default(): void {
		$node = new Container();

		$this->assertSame( array(), $node->get_settings() );
	}

	public function test_get_children_returns_empty_array_by_default(): void {
		$node = new Container();

		$this->assertSame( array(), $node->get_children() );
	}

	public function test_get_children_returns_appended_children(): void {
		$parent = new Container();
		$child  = Heading::create( 'Hello' );
		$parent->add_child( $child );

		$children = $parent->get_children();
		$this->assertCount( 1, $children );
		$this->assertSame( $child, $children[0] );
	}

	public function test_is_inner_defaults_to_false(): void {
		$node = new Container();

		$this->assertFalse( $node->is_inner() );
	}

	public function test_mark_inner_sets_flag_to_true(): void {
		$node = new Container();
		$node->mark_inner();

		$this->assertTrue( $node->is_inner() );
	}

	public function test_mark_inner_cascades_to_all_children(): void {
		$root    = new Container();
		$level1  = new Container();
		$level2  = new Container();
		$widget  = Heading::create( 'Deep' );

		$level1->add_child( $level2 );
		$level2->add_child( $widget );
		$root->add_child( $level1 );

		$root->mark_inner();

		$this->assertTrue( $root->is_inner() );
		$this->assertTrue( $level1->is_inner() );
		$this->assertTrue( $level2->is_inner() );
		$this->assertTrue( $widget->is_inner() );
	}

	public function test_append_child_propagates_inner_flag_to_new_child(): void {
		$parent = new Container();
		$parent->mark_inner();

		$child = Heading::create( 'Added after inner' );
		$parent->add_child( $child );

		$this->assertTrue( $child->is_inner() );
	}

	public function test_append_child_does_not_set_inner_on_non_inner_parent(): void {
		$parent = new Container();
		$child  = Heading::create( 'Regular child' );
		$parent->add_child( $child );

		$this->assertFalse( $child->is_inner() );
	}

	public function test_children_to_array_serializes_all_children(): void {
		$parent = new Container( array(), 'parent01' );
		$parent->add_child( Heading::create( 'A' ) );
		$parent->add_child( Heading::create( 'B' ) );

		$arr = $parent->to_array();

		$this->assertCount( 2, $arr['elements'] );
		$this->assertSame( 'A', $arr['elements'][0]['settings']->title );
		$this->assertSame( 'B', $arr['elements'][1]['settings']->title );
	}

	public function test_children_to_array_empty_when_no_children(): void {
		$parent = new Container();
		$arr    = $parent->to_array();

		$this->assertSame( array(), $arr['elements'] );
	}
}
