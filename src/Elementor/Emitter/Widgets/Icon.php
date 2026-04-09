<?php
/**
 * Elementor icon widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Icon extends Widget {

	public function widget_type(): string {
		return 'icon';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( string $icon_library = 'fa-solid', string $icon_value = 'fas fa-star', array $extra = array() ): self {
		$settings = array(
			'selected_icon' => array(
				'value'   => $icon_value,
				'library' => $icon_library,
			),
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
