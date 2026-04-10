<?php
/**
 * MCP tool: create_page.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\CacheClearer;
use ElementorForge\Elementor\Emitter\ContentDoc;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\Encoder;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Builds a fresh Elementor page from a content doc. Pure — no side effects
 * beyond the wp_insert_post + update_post_meta calls in {@see execute()}.
 */
final class CreatePage {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'title', 'content_doc' ),
			'additionalProperties' => false,
			'properties'           => array(
				'title'       => array( 'type' => 'string', 'minLength' => 1 ),
				'status'      => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'default' => 'draft' ),
				'content_doc' => array(
					'type'       => 'object',
					'required'   => array( 'title', 'blocks' ),
					'properties' => array(
						'title'  => array( 'type' => 'string' ),
						'blocks' => array( 'type' => 'array' ),
					),
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
				'post_id' => array( 'type' => 'integer' ),
				'url'     => array( 'type' => 'string' ),
			),
		);
	}

	public static function permission(): bool {
		return current_user_can( 'publish_pages' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input ) {
		$gate = Gate::check( 'create_page', Gate::ACTION_CREATE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$title       = isset( $input['title'] ) && is_string( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$status      = isset( $input['status'] ) && is_string( $input['status'] ) ? $input['status'] : 'draft';
		$content_doc = isset( $input['content_doc'] ) && is_array( $input['content_doc'] ) ? $input['content_doc'] : array();

		if ( '' === $title ) {
			return new WP_Error( 'elementor_forge_missing_title', 'Page title is required.' );
		}

		$doc = ContentDoc::from_array( $content_doc );

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'page',
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = (int) $post_id;

		$document = ( new Emitter() )->emit( $doc );
		Encoder::write_document( $post_id, $document );

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.20.0' );
		update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );

		CacheClearer::clear( $post_id );

		return array(
			'post_id' => $post_id,
			'url'     => (string) get_permalink( $post_id ),
		);
	}
}
