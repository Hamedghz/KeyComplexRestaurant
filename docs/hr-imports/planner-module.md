# Planner / Daily Task Module

Phase 4 adds Planner as the internal execution layer for HR, KPI, checklist, OKR/TMO, and business coaching follow-up work.

## What changed

- Canonical planner tables are `planner_tasks`, `planner_task_logs`, and `planner_task_comments`.
- Existing HR shell routes remain available; the visible HR menu now opens `admin/planner.php`.
- `admin/includes/planner-widget.php` adds a compact dashboard surface with quick add, today tasks, overdue count, tomorrow count, mark done, and transfer to tomorrow.
- Integration modules should create tasks through `admin/lib/hr/planner/planner_adapter.php`.

## Integration points

Use `plannerCreateLinkedTask()` for generic linked work and the wrapper helpers for specific sources:

- `plannerCreateChecklistTask()`
- `plannerCreateKpiCorrectiveTask()`
- `plannerCreateOkrActionTask()`
- `plannerCreateBusinessCoachingTask()`

The CRM fields `linked_customer_id` and `linked_followup_id` are nullable future integration points only. Phase 4 does not build or modify CRM.

## Access model

- Employees see and manage their own tasks.
- Managers with planner team access can see department/team tasks.
- TMO users can see OKR/action-linked tasks.
- Admin and HR-style permission holders can view broader planner reports.

## Migration notes

Run the normal system update/migration workflow. The migration is additive and uses `CREATE TABLE IF NOT EXISTS`.

## Rollback notes

No data is removed by this phase. To disable the feature without deleting data, mark the `hr_planner_mine` navigation row inactive or point it back to the older HR shell route.
