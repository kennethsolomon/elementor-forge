<?php
/**
 * Elementor icon-box widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class IconBox extends Widget {

	public function widget_type(): string {
		return 'icon-box';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( string $title, string $description = '', array $extra = array() ): self {
		$settings = array(
			'title_text'       => $title,
			'description_text' => $description,
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
