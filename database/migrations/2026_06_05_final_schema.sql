-- KEY Restaurant & Coffeehouse final schema marker.
-- The canonical schema now lives in database/schema.sql.
-- MigrationRunner records this version once, and SchemaSynchronizer reconciles
-- existing installations from database/schema.sql using safe existence checks.
SELECT '2026_06_05_final_schema' AS version_name;
