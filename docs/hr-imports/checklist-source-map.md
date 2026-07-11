# KEY Restaurant Checklist Templates Seed

SEED-3 creates checklist templates and checklist items for KEY Restaurant operations. It does not create submissions, assignments, planner tasks, or user links.

## Source Map

- Daily operations checklist for internal manager
- Cashier shift checklist
- Hall captain checklist
- Delivery rider operational checklist
- Kitchen daily checklist
- Marketing and sales daily/weekly checklist

## Tables

- `hr_checklist_templates`
- `hr_checklist_items`

The existing `template_key` remains supported. `template_code` is stored as an alias for stable seed mapping.

## Counts

| Template Code | Role Code | Items |
| --- | --- | ---: |
| `internal_manager_daily_visit_checklist` | `internal_manager` | 15 |
| `cashier_daily_shift_checklist` | `cashier` | 16 |
| `hall_captain_daily_checklist` | `hall_captain` | 12 |
| `delivery_rider_operational_checklist` | `delivery_rider` | 13 |
| `kitchen_daily_checklist` | `head_chef` | 8 |
| `marketing_sales_daily_weekly_checklist` | `marketing_sales_manager` | 6 |

Total: 6 templates and 70 checklist items.

## Seed Behavior

- `template_code` maps to `template_key` for compatibility.
- `item_code` is unique within a template.
- Existing templates/items are updated when source data changes.
- Unchanged templates/items are skipped.
- Running the seed twice must not create duplicate templates or items.

## Next Phase

SEED-4 should create KPI definitions only. Checklist submissions and assignments should be created later by runtime workflows, not by this seed.
