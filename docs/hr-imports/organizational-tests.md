# Organizational Tests and Business Standard Assessments

Phase 7 rebuilds KEY restaurant organizational tests as HR, training, service, sales, and business coaching assessments.

## Scope

- Tests are for KEY restaurant personnel.
- Tests are not timed.
- Tests are period-based through `period_id` on assignments.
- `hr_assessment_tests` remains the canonical test bank.
- Results are organizational, educational, and managerial only.

Result pages must show:

`این نتیجه برای تحلیل سازمانی، آموزشی و مدیریتی استفاده می‌شود و جایگزین ارزیابی تخصصی، پزشکی یا روان‌شناسی نیست.`

## Routes

- `admin/hr-tests.php`
- `admin/hr-test-questions.php`
- `admin/hr-test-assignments.php`
- `admin/employee-tests.php`
- `admin/hr-test-results.php`
- `admin/hr-test-report.php`

Legacy routes now redirect to the new implementation:

- `admin/evaluation-builder.php` -> `admin/hr-tests.php`
- `admin/employee-evaluation-settings.php` -> `admin/hr-tests.php`
- `admin/employee-assessments.php` -> `admin/hr-test-results.php`

## Tables

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

## Seed

Run `database/seeds/seed_key_organizational_tests.php` through the existing update/seed flow or the `Seed آزمون‌های KEY` button on `admin/hr-tests.php`.

Seed groups:

- Organizational behavior
- Restaurant operational knowledge
- Sales and customer interaction
- Marketing and content
- KPI and reporting literacy

The seed is idempotent and stores options both in `options_json` for the existing employee test renderer and in `hr_test_options` for normalized reporting.

## Retake Policy

- `free`: request is auto-approved.
- `manager_approval_required`: manager/HR reviews it on `admin/hr-test-assignments.php`.

Approved retake requests add one extra allowed submitted attempt without changing historical attempts.

## Validation

Use PHP lint on changed PHP files and run `tests/hr_organizational_tests_contract_test.php`.
