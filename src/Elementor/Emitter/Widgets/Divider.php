<?php
/**
 * Elementor divider widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Divider extends Widget {

	public function widget_type(): string {
		return 'divider';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( array $extra = array() ): self {
		return new self( $extra );
	}
}
