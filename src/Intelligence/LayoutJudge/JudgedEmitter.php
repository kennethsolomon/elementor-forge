<?php
/**
 * Emitter that consults the layout judge for unknown block types.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\ContentDoc;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\Widgets\IconBox;
use ElementorForge\Elementor\Emitter\Widgets\ImageCarousel;
use ElementorForge\Elementor\Emitter\Widgets\NestedAccordion;
use ElementorForge\Elementor\Emitter\Widgets\NestedCarousel;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;

/**
 * Composes {@see Emitter} and {@see LayoutJudge}. For block types the base
 * emitter recognizes (the Phase 1 explicit set), behavior is delegated
 * unchanged so Phase 1 round-trips and existing tests are not affected. For
 * block types the base emitter does not recognize, the judge picks a widget
 * pattern and this class translates the decision into a Container subtree.
 *
 * Composition (not inheritance) so the base emitter stays final and so test
 * doubles can swap the judge without subclassing.
 *
 * Phase 1 invariant preserved: ANY block whose `type` is one of the original
 * Phase 1 types (`heading`, `paragraph`, `cta`, `image`, `hero`, `card_grid`,
 * `faq`, `map`, `form`) is handled by `Emitter::emit_block()` exactly as
 * before. The judge is only consulted when the base emitter would have fallen
 * through to the unknown-block path.
 */
final class JudgedEmitter {

	private const KNOWN_PHASE_1_TYPES = array(
		'heading',
		'paragraph',
		'cta',
		'image',
		'hero',
		'card_grid',
		'faq',
		'map',
		'form',
	);

	private Emitter $base;
	private LayoutJudge $judge;

	public function __construct( Emitter $base, LayoutJudge $judge ) {
		$this->base  = $base;
		$this->judge = $judge;
	}

	/**
	 * Build a complete page document from a content doc, consulting the judge
	 * for any block the base emitter does not recognize.
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
	 * Emit a single block. Phase 1 types take the base path. Anything else is
	 * routed through the judge.
	 *
	 * @param array<string, mixed> $block
	 */
	public function emit_block( array $block ): ?Container {
		$type = isset( $block['type'] ) && is_string( $block['type'] ) ? $block['type'] : '';

		if ( in_array( $type, self::KNOWN_PHASE_1_TYPES, true ) ) {
			return $this->base->emit_block( $block );
		}

		// Unknown block — judge it.
		$decision = $this->judge->decide( self::block_to_section( $block ) );

		return $this->container_from_decision( $decision, $block );
	}

	/**
	 * Build a container subtree implementing the judge's decision.
	 *
	 * @param array<string, mixed> $block
	 */
	private function container_from_decision( Decision $decision, array $block ): Container {
		$container = new Container( array( 'content_width' => 'boxed' ) );

		switch ( $decision->widget() ) {
			case Decision::WIDGET_NESTED_ACCORDION:
				$headings = self::extract_headings( $block );
				$container->add_child( NestedAccordion::create( $headings ) );
				break;

			case Decision::WIDGET_IMAGE_CAROUSEL:
				$images = self::extract_images( $block );
				$container->add_child( ImageCarousel::create( $images ) );
				break;

			case Decision::WIDGET_NESTED_CAROUSEL:
				$count = max( 1, count( self::extract_items( $block ) ) );
				$container->add_child( NestedCarousel::create( $count ) );
				break;

			case Decision::WIDGET_ICON_BOX_GRID:
				foreach ( self::extract_items( $block ) as $item ) {
					$cell = new Container( array( 'content_width' => 'boxed', '_flex_size' => 'grow' ) );
					$cell->add_child(
						IconBox::create(
							self::item_string( $item, array( 'name', 'title', 'heading' ) ),
							self::item_string( $item, array( 'description', 'text', 'body' ) )
						)
					);
					$container->add_child( $cell );
				}
				break;

			case Decision::WIDGET_ICON_LIST:
				$lines = array();
				foreach ( self::extract_items( $block ) as $item ) {
					$lines[] = '<li>' . self::item_string( $item, array( 'text', 'name', 'title' ) ) . '</li>';
				}
				$container->add_child(
					TextEditor::create( '<ul class="ef-icon-list">' . implode( '', $lines ) . '</ul>' )
				);
				break;

			case Decision::WIDGET_TEXT_EDITOR:
			default:
				$container->add_child(
					TextEditor::create(
						'<!-- elementor-forge: ' . self::sanitize_comment( $decision->reason() ) . ' -->'
					)
				);
				break;
		}

		return $container;
	}

	/**
	 * Defang an HTML comment payload — strip the close-comment sequence so
	 * caller-controlled text cannot break out of the surrounding comment node.
	 */
	private static function sanitize_comment( string $value ): string {
		return str_replace( array( '-->', '<!--' ), array( '--&gt;', '&lt;!--' ), $value );
	}

	/**
	 * Map an emitter block (`type` keyed) to a judge section (`section` keyed).
	 *
	 * @param array<string, mixed> $block
	 * @return array<string, mixed>
	 */
	private static function block_to_section( array $block ): array {
		$section          = $block;
		$section['section'] = isset( $block['type'] ) && is_string( $block['type'] ) ? $block['type'] : '';
		return $section;
	}

	/**
	 * @param array<string, mixed> $block
	 * @return list<array<string, mixed>>
	 */
	private static function extract_items( array $block ): array {
		if ( ! isset( $block['items'] ) || ! is_array( $block['items'] ) ) {
			return array();
		}
		/** @var list<array<string, mixed>> $items */
		$items = array();
		foreach ( $block['items'] as $item ) {
			if ( is_array( $item ) ) {
				$items[] = $item;
			} elseif ( is_string( $item ) ) {
				$items[] = array( 'text' => $item );
			}
		}
		return $items;
	}

	/**
	 * @param array<string, mixed> $block
	 * @return list<string>
	 */
	private static function extract_headings( array $block ): array {
		$headings = array();
		foreach ( self::extract_items( $block ) as $item ) {
			$heading = self::item_string( $item, array( 'question', 'title', 'heading', 'name' ) );
			if ( '' !== $heading ) {
				$headings[] = $heading;
			}
		}
		return $headings;
	}

	/**
	 * @param array<string, mixed> $block
	 * @return list<array{id:int, url:string, alt:string}>
	 */
	private static function extract_images( array $block ): array {
		$images = array();
		foreach ( self::extract_items( $block ) as $item ) {
			$url = self::item_string( $item, array( 'image', 'url', 'src' ) );
			if ( '' === $url ) {
				continue;
			}
			$id  = isset( $item['id'] ) && is_int( $item['id'] ) ? $item['id'] : 0;
			$alt = self::item_string( $item, array( 'alt', 'caption', 'text' ) );
			$images[] = array( 'id' => $id, 'url' => $url, 'alt' => $alt );
		}
		return $images;
	}

	/**
	 * @param array<string, mixed> $item
	 * @param list<string>         $keys
	 */
	private static function item_string( array $item, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $item[ $key ] ) && is_string( $item[ $key ] ) ) {
				return $item[ $key ];
			}
		}
		return '';
	}
}
