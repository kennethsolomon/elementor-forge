<?php
/**
 * MCP tool: edit_section.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\CacheClearer;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\Encoder;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Replaces a section (top-level container) on an existing Elementor page.
 * Target by section_index (0-based) or section_id.
 *
 * Prompting hint: "Replace the hero section (index 0) on page 42 with a
 * new hero block." Use get_page_structure first to find the right index.
 */
final class EditSection {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'block' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'section_index' => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Zero-based index of the section to replace.' ),
				'section_id'    => array( 'type' => 'string', 'description' => 'Elementor element ID of the section to replace.' ),
				'block'         => array( 'type' => 'object', 'description' => 'New block content (same format as create_page blocks).' ),
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
				'post_id'  => array( 'type' => 'integer' ),
				'replaced' => array( 'type' => 'boolean' ),
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

		$gate = Gate::check( 'edit_section', Gate::ACTION_MODIFY, $post_id );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$block = isset( $input['block'] ) && is_array( $input['block'] ) ? $input['block'] : array();
		if ( empty( $block ) ) {
			return new WP_Error( 'elementor_forge_missing_block', 'block payload is required.' );
		}

		$content = self::read_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$target_index = self::resolve_index( $input, $content );
		if ( null === $target_index ) {
			return new WP_Error( 'elementor_forge_section_not_found', 'Section not found by index or ID.' );
		}

		$container = ( new Emitter() )->emit_block( $block );
		if ( null === $container ) {
			return new WP_Error( 'elementor_forge_unknown_block', 'Unknown block type.' );
		}

		$content[ $target_index ] = $container->to_array();
		self::write_content( $post_id, $content );

		return array( 'post_id' => $post_id, 'replaced' => true );
	}

	/**
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public static function read_content( int $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new WP_Error( 'elementor_forge_no_elementor_data', 'Post has no Elementor data.' );
		}
		$content = Encoder::decode_from_meta( $raw );
		if ( empty( $content ) ) {
			return new WP_Error( 'elementor_forge_decode_failed', 'Failed to decode Elementor data.' );
		}
		return $content;
	}

	/**
	 * @param list<array<string, mixed>> $content
	 */
	public static function write_content( int $post_id, array $content ): void {
		$slashed = Encoder::encode_for_meta( $content );
		update_post_meta( $post_id, '_elementor_data', $slashed );
		CacheClearer::clear( $post_id );
	}

	/**
	 * @param array<string, mixed>       $input
	 * @param list<array<string, mixed>> $content
	 */
	public static function resolve_index( array $input, array $content ): ?int {
		// By index.
		if ( isset( $input['section_index'] ) && is_numeric( $input['section_index'] ) ) {
			$idx = (int) $input['section_index'];
			if ( $idx >= 0 && $idx < count( $content ) ) {
				return $idx;
			}
			return null;
		}

		// By ID.
		if ( isset( $input['section_id'] ) && is_string( $input['section_id'] ) ) {
			$target_id = $input['section_id'];
			foreach ( $content as $idx => $element ) {
				if ( isset( $element['id'] ) && $element['id'] === $target_id ) {
					return $idx;
				}
			}
			return null;
		}

		return null;
	}
}
