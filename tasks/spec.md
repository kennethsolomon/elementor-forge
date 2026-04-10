# Spec: Full-Spectrum Audit & Improvement of Elementor Forge

## Metadata
- Interview rounds: 4
- Final ambiguity: 19%
- Status: PASSED
- Generated: 2026-04-10

## Goal
Perform a comprehensive audit of the Elementor Forge WordPress plugin across four dimensions — code quality & architecture, security hardening, performance & reliability, and test coverage — then fix all identified issues to bring the plugin to production-grade quality.

## Constraints
- Breaking changes to MCP tool signatures, function APIs, and internal structure are allowed (pre-1.0, not registered/distributed yet)
- Must maintain backwards compatibility with WordPress 6.4+ (minimum supported version)
- PHP 8.0+ target (per composer.json)
- All changes must pass PHPStan level 9, PHPCS (WPCS), and existing tests
- Test environment exists via wp-env — use it for integration verification

## Non-Goals (explicitly excluded)
- Adding new features or MCP tools
- Changing the plugin's public-facing behavior (what it does stays the same — how it does it gets better)
- Upgrading WordPress minimum version beyond 6.4
- UI/UX redesign of the admin settings page

## Acceptance Criteria
- [ ] PHPStan level 9 clean — zero warnings on `src/` and `tests/`
- [ ] PHPCS (WPCS 3.1) clean — zero violations
- [ ] All PHPUnit tests pass (unit + integration suites)
- [ ] Test coverage >= 80% line coverage on `src/`
- [ ] Zero OWASP Top 10 vulnerabilities: all user input sanitized, all output escaped, nonces on all forms/AJAX, capability checks on all MCP tools
- [ ] No SQL injection vectors (parameterized queries or `$wpdb->prepare()` everywhere)
- [ ] No XSS vectors (all dynamic output escaped with appropriate `esc_*()` function)
- [ ] Performance: no N+1 query patterns in bulk generation, no unbounded loops
- [ ] Architecture: no SOLID violations that impact maintainability (dead code removed, coupling reduced where practical)
- [ ] CHANGELOG.md updated with all breaking changes documented

## Assumptions Exposed
| Assumption | How surfaced | Resolution |
|------------|-------------|------------|
| Plugin is in active use on production sites | Round 4 | Not registered/distributed — pre-1.0, breaking changes OK |
| WP backwards compatibility matters | Round 4 | Yes — maintain WP 6.4+ support |
| All four audit dimensions are equally important | Round 1 | User selected all four — no priority ranking |
| Fixing everything in one session is feasible | Round 2 | User chose "audit + fix everything" |

## Technical Context
- WordPress plugin at v0.5.0 with 5 completed development phases
- 12 src/ modules: ACF, Admin, CPT, Elementor, Intelligence, Lifecycle, MCP, Onboarding, Safety, Settings, SmartSlider, Support, WooCommerce
- 6 MCP tools exposed via wordpress/mcp-adapter
- PHPUnit 9.6 with unit + integration suites, Playwright for E2E
- PHPStan already configured with WordPress/WooCommerce stubs
- PHPCS configured with WPCS + PHPCompatibility

## Ontology
Core entity: Elementor Forge plugin (WordPress plugin for Elementor page generation via MCP)
Supporting concepts: MCP tools, Elementor emitter, section templates, bulk generation, safety modes
