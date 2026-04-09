<?php
/**
 * Elementor text-editor widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class TextEditor extends Widget {

	public function widget_type(): string {
		return 'text-editor';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( string $editor, array $extra = array() ): self {
		return new self( array_merge( array( 'editor' => $editor ), $extra ) );
	}
}
