<?php
/**
 * Base widget node.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

/**
 * Elementor v0.4 widget node. Subclasses declare their `widgetType` via the
 * abstract {@see widget_type()} method and are responsible for providing a
 * sensible default-settings shape in their constructor.
 */
abstract class Widget extends Node {

	/**
	 * The Elementor widget slug — e.g. `heading`, `icon-box`, `google_maps`.
	 */
	abstract public function widget_type(): string;

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'settings'   => (object) $this->settings,
			'elements'   => array(),
			'isInner'    => $this->is_inner,
			'widgetType' => $this->widget_type(),
			'elType'     => 'widget',
		);
	}
}
