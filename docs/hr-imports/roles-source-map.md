# KEY Restaurant Roles Seed

SEED-1 creates role definitions for the KEY Restaurant personnel structure. It does not create real users and does not assign existing admins or users to roles.

## Source Map

- چارت پرسنلی رستوران کیکاووس
- شناسنامه شغلی و عملیاتی رستوران
- اسناد شرح وظایف داخلی

## Table

Role definitions are stored in `hr_roles` with stable `role_code` values. The table is additive and can be created by the migration or by the idempotent seed if the migration has not run yet.

## Hierarchy

| Level | Role Code | Parent |
| --- | --- | --- |
| 0 | `restaurant_owner` | - |
| 1 | `restaurant_manager` | `restaurant_owner` |
| 2 | `tmo_owner` | `restaurant_manager` |
| 2 | `internal_manager` | `restaurant_manager` |
| 2 | `hr_manager` | `restaurant_manager` |
| 2 | `accountant` | `restaurant_manager` |
| 2 | `marketing_sales_manager` | `restaurant_manager` |
| 2 | `purchasing_manager` | `restaurant_manager` |
| 2 | `legal_advisor` | `restaurant_owner` |
| 2 | `financial_advisor` | `restaurant_owner` |
| 3 | `cashier` | `internal_manager` |
| 3 | `hall_captain` | `internal_manager` |
| 3 | `head_chef` | `internal_manager` |
| 3 | `page_admin` | `marketing_sales_manager` |
| 3 | `content_creator` | `marketing_sales_manager` |
| 4 | `waiter` | `hall_captain` |
| 4 | `hall_service` | `hall_captain` |
| 4 | `assistant_chef` | `head_chef` |
| 4 | `kitchen_service` | `head_chef` |
| 4 | `delivery_rider` | `internal_manager` |
| 4 | `flyer_distributor` | `marketing_sales_manager` |

## Seed Behavior

- `role_code` is the stable unique key.
- Existing rows are updated only when the role definition has changed.
- Unchanged rows are skipped.
- Running the seed twice must not create duplicate rows.

## Next Phase

SEED-2 should create role duties linked by `role_code`; it should not create checklists or KPI definitions.
