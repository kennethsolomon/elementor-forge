<?php
/**
 * Fibosearch configuration layer.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\WooCommerce\Fibosearch;

/**
 * Applies sensible default settings to Fibosearch (AJAX Search for WooCommerce
 * by Fibosearch, wp.org slug `ajax-search-for-woocommerce`). Fibosearch stores
 * its configuration in a single `dgwt_wcas_settings` option row — a flat
 * associative array keyed by the plugin's internal field names. This class
 * merges {@see DEFAULTS} onto whatever is currently stored so existing user
 * customizations survive a re-apply.
 *
 * Source of truth for the option key: Fibosearch's
 * `DgoraWcas\Settings\Controllers\DataStore::OPTION_KEY` constant is
 * `dgwt_wcas_settings`. The default values below mirror the wp-admin settings
 * UI labels (search in title + SKU + categories, show product image, show
 * price, fuzzy matching on, mobile overlay on).
 *
 * All Fibosearch calls are gated on {@see self::is_available()} via
 * `function_exists('dgwt_wcas')` — Fibosearch's canonical bootstrap function.
 * The configurator is safe to construct without Fibosearch present; it simply
 * becomes a set of no-ops that return structured results indicating what was
 * and was not applied.
 */
final class Configurator {

	public const OPTION_KEY = 'dgwt_wcas_settings';

	/**
	 * Canonical default settings Forge applies on first detection. These keys
	 * were verified against Fibosearch's own settings schema in
	 * `partials/settings/sections/` — any key not present in that schema is
	 * silently ignored by Fibosearch so no harm in passing extras.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		// Search in which fields.
		'search_in_product_title'          => 1,
		'search_in_product_content'        => 1,
		'search_in_product_excerpt'        => 1,
		'search_in_product_sku'            => 1,
		'search_in_product_categories'     => 1,
		'search_in_product_tags'           => 0,

		// What to show in the results dropdown.
		'show_product_image'               => 1,
		'show_product_price'               => 1,
		'show_product_desc'                => 1,
		'show_product_sku'                 => 0,
		'show_product_vendor'              => 0,
		'show_more_products'               => 1,

		// Suggestions first — products first, then categories.
		'show_product_headline'            => 1,
		'show_product_categories'          => 1,
		'show_product_tags'                => 0,

		// Behavior.
		'is_fuzzy_matching'                => 1,
		'show_matching_fuzziness_level'    => 'normal',
		'min_chars'                        => 2,
		'show_preloader'                   => 1,

		// Mobile.
		'mobile_overlay'                   => 1,
		'mobile_breakpoint'                => 992,
		'mobile_search_icon'               => 1,

		// Performance.
		'enable_https_mixed_content_fixer' => 0,
		'disable_autocomplete'             => 0,
	);

	/**
	 * Apply the Forge defaults to Fibosearch's settings option. Existing
	 * user-configured keys are preserved — if the user has visited a setting
	 * at all (the key exists on the stored option) their value is kept
	 * regardless of whether it is `0`, `1`, an empty string, or any other
	 * value. Only keys completely absent from the stored option are
	 * populated with Forge defaults. This makes the call idempotent and,
	 * crucially, preserves explicit user disables:
	 *
	 *   - Fibosearch stores checkbox state as int 0 / int 1.
	 *   - A user who unticks a checkbox persists int 0 on the option row.
	 *   - The previous sentinel (`'' !== $current[$key]`) treated int 0 as
	 *     "not set" and stomped it back to the default on re-apply. That is
	 *     a real behavior bug (user toggles lost after a re-apply).
	 *   - The correct sentinel is `array_key_exists($key, $current)` — if
	 *     the key is present the user has visited that setting.
	 *
	 * @return array{
	 *     applied: bool,
	 *     reason: string,
	 *     keys_updated: list<string>,
	 *     keys_preserved: list<string>
	 * }
	 */
	public function apply_defaults(): array {
		if ( ! self::is_available() ) {
			return array(
				'applied'        => false,
				'reason'         => 'Fibosearch not detected (function dgwt_wcas missing).',
				'keys_updated'   => array(),
				'keys_preserved' => array(),
			);
		}

		$current = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updated   = array();
		$preserved = array();

		foreach ( self::DEFAULTS as $key => $default_value ) {
			if ( array_key_exists( $key, $current ) ) {
				$preserved[] = $key;
				continue;
			}
			$current[ $key ] = $default_value;
			$updated[]       = $key;
		}

		if ( array() === $updated ) {
			return array(
				'applied'        => true,
				'reason'         => 'All Fibosearch default keys already set — no changes required.',
				'keys_updated'   => array(),
				'keys_preserved' => $preserved,
			);
		}

		update_option( self::OPTION_KEY, $current, false );

		return array(
			'applied'        => true,
			'reason'         => sprintf( 'Applied %d default keys.', count( $updated ) ),
			'keys_updated'   => $updated,
			'keys_preserved' => $preserved,
		);
	}

	/**
	 * Detect whether Forge's Fibosearch defaults have been applied to the
	 * stored option at some point — regardless of whether the user has since
	 * tweaked individual values. Returns `true` when every key in
	 * {@see DEFAULTS} is present on the stored option.
	 *
	 * This is intentionally NOT an equality check against DEFAULTS — a user
	 * who has legitimately customized a value (or cleared a text field to
	 * empty string) should not cause the settings page to report "defaults
	 * out of sync". The question this method answers is "has Forge ever run
	 * apply_defaults() successfully on this install?", not "does the current
	 * state equal the ship-time defaults?". Because apply_defaults() only
	 * writes keys that are entirely missing, the presence of every default
	 * key is the correct signal.
	 */
	public function has_been_applied(): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		$current = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			return false;
		}

		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			if ( ! array_key_exists( $key, $current ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Produce a structured detection report for the settings page debug panel.
	 *
	 * @return array{
	 *     detected: bool,
	 *     version: string,
	 *     option_exists: bool,
	 *     has_been_applied: bool,
	 *     keys_present: int
	 * }
	 */
	public function report(): array {
		if ( ! self::is_available() ) {
			return array(
				'detected'         => false,
				'version'          => '',
				'option_exists'    => false,
				'has_been_applied' => false,
				'keys_present'     => 0,
			);
		}

		$stored        = get_option( self::OPTION_KEY, null );
		$option_exists = is_array( $stored );
		$keys_present  = $option_exists ? count( $stored ) : 0;

		return array(
			'detected'         => true,
			'version'          => defined( 'DGWT_WCAS_VERSION' ) ? (string) constant( 'DGWT_WCAS_VERSION' ) : 'unknown',
			'option_exists'    => $option_exists,
			'has_been_applied' => $this->has_been_applied(),
			'keys_present'     => $keys_present,
		);
	}

	/**
	 * Fibosearch feature-detect. Uses the canonical bootstrap function name
	 * (`dgwt_wcas`) rather than a class check so we do not depend on the
	 * internal namespace layout, which has shifted between Fibosearch versions.
	 */
	public static function is_available(): bool {
		return function_exists( 'dgwt_wcas' );
	}
}
