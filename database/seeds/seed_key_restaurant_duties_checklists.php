<?php

require_once dirname(__DIR__, 2) . '/admin/lib/admin_schema.php';
require_once dirname(__DIR__, 2) . '/admin/lib/hr/checklists.php';
require_once __DIR__ . '/seed_key_duties.php';
require_once __DIR__ . '/seed_key_checklists.php';

function seedKeyRestaurantDutiesChecklists(PDO $db, int $actorId = 0): array {
    hrChecklistEnsureSchema($db);

    $db->exec("CREATE TABLE IF NOT EXISTS `hr_checklist_categories` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `category_key` varchar(120) NOT NULL,
        `title` varchar(190) NOT NULL,
        `status` varchar(30) NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_hr_checklist_category_key` (`category_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $duties = seedKeyDuties($db, $actorId);
    $checklists = seedKeyChecklists($db, $actorId);

    $categories = [
        ['hygiene', 'بهداشت'],
        ['cleanliness', 'نظافت'],
        ['branch_readiness', 'آمادگی شعبه'],
        ['customer_service', 'خدمات مشتری'],
        ['complaint_handling', 'مدیریت شکایت'],
        ['order_and_discipline', 'نظم و دیسیپلین'],
        ['sop_execution', 'اجرای SOP'],
        ['5s_audit', 'ممیزی 5S'],
        ['cashier_daily_control', 'کنترل روزانه صندوق'],
        ['hall_captain_daily_control', 'کنترل روزانه مسئول سالن'],
        ['delivery_rider_operational_control', 'کنترل عملیاتی پیک'],
        ['internal_manager_daily_kpi_checklist', 'چک‌لیست KPI روزانه مدیر داخلی'],
    ];
    $categoryStmt = $db->prepare('INSERT INTO hr_checklist_categories (category_key,title,status) VALUES (?,?,"active") ON DUPLICATE KEY UPDATE title=VALUES(title),status="active",updated_at=CURRENT_TIMESTAMP');
    foreach ($categories as $category) {
        $categoryStmt->execute($category);
    }

    $templateCode = 'business_coaching_sales_behavior_checklist';
    $items = [
        ['bc_starts_with_question', 'مکالمه را با سؤال شروع می‌کند', 'during_shift', 1, 1, 5, 1, 0],
        ['bc_listens_without_interruption', 'بدون قطع کردن گوش می‌دهد', 'during_shift', 1, 1, 5, 1, 0],
        ['bc_writes_customer_notes', 'یادداشت مشتری را ثبت می‌کند', 'during_shift', 1, 0, null, 1, 0],
        ['bc_handles_objection_calmly', 'اعتراض را بدون واکنش دفاعی مدیریت می‌کند', 'during_shift', 1, 1, 5, 1, 0],
        ['bc_uses_fab_language', 'از زبان FAB استفاده می‌کند', 'during_shift', 1, 1, 5, 1, 0],
        ['bc_ends_with_cta', 'مکالمه را با CTA واضح تمام می‌کند', 'during_shift', 1, 1, 5, 1, 0],
        ['bc_registers_followup', 'در صورت نیاز شکایت یا پیگیری را ثبت می‌کند', 'as_needed', 0, 0, null, 1, 1],
        ['bc_creates_corrective_task', 'در صورت وجود ایراد، تسک اصلاحی ایجاد می‌کند', 'as_needed', 0, 0, null, 1, 1],
    ];
    $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $templateStmt = $db->prepare('INSERT INTO hr_checklist_templates (template_key,template_code,code,title,role_code,role_key,department,period_type,description,standard_group,requires_manager_approval,requires_inspector_approval,items_json,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"active",?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),role_code=VALUES(role_code),role_key=VALUES(role_key),department=VALUES(department),period_type=VALUES(period_type),description=VALUES(description),standard_group=VALUES(standard_group),requires_manager_approval=VALUES(requires_manager_approval),requires_inspector_approval=VALUES(requires_inspector_approval),items_json=VALUES(items_json),status="active",updated_by=VALUES(updated_by)');
    $templateStmt->execute([$templateCode, $templateCode, $templateCode, 'چک‌لیست رفتار فروش و کوچینگ کسب‌وکار', 'marketing_sales_manager', 'marketing_sales_manager', 'marketing_sales', 'weekly', 'کنترل رفتار مکالمه، گوش دادن حرفه‌ای، FAB، CTA، پیگیری و اقدام اصلاحی.', 'sales_script', 1, 0, $itemsJson, 90, $actorId ?: null, $actorId ?: null]);
    $templateId = (int)$db->query('SELECT id FROM hr_checklist_templates WHERE template_code=' . $db->quote($templateCode) . ' LIMIT 1')->fetchColumn();
    $itemStmt = $db->prepare('INSERT INTO hr_checklist_items (template_id,template_code,item_code,title,phase,is_required,has_quality_score,max_quality_score,has_note,can_create_planner_task,sort_order,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,"active") ON DUPLICATE KEY UPDATE title=VALUES(title),phase=VALUES(phase),is_required=VALUES(is_required),has_quality_score=VALUES(has_quality_score),max_quality_score=VALUES(max_quality_score),has_note=VALUES(has_note),can_create_planner_task=VALUES(can_create_planner_task),sort_order=VALUES(sort_order),status="active"');
    $sort = 0;
    foreach ($items as $item) {
        $sort += 10;
        [$code, $title, $phase, $required, $score, $max, $note, $task] = $item;
        $itemStmt->execute([$templateId, $templateCode, $code, $title, $phase, $required, $score, $max, $note, $task, $sort]);
    }

    if (function_exists('hrAuditLog')) {
        hrAuditLog('hr_checklists', 'seed', null, 'seed_key_restaurant_duties_checklists', $actorId ?: null, null, ['categories' => count($categories), 'coaching_items' => count($items)]);
    }

    return [
        'duties' => $duties,
        'checklists' => $checklists,
        'categories' => count($categories),
        'business_coaching_items' => count($items),
        'inserted' => (int)($duties['inserted'] ?? 0) + (int)($checklists['templates_inserted'] ?? 0) + (int)($checklists['items_inserted'] ?? 0) + count($categories) + count($items),
        'updated' => (int)($duties['updated'] ?? 0) + (int)($checklists['templates_updated'] ?? 0) + (int)($checklists['items_updated'] ?? 0),
        'skipped' => (int)($duties['skipped'] ?? 0) + (int)($checklists['templates_skipped'] ?? 0) + (int)($checklists['items_skipped'] ?? 0),
        'messages' => ['Restaurant duties/checklists seed creates templates/reference rows only; no submissions or operational assignments are created.'],
    ];
}
