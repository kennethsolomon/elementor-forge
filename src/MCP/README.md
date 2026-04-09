# MCP Server — Architecture Notes

## Abilities API dependency: dropped, vendored internally

**Decision date:** 2026-04-09
**Decision owner:** Kenneth + John, implemented by Hux in Phase 1.

`wordpress/abilities-api` was removed from `composer.json` after composer audit flagged the package abandoned. The package is abandoned because the WordPress AI Team is merging it into WordPress core (targeted at WP 6.9), not because the code is broken. It is however a composer-audit red flag and Kenneth does not want to ship an abandoned dependency.

`wordpress/mcp-adapter` still hard-references the global classes `WP_Ability`, `WP_Ability_Category`, `WP_Abilities_Registry`, `WP_Ability_Categories_Registry` and the procedural functions `wp_register_ability()`, `wp_register_ability_category()`, etc. Forking mcp-adapter to remove these references would mean owning a permanent diff. Not worth it.

**What we did instead:** vendored the minimum Abilities API runtime (four class files + the procedural wrappers) into `src/MCP/Internal/` under the same GPL-2.0-or-later license. Every class declaration and function declaration is guarded by `class_exists()` / `function_exists()`, so when WordPress core 6.9 ships the real Abilities API, our declarations become clean no-ops and the runtime automatically uses core's version. No migration work required.

Files vendored:
- `src/MCP/Internal/class-wp-ability.php` — `WP_Ability` runtime
- `src/MCP/Internal/class-wp-ability-category.php` — `WP_Ability_Category` runtime
- `src/MCP/Internal/class-wp-abilities-registry.php` — `WP_Abilities_Registry` singleton
- `src/MCP/Internal/class-wp-ability-categories-registry.php` — `WP_Ability_Categories_Registry` singleton
- `src/MCP/Internal/abilities-api-functions.php` — `wp_register_ability()` et al.
- `src/MCP/Internal/abilities-api-shim.php` — entry point that `require_once`s the above

The shim is loaded via composer's `files` autoload entry so it's available on every request without any WordPress plugin boot order concern.

**What we did NOT vendor:** REST API controllers, asset init (for the abilities client package), and the core abilities registration (`wp_register_core_abilities()`). Forge's MCP server does not need the REST endpoint — the MCP transport is the entry point — and Forge ships no client-side JavaScript for the abilities API. Trimming these kept the vendored surface small and focused on what mcp-adapter actually calls at runtime.

## Tool registration flow

Elementor Forge registers six MCP tools:

| Tool | Ability name | Purpose |
|---|---|---|
| `create_page` | `elementor-forge/create-page` | Build a one-off Elementor page from a content doc |
| `add_section` | `elementor-forge/add-section` | Append a saved section template to an existing page |
| `apply_template` | `elementor-forge/apply-template` | Create a CPT post, populate ACF, assign Single template |
| `bulk_generate_pages` | `elementor-forge/bulk-generate-pages` | Batched + transactional matrix page generation. Suspends cache addition and defers term counting across the loop; wraps the loop in a single `START TRANSACTION` / `COMMIT` / `ROLLBACK` with cleanup in a `finally` block so uncaught throwables never leave state lingering. Matrix mode crosses an items list with a service_items list to produce the Cartesian product. Dry-run mode returns the plan without writing. Progress polling via a transient keyed by job ID. Meta-key allowlist blocks `_`-prefixed internal WP meta and optionally enforces an explicit `allowed_fields` list. |
| `configure_woocommerce` | `elementor-forge/configure-woocommerce` | Idempotent WooCommerce Theme Builder template install + Fibosearch configuration layer (feature-detected via `function_exists('dgwt_wcas')`) |
| `manage_slider` | `elementor-forge/manage-slider` | Smart Slider 3 CRUD — single tool, 8 actions (`create_slider`, `update_slider`, `get_slider`, `delete_slider`, `add_slide`, `update_slide`, `delete_slide`, `list_sliders`). All writes run through a recursive `wp_kses_post()` walker so programmatic slider content cannot introduce stored XSS. |

Each tool is a WP Ability registered on the `wp_abilities_api_init` action via `\ElementorForge\MCP\Server::register_abilities()`. The adapter then consumes the abilities during `mcp_adapter_init` and exposes them as MCP tools over the HTTP transport.

Canonical input/output JSON schemas for every tool live in `docs/mcp-tools.md`.

The entire `\ElementorForge\MCP\Server` class is gated on the `mcp_server` plugin setting (`enabled` / `disabled`) — when disabled, no abilities are registered and no MCP server is created, leaving zero runtime footprint.

## Capability + sanitization pattern

Every tool's `permission_callback` calls `current_user_can( 'manage_options' )` (or a narrower capability where appropriate). Every tool's `execute_callback` sanitizes every input via `wp_unslash` + the correct `sanitize_*` function before using it, and validates the full payload against the declared `input_schema`. Validation is delegated to `WP_Ability::validate_input()` which uses `rest_validate_value_from_schema()` — the same validator the REST API uses.
