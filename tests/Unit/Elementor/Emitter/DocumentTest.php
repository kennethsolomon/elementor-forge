<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase {

	public function test_document_top_level_shape_matches_v04(): void {
		$doc = new Document( 'Home', 'page', array( 'some_setting' => 'value' ) );
		$c   = new Container( array( 'content_width' => 'boxed' ) );
		$c->add_child( Heading::create( 'Hello', 'h1', 'center' ) );
		$doc->append( $c );

		$out = $doc->to_array();

		$this->assertSame( '0.4', $out['version'] );
		$this->assertSame( 'Home', $out['title'] );
		$this->assertSame( 'page', $out['type'] );
		$this->assertIsObject( $out['page_settings'] );
		$this->assertCount( 1, $out['content'] );
		$this->assertSame( 'container', $out['content'][0]['elType'] );
	}

	public function test_append_all_preserves_order(): void {
		$doc = new Document( 'Test' );
		$c1  = new Container( array(), 'aaaaaaaa' );
		$c2  = new Container( array(), 'bbbbbbbb' );
		$c3  = new Container( array(), 'cccccccc' );
		$doc->append_all( array( $c1, $c2, $c3 ) );

		$out = $doc->to_array();
		$this->assertSame( 'aaaaaaaa', $out['content'][0]['id'] );
		$this->assertSame( 'bbbbbbbb', $out['content'][1]['id'] );
		$this->assertSame( 'cccccccc', $out['content'][2]['id'] );
	}
}
