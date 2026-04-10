# Investigation: Elementor Forge — Full Plugin Audit + MCP Capability Expansion

**Date:** 2026-04-10
**Scope:** Map emitter architecture, MCP registration pattern, safety gate, header/footer system, data structures, and test gaps for 3-phase expansion (fix broken core → headers + kit → content manipulation)
**Status:** complete

## Entry Points

| Type | Name | File | Handler |
|------|------|------|---------|
| Hook | `plugins_loaded` (priority 20) | `elementor-forge.php:63` | `Plugin::boot()` |
| Hook | `elementor/loaded` | `src/Plugin.php:71` | `Plugin::on_elementor_loaded()` |
| Hook | `wp_abilities_api_init` | `src/MCP/Server.php:50` | `Server::register_abilities()` |
| Hook | `mcp_adapter_init` | `src/MCP/Server.php:51` | `Server::register_server()` |
| MCP Tool | `elementor-forge/create-page` | `src/MCP/Tools/CreatePage.php` | `CreatePage::execute()` |
| MCP Tool | `elementor-forge/add-section` | `src/MCP/Tools/AddSection.php` | `AddSection::execute()` |
| MCP Tool | `elementor-forge/apply-template` | `src/MCP/Tools/ApplyTemplate.php` | `ApplyTemplate::execute()` |
| MCP Tool | `elementor-forge/bulk-generate-pages` | `src/MCP/Tools/BulkGenerate.php` | `BulkGenerate::execute()` |
| MCP Tool | `elementor-forge/configure-woocommerce` | `src/MCP/Tools/ConfigureWooCommerce.php` | `ConfigureWooCommerce::execute()` |
| MCP Tool | `elementor-forge/manage-slider` | `src/MCP/Tools/ManageSlider.php` | `ManageSlider::execute()` |
| Extension | `elementor_forge/mcp/register` | `src/Plugin.php:83` | Fired after MCP boot — hook point for new tools |

## Data Model

### _elementor_data JSON structure
```json
[
  {
    "id": "a1b2c3d4",           // 8-char hex
    "elType": "container",       // or "widget"
    "isInner": false,            // true for nested
    "settings": { ... },         // container or widget settings
    "elements": [ ... ],         // children
    "widgetType": "heading"      // only on widgets
  }
]
```

### Key post meta keys
| Key | Purpose |
|-----|---------|
| `_elementor_data` | Slashed JSON element tree |
| `_elementor_edit_mode` | `"builder"` to flag as Elementor-managed |
| `_elementor_version` | Running Elementor version |
| `_elementor_template_type` | `header`, `footer`, `single-post`, `product-archive`, etc. |
| `_elementor_conditions` | PHP array: `['include/general']`, `['include/singular/ef_location']` |
| `_ef_template_type` | Forge's idempotency key for template discovery |
| `_wp_page_template` | **NOT SET by create_page** — backlog item #31 |

### Settings option (single row: `elementor_forge_settings`)
| Key | Values | Default |
|-----|--------|---------|
| `safety_mode` | `full`, `page_only`, `read_only` | `full` |
| `safety_allowed_post_ids` | CSV of post IDs | `""` |
| `mcp_server` | `enabled`, `disabled` | `enabled` |
| `header_pattern` | `service_business`, `ecommerce` | `service_business` |
| `acf_mode` | `free`, `pro` | `free` |
| `ucaddon_shim` | `preserve`, `strip` | `preserve` |

## MCP Tool Registration Pattern

All tools follow the same static-class pattern in `src/MCP/Tools/`:

```php
final class ToolName {
    public static function input_schema(): array { ... }
    public static function output_schema(): array { ... }
    public static function permission(): bool { return current_user_can('...'); }
    public static function execute(array $input): array|WP_Error {
        $gate = Gate::check('tool_name', Gate::ACTION_TYPE, $post_id);
        if (is_wp_error($gate)) return $gate;
        // business logic
    }
}
```

Registration in `Server::register_abilities()` via `wp_register_ability()`. New tools: add class in `src/MCP/Tools/`, add constant in `Server.php`, add `wp_register_ability()` call, add to `register_server()` ability list.

## Safety Gate Enforcement

**File:** `src/Safety/Gate.php` — single static `check()` method.

| Mode | `create` | `modify` | `site_wide` | `theme_install` |
|------|----------|----------|-------------|-----------------|
| `full` | allow | allow | allow | allow |
| `page_only` | allow | allowlist check | REJECT | REJECT |
| `read_only` | REJECT | REJECT | REJECT | REJECT |

**For Phase C content tools:** New `ACTION_MODIFY` calls with `$target_post_id` will naturally work with existing gate. Need to ensure `get_page_structure` (read-only) bypasses the gate or uses a new `ACTION_READ` type.

## Emitter Architecture

```
ContentDoc (title + blocks[])
  → Emitter::emit() iterates blocks
    → emit_block() dispatches on block['type']
      → creates Container + Widget nodes
        → Document (collection of top-level Containers)
          → Encoder::write_document() → wp_slash(json_encode) → update_post_meta
```

**Block type → widget mapping:**

| Block type | Widget | Known issue |
|-----------|--------|-------------|
| `heading` | Heading | — |
| `paragraph` | TextEditor | — |
| `cta` | Button | — |
| `image` | Image | — |
| `hero` | Heading + TextEditor + Button | **No background** (backlog #28) |
| `card_grid` | N × IconBox in containers | **`_flex_size: grow` broken** — columns stack vertically (backlog #27) |
| `faq` | NestedAccordion | **Answers dropped** — only questions emitted (line 228-230) |
| `map` | GoogleMaps | — |
| `form` | Shortcode | — |

**15 widget emitters** in `src/Elementor/Emitter/Widgets/`: Button, Divider, GoogleMaps, Heading, Icon, IconBox, Image, ImageCarousel, NestedAccordion, NestedCarousel, Shortcode, Spacer, TemplateRef, TextEditor, UcaddonShim.

## Header/Footer Template System

**Current headers:**
1. **Service business** (`Templates::service_business_header()`, `Templates.php:166`): single row — site_title Heading + "Get a Free Quote" Button. No nav menu. Minimal.
2. **Ecommerce** (`EcommerceHeader::spec()`, 253 lines): multi-row — desktop (logo + search + cart) + nav row + mobile top row + mobile tab bar. Uses RawNode for nav-menu, fibosearch, woocommerce-menu-cart widgets.

**Footer:** Service business only — 3 columns (Contact/Services/Areas) + copyright. Placeholder text.

**Theme Builder template installer:** `Installer::install_one(TemplateSpec)` — idempotent via `_ef_template_type` meta key. `find_existing()` uses primed type map.

**For Phase B:** New `create_header` and `create_footer` MCP tools should follow the TemplateSpec + Installer pattern. Header presets live as factory methods (like `service_business_header()` and `EcommerceHeader::spec()`), but with a composable row system for overrides.

## Elementor Cache Clearing (MISSING)

**No cache clearing happens anywhere after writes.** Critical gap.

Required calls after any `_elementor_data` write:
1. `\Elementor\Plugin::$instance->files_manager->clear_cache()` — global CSS cache
2. `delete_post_meta($post_id, '_elementor_css')` — per-post CSS file reference
3. `delete_option('elementor_pro_theme_builder_conditions_cache')` — Theme Builder conditions

None of these exist in the codebase. Affects: `CreatePage`, `AddSection`, `Encoder::write_document()`, `Installer::install_one()`.

## ACF Integration Points

**Field groups:** `src/ACF/FieldGroups.php` — 4 groups (location, service, testimonial, faq) registered via `acf_add_local_field_group()`.

**Dynamic tags in templates:** `DynamicWidget` wraps a widget and injects `__dynamic__` tags pointing to ACF fields via `[elementor-tag ...]` strings.

**ApplyTemplate tool:** Writes ACF fields via `update_field($key, $value, $post_id)` — **NO sanitization** on values (backlog #1).

**For Phase C:** ACF field editing via content tools needs `get_field()` / `update_field()` with proper sanitization. Support both Free (relationship fields → separate CPT posts) and Pro (repeater fields inline).

## Existing Tests

| Layer | Files | Methods | Notes |
|-------|-------|---------|-------|
| Unit | 76 classes | ~718 | Brain\Monkey mocks, no real WP |
| Integration | 0 | 0 | Empty directory |
| E2E | 1 | 1 | Placeholder smoke test |

**Coverage gaps relevant to this work:**
- All 15 widget emitters: only aggregate shape test, no per-widget behavioral tests
- `Encoder::encode_for_meta()` silent empty-string return on failure
- No tests for cache clearing (because no cache clearing exists)
- No integration tests against real WP/Elementor
- `Admin/Settings/Page.php` (633 lines, 0 tests)

## Config & Flags

| Name | File | Purpose |
|------|------|---------|
| `safety_mode` | `Settings/Store.php` | Gates all MCP writes |
| `mcp_server` | `Settings/Store.php` | Enables/disables MCP endpoint |
| `header_pattern` | `Settings/Store.php` | Selects header variant |
| `acf_mode` | `Settings/Store.php` | Free vs Pro ACF features |

## Load-Bearing Files Read

- `src/Elementor/Emitter/Emitter.php` (249 lines) — the core translation engine; every block type dispatches here
- `src/MCP/Server.php` (201 lines) — registration pattern for all MCP tools; new tools follow this exactly
- `src/Safety/Gate.php` (172 lines) — single chokepoint for write permission; Phase C tools route through here
- `src/Elementor/Emitter/Encoder.php` (106 lines) — the `_elementor_data` slash dance; all reads/writes go through here
- `src/Elementor/ThemeBuilder/Templates.php` (240 lines) — header/footer factory; Phase B headers extend this pattern

## Prior Decisions Referenced

- `tasks/findings.md` 2026-04-10: PHPStan clean, PHPCS clean, security clean. God classes identified (Page.php 633L, SliderRepository 567L).
- `tasks/lessons.md`: No active lessons yet.
- `PHASE_1_5_BACKLOG.md`: 39 items across 4 sections. Items #27 (columns), #28 (hero bg), #29 (empty header/footer), #30 (cache), #31 (page template), #32 (kit globals) are highest priority for Phase A/B.

## God Nodes

Top files with the most inbound references — highest blast radius:

1. **`src/Elementor/Emitter/Emitter.php`** — called by CreatePage, AddSection, JudgedEmitter; every page creation flows through here
2. **`src/Safety/Gate.php`** — called by all 6 MCP tools; every write permission check routes here
3. **`src/Elementor/Emitter/Encoder.php`** — called by CreatePage, AddSection, Installer; all `_elementor_data` persistence
4. **`src/Settings/Store.php`** — called by Gate, Server, Wizard, all tools; central settings read
5. **`src/Elementor/Emitter/Container.php`** — instantiated by every emitter, every template, every header; the fundamental layout primitive

## Unknowns / Open Questions

1. **Kit globals write path:** How exactly does Elementor store the Default Kit settings? Need to inspect `_elementor_page_settings` on the Kit post. The `set_kit_globals` tool needs to know the exact option shape.
2. **Nav menu widget settings:** The ecommerce header uses `RawNode::raw_widget('nav-menu', ...)` — need to verify the exact settings shape for WP registered menu integration.
3. **Content manipulation ID stability:** When editing sections on existing pages, how stable are Elementor element IDs across saves? If IDs regenerate on editor save, index-based targeting may be more reliable.
4. **Elementor Pro dependency:** Some widgets (nav-menu, woocommerce-*) require Elementor Pro. Should Phase B headers degrade gracefully without Pro, or require it?

## Suggested Questions

1. How should the `set_kit_globals` tool handle the Kit post discovery — hardcode the active kit query or use `\Elementor\Plugin::$instance->kits_manager->get_active_id()`?
2. Should `get_page_structure` return the raw Elementor JSON or a simplified/annotated tree?
3. For header presets, should each preset be a separate PHP class (like `EcommerceHeader`) or all methods on a single `HeaderPresets` factory?
4. Should `create_header` auto-clear the existing header template, or require explicit deletion first?
5. How should ACF field editing handle repeater fields in Free mode (where they're relationship fields to separate CPT posts)?

## Entry Points for the Brainstorm

Start with Phase A fixes — the 3 highest-impact broken emitters (`card_grid` columns at `Emitter.php:200`, FAQ answer dropping at `Emitter.php:228`, hero backgrounds at `Emitter.php:152`) plus cache clearing (needs a new `CacheClearer` utility called after every `Encoder::write_document()`). These are prerequisites for Phase B/C to produce correct output.
