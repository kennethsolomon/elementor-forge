<?php
/**
 * Structured content document shape.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

use InvalidArgumentException;

/**
 * Normalized shape of a content doc accepted by Forge's MCP tools and the
 * high-level generator. A content doc is an ordered list of labeled blocks
 * and each block becomes one top-level container in the emitted Elementor
 * document.
 *
 * Canonical block shapes:
 *
 *   heading:     { type:'heading', text:string, level?:1-6, align?:string }
 *   paragraph:   { type:'paragraph', text:string, align?:string }
 *   cta:         { type:'cta', text:string, url:string }
 *   image:       { type:'image', id:int, url:string, alt?:string }
 *   hero:        { type:'hero', heading:string, subheading?:string, cta?:{text, url} }
 *   card_grid:   { type:'card_grid', cards:list<{heading, description}> }
 *   faq:         { type:'faq', items:list<{question, answer}> }
 *   map:         { type:'map', address:string, zoom?:int }
 *   form:        { type:'form', shortcode:string }
 *
 * Anything else is passed through to {@see RawNode}.
 */
final class ContentDoc {

	/** @var string */
	private string $title;

	/** @var list<array<string, mixed>> */
	private array $blocks;

	/**
	 * @param list<array<string, mixed>> $blocks
	 */
	public function __construct( string $title, array $blocks ) {
		$this->title  = $title;
		$this->blocks = $blocks;
	}

	public function title(): string {
		return $this->title;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function blocks(): array {
		return $this->blocks;
	}

	/**
	 * Build a {@see ContentDoc} from a loosely-typed array — the shape passed
	 * in by MCP tools after JSON-schema validation.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data ): self {
		if ( ! isset( $data['title'] ) || ! is_string( $data['title'] ) ) {
			throw new InvalidArgumentException( 'Content doc must have a string `title`.' );
		}
		if ( ! isset( $data['blocks'] ) || ! is_array( $data['blocks'] ) ) {
			throw new InvalidArgumentException( 'Content doc must have a `blocks` array.' );
		}

		$blocks = array();
		foreach ( $data['blocks'] as $block ) {
			if ( is_array( $block ) && isset( $block['type'] ) && is_string( $block['type'] ) ) {
				/** @var array<string, mixed> $block */
				$blocks[] = $block;
			}
		}

		return new self( $data['title'], $blocks );
	}
}
