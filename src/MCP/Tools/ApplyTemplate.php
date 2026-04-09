<?php
/**
 * MCP tool: apply_template.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\CPT\PostTypes;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Creates a CPT post (Location or Service), populates its ACF fields, and
 * — because the Theme Builder Single templates installed by the wizard use
 * display conditions targeting the CPT — the correct Single automatically
 * renders on front-end requests. No per-post template assignment required.
 */
final class ApplyTemplate {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'cpt', 'post_data' ),
			'additionalProperties' => false,
			'properties'           => array(
				'cpt'       => array( 'type' => 'string', 'enum' => array( PostTypes::LOCATION, PostTypes::SERVICE ) ),
				'post_data' => array(
					'type'       => 'object',
					'required'   => array( 'title' ),
					'properties' => array(
						'title'      => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'default' => 'draft' ),
						'acf_fields' => array( 'type' => 'object' ),
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
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input ) {
		$gate = Gate::check( 'apply_template', Gate::ACTION_CREATE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$cpt = isset( $input['cpt'] ) && is_string( $input['cpt'] ) ? $input['cpt'] : '';
		if ( ! in_array( $cpt, array( PostTypes::LOCATION, PostTypes::SERVICE ), true ) ) {
			return new WP_Error( 'elementor_forge_invalid_cpt', 'Invalid cpt.' );
		}

		$post_data  = isset( $input['post_data'] ) && is_array( $input['post_data'] ) ? $input['post_data'] : array();
		$title      = isset( $post_data['title'] ) && is_string( $post_data['title'] ) ? sanitize_text_field( $post_data['title'] ) : '';
		$status     = isset( $post_data['status'] ) && is_string( $post_data['status'] ) ? $post_data['status'] : 'draft';
		$acf_fields = isset( $post_data['acf_fields'] ) && is_array( $post_data['acf_fields'] ) ? $post_data['acf_fields'] : array();

		if ( '' === $title ) {
			return new WP_Error( 'elementor_forge_missing_title', 'post_data.title is required.' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => $cpt,
				'post_title'  => $title,
				'post_status' => $status,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = (int) $post_id;

		if ( function_exists( 'update_field' ) ) {
			foreach ( $acf_fields as $key => $value ) {
				if ( is_string( $key ) ) {
					update_field( $key, $value, $post_id );
				}
			}
		}

		return array(
			'post_id' => $post_id,
			'url'     => (string) get_permalink( $post_id ),
		);
	}
}
