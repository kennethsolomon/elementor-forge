# Elementor Forge

WordPress plugin + MCP server that turns structured content documents into fully-built Elementor Pro pages.

**Status:** Phase 0 scaffolding complete. Phase 1 in progress. See `brain.db` project id `3`.

## What it does

- Emits valid Elementor JSON v0.4 from content documents (no legacy section/column; containers + widgets only)
- Onboarding wizard: detects theme, syncs Kit settings, installs a base template library, registers Locations + Services CPTs, creates Theme Builder Singles wired to ACF dynamic tags
- Ships a service-business Theme Builder Header and Footer; ecommerce variant (bottom tab bar) available via setting
- WooCommerce Theme Builder templates (Shop, Single Product, Cart, Checkout) in Phase 2
- Semantic layout judge + Smart Slider 3 Free CRUD + bulk page generation in Phase 3
- Exposes an MCP server (via `wordpress/mcp-adapter` + `wordpress/abilities-api`) so Claude Code can remote-drive the builder

## Stack constraints

- WordPress 6.4+, PHP 8.0+, Elementor Pro 3.20+
- **No third-party Elementor addons.** `ucaddon_*` is preserved on update via a compat shim but never generated.
- ACF: Free is the default path; Pro unlocks repeaters via `acf_mode` toggle
- Smart Slider 3 Free — direct DB writes (schema reverse-engineered)
- Header pattern: `service_business` (default) or `ecommerce`

## Plugin-level settings

| Setting | Default | Alt |
|---|---|---|
| `acf_mode` | `free` | `pro` |
| `ucaddon_shim` | `preserve` | `strip` |
| `mcp_server` | `enabled` | `disabled` |
| `header_pattern` | `service_business` | `ecommerce` |

## Development

### Requirements

- PHP 8.0+
- Composer
- Node.js 20+
- Docker (for `wp-env`)

### Install

```bash
composer install
npm install
```

### Running wp-env

```bash
npm run env:start           # boots WP 6.5 + PHP 8.1
npm run env:run cli wp plugin activate elementor-forge
```

### Red-means-didn't-happen ritual

All of the following must be green before a feature is done:

```bash
composer lint                # PHPCS + WPCS
composer analyse             # PHPStan level 6
composer test:unit           # PHPUnit unit
npm run env:run tests-cli --env-cwd=wp-content/plugins/elementor-forge ./vendor/bin/phpunit   # integration
npm run test:e2e             # Playwright admin UI
npm run env:run cli wp plugin check elementor-forge                                           # WP Plugin Check
```

## License

GPL v2 or later.
