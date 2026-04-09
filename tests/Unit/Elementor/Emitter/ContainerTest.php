<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase {

	public function test_container_serializes_to_v04_shape(): void {
		$c = new Container( array( 'content_width' => 'boxed' ), 'abcdef01' );
		$c->add_child( Heading::create( 'Hello', 'h2', 'center' ) );

		$out = $c->to_array();

		$this->assertSame( 'abcdef01', $out['id'] );
		$this->assertSame( 'container', $out['elType'] );
		$this->assertFalse( $out['isInner'] );
		$this->assertIsArray( $out['elements'] );
		$this->assertCount( 1, $out['elements'] );
		$this->assertIsObject( $out['settings'] );
	}

	public function test_mark_inner_cascades(): void {
		$outer = new Container();
		$inner = new Container();
		$outer->add_child( $inner );
		$outer->mark_inner();

		$this->assertTrue( $outer->to_array()['isInner'] );
		$this->assertTrue( $inner->to_array()['isInner'] );
	}

	public function test_max_depth_is_enforced(): void {
		$root = new Container();
		$cursor = $root;
		for ( $i = 0; $i < Container::MAX_DEPTH; $i++ ) {
			$next = new Container();
			$cursor->add_child( $next );
			$cursor = $next;
		}

		$this->expectException( InvalidArgumentException::class );
		$cursor->add_child( new Container() );
	}

	public function test_add_children_appends_in_order(): void {
		$c = new Container();
		$c->add_children(
			array(
				Heading::create( 'A' ),
				Heading::create( 'B' ),
				Heading::create( 'C' ),
			)
		);

		$elements = $c->to_array()['elements'];
		$this->assertCount( 3, $elements );
		$this->assertSame( 'A', $elements[0]['settings']->title );
		$this->assertSame( 'B', $elements[1]['settings']->title );
		$this->assertSame( 'C', $elements[2]['settings']->title );
	}
}
