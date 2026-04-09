<?php
/**
 * Settings store — single read/write path for the four plugin toggles.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Settings;

/**
 * Wraps the WP options API with a typed accessor for the four locked toggles
 * (acf_mode, ucaddon_shim, mcp_server, header_pattern). Every read from the
 * option is merged with {@see Defaults::all()} so callers never have to handle
 * partial arrays left behind by hand-edited wp_options rows.
 */
final class Store {

	/**
	 * Returns every setting, falling back to defaults for any missing key.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		$stored = get_option( OptionKeys::SETTINGS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( Defaults::all(), $stored );
	}

	/**
	 * Returns a single setting by key.
	 */
	public static function get( string $key ): string {
		$all = self::all();
		return isset( $all[ $key ] ) && is_string( $all[ $key ] ) ? $all[ $key ] : '';
	}

	/**
	 * Persist a partial settings update. Merges onto existing stored values.
	 *
	 * @param array<string, string> $partial Keys + values to overwrite.
	 */
	public static function update( array $partial ): bool {
		$current = self::all();
		$next    = array_merge( $current, self::sanitize( $partial ) );
		return (bool) update_option( OptionKeys::SETTINGS, $next, false );
	}

	/**
	 * Reset all settings to defaults.
	 */
	public static function reset(): bool {
		return (bool) update_option( OptionKeys::SETTINGS, Defaults::all(), false );
	}

	/**
	 * Coerce incoming values to the canonical enum strings. Unknown keys are dropped.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, string>
	 */
	public static function sanitize( array $input ): array {
		$clean = array();

		if ( isset( $input['acf_mode'] ) && in_array( $input['acf_mode'], array( Defaults::ACF_MODE_FREE, Defaults::ACF_MODE_PRO ), true ) ) {
			$clean['acf_mode'] = (string) $input['acf_mode'];
		}
		if ( isset( $input['ucaddon_shim'] ) && in_array( $input['ucaddon_shim'], array( Defaults::UCADDON_SHIM_PRESERVE, Defaults::UCADDON_SHIM_STRIP ), true ) ) {
			$clean['ucaddon_shim'] = (string) $input['ucaddon_shim'];
		}
		if ( isset( $input['mcp_server'] ) && in_array( $input['mcp_server'], array( Defaults::MCP_SERVER_ENABLED, Defaults::MCP_SERVER_DISABLED ), true ) ) {
			$clean['mcp_server'] = (string) $input['mcp_server'];
		}
		if ( isset( $input['header_pattern'] ) && in_array( $input['header_pattern'], array( Defaults::HEADER_PATTERN_SERVICE_BUSINESS, Defaults::HEADER_PATTERN_ECOMMERCE ), true ) ) {
			$clean['header_pattern'] = (string) $input['header_pattern'];
		}

		return $clean;
	}

	/**
	 * Convenience booleans.
	 */
	public static function is_mcp_enabled(): bool {
		return Defaults::MCP_SERVER_ENABLED === self::get( 'mcp_server' );
	}

	public static function is_acf_pro_mode(): bool {
		return Defaults::ACF_MODE_PRO === self::get( 'acf_mode' );
	}

	public static function is_ucaddon_preserve(): bool {
		return Defaults::UCADDON_SHIM_PRESERVE === self::get( 'ucaddon_shim' );
	}

	public static function is_ecommerce_header(): bool {
		return Defaults::HEADER_PATTERN_ECOMMERCE === self::get( 'header_pattern' );
	}
}
