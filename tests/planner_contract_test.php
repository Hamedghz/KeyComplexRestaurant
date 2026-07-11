<?php

$root = dirname(__DIR__);

$migration = file_get_contents($root . '/database/migrations/2026_07_10_planner_tasks.sql');
if ($migration === false) {
    throw new RuntimeException('Planner migration is missing.');
}

foreach (['planner_tasks','planner_task_logs','planner_task_comments'] as $table) {
    if (strpos($migration, "CREATE TABLE IF NOT EXISTS `{$table}`") === false) {
        throw new RuntimeException("Planner migration must create {$table}.");
    }
}

foreach ([
    'owner_user_id','assigned_by','task_date','due_at','period_id','shift_code','priority','status',
    'progress_percent','source_module','source_entity_type','source_entity_id','linked_objective_id',
    'linked_kr_id','linked_action_id','linked_kpi_score_id','linked_checklist_item_id',
    'linked_customer_id','linked_followup_id','is_recurring','recurrence_rule','completed_at'
] as $column) {
    if (strpos($migration, "`{$column}`") === false) {
        throw new RuntimeException("Planner migration is missing {$column}.");
    }
}

if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Planner migration must not contain destructive SQL.');
}

foreach ([
    '/admin/lib/hr/planner/planner_repository.php',
    '/admin/lib/hr/planner/planner_service.php',
    '/admin/lib/hr/planner/planner_adapter.php',
    '/admin/lib/hr/planner/planner_report_service.php',
    '/admin/planner.php',
    '/admin/planner-today.php',
    '/admin/planner-assigned.php',
    '/admin/planner-report.php',
    '/admin/includes/planner-widget.php',
] as $path) {
    if (!is_file($root . $path)) {
        throw new RuntimeException("Required planner file is missing: {$path}");
    }
}

$adapter = file_get_contents($root . '/admin/lib/hr/planner/planner_adapter.php') ?: '';
foreach (['plannerCreateLinkedTask','plannerCreateChecklistTask','plannerCreateKpiCorrectiveTask','plannerCreateOkrActionTask','plannerCreateBusinessCoachingTask'] as $function) {
    if (strpos($adapter, 'function ' . $function) === false) {
        throw new RuntimeException("Planner adapter is missing {$function}.");
    }
}

$service = file_get_contents($root . '/admin/lib/hr/planner/planner_service.php') ?: '';
foreach (['plannerQuickAdd','plannerMarkDone','plannerTransferToTomorrow','date(\'Y-m-d\')'] as $needle) {
    if (strpos($service, $needle) === false) {
        throw new RuntimeException("Planner service contract missing {$needle}.");
    }
}

$dashboard = file_get_contents($root . '/admin/dashboard.php') ?: '';
if (strpos($dashboard, "includes/planner-widget.php") === false) {
    throw new RuntimeException('Admin dashboard must include the planner widget.');
}
$employeeDashboard = file_get_contents($root . '/admin/employee-dashboard.php') ?: '';
if (strpos($employeeDashboard, "includes/planner-widget.php") === false) {
    throw new RuntimeException('Employee dashboard must include the planner widget.');
}

$navigation = (file_get_contents($root . '/database/migrations/2026_07_10_planner_tasks.sql') ?: '')
    . (file_get_contents($root . '/database/migrations/2026_06_18_admin_navigation.sql') ?: '');
if (substr_count($navigation, "'hr_planner_mine'") < 2) {
    throw new RuntimeException('Planner menu row must be repaired in migration and baseline navigation.');
}
if (preg_match("/'business_standards[^']*'/", $navigation)) {
    throw new RuntimeException('Business standards must not become a visible navigation item.');
}

echo "planner_contract_test: OK\n";
