<?php
/**
 * Internal shim for the WordPress Abilities API.
 *
 * The upstream wordpress/abilities-api composer package was flagged abandoned on
 * 2026-04-09 because the code is being merged into WordPress core for 6.9. The
 * wordpress/mcp-adapter package we use hard-references the global classes
 * WP_Ability, WP_Ability_Category, WP_Abilities_Registry, WP_Ability_Categories_Registry
 * and the global procedural functions wp_register_ability(), etc.
 *
 * Rather than re-add the abandoned dep or fork mcp-adapter, we vendor the minimum
 * Abilities API surface here, guarded by class_exists() and function_exists() so
 * when WordPress 6.9 core ships the real thing our declarations become no-ops.
 *
 * Source: https://github.com/WordPress/abilities-api (GPL-2.0-or-later)
 * Vendored version: 0.5.0, trimmed to the runtime classes mcp-adapter needs.
 * REST API controllers, asset init, and core abilities were intentionally omitted.
 *
 * @package ElementorForge
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Only load inside WordPress — abort cleanly if included from a non-WP context.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/class-wp-ability.php';
require_once __DIR__ . '/class-wp-ability-category.php';
require_once __DIR__ . '/class-wp-abilities-registry.php';
require_once __DIR__ . '/class-wp-ability-categories-registry.php';
require_once __DIR__ . '/abilities-api-functions.php';
