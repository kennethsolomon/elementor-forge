<?php
/**
 * MCP tool: add_section.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\Encoder;
use ElementorForge\Elementor\Emitter\Parser;
use ElementorForge\Settings\Store;
use WP_Error;

/**
 * Appends a single block (emitted as a container) to an existing Elementor page.
 * Preserves the rest of the page by parsing the existing `_elementor_data`,
 * appending a new top-level container from the block, and writing back through
 * the encoder so the slash dance stays intact.
 */
final class AddSection {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'page_id', 'block' ),
			'additionalProperties' => false,
			'properties'           => array(
				'page_id' => array( 'type' => 'integer', 'minimum' => 1 ),
				'block'   => array( 'type' => 'object' ),
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
				'appended' => array( 'type' => 'boolean' ),
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
		$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
		$block   = isset( $input['block'] ) && is_array( $input['block'] ) ? $input['block'] : array();

		if ( $page_id <= 0 ) {
			return new WP_Error( 'elementor_forge_invalid_page', 'Invalid page_id.' );
		}
		if ( empty( $block ) ) {
			return new WP_Error( 'elementor_forge_missing_block', 'block payload is required.' );
		}

		$parser   = new Parser( ! Store::is_ucaddon_preserve() );
		$document = Encoder::read_document( $page_id, $parser );
		if ( null === $document ) {
			$title    = get_the_title( $page_id );
			$document = new Document( is_string( $title ) ? $title : '', 'page' );
		}

		$container = ( new Emitter() )->emit_block( $block );
		if ( null === $container ) {
			return new WP_Error( 'elementor_forge_unknown_block', 'Unknown block type.' );
		}
		$document->append( $container );

		$ok = Encoder::write_document( $page_id, $document );

		return array(
			'post_id'  => $page_id,
			'appended' => $ok,
		);
	}
}
