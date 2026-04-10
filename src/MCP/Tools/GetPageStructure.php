<?php
/**
 * MCP tool: get_page_structure (read-only).
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\Emitter\Encoder;
use WP_Error;

/**
 * Returns an annotated tree of an Elementor page's structure. Read-only —
 * does not modify the page. Works on any post type with `_elementor_data`.
 *
 * Prompting hint: Use this to inspect what's on a page before editing.
 * "Show me the structure of page 42."
 */
final class GetPageStructure {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'The post/page/template ID to inspect.' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'       => array( 'type' => 'integer' ),
				'title'         => array( 'type' => 'string' ),
				'section_count' => array( 'type' => 'integer' ),
				'sections'      => array( 'type' => 'array' ),
			),
		);
	}

	public static function permission(): bool {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input ) {
		// Read-only — no gate check required.
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'elementor_forge_invalid_post', 'Invalid post_id.' );
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new WP_Error( 'elementor_forge_no_elementor_data', 'Post has no Elementor data.' );
		}

		$content = Encoder::decode_from_meta( $raw );
		if ( empty( $content ) ) {
			return new WP_Error( 'elementor_forge_decode_failed', 'Failed to decode Elementor data.' );
		}

		$title    = get_the_title( $post_id );
		$sections = array();
		foreach ( $content as $index => $element ) {
			$sections[] = self::annotate_element( $element, $index );
		}

		return array(
			'post_id'       => $post_id,
			'title'         => is_string( $title ) ? $title : '',
			'section_count' => count( $sections ),
			'sections'      => $sections,
		);
	}

	/**
	 * Annotate an element for the response tree.
	 *
	 * @param array<string, mixed> $element
	 * @return array<string, mixed>
	 */
	private static function annotate_element( array $element, int $index ): array {
		$el_type     = isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : 'unknown';
		$widget_type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : null;
		$el_id       = isset( $element['id'] ) && is_string( $element['id'] ) ? $element['id'] : '';
		$settings    = isset( $element['settings'] ) && ( is_array( $element['settings'] ) || is_object( $element['settings'] ) ) ? (array) $element['settings'] : array();

		$annotation = array(
			'index' => $index,
			'id'    => $el_id,
			'type'  => $el_type,
		);

		if ( null !== $widget_type ) {
			$annotation['widget_type'] = $widget_type;
		}

		// Add content preview for key widget types.
		if ( 'heading' === $widget_type && isset( $settings['title'] ) ) {
			$annotation['preview'] = mb_substr( (string) $settings['title'], 0, 80 );
		} elseif ( 'text-editor' === $widget_type && isset( $settings['editor'] ) ) {
			$annotation['preview'] = mb_substr( wp_strip_all_tags( (string) $settings['editor'] ), 0, 80 );
		} elseif ( 'button' === $widget_type && isset( $settings['text'] ) ) {
			$annotation['preview'] = (string) $settings['text'];
		}

		// Recurse into children.
		$children = isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array();
		if ( ! empty( $children ) ) {
			$annotation['children']     = array();
			$annotation['child_count']  = count( $children );
			foreach ( $children as $i => $child ) {
				if ( is_array( $child ) ) {
					$annotation['children'][] = self::annotate_element( $child, $i );
				}
			}
		}

		return $annotation;
	}
}
