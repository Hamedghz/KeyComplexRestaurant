# Database schema and migrations

`database/schema.sql` is the canonical production schema and the only schema file the installer imports.

Active migrations are limited to `database/migrations/`. The current final migration is:

```sql
database/migrations/2026_06_05_final_schema.sql
```

The application migration flow records migration execution in `system_versions` and then reconciles existing installations against `database/schema.sql` with safe PHP existence checks before adding missing columns or indexes. Do not add MySQL-8-only IF-NOT-EXISTS ALTER or INDEX statements.
