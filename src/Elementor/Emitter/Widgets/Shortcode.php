<?php
/**
 * Elementor shortcode widget — used to reference CF7 forms.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Shortcode extends Widget {

	public function widget_type(): string {
		return 'shortcode';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( string $shortcode, array $extra = array() ): self {
		return new self( array_merge( array( 'shortcode' => $shortcode ), $extra ) );
	}
}
