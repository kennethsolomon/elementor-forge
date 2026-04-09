<?php
/**
 * Elementor JSON parser — round-trip support.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

use InvalidArgumentException;

/**
 * Parses an Elementor v0.4 document or a single element tree back into the
 * Node object graph. Every element Forge knows how to generate is mapped to
 * its canonical class; every element Forge does NOT know how to generate is
 * preserved as a {@see RawNode} so round-trip updates never drop or mutate
 * unfamiliar content.
 *
 * ucaddon widgets are handled based on the `$strip_ucaddon` flag:
 *   - false (preserve): captured as RawNode, round-tripped byte-identical
 *   - true (strip):     dropped from the output
 */
final class Parser {

	/** @var bool */
	private bool $strip_ucaddon;

	public function __construct( bool $strip_ucaddon = false ) {
		$this->strip_ucaddon = $strip_ucaddon;
	}

	/**
	 * Parse a full Elementor document array into a {@see Document}.
	 *
	 * @param array<string, mixed> $data
	 */
	public function parse_document( array $data ): Document {
		if ( ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
			throw new InvalidArgumentException( 'Elementor document must have a `content` array.' );
		}
		$title         = isset( $data['title'] ) && is_string( $data['title'] ) ? $data['title'] : '';
		$type          = isset( $data['type'] ) && is_string( $data['type'] ) ? $data['type'] : 'page';
		$page_settings = array();
		if ( isset( $data['page_settings'] ) ) {
			$page_settings = self::to_array( $data['page_settings'] );
		}

		$document = new Document( $title, $type, $page_settings );

		foreach ( $data['content'] as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$parsed = $this->parse_element( $element );
			if ( null !== $parsed ) {
				$document->append( $parsed );
			}
		}

		return $document;
	}

	/**
	 * Parse a single element (container or widget) recursively.
	 *
	 * @param array<string, mixed> $element
	 */
	public function parse_element( array $element ): ?Node {
		$el_type = $element['elType'] ?? '';

		if ( 'container' === $el_type ) {
			return $this->parse_container( $element );
		}

		if ( 'widget' === $el_type ) {
			$widget_type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';

			if ( Widgets\UcaddonShim::is_ucaddon( $widget_type ) ) {
				if ( $this->strip_ucaddon ) {
					return null;
				}
				return new RawNode( $element );
			}

			// Widget types may have nested element subtrees (nested-carousel,
			// nested-accordion). If strip mode is active, we need to recursively
			// drop any ucaddon children even inside those subtrees. Otherwise
			// the widget round-trips as a RawNode to guarantee byte-identical
			// preservation.
			if ( $this->strip_ucaddon && isset( $element['elements'] ) && is_array( $element['elements'] ) && ! empty( $element['elements'] ) ) {
				$element['elements'] = $this->strip_ucaddon_recursive( $element['elements'] );
			}

			return new RawNode( $element );
		}

		// Unknown elType — preserve opaquely.
		return new RawNode( $element );
	}

	/**
	 * @param array<string, mixed> $element
	 */
	private function parse_container( array $element ): Container {
		$id       = isset( $element['id'] ) && is_string( $element['id'] ) ? $element['id'] : null;
		$settings = isset( $element['settings'] ) ? self::to_array( $element['settings'] ) : array();
		$container = new Container( $settings, $id );

		if ( isset( $element['isInner'] ) && true === $element['isInner'] ) {
			$container->mark_inner();
		}

		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$child_node = $this->parse_element( $child );
				if ( null !== $child_node ) {
					$container->add_child( $child_node );
				}
			}
		}

		return $container;
	}

	/**
	 * Recursively drop ucaddon widgets from an array of raw elements. Used
	 * when {@see $strip_ucaddon} is true and the parent is a widget (which we
	 * otherwise preserve as a RawNode).
	 *
	 * @param list<mixed> $elements
	 * @return list<array<string, mixed>>
	 */
	private function strip_ucaddon_recursive( array $elements ): array {
		$out = array();
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			/** @var array<string, mixed> $el */
			$widget_type = isset( $el['widgetType'] ) && is_string( $el['widgetType'] ) ? $el['widgetType'] : '';
			if ( 'widget' === ( $el['elType'] ?? '' ) && Widgets\UcaddonShim::is_ucaddon( $widget_type ) ) {
				continue;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) && ! empty( $el['elements'] ) ) {
				$el['elements'] = $this->strip_ucaddon_recursive( $el['elements'] );
			}
			$out[] = $el;
		}
		return $out;
	}

	/**
	 * Coerce mixed input to a plain associative array. Handles both real arrays
	 * and stdClass objects (which is what `json_decode( ..., false )` emits).
	 *
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	private static function to_array( $value ): array {
		if ( is_array( $value ) ) {
			/** @var array<string, mixed> */
			return $value;
		}
		if ( is_object( $value ) ) {
			/** @var array<string, mixed> */
			return (array) $value;
		}
		return array();
	}
}
