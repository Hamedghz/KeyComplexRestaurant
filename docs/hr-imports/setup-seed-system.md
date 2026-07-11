# Setup, migration, and seed system

## What changed

The authenticated System Update page now tracks migration checksums/status and centrally registers PHP seed files. It can run pending seeds, rerun a selected seed, rerun the HR seed catalog, and repair the additive HR foundation schema.

## Registered seeds

- Business coaching standards
- Restaurant duties and checklists
- Restaurant KPI definitions
- Restaurant organizational tests
- Planner defaults
- OKR/TMO templates
- HR permission catalog metadata
- Approved HR navigation
- Existing HR performance suite compatibility seed

The organizational test adapter includes only `seed_key_organizational_tests.php`. The old professional test seed remains available to legacy code but is not part of setup. FINAL-3 must archive/reset legacy test data before removing its remaining legacy execution path.

## Migration behavior

Migrations are no longer skipped because a destination table already contains rows. Statements execute normally. A duplicate-key conflict may be logged as skipped only for a plain single-row insert; multi-row or otherwise unsafe failures keep the migration in failed status.

## Operations

All setup actions require `super_admin` and CSRF validation. Errors are logged while the UI receives a clean Persian message. Reset actions are visibly disabled until archive-before-delete support exists.

## Rollback

No destructive rollback is provided. To disable the new execution surface, remove the new buttons/runner include while leaving registry tables intact. Existing application data remains unchanged.

## Known limitation

This phase adds infrastructure only. It does not execute migrations/seeds and does not archive or reset old test questions.

