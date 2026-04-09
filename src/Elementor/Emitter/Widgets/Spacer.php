<?php
/**
 * Elementor spacer widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Spacer extends Widget {

	public function widget_type(): string {
		return 'spacer';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( int $size_px = 40, array $extra = array() ): self {
		$settings = array(
			'space' => array(
				'unit'  => 'px',
				'size'  => $size_px,
				'sizes' => array(),
			),
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
