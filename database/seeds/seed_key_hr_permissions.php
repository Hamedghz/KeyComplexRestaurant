<?php

require_once dirname(__DIR__, 2) . '/admin/lib/hr/bootstrap.php';

function seedKeyHrPermissions(PDO $db, int $actorId = 0): array {
    hrEnsureCoreSchema($db);
    $permissions = [
        'hr_platform_access','hr_platform_manage','business_standards_view','business_standards_manage',
        'hr_tests_manage','hr_tests_assign','hr_tests_take','hr_tests_view_results','hr_tests_reports','hr_tests_retake_approve',
        'hr_duties_manage','hr_checklists_manage','hr_checklists_submit','hr_checklists_approve_manager','hr_checklists_approve_inspector','hr_checklists_report',
        'hr_kpi_manage','hr_kpi_assign','hr_kpi_entry','hr_kpi_report',
        'planner_access','planner_manage_own','planner_assign','planner_view_team','planner_report',
        'okr_access','okr_manage','okr_review','tmo_access','tmo_manage','tmo_report',
    ];
    $stmt = $db->prepare('INSERT INTO hr_module_settings (module_key,setting_key,setting_value_json,updated_by,updated_at) VALUES ("hr_platform","permission_catalog",?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value_json=VALUES(setting_value_json),updated_by=VALUES(updated_by),updated_at=NOW()');
    $stmt->execute([json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $actorId ?: null]);
    return ['inserted' => $stmt->rowCount() === 1 ? 1 : 0, 'updated' => $stmt->rowCount() > 1 ? 1 : 0, 'skipped' => 0, 'messages' => ['Permission catalog stored as HR platform settings without changing user/admin records.']];
}
