<?php
/**
 * Elementor nested-accordion widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Widget;

/**
 * Nested Accordion — each item is a full Container subtree. The widget holds
 * accordion item headings in `items` and the container children render the
 * panel content.
 *
 * Elementor stores nested accordion answer content as child Container elements
 * in `elements[]`, one per accordion item. Each container holds the answer
 * widgets (typically a TextEditor).
 */
final class NestedAccordion extends Widget {

	public function widget_type(): string {
		return 'nested-accordion';
	}

	/**
	 * Overrides Widget::to_array() to include child containers for answer content.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'settings'   => (object) $this->settings,
			'elements'   => $this->children_to_array(),
			'isInner'    => $this->is_inner,
			'widgetType' => $this->widget_type(),
			'elType'     => 'widget',
		);
	}

	/**
	 * Create a nested accordion with optional answer content.
	 *
	 * Accepts two formats:
	 *   - Legacy: `list<string>` — title-only headings (backward compatible).
	 *   - Rich:   `list<array{title|question: string, content|answer?: string}>` — Q&A pairs.
	 *
	 * @param list<string|array<string, string>> $items
	 * @param array<string, mixed>               $extra
	 */
	public static function create( array $items = array(), array $extra = array() ): self {
		$settings_items = array();
		$instance       = new self( array() );

		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$id               = substr( md5( $item . (string) microtime( true ) ), 0, 7 );
				$settings_items[] = array(
					'item_title' => $item,
					'_id'        => $id,
				);
				// Empty panel container for title-only items.
				$panel = new Container( array() );
				$instance->append_child( $panel );
			} elseif ( is_array( $item ) ) {
				$title   = $item['title'] ?? $item['question'] ?? '';
				$content = $item['content'] ?? $item['answer'] ?? '';
				$id      = substr( md5( $title . (string) microtime( true ) ), 0, 7 );

				$settings_items[] = array(
					'item_title' => $title,
					'_id'        => $id,
				);

				$panel = new Container( array() );
				if ( '' !== $content ) {
					$panel->add_child( TextEditor::create( $content ) );
				}
				$instance->append_child( $panel );
			}
		}

		$instance->settings = array_merge( array( 'items' => $settings_items ), $extra );
		return $instance;
	}
}
