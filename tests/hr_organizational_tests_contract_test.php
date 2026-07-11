<?php

$root = dirname(__DIR__);

foreach ([
    '/admin/lib/hr/tests.php',
    '/admin/lib/hr/test_pages.php',
    '/admin/hr-tests.php',
    '/admin/hr-test-questions.php',
    '/admin/hr-test-assignments.php',
    '/admin/employee-tests.php',
    '/admin/hr-test-results.php',
    '/admin/hr-test-report.php',
    '/database/migrations/2026_07_17_hr_organizational_tests.sql',
    '/database/seeds/seed_key_organizational_tests.php',
    '/docs/hr-imports/organizational-tests.md',
] as $file) {
    if (!is_file($root . $file)) {
        throw new RuntimeException('Missing Phase 7 file: ' . $file);
    }
}

$migration = file_get_contents($root . '/database/migrations/2026_07_17_hr_organizational_tests.sql') ?: '';
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
    if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('Phase 7 migration missing table: ' . $table);
    }
}
if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `hr_tests`') !== false) {
    throw new RuntimeException('Phase 7 must not create a confusing parallel hr_tests table.');
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Phase 7 migration must not contain destructive SQL.');
}

$service = file_get_contents($root . '/admin/lib/hr/tests.php') ?: '';
$seed = file_get_contents($root . '/database/seeds/seed_key_organizational_tests.php') ?: '';
foreach (['hrOrgTestsEnsureSchema', 'seedKeyOrganizationalTests', 'hrOrgTestsRequestRetake', 'hrOrgTestsReviewRetake', 'retake_policy', 'manager_approval_required'] as $needle) {
    if (strpos($service . $seed, $needle) === false) {
        throw new RuntimeException('Phase 7 service missing token: ' . $needle);
    }
}

foreach (['KEY_ORG_BEHAVIOR', 'KEY_RESTAURANT_OPERATIONS', 'KEY_SALES_CUSTOMER_INTERACTION', 'KEY_MARKETING_CONTENT', 'KEY_KPI_REPORTING_LITERACY', 'hr_test_options', 'ON DUPLICATE KEY UPDATE'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('Phase 7 seed missing token: ' . $needle);
    }
}
if (preg_match('/\b(clinical|diagnostic|diagnosis|medical)\b/i', $seed)) {
    throw new RuntimeException('Phase 7 seed must not use clinical or medical framing.');
}

$employeePage = file_get_contents($root . '/admin/employee-tests.php') ?: '';
foreach (['hrOrgTestsRequestRetake', 'request_retake', 'hrAssessmentDisclaimer'] as $needle) {
    if (strpos($employeePage, $needle) === false) {
        throw new RuntimeException('Employee tests page missing token: ' . $needle);
    }
}

$report = file_get_contents($root . '/admin/hr-test-report.php') ?: '';
foreach (["'format'=>'csv'", 'window.print()', 'hrOrgTestsEnsureSchema', 'hr_tests_reports'] as $needle) {
    if (strpos($report, $needle) === false) {
        throw new RuntimeException('HR test report missing token: ' . $needle);
    }
}

foreach ([
    '/admin/evaluation-builder.php' => 'hr-tests.php',
    '/admin/employee-evaluation-settings.php' => 'hr-tests.php',
    '/admin/employee-assessments.php' => 'hr-test-results.php',
] as $file => $target) {
    $content = file_get_contents($root . $file) ?: '';
    if (strpos($content, "redirectTo('$target')") === false) {
        throw new RuntimeException('Legacy route is not a thin redirect: ' . $file);
    }
}

echo "hr_organizational_tests_contract_test: OK\n";
