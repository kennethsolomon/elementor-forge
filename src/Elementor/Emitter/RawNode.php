<?php
/**
 * Opaque round-trip node — preserves unknown element shapes.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

/**
 * Holds an element parsed from existing Elementor JSON that Forge does not
 * natively generate. Used for:
 *
 *   - ucaddon_* widgets when the `ucaddon_shim` setting is `preserve`
 *   - Any unknown widget type encountered during round-trip parsing
 *
 * The full original array is retained and round-tripped byte-identical so the
 * update path never corrupts unfamiliar content.
 */
final class RawNode extends Node {

	/** @var array<string, mixed> */
	private array $raw;

	/**
	 * @param array<string, mixed> $raw
	 */
	public function __construct( array $raw ) {
		parent::__construct();
		$this->raw = $raw;
		if ( isset( $raw['id'] ) && is_string( $raw['id'] ) ) {
			$this->id = $raw['id'];
		}
		if ( isset( $raw['isInner'] ) && true === $raw['isInner'] ) {
			$this->is_inner = true;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->raw;
	}
}
