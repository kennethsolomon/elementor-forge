# Architecture Decision Records

A cumulative log of key design decisions made across features. Append-only — never overwrite.

## [2026-04-10] Full-Spectrum Quality Audit

**Context:** Elementor Forge v0.5.0 needs production-grade quality across code, security, performance, and test coverage before distribution.
**Decision:** Layered sweep approach — fix code quality first, then performance, then write comprehensive tests, then verify all gates pass.
**Rationale:** Issues cluster by type (not module). Cleaning code before testing means tests validate the improved version. Security is already excellent — no remediation needed.
**Consequences:** Breaking changes allowed (pre-1.0). God classes (Page.php, SliderRepository.php) will be split. Error handling standardized to exceptions internally, WP_Error at boundaries. 52 untested classes need new test coverage.
**Status:** accepted
