<?php
/**
 * Flexbox Container node.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

use InvalidArgumentException;

/**
 * Elementor v0.4 Flexbox Container. Replaces the legacy `section` + `column`
 * elTypes entirely. Containers can hold Widgets, other Containers, or a mix
 * of the two, and can nest up to 7 levels (Phase 1 constraint from Kenneth).
 */
final class Container extends Node {

	/**
	 * Hard cap on container nesting. Enforced on {@see add_child()} so a bug in
	 * the caller surfaces as an exception during emission, not a silent data
	 * corruption at Elementor runtime.
	 */
	public const MAX_DEPTH = 7;

	/** @var int */
	private int $depth;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings = array(), ?string $id = null, int $depth = 0 ) {
		parent::__construct( $settings, $id );
		$this->depth = $depth;
	}

	public function depth(): int {
		return $this->depth;
	}

	/**
	 * Append a child Node. Child containers get their depth bumped and the
	 * depth cap enforced. Throws if the child would exceed {@see MAX_DEPTH}.
	 *
	 * @return $this
	 */
	public function add_child( Node $child ): self {
		if ( $child instanceof self ) {
			$child_depth = $this->depth + 1;
			if ( $child_depth > self::MAX_DEPTH ) {
				throw new InvalidArgumentException(
					sprintf( 'Container nesting depth %d exceeds maximum of %d.', $child_depth, self::MAX_DEPTH )
				);
			}
			$child->depth = $child_depth;
		}

		$this->append_child( $child );
		return $this;
	}

	/**
	 * Convenience — add many children in order.
	 *
	 * @param iterable<Node> $children
	 * @return $this
	 */
	public function add_children( iterable $children ): self {
		foreach ( $children as $child ) {
			$this->add_child( $child );
		}
		return $this;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'       => $this->id,
			'settings' => (object) $this->settings,
			'elements' => $this->children_to_array(),
			'isInner'  => $this->is_inner,
			'elType'   => 'container',
		);
	}
}
