<?php
/**
 * Settings store — single read/write path for the four plugin toggles.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Settings;

use ElementorForge\Safety\Allowlist;
use ElementorForge\Safety\Mode;

/**
 * Wraps the WP options API with a typed accessor for the plugin settings.
 * Every read from the option is merged with {@see Defaults::all()} so callers
 * never have to handle partial arrays left behind by hand-edited wp_options
 * rows. In v0.4.0 the store grew two safety keys (safety_mode,
 * safety_allowed_post_ids) that back the scope_mode gate — these are
 * sanitized through the same pipeline as the original four toggles.
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

		// Safety mode: restrict to the three Mode constants. Invalid input
		// falls through to Mode::FULL via the Defaults merge — we do not
		// write "full" here because that would silently rewrite a hand-edited
		// invalid value into the default, masking the issue.
		if ( isset( $input['safety_mode'] ) && is_string( $input['safety_mode'] ) && Mode::is_valid( $input['safety_mode'] ) ) {
			$clean['safety_mode'] = $input['safety_mode'];
		}

		// Safety allowlist: CSV normalized through the Allowlist value object
		// so we strip whitespace, dedupe, and reject non-positive ints before
		// writing to the option row.
		if ( isset( $input['safety_allowed_post_ids'] ) && is_string( $input['safety_allowed_post_ids'] ) ) {
			$clean['safety_allowed_post_ids'] = Allowlist::from_string( $input['safety_allowed_post_ids'] )->to_string();
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

	/**
	 * Typed safety accessors. Callers should prefer these over raw get() so
	 * the fallback-to-default logic lives in one place.
	 */
	public static function safety_mode(): string {
		$stored = self::get( 'safety_mode' );
		return Mode::is_valid( $stored ) ? $stored : Mode::FULL;
	}

	public static function safety_allowlist(): Allowlist {
		return Allowlist::from_string( self::get( 'safety_allowed_post_ids' ) );
	}

	public static function is_read_only_mode(): bool {
		return Mode::READ_ONLY === self::safety_mode();
	}

	public static function is_page_only_mode(): bool {
		return Mode::PAGE_ONLY === self::safety_mode();
	}
}
