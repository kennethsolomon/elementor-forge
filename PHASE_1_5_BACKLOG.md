# Phase 1.5 — Polish Backlog

Deferred items from the Phase 1, Phase 2, and Phase 3 Layer 2 reviews (Cyra, Sentry, Testa, Perry, Scribe). Nothing in this list is a production blocker; everything is a polish / hardening item that John triaged as "defer to Phase 1.5" during the Phase 3 final fix pass. Items are organized by phase of origin and by review dimension so the reviewer for Phase 1.5 can group them into a single coherent pass.

Ordering inside each dimension is rough priority within that dimension, not a strict sequence.

## Phase 1 deferred

### Security (Sentry)

1. **ApplyTemplate ACF unsanitized writes.** The Phase 1 `apply_template` MCP tool writes caller-supplied ACF field values directly via `update_field()` without sanitization. Same class of issue as Phase 3's BulkGenerate meta-key allowlist but on a different code path. Should also run through the same `_`-prefixed reject + optional `allowed_fields` allowlist.

### Correctness (Cyra)

2. **Encoder silent failure on `wp_json_encode`.** The Phase 1 Encoder swallows `wp_json_encode()` returning `false` and emits `{}` or `[]`. Should raise an explicit `EncoderException` with the full `JSON_ERROR_*` code instead, and callers should surface it as a WP_Error. Silent `{}` writes corrupt `_elementor_data` with no signal.

### Tests (Testa)

3. **Round-trip fixture coverage on the legacy `section`/`column` path.** Phase 1 ships round-trip tests for container-only v0.4 exports, but the Emitter also has a legacy fallback for `section`/`column` elType trees. No tests cover that path.
4. **ACF field group Free-mode tests.** The ACF Free vs Pro branching logic in the field group registrar is tested only on the Pro path. Free-mode tests are missing.

### Docs (Scribe)

5. **`uninstall.php` coverage table.** The uninstall script should have a table in `docs/runbooks/uninstall.md` listing every option, table, capability, and scheduled event the plugin writes, so the review pass can verify the uninstall path by diff instead of by inspection.

## Phase 2 deferred

### Correctness (Cyra)

6. **Header hierarchy unification.** The `service_business` and `ecommerce` header variants have duplicated logic for nav / logo / cart icon placement. Should be refactored into a `HeaderBase` composition so a new variant (e.g. `saas`) can be added without duplicating nav wiring.

### Tests (Testa)

7. **Eight substring-match weak tests.** Eight Phase 2 integration tests assert behavior via `assertStringContainsString('class="shop-grid"', $html)`-style checks. These pass when unrelated HTML happens to contain the substring. Should be rewritten to query the DOM via `DOMDocument` + XPath for the specific expected structure.

### Security (Sentry)

8. **Fibosearch options write path — nonce audit.** The Phase 2 Fibosearch config writer is called from the onboarding wizard (correctly nonce-guarded) but also from the `configure_woocommerce` MCP tool (capability-gated, not nonce-gated — which is correct for MCP but worth documenting). Docs should explicitly note the two entry points and their respective gates.

### Docs (Scribe)

9. **`docs/runbooks/configure-woocommerce.md`** — runbook for re-running the WooCommerce configuration against an existing site (rollback, partial install recovery, how to verify).

## Phase 3 deferred

### Security (Sentry)

10. **Sentry Phase 3 #3 — table name `%i` migration.** Smart Slider 3 table names are currently concatenated directly into prepared SQL via `"{$this->sliders_table}"` with a `phpcs:ignore`. WordPress 6.2+ supports `%i` as an identifier placeholder in `$wpdb->prepare()`. Should migrate to `%i` where supported and drop the ignore.
11. **Sentry Phase 3 #4 — MyISAM transaction engine check.** The Smart Slider + BulkGenerate transaction code calls `$wpdb->query('START TRANSACTION')` without checking the storage engine. On MyISAM, the START is silently ignored and the COMMIT is a no-op — partial state can persist and the code will return a success that isn't. Should probe `information_schema.TABLES` once on activation and store the engine per table in a plugin option; gate the transaction wrapping on InnoDB.
12. **Sentry Phase 3 #5 — `get_progress` capability check.** `BulkGenerate::get_progress()` reads a transient without calling `current_user_can()`. Progress data is not sensitive, but the entry point should still be gated on the same capability as the tool that wrote the transient.
13. **Sentry Phase 3 #6 — version regex tightening.** `SliderRepository::detect_version()` uses `preg_match('/^([\d.]+)/', ...)` which accepts `..` and `...` as version prefixes. Should tighten to `/^(\d+\.\d+\.\d+(?:\.\d+)?)/` for Smart Slider's `MAJOR.MINOR.PATCH.BUILD` convention.
14. **Sentry Phase 3 #7 — `last_error` leak.** `SmartSliderUnavailable` exception messages include `$wpdb->last_error` verbatim. In a verbose MySQL error, this can include the raw query. Should be scrubbed to a stable error code before surfacing, with the full `last_error` logged to the PHP error log instead.

### Correctness (Cyra)

15. **`SmartSliderUnavailable` exception split.** The single exception type is used for "plugin missing", "version out of range", "capability missing", and "DB write failed". These are four different categories of caller concern. Should be split into `SmartSliderNotInstalled`, `SmartSliderVersionOutOfRange`, `SmartSliderAccessDenied`, `SmartSliderWriteFailed`.
16. **Cyra Phase 3 #5 — BulkGenerate nested-transaction detection.** `BulkGenerate::execute()` calls `START TRANSACTION` unconditionally when `transactional: true`. If the caller is already inside a transaction (e.g. a WP-CLI command that wrapped its own), we issue a nested START which many storage engines silently downgrade to a savepoint. Should probe `@@in_transaction` or track the state via a plugin-owned flag.
17. **ManageSlider `slider_id=0` rejection.** The `ManageSlider` MCP tool's `int()` helper coerces missing / invalid `slider_id` to `0`. A downstream `$wpdb->delete($table, ['id' => 0])` then does nothing and returns `0 rows affected`, which the caller sees as a successful delete of "nothing". Should explicitly reject `slider_id <= 0` with a `WP_Error`.
18. **Smart Slider 3 Pro (`N2SSPRO`) discriminator.** The `SliderRepository` gates on Smart Slider 3 Free only; when Pro is active, the constant and table names differ. Should either explicitly refuse Pro with a clear error or add a Pro discriminator path.

### Tests (Testa)

19. **LayoutJudge rule-combination coverage.** Each of the 6 rules has a happy-path test. There are no tests for situations where two rules both match with close confidence scores — the tie-breaking behavior is untested.
20. **SliderRepository integration tests on a real wp-env DB.** Unit tests cover every SliderRepository method against the `wpdb` stub. Integration tests against a real MySQL instance inside wp-env are not yet written; the schema assumptions (column types, JSON encoding compatibility with the Smart Slider front-end) are only verified by source inspection, not by round-trip.

### Performance (Perry)

21. **Perry Phase 3 #X — `JudgedEmitter` rule evaluation caching.** `LayoutJudge::decide()` re-runs every rule on every section evaluation. For the bulk page generator's 50-item run, that's 300 rule evaluations. Should memoize `SectionData` hash → decision.

### Docs (Scribe)

22. **`docs/runbooks/smart-slider-3-crud.md`** — runbook for the Smart Slider 3 CRUD layer: version compatibility matrix, cache invalidation behavior, how to recover a corrupted slider, how to roll back a mid-sequence delete failure.
23. **`docs/runbooks/bulk-generate-pages.md`** — runbook for the `bulk_generate_pages` MCP tool: matrix mode recipes, progress polling, rollback on failure, how to re-run after a partial run.

## Cross-phase infrastructure deferred

24. **PHPStan 2.x upgrade.** PHPStan 2.0 adds level 10, list types, `@phpstan-pure` enforcement, and uses 50-70% less memory. Package is pinned to `^1.11` and upgrade requires a config migration. Hux has been ignoring the "Tell the user PHPStan 2.x is available" nag message on every run since Phase 2; noise instances 1-7 acknowledged across the three phases.
25. **CI matrix — PHP 8.0, 8.1, 8.2, 8.3, 8.4 × WP 6.4, 6.5, 6.6, 6.7, 6.8 × Elementor 3.20, 3.25, 3.30.** Currently only PHP 8.4 + WP 6.5 + Elementor 3.20 is exercised locally. Full matrix should land in Phase 1.5 via GitHub Actions.
26. **Strauss or php-scoper dependency prefixing.** `wordpress/mcp-adapter` ships under its own namespace, which is fine today but will conflict with any other plugin that ships the same dep once Forge hits 1000+ installs. Should be prefixed via Strauss before first public release.
