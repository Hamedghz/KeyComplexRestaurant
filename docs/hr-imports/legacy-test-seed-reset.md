# Legacy HR Test Seed Reset

## What Changed

FINAL-3 disables the old `database/seeds/seed_restaurant_hr_tests.php` question bank and adds a protected reset flow for the canonical restaurant organizational tests seed:

- Canonical table: `hr_assessment_tests`
- Canonical seed key: `key_restaurant_organizational_tests`
- Canonical seed file: `database/seeds/seed_key_restaurant_organizational_tests.php`
- Reset service: `admin/lib/hr/tests/test_seed_reset_service.php`

The old seed file remains in the repository for backward compatibility, but `hrSeedRestaurantProfessionalTests()` now returns a disabled/skipped result and does not insert the old questions.

## How Reset Works

The reset is available only from `admin/system-update.php` for `super_admin` users.

Required safeguards:

- CSRF token is required.
- Typed confirmation must be exactly `RESET_HR_TESTS`.
- Only HR/test-domain tables from the service whitelist are affected.
- Every non-empty allowed table is archived first into `backup_<table>_<YYYYMMDD_HHMMSS>`.
- Old HR/test rows are removed with dependency-ordered `DELETE` statements.
- The new restaurant organizational tests seed is run through the central seed runner.
- Verification checks confirm new KEY test codes, questions, and options exist.

## Tables In Scope

The reset whitelist is limited to the organizational test domain:

- `hr_assessment_tests`
- `hr_test_dimensions`
- `hr_test_questions`
- `hr_test_options`
- `hr_test_scoring_rules`
- `hr_test_assignments`
- `hr_test_attempts`
- `hr_test_responses`
- `hr_test_results`
- `hr_test_retake_requests`
- `hr_test_audit_logs`

Legacy HR/test tables are considered only when they exist and are clearly test-domain tables.

## Out Of Scope

The reset must not touch admins, users, orders, public menu data, CRM/customer data, analytics, banners, matches, predictions, settings, media, public website data, or login/auth architecture.

## Rerun

To safely rerun the new question bank without reset, use System Update:

1. Register default seeds.
2. Force rerun `key_restaurant_organizational_tests`.

This seed is idempotent and uses stable test, dimension, question, and option codes.

## Limitations

This reset is implemented but not automatically executed. Old rows remain until a `super_admin` runs `reset_hr_test_seed_only` from System Update with the required confirmation phrase.
