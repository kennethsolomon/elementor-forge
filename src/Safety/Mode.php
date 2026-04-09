<?php
/**
 * Scope mode enum for the Elementor Forge safety feature.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Safety;

/**
 * Canonical scope modes for Forge's plugin-level safety gate.
 *
 * Three discrete modes control the blast radius of every MCP write tool and
 * the onboarding wizard:
 *
 *   - {@see Mode::FULL}      — default. All tools enabled. Wizard runs.
 *                              Site-wide actions (configure_woocommerce) allowed.
 *   - {@see Mode::PAGE_ONLY} — Wizard disabled. configure_woocommerce rejected.
 *                              add_section only modifies posts in the allowlist.
 *   - {@see Mode::READ_ONLY} — Every MCP write tool returns WP_Error. Diagnostic.
 *
 * The enum is a final class of string constants (not a PHP 8.1 native enum) to
 * stay compatible with the plugin's PHP 8.0 minimum.
 */
final class Mode {

	public const FULL      = 'full';
	public const PAGE_ONLY = 'page_only';
	public const READ_ONLY = 'read_only';

	/**
	 * Every valid mode string.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::FULL, self::PAGE_ONLY, self::READ_ONLY );
	}

	public static function is_valid( string $mode ): bool {
		return in_array( $mode, self::all(), true );
	}

	/**
	 * Human-readable label for the settings UI.
	 */
	public static function label( string $mode ): string {
		switch ( $mode ) {
			case self::FULL:
				return 'Full (site-wide)';
			case self::PAGE_ONLY:
				return 'Page-only (allowlisted)';
			case self::READ_ONLY:
				return 'Read-only (diagnostic)';
			default:
				return 'Unknown';
		}
	}

	/**
	 * Tailwind-ish color class for the settings UI badge. Green = safe default,
	 * yellow = restricted, red = diagnostic lockdown. Callers map this to their
	 * own CSS — the string is treated as an opaque token.
	 */
	public static function color( string $mode ): string {
		switch ( $mode ) {
			case self::FULL:
				return 'green';
			case self::PAGE_ONLY:
				return 'yellow';
			case self::READ_ONLY:
				return 'red';
			default:
				return 'gray';
		}
	}
}
