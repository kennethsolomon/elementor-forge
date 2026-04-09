# Changelog

All notable changes to Elementor Forge will be documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versioning is [SemVer](https://semver.org/spec/v2.0.0.html).

## [0.3.0] — 2026-04-09 — Phase 3: Intelligence Layer + Smart Slider CRUD + Batched Bulk Generation

### Added

- **Intelligence Layer (Layout Judge).** Pure-PHP deterministic rules engine that picks an Elementor layout for a section of content. 6 rules ship: FaqRule, GalleryRule, IconListRule, IconBoxGridRule, TextHeavyAccordionRule, NestedCarouselRule. Extension point via the `Rule` interface so an LLM-backed judge can replace the deterministic rule list without touching callers. `JudgedEmitter` composes with the Phase 1 Emitter and falls back to the Emitter's default layout when no rule matches.
- **Smart Slider 3 CRUD layer.** 8-method repository against `nextend2_smartslider3_{sliders,slides,sliders_xref}`. Schema reverse-engineered from WP.org SVN trunk rev 3502120 (3.5.1.34). Version-gated (`3.5.0 <= ver < 3.7.0`). Every public method gates on `manage_options`. `delete_slider()` wraps the three cascading deletes in a single transaction on engines that support it; rolls back on mid-sequence failure. Cache invalidation via `Nextend\SmartSlider3\PublicApi\Project::clearCache()` when reachable, fallback to a Forge option flag otherwise.
- **Batched `bulk_generate_pages`.** Replaces the Phase 1 stub. Suspends `wp_suspend_cache_addition` + `wp_defer_term_counting` across the loop, wraps the loop in a single `START TRANSACTION` / `COMMIT`, uses `meta_input` in `wp_insert_post` so each post writes in one DB round-trip instead of N `update_field` calls. Matrix mode crosses items × service_items. Dry-run mode returns the plan without writing. Progress polling via a transient keyed by job ID.
- **`manage_slider` MCP tool.** Single tool, 8 actions — CRUD surface for Smart Slider 3 via MCP.
- **`.base` query views for the WP admin.** `elementor_forge_bulk_jobs.base` for progress monitoring (kept behind the plugin's admin menu).
- **Recursive string-leaf sanitization.** `SliderRepository::sanitize_string_leaves()` walks `params` / `layers` arrays of arbitrary depth and runs `wp_kses_post()` on every string leaf before the JSON encode. Layer-level sanitization also applied in `SlideTemplate::heading_layer()` and `SlideTemplate::text_layer()`. Defense against stored XSS through `sliders.params` and `slides.slide` on Smart Slider 3 Free's front-end renderer.
- **`BulkGenerate` meta-key allowlist.** Blocks `_`-prefixed WP-internal meta keys unconditionally (e.g. `_edit_lock`, `_elementor_data`, `_ef_template_type`). Optional explicit `allowed_fields` param enforces a hard caller-supplied allowlist. Rejected keys are returned in the result so the caller can see what was stripped.
- **CHANGELOG.md** at the project root (this file).
- **PHASE_1_5_BACKLOG.md** at the project root enumerating deferred review items from all three phases.
- **src/Intelligence/README.md** — architectural doc for the Layout Judge rules engine.
- **docs/mcp-tools.md** — canonical list of all 6 MCP tools with input/output JSON schemas and capability requirements.

### Fixed

- **BulkGenerate cleanup hygiene.** Loop is now wrapped in `try { } finally { }` so `wp_suspend_cache_addition` is restored to its prior state even when an uncaught `Throwable` propagates from `wp_insert_post`. The previous code would leak a suspended cache to the rest of the request if an insert threw a `RuntimeException` or similar non-`WP_Error`. Transaction rollback on throwable path is explicit; COMMIT moved inside the `try`.
- **`delete_slider` transactional integrity.** Three sequential `$wpdb->delete` calls (slides → xref → slider) are now wrapped in `START TRANSACTION` / `COMMIT` / `ROLLBACK`. Mid-sequence failure rolls back instead of leaving orphaned slide / xref rows.
- **ManageSlider MCP tool coverage.** Added happy-path tests for the 5 previously untested actions (`update_slider`, `delete_slider`, `add_slide`, `update_slide`, `delete_slide`) plus an explicit unknown-action rejection test.

### Changed

- Plugin version bumped to `0.3.0` (was `0.2.0`).
- Plugin header `Description` rewritten to mention the Intelligence Layer, Smart Slider CRUD, bulk generation, and MCP server.
- `composer.json` description updated to match the plugin header.
- `src/MCP/README.md` tool table rewritten to list all 6 tools (previously listed 4, missing `configure_woocommerce` from Phase 2 and `manage_slider` from Phase 3).
- Test bootstrap `wpdb` stub extended with a `query()` method and `delete_fail_at` per-call fail map so transaction tests can assert on the START/COMMIT/ROLLBACK sequence without loading a real WordPress.

## [0.2.0] — 2026-04-09 — Phase 2: WooCommerce Theme Builder + Fibosearch + Ecommerce Header

### Added

- **WooCommerce Theme Builder templates.** Shop archive, Single Product, Cart, and Checkout, installed via the onboarding wizard's single-query scan so reinstalls update in place. Feature-detected via `class_exists('WooCommerce')`.
- **Fibosearch configuration layer.** Writes the Fibosearch plugin's settings idempotently via its public options API. Feature-detected via `function_exists('dgwt_wcas')` — the class namespace has shifted between Fibosearch versions, so the function check is more stable.
- **Ecommerce header variant.** Alternative header pattern selected by the `header_pattern: ecommerce` plugin option. Ships alongside the existing `service_business` header.
- **Mobile bottom-tab-bar.** Injected via the ecommerce header container's `custom_css` setting as a `position: fixed` block — Elementor Pro's sticky module does not support permanent fixed positioning.
- **`configure_woocommerce` MCP tool.** New tool exposed via the Abilities API.

### Fixed

- **Phase 2 MCP idempotency.** Re-running `configure_woocommerce` now short-circuits when templates are already installed instead of creating duplicates.
- **`raw_widget` dedup.** Duplicate `raw_widget` entries from repeated ingestion are now de-duplicated by content hash.
- **Fibosearch int-0 bug.** Options that were supposed to be booleans were being written as the integer `0` due to a fallback branch; now normalized to `false` so the Fibosearch admin UI renders them correctly.
- **`is_in_sync` rename.** Ambiguous method name replaced with `has_up_to_date_install()` for clarity.

### Changed

- Plugin version bumped to `0.2.0`.

## [0.1.0] — 2026-04-09 — Phase 1: Foundation (Emitter, CPTs, Theme Builder, Onboarding, MCP server)

### Added

- **Elementor JSON Emitter (v0.4).** Pure PHP generator for Elementor's flexbox container schema. 14 widget types supported. Containers nest up to 7 levels.
- **Round-trip Parser + Encoder.** Reads existing `_elementor_data` postmeta and re-encodes without byte-level drift. Preserves unknown widgets (including `ucaddon_*`) via a `RawNode` shim so the compat-shim path never corrupts legacy pages.
- **4 Custom Post Types.** `ef_location`, `ef_service`, `ef_testimonial`, `ef_faq`, with ACF field groups for each (Free mode + Pro mode).
- **Theme Builder templates.** Location Single + Service Single wired to ACF dynamic tags. Installed idempotently by a single-query scan so reinstalls update in place instead of duplicating.
- **Service-Business Header + Footer.** Default header pattern.
- **12 reusable section templates.** hero, trust strip, service cards, FAQ, CTA, testimonials, process steps, service-area list, location cards, contact form, map+hours, footer CTA.
- **Onboarding wizard.** `Elementor Forge > Setup` auto-installs the curated dependency allowlist (Elementor, ACF, CF7, Smart Slider 3, WooCommerce, Fibosearch) from wp.org, registers ACF field groups for the active `acf_mode`, and installs every Theme Builder template + section template in a single pass.
- **Admin Settings page with 4 toggles.** `acf_mode`, `ucaddon_shim`, `mcp_server`, `header_pattern`.
- **MCP server with 4 tools.** `create_page`, `add_section`, `apply_template`, `bulk_generate_pages` (stub) — exposed as MCP Abilities. Transport via `wordpress/mcp-adapter` against an internal vendored Abilities shim.
- **Vendored Abilities API runtime.** `wordpress/abilities-api` was removed from `composer.json` after composer audit flagged it abandoned (package is being merged into WP core). The minimum runtime is vendored under `src/MCP/Internal/` with `class_exists()` / `function_exists()` guards so it becomes a clean no-op when WP core 6.9 ships the real API.

### Fixed

- **Phase 1 Layer 2 blockers (post-review).** Dependency allowlist enforcement, round-trip Emitter byte-identity, installer idempotency, installer batching — all addressed in the Phase 1 hotfix commit.

### Changed

- Plugin scaffold, PHPCS + WPCS ruleset, PHPStan level 6 config, PHPUnit unit + integration suites, Playwright admin E2E scaffold, wp-env config.
- Initial version `0.1.0`.
