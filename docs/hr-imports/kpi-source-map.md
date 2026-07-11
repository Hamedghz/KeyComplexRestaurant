# KEY Restaurant KPI Definitions Seed

SEED-4 creates KPI definitions only. It does not create KPI entries, scores, assignments, or user links.

## Tables

- `hr_kpi_definitions`

The existing `kpi_key` remains supported. `kpi_code` is stored as an alias for stable seed mapping.

## Counts

| Area | Count |
| --- | ---: |
| Internal manager | 5 |
| Cashier | 4 |
| Hall captain | 4 |
| Waiter | 4 |
| Delivery | 4 |
| Marketing and sales | 13 |
| BSF | 5 |
| Sales coaching standards | 6 |
| Support and customer experience | 5 |

Total: 50 KPI definitions.

## Seed Behavior

- `kpi_code` maps to `kpi_key` for compatibility.
- Existing definitions are updated when source data changes.
- Unchanged definitions are skipped.
- Running the seed twice must not create duplicate KPI definitions.

## Next Phase

SEED-5 is the business coaching standards checkpoint if needed. KPI assignments, entries, and scoring should remain runtime workflows.
