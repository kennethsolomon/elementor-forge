<?php
/**
 * Elementor nested-carousel widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

/**
 * Nested Carousel uses the Elementor nested-elements system — each slide is
 * itself a full Container subtree. The carousel widget holds slide settings in
 * `carousel_items` and the container children render the actual slide content.
 */
final class NestedCarousel extends Widget {

	public function widget_type(): string {
		return 'nested-carousel';
	}

	/**
	 * @param int                  $slide_count Number of slides to render. Must match the children array on the parent container.
	 * @param array<string, mixed> $extra
	 */
	public static function create( int $slide_count = 3, array $extra = array() ): self {
		$items = array();
		for ( $i = 0; $i < $slide_count; $i++ ) {
			$items[] = array(
				'_id' => substr( md5( 'slide' . (string) $i . (string) microtime( true ) ), 0, 7 ),
			);
		}
		$settings = array( 'carousel_items' => $items );
		return new self( array_merge( $settings, $extra ) );
	}
}
