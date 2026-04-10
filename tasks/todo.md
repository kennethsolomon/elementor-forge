# TODO — 2026-04-10 — Plugin Audit + MCP Capability Expansion

## Goal
Fix all broken emitters, then expand the MCP tool surface: Phase A fixes broken core (v0.6.0), Phase B adds headers + kit globals (v0.7.0), Phase C adds content manipulation (v0.8.0).

## Phase A — Fix Broken Core (v0.6.0)

### A1: Fix column layouts (card_grid + all multi-column)
- [ ] Replace `_flex_size: grow` with `width` dimension object in `Emitter::emit_card_grid()` (`src/Elementor/Emitter/Emitter.php:200`)
- [ ] Compute width: `floor(100 / count($cards))` as `{'unit': '%', 'size': N}`
- [ ] Apply same fix in SectionLibrary templates that use row layouts (`src/Elementor/SectionLibrary.php`)
- [ ] Apply same fix in footer columns (`src/Elementor/ThemeBuilder/Templates.php:209-222`)
- [ ] Write unit tests: card_grid with 2, 3, 4 cards produces correct width percentages
- [ ] Write unit tests: SectionLibrary row templates produce correct widths

### A2: Fix hero backgrounds
- [ ] Add `background_background: 'classic'` + Kit primary color global to hero container settings in `Emitter::emit_hero()` (`Emitter.php:152`)
- [ ] Support optional `background_color` and `background_image` overrides from block data
- [ ] Write unit tests: hero emits background settings by default; overrides work

### A3: Fix FAQ answer dropping
- [ ] Modify `Emitter::emit_faq()` (`Emitter.php:221-233`) to emit both question AND answer
- [ ] Update `NestedAccordion::create()` to accept `array<array{title: string, content: string}>` instead of `array<string>`
- [ ] Each accordion item needs: `item_title` (question) + child TextEditor widget (answer)
- [ ] Write unit tests: FAQ with 3 Q&A pairs produces 3 accordion items, each with answer content

### A4: Add Elementor cache clearing
- [ ] Create `src/Elementor/CacheClearer.php` with static `clear(int $post_id)` method
- [ ] Calls: `\Elementor\Plugin::$instance->files_manager->clear_cache()` (guarded by class_exists)
- [ ] Calls: `delete_post_meta($post_id, '_elementor_css')`
- [ ] Calls: `delete_option('elementor_pro_theme_builder_conditions_cache')`
- [ ] Call `CacheClearer::clear()` after every `Encoder::write_document()` call in: CreatePage, AddSection, Installer
- [ ] Write unit tests with Brain\Monkey expectations for each cache-clearing function

### A5: Fix Encoder silent failure
- [ ] Create `src/Elementor/Emitter/EncoderException.php`
- [ ] In `Encoder::encode_for_meta()` (`Encoder.php:47-48`): throw `EncoderException` when `wp_json_encode` returns false
- [ ] Update callers to handle/propagate the exception
- [ ] Write unit tests: encode failure throws, decode handles gracefully

### A6: Sanitize ACF fields in apply_template
- [ ] In `ApplyTemplate::execute()`: sanitize each ACF field value before `update_field()`
- [ ] String fields: `sanitize_text_field()`
- [ ] HTML fields (description/wysiwyg): `wp_kses_post()`
- [ ] Integer fields: `absint()`
- [ ] URL fields: `esc_url_raw()`
- [ ] Write unit tests: each field type sanitized correctly; malicious input stripped

### A7: Set page template in create_page
- [ ] In `CreatePage::execute()`: add `update_post_meta($post_id, '_wp_page_template', 'elementor_header_footer')` after post creation
- [ ] Support optional `page_template` input parameter (default: `elementor_header_footer`)
- [ ] Write unit test: create_page sets correct page template meta

### A8: Reject slider_id=0
- [ ] In `ManageSlider::execute()`: validate `slider_id` > 0 for actions that require it (update, get, delete, add_slide, update_slide, delete_slide)
- [ ] Return `WP_Error('elementor_forge_invalid_slider_id', ...)` for invalid IDs
- [ ] Write unit tests: slider_id=0 returns error; valid IDs proceed

### A9: Fix flex gap math
- [ ] Create helper: `Container::column_width(int $count, int $gap_px = 0): array` returning `{'unit': '%', 'size': computed}`
- [ ] Formula: if gap > 0, use `calc()` via CSS or compute `(100 - (count-1) * gap_percent) / count`
- [ ] Apply to card_grid, SectionLibrary row layouts, footer columns
- [ ] Write unit tests: 3 columns with 24px gap don't overflow

### A10: Phase A commit + gates
- [ ] Run `composer analyse` — 0 errors
- [ ] Run `composer lint` — 0 errors
- [ ] Run `composer test:unit` — all pass
- [ ] Verify 100% coverage on all modified/new code
- [ ] Commit: `fix(emitter): fix broken column layouts, hero backgrounds, FAQ answers, cache clearing`

## Phase B — Headers + Kit Globals (v0.7.0)

### B1: `set_kit_globals` MCP tool
- [ ] Create `src/MCP/Tools/SetKitGlobals.php` following existing tool pattern
- [ ] Discover active Kit post: `\Elementor\Plugin::$instance->kits_manager->get_active_id()`
- [ ] Accept input: `colors` (object: primary, secondary, text, accent), `typography` (body, headings), `button_styles`
- [ ] Write to Kit post's `_elementor_page_settings` meta
- [ ] Register in `Server.php` as `elementor-forge/set-kit-globals`
- [ ] Gate: `ACTION_SITE_WIDE` (blocked in page_only + read_only)
- [ ] Write unit tests: validates input, writes correct Kit settings, gate enforcement
- [ ] Add tool description with prompting hints

### B2: Header preset system
- [ ] Create `src/Elementor/Header/HeaderPresets.php` — factory with methods per preset
- [ ] Create `src/Elementor/Header/HeaderBuilder.php` — composable row builder
- [ ] Each preset returns a `TemplateSpec` with full responsive breakpoints
- [ ] Override system: preset + overrides merged (rows, items, positions, colors)
- [ ] 5 presets:
  - [ ] `business`: logo left + nav center + CTA right
  - [ ] `ecommerce`: logo + search + cart row + nav row (refactor from existing EcommerceHeader)
  - [ ] `portfolio`: centered logo + centered nav
  - [ ] `blog`: logo left + nav right (simple)
  - [ ] `saas`: logo + nav + login + CTA
- [ ] All presets: WP nav-menu widget integration, mobile hamburger, sticky support
- [ ] Write unit tests per preset: correct structure, responsive breakpoints, nav menu widget

### B3: `create_header` MCP tool
- [ ] Create `src/MCP/Tools/CreateHeader.php`
- [ ] Input: `preset` (required), `overrides` (optional object), `sticky` (bool), `transparent` (bool)
- [ ] Override structure: `{ rows: [{ items: ['logo', 'nav', 'button:cta'], align: 'center' }] }`
- [ ] Calls `HeaderPresets::{preset}()` then applies overrides via `HeaderBuilder`
- [ ] Installs via `Installer::install_one()` with `_elementor_template_type = 'header'`
- [ ] Clears cache after install
- [ ] Register in `Server.php`
- [ ] Gate: `ACTION_CREATE`
- [ ] Write unit tests: each preset installs correctly, overrides apply, gate enforcement

### B4: Footer preset system + `create_footer` MCP tool
- [ ] Create `src/Elementor/Footer/FooterPresets.php` — matching presets for footers
- [ ] Create `src/MCP/Tools/CreateFooter.php`
- [ ] 3-5 footer presets (simple, multi-column, minimal, with-newsletter)
- [ ] Same override system as headers
- [ ] Register in `Server.php`, gate, tests

### B5: Prompting guide
- [ ] Write `docs/prompting-guide.md` — full workflow examples for Claude
- [ ] Include: page creation flow, header customization, kit globals setup, content editing
- [ ] Include: example prompts for each MCP tool
- [ ] Embed prompting hints in each MCP tool's `description` field

### B6: Phase B commit + gates
- [ ] All new tools registered and tested
- [ ] 100% coverage on new code
- [ ] E2E test: create header via MCP → verify desktop + mobile rendering
- [ ] Commit: `feat(mcp): add set_kit_globals, create_header, create_footer tools with preset system`

## Phase C — Content Manipulation (v0.8.0)

### C1: `get_page_structure` MCP tool (read-only)
- [ ] Create `src/MCP/Tools/GetPageStructure.php`
- [ ] Reads `_elementor_data` via `Encoder::read_document()`
- [ ] Returns annotated tree: section index, widget types, content previews, element IDs
- [ ] No gate check needed (read-only) — or add `ACTION_READ` that always passes
- [ ] Works on any post type with `_elementor_data`
- [ ] Register in `Server.php`
- [ ] Write unit tests

### C2: `edit_section` MCP tool
- [ ] Create `src/MCP/Tools/EditSection.php`
- [ ] Input: `post_id`, `section_index` or `section_id`, `content` (block data)
- [ ] Reads existing document, replaces section at index/ID, writes back
- [ ] Gate: `ACTION_MODIFY` with `$post_id`
- [ ] Clear cache after write
- [ ] Write unit tests: edit by index, edit by ID, gate enforcement, cache cleared

### C3: `delete_section` MCP tool
- [ ] Create `src/MCP/Tools/DeleteSection.php`
- [ ] Input: `post_id`, `section_index` or `section_id`
- [ ] Reads existing document, removes section, writes back
- [ ] Gate: `ACTION_MODIFY` with `$post_id`
- [ ] Write unit tests

### C4: `reorder_sections` MCP tool
- [ ] Create `src/MCP/Tools/ReorderSections.php`
- [ ] Input: `post_id`, `order` (array of section indices or IDs in desired order)
- [ ] Reads existing document, reorders top-level containers, writes back
- [ ] Gate: `ACTION_MODIFY` with `$post_id`
- [ ] Write unit tests

### C5: `update_widget` MCP tool
- [ ] Create `src/MCP/Tools/UpdateWidget.php`
- [ ] Input: `post_id`, `widget_id`, `settings` (partial settings merge)
- [ ] Walks document tree to find widget by ID, merges settings, writes back
- [ ] Gate: `ACTION_MODIFY` with `$post_id`
- [ ] Write unit tests: widget found and updated, widget not found returns error

### C6: `duplicate_section` MCP tool
- [ ] Create `src/MCP/Tools/DuplicateSection.php`
- [ ] Input: `post_id`, `section_index` or `section_id`, `insert_after` (optional index)
- [ ] Reads existing document, deep-clones section (regenerating all IDs), inserts
- [ ] Gate: `ACTION_MODIFY` with `$post_id`
- [ ] Write unit tests: cloned section has new IDs, inserted at correct position

### C7: ACF field support in content tools
- [ ] Add `acf_fields` parameter to `edit_section` and `update_widget`
- [ ] Read ACF fields: `get_field($key, $post_id)` with sanitization
- [ ] Write ACF fields: `update_field($key, sanitized_value, $post_id)` with type-based sanitization
- [ ] Support both Free (relationship → separate CPT) and Pro (repeater inline) modes
- [ ] Write unit tests for each ACF field type

### C8: Safety gate extension for content tools
- [ ] All content manipulation tools pass through `Gate::check()` with `ACTION_MODIFY` + `$post_id`
- [ ] `get_page_structure` bypasses gate (read-only)
- [ ] Verify allowlist enforcement: page_only mode only allows editing allowlisted posts
- [ ] Write integration-style tests: full gate matrix for all new tools

### C9: Phase C commit + gates
- [ ] All 6 new tools registered and tested
- [ ] 100% coverage on new code
- [ ] E2E test: create page → add section → edit section → reorder → delete → verify
- [ ] Commit: `feat(mcp): add content manipulation tools — edit, delete, reorder, update, duplicate sections`

## Verification
- `composer analyse` → 0 errors (PHPStan level 9)
- `composer lint` → 0 errors, 0 warnings
- `composer test:unit` → all tests pass
- Each phase: 100% coverage on new/modified code
- Each phase: E2E test for core functionality

## Risks / Unknowns
- Kit globals write path needs Elementor internals inspection (B1)
- NestedAccordion answer content may need child elements, not just settings (A3)
- Element ID stability across Elementor editor saves affects content tool reliability (C2-C6)
- Elementor Pro dependency for nav-menu widget in headers (B2)
- `_elementor_conditions` cache invalidation timing may cause intermittent stale renders (A4)

## Errors
| Error | Attempt | Resolution |
|-------|---------|------------|
|       | 1       |            |
