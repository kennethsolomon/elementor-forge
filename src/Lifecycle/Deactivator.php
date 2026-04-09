<?php
/**
 * Deactivation handler.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Lifecycle;

/**
 * Fires once on plugin deactivation. Opposite of Activator — does NOT remove data.
 * Data removal lives in uninstall.php.
 */
final class Deactivator {

	/**
	 * Run deactivation cleanup. Never removes data — that lives in uninstall.php.
	 */
	public static function deactivate(): void {
		// Clear scheduled events so the cron table stays clean.
		// Phase 1 adds actual hook names here.
		do_action( 'elementor_forge/deactivated' );

		flush_rewrite_rules( false );
	}
}
