<?php
/**
 * Elementor image-carousel widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class ImageCarousel extends Widget {

	public function widget_type(): string {
		return 'image-carousel';
	}

	/**
	 * @param list<array{id:int, url:string, alt?:string}> $images
	 * @param array<string, mixed>                         $extra
	 */
	public static function create( array $images, array $extra = array() ): self {
		$settings = array(
			'carousel' => array_map(
				static fn( array $image ): array => array(
					'id'  => $image['id'],
					'url' => $image['url'],
					'alt' => $image['alt'] ?? '',
				),
				$images
			),
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
