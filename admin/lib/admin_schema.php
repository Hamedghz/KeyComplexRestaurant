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


function indexExists(string $table, string $index): bool {
    try {
        $stmt = adminDb()->prepare('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = :index_name');
        $stmt->execute(['index_name' => $index]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensureTableColumns(string $table, array $columns): void {
    if (!tableExists($table)) {
        return;
    }
    $db = adminDb();
    foreach ($columns as $column => $definition) {
        if (!columnExists($table, $column)) {
            try {
                $db->exec("ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD COLUMN `" . str_replace('`', '``', $column) . "` {$definition}");
            } catch (Throwable $e) {
                error_log("Schema column ensure failed for {$table}.{$column}: " . $e->getMessage());
            }
        }
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

    if (tableExists('crm_customers')) {
        if (!columnExists('crm_customers', 'acquisition_source')) {
            $run("ALTER TABLE `crm_customers` ADD COLUMN `acquisition_source` varchar(100) DEFAULT NULL AFTER `reminder_date`", 'افزودن acquisition_source به CRM');
        }
        if (!indexExists('crm_customers', 'idx_crm_acquisition_source')) {
            $run("ALTER TABLE `crm_customers` ADD INDEX `idx_crm_acquisition_source` (`acquisition_source`)", 'ایندکس منبع جذب CRM');
        }
        if (!columnExists('crm_customers', 'attended_match_event')) {
            $run("ALTER TABLE `crm_customers` ADD COLUMN `attended_match_event` tinyint(1) NOT NULL DEFAULT 0 AFTER `tags`", 'افزودن attended_match_event به CRM');
        }
        if (!indexExists('crm_customers', 'idx_crm_attended')) {
            $run("ALTER TABLE `crm_customers` ADD INDEX `idx_crm_attended` (`attended_match_event`)", 'ایندکس حضور مسابقه CRM');
        }
    }

    $run("CREATE TABLE IF NOT EXISTS `acquisition_sources` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` varchar(100) NOT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_acquisition_title` (`title`),
        KEY `idx_acquisition_active_order` (`active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول acquisition_sources');

    $sources = ['Instagram','Telegram','Google','Balad','Friend Referral','Walk-in','Website','Advertisement','Other'];
    foreach ($sources as $i => $source) {
        try {
            $stmt = $db->prepare('INSERT INTO acquisition_sources (title, sort_order, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE title = title');
            $stmt->execute([$source, ($i + 1) * 10]);
        } catch (Throwable $e) {
            $changes[] = 'منبع جذب پیش‌فرض ' . $source . ' (خطا: ' . $e->getMessage() . ')';
        }
    }

    $run("CREATE TABLE IF NOT EXISTS `social_links` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` varchar(100) NOT NULL,
        `icon` varchar(50) NOT NULL,
        `url` varchar(500) NOT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_social_active_order` (`active`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول social_links');

    $socials = [
        ['Instagram', '📷', 'https://instagram.com/keyrestaurant', 10],
        ['Telegram', '✈️', 'https://t.me/keyrestaurant', 20],
        ['WhatsApp', '💬', 'https://wa.me/989121234567', 30],
        ['YouTube', '▶️', '', 40],
        ['Aparat', '🎬', '', 50],
        ['LinkedIn', '💼', '', 60],
        ['X', '𝕏', '', 70],
        ['TikTok', '🎵', '', 80],
        ['Snapchat', '👻', '', 90],
    ];
    foreach ($socials as $item) {
        try {
            $stmt = $db->prepare('INSERT INTO social_links (title, icon, url, sort_order, active) SELECT ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM social_links WHERE title = ?)');
            $stmt->execute([$item[0], $item[1], $item[2], $item[3], $item[2] !== '' ? 1 : 0, $item[0]]);
        } catch (Throwable $e) {
            $changes[] = 'شبکه اجتماعی پیش‌فرض ' . $item[0] . ' (خطا: ' . $e->getMessage() . ')';
        }
    }

    $run("CREATE TABLE IF NOT EXISTS `employee_evaluations` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `evaluator_id` int(11) UNSIGNED NOT NULL,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `period_month` char(7) NOT NULL,
        `category_group` varchar(50) NOT NULL DEFAULT 'common',
        `scores` JSON NOT NULL,
        `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `notes` text DEFAULT NULL,
        `is_private` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_eval_once` (`evaluator_id`, `employee_id`, `period_month`),
        KEY `idx_eval_employee_month` (`employee_id`, `period_month`),
        CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_eval_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول employee_evaluations');

    $run("CREATE TABLE IF NOT EXISTS `employee_score_history` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `period_month` char(7) NOT NULL,
        `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `peer_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_score_employee_month` (`employee_id`, `period_month`),
        KEY `idx_score_month_final` (`period_month`, `final_score`),
        CONSTRAINT `fk_score_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول employee_score_history');

    $run("CREATE TABLE IF NOT EXISTS `employee_rewards` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `title` varchar(160) NOT NULL,
        `description` text DEFAULT NULL,
        `reward_date` date NOT NULL,
        `created_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_rewards_employee_date` (`employee_id`, `reward_date`),
        CONSTRAINT `fk_rewards_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_rewards_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول employee_rewards');

    $run("CREATE TABLE IF NOT EXISTS `employee_warnings` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `title` varchar(160) NOT NULL,
        `description` text DEFAULT NULL,
        `warning_date` date NOT NULL,
        `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
        `created_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_warnings_employee_date` (`employee_id`, `warning_date`),
        CONSTRAINT `fk_warnings_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_warnings_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول employee_warnings');

    $run("CREATE TABLE IF NOT EXISTS `employee_monthly_inputs` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) UNSIGNED NOT NULL,
        `period_month` char(7) NOT NULL,
        `manager_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `attendance_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `department_kpi_score` decimal(5,2) NOT NULL DEFAULT 0.00,
        `notes` text DEFAULT NULL,
        `created_by` int(11) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_monthly_inputs` (`employee_id`, `period_month`),
        CONSTRAINT `fk_monthly_inputs_employee` FOREIGN KEY (`employee_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_monthly_inputs_creator` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول employee_monthly_inputs');

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

    if (tableExists('matches')) {
        ensureTableColumns('matches', [
            'final_score_team_a' => 'int(11) DEFAULT NULL',
            'final_score_team_b' => 'int(11) DEFAULT NULL',
            'match_finished' => 'tinyint(1) NOT NULL DEFAULT 0',
        ]);
        if (!indexExists('matches', 'idx_matches_finished')) {
            $run("ALTER TABLE `matches` ADD INDEX `idx_matches_finished` (`match_finished`)", 'ایندکس پایان مسابقه');
        }
    }

    if (tableExists('predictions')) {
        ensureTableColumns('predictions', [
            'is_correct_prediction' => 'tinyint(1) NOT NULL DEFAULT 0',
            'crm_match' => 'tinyint(1) NOT NULL DEFAULT 0',
            'attended_match' => 'tinyint(1) NOT NULL DEFAULT 0',
        ]);
        $run("UPDATE `predictions` SET `crm_match` = `crm_matched` WHERE (`crm_match` IS NULL OR `crm_match` = 0) AND `crm_matched` = 1", 'همگام‌سازی crm_match');
        $run("UPDATE `predictions` SET `attended_match` = `attended_match_time` WHERE (`attended_match` IS NULL OR `attended_match` = 0) AND `attended_match_time` = 1", 'همگام‌سازی attended_match');
    }

    $run("CREATE TABLE IF NOT EXISTS `pool_leads` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `full_name` varchar(255) DEFAULT NULL,
        `mobile` varchar(20) NOT NULL,
        `acquisition_source` varchar(100) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `status` enum('new','contacted','converted','rejected') NOT NULL DEFAULT 'new',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pool_mobile` (`mobile`),
        KEY `idx_pool_source` (`acquisition_source`),
        KEY `idx_pool_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول pool_leads');

    $run("CREATE TABLE IF NOT EXISTS `traffic_logs` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_id` varchar(64) NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `country` varchar(100) DEFAULT NULL,
        `city` varchar(100) DEFAULT NULL,
        `isp` varchar(255) DEFAULT NULL,
        `referrer` varchar(500) DEFAULT NULL,
        `landing_page` varchar(500) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `browser` varchar(100) DEFAULT NULL,
        `os` varchar(100) DEFAULT NULL,
        `device` varchar(50) DEFAULT NULL,
        `language` varchar(20) DEFAULT NULL,
        `visit_duration` int(11) DEFAULT NULL,
        `pages_viewed` int(11) NOT NULL DEFAULT 1,
        `is_bot` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_traffic_session` (`session_id`),
        KEY `idx_traffic_date` (`created_at`),
        KEY `idx_traffic_country` (`country`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول traffic_logs');

    $run("CREATE TABLE IF NOT EXISTS `traffic_sources` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `source_name` varchar(100) NOT NULL,
        `source_type` enum('direct','organic','social','referral','campaign') NOT NULL DEFAULT 'direct',
        `visits_count` int(11) NOT NULL DEFAULT 0,
        `date` date NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_source_date` (`source_name`, `date`),
        KEY `idx_source_type` (`source_type`),
        KEY `idx_source_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول traffic_sources');

    $run("CREATE TABLE IF NOT EXISTS `visitor_sessions` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_id` varchar(64) NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_session` (`session_id`),
        KEY `idx_session_active` (`is_active`, `last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول visitor_sessions');

    $run("CREATE TABLE IF NOT EXISTS `visitor_locations` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `country` varchar(100) NOT NULL,
        `city` varchar(100) DEFAULT NULL,
        `visits_count` int(11) NOT NULL DEFAULT 0,
        `date` date NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_location_date` (`country`, `city`, `date`),
        KEY `idx_location_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول visitor_locations');

    $run("CREATE TABLE IF NOT EXISTS `traffic_statistics` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `stat_date` date NOT NULL,
        `total_visits` int(11) NOT NULL DEFAULT 0,
        `unique_visitors` int(11) NOT NULL DEFAULT 0,
        `total_page_views` int(11) NOT NULL DEFAULT 0,
        `bounce_rate` decimal(5,2) DEFAULT NULL,
        `avg_duration` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_stat_date` (`stat_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ایجاد/بررسی جدول traffic_statistics');

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
