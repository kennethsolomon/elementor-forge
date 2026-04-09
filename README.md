# Elementor Forge

WordPress plugin + MCP server that turns structured content documents into fully-built Elementor Pro pages.

**Status:** Phase 1 complete. Phase 2 pending. See `brain.db` project id `3`.

## What it does

- **Elementor JSON Emitter (v0.4).** Pure PHP generator for Elementor's flexbox container schema. 14 widget types: heading, text-editor, button, divider, spacer, icon, icon-box, image, image-carousel, nested-carousel, nested-accordion, google_maps, `template` references, and CF7 `shortcode`. Containers nest up to 7 levels. Round-trip Parser preserves unknown widgets (including `ucaddon_*`) byte-identical via a `RawNode` shim.
- **4 Custom Post Types.** `ef_location`, `ef_service`, and the two Theme Builder library surfaces.
- **Theme Builder templates.** Two Singles (Location, Service) wired to ACF dynamic tags, plus a service-business Header and Footer. All installed idempotently by a single-query scan (`prime_type_map`) so reinstalls update in place instead of duplicating.
- **12 reusable section templates.** hero, trust strip, service cards, FAQ, CTA, testimonials, process steps, service area list, location cards, contact form, map+hours, footer CTA — all built from the same emitter primitives the MCP tools use.
- **Onboarding wizard.** `Elementor Forge > Setup` auto-installs the curated dependency allowlist (Elementor, ACF, CF7, Smart Slider 3, WooCommerce, FiboSearch) from wp.org, registers ACF field groups for the active `acf_mode`, and installs every Theme Builder template + section template in a single pass.
- **Admin Settings page with 4 toggles.** `acf_mode`, `ucaddon_shim`, `mcp_server`, `header_pattern` — all persisted as plugin options with sane defaults.
- **MCP server with 4 tools.** `emit_page`, `apply_template`, `add_section`, `list_templates` — exposed as MCP Abilities so Claude Code can remote-drive the builder. Transport via `wordpress/mcp-adapter` against an internal Abilities shim (see `src/MCP/README.md`).

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
