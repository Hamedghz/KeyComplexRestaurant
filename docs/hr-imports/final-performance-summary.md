# Final HR Integration and Performance Summary

Phase 9 connects the HR foundation modules into one employee performance summary.

## Relationship

- Organizational tests produce `hr_test_results`.
- Checklists produce completion and quality scores in `hr_checklist_submissions`.
- KPI produces weighted scores in `hr_kpi_scores`.
- Planner produces task completion through `planner_tasks`.
- OKR/KR uses planner task progress and linked KPI scores.
- TMO reviews objectives through `tmo_reviews`.
- Business coaching standards feed tests, checklists, KPI, planner, and OKR seeds.

## Page

`admin/hr-performance-summary.php`

The page shows:

- Employee, role, and department
- Current period or target month
- Test score
- Checklist completion and quality score
- KPI weighted score
- Planner completion
- OKR/action participation
- Sales standard compliance when KPI standards are available
- Customer handling score when KPI standards are available
- Final score
- Trend
- Warnings
- Manager notes

## Score Weights

Weights are stored in `hr_module_settings`:

- Module: `performance_summary`
- Setting: `score_weights`

Default:

```json
{"kpi":40,"checklist":25,"planner":20,"tests":15}
```

The page uses the configured weights and ignores missing component scores instead of treating absent data as zero.

## Legacy Routes

Legacy files remain for backward compatibility and redirect safely:

- `admin/evaluation-builder.php` -> `admin/hr-tests.php`
- `admin/employee-evaluation-settings.php` -> `admin/hr-tests.php`
- `admin/employee-assessments.php` -> `admin/hr-test-results.php`
- `admin/employee-performance.php` -> `admin/hr-performance-summary.php`

## Menu Cleanup

Old visible HR menu items are hidden through migration. New implementation routes remain under `ارزیابی، عملکرد و اهداف`.

No files, tables, columns, or data are deleted.
