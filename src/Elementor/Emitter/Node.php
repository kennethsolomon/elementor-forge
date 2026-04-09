<?php
/**
 * Base node for the Elementor JSON emitter.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

/**
 * Every element in an Elementor v0.4 tree is a node. Concrete subclasses are
 * {@see Container} and {@see Widget}. Every node serializes to the canonical
 * shape `{id, elType, settings, elements, isInner}` plus a `widgetType` on widgets.
 */
abstract class Node {

	/** @var string */
	protected string $id;

	/** @var array<string, mixed> */
	protected array $settings = array();

	/** @var list<Node> */
	protected array $children = array();

	/** @var bool */
	protected bool $is_inner = false;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings = array(), ?string $id = null ) {
		$this->id       = $id ?? self::generate_id();
		$this->settings = $settings;
	}

	/**
	 * Produce a lowercase hex id in Elementor's style. Elementor uses 7- or 8-char
	 * lowercase hex. We use 8 for headroom — collisions inside a single document
	 * are vanishingly unlikely at this length.
	 */
	public static function generate_id(): string {
		try {
			$bytes = random_bytes( 4 );
		} catch ( \Exception $e ) {
			// Fallback for environments without CSPRNG (tests, phpstan).
			$bytes = pack( 'N', mt_rand() );
		}
		return bin2hex( $bytes );
	}

	public function get_id(): string {
		return $this->id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		return $this->settings;
	}

	/**
	 * @return list<Node>
	 */
	public function get_children(): array {
		return $this->children;
	}

	public function is_inner(): bool {
		return $this->is_inner;
	}

	/**
	 * Mark this node as an inner container/widget. Elementor flags every element
	 * nested inside another container with `isInner: true`.
	 */
	public function mark_inner(): void {
		$this->is_inner = true;
		foreach ( $this->children as $child ) {
			$child->mark_inner();
		}
	}

	/**
	 * Append a child node. Children automatically inherit the inner flag if the
	 * parent is inner, maintaining the contract with {@see Container::add_child}.
	 */
	protected function append_child( Node $child ): void {
		if ( $this->is_inner ) {
			$child->mark_inner();
		}
		$this->children[] = $child;
	}

	/**
	 * Serialize the full subtree to Elementor v0.4 JSON shape.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function to_array(): array;

	/**
	 * Recursively serialize children for inclusion in the parent array.
	 *
	 * @return list<array<string, mixed>>
	 */
	protected function children_to_array(): array {
		$out = array();
		foreach ( $this->children as $child ) {
			$out[] = $child->to_array();
		}
		return $out;
	}
}
