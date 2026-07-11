# HR Duties, Checklists, SOP and 5S Workflow

Phase 5 turns the seeded role duties and checklist templates into a usable workflow.

## Scope

- Duties are role-based, not person-based.
- Checklist templates can be daily, shift-based, weekly, monthly, or custom.
- Checklist submissions support done checkbox, quality score, note, and issue flag.
- Approvals can be recorded as manager or inspector approvals.
- Checklist issues can create planner corrective tasks through the Phase 4 planner adapter.

## Admin Routes

- `admin/hr-duties.php`
- `admin/hr-role-duties.php`
- `admin/hr-checklist-templates.php`
- `admin/hr-checklist-assignments.php`
- `admin/hr-checklist-submissions.php`
- `admin/hr-checklist-approvals.php`
- `admin/hr-checklist-report.php`
- `admin/hr-checklist-progress.php`

The approved menu structure is preserved. Existing menu routes continue to work.

## Tables

- `hr_role_duties`
- `hr_checklist_categories`
- `hr_checklist_templates`
- `hr_checklist_items`
- `hr_checklist_assignments`
- `hr_checklist_submissions`
- `hr_checklist_submission_items`
- `hr_checklist_approvals`

## Seed

`database/seeds/seed_key_restaurant_duties_checklists.php` runs the duties/checklists seed stack, adds checklist categories, and adds the business-coaching sales behavior checklist.

## Planner Integration

When a submitted checklist item has an issue flag and the item allows corrective tasks, a planner task is created via `plannerCreateLinkedTask()` with `source_module = checklist`.

## Limitations

This phase creates operational workflow screens and data structures. It does not implement advanced scoring, notification automation, or user provisioning.
