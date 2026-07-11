<?php
$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/2026_07_08_hr_performance_suite.sql');
if ($migration === false) {
    throw new RuntimeException('Missing HR performance suite migration.');
}
$coreMigration = file_get_contents($root . '/database/migrations/2026_07_09_hr_core_foundation.sql');
if ($coreMigration === false) {
    throw new RuntimeException('Missing HR core foundation migration.');
}
$combinedSql = $migration . "\n" . $coreMigration;
if (preg_match('/\b(DROP|TRUNCATE)\b/i', $combinedSql)) {
    throw new RuntimeException('Migration must not contain destructive SQL.');
}
foreach (['hr_role_duties','hr_checklist_templates','hr_checklist_items','hr_kpi_definitions','hr_planner_tasks','hr_monthly_objectives','hr_key_results','hr_okr_actions','hr_tmo_reviews'] as $table) {
    if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('Missing table in migration: ' . $table);
    }
}
foreach (['hr_roles','hr_periods','hr_dynamic_fields','hr_dynamic_field_values','hr_audit_logs','hr_module_settings','business_standards','business_standard_items'] as $table) {
    if (strpos($coreMigration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('Missing core table in migration: ' . $table);
    }
}
if (strpos($combinedSql, 'hr_business_standards') !== false) {
    throw new RuntimeException('Deprecated hr_business_standards table must not be created.');
}
$pages = [
    'hr-tests-bank.php','hr-test-questions.php','hr-test-assignments.php','hr-my-tests.php','hr-test-results.php','hr-test-personnel-report.php',
    'hr-role-duties.php','hr-checklist-templates.php','hr-checklist-assignments.php','hr-checklist-submissions.php','hr-checklist-approvals.php','hr-checklist-progress.php',
    'hr-kpi-definitions.php','hr-kpi-assignments.php','hr-kpi-entries.php','hr-kpi-scores.php','hr-kpi-reports.php',
    'hr-planner-mine.php','hr-planner-today.php','hr-planner-tomorrow.php','hr-planner-overdue.php','hr-planner-referred.php','hr-planner-reports.php',
    'hr-okr-objectives.php','hr-okr-key-results.php','hr-okr-actions.php','hr-okr-task-links.php','hr-okr-progress.php','hr-tmo-reviews.php',
];
foreach ($pages as $file) {
    $path = $root . '/admin/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException('Missing HR suite route wrapper: ' . $file);
    }
}
$dashboard = file_get_contents($root . '/admin/hr-dashboard.php');
if ($dashboard === false || strpos($dashboard, 'adminGuard') === false) {
    throw new RuntimeException('HR dashboard must exist and use adminGuard.');
}
$users = file_get_contents($root . '/admin/users.php');
foreach ([
    'hr_platform_access','hr_platform_manage','business_standards_manage','business_standards_view',
    'hr_tests_manage','hr_tests_assign','hr_tests_take','hr_tests_view_results','hr_tests_reports','hr_tests_retake_approve',
    'hr_duties_manage','hr_checklists_manage','hr_checklists_submit','hr_checklists_approve_manager','hr_checklists_approve_inspector','hr_checklists_report',
    'hr_kpi_manage','hr_kpi_assign','hr_kpi_entry','hr_kpi_report',
    'planner_access','planner_manage_own','planner_assign','planner_view_team','planner_report',
    'okr_access','okr_manage','okr_review','tmo_access','tmo_manage','tmo_report',
] as $permission) {
    if ($users === false || strpos($users, "'" . $permission . "'") === false) {
        throw new RuntimeException('Missing permission key: ' . $permission);
    }
}
$navigationSql = file_get_contents($root . '/database/migrations/2026_07_09_hr_core_foundation.sql') ?: '';
if (strpos($navigationSql, 'business_standards') !== false && strpos($navigationSql, "'business_standards") !== false) {
    throw new RuntimeException('Business standards must not be a visible menu item.');
}
echo "hr_performance_suite_contract_test: OK\n";
