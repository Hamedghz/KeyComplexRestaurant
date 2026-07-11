<?php

$root = dirname(__DIR__);

foreach ([
    '/admin/hr-performance-summary.php',
    '/admin/lib/hr/performance_summary.php',
    '/database/migrations/2026_07_19_hr_final_integration_cleanup.sql',
    '/docs/hr-imports/final-performance-summary.md',
] as $file) {
    if (!is_file($root . $file)) {
        throw new RuntimeException('Missing Phase 9 file: ' . $file);
    }
}

$summary = file_get_contents($root . '/admin/lib/hr/performance_summary.php') ?: '';
foreach ([
    'hr_module_settings',
    'performance_summary',
    'score_weights',
    "'kpi' => 40",
    "'checklist' => 25",
    "'planner' => 20",
    "'tests' => 15",
    'hr_test_results',
    'hr_checklist_submissions',
    'hr_kpi_scores',
    'planner_tasks',
    'okr_actions',
    'sales_script',
    'customer_journey',
    'export',
    'hr_performance_summary_view',
    'hr_performance_summary_manage',
] as $needle) {
    if (strpos($summary, $needle) === false) {
        throw new RuntimeException('Performance summary missing token: ' . $needle);
    }
}

$route = file_get_contents($root . '/admin/hr-performance-summary.php') ?: '';
if (strpos($route, 'hrPerformanceSummaryRenderPage') === false) {
    throw new RuntimeException('Summary route does not render performance summary.');
}

foreach ([
    '/admin/evaluation-builder.php' => 'hr-tests.php',
    '/admin/employee-evaluation-settings.php' => 'hr-tests.php',
    '/admin/employee-assessments.php' => 'hr-test-results.php',
    '/admin/employee-performance.php' => 'hr-performance-summary.php',
] as $file => $target) {
    $content = file_get_contents($root . $file) ?: '';
    if (strpos($content, "redirectTo('$target')") === false) {
        throw new RuntimeException('Legacy route is not a safe redirect: ' . $file);
    }
}

$migration = file_get_contents($root . '/database/migrations/2026_07_19_hr_final_integration_cleanup.sql') ?: '';
foreach (['hr_performance_summary', 'hr-performance-summary.php', 'evaluation_builder', 'employee_evaluations', 'employee_performance', 'employee_assessments', 'hr_test_report'] as $needle) {
    if (strpos($migration, $needle) === false) {
        throw new RuntimeException('Cleanup migration missing token: ' . $needle);
    }
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Phase 9 migration must not contain destructive SQL.');
}

$users = file_get_contents($root . '/admin/users.php') ?: '';
foreach (['hr_performance_summary_view', 'hr_performance_summary_manage'] as $permission) {
    if (strpos($users, $permission) === false) {
        throw new RuntimeException('Permission missing: ' . $permission);
    }
}

$businessSeed = file_get_contents($root . '/database/seeds/seed_key_business_coaching_standards.php') ?: '';
foreach (['fab', 'listening', 'objections'] as $standard) {
    if (stripos($businessSeed, $standard) === false) {
        throw new RuntimeException('Business coaching seed missing standard: ' . $standard);
    }
}

$kpiSeed = file_get_contents($root . '/database/seeds/seed_key_kpis.php') ?: '';
foreach (['script_compliance_score', 'listening_score', 'fab_usage_score', 'objection_handling_score'] as $metric) {
    if (strpos($kpiSeed, $metric) === false) {
        throw new RuntimeException('KPI seed missing business coaching metric: ' . $metric);
    }
}

echo "hr_final_integration_contract_test: OK\n";
