<?php

$root = dirname(__DIR__);

foreach ([
    '/admin/lib/hr/kpis.php',
    '/admin/lib/hr/kpi_pages.php',
    '/admin/hr-kpi-definitions.php',
    '/admin/hr-kpi-assignments.php',
    '/admin/hr-kpi-entries.php',
    '/admin/hr-kpi-scores.php',
    '/admin/hr-kpi-report.php',
    '/admin/hr-kpi-reports.php',
    '/database/migrations/2026_07_16_hr_kpi_evaluation.sql',
    '/docs/hr-imports/kpi-evaluation-workflow.md',
] as $file) {
    if (!is_file($root . $file)) {
        throw new RuntimeException('Missing Phase 6 file: ' . $file);
    }
}

$library = file_get_contents($root . '/admin/lib/hr/kpis.php') ?: '';
foreach ([
    'hrKpiScorePercent',
    'hrKpiRagStatus',
    'hr_kpi_scores',
    'hr_kpi_corrective_actions',
    'plannerCreateKpiCorrectiveTask',
    '$scorePercent >= $green',
] as $needle) {
    if (strpos($library, $needle) === false) {
        throw new RuntimeException('KPI library missing required token: ' . $needle);
    }
}

if (preg_match('/INSERT\s+INTO\s+`?(admins|users|employee_score_history)/i', $library)) {
    throw new RuntimeException('KPI workflow must not create users/admins or write final employee summaries directly.');
}

$migration = file_get_contents($root . '/database/migrations/2026_07_16_hr_kpi_evaluation.sql') ?: '';
foreach (['hr_kpi_definitions', 'hr_kpi_assignments', 'hr_kpi_entries', 'hr_kpi_scores', 'hr_kpi_corrective_actions'] as $table) {
    if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('KPI migration missing table: ' . $table);
    }
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('KPI migration must not contain destructive SQL.');
}

$pages = file_get_contents($root . '/admin/lib/hr/kpi_pages.php') ?: '';
foreach (['hrKpiRenderDefinitionsPage', 'hrKpiRenderAssignmentsPage', 'hrKpiRenderEntriesPage', 'hrKpiRenderScoresPage', 'hrKpiRenderReportPage', "'export' => 'csv'", 'hrKpiReportFilterSql', 'employee_id', 'period_id'] as $needle) {
    if (strpos($pages, $needle) === false) {
        throw new RuntimeException('KPI pages missing required token: ' . $needle);
    }
}

echo "hr_kpi_evaluation_workflow_contract_test: OK\n";
