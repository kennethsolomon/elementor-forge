<?php
/**
 * MCP tool: duplicate_section.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\Emitter\Node;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Deep-clones a top-level section and inserts it after the original (or at
 * a specified position). All element IDs are regenerated to avoid conflicts.
 *
 * Prompting hint: "Duplicate the hero section on page 42."
 */
final class DuplicateSection {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'section_index' => array( 'type' => 'integer', 'minimum' => 0 ),
				'section_id'    => array( 'type' => 'string' ),
				'insert_after'  => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Insert the clone after this index. Defaults to right after the original.' ),
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
				'post_id'    => array( 'type' => 'integer' ),
				'duplicated' => array( 'type' => 'boolean' ),
				'new_index'  => array( 'type' => 'integer' ),
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

		$gate = Gate::check( 'duplicate_section', Gate::ACTION_MODIFY, $post_id );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$content = EditSection::read_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$target_index = EditSection::resolve_index( $input, $content );
		if ( null === $target_index ) {
			return new WP_Error( 'elementor_forge_section_not_found', 'Section not found by index or ID.' );
		}

		$clone = self::deep_clone( $content[ $target_index ] );

		$insert_at = isset( $input['insert_after'] ) && is_numeric( $input['insert_after'] )
			? (int) $input['insert_after'] + 1
			: $target_index + 1;

		$insert_at = max( 0, min( $insert_at, count( $content ) ) );
		array_splice( $content, $insert_at, 0, array( $clone ) );

		EditSection::write_content( $post_id, $content );

		return array(
			'post_id'    => $post_id,
			'duplicated' => true,
			'new_index'  => $insert_at,
		);
	}

	/**
	 * Deep-clone an element tree, regenerating all IDs.
	 *
	 * @param array<string, mixed> $element
	 * @return array<string, mixed>
	 */
	private static function deep_clone( array $element ): array {
		$element['id'] = Node::generate_id();

		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as &$child ) {
				if ( is_array( $child ) ) {
					$child = self::deep_clone( $child );
				}
			}
			unset( $child );
		}

		return $element;
	}
}
