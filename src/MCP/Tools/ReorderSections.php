<?php
/**
 * MCP tool: reorder_sections.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Reorders top-level sections on an Elementor page.
 *
 * Prompting hint: "Move the CTA section to the end of page 42."
 * Provide the desired order as an array of section indices.
 */
final class ReorderSections {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'order' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'order'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Array of current section indices in the desired new order. E.g. [2, 0, 1] moves section 2 to the top.',
				),
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
				'post_id'   => array( 'type' => 'integer' ),
				'reordered' => array( 'type' => 'boolean' ),
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

		$gate = Gate::check( 'reorder_sections', Gate::ACTION_MODIFY, $post_id );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$order = isset( $input['order'] ) && is_array( $input['order'] ) ? $input['order'] : array();
		if ( empty( $order ) ) {
			return new WP_Error( 'elementor_forge_missing_order', 'order array is required.' );
		}

		$content = EditSection::read_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$count = count( $content );

		// Validate order indices.
		$seen = array();
		foreach ( $order as $idx ) {
			$idx = is_numeric( $idx ) ? (int) $idx : -1;
			if ( $idx < 0 || $idx >= $count ) {
				return new WP_Error( 'elementor_forge_invalid_order', "Index $idx is out of range (0-" . ( $count - 1 ) . ').' );
			}
			if ( isset( $seen[ $idx ] ) ) {
				return new WP_Error( 'elementor_forge_duplicate_index', "Duplicate index $idx in order array." );
			}
			$seen[ $idx ] = true;
		}

		if ( count( $order ) !== $count ) {
			return new WP_Error( 'elementor_forge_order_mismatch', 'order must contain exactly ' . $count . ' indices (one per section).' );
		}

		$reordered = array();
		foreach ( $order as $idx ) {
			$reordered[] = $content[ (int) $idx ];
		}

		EditSection::write_content( $post_id, $reordered );

		return array( 'post_id' => $post_id, 'reordered' => true );
	}
}
