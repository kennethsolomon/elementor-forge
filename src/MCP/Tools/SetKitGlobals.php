<?php
/**
 * MCP tool: set_kit_globals.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\Kit\KitWriter;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Sets the Default Kit's global brand palette (colors, typography, button
 * styles). This is the foundation step before creating any pages or headers —
 * all widgets that reference Kit globals will pick up these values.
 *
 * Prompting hint: Call this tool FIRST when setting up a new site. Example:
 * "Set the brand colors to primary=#1a73e8, secondary=#34a853, text=#202124,
 * accent=#ea4335, and headings font to Inter."
 */
final class SetKitGlobals {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'colors'     => array(
					'type'        => 'object',
					'description' => 'Brand color palette. Keys: primary, secondary, text, accent (or custom IDs). Values: hex color strings (#rrggbb).',
				),
				'typography' => array(
					'type'        => 'object',
					'description' => 'Typography settings. Keys: primary (headings), secondary (body), or custom IDs. Each value: {font_family, font_size, font_weight, line_height}.',
				),
				'button'     => array(
					'type'        => 'object',
					'description' => 'Button style overrides. Keys: text_color, background_color, border_color, border_radius, padding, font_family, font_size, font_weight.',
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
				'kit_id'  => array( 'type' => 'integer' ),
				'updated' => array( 'type' => 'object' ),
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
		$gate = Gate::check( 'set_kit_globals', Gate::ACTION_SITE_WIDE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$settings = array();
		if ( isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
			$settings['colors'] = $input['colors'];
		}
		if ( isset( $input['typography'] ) && is_array( $input['typography'] ) ) {
			$settings['typography'] = $input['typography'];
		}
		if ( isset( $input['button'] ) && is_array( $input['button'] ) ) {
			$settings['button'] = $input['button'];
		}

		if ( empty( $settings ) ) {
			return new WP_Error( 'elementor_forge_empty_kit_settings', 'At least one of colors, typography, or button must be provided.' );
		}

		$result = KitWriter::write( $settings );
		if ( $result['kit_id'] <= 0 ) {
			return new WP_Error( 'elementor_forge_no_active_kit', 'No active Elementor Kit found. Ensure Elementor is installed and activated.' );
		}

		return $result;
	}
}
