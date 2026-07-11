<?php

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$runner = file_get_contents($root . '/admin/lib/system_seed_runner.php') ?: '';
$update = file_get_contents($root . '/admin/system-update.php') ?: '';
$migration = file_get_contents($root . '/database/migrations/2026_07_20_setup_seed_registry.sql') ?: '';
$schema = file_get_contents($root . '/database/schema.sql') ?: '';

foreach (['seed_registry', 'setup_run_logs'] as $table) {
    $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`'), 'Missing additive migration table: ' . $table);
    $assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS `' . $table . '`'), 'Missing fresh-install table: ' . $table);
}
foreach (['key_business_coaching_standards','key_restaurant_duties_checklists','key_restaurant_kpis','key_restaurant_organizational_tests','key_planner_defaults','key_okr_tmo_samples','key_hr_permissions','key_hr_navigation','hr_performance_suite'] as $key) {
    $assert(str_contains($runner, "'" . $key . "'"), 'Seed is not registered: ' . $key);
}
foreach (['ensureSeedRegistrySchema','listRegisteredSeeds','registerSeed','runSeed','runPendingSeeds','calculateSeedChecksum','updateSeedStatus'] as $function) {
    $assert(str_contains($runner, 'function ' . $function . '('), 'Missing seed runner function: ' . $function);
}
$assert(!str_contains($runner, 'seed_restaurant_hr_tests.php'), 'Legacy test seed must not be registered.');
$assert(!str_contains($update, "adminTableHasRows(\$seedMatch[1])"), 'Unsafe table-has-rows migration skip remains.');
$assert(str_contains($update, "adminGuard('super_admin')"), 'System update must remain super-admin guarded.');
$assert(str_contains($update, 'requireValidCsrf()'), 'System update POST must require CSRF.');
$assert(str_contains($update, 'reset_hr_test_seed_only'), 'Protected test reset mode is not prepared.');
$assert(!preg_match('/\b(?:DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration), 'Setup migration contains destructive SQL.');

foreach (['seed_key_restaurant_kpis.php','seed_key_restaurant_organizational_tests.php','seed_key_planner_defaults.php','seed_key_okr_tmo_samples.php','seed_key_hr_permissions.php','seed_key_hr_navigation.php'] as $file) {
    $assert(is_file($root . '/database/seeds/' . $file), 'Missing seed file: ' . $file);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "setup seed system contract: ok\n";

