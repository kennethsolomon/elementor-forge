<?php
/**
 * MCP tool: delete_section.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Removes a top-level section from an Elementor page by index or ID.
 *
 * Prompting hint: "Delete the FAQ section (index 3) from page 42."
 */
final class DeleteSection {

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
				'post_id'         => array( 'type' => 'integer' ),
				'deleted'         => array( 'type' => 'boolean' ),
				'remaining_count' => array( 'type' => 'integer' ),
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

		$gate = Gate::check( 'delete_section', Gate::ACTION_MODIFY, $post_id );
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

		array_splice( $content, $target_index, 1 );
		EditSection::write_content( $post_id, $content );

		return array(
			'post_id'         => $post_id,
			'deleted'         => true,
			'remaining_count' => count( $content ),
		);
	}
}
