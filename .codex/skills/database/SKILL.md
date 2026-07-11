---
name: database
description: Analyze schema, migrations, seeds, SQL queries, indexes, and data integrity.
---

# Database Skill

## Rules

- Never drop or overwrite data without explicit approval.
- Prefer idempotent migrations.
- Use safe defaults.
- Add nullable columns first when possible.
- Keep seed data separate from schema.
- Keep seeds repeatable.
- Avoid raw dynamic SQL.
- Add indexes only with reason.
- Preserve legacy columns when needed for compatibility.

## Checklist

- Table existence
- Column existence
- Column type compatibility
- Nullability
- Default values
- Indexes
- Foreign keys if existing project uses them
- Duplicate records
- Migration safety
- Seed idempotency
- Query performance
- Large result pagination
- Dynamic sort/filter allowlists

## Schema Repair Guidance

For existing production tables:

- Do not rely only on CREATE TABLE IF NOT EXISTS.
- Check each required column.
- Add missing columns safely.
- Check each required index.
- Add missing indexes safely.
- Never recreate existing tables.
- Never destroy old data.
