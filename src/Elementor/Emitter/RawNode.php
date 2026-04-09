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

	/**
	 * Build a minimal Elementor widget element array for a widget type the
	 * Forge emitter does not natively model (WooCommerce widgets, nav-menu,
	 * fibosearch, etc.). Shared by every caller that needs to wrap a non-core
	 * widget in a {@see RawNode} — keeps the "minimum shape Elementor will
	 * render" contract in one place. The `settings` field is cast to
	 * `stdClass` because Elementor round-trips empty settings as `{}` and an
	 * empty PHP array would encode as `[]`, which breaks the editor.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public static function raw_widget( string $widget_type, array $settings = array(), ?string $id = null ): array {
		return array(
			'id'         => $id ?? '',
			'settings'   => (object) $settings,
			'elements'   => array(),
			'isInner'    => false,
			'widgetType' => $widget_type,
			'elType'     => 'widget',
		);
	}
}
