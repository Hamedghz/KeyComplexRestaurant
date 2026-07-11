# KPI Evaluation Workflow

Phase 6 implements KPI definitions, assignments, manual entries, score calculation, RAG reporting, and corrective planner tasks.

## Formula

For positive KPIs:

`score_percent = actual_value / target_value * 100`

For negative KPIs:

`score_percent = target_value / actual_value * 100`

`weighted_score = score_percent * weight / 100`

RAG:

- Green: `>= 90`
- Yellow: `>= 70 and < 90`
- Red: `< 70`

## Routes

- `admin/hr-kpi-definitions.php`
- `admin/hr-kpi-assignments.php`
- `admin/hr-kpi-entries.php`
- `admin/hr-kpi-scores.php`
- `admin/hr-kpi-report.php`
- `admin/hr-kpi-reports.php`

## Planner Integration

Red KPI scores create a row in `hr_kpi_corrective_actions` and attempt to create a Phase 4 planner task through `plannerCreateKpiCorrectiveTask()`.

## Limits

Values are manually entered for now. This phase does not implement automatic VoIP, CRM, finance, or OKR progress synchronization; it creates the fields and scoring layer those modules can consume.
