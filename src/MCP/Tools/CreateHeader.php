<?php
/**
 * MCP tool: create_header.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Elementor\CacheClearer;
use ElementorForge\Elementor\Header\HeaderPresets;
use ElementorForge\Elementor\ThemeBuilder\Installer;
use ElementorForge\Safety\Gate;
use WP_Error;

/**
 * Creates a Theme Builder header template from a preset with optional
 * overrides. The header is installed as an `elementor_library` post with
 * `include/general` display conditions so it applies site-wide.
 *
 * Prompting hint: Use a preset name for quick setup, or provide rows[] to
 * fully customize the layout. Example: "Create a business header" or
 * "Create a header with logo centered in row 1, search bar and contact
 * button in row 2."
 */
final class CreateHeader {

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
					'enum'        => HeaderPresets::PRESETS,
					'description' => 'Header preset: business, ecommerce, portfolio, blog, or saas.',
				),
				'overrides' => array(
					'type'        => 'object',
					'description' => 'Override the preset layout. Keys: rows (array of row specs), background_color (hex), sticky (bool or {enabled, shrink}), transparent (bool).',
					'properties'  => array(
						'rows'             => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'items'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Item keywords: logo, logo_center, nav, hamburger, search, cart, account, button:Label, text:Content' ),
									'align'        => array( 'type' => 'string', 'enum' => array( 'center', 'space-between', 'space-around', 'flex-start', 'flex-end' ) ),
									'background'   => array( 'type' => 'string', 'description' => 'Hex background color for this row.' ),
									'hide_mobile'  => array( 'type' => 'boolean' ),
									'hide_desktop' => array( 'type' => 'boolean' ),
								),
							),
						),
						'background_color' => array( 'type' => 'string' ),
						'sticky'           => array(),
						'transparent'      => array( 'type' => 'boolean' ),
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
		$gate = Gate::check( 'create_header', Gate::ACTION_CREATE );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$preset = isset( $input['preset'] ) && is_string( $input['preset'] ) ? $input['preset'] : 'business';
		if ( ! in_array( $preset, HeaderPresets::PRESETS, true ) ) {
			return new WP_Error( 'elementor_forge_invalid_preset', 'Invalid header preset. Available: ' . implode( ', ', HeaderPresets::PRESETS ) );
		}

		$overrides = isset( $input['overrides'] ) && is_array( $input['overrides'] ) ? $input['overrides'] : array();

		$spec     = HeaderPresets::build( $preset, $overrides );
		$installer = new Installer();
		$post_id  = $installer->install_one( $spec );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'elementor_forge_header_install_failed', 'Failed to install header template.' );
		}

		CacheClearer::clear( $post_id );

		return array(
			'post_id' => $post_id,
			'preset'  => $preset,
			'url'     => (string) get_permalink( $post_id ),
		);
	}
}
