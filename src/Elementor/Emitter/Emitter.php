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
		$outer = new Container(
			array(
				'content_width'        => 'full',
				'min_height'           => array( 'unit' => 'vh', 'size' => 60, 'sizes' => array() ),
				'flex_direction'       => 'column',
				'flex_justify_content' => 'center',
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
			)
		);

		$cards = isset( $block['cards'] ) && is_array( $block['cards'] ) ? $block['cards'] : array();
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			/** @var array<string, mixed> $card */
			$card_container = new Container(
				array(
					'content_width' => 'boxed',
					'_flex_size'    => 'grow',
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

		$items   = isset( $block['items'] ) && is_array( $block['items'] ) ? $block['items'] : array();
		$headings = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['question'] ) && is_string( $item['question'] ) ) {
				$headings[] = $item['question'];
			}
		}

		$outer->add_child( NestedAccordion::create( $headings ) );
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
}
