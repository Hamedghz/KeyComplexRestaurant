# KEY Restaurant Role Duties Seed

SEED-2 creates role-based duty definitions for KEY Restaurant personnel. Duties are linked by `role_code` only and are stored in `hr_role_duties`.

## Source Map

- شناسنامه شغلی و عملیاتی رستوران
- شرح وظایف مدیر داخلی، صندوق، سالن، آشپزخانه، پیک، بازاریابی و خرید
- چارت نقش‌های SEED-1

## Scope

This phase creates duties only. It does not create checklist templates, KPI definitions, planner tasks, users, or user assignments.

## Counts

| Role Code | Duties |
| --- | ---: |
| `internal_manager` | 10 |
| `cashier` | 8 |
| `hall_captain` | 7 |
| `waiter` | 5 |
| `head_chef` | 6 |
| `assistant_chef` | 5 |
| `hall_service` | 5 |
| `kitchen_service` | 5 |
| `delivery_rider` | 5 |
| `marketing_sales_manager` | 6 |
| `page_admin` | 3 |
| `purchasing_manager` | 2 |

Total: 67 duties.

## Seed Behavior

- `duty_code` is the stable unique key.
- Existing duties are updated when source text changes.
- Unchanged duties are skipped.
- Running the seed twice must not create duplicates.

## Next Phase

SEED-3 should create checklist templates from these duties. It should keep checklist instances separate from duty definitions.
