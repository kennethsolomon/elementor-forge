<?php
/**
 * MCP tool: configure_woocommerce.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\WooCommerce\WooCommerce;
use WP_Error;

/**
 * Remote tool that applies the Phase 2 WooCommerce configuration in one call.
 * Designed to be idempotent: repeated invocations produce the same state.
 *
 * Behavior:
 *
 *   - Rejects when WooCommerce is not present (returns WP_Error).
 *   - Installs the four WC Theme Builder templates if missing, updates them
 *     in place if already installed (via the shared idempotent installer).
 *   - Applies the Fibosearch default settings if Fibosearch is present;
 *     otherwise returns a "skipped" entry with a reason.
 *   - Switches the `header_pattern` setting to `ecommerce` and installs the
 *     ecommerce header variant.
 *   - Returns a structured report with a per-step status so the caller can
 *     surface exactly what was applied vs what was skipped.
 *
 * Capability: `manage_woocommerce`. Every other Forge tool uses
 * `manage_options` but this one touches WC state directly, so the WC-specific
 * capability is the correct match — it is the cap WooCommerce assigns to
 * shop managers by default.
 */
final class ConfigureWooCommerce {

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array(),
			'additionalProperties' => false,
			'properties'           => array(
				'install_templates' => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Install the four WooCommerce Theme Builder templates.',
				),
				'apply_fibosearch'  => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Apply Forge default settings to Fibosearch if it is present.',
				),
				'switch_header'     => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Switch header_pattern to ecommerce and install the ecommerce header variant.',
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
				'wc_active'  => array( 'type' => 'boolean' ),
				'templates'  => array( 'type' => 'object' ),
				'fibosearch' => array( 'type' => 'object' ),
				'header'     => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * Capability check. Uses `manage_woocommerce` (the WC shop-manager cap)
	 * rather than `manage_options` because this tool touches WC state.
	 * If WooCommerce is not present `manage_woocommerce` is not registered;
	 * fall back to `manage_options` in that case so admins can still run the
	 * tool's WP_Error response path for diagnostic purposes.
	 */
	public static function permission(): bool {
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input ) {
		if ( ! WooCommerce::is_wc_active() ) {
			return new WP_Error(
				'elementor_forge_wc_missing',
				'WooCommerce is not active — configure_woocommerce cannot run. Install and activate WooCommerce first.'
			);
		}

		$install_templates = self::bool_flag( $input, 'install_templates', true );
		$apply_fibosearch  = self::bool_flag( $input, 'apply_fibosearch', true );
		$switch_header     = self::bool_flag( $input, 'switch_header', true );

		$wc = new WooCommerce();

		$templates  = array( 'status' => 'skipped', 'reason' => 'install_templates flag was false' );
		$fibosearch = array( 'status' => 'skipped', 'reason' => 'apply_fibosearch flag was false' );
		$header     = array( 'status' => 'skipped', 'reason' => 'switch_header flag was false' );

		if ( $install_templates ) {
			$installed = $wc->install_templates();
			$templates = array(
				'status'    => 'installed',
				'installed' => $installed,
			);
		}

		if ( $apply_fibosearch ) {
			$fibo_raw = $wc->apply_fibosearch_defaults();
			$fibosearch = $fibo_raw['applied']
				? array(
					'status'         => 'applied',
					'applied'        => true,
					'reason'         => $fibo_raw['reason'],
					'keys_updated'   => $fibo_raw['keys_updated'],
					'keys_preserved' => $fibo_raw['keys_preserved'],
				)
				: array(
					'status'  => 'skipped',
					'applied' => false,
					'reason'  => $fibo_raw['reason'],
				);
		}

		if ( $switch_header ) {
			$post_id = $wc->switch_to_ecommerce_header();
			$header  = $post_id > 0
				? array( 'status' => 'installed', 'post_id' => $post_id )
				: array( 'status' => 'failed', 'reason' => 'base installer returned 0 for ecommerce header spec' );
		}

		return array(
			'wc_active'  => true,
			'templates'  => $templates,
			'fibosearch' => $fibosearch,
			'header'     => $header,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private static function bool_flag( array $input, string $key, bool $fallback ): bool {
		if ( ! array_key_exists( $key, $input ) ) {
			return $fallback;
		}
		$value = $input[ $key ];
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}
		if ( is_int( $value ) ) {
			return $value > 0;
		}
		return $fallback;
	}
}
