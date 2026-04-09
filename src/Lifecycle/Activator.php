<?php
/**
 * Activation handler.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Lifecycle;

/**
 * Fires once on plugin activation. Keep work minimal — all heavy setup is deferred to the
 * onboarding wizard so activation stays fast and recoverable.
 */
final class Activator {

	public const OPTION_VERSION      = 'elementor_forge_version';
	public const OPTION_ACTIVATED_AT = 'elementor_forge_activated_at';

	/**
	 * Run activation-time setup. Must stay fast and idempotent.
	 */
	public static function activate(): void {
		// Version gate — refuse to activate on unsupported PHP.
		if ( version_compare( PHP_VERSION, ELEMENTOR_FORGE_MIN_PHP, '<' ) ) {
			deactivate_plugins( plugin_basename( ELEMENTOR_FORGE_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Elementor Forge requires PHP 8.0 or higher.', 'elementor-forge' ),
				esc_html__( 'Plugin activation failed', 'elementor-forge' ),
				array( 'back_link' => true )
			);
		}

		// Record version + activation timestamp. Schema migrations key off this.
		update_option( self::OPTION_VERSION, ELEMENTOR_FORGE_VERSION, false );
		if ( false === get_option( self::OPTION_ACTIVATED_AT, false ) ) {
			update_option( self::OPTION_ACTIVATED_AT, gmdate( 'c' ), false );
		}

		// Phase 1 fills in: CPT registration flush, ACF field group seed, default settings.
		do_action( 'elementor_forge/activated' );

		// Flush rewrite rules exactly once.
		flush_rewrite_rules( false );
	}
}
