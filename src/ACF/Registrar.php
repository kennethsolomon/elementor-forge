<?php
/**
 * ACF field group registrar.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\ACF;

use ElementorForge\Settings\Store;

/**
 * Feature-detects ACF and registers the mode-appropriate field groups. Pro
 * features are gated on a second `class_exists('ACF_Pro')` check so the free
 * fallback never attempts to emit a repeater.
 */
final class Registrar {

	/**
	 * Wire registration onto the ACF init hook. Intended to run once from the
	 * plugin bootstrap.
	 */
	public function boot(): void {
		add_action( 'acf/init', array( $this, 'register_all' ) );
	}

	/**
	 * Register every field group for the active ACF mode, gated on ACF presence.
	 * Pro-mode groups downgrade silently to Free layout if ACF Pro is absent, so
	 * a user flipping the toggle without installing Pro never breaks the admin.
	 */
	public function register_all(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$mode = Store::is_acf_pro_mode() && self::acf_pro_active() ? 'pro' : 'free';

		foreach ( FieldGroups::all( $mode ) as $group ) {
			acf_add_local_field_group( $group );
		}
	}

	/**
	 * Check whether ACF Pro is active. ACF Pro does not define a single canonical
	 * class — detect via the repeater field class which ships only with Pro.
	 */
	public static function acf_pro_active(): bool {
		return class_exists( 'acf_field_repeater' ) || class_exists( 'ACF_Pro' );
	}
}
