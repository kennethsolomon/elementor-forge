<?php
/**
 * Central enforcement gate for the Elementor Forge safety feature.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Safety;

use ElementorForge\Settings\Store;
use WP_Error;

/**
 * Gate::check() is the single chokepoint every MCP write tool calls before
 * it touches WordPress state. Returns true when the action is allowed under
 * the current scope mode + allowlist, or a typed {@see WP_Error} with a
 * specific error code when the action is blocked. The error codes are
 * stable — tests assert on them, the settings UI surfaces them, and any
 * future caller can branch on them instead of string-matching a message.
 *
 * Decision matrix (one row per tool, one cell per mode):
 *
 *   | Tool                     | action_type | full   | page_only                                    | read_only |
 *   | ------------------------ | ----------- | ------ | -------------------------------------------- | --------- |
 *   | create_page              | create      | allow  | allow                                        | REJECT    |
 *   | add_section              | modify      | allow  | allow IFF allowlist.contains(post_id);       | REJECT    |
 *   |                          |             |        | empty allowlist → REJECT                     |           |
 *   | apply_template           | create      | allow  | allow                                        | REJECT    |
 *   | bulk_generate_pages      | create      | allow  | allow                                        | REJECT    |
 *   | configure_woocommerce    | site_wide   | allow  | REJECT                                       | REJECT    |
 *   | manage_slider            | modify      | allow  | allow (sliders are not posts)                | REJECT    |
 *
 * Error codes returned from the rejection paths:
 *
 *   - elementor_forge_read_only_mode
 *   - elementor_forge_site_wide_in_page_only
 *   - elementor_forge_allowlist_empty_in_page_only
 *   - elementor_forge_post_not_in_allowlist
 */
final class Gate {

	public const ACTION_CREATE    = 'create';
	public const ACTION_MODIFY    = 'modify';
	public const ACTION_SITE_WIDE = 'site_wide';

	public const ERR_READ_ONLY              = 'elementor_forge_read_only_mode';
	public const ERR_SITE_WIDE_IN_PAGE_ONLY = 'elementor_forge_site_wide_in_page_only';
	public const ERR_ALLOWLIST_EMPTY        = 'elementor_forge_allowlist_empty_in_page_only';
	public const ERR_POST_NOT_IN_ALLOWLIST  = 'elementor_forge_post_not_in_allowlist';

	/**
	 * Check if the given tool action is allowed under the current scope mode.
	 *
	 * @param string   $tool_name      Tool identifier (e.g. 'add_section'). Used for
	 *                                 the 'manage_slider' special case and for
	 *                                 including context in rejection messages.
	 * @param string   $action_type    One of {@see self::ACTION_CREATE},
	 *                                 {@see self::ACTION_MODIFY},
	 *                                 {@see self::ACTION_SITE_WIDE}.
	 * @param int|null $target_post_id Required when $action_type === ACTION_MODIFY
	 *                                 and $tool_name is a post-scoped tool. Null
	 *                                 otherwise.
	 *
	 * @return true|WP_Error true when allowed; WP_Error with a stable error code
	 *                       when blocked.
	 */
	public static function check( string $tool_name, string $action_type, ?int $target_post_id = null ) {
		$mode = self::current_mode();

		if ( Mode::READ_ONLY === $mode ) {
			return new WP_Error(
				self::ERR_READ_ONLY,
				sprintf(
					'Tool "%s" blocked: Elementor Forge is in read_only scope mode. All write tools are disabled.',
					$tool_name
				)
			);
		}

		if ( Mode::FULL === $mode ) {
			return true;
		}

		// page_only below.
		if ( self::ACTION_SITE_WIDE === $action_type ) {
			return new WP_Error(
				self::ERR_SITE_WIDE_IN_PAGE_ONLY,
				sprintf(
					'Tool "%s" blocked: site-wide actions are not allowed in page_only scope mode. Switch to full mode to run this tool.',
					$tool_name
				)
			);
		}

		if ( self::ACTION_CREATE === $action_type ) {
			// Creating new posts is allowed in page_only — the safety rule is
			// "do not modify posts outside the allowlist", not "do not create
			// any content". New posts are tracked by their own ID and the
			// operator can add them to the allowlist afterwards.
			return true;
		}

		// ACTION_MODIFY.
		// Sliders are not posts and have no post ID, so the allowlist does not
		// apply. Treat manage_slider as allowed in page_only. (A Phase 1.5
		// follow-up can add a slider_id allowlist if needed.)
		if ( 'manage_slider' === $tool_name ) {
			return true;
		}

		$allowlist = Store::safety_allowlist();
		if ( $allowlist->is_empty() ) {
			return new WP_Error(
				self::ERR_ALLOWLIST_EMPTY,
				sprintf(
					'Tool "%s" blocked: page_only scope mode requires a non-empty post ID allowlist. Populate the allowlist in Elementor Forge → Settings → Safety.',
					$tool_name
				)
			);
		}

		$post_id = null === $target_post_id ? 0 : $target_post_id;
		if ( $post_id <= 0 || ! $allowlist->contains( $post_id ) ) {
			return new WP_Error(
				self::ERR_POST_NOT_IN_ALLOWLIST,
				sprintf(
					'Tool "%s" blocked: post_id %d is not in the safety allowlist [%s]. Add the post ID to the allowlist to modify this page.',
					$tool_name,
					$post_id,
					$allowlist->to_string()
				)
			);
		}

		return true;
	}

	/**
	 * Whether the onboarding wizard is enabled under the current scope mode.
	 * The wizard is site-wide by definition — it installs templates, registers
	 * CPTs, and writes Theme Builder display conditions — so it is only
	 * available in full mode.
	 */
	public static function is_wizard_enabled(): bool {
		return Mode::FULL === self::current_mode();
	}

	public static function current_mode(): string {
		$mode = Store::safety_mode();
		return Mode::is_valid( $mode ) ? $mode : Mode::FULL;
	}
}
