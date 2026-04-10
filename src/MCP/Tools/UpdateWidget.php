<?php
/**
 * MCP tool: update_widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Updates a widget's settings by its Elementor element ID. Walks the full
 * document tree to find the widget, then merges new settings into existing.
 *
 * Prompting hint: "Change the heading text on widget abc123 to 'New Title'."
 * Use get_page_structure to find widget IDs.
 */
final class UpdateWidget {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'widget_id', 'settings' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
				'widget_id' => array( 'type' => 'string', 'description' => 'Elementor element ID of the widget to update.' ),
				'settings'  => array( 'type' => 'object', 'description' => 'Partial settings to merge into the existing widget settings.' ),
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
				'post_id' => array( 'type' => 'integer' ),
				'updated' => array( 'type' => 'boolean' ),
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
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'elementor_forge_invalid_post', 'Invalid post_id.' );
		}

		$gate = Gate::check( 'update_widget', Gate::ACTION_MODIFY, $post_id );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$widget_id    = isset( $input['widget_id'] ) && is_string( $input['widget_id'] ) ? $input['widget_id'] : '';
		$new_settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		if ( '' === $widget_id ) {
			return new WP_Error( 'elementor_forge_missing_widget_id', 'widget_id is required.' );
		}
		if ( empty( $new_settings ) ) {
			return new WP_Error( 'elementor_forge_empty_settings', 'settings must be a non-empty object.' );
		}

		$content = EditSection::read_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$found   = false;
		$content = self::walk_and_update( $content, $widget_id, $new_settings, $found );

		if ( ! $found ) {
			return new WP_Error( 'elementor_forge_widget_not_found', "Widget with ID '$widget_id' not found in document tree." );
		}

		EditSection::write_content( $post_id, $content );

		return array( 'post_id' => $post_id, 'updated' => true );
	}

	/**
	 * Recursively walk the element tree and update the matching widget.
	 *
	 * @param list<array<string, mixed>> $elements
	 * @param array<string, mixed>       $new_settings
	 * @return list<array<string, mixed>>
	 */
	private static function walk_and_update( array $elements, string $widget_id, array $new_settings, bool &$found ): array {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$el_id = isset( $element['id'] ) && is_string( $element['id'] ) ? $element['id'] : '';
			if ( $el_id === $widget_id ) {
				$existing         = isset( $element['settings'] ) && ( is_array( $element['settings'] ) || is_object( $element['settings'] ) ) ? (array) $element['settings'] : array();
				$element['settings'] = (object) array_merge( $existing, $new_settings );
				$found            = true;
				break;
			}

			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk_and_update( $element['elements'], $widget_id, $new_settings, $found );
				if ( $found ) {
					break;
				}
			}
		}
		unset( $element );
		return $elements;
	}
}
