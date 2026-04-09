<?php
/**
 * Compat shim for legacy ucaddon_* widgets.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

/**
 * Preserves `ucaddon_*` widgets found in existing Elementor JSON as opaque blobs.
 *
 * Forge's `ucaddon_shim` setting controls behavior:
 *   - `preserve` (default): passed through unchanged during round-trip updates so
 *     content created by the old Ultimate Addons stack keeps working.
 *   - `strip`: omitted during round-trip parsing.
 *
 * This class NEVER emits new ucaddon widgets. It only round-trips existing ones.
 * Generating new widgets is reserved for vanilla Elementor Pro equivalents.
 */
final class UcaddonShim extends Widget {

	/** @var string */
	private string $ucaddon_type;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( string $ucaddon_type, array $settings = array(), ?string $id = null ) {
		parent::__construct( $settings, $id );
		$this->ucaddon_type = $ucaddon_type;
	}

	public function widget_type(): string {
		return $this->ucaddon_type;
	}

	/**
	 * True if this widget slug looks like a ucaddon_* legacy widget.
	 */
	public static function is_ucaddon( string $widget_type ): bool {
		return 0 === strpos( $widget_type, 'ucaddon_' );
	}
}
