<?php
/**
 * Elementor v0.4 document — top-level wrapper.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

/**
 * Top-level Elementor export shape:
 *
 *     {
 *       "content": [...top-level containers],
 *       "page_settings": { ... },
 *       "version": "0.4",
 *       "title": "...",
 *       "type": "page" | "section" | "header" | "footer" | ...
 *     }
 *
 * {@see Emitter} uses this as the canonical object to write into `_elementor_data`
 * postmeta via {@see Encoder::encode_for_meta()}. Also serves as the round-trip
 * container for {@see Parser} output.
 */
final class Document {

	public const SCHEMA_VERSION = '0.4';

	/** @var list<Node> */
	private array $content = array();

	/** @var array<string, mixed> */
	private array $page_settings = array();

	/** @var string */
	private string $title;

	/** @var string */
	private string $type;

	/**
	 * @param array<string, mixed> $page_settings
	 */
	public function __construct( string $title = '', string $type = 'page', array $page_settings = array() ) {
		$this->title         = $title;
		$this->type          = $type;
		$this->page_settings = $page_settings;
	}

	public function title(): string {
		return $this->title;
	}

	public function type(): string {
		return $this->type;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function page_settings(): array {
		return $this->page_settings;
	}

	/**
	 * @return list<Node>
	 */
	public function content(): array {
		return $this->content;
	}

	/**
	 * Append a top-level section/container. Phase 1 documents are expected to be
	 * built from top-level Containers (Elementor v0.4 does not use legacy sections).
	 *
	 * @return $this
	 */
	public function append( Node $node ): self {
		$this->content[] = $node;
		return $this;
	}

	/**
	 * @param iterable<Node> $nodes
	 * @return $this
	 */
	public function append_all( iterable $nodes ): self {
		foreach ( $nodes as $node ) {
			$this->append( $node );
		}
		return $this;
	}

	/**
	 * Serialize the document to Elementor's top-level JSON shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$content_arr = array();
		foreach ( $this->content as $node ) {
			$content_arr[] = $node->to_array();
		}

		return array(
			'content'       => $content_arr,
			'page_settings' => (object) $this->page_settings,
			'version'       => self::SCHEMA_VERSION,
			'title'         => $this->title,
			'type'          => $this->type,
		);
	}
}
