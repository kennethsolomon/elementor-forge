<?php
/**
 * Widget wrapper that marks settings keys as ACF dynamic-tag references.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\ThemeBuilder;

use ElementorForge\Elementor\Emitter\Node;
use ElementorForge\Elementor\Emitter\Widget;

/**
 * Wraps a concrete {@see Widget} and marks some of its settings fields as
 * bound to ACF dynamic tags. The wizard installer uses this to fold
 * `__dynamic__` entries into the final JSON using ACF field keys from
 * {@see \ElementorForge\ACF\FieldGroups}.
 *
 * Format of the `$dynamic_map` parameter:
 *
 *   [
 *     'title' => [ 'field' => 'suburb_name' ],
 *     'image' => [ 'field' => 'hero_image' ],
 *   ]
 *
 * Keys are the widget-setting keys Elementor uses; values describe the ACF
 * field binding. The resolver at install time converts each binding into a
 * full Elementor `__dynamic__` shortcode string.
 */
final class DynamicWidget extends Node {

	/** @var Widget */
	private Widget $inner;

	/** @var array<string, array<string, string>> */
	private array $dynamic_map;

	/**
	 * @param array<string, array<string, string>> $dynamic_map
	 */
	public function __construct( Widget $inner, array $dynamic_map ) {
		parent::__construct( array(), $inner->get_id() );
		$this->inner       = $inner;
		$this->dynamic_map = $dynamic_map;
	}

	/**
	 * Expose the wrapped widget for the installer.
	 */
	public function inner(): Widget {
		return $this->inner;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function dynamic_map(): array {
		return $this->dynamic_map;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$arr      = $this->inner->to_array();
		$dynamic  = array();
		foreach ( $this->dynamic_map as $setting_key => $binding ) {
			$field_name              = $binding['field'] ?? '';
			$dynamic[ $setting_key ] = '[elementor-tag id="' . substr( md5( $field_name ), 0, 7 ) . '" name="acf" settings="%7B%22key%22%3A%22field_ef_' . $field_name . '%22%7D"]';
		}

		// Fold __dynamic__ into settings. Widget::to_array casts settings to object,
		// so we re-flatten to an array, inject __dynamic__, then cast back.
		$settings               = isset( $arr['settings'] ) ? (array) $arr['settings'] : array();
		$settings['__dynamic__'] = $dynamic;
		$arr['settings']         = (object) $settings;

		return $arr;
	}
}
