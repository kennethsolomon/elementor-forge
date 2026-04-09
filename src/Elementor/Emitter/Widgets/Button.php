<?php
/**
 * Elementor button widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Button extends Widget {

	public function widget_type(): string {
		return 'button';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( string $text, string $url = '', array $extra = array() ): self {
		$settings = array( 'text' => $text );
		if ( '' !== $url ) {
			$settings['link'] = array(
				'url'               => $url,
				'is_external'       => '',
				'nofollow'          => '',
				'custom_attributes' => '',
			);
		}
		return new self( array_merge( $settings, $extra ) );
	}
}
