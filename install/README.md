# KEY Restaurant installation and updates

## Fresh installation

1. Import `database/schema.sql` using the hosting database tool.
2. Configure the application normally and sign in as `super_admin`.
3. Open `admin/system-update.php`.
4. Run pending migrations, register default seeds, then run pending seeds.

There is intentionally no public installer. Database credentials must never be exposed through an unauthenticated setup route.

## Existing installation

Use the authenticated System Update page to inspect and run pending migrations and PHP seeds. Migration and seed executions are tracked in `schema_migrations`, `seed_registry`, and `setup_run_logs`.

Seed files are rerunnable and use stable keys with upsert behavior. A changed seed checksum returns that seed to pending status.

## Safety

- HR setup does not use Replace Database.
- Replace Database is an existing emergency restore function and is unrelated to normal setup.
- HR test reset actions remain disabled until FINAL-3 adds archive-before-delete behavior.
- The legacy `seed_restaurant_hr_tests.php` is not registered by the setup seed runner.

