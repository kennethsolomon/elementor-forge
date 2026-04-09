<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\ContentDoc;
use ElementorForge\Elementor\Emitter\Emitter;
use PHPUnit\Framework\TestCase;

final class EmitterTest extends TestCase {

	public function test_emits_heading_block(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'My Page',
				'blocks' => array(
					array( 'type' => 'heading', 'text' => 'Welcome', 'level' => 1, 'align' => 'center' ),
				),
			)
		);

		$document = ( new Emitter() )->emit( $doc );
		$out      = $document->to_array();

		$this->assertSame( 'My Page', $out['title'] );
		$this->assertCount( 1, $out['content'] );
		$container = $out['content'][0];
		$this->assertSame( 'container', $container['elType'] );
		$this->assertCount( 1, $container['elements'] );
		$widget = $container['elements'][0];
		$this->assertSame( 'widget', $widget['elType'] );
		$this->assertSame( 'heading', $widget['widgetType'] );
		$this->assertSame( 'Welcome', $widget['settings']->title );
		$this->assertSame( 'h1', $widget['settings']->header_size );
		$this->assertSame( 'center', $widget['settings']->align );
	}

	public function test_emits_hero_with_cta(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Service Page',
				'blocks' => array(
					array(
						'type'       => 'hero',
						'heading'    => 'Professional Painting',
						'subheading' => 'Decades of trusted experience.',
						'cta'        => array( 'text' => 'Get a Quote', 'url' => '/contact/' ),
					),
				),
			)
		);

		$document = ( new Emitter() )->emit( $doc );
		$out      = $document->to_array();

		$this->assertCount( 1, $out['content'] );
		$hero_container = $out['content'][0];
		$this->assertCount( 3, $hero_container['elements'] );
		$this->assertSame( 'heading', $hero_container['elements'][0]['widgetType'] );
		$this->assertSame( 'text-editor', $hero_container['elements'][1]['widgetType'] );
		$this->assertSame( 'button', $hero_container['elements'][2]['widgetType'] );
	}

	public function test_emits_card_grid(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Services',
				'blocks' => array(
					array(
						'type'  => 'card_grid',
						'cards' => array(
							array( 'heading' => 'Interior', 'description' => 'Inside work' ),
							array( 'heading' => 'Exterior', 'description' => 'Outside work' ),
						),
					),
				),
			)
		);

		$document = ( new Emitter() )->emit( $doc );
		$out      = $document->to_array();
		$grid     = $out['content'][0];

		$this->assertSame( 'container', $grid['elType'] );
		$this->assertCount( 2, $grid['elements'] );
		$this->assertSame( 'container', $grid['elements'][0]['elType'] );
		$this->assertSame( 'icon-box', $grid['elements'][0]['elements'][0]['widgetType'] );
	}

	public function test_emits_faq_accordion(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'FAQ',
				'blocks' => array(
					array(
						'type'  => 'faq',
						'items' => array(
							array( 'question' => 'Q1', 'answer' => 'A1' ),
							array( 'question' => 'Q2', 'answer' => 'A2' ),
						),
					),
				),
			)
		);

		$out  = ( new Emitter() )->emit( $doc )->to_array();
		$faq  = $out['content'][0];
		$acc  = $faq['elements'][0];

		$this->assertSame( 'nested-accordion', $acc['widgetType'] );
		$this->assertCount( 2, $acc['settings']->items );
	}

	public function test_emits_google_map_and_cf7(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Contact',
				'blocks' => array(
					array( 'type' => 'map', 'address' => 'Melbourne VIC' ),
					array( 'type' => 'form', 'shortcode' => '[contact-form-7 id="123"]' ),
				),
			)
		);

		$out = ( new Emitter() )->emit( $doc )->to_array();
		$this->assertCount( 2, $out['content'] );
		$this->assertSame( 'google_maps', $out['content'][0]['elements'][0]['widgetType'] );
		$this->assertSame( 'shortcode', $out['content'][1]['elements'][0]['widgetType'] );
	}

	public function test_unknown_block_falls_through_to_diagnostic(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Edge Case',
				'blocks' => array(
					array( 'type' => 'totally_made_up' ),
				),
			)
		);

		$out = ( new Emitter() )->emit( $doc )->to_array();
		$this->assertCount( 1, $out['content'] );
		$widget = $out['content'][0]['elements'][0];
		$this->assertSame( 'text-editor', $widget['widgetType'] );
		$this->assertStringContainsString( 'unknown block type', $widget['settings']->editor );
	}
}
