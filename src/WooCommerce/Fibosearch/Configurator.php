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
	 * user-configured keys are preserved — only keys in {@see DEFAULTS} that
	 * are not already set (or whose stored value is an empty string) are
	 * overwritten. This makes the call idempotent: running it twice is a no-op
	 * on the second call, and running it once after the user has tweaked a
	 * value does not stomp their tweak.
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
			if ( array_key_exists( $key, $current ) && '' !== $current[ $key ] ) {
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
	 * Detect whether the currently stored Fibosearch settings are in sync with
	 * the Forge defaults. Returns `true` when every key in {@see DEFAULTS}
	 * matches the stored value.
	 */
	public function is_in_sync(): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		$current = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			return false;
		}

		foreach ( self::DEFAULTS as $key => $default_value ) {
			if ( ! array_key_exists( $key, $current ) ) {
				return false;
			}
			if ( $current[ $key ] !== $default_value ) {
				// A user tweak counts as "in sync" — Forge only overwrites
				// empty keys. So only report out-of-sync when the stored value
				// is an empty string (which means Forge has never applied).
				if ( '' === $current[ $key ] ) {
					return false;
				}
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
	 *     in_sync: bool,
	 *     keys_present: int
	 * }
	 */
	public function report(): array {
		if ( ! self::is_available() ) {
			return array(
				'detected'      => false,
				'version'       => '',
				'option_exists' => false,
				'in_sync'       => false,
				'keys_present'  => 0,
			);
		}

		$stored        = get_option( self::OPTION_KEY, null );
		$option_exists = is_array( $stored );
		$keys_present  = $option_exists ? count( $stored ) : 0;

		return array(
			'detected'      => true,
			'version'       => defined( 'DGWT_WCAS_VERSION' ) ? (string) constant( 'DGWT_WCAS_VERSION' ) : 'unknown',
			'option_exists' => $option_exists,
			'in_sync'       => $this->is_in_sync(),
			'keys_present'  => $keys_present,
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
