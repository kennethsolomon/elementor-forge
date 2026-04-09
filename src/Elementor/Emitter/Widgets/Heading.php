<?php
/**
 * Elementor heading widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Heading extends Widget {

	public function widget_type(): string {
		return 'heading';
	}

	/**
	 * Convenience constructor.
	 *
	 * @param array<string, mixed> $extra Extra settings merged onto the defaults.
	 */
	public static function create( string $title, string $tag = 'h2', string $align = '', array $extra = array() ): self {
		$settings = array(
			'title'       => $title,
			'header_size' => $tag,
		);
		if ( '' !== $align ) {
			$settings['align'] = $align;
		}
		return new self( array_merge( $settings, $extra ) );
	}
}
