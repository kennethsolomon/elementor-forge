# TODO — 2026-04-10 — Full-Spectrum Quality Audit & Improvement

## Goal
Bring Elementor Forge to production-grade quality: fix code quality issues, resolve performance problems, achieve 80%+ test coverage across 35 untested classes, and pass all quality gates (PHPStan level 9, PHPCS, zero OWASP vulns).

## Plan

### Milestone 1: Code Quality Fixes

#### Wave 1 (parallel — independent fixes)
- [ ] Fix 3 PHPCS warnings: remove commented code in ManageSlider.php:81, replace file_get_contents in test files with WP-compatible alternative
- [ ] Standardize error handling: add `throw_if_wp_error()` helper in `src/Support/`, convert internal WP_Error returns to exceptions (keep WP_Error only at WP API boundaries — hooks, REST)
- [ ] Remove @phpstan-ignore suppression in SliderRepository.php — fix the underlying type issue

#### Wave 2 (depends on Wave 1 — refactoring after lint is clean)
- [ ] Split `Admin\Settings\Page.php` (633L): extract tab renderers into `Admin\Settings\Tabs\{GeneralTab,MCPTab,SafetyTab}.php`, keep Page.php as orchestrator
- [ ] Split `SliderRepository.php` (567L): extract validation/sanitization into `SmartSlider\SliderSanitizer.php`

### Milestone 2: Performance Fixes

#### Wave 3 (parallel — independent perf fixes)
- [ ] Fix N+1 transient in BulkGenerate.php:414-424: batch progress writes (update every 5 items + on completion, not every iteration)
- [ ] Cache Store::all() at request level: add static property cache in Store.php, invalidate on option update via `update_option_{key}` hook

### Milestone 3: Test Coverage (35 untested classes → 80%+ line coverage)

#### Wave 4 (parallel — core emission layer, highest blast radius)
- [ ] Tests for Elementor/Emitter: Node, RawNode, Widget (3 classes)
- [ ] Tests for Elementor/Schema: Breakpoints, Units (2 classes)
- [ ] Tests for Elementor/ThemeBuilder: DynamicWidget, Installer, TemplateSpec (3 classes)

#### Wave 5 (parallel — intelligence layer)
- [ ] Tests for Intelligence/LayoutJudge: Decision, Rule base class (2 classes)
- [ ] Tests for Intelligence/LayoutJudge/Rules: FaqRule, GalleryRule, IconBoxGridRule, IconListRule, NestedCarouselRule, TextHeavyAccordionRule (6 classes)

#### Wave 6 (parallel — MCP layer)
- [ ] Tests for MCP/Tools: AddSection, ApplyTemplate, CreatePage (3 classes)
- [ ] Tests for MCP/Internal: WP_Ability, WP_Abilities_Registry, WP_Ability_Category, WP_Ability_Categories_Registry (4 classes)
- [ ] Tests for MCP/Server (1 class)

#### Wave 7 (parallel — infrastructure + lifecycle)
- [ ] Tests for Lifecycle: Activator, Deactivator (2 classes)
- [ ] Tests for Onboarding: Wizard, PluginInstaller, PluginInstallerInterface (3 classes)
- [ ] Tests for Plugin.php main class (1 class)

#### Wave 8 (parallel — settings + remaining modules)
- [ ] Tests for Settings: Defaults, OptionKeys (2 classes)
- [ ] Tests for ACF/Registrar (1 class)
- [ ] Tests for CPT/Registrar (1 class)
- [ ] Tests for SmartSlider/SmartSliderUnavailable (1 class)
- [ ] Tests for WooCommerce/ThemeBuilder/Installer (1 class)
- [ ] Tests for Admin/Settings/Page (after Wave 2 split — test new tab classes)

### Milestone 4: Gate Verification

#### Wave 9 (sequential — final quality gates)
- [ ] Run PHPStan level 9 — fix any new warnings from refactored code
- [ ] Run PHPCS — verify zero violations
- [ ] Run full PHPUnit suite — verify all tests pass (unit + integration)
- [ ] Generate coverage report — verify ≥ 80% line coverage on src/
- [ ] Update CHANGELOG.md with all breaking changes from Milestones 1-2

## Verification
- `composer analyse` → 0 errors (PHPStan level 9)
- `composer lint` → 0 errors, 0 warnings
- `composer test:unit` → all tests pass
- `composer test:unit -- --coverage-text` → ≥ 80% line coverage on src/
- `grep -r 'is_wp_error' src/ | grep -v 'throw_if_wp_error'` → only at WP API boundaries

## Acceptance Criteria
- [ ] PHPStan level 9 clean — zero warnings on src/ and tests/
- [ ] PHPCS (WPCS 3.1) clean — zero violations
- [ ] All PHPUnit tests pass (unit + integration suites)
- [ ] Test coverage ≥ 80% line coverage on src/
- [ ] Zero OWASP Top 10 vulnerabilities (already verified — maintain)
- [ ] No N+1 query patterns in bulk generation
- [ ] God classes split (Page.php < 200L, SliderRepository.php < 300L)
- [ ] Error handling standardized (exceptions internal, WP_Error at boundaries)
- [ ] CHANGELOG.md updated with breaking changes

## Risks / Unknowns
- Splitting Page.php may break admin settings rendering — verify with wp-env after refactor
- Some untested classes may have hard WP dependencies requiring complex mocking — budget extra time for Wave 6 (MCP internals)
- Coverage target (80%) may require testing Admin views currently excluded in phpunit.xml.dist — may need to adjust exclusion
- Breaking changes from god class splits need careful namespace/import updates across all consuming code

## Results
- (fill after execution)

## Errors
| Error | Attempt | Resolution |
|-------|---------|------------|
|       | 1       |            |
