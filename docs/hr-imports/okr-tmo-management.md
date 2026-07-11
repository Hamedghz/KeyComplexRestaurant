# OKR / KR / TMO Person Management

Phase 8 adds monthly OKR management for KEY restaurant operations.

## Principles

- TMO is a person selected from existing admins/users.
- TMO is not a standalone objective.
- Monthly objectives are defined at company, department, or team scope.
- Key Results can be numeric or descriptive.
- Actions can be converted into planner tasks.
- Progress can come from manual input, planner task completion, or linked KPI scores.

## Routes

- `admin/okr-objectives.php`
- `admin/okr-key-results.php`
- `admin/okr-actions.php`
- `admin/okr-progress.php`
- `admin/tmo-review.php`
- `admin/tmo-dashboard.php`

Legacy HR shell routes redirect to the canonical routes:

- `admin/hr-okr-objectives.php` -> `admin/okr-objectives.php`
- `admin/hr-okr-key-results.php` -> `admin/okr-key-results.php`
- `admin/hr-okr-actions.php` -> `admin/okr-actions.php`
- `admin/hr-okr-progress.php` -> `admin/okr-progress.php`
- `admin/hr-tmo-reviews.php` -> `admin/tmo-review.php`

## Tables

- `okr_objectives`
- `okr_key_results`
- `okr_actions`
- `okr_kpi_links`
- `okr_progress_logs`
- `tmo_reviews`

The older `hr_okr_*` shell tables are left untouched for backward compatibility but are not the canonical Phase 8 implementation.

## Progress Rule

For actions, planner progress is used when a planner task exists. Manual progress can override by final rule:

`final_progress = max(manual_progress, calculated_progress)`

For KRs, progress is calculated from linked actions, linked KPI scores, and numeric current/target values.

For objectives, progress is the weighted average of KRs plus objective-level KPI links.

## Business Coaching Examples

The OKR page can seed examples for:

- Improve sales conversion
- Improve customer experience
- Improve operational discipline
- Improve marketing and content

## Validation

Run `tests/okr_tmo_management_contract_test.php` and PHP lint for changed PHP files.
