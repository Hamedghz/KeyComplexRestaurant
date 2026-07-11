<?php

require_once dirname(__DIR__, 2) . '/admin/lib/admin_schema.php';

function seedKeyHrNavigation(PDO $db, int $actorId = 0): array {
    $items = [
        ['hr_dashboard','hr-dashboard.php','📌','employee',5,'["hr-dashboard.php"]'],
        ['hr_tests_bank','hr-tests.php','🧠','admin',10,'["hr-tests.php","hr-test-questions.php","hr-test-assignments.php","employee-tests.php","hr-test-results.php","hr-test-report.php"]'],
        ['hr_role_duties','hr-duties.php','✅','manager',20,'["hr-duties.php","hr-checklist-templates.php","hr-checklist-assignments.php","hr-checklist-submissions.php","hr-checklist-approvals.php","hr-checklist-report.php"]'],
        ['hr_kpi_definitions','hr-kpi-definitions.php','📈','manager',30,'["hr-kpi-definitions.php","hr-kpi-assignments.php","hr-kpi-entries.php","hr-kpi-scores.php","hr-kpi-report.php"]'],
        ['hr_planner_mine','planner.php','📅','employee',40,'["planner.php","planner-today.php","planner-assigned.php","planner-report.php"]'],
        ['hr_okr_objectives','okr-objectives.php','🎯','manager',50,'["okr-objectives.php","okr-key-results.php","okr-actions.php","okr-progress.php","tmo-review.php","tmo-dashboard.php"]'],
        ['hr_performance_summary','hr-performance-summary.php','📊','manager',60,'["hr-performance-summary.php","employee-performance.php"]'],
    ];
    $stmt = $db->prepare('INSERT INTO admin_navigation_items (group_key,group_order,item_key,url,icon,min_role,sort_order,active_pages,is_active) VALUES ("hr_performance_goals",65,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE group_order=65,url=VALUES(url),icon=VALUES(icon),min_role=VALUES(min_role),sort_order=VALUES(sort_order),active_pages=VALUES(active_pages),is_active=1');
    $affected = 0;
    foreach ($items as $item) {
        $stmt->execute($item);
        $affected += $stmt->rowCount();
    }
    $old = ['evaluation_builder','employee_evaluations','employee_performance','employee_assessments','hr_test_report'];
    $placeholders = implode(',', array_fill(0, count($old), '?'));
    $hide = $db->prepare('UPDATE admin_navigation_items SET is_active=0 WHERE item_key IN (' . $placeholders . ')');
    $hide->execute($old);
    return ['inserted' => 0, 'updated' => $affected + $hide->rowCount(), 'skipped' => 0, 'messages' => ['Approved HR navigation is upserted; old duplicate HR menu rows are hidden when present.']];
}
