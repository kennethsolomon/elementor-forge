<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\ContentDoc;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\KitTag;
use PHPUnit\Framework\TestCase;

final class EmitterFixesTest extends TestCase {


	public function test_emit_hero_container_has_background_background_classic(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Hero Page',
				'blocks' => array(
					array(
						'type'    => 'hero',
						'heading' => 'Welcome',
					),
				),
			)
		);

		$out       = ( new Emitter() )->emit( $doc )->to_array();
		$container = $out['content'][0];
		$settings  = (array) $container['settings'];

		$this->assertSame( 'classic', $settings['background_background'] );
	}

	public function test_emit_hero_container_globals_contains_primary_color_reference(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Hero Page',
				'blocks' => array(
					array(
						'type'    => 'hero',
						'heading' => 'Welcome',
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$settings = (array) $out['content'][0]['settings'];

		$this->assertArrayHasKey( '__globals__', $settings );
		$this->assertArrayHasKey( 'background_color', $settings['__globals__'] );
		$this->assertSame(
			KitTag::color( KitTag::COLOR_PRIMARY ),
			$settings['__globals__']['background_color']
		);
	}

	public function test_emit_hero_with_explicit_background_color_override_uses_literal_color(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Override Hero',
				'blocks' => array(
					array(
						'type'             => 'hero',
						'heading'          => 'Hello',
						'background_color' => '#ff0000',
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$settings = (array) $out['content'][0]['settings'];

		$this->assertSame( 'classic', $settings['background_background'] );
		$this->assertSame( '#ff0000', $settings['background_color'] );
		// Explicit color override must NOT carry the __globals__ reference.
		$this->assertArrayNotHasKey( '__globals__', $settings );
	}

	public function test_emit_hero_without_background_color_does_not_set_literal_background_color(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Default Hero',
				'blocks' => array(
					array(
						'type'    => 'hero',
						'heading' => 'Hello',
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$settings = (array) $out['content'][0]['settings'];

		$this->assertArrayNotHasKey( 'background_color', $settings );
	}

	public function test_emit_hero_with_subheading_has_three_child_widgets(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Hero',
				'blocks' => array(
					array(
						'type'       => 'hero',
						'heading'    => 'Title',
						'subheading' => 'Sub',
						'cta'        => array( 'text' => 'Go', 'url' => '/go' ),
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$elements = $out['content'][0]['elements'];

		$this->assertCount( 3, $elements );
		$this->assertSame( 'heading', $elements[0]['widgetType'] );
		$this->assertSame( 'text-editor', $elements[1]['widgetType'] );
		$this->assertSame( 'button', $elements[2]['widgetType'] );
	}

	public function test_emit_hero_without_subheading_has_heading_only_when_no_cta(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Minimal Hero',
				'blocks' => array(
					array(
						'type'    => 'hero',
						'heading' => 'Title Only',
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$elements = $out['content'][0]['elements'];

		$this->assertCount( 1, $elements );
		$this->assertSame( 'heading', $elements[0]['widgetType'] );
	}


	public function test_emit_card_grid_child_containers_have_width_key_with_percent_unit(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Grid',
				'blocks' => array(
					array(
						'type'  => 'card_grid',
						'cards' => array(
							array( 'heading' => 'Card 1', 'description' => 'Desc 1' ),
							array( 'heading' => 'Card 2', 'description' => 'Desc 2' ),
						),
					),
				),
			)
		);

		$out            = ( new Emitter() )->emit( $doc )->to_array();
		$grid           = $out['content'][0];
		$card_container = $grid['elements'][0];
		$card_settings  = (array) $card_container['settings'];

		$this->assertArrayHasKey( 'width', $card_settings );
		$this->assertSame( '%', $card_settings['width']['unit'] );
	}

	public function test_emit_card_grid_child_containers_do_not_use_legacy_flex_size(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Grid',
				'blocks' => array(
					array(
						'type'  => 'card_grid',
						'cards' => array(
							array( 'heading' => 'Card A' ),
							array( 'heading' => 'Card B' ),
						),
					),
				),
			)
		);

		$out           = ( new Emitter() )->emit( $doc )->to_array();
		$card_settings = (array) $out['content'][0]['elements'][0]['settings'];

		$this->assertArrayNotHasKey( '_flex_size', $card_settings );
	}

	public function test_column_width_returns_100_percent_for_single_card(): void {
		$width = Emitter::column_width( 1, 20 );

		$this->assertSame( '%', $width['unit'] );
		$this->assertSame( 100, $width['size'] );
		$this->assertSame( array(), $width['sizes'] );
	}

	public function test_column_width_returns_correct_percentage_for_two_cards(): void {
		// 2 columns, 1 gap of 20px in 1200px container → gap% = 20/1200*100 ≈ 1.67%
		// each col = (100 - 1.67) / 2 ≈ 49.17%
		$width = Emitter::column_width( 2, 20 );

		$this->assertSame( '%', $width['unit'] );
		$this->assertEqualsWithDelta( 49.17, $width['size'], 0.01 );
	}

	public function test_column_width_returns_correct_percentage_for_three_cards(): void {
		// 3 columns, 2 gaps of 20px → gap_total_px=40, gap%=40/1200*100=3.33%
		// each col = (100 - 3.33) / 3 ≈ 32.22%
		$width = Emitter::column_width( 3, 20 );

		$this->assertSame( '%', $width['unit'] );
		$this->assertEqualsWithDelta( 32.22, $width['size'], 0.01 );
	}

	public function test_column_width_returns_correct_percentage_for_four_cards(): void {
		// 4 columns, 3 gaps of 20px → gap_total_px=60, gap%=60/1200*100=5%
		// each col = (100 - 5) / 4 = 23.75%
		$width = Emitter::column_width( 4, 20 );

		$this->assertSame( '%', $width['unit'] );
		$this->assertEqualsWithDelta( 23.75, $width['size'], 0.01 );
	}

	public function test_column_width_sizes_key_is_always_empty_array(): void {
		foreach ( array( 1, 2, 3, 4 ) as $count ) {
			$width = Emitter::column_width( $count, 20 );
			$this->assertSame( array(), $width['sizes'], "sizes must be empty for {$count} column(s)" );
		}
	}

	public function test_emit_card_grid_applies_column_width_to_all_child_containers(): void {
		$cards = array(
			array( 'heading' => 'A' ),
			array( 'heading' => 'B' ),
			array( 'heading' => 'C' ),
		);

		$doc = ContentDoc::from_array(
			array(
				'title'  => '3-col Grid',
				'blocks' => array(
					array( 'type' => 'card_grid', 'cards' => $cards ),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$children = $out['content'][0]['elements'];

		$this->assertCount( 3, $children );
		$expected = Emitter::column_width( 3, 20 );
		foreach ( $children as $child ) {
			$settings = (array) $child['settings'];
			$this->assertSame( $expected, $settings['width'] );
		}
	}


	public function test_emit_faq_nested_accordion_elements_contain_text_editor_widgets_for_answers(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'FAQ',
				'blocks' => array(
					array(
						'type'  => 'faq',
						'items' => array(
							array( 'question' => 'What?', 'answer' => 'This.' ),
							array( 'question' => 'Why?', 'answer' => 'Because.' ),
						),
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$faq      = $out['content'][0];
		$acc      = $faq['elements'][0];

		$this->assertSame( 'nested-accordion', $acc['widgetType'] );
		// Each item produces one child panel container.
		$this->assertCount( 2, $acc['elements'] );

		// Each panel container holds a TextEditor with the answer text.
		$panel_0 = $acc['elements'][0];
		$this->assertSame( 'container', $panel_0['elType'] );
		$this->assertCount( 1, $panel_0['elements'] );
		$this->assertSame( 'text-editor', $panel_0['elements'][0]['widgetType'] );
	}

	public function test_emit_faq_question_titles_appear_in_settings_items(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'FAQ',
				'blocks' => array(
					array(
						'type'  => 'faq',
						'items' => array(
							array( 'question' => 'How does it work?', 'answer' => 'Like magic.' ),
							array( 'question' => 'Is it free?', 'answer' => 'Yes.' ),
						),
					),
				),
			)
		);

		$out      = ( new Emitter() )->emit( $doc )->to_array();
		$acc      = $out['content'][0]['elements'][0];
		$items    = $acc['settings']->items;

		$this->assertCount( 2, $items );
		$this->assertSame( 'How does it work?', $items[0]['item_title'] );
		$this->assertSame( 'Is it free?', $items[1]['item_title'] );
	}

	public function test_emit_faq_items_with_empty_answers_produce_empty_panel_containers(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'FAQ',
				'blocks' => array(
					array(
						'type'  => 'faq',
						'items' => array(
							array( 'question' => 'Q1', 'answer' => '' ),
						),
					),
				),
			)
		);

		$out   = ( new Emitter() )->emit( $doc )->to_array();
		$panel = $out['content'][0]['elements'][0]['elements'][0];

		// Panel container exists but has no child widgets.
		$this->assertSame( 'container', $panel['elType'] );
		$this->assertEmpty( $panel['elements'] );
	}

	public function test_emit_faq_with_empty_items_produces_accordion_with_no_settings_items(): void {
		$doc = ContentDoc::from_array(
			array(
				'title'  => 'Empty FAQ',
				'blocks' => array(
					array(
						'type'  => 'faq',
						'items' => array(),
					),
				),
			)
		);

		$out  = ( new Emitter() )->emit( $doc )->to_array();
		$acc  = $out['content'][0]['elements'][0];

		$this->assertSame( 'nested-accordion', $acc['widgetType'] );
		$this->assertEmpty( $acc['settings']->items );
	}
}
