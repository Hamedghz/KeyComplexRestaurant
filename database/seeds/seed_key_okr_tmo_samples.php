<?php

require_once dirname(__DIR__, 2) . '/admin/lib/hr/bootstrap.php';

function seedKeyOkrTmoSamples(PDO $db, int $actorId = 0): array {
    hrEnsureCoreSchema($db);
    $templates = [
        'objective' => 'بهبود نرخ تبدیل فروش ماهانه',
        'key_results' => ['افزایش تعداد لید', 'بهبود نرخ تبدیل', 'کاهش پیگیری‌های ازدست‌رفته'],
        'action' => 'بازبینی هفتگی اسکریپت و پیگیری‌ها',
        'tmo_review' => ['result_summary', 'blockers', 'decisions', 'next_actions', 'final_score'],
        'tmo_person_model' => 'TMO is selected from existing users/admins as tmo_user_id; this seed creates templates only.',
        'note' => 'Template only; no user or TMO is assigned automatically.',
    ];
    $stmt = $db->prepare('INSERT INTO hr_module_settings (module_key,setting_key,setting_value_json,updated_by,updated_at) VALUES ("okr_tmo","sample_templates",?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value_json=VALUES(setting_value_json),updated_by=VALUES(updated_by),updated_at=NOW()');
    $stmt->execute([json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $actorId ?: null]);
    return ['inserted' => $stmt->rowCount() === 1 ? 1 : 0, 'updated' => $stmt->rowCount() > 1 ? 1 : 0, 'skipped' => 0, 'messages' => ['OKR/TMO sample seed stores templates only and does not assign a real TMO user.']];
}
