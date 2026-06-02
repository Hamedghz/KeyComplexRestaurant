<?php
require_once __DIR__ . '/admin_crud.php';

function adminDb(): PDO {
    return Database::getInstance()->getConnection();
}

function tableExists(string $table): bool {
    try {
        $stmt = adminDb()->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function columnExists(string $table, string $column): bool {
    try {
        $stmt = adminDb()->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE :column');
        $stmt->execute(['column' => $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensureAdminSchema(): array {
    $db = adminDb();
    $changes = [];
    $run = function (string $sql, string $label) use ($db, &$changes) {
        try {
            $db->exec($sql);
            $changes[] = $label;
        } catch (Throwable $e) {
            $changes[] = $label . ' (خطا: ' . $e->getMessage() . ')';
        }
    };

    if (tableExists('admins')) {
        if (!columnExists('admins', 'department')) {
            $run("ALTER TABLE `admins` ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `role`", 'افزودن department به admins');
        }
        if (!columnExists('admins', 'permissions')) {
            $run("ALTER TABLE `admins` ADD COLUMN `permissions` JSON DEFAULT NULL AFTER `department`", 'افزودن permissions به admins');
        }
        $run("ALTER TABLE `admins` MODIFY `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin'", 'همگام‌سازی نقش employee در admins');
    }

    $run("CREATE TABLE IF NOT EXISTS `employee_performance` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) UNSIGNED NOT NULL,
        `period_month` char(7) NOT NULL,
        `score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `reward` varchar(255) DEFAULT NULL,
        `penalty` varchar(255) DEFAULT NULL,
        `evaluation_notes` text DEFAULT NULL,
        `evaluated_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_employee_period` (`admin_id`, `period_month`),
        KEY `idx_employee_performance_month_score` (`period_month`, `score`),
        CONSTRAINT `fk_employee_performance_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_employee_performance_evaluator` FOREIGN KEY (`evaluated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول employee_performance');

    $settings = [
        ['site_description_fa', 'تجربه‌ای لوکس از غذا و نوشیدنی', 'text', 'general', 1],
        ['logo_image', '', 'url', 'general', 1],
        ['seo_title_fa', 'KEY رستوران و کافه', 'text', 'seo', 1],
        ['seo_description_fa', 'رستوران و کافه KEY', 'text', 'seo', 1],
        ['default_language', 'fa', 'text', 'general', 1],
        ['jalali_calendar_enabled', '1', 'boolean', 'general', 0],
        ['balad_map_url', 'https://balad.ir/location?latitude=35.6892&longitude=51.3890', 'url', 'contact', 1],
        ['lotus_logo_image', '', 'url', 'lotus', 1],
        ['lotus_title_fa', 'KEY رستوران و کافه', 'text', 'lotus', 1],
        ['lotus_subtitle_fa', 'تجربه‌ای بی‌نظیر از غذا و نوشیدنی', 'text', 'lotus', 1],
        ['lotus_description_fa', '', 'text', 'lotus', 1],
        ['lotus_cta_text_fa', '', 'text', 'lotus', 1],
        ['lotus_cta_link', '#menu', 'url', 'lotus', 1],
        ['lotus_active', '1', 'boolean', 'lotus', 1],
    ];
    foreach ($settings as $setting) {
        try {
            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, category, is_public) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key');
            $stmt->execute($setting);
        } catch (Throwable $e) {
            $changes[] = 'تنظیم پیش‌فرض ' . $setting[0] . ' (خطا: ' . $e->getMessage() . ')';
        }
    }

    return $changes;
}

function schemaColumns(string $table): array {
    if (!tableExists($table)) return [];
    $stmt = adminDb()->query('DESCRIBE `' . str_replace('`', '``', $table) . '`');
    return $stmt->fetchAll();
}

function uploadAdminImage(string $field, string $folder, string $current = ''): string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $current;
    }
    if (!in_array($_FILES[$field]['type'], ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException('نوع تصویر مجاز نیست.');
    }
    $dir = UPLOAD_PATH . '/' . trim($folder, '/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) throw new RuntimeException('پسوند تصویر مجاز نیست.');
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) throw new RuntimeException('آپلود فایل ناموفق بود.');
    return 'uploads/' . trim($folder, '/') . '/' . $name;
}
