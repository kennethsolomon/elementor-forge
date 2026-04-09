<?php
/**
 * MCP tool: bulk_generate_pages.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\CPT\PostTypes;
use WP_Error;

/**
 * Basic Phase 1 implementation of bulk page generation. Loops over a list of
 * content documents and calls {@see ApplyTemplate::execute()} for each. The
 * full intelligence layer (suburbs × services matrix, layout judge, Smart
 * Slider CRUD) lives in Phase 3.
 */
final class BulkGenerate {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'cpt', 'items' ),
			'additionalProperties' => false,
			'properties'           => array(
				'cpt'   => array( 'type' => 'string', 'enum' => array( PostTypes::LOCATION, PostTypes::SERVICE ) ),
				'items' => array(
					'type'     => 'array',
					'minItems' => 1,
					'items'    => array(
						'type'       => 'object',
						'required'   => array( 'title' ),
						'properties' => array(
							'title'      => array( 'type' => 'string' ),
							'acf_fields' => array( 'type' => 'object' ),
						),
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
				'created' => array( 'type' => 'array' ),
				'failed'  => array( 'type' => 'array' ),
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
		$cpt = isset( $input['cpt'] ) && is_string( $input['cpt'] ) ? $input['cpt'] : '';
		if ( ! in_array( $cpt, array( PostTypes::LOCATION, PostTypes::SERVICE ), true ) ) {
			return new WP_Error( 'elementor_forge_invalid_cpt', 'Invalid cpt.' );
		}

		$items = isset( $input['items'] ) && is_array( $input['items'] ) ? $input['items'] : array();

		$created = array();
		$failed  = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$result = ApplyTemplate::execute(
				array(
					'cpt'       => $cpt,
					'post_data' => $item,
				)
			);
			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'title' => isset( $item['title'] ) && is_string( $item['title'] ) ? $item['title'] : '',
					'error' => $result->get_error_message(),
				);
			} else {
				$created[] = $result;
			}
		}

		return array(
			'created' => $created,
			'failed'  => $failed,
		);
	}
}
