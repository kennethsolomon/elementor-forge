<?php
/**
 * MCP tool: create_footer.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\CacheClearer;
use ElementorForge\Elementor\Footer\FooterPresets;
use ElementorForge\Elementor\ThemeBuilder\Installer;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Creates a Theme Builder footer template from a preset with optional
 * overrides. Works identically to create_header but for footer templates.
 *
 * Prompting hint: "Create a multi-column footer with dark background" or
 * "Create a newsletter footer with custom copyright text."
 */
final class CreateFooter {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'preset' ),
			'additionalProperties' => false,
			'properties'           => array(
				'preset'    => array(
					'type'        => 'string',
					'enum'        => FooterPresets::PRESETS,
					'description' => 'Footer preset: simple, multi_column, minimal, or newsletter.',
				),
				'overrides' => array(
					'type'        => 'object',
					'description' => 'Override the preset. Keys: background_color (hex), copyright_text (HTML string).',
					'properties'  => array(
						'background_color' => array( 'type' => 'string' ),
						'copyright_text'   => array( 'type' => 'string' ),
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
				'preset'  => array( 'type' => 'string' ),
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
		$gate = Gate::check( 'create_footer', Gate::ACTION_CREATE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$preset = isset( $input['preset'] ) && is_string( $input['preset'] ) ? $input['preset'] : 'simple';
		if ( ! in_array( $preset, FooterPresets::PRESETS, true ) ) {
			return new WP_Error( 'elementor_forge_invalid_preset', 'Invalid footer preset. Available: ' . implode( ', ', FooterPresets::PRESETS ) );
		}

		$overrides = isset( $input['overrides'] ) && is_array( $input['overrides'] ) ? $input['overrides'] : array();

		$spec      = FooterPresets::build( $preset, $overrides );
		$installer = new Installer();
		$post_id   = $installer->install_one( $spec );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'elementor_forge_footer_install_failed', 'Failed to install footer template.' );
		}

		CacheClearer::clear( $post_id );

		return array(
			'post_id' => $post_id,
			'preset'  => $preset,
			'url'     => (string) get_permalink( $post_id ),
		);
	}
}
