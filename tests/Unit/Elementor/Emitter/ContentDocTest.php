<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\ContentDoc;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContentDocTest extends TestCase {

	public function test_from_array_requires_title(): void {
		$this->expectException( InvalidArgumentException::class );
		ContentDoc::from_array( array( 'blocks' => array() ) );
	}

	public function test_from_array_requires_blocks(): void {
		$this->expectException( InvalidArgumentException::class );
		ContentDoc::from_array( array( 'title' => 'Hi' ) );
	}

	public function test_blocks_filter_out_non_arrays(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Test',
				'blocks' => array(
					array( 'type' => 'heading', 'text' => 'ok' ),
					'not an array',
					array( 'no_type_key' => 'x' ),
					array( 'type' => 'paragraph', 'text' => 'ok' ),
				),
			)
		);

		$blocks = $doc->blocks();
		$this->assertCount( 2, $blocks );
		$this->assertSame( 'heading', $blocks[0]['type'] );
		$this->assertSame( 'paragraph', $blocks[1]['type'] );
	}

	public function test_title_getter(): void {
		$doc = ContentDoc::from_array( array( 'title' => 'Hello', 'blocks' => array() ) );
		$this->assertSame( 'Hello', $doc->title() );
	}
}
