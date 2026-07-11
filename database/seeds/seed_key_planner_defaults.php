<?php

require_once dirname(__DIR__, 2) . '/admin/lib/hr/bootstrap.php';

function seedKeyPlannerDefaults(PDO $db, int $actorId = 0): array {
    hrEnsureCoreSchema($db);
    $settings = [
        'priorities' => ['low', 'normal', 'high', 'urgent'],
        'statuses' => ['pending', 'in_progress', 'done', 'cancelled', 'postponed', 'overdue'],
        'source_modules' => ['manual', 'checklist', 'kpi', 'okr', 'tmo', 'customer_followup', 'complaint_followup', 'referral_followup', 'after_sales', 'referral', 'sop'],
    ];
    $stmt = $db->prepare('INSERT INTO hr_module_settings (module_key,setting_key,setting_value_json,updated_by,updated_at) VALUES ("planner","defaults",?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value_json=VALUES(setting_value_json),updated_by=VALUES(updated_by),updated_at=NOW()');
    $stmt->execute([json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $actorId ?: null]);
    return ['inserted' => $stmt->rowCount() === 1 ? 1 : 0, 'updated' => $stmt->rowCount() > 1 ? 1 : 0, 'skipped' => 0, 'messages' => ['Planner defaults seed is idempotent and stores settings only.']];
}
