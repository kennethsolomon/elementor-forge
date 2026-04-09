<?php
/**
 * Elementor Forge uninstall — total cleanup.
 *
 * Fires when the plugin is deleted from wp-admin. Removes every option, table,
 * capability, scheduled event, and post the plugin writes.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Composer autoload may not be available during uninstall if files were removed
// asynchronously, so we hard-code the option keys + CPT slugs below rather than
// importing the Settings\OptionKeys and CPT\PostTypes classes.

$elementor_forge_options = array(
	'elementor_forge_version',
	'elementor_forge_activated_at',
	'elementor_forge_settings',
	'elementor_forge_schema_version',
	'elementor_forge_onboarding_complete',
	'elementor_forge_ss3_cache_dirty',
	'elementor_forge_safety_mode',
	'elementor_forge_safety_allowed_post_ids',
);
foreach ( $elementor_forge_options as $key ) {
	delete_option( $key );
	delete_site_option( $key );
}

// Purge any leftover bulk-generate progress transients written by
// ElementorForge\MCP\Tools\BulkGenerate. Transients use the elementor_forge_bulk_
// prefix so a single LIKE delete cleans them up.
global $wpdb;
if ( isset( $wpdb ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_elementor_forge_bulk_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_elementor_forge_bulk_' ) . '%'
		)
	);
}

// Remove every post of our CPTs and any associated meta/terms.
$elementor_forge_cpts = array( 'ef_location', 'ef_service', 'ef_testimonial', 'ef_faq' );
foreach ( $elementor_forge_cpts as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $posts as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}

// Remove the Theme Builder + section library templates we installed (identified by
// our own meta key on the elementor_library post type).
$template_posts = get_posts(
	array(
		'post_type'        => 'elementor_library',
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'       => array(
			array(
				'key'     => '_ef_template_type',
				'compare' => 'EXISTS',
			),
		),
		'suppress_filters' => true,
	)
);
foreach ( $template_posts as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

// Flush everything so caches and rewrite rules don't hold stale data.
if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}
