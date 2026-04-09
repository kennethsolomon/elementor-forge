<?php
/**
 * Elementor Forge uninstall.
 *
 * Fires when the plugin is deleted from wp-admin. Removes every option, table,
 * capability, scheduled event, and transient the plugin writes. The red-means-
 * didn't-happen ritual requires `uninstall.php` to leave zero residue.
 *
 * TODO(phase1): wire the full cleanup as options / tables are introduced.
 *   - delete_option() for each plugin option (see \ElementorForge\Settings\OptionKeys)
 *   - $wpdb->query("DROP TABLE IF EXISTS ...") for any custom tables
 *   - wp_clear_scheduled_hook() for any cron hooks
 *   - wp_cache_flush() at the end
 *   - unregister CPTs' posts only if the user opts in (destructive — default NO)
 *
 * @package ElementorForge
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Phase 0 placeholder — no options to clean up yet. Phase 1 fills this in.
// Every option key MUST be deleted here; the integration test suite verifies this.
