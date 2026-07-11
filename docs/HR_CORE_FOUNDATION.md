# HR Core Foundation

## What changed

Phase 2 adds a shared HR foundation under `admin/lib/hr/` and additive database tables for periods, dynamic fields, audit logs, module settings, and business coaching standards.

The foundation does not rewrite old HR pages. Existing routes such as `employee-evaluation-settings.php`, `employee-tests.php`, `employee-assessments.php`, `employee-performance.php`, and `hr-test-report.php` remain available.

## Architecture rules

- `hr_assessment_tests` remains the canonical organizational test catalog.
- `hr_periods` is a shared period layer and does not replace `hr_evaluation_periods` yet.
- `business_standards` and `business_standard_items` are reference and seed tables only.
- Business standards must be consumed by tests, checklists, KPI, planner, and OKR/TMO. They must not become a separate visible admin module.
- The admin menu continues to use `admin_navigation_items` and the shared sidebar renderer.

## Migration and seed

Run migrations through the existing System Update workflow. The Phase 2 migration is additive and uses `CREATE TABLE IF NOT EXISTS`.

Seed data should be idempotent. The business standards seed writes stable keys and may be safely re-run.

## Validation

- Lint changed PHP files with `php -l`.
- Run `tests/hr_performance_suite_contract_test.php`.
- Confirm admin login, sidebar rendering, and `admin/hr-dashboard.php` manually.

## Rollback

Rollback code by reverting the Phase 2 files. Database rollback should be logical: deactivate menu rows or records if needed. Do not physically remove tables or data without explicit approval.
