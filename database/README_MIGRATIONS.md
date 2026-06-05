# Database schema and migrations

`database/schema.sql` is the canonical production schema and the only SQL schema file the installer imports for fresh installs.

Active incremental migrations are limited to `database/migrations/`. The current runtime/admin repair migration is:

```sql
database/migrations/2026_06_05_runtime_analytics.sql
```

The application migration flow records migration execution in `schema_migrations`. Admin bootstrap also reconciles existing installations against `database/schema.sql` with safe PHP existence checks before adding missing columns or indexes. Do not add MySQL-8-only `ALTER ... IF NOT EXISTS` or `CREATE INDEX IF NOT EXISTS` statements.
