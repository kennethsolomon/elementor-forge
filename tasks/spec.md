# Spec: Elementor Forge Audit + MCP Capability Expansion

## Metadata
- Interview rounds: 5
- Final ambiguity: 18%
- Status: PASSED
- Generated: 2026-04-10

## Goal
Audit and fix all broken functionality in Elementor Forge, then expand the MCP tool surface into a complete Elementor automation platform: fix all 13+ broken emitters, add intelligent header creation with presets + full override customization, add Kit globals management, add content manipulation tools (edit/delete/reorder on any Elementor page/template with safety mode), and provide a prompting guide for the full workflow. Delivered in 3 phased releases.

## Constraints
- Phased delivery: Phase A (v0.6.0) fixes, Phase B (v0.7.0) headers + kit, Phase C (v0.8.0) content manipulation
- Breaking changes allowed (pre-1.0, not distributed)
- Safety mode (allowlist + scope modes) enforced on ALL content manipulation tools
- Content tools must work on ANY Elementor page, template, or section — not just Forge-created ones
- Must also support ACF fields, Contact Form 7, and other WordPress/Elementor ecosystem tools
- PHP 8.0+ / WordPress 6.4+ minimum maintained
- All changes must pass PHPStan level 9, PHPCS (WPCS), existing tests
- Prompting guide: standalone doc + embedded in MCP tool descriptions

## Non-Goals (explicitly excluded)
- Replacing Elementor's visual editor (Forge is the programmatic layer)
- Supporting non-Elementor page builders
- Building a SaaS/hosted service around Forge
- Admin UI redesign (beyond settings needed for new features)

## Acceptance Criteria

### Phase A — Fix Broken Core (v0.6.0)
- [ ] Column layouts render as actual columns (width dimension object, not _flex_size: grow)
- [ ] Hero blocks have background color/image defaults
- [ ] FAQ blocks emit both questions AND answers into NestedAccordion
- [ ] Elementor cache cleared after every write operation (files_manager + conditions cache + per-post CSS)
- [ ] Encoder throws EncoderException on wp_json_encode failure (no silent empty string)
- [ ] apply_template sanitizes ACF field values before write
- [ ] create_page sets _wp_page_template to elementor_header_footer
- [ ] manage_slider rejects slider_id=0 with error
- [ ] Flex gap math computed correctly for multi-column grids
- [ ] 100% test coverage on all fixed code paths
- [ ] E2E test: create a page with card_grid, verify columns render side-by-side

### Phase B — Headers + Kit Globals (v0.7.0)
- [ ] `set_kit_globals` MCP tool: sets brand colors, typography, button styles on Default Kit
- [ ] `create_header` MCP tool with 5+ presets (business, ecommerce, portfolio, blog, saas)
- [ ] Each preset is fully overridable (rows, items, positions, colors, sticky, responsive)
- [ ] Headers use real WP nav menus (wp_nav_menu registered locations)
- [ ] Mobile responsive: hamburger/off-canvas nav on mobile breakpoint
- [ ] Sticky header support (shrink on scroll via Elementor sticky settings)
- [ ] `create_footer` MCP tool with matching presets
- [ ] Proper Theme Builder conditions set on header/footer templates
- [ ] Visual match: header output comparable to Astra Pro / GeneratePress quality
- [ ] E2E test: create header via MCP, verify desktop nav + mobile hamburger + sticky behavior
- [ ] Prompting guide doc written (docs/prompting-guide.md)

### Phase C — Content Manipulation (v0.8.0)
- [ ] `get_page_structure` MCP tool: read-only, returns document tree of any Elementor page/template
- [ ] `edit_section` MCP tool: modify content of a section by index or ID
- [ ] `delete_section` MCP tool: remove a section from a page
- [ ] `reorder_sections` MCP tool: change section order
- [ ] `update_widget` MCP tool: update widget properties by widget ID
- [ ] `duplicate_section` MCP tool: clone a section within a page
- [ ] All content tools enforce safety mode (full/page_only/read_only + allowlist)
- [ ] Works on pages, templates, sections, and any post type with _elementor_data
- [ ] ACF field editing via content tools (read + write with sanitization)
- [ ] Contact Form 7 integration (shortcode widget creation/update)
- [ ] Tool descriptions include prompting hints for Claude
- [ ] 100% test coverage on new tools
- [ ] E2E test: create page, add section, edit section, reorder, delete — verify each step

## Assumptions Exposed
| Assumption | How surfaced | Resolution |
|------------|-------------|------------|
| Plugin is pre-1.0 and not distributed | Round 2 | Confirmed — breaking changes OK |
| Headers need AI-level intelligence | Round 4 | No — presets + overrides, Claude is the intelligence layer |
| Content tools only for Forge-created pages | Round 5 | No — any Elementor page/template, with safety mode |
| Scope limited to Elementor widgets | Round 5 | No — also ACF, CF7, and other WP ecosystem tools |
| Prompting guide is a nice-to-have | Round 5 | No — required, both standalone doc and embedded in tool descriptions |

## Technical Context
- WordPress plugin at v0.5.0, 5 completed dev phases
- 12 src/ modules with PSR-4 autoloading under ElementorForge\ namespace
- 6 existing MCP tools via wordpress/mcp-adapter ^0.4
- Existing safety gate (Gate::check) already enforces scope modes — extend for new tools
- LayoutJudge intelligence layer exists — can be extended for header layout decisions
- PHPUnit 9.6 (unit + integration), Playwright E2E, PHPStan level 9, PHPCS/WPCS
- 52 classes currently have zero test coverage (all 15 widgets, 6 LayoutJudge rules)
- 13 known broken emitter issues documented in PHASE_1_5_BACKLOG.md
- Existing header variants: service_business (minimal) and ecommerce (more complete)

## Ontology
Core entity: Elementor Forge MCP tool surface (programmatic Elementor automation)
Supporting concepts: header presets, kit globals, content manipulation tools, safety mode/allowlist, emitter system, LayoutJudge, prompting guide, Theme Builder templates, ACF integration, Contact Form 7 integration
