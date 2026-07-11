<?php

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$serviceFile = $root . '/admin/lib/hr/tests/test_seed_reset_service.php';
$runnerFile = $root . '/admin/lib/system_seed_runner.php';
$updateFile = $root . '/admin/system-update.php';
$legacySeedFile = $root . '/database/seeds/seed_restaurant_hr_tests.php';

$assert(is_file($serviceFile), 'Reset service file is missing.');
$service = is_file($serviceFile) ? (file_get_contents($serviceFile) ?: '') : '';
$runner = is_file($runnerFile) ? (file_get_contents($runnerFile) ?: '') : '';
$update = is_file($updateFile) ? (file_get_contents($updateFile) ?: '') : '';
$legacySeed = is_file($legacySeedFile) ? (file_get_contents($legacySeedFile) ?: '') : '';

foreach ([
    'hrTestSeedResetAllowedTables',
    'hrDetectExistingTestTables',
    'hrArchiveTestTable',
    'hrArchiveOldTestData',
    'hrDeleteOldTestData',
    'hrDisableLegacyTestSeed',
    'hrRunNewRestaurantTestSeed',
    'hrResetRestaurantOrganizationalTests',
    'hrVerifyNewTestSeed',
    'hrVerifyOldQuestionsRemoved',
] as $function) {
    $assert(str_contains($service, 'function ' . $function . '('), 'Missing reset service function: ' . $function);
}

foreach ([
    'hr_assessment_tests',
    'hr_test_dimensions',
    'hr_test_questions',
    'hr_test_options',
    'hr_test_scoring_rules',
    'hr_test_assignments',
    'hr_test_attempts',
    'hr_test_responses',
    'hr_test_results',
    'hr_test_retake_requests',
    'hr_test_audit_logs',
] as $table) {
    $assert(str_contains($service, "'" . $table . "'"), 'Allowed test table missing from whitelist: ' . $table);
}

$whitelistBlock = '';
if (preg_match('/function\s+hrTestSeedResetAllowedTables\s*\(\)\s*:\s*array\s*\{(.*?)\n\}/s', $service, $match)) {
    $whitelistBlock = $match[1];
}
$assert($whitelistBlock !== '', 'Allowed table whitelist block could not be inspected.');
foreach (['admins', 'users', 'orders', 'menu_items', 'analytics', 'settings', 'media', 'banners', 'matches', 'predictions', 'crm', 'customers'] as $forbidden) {
    $assert(!preg_match('/[\'"]' . preg_quote($forbidden, '/') . '[\'"]/', $whitelistBlock), 'Forbidden table appears in reset whitelist: ' . $forbidden);
}

$assert(str_contains($service, "RESET_HR_TESTS"), 'Reset service must require RESET_HR_TESTS confirmation.');
$assert(str_contains($service, 'CREATE TABLE ') && str_contains($service, ' AS SELECT * FROM '), 'Reset service must archive before deleting old test rows.');
$assert(str_contains($service, 'DELETE FROM '), 'Reset service must use dependency-ordered DELETE for HR/test rows.');
$assert(!preg_match('/\bTRUNCATE\b/i', $service), 'Reset service must not use TRUNCATE.');
$assert(!preg_match('/\bDROP\b/i', $service), 'Reset service must not use DROP.');

$assert(!str_contains($runner, 'seed_restaurant_hr_tests.php'), 'Legacy test seed must not be registered in the default seed runner.');
$assert(str_contains($runner, "'key_restaurant_organizational_tests'"), 'New restaurant organizational test seed must be registered.');
$assert(str_contains($update, "reset_hr_test_seed_only"), 'System update must expose reset_hr_test_seed_only action.');
$assert(str_contains($update, 'requireValidCsrf()'), 'System update reset action must be CSRF protected through POST guard.');
$assert(str_contains($update, "adminGuard('super_admin')"), 'System update must remain super_admin protected.');
$assert(str_contains($update, 'hrResetRestaurantOrganizationalTests'), 'System update must call the reset service for test reset.');
$assert(str_contains($update, 'reset_confirmation'), 'System update must collect typed reset confirmation.');
$assert(str_contains($update, 'reset_all_hr_domain_seed_only') && str_contains($update, 'هنوز غیرفعال است'), 'Reset-all HR seed action must remain disabled.');

$assert(str_contains($legacySeed, 'LEGACY_HR_TESTS_SEED_DISABLED'), 'Legacy test seed must be explicitly disabled.');
$assert(str_contains($legacySeed, 'legacy_disabled'), 'Legacy test seed must return a disabled result.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "hr_test_seed_reset_contract_test: OK\n";
