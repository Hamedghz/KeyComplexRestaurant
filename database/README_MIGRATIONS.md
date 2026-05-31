# Database migrations

Run migrations in phpMyAdmin or with the MySQL CLI after importing `database/schema.sql` and `database/survey_schema.sql`.

```sql
SOURCE database/migrations/2026_05_31_admin_crm_prediction_content.sql;
```

The migration adds the admin CRM, hero banner, match, prediction and extended menu fields. Existing datetime values remain standard Gregorian `date/datetime` values in MySQL; PHP helpers render and parse Jalali dates for users and admins.
