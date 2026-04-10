<?php
/**
 * Content doc → Elementor Document emitter.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\GoogleMaps;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\IconBox;
use ElementorForge\Elementor\Emitter\Widgets\Image;
use ElementorForge\Elementor\Emitter\Widgets\NestedAccordion;
use ElementorForge\Elementor\Emitter\Widgets\Shortcode;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\Emitter\KitTag;

/**
 * Translates a {@see ContentDoc} into a fully-constructed Elementor
 * {@see Document} ready to be round-tripped through {@see Encoder}.
 *
 * Each recognized block type maps to an idiomatic container + widget layout:
 *
 *   heading → Container holding a Heading widget
 *   paragraph → Container holding a TextEditor widget
 *   cta → Centered container holding a Button
 *   image → Container holding an Image widget
 *   hero → Container (background-ready) with Heading + subheading TextEditor + optional CTA Button
 *   card_grid → Container with a grid of IconBox cards
 *   faq → Container with a NestedAccordion
 *   map → Container with a GoogleMaps widget
 *   form → Container with a Shortcode widget referencing the CF7 form id
 *
 * Unknown types fall through to a diagnostic paragraph so the Claude-driven
 * MCP flow never silently drops content.
 */
final class Emitter {

	/**
	 * Build a complete Elementor page document from a content doc.
	 */
	public function emit( ContentDoc $doc, string $page_type = 'page' ): Document {
		$document = new Document( $doc->title(), $page_type, array() );

		foreach ( $doc->blocks() as $block ) {
			$container = $this->emit_block( $block );
			if ( null !== $container ) {
				$document->append( $container );
			}
		}

		return $document;
	}

	/**
	 * Emit a single block. Kept public so callers can splice a single block
	 * into an existing document without rebuilding the whole tree (used by the
	 * `add_section` MCP tool).
	 *
	 * @param array<string, mixed> $block
	 */
	public function emit_block( array $block ): ?Container {
		$type = isset( $block['type'] ) && is_string( $block['type'] ) ? $block['type'] : '';

		switch ( $type ) {
			case 'heading':
				return $this->wrap_widget(
					Heading::create(
						self::string( $block, 'text' ),
						'h' . max( 1, min( 6, self::int( $block, 'level', 2 ) ) ),
						self::string( $block, 'align', '' )
					)
				);

			case 'paragraph':
				return $this->wrap_widget(
					TextEditor::create(
						self::string( $block, 'text' )
					)
				);

			case 'cta':
				return $this->wrap_widget(
					Button::create(
						self::string( $block, 'text' ),
						self::string( $block, 'url', '' )
					),
					array( 'content_width' => 'boxed', 'flex_justify_content' => 'center' )
				);

			case 'image':
				return $this->wrap_widget(
					Image::create(
						self::int( $block, 'id' ),
						self::string( $block, 'url' ),
						self::string( $block, 'alt', '' )
					)
				);

			case 'hero':
				return $this->emit_hero( $block );

			case 'card_grid':
				return $this->emit_card_grid( $block );

			case 'faq':
				return $this->emit_faq( $block );

			case 'map':
				return $this->wrap_widget(
					GoogleMaps::create(
						self::string( $block, 'address' ),
						self::int( $block, 'zoom', 13 )
					)
				);

			case 'form':
				return $this->wrap_widget(
					Shortcode::create( self::string( $block, 'shortcode' ) )
				);

			default:
				// Unknown block — emit as a diagnostic text block so the content
				// is not silently dropped during MCP tool calls.
				return $this->wrap_widget(
					TextEditor::create( '<!-- elementor-forge: unknown block type "' . $type . '" -->' )
				);
		}
	}

	/**
	 * Wrap a single widget in a top-level container with sensible layout defaults.
	 *
	 * @param array<string, mixed> $container_settings
	 */
	private function wrap_widget( Widget $widget, array $container_settings = array() ): Container {
		$defaults = array(
			'content_width' => 'boxed',
		);
		$container = new Container( array_merge( $defaults, $container_settings ) );
		$container->add_child( $widget );
		return $container;
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private function emit_hero( array $block ): Container {
		$bg_settings = array(
			'background_background' => 'classic',
			'__globals__'           => array(
				'background_color' => KitTag::color( KitTag::COLOR_PRIMARY ),
			),
		);

		// Allow explicit background overrides from block data.
		if ( isset( $block['background_color'] ) && is_string( $block['background_color'] ) ) {
			$bg_settings = array(
				'background_background' => 'classic',
				'background_color'      => $block['background_color'],
			);
		}
		if ( isset( $block['background_image'] ) && is_array( $block['background_image'] ) ) {
			$bg_settings['background_background'] = 'classic';
			$bg_settings['background_image']      = $block['background_image'];
		}

		$outer = new Container(
			array_merge(
				array(
					'content_width'        => 'full',
					'min_height'           => array( 'unit' => 'vh', 'size' => 60, 'sizes' => array() ),
					'flex_direction'       => 'column',
					'flex_justify_content' => 'center',
					'flex_align_items'     => 'center',
				),
				$bg_settings
			)
		);

		$outer->add_child( Heading::create( self::string( $block, 'heading' ), 'h1', 'center' ) );

		$subheading = self::string( $block, 'subheading', '' );
		if ( '' !== $subheading ) {
			$outer->add_child( TextEditor::create( $subheading ) );
		}

		if ( isset( $block['cta'] ) && is_array( $block['cta'] ) ) {
			/** @var array<string, mixed> $cta */
			$cta = $block['cta'];
			$outer->add_child(
				Button::create(
					self::string( $cta, 'text' ),
					self::string( $cta, 'url', '' )
				)
			);
		}

		return $outer;
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private function emit_card_grid( array $block ): Container {
		$outer = new Container(
			array(
				'content_width'  => 'boxed',
				'flex_direction' => 'row',
				'flex_wrap'      => 'wrap',
				'flex_gap'       => array( 'unit' => 'px', 'size' => 20, 'sizes' => array(), 'column' => '20', 'row' => '20', 'isLinked' => true ),
			)
		);

		$cards      = isset( $block['cards'] ) && is_array( $block['cards'] ) ? $block['cards'] : array();
		$card_count = count( $cards );
		$col_width  = $card_count > 0 ? self::column_width( $card_count, 20 ) : array( 'unit' => '%', 'size' => 100, 'sizes' => array() );

		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			/** @var array<string, mixed> $card */
			$card_container = new Container(
				array(
					'content_width' => 'boxed',
					'width'         => $col_width,
				)
			);
			$card_container->add_child(
				IconBox::create(
					self::string( $card, 'heading' ),
					self::string( $card, 'description', '' )
				)
			);
			$outer->add_child( $card_container );
		}

		return $outer;
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private function emit_faq( array $block ): Container {
		$outer = new Container( array( 'content_width' => 'boxed' ) );

		$items          = isset( $block['items'] ) && is_array( $block['items'] ) ? $block['items'] : array();
		$accordion_items = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['question'] ) && is_string( $item['question'] ) ) {
				$accordion_items[] = array(
					'title'   => $item['question'],
					'content' => isset( $item['answer'] ) && is_string( $item['answer'] ) ? $item['answer'] : '',
				);
			}
		}

		$outer->add_child( NestedAccordion::create( $accordion_items ) );
		return $outer;
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private static function string( array $block, string $key, string $fallback = '' ): string {
		return isset( $block[ $key ] ) && is_string( $block[ $key ] ) ? $block[ $key ] : $fallback;
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private static function int( array $block, string $key, int $fallback = 0 ): int {
		return isset( $block[ $key ] ) && ( is_int( $block[ $key ] ) || is_numeric( $block[ $key ] ) ) ? (int) $block[ $key ] : $fallback;
	}

	/**
	 * Compute the percentage width for N equal columns accounting for gap.
	 *
	 * Elementor's Flexbox Container uses `width` as a dimension object with
	 * `unit: '%'`. Unlike the legacy `_flex_size: grow` / `_inline_size` which
	 * are ignored by the Flexbox engine, this produces actual column widths.
	 *
	 * @param int $count  Number of columns (must be >= 1).
	 * @param int $gap_px Gap between columns in pixels. Width is reduced
	 *                    proportionally so N columns + (N-1) gaps fit 100%.
	 * @return array{unit: string, size: float|int, sizes: array<empty>}
	 */
	public static function column_width( int $count, int $gap_px = 0 ): array {
		if ( $count <= 1 ) {
			return array( 'unit' => '%', 'size' => 100, 'sizes' => array() );
		}

		// Convert gap from px to approximate % of a 1200px boxed container.
		$container_px = 1200;
		$total_gap_px = ( $count - 1 ) * $gap_px;
		$total_gap_pct = ( $total_gap_px / $container_px ) * 100;
		$size = round( ( 100 - $total_gap_pct ) / $count, 2 );

		return array( 'unit' => '%', 'size' => $size, 'sizes' => array() );
	}
}
