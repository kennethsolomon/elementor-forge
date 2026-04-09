<?php
/**
 * Elementor nested-accordion widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

/**
 * Nested Accordion — each item is a full Container subtree. The widget holds
 * accordion item headings in `items` and the container children render the
 * panel content.
 */
final class NestedAccordion extends Widget {

	public function widget_type(): string {
		return 'nested-accordion';
	}

	/**
	 * @param list<string>         $headings
	 * @param array<string, mixed> $extra
	 */
	public static function create( array $headings = array(), array $extra = array() ): self {
		$items = array();
		foreach ( $headings as $heading ) {
			$items[] = array(
				'item_title' => $heading,
				'_id'        => substr( md5( $heading . (string) microtime( true ) ), 0, 7 ),
			);
		}
		$settings = array( 'items' => $items );
		return new self( array_merge( $settings, $extra ) );
	}
}
