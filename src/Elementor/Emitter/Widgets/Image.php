<?php
/**
 * Elementor image widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class Image extends Widget {

	public function widget_type(): string {
		return 'image';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( int $attachment_id, string $url, string $alt = '', array $extra = array() ): self {
		$settings = array(
			'image' => array(
				'id'  => $attachment_id,
				'url' => $url,
				'alt' => $alt,
			),
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
