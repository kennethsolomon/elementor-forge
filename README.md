# Elementor Forge

WordPress plugin + MCP server that turns structured content documents into fully-built Elementor Pro pages, with an Intelligence Layer that picks layouts deterministically, a Smart Slider 3 CRUD layer, and batched bulk page generation for matrix runs (e.g. 10 suburbs × 5 services).

**Status:** Phase 3 complete. Ready for handoff. See `brain.db` project id `3`.

## What it does

- **Elementor JSON Emitter (v0.4).** Pure PHP generator for Elementor's flexbox container schema. 14 widget types: heading, text-editor, button, divider, spacer, icon, icon-box, image, image-carousel, nested-carousel, nested-accordion, google_maps, `template` references, and CF7 `shortcode`. Containers nest up to 7 levels. Round-trip Parser + Encoder preserves unknown widgets (including `ucaddon_*`) byte-identical via a `RawNode` shim.
- **4 Custom Post Types.** `ef_location`, `ef_service`, `ef_testimonial`, `ef_faq`, with ACF field groups for each (Free mode uses related-CPT + Elementor Loop Grid; Pro mode uses repeaters).
- **Theme Builder Singles.** Location and Service singles wired to ACF dynamic tags, installed idempotently by a single-query scan so reinstalls update in place.
- **Service-Business Header + Footer** + **Ecommerce Header variant.** Header pattern is selectable via the `header_pattern` plugin option.
- **12 Section templates.** hero, trust strip, service cards, FAQ, CTA, testimonials, process steps, service-area list, location cards, contact form, map+hours, footer CTA — all built from the same emitter primitives the MCP tools use.
- **WooCommerce integration.** Shop archive, Single Product, Cart, and Checkout Theme Builder templates, plus a Fibosearch configuration layer that feature-detects `function_exists('dgwt_wcas')` and writes settings idempotently.
- **Intelligence Layer — Layout Judge.** Deterministic rules engine that picks the best Elementor layout for a section of content. 6 rules ship in Phase 3: FaqRule, GalleryRule, IconListRule, IconBoxGridRule, TextHeavyAccordionRule, NestedCarouselRule. Confidence-scored; highest-confidence rule wins with an Emitter-backed fallback. The judge is a pure-PHP class with a `Rule` interface, so a future LLM-backed judge can replace the default rule list without touching callers. See `src/Intelligence/README.md`.
- **Smart Slider 3 CRUD.** 8-method repository against the `nextend2_smartslider3_{sliders,slides,sliders_xref}` tables — schema reverse-engineered from WP.org SVN trunk rev 3502120 (Smart Slider 3 Free 3.5.1.34). Every write runs through `wp_kses_post()` on both top-level and nested string leaves so programmatic slider content cannot introduce stored XSS. Version-gated (3.5.0 <= ver < 3.7.0) — refuses to touch the DB on unknown releases.
- **Bulk page generation.** Matrix mode crosses an `items` list with a `service_items` list to produce the Cartesian product (one `ef_location` post per suburb × service combination). Transactional (`START TRANSACTION` / `COMMIT` / `ROLLBACK` on first failure, gated on engine capability). Batched (`wp_suspend_cache_addition(true)` + `wp_defer_term_counting(true)` across the loop; restored in a `finally` block so uncaught throwables never leave the cache suspended). Dry-run mode returns the plan without writing. Progress polling via a transient keyed by job ID. Meta-key allowlist blocks `_`-prefixed WP-internal keys and optionally enforces an explicit `allowed_fields` list.
- **Onboarding wizard.** `Elementor Forge > Setup` auto-installs the curated dependency allowlist (Elementor, ACF, CF7, Smart Slider 3, WooCommerce, Fibosearch) from wp.org, registers ACF field groups for the active `acf_mode`, and installs every Theme Builder template + section template in a single pass.
- **Admin Settings page with 4 toggles.** `acf_mode`, `ucaddon_shim`, `mcp_server`, `header_pattern` — all persisted as plugin options with sane defaults.
- **MCP server with 6 tools** — exposed as MCP Abilities so Claude Code can remote-drive the builder. Transport via `wordpress/mcp-adapter` against an internal vendored Abilities shim (see `src/MCP/README.md` for the decision + vendoring scope).

## MCP tool surface (6 tools)

| Tool | Purpose |
|---|---|
| `create_page` | Build a one-off Elementor page from a content doc |
| `add_section` | Append a saved section template to an existing page |
| `apply_template` | Create a CPT post, populate ACF fields, assign the Single template |
| `bulk_generate_pages` | Batched + transactional matrix page generation with dry-run and progress polling |
| `configure_woocommerce` | Configure the Fibosearch + WC Theme Builder templates |
| `manage_slider` | Smart Slider 3 CRUD (create, update, delete, get, list sliders; add, update, delete slides) — 8 actions behind a single MCP tool |

Canonical schemas + capability requirements are in `docs/mcp-tools.md`.

## Stack constraints

- WordPress 6.4+, PHP 8.0+, Elementor Pro 3.20+
- **No third-party Elementor addons.** `ucaddon_*` is preserved on update via a compat shim but never generated.
- ACF: Free is the default path; Pro unlocks repeaters via `acf_mode` toggle.
- Smart Slider 3 Free — direct DB writes (schema reverse-engineered, version-gated to 3.5.0 <= ver < 3.7.0).
- Header pattern: `service_business` (default) or `ecommerce`.
- WooCommerce: feature-detected via `class_exists('WooCommerce')`, no hard dependency.
- Fibosearch: feature-detected via `function_exists('dgwt_wcas')`.

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
composer audit                                                                                # CVE scan
```

## License

GPL v2 or later.
