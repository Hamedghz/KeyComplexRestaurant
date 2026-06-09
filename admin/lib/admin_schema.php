<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/SchemaSynchronizer.php';


if (!function_exists('adminGuard')) {
    function adminGuard($requiredRole = 'employee') {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        try {
            $auth = new Auth();
        } catch (Throwable $e) {
            error_log('[admin] Authentication bootstrap failed: ' . $e->getMessage());
            http_response_code(500);
            exit('درخواست قابل پردازش نیست. جزئیات خطا در لاگ سیستم ثبت شد.');
        }
        if (!$auth->isLoggedIn()) {
            header('Location: index.php');
            exit;
        }
        if (!$auth->hasPermission($requiredRole)) {
            http_response_code(403);
            exit('دسترسی کافی نیست.');
        }
        return $auth->getCurrentAdmin();
    }
}

if (!function_exists('h')) {
    function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('safeAdminLog')) {
    function safeAdminLog(string $message): void { error_log('[admin] ' . $message); }
}

function adminDb(): PDO {
    return Database::getInstance()->getConnection();
}

function adminTableExists(string $table): bool {
    try {
        $stmt = adminDb()->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function adminColumnExists(string $table, string $column): bool {
    try {
        $stmt = adminDb()->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE :column');
        $stmt->execute(['column' => $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}


function adminIndexExists(string $table, string $index): bool {
    try {
        $stmt = adminDb()->prepare('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = :index_name');
        $stmt->execute(['index_name' => $index]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensureTableColumns(string $table, array $columns): void {
    if (!adminTableExists($table)) {
        return;
    }
    $db = adminDb();
    foreach ($columns as $column => $definition) {
        if (!adminColumnExists($table, $column)) {
            try {
                $db->exec("ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD COLUMN `" . str_replace('`', '``', $column) . "` {$definition}");
            } catch (Throwable $e) {
                error_log("Schema column ensure failed for {$table}.{$column}: " . $e->getMessage());
            }
        }
    }
}


function adminCanonicalSchemaFiles(): array {
    $schema = ROOT_PATH . '/database/schema.sql';
    return is_readable($schema) ? [$schema] : [];
}

function adminExtractCreateStatement(string $sql, string $table): ?string {
    $quotedTable = preg_quote($table, '/');
    if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`' . $quotedTable . '`/i', $sql, $match, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $start = $match[0][1];
    $quote = null;
    $length = strlen($sql);
    for ($i = $start; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote !== null) {
            if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = null;
            }
            continue;
        }
        if (in_array($char, ["'", '"', '`'], true)) {
            $quote = $char;
            continue;
        }
        if ($char === ';') {
            return trim(substr($sql, $start, $i - $start + 1));
        }
    }

    return null;
}

function adminCanonicalCreateStatement(string $table): ?string {
    foreach (adminCanonicalSchemaFiles() as $schemaFile) {
        $sql = file_get_contents($schemaFile);
        if ($sql === false || trim($sql) === '') {
            continue;
        }

        $statement = adminExtractCreateStatement($sql, $table);
        if ($statement !== null) {
            return $statement;
        }
    }

    return null;
}

function adminSharedHostingCompatibleCreate(string $statement): string {
    // Some shared hosts still run old MySQL/MariaDB variants. LONGTEXT preserves
    // existing JSON payloads safely when a native JSON column is unavailable.
    return preg_replace('/\bJSON\b/i', 'LONGTEXT', $statement) ?: $statement;
}

function ensureAdminCanonicalTables(PDO $db, array $requestedTables = []): array {
    $changes = [];
    // Keep this list in dependency order. It is the shared-hosting fallback when
    // the full schema synchronizer stops early on old MySQL/MariaDB features
    // (for example native JSON columns). Missing admin modules should still be
    // able to repair their own tables from database/schema.sql.
    $canonicalOrder = [
        'admins',
        'admin_sessions',
        'activity_log',
        'users',
        'orders',
        'order_items',
        'feedback',
        'media',
        'settings',
        'newsletter_subscribers',
        'memberships',
        'menu_categories',
        'menu_items',
        'dynamic_forms',
        'survey_responses',
        'crm_customers',
        'crm_timelines',
        'hero_banners',
        'matches',
        'predictions',
        'system_versions',
        'acquisition_sources',
        'social_links',
        'employee_evaluations',
        'employee_monthly_inputs',
        'employee_score_history',
        'employee_rewards',
        'employee_warnings',
        'employee_performance',
        'key_story_settings',
        'pool_leads',
        'traffic_logs',
        'traffic_sources',
        'visitor_sessions',
        'visitor_locations',
        'traffic_statistics',
        'schema_migrations',
        'analytics_visitors',
        'analytics_sessions',
        'analytics_pageviews',
        'visitor_analytics_logs',
    ];

    $dependencyMap = [
        'menu_items' => ['menu_categories'],
        'dynamic_forms' => ['admins'],
        'admin_sessions' => ['admins'],
        'activity_log' => ['admins'],
        'order_items' => ['orders', 'menu_items'],
        'feedback' => ['users', 'orders'],
        'media' => ['admins'],
        'memberships' => ['users'],
        'survey_responses' => ['admins', 'users', 'orders', 'dynamic_forms'],
        'crm_customers' => ['users'],
        'crm_timelines' => ['users', 'crm_customers'],
        'predictions' => ['matches'],
        'employee_evaluations' => ['admins'],
        'employee_monthly_inputs' => ['admins'],
        'employee_score_history' => ['admins'],
        'employee_rewards' => ['admins'],
        'employee_warnings' => ['admins'],
        'employee_performance' => ['admins'],
    ];

    if ($requestedTables) {
        $wanted = [];
        $addWithDependencies = function (string $table) use (&$wanted, &$addWithDependencies, $dependencyMap): void {
            foreach (($dependencyMap[$table] ?? []) as $dependency) {
                $addWithDependencies($dependency);
            }
            $wanted[$table] = true;
        };
        foreach ($requestedTables as $table) {
            $addWithDependencies($table);
        }
        $tables = array_values(array_filter($canonicalOrder, static fn($table) => isset($wanted[$table])));
    } else {
        $tables = $canonicalOrder;
    }

    foreach ($tables as $table) {
        if (adminTableExists($table)) {
            continue;
        }

        $statement = adminCanonicalCreateStatement($table);
        if ($statement === null) {
            $changes[] = "canonical create skipped for {$table} (schema statement missing)";
            continue;
        }

        try {
            $db->exec($statement);
            $changes[] = "created missing canonical table {$table}";
        } catch (Throwable $firstError) {
            try {
                $db->exec(adminSharedHostingCompatibleCreate($statement));
                $changes[] = "created missing canonical table {$table} with shared-hosting compatible JSON columns";
            } catch (Throwable $fallbackError) {
                $changes[] = "canonical create failed for {$table} (خطا: " . $fallbackError->getMessage() . ')';
                safeAdminLog("Canonical table repair failed for {$table}: " . $firstError->getMessage() . ' | fallback: ' . $fallbackError->getMessage());
            }
        }
    }

    return $changes;
}

function ensureAdminSchema(): array {
    $db = adminDb();
    try {
        foreach (SchemaSynchronizer::sync($db, ROOT_PATH . '/database/schema.sql') as $syncChange) {
            safeAdminLog('schema-sync: ' . $syncChange);
        }
    } catch (Throwable $e) {
        safeAdminLog('Schema synchronizer skipped: ' . $e->getMessage());
    }

    foreach (ensureAdminCanonicalTables($db) as $repairChange) {
        safeAdminLog('Canonical table repair: ' . $repairChange);
    }

    $run = function (string $sql, string $label) use ($db) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            safeAdminLog($label . ' failed: ' . $e->getMessage());
        }
    };

    $run("CREATE TABLE IF NOT EXISTS `admin_sessions` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) UNSIGNED NOT NULL,
        `session_token` varchar(64) NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_admin_session_token` (`session_token`),
        KEY `idx_admin_sessions_admin` (`admin_id`),
        KEY `idx_admin_sessions_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table admin_sessions');

    $run("CREATE TABLE IF NOT EXISTS `activity_log` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) UNSIGNED DEFAULT NULL,
        `action` varchar(120) NOT NULL,
        `entity_type` varchar(120) DEFAULT NULL,
        `entity_id` varchar(120) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_activity_admin` (`admin_id`),
        KEY `idx_activity_action` (`action`),
        KEY `idx_activity_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table activity_log');

    if (adminTableExists('admins')) {
        if (!adminColumnExists('admins', 'department')) {
            $run("ALTER TABLE `admins` ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `role`", 'افزودن department به admins');
        }
        if (!adminColumnExists('admins', 'permissions')) {
            $run("ALTER TABLE `admins` ADD COLUMN `permissions` JSON DEFAULT NULL AFTER `department`", 'افزودن permissions به admins');
        }
        $run("ALTER TABLE `admins` MODIFY `role` enum('super_admin','admin','manager','employee') DEFAULT 'admin'", 'همگام‌سازی نقش employee در admins');
    }

    if (adminTableExists('crm_customers')) {
        if (!adminColumnExists('crm_customers', 'acquisition_source')) {
            $run("ALTER TABLE `crm_customers` ADD COLUMN `acquisition_source` varchar(100) DEFAULT NULL AFTER `reminder_date`", 'افزودن acquisition_source به CRM');
        }
        if (!adminIndexExists('crm_customers', 'idx_crm_acquisition_source')) {
            $run("ALTER TABLE `crm_customers` ADD INDEX `idx_crm_acquisition_source` (`acquisition_source`)", 'ایندکس منبع جذب CRM');
        }
        if (!adminColumnExists('crm_customers', 'attended_match_event')) {
            $run("ALTER TABLE `crm_customers` ADD COLUMN `attended_match_event` tinyint(1) NOT NULL DEFAULT 0 AFTER `tags`", 'افزودن attended_match_event به CRM');
        }
        if (!adminIndexExists('crm_customers', 'idx_crm_attended')) {
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table acquisition_sources');

    $sources = ['Instagram','Telegram','Google','Balad','Friend Referral','Walk-in','Website','Advertisement','Other'];
    foreach ($sources as $i => $source) {
        try {
            $stmt = $db->prepare('INSERT INTO acquisition_sources (title, sort_order, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE title = title');
            $stmt->execute([$source, ($i + 1) * 10]);
        } catch (Throwable $e) {
            safeAdminLog('Default acquisition source failed for ' . $source . ': ' . $e->getMessage());
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table social_links');

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
            safeAdminLog('Default social link failed for ' . $item[0] . ': ' . $e->getMessage());
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table employee_evaluations');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table employee_score_history');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table employee_rewards');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table employee_warnings');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table employee_monthly_inputs');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table employee_performance');

    if (adminTableExists('matches')) {
        ensureTableColumns('matches', [
            'team_one_name' => 'varchar(120) DEFAULT NULL',
            'team_two_name' => 'varchar(120) DEFAULT NULL',
            'team_one_logo' => 'varchar(255) DEFAULT NULL',
            'team_two_logo' => 'varchar(255) DEFAULT NULL',
            'prediction_start_at' => 'datetime DEFAULT NULL',
            'prediction_end_at' => 'datetime DEFAULT NULL',
            'match_start_at' => 'datetime DEFAULT NULL',
            'match_end_at' => 'datetime DEFAULT NULL',
            'final_score_team_a' => 'int(11) DEFAULT NULL',
            'final_score_team_b' => 'int(11) DEFAULT NULL',
            'final_team_one_score' => 'int(11) DEFAULT NULL',
            'final_team_two_score' => 'int(11) DEFAULT NULL',
            'final_result_status' => 'varchar(50) DEFAULT NULL',
            'match_finished' => 'tinyint(1) NOT NULL DEFAULT 0',
            'reward_title' => 'varchar(200) DEFAULT NULL',
            'points_reward' => 'int(11) NOT NULL DEFAULT 0',
        ]);
        $run("ALTER TABLE `matches` MODIFY `status` enum('active','inactive','archived','scheduled','live','finished','cancelled') NOT NULL DEFAULT 'active'", 'همگام‌سازی وضعیت مسابقه');
        if (!adminIndexExists('matches', 'idx_matches_finished')) {
            $run("ALTER TABLE `matches` ADD INDEX `idx_matches_finished` (`match_finished`)", 'ایندکس پایان مسابقه');
        }
        foreach (['idx_matches_status' => '(`status`)', 'idx_matches_prediction_start' => '(`prediction_start_at`)', 'idx_matches_prediction_end' => '(`prediction_end_at`)', 'idx_matches_match_start' => '(`match_start_at`)'] as $index => $columns) {
            if (!adminIndexExists('matches', $index)) {
                $run("ALTER TABLE `matches` ADD INDEX `{$index}` {$columns}", 'ایندکس ' . $index);
            }
        }
    }

    if (adminTableExists('predictions')) {
        ensureTableColumns('predictions', [
            'customer_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'customer_last_name' => 'varchar(150) DEFAULT NULL',
            'customer_mobile' => 'varchar(20) DEFAULT NULL',
            'team_one_name' => 'varchar(120) DEFAULT NULL',
            'team_two_name' => 'varchar(120) DEFAULT NULL',
            'predicted_team_one_score' => 'tinyint UNSIGNED DEFAULT NULL',
            'predicted_team_two_score' => 'tinyint UNSIGNED DEFAULT NULL',
            'wants_reservation' => 'tinyint(1) NOT NULL DEFAULT 0',
            'reserve_table_interest' => 'tinyint(1) NOT NULL DEFAULT 0',
            'is_correct_prediction' => 'tinyint(1) NOT NULL DEFAULT 0',
            'is_winner' => 'tinyint(1) NOT NULL DEFAULT 0',
            'evaluated_at' => 'datetime DEFAULT NULL',
            'crm_match' => 'tinyint(1) NOT NULL DEFAULT 0',
            'attended_match' => 'tinyint(1) NOT NULL DEFAULT 0',
            'source' => 'varchar(150) DEFAULT NULL',
            'ip_address' => 'varchar(45) DEFAULT NULL',
            'user_agent' => 'varchar(255) DEFAULT NULL',
            'submitted_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ]);
        $run("UPDATE `predictions` SET `crm_match` = `crm_matched` WHERE (`crm_match` IS NULL OR `crm_match` = 0) AND `crm_matched` = 1", 'همگام‌سازی crm_match');
        $run("UPDATE `predictions` SET `attended_match` = `attended_match_time` WHERE (`attended_match` IS NULL OR `attended_match` = 0) AND `attended_match_time` = 1", 'همگام‌سازی attended_match');
        foreach (['idx_predictions_customer_mobile' => '(`customer_mobile`)', 'idx_predictions_customer_id' => '(`customer_id`)', 'idx_predictions_winner' => '(`is_winner`)'] as $index => $columns) {
            if (!adminIndexExists('predictions', $index)) {
                $run("ALTER TABLE `predictions` ADD INDEX `{$index}` {$columns}", 'ایندکس ' . $index);
            }
        }
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table pool_leads');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table traffic_logs');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table traffic_sources');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table visitor_sessions');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table visitor_locations');

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table traffic_statistics');

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
            safeAdminLog('Default setting failed for ' . $setting[0] . ': ' . $e->getMessage());
        }
    }

    return [];
}

function schemaColumns(string $table): array {
    if (!adminTableExists($table)) return [];
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

if (!function_exists('redirectTo')) {
    function redirectTo($url) {
        header('Location: ' . $url);
        exit;
    }
}

function adminPermissionAllows(?array $admin, string $permission, array $fallbackRoles = ['manager', 'admin', 'super_admin']): bool {
    if (!$admin) return false;
    $role = (string)($admin['role'] ?? 'employee');
    if ($role === 'super_admin') return true;
    $raw = $admin['permissions'] ?? null;
    $permissions = [];
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $permissions = $decoded;
    } elseif (is_array($raw)) {
        $permissions = $raw;
    }
    if (array_key_exists($permission, $permissions)) return (bool)$permissions[$permission];
    return in_array($role, $fallbackRoles, true);
}

function adminAcquisitionSourceOptions(): array {
    try {
        if (adminTableExists('acquisition_sources')) {
            $stmt = adminDb()->query('SELECT title FROM acquisition_sources WHERE active = 1 ORDER BY sort_order ASC, title ASC');
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($rows) return array_combine($rows, $rows) ?: [];
        }
    } catch (Throwable $e) {
        safeAdminLog('Acquisition source lookup failed: ' . $e->getMessage());
    }
    return ['Instagram'=>'Instagram','Telegram'=>'Telegram','Google'=>'Google','Balad'=>'Balad','Friend Referral'=>'Friend Referral','Walk-in'=>'Walk-in','Website'=>'Website','Advertisement'=>'Advertisement','Other'=>'Other'];
}

function adminModuleDefinitions(): array {
    return [
        'matches' => [
            'title' => 'مدیریت مسابقات', 'add_label' => 'افزودن مسابقه', 'min_role' => 'manager', 'table' => 'matches',
            'search' => ['title','team_a','team_b','team_one_name','team_two_name','status','campaign_status'], 'filters' => ['status','campaign_status','is_active','active_for_prediction'], 'date_column' => 'match_date',
            'fields' => [
                'title' => ['label'=>'عنوان','type'=>'text'], 'description' => ['label'=>'توضیحات','type'=>'textarea'], 'rules' => ['label'=>'قوانین','type'=>'textarea'], 'participation_conditions' => ['label'=>'شرایط مشارکت','type'=>'textarea'],
                'team_a' => ['label'=>'تیم اول','type'=>'text','required'=>true], 'team_b' => ['label'=>'تیم دوم','type'=>'text','required'=>true], 'team_one_name' => ['label'=>'نام سازگار تیم اول','type'=>'text'], 'team_two_name' => ['label'=>'نام سازگار تیم دوم','type'=>'text'],
                'team_one_logo' => ['label'=>'لوگوی تیم اول','type'=>'image','folder'=>'matches'], 'team_two_logo' => ['label'=>'لوگوی تیم دوم','type'=>'image','folder'=>'matches'],
                'match_date' => ['label'=>'تاریخ مسابقه','type'=>'date','required'=>true], 'kickoff_time' => ['label'=>'ساعت شروع','type'=>'time','required'=>true], 'broadcast_time' => ['label'=>'ساعت پخش','type'=>'time'],
                'prediction_open_at' => ['label'=>'شروع پیش‌بینی','type'=>'datetime','required'=>true], 'prediction_close_at' => ['label'=>'پایان پیش‌بینی','type'=>'datetime','required'=>true], 'prediction_start_at' => ['label'=>'شروع پیش‌بینی سازگار','type'=>'datetime'], 'prediction_end_at' => ['label'=>'پایان پیش‌بینی سازگار','type'=>'datetime'],
                'match_start_at' => ['label'=>'شروع مسابقه سازگار','type'=>'datetime'], 'match_end_at' => ['label'=>'پایان مسابقه','type'=>'datetime'], 'start_date' => ['label'=>'شروع کمپین','type'=>'datetime'], 'end_date' => ['label'=>'پایان کمپین','type'=>'datetime'],
                'status' => ['label'=>'وضعیت','type'=>'select','options'=>['active'=>'فعال','inactive'=>'غیرفعال','archived'=>'آرشیو','scheduled'=>'برنامه‌ریزی شده','live'=>'زنده','finished'=>'تمام شده','cancelled'=>'لغو شده']],
                'campaign_status' => ['label'=>'وضعیت کمپین','type'=>'select','options'=>['active'=>'فعال','inactive'=>'غیرفعال','archived'=>'آرشیو']],
                'final_score_team_a' => ['label'=>'نتیجه تیم اول','type'=>'number'], 'final_score_team_b' => ['label'=>'نتیجه تیم دوم','type'=>'number'], 'final_team_one_score' => ['label'=>'نتیجه سازگار تیم اول','type'=>'number'], 'final_team_two_score' => ['label'=>'نتیجه سازگار تیم دوم','type'=>'number'], 'final_result_status' => ['label'=>'وضعیت نتیجه','type'=>'text'], 'match_finished' => ['label'=>'مسابقه تمام شده','type'=>'checkbox'],
                'campaign_target' => ['label'=>'هدف کمپین','type'=>'text'], 'reward_title' => ['label'=>'عنوان پاداش','type'=>'text'], 'points_reward' => ['label'=>'امتیاز پاداش','type'=>'number'], 'reward_points' => ['label'=>'امتیاز پاداش سازگار','type'=>'number'], 'reward_description' => ['label'=>'شرح پاداش','type'=>'textarea'],
                'is_active' => ['label'=>'فعال','type'=>'checkbox'], 'active_for_prediction' => ['label'=>'فعال برای پیش‌بینی','type'=>'checkbox'],
            ],
            'columns' => ['id','title','team_a','team_b','match_date','kickoff_time','prediction_open_at','prediction_close_at','status','is_active','active_for_prediction'],
        ],
        'predictions' => [
            'title' => 'پیش‌بینی‌ها', 'add_label' => 'افزودن پیش‌بینی', 'min_role' => 'manager', 'table' => 'predictions',
            'search' => ['customer_name','customer_last_name','mobile','customer_mobile'], 'filters' => ['match_id','status','is_winner'], 'date_column' => 'submitted_at',
            'join' => 'SELECT p.*, CONCAT(COALESCE(m.title, ""), " ", COALESCE(m.team_a, m.team_one_name, ""), " - ", COALESCE(m.team_b, m.team_two_name, "")) AS match_title, CONCAT(COALESCE(p.predicted_score_team_a, p.predicted_team_one_score), " - ", COALESCE(p.predicted_score_team_b, p.predicted_team_two_score)) AS predicted_scores FROM predictions p LEFT JOIN matches m ON p.match_id = m.id', 'alias' => 'p', 'required_tables' => ['matches'],
            'fields' => [
                'customer_id' => ['label'=>'شناسه مشتری','type'=>'number'], 'customer_name' => ['label'=>'نام','type'=>'text','required'=>true], 'customer_last_name' => ['label'=>'نام خانوادگی','type'=>'text'], 'customer_mobile' => ['label'=>'موبایل مشتری','type'=>'mobile'], 'mobile' => ['label'=>'موبایل','type'=>'mobile'],
                'match_id' => ['label'=>'مسابقه','type'=>'match','required'=>true], 'team_one_name' => ['label'=>'نام تیم اول','type'=>'text'], 'team_two_name' => ['label'=>'نام تیم دوم','type'=>'text'],
                'predicted_team_one_score' => ['label'=>'پیش‌بینی تیم اول','type'=>'number'], 'predicted_team_two_score' => ['label'=>'پیش‌بینی تیم دوم','type'=>'number'], 'predicted_score_team_a' => ['label'=>'گل تیم اول','type'=>'number'], 'predicted_score_team_b' => ['label'=>'گل تیم دوم','type'=>'number'],
                'prediction_content' => ['label'=>'محتوای پیش‌بینی','type'=>'textarea'], 'status' => ['label'=>'وضعیت','type'=>'select','options'=>['pending'=>'در انتظار','approved'=>'تایید','rejected'=>'رد']], 'is_winner' => ['label'=>'برنده','type'=>'checkbox'], 'points_awarded' => ['label'=>'امتیاز اعطا شده','type'=>'number'],
                'crm_follow_up' => ['label'=>'پیگیری CRM','type'=>'checkbox'], 'wants_reservation' => ['label'=>'علاقه‌مند به رزرو','type'=>'checkbox'], 'reserve_table_interest' => ['label'=>'علاقه‌مند به رزرو سازگار','type'=>'checkbox'], 'source' => ['label'=>'منبع','type'=>'text'],
                'crm_matched' => ['label'=>'تطبیق CRM','type'=>'checkbox'], 'customer_exists' => ['label'=>'مشتری موجود','type'=>'checkbox'], 'attended_match_time' => ['label'=>'حضور زمان مسابقه','type'=>'checkbox'], 'is_correct_prediction' => ['label'=>'پیش‌بینی صحیح','type'=>'checkbox'], 'crm_match' => ['label'=>'تطبیق CRM سازگار','type'=>'checkbox'], 'attended_match' => ['label'=>'حضور مسابقه','type'=>'checkbox'],
            ],
            'columns' => ['id','customer_name','mobile','match_title','predicted_scores','status','is_winner','points_awarded','submitted_at'],
        ],
        'categories' => [
            'title' => 'دسته‌بندی‌های منو', 'add_label' => 'افزودن دسته‌بندی', 'min_role' => 'manager', 'table' => 'menu_categories', 'unique' => 'slug', 'search' => ['name_fa','name_en','slug'], 'filters' => ['visible_qr_menu','visible_website','visible_kiosk','is_active'], 'date_column' => 'created_at',
            'fields' => [
                'name_fa' => ['label'=>'نام فارسی','type'=>'text','required'=>true], 'name_en' => ['label'=>'نام انگلیسی','type'=>'text','required'=>true], 'slug' => ['label'=>'اسلاگ','type'=>'text'], 'description_fa' => ['label'=>'توضیح فارسی','type'=>'textarea'], 'description_en' => ['label'=>'توضیح انگلیسی','type'=>'textarea'],
                'icon' => ['label'=>'آیکن','type'=>'text'], 'image' => ['label'=>'تصویر','type'=>'image','folder'=>'menu'], 'parent_id' => ['label'=>'دسته والد','type'=>'category'], 'sepid_id' => ['label'=>'شناسه Sepid','type'=>'text'],
                'visible_qr_menu' => ['label'=>'نمایش در QR','type'=>'checkbox'], 'visible_website' => ['label'=>'نمایش وب‌سایت','type'=>'checkbox'], 'visible_kiosk' => ['label'=>'نمایش کیوسک','type'=>'checkbox'], 'sort_order' => ['label'=>'ترتیب','type'=>'number'], 'is_active' => ['label'=>'فعال','type'=>'checkbox'],
            ],
            'columns' => ['id','name_fa','name_en','slug','parent_id','visible_qr_menu','visible_website','visible_kiosk','sort_order','is_active'],
        ],
        'menu-items' => [
            'title' => 'آیتم‌های منو', 'add_label' => 'افزودن آیتم منو', 'min_role' => 'manager', 'table' => 'menu_items', 'unique' => 'slug', 'search' => ['name_fa','name_en','slug','description_fa'], 'filters' => ['category_id','availability_status','is_available','is_featured','visible_qr_menu','visible_website'], 'date_column' => 'created_at',
            'join' => 'SELECT mi.*, mc.name_fa AS category_title FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id = mc.id', 'alias' => 'mi', 'required_tables' => ['menu_categories'],
            'fields' => [
                'category_id' => ['label'=>'دسته‌بندی','type'=>'category','required'=>true], 'name_fa' => ['label'=>'نام فارسی','type'=>'text','required'=>true], 'name_en' => ['label'=>'نام انگلیسی','type'=>'text','required'=>true], 'slug' => ['label'=>'اسلاگ','type'=>'text'], 'description_fa' => ['label'=>'توضیح فارسی','type'=>'textarea'], 'description_en' => ['label'=>'توضیح انگلیسی','type'=>'textarea'],
                'price' => ['label'=>'قیمت','type'=>'number','required'=>true], 'discount_price' => ['label'=>'قیمت تخفیف','type'=>'number'], 'image' => ['label'=>'تصویر','type'=>'image','folder'=>'menu'], 'gallery_images' => ['label'=>'گالری تصاویر JSON','type'=>'json'], 'ingredients_fa' => ['label'=>'مواد اولیه فارسی','type'=>'textarea'], 'ingredients_en' => ['label'=>'مواد اولیه انگلیسی','type'=>'textarea'],
                'calories' => ['label'=>'کالری','type'=>'number'], 'preparation_time' => ['label'=>'زمان آماده‌سازی','type'=>'number'], 'availability_status' => ['label'=>'وضعیت موجودی','type'=>'select','options'=>['available'=>'موجود','unavailable'=>'ناموجود','limited'=>'محدود']],
                'visible_qr_menu' => ['label'=>'نمایش در QR','type'=>'checkbox'], 'visible_website' => ['label'=>'نمایش وب‌سایت','type'=>'checkbox'], 'visible_kiosk' => ['label'=>'نمایش کیوسک','type'=>'checkbox'], 'visible_loyalty' => ['label'=>'نمایش وفاداری','type'=>'checkbox'], 'campaign_price' => ['label'=>'قیمت کمپین','type'=>'number'], 'promo_text' => ['label'=>'متن تبلیغ','type'=>'text'], 'promo_image' => ['label'=>'تصویر تبلیغ','type'=>'image','folder'=>'menu'], 'sepid_id' => ['label'=>'شناسه Sepid','type'=>'text'],
                'is_available' => ['label'=>'موجود','type'=>'checkbox'], 'is_featured' => ['label'=>'ویژه','type'=>'checkbox'], 'is_vegetarian' => ['label'=>'گیاهی','type'=>'checkbox'], 'is_spicy' => ['label'=>'تند','type'=>'checkbox'], 'sort_order' => ['label'=>'ترتیب','type'=>'number'],
            ],
            'columns' => ['id','category_title','name_fa','name_en','slug','price','discount_price','availability_status','is_available','is_featured','sort_order'],
        ],
        'surveys' => [
            'title' => 'فرم‌های نظرسنجی', 'add_label' => 'افزودن فرم', 'min_role' => 'admin', 'table' => 'dynamic_forms', 'unique' => 'form_name', 'search' => ['form_name','form_title_fa','form_title_en'], 'filters' => ['is_active','branch_id'], 'date_column' => 'created_at',
            'fields' => [
                'form_name' => ['label'=>'نام سیستمی','type'=>'text','required'=>true], 'form_title_fa' => ['label'=>'عنوان فارسی','type'=>'text','required'=>true], 'form_title_en' => ['label'=>'عنوان انگلیسی','type'=>'text'], 'form_description_fa' => ['label'=>'توضیح فارسی','type'=>'textarea'], 'form_description_en' => ['label'=>'توضیح انگلیسی','type'=>'textarea'],
                'form_schema' => ['label'=>'ساختار فرم JSON','type'=>'json','required'=>true,'default'=>'{"fields":[]}'], 'is_active' => ['label'=>'فعال','type'=>'checkbox'], 'display_order' => ['label'=>'ترتیب','type'=>'number'], 'start_date' => ['label'=>'شروع انتشار','type'=>'datetime'], 'end_date' => ['label'=>'پایان انتشار','type'=>'datetime'], 'publishing_channels' => ['label'=>'کانال‌های انتشار','type'=>'text'], 'branch_id' => ['label'=>'شعبه','type'=>'number'], 'survey_version' => ['label'=>'نسخه','type'=>'number'],
            ],
            'columns' => ['id','form_name','form_title_fa','form_title_en','is_active','display_order','start_date','end_date','publishing_channels','branch_id','survey_version'],
        ],
        'survey-responses' => [
            'title' => 'پاسخ‌های نظرسنجی', 'add_label' => 'افزودن پاسخ', 'min_role' => 'manager', 'table' => 'survey_responses', 'search' => ['customer_name','customer_phone','customer_email'], 'filters' => ['form_id','branch_id','is_dissatisfied','crm_follow_up'], 'date_column' => 'submitted_at',
            'join' => 'SELECT sr.*, df.form_title_fa AS form_title FROM survey_responses sr LEFT JOIN dynamic_forms df ON sr.form_id = df.id', 'alias' => 'sr', 'required_tables' => ['dynamic_forms'],
            'fields' => [
                'form_id' => ['label'=>'فرم','type'=>'survey_form','required'=>true], 'order_id' => ['label'=>'شناسه سفارش','type'=>'number'], 'user_id' => ['label'=>'شناسه کاربر','type'=>'number'], 'response_data' => ['label'=>'داده پاسخ JSON','type'=>'json','default'=>'{}'],
                'customer_name' => ['label'=>'نام مشتری','type'=>'text'], 'customer_phone' => ['label'=>'تلفن مشتری','type'=>'mobile'], 'customer_email' => ['label'=>'ایمیل مشتری','type'=>'text'], 'branch_id' => ['label'=>'شعبه','type'=>'number'], 'satisfaction_score' => ['label'=>'امتیاز رضایت','type'=>'number'], 'is_dissatisfied' => ['label'=>'ناراضی','type'=>'checkbox'], 'crm_follow_up' => ['label'=>'پیگیری CRM','type'=>'checkbox'],
            ],
            'columns' => ['id','form_title','customer_name','customer_phone','customer_email','branch_id','satisfaction_score','is_dissatisfied','crm_follow_up','submitted_at'],
        ],
        'crm' => [
            'title' => 'CRM مشتریان', 'add_label' => 'افزودن مشتری', 'min_role' => 'manager', 'table' => 'crm_customers', 'unique' => 'mobile', 'search' => ['full_name','mobile','tags','acquisition_source'], 'filters' => ['acquisition_source','customer_status','attended_match_event'], 'date_column' => 'created_at',
            'fields' => [
                'user_id' => ['label'=>'شناسه کاربر','type'=>'number'], 'full_name' => ['label'=>'نام کامل','type'=>'text','required'=>true], 'mobile' => ['label'=>'موبایل','type'=>'mobile','required'=>true], 'birth_date' => ['label'=>'تولد','type'=>'date'], 'first_purchase_date' => ['label'=>'اولین خرید','type'=>'date'],
                'total_orders' => ['label'=>'تعداد سفارش','type'=>'number'], 'total_purchase_volume' => ['label'=>'حجم خرید','type'=>'number'], 'reminder_date' => ['label'=>'یادآوری','type'=>'date'], 'acquisition_source' => ['label'=>'منبع جذب','type'=>'select','options'=>adminAcquisitionSourceOptions()], 'notes' => ['label'=>'یادداشت','type'=>'textarea'], 'surveys_completed_count' => ['label'=>'تعداد نظرسنجی','type'=>'number'], 'last_visit_date' => ['label'=>'آخرین مراجعه','type'=>'date'], 'tags' => ['label'=>'برچسب‌ها','type'=>'text'], 'attended_match_event' => ['label'=>'حضور در رویداد مسابقه','type'=>'checkbox'],
                'customer_status' => ['label'=>'وضعیت مشتری','type'=>'select','options'=>['new_customer'=>'مشتری جدید','loyal_customer'=>'وفادار','vip'=>'VIP','dissatisfied_customer'=>'ناراضی','churn_risk'=>'ریسک ریزش']], 'points_balance' => ['label'=>'امتیازها','type'=>'number'], 'rewards_notes' => ['label'=>'یادداشت پاداش‌ها','type'=>'textarea'], 'follow_up_notes' => ['label'=>'یادداشت پیگیری','type'=>'textarea'],
            ],
            'columns' => ['id','full_name','mobile','customer_status','points_balance','total_orders','total_purchase_volume','acquisition_source','attended_match_event','tags','created_at'],
        ],
        'banners' => [
            'title' => 'بنرهای صفحه اصلی', 'add_label' => 'افزودن بنر', 'min_role' => 'manager', 'table' => 'hero_banners', 'search' => ['title','subtitle','display_location'], 'filters' => ['display_location','active_status','match_id','menu_item_id','category_id'], 'date_column' => 'created_at',
            'fields' => [
                'title' => ['label'=>'عنوان','type'=>'text','required'=>true], 'subtitle' => ['label'=>'زیرعنوان','type'=>'text'], 'description' => ['label'=>'توضیحات','type'=>'textarea'], 'button_text' => ['label'=>'متن دکمه','type'=>'text'], 'button_link' => ['label'=>'لینک دکمه','type'=>'text'],
                'image' => ['label'=>'تصویر','type'=>'image','folder'=>'hero'], 'mobile_image' => ['label'=>'تصویر موبایل','type'=>'image','folder'=>'hero'], 'display_location' => ['label'=>'محل نمایش','type'=>'select','options'=>['homepage'=>'صفحه اصلی','menu_page'=>'صفحه منو','campaigns_page'=>'صفحه کمپین‌ها','qr_menu'=>'منوی QR','customer_panel'=>'پنل مشتری']],
                'match_id' => ['label'=>'مسابقه','type'=>'match'], 'menu_item_id' => ['label'=>'آیتم منو','type'=>'menu_item'], 'category_id' => ['label'=>'دسته‌بندی','type'=>'category'], 'loyalty_campaign' => ['label'=>'کمپین وفاداری','type'=>'text'], 'display_order' => ['label'=>'ترتیب','type'=>'number'], 'active_status' => ['label'=>'فعال','type'=>'checkbox'], 'start_date' => ['label'=>'شروع نمایش','type'=>'datetime'], 'end_date' => ['label'=>'پایان نمایش','type'=>'datetime'],
            ],
            'columns' => ['id','title','subtitle','display_location','match_id','menu_item_id','category_id','display_order','active_status','start_date','end_date'],
        ],
    ];
}

function adminModuleDefinition(string $key): ?array {
    $modules = adminModuleDefinitions();
    return $modules[$key] ?? null;
}

function adminModuleLabel(array $config, string $column): string {
    return $config['fields'][$column]['label'] ?? [
        'id'=>'شناسه',
        'created_at'=>'ایجاد',
        'updated_at'=>'ویرایش',
        'match_title'=>'مسابقه',
        'category_title'=>'دسته‌بندی',
        'form_title'=>'فرم',
        'submitted_at'=>'ارسال',
        'prediction_count'=>'تعداد پیش‌بینی',
        'winner_count'=>'تعداد برنده',
        'prediction_team_one'=>'تیم اول',
        'prediction_team_two'=>'تیم دوم',
        'prediction_score_one'=>'گل پیش‌بینی تیم اول',
        'prediction_score_two'=>'گل پیش‌بینی تیم دوم',
        'final_score_one'=>'گل نهایی تیم اول',
        'final_score_two'=>'گل نهایی تیم دوم',
        'crm_status'=>'وضعیت CRM',
        'team_one_display'=>'تیم اول',
        'team_two_display'=>'تیم دوم',
        'match_start_display'=>'زمان مسابقه',
        'prediction_start_display'=>'شروع پیش‌بینی',
        'prediction_end_display'=>'پایان پیش‌بینی',
        'points_reward_display'=>'امتیاز پاداش',
        'user_phone'=>'موبایل کاربر',
        'user_full_name'=>'نام کاربر',
        'order_number'=>'شماره سفارش',
        'order_total'=>'مبلغ سفارش',
    ][$column] ?? $column;
}

function adminModuleRequiredTables(array $config): array {
    return array_values(array_unique(array_merge([$config['table']], $config['required_tables'] ?? [])));
}

function adminModuleExistingColumnNames(string $table): array {
    return array_map(static fn($row) => $row['Field'], schemaColumns($table));
}


function adminModuleColumnMeta(string $table, string $column): ?array {
    foreach (schemaColumns($table) as $row) {
        if (($row['Field'] ?? '') === $column) {
            return $row;
        }
    }
    return null;
}

function adminModuleSoftDeleteColumn(string $table): ?array {
    foreach (['is_active', 'active', 'active_status', 'is_available'] as $column) {
        if (adminColumnExists($table, $column)) {
            return [$column, 0];
        }
    }

    $status = adminModuleColumnMeta($table, 'status');
    $type = strtolower((string)($status['Type'] ?? ''));
    if ($type !== '') {
        if (preg_match("/'inactive'/", $type)) return ['status', 'inactive'];
        if (preg_match("/'archived'/", $type)) return ['status', 'archived'];
        if (preg_match("/'deleted'/", $type)) return ['status', 'deleted'];
    }

    return null;
}

function adminModuleConfigError(string $table): string {
    return 'Module form config is empty for ' . $table;
}

function adminModuleOptionalColumns(): array {
    return [
        'matches' => [
            'title' => "varchar(200) DEFAULT NULL",
            'description' => "text DEFAULT NULL",
            'rules' => "text DEFAULT NULL",
            'participation_conditions' => "text DEFAULT NULL",
            'team_one_name' => "varchar(120) DEFAULT NULL",
            'team_two_name' => "varchar(120) DEFAULT NULL",
            'team_one_logo' => "varchar(255) DEFAULT NULL",
            'team_two_logo' => "varchar(255) DEFAULT NULL",
            'final_team_one_score' => "int(11) DEFAULT NULL",
            'final_team_two_score' => "int(11) DEFAULT NULL",
            'final_result_status' => "varchar(50) DEFAULT NULL",
            'prediction_start_at' => "datetime DEFAULT NULL",
            'prediction_end_at' => "datetime DEFAULT NULL",
            'match_start_at' => "datetime DEFAULT NULL",
            'match_end_at' => "datetime DEFAULT NULL",
            'start_date' => "datetime DEFAULT NULL",
            'end_date' => "datetime DEFAULT NULL",
            'campaign_status' => "enum('active','inactive','archived') NOT NULL DEFAULT 'active'",
            'participant_count' => "int(11) NOT NULL DEFAULT 0",
            'banner_id' => "int(11) UNSIGNED DEFAULT NULL",
            'menu_item_id' => "int(11) UNSIGNED DEFAULT NULL",
            'campaign_target' => "varchar(150) DEFAULT NULL",
            'reward_title' => "varchar(200) DEFAULT NULL",
            'points_reward' => "int(11) NOT NULL DEFAULT 0",
            'reward_points' => "int(11) NOT NULL DEFAULT 0",
            'reward_description' => "text DEFAULT NULL",
        ],
        'predictions' => [
            'customer_id' => "int(11) UNSIGNED DEFAULT NULL",
            'customer_last_name' => "varchar(150) DEFAULT NULL",
            'customer_mobile' => "varchar(20) DEFAULT NULL",
            'team_one_name' => "varchar(120) DEFAULT NULL",
            'team_two_name' => "varchar(120) DEFAULT NULL",
            'predicted_team_one_score' => "tinyint UNSIGNED DEFAULT NULL",
            'predicted_team_two_score' => "tinyint UNSIGNED DEFAULT NULL",
            'prediction_content' => "text DEFAULT NULL",
            'status' => "enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
            'is_winner' => "tinyint(1) NOT NULL DEFAULT 0",
            'evaluated_at' => "datetime DEFAULT NULL",
            'points_awarded' => "int(11) NOT NULL DEFAULT 0",
            'crm_follow_up' => "tinyint(1) NOT NULL DEFAULT 0",
            'wants_reservation' => "tinyint(1) NOT NULL DEFAULT 0",
            'reserve_table_interest' => "tinyint(1) NOT NULL DEFAULT 0",
            'source' => "varchar(150) DEFAULT NULL",
            'ip_address' => "varchar(45) DEFAULT NULL",
            'user_agent' => "varchar(255) DEFAULT NULL",
            'submitted_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP",
        ],
        'menu_categories' => [
            'image' => "varchar(255) DEFAULT NULL",
            'parent_id' => "int(11) UNSIGNED DEFAULT NULL",
            'sepid_id' => "varchar(100) DEFAULT NULL",
            'visible_qr_menu' => "tinyint(1) NOT NULL DEFAULT 1",
            'visible_website' => "tinyint(1) NOT NULL DEFAULT 1",
            'visible_kiosk' => "tinyint(1) NOT NULL DEFAULT 1",
        ],
        'menu_items' => [
            'availability_status' => "enum('available','unavailable','limited') NOT NULL DEFAULT 'available'",
            'visible_qr_menu' => "tinyint(1) NOT NULL DEFAULT 1",
            'visible_website' => "tinyint(1) NOT NULL DEFAULT 1",
            'visible_kiosk' => "tinyint(1) NOT NULL DEFAULT 1",
            'visible_loyalty' => "tinyint(1) NOT NULL DEFAULT 1",
            'campaign_price' => "decimal(10,2) DEFAULT NULL",
            'promo_text' => "varchar(255) DEFAULT NULL",
            'promo_image' => "varchar(255) DEFAULT NULL",
            'sepid_id' => "varchar(100) DEFAULT NULL",
            'sepid_last_sync_at' => "datetime DEFAULT NULL",
        ],
        'dynamic_forms' => [
            'start_date' => "datetime DEFAULT NULL",
            'end_date' => "datetime DEFAULT NULL",
            'publishing_channels' => "varchar(255) DEFAULT NULL",
            'branch_id' => "int(11) UNSIGNED DEFAULT NULL",
            'survey_version' => "int(11) NOT NULL DEFAULT 1",
        ],
        'survey_responses' => [
            'branch_id' => "int(11) UNSIGNED DEFAULT NULL",
            'satisfaction_score' => "decimal(5,2) DEFAULT NULL",
            'is_dissatisfied' => "tinyint(1) NOT NULL DEFAULT 0",
            'crm_follow_up' => "tinyint(1) NOT NULL DEFAULT 0",
        ],
        'crm_customers' => [
            'customer_status' => "enum('new_customer','loyal_customer','vip','dissatisfied_customer','churn_risk') NOT NULL DEFAULT 'new_customer'",
            'points_balance' => "int(11) NOT NULL DEFAULT 0",
            'rewards_notes' => "text DEFAULT NULL",
            'follow_up_notes' => "text DEFAULT NULL",
        ],
        'hero_banners' => [
            'display_location' => "enum('homepage','menu_page','campaigns_page','qr_menu','customer_panel') NOT NULL DEFAULT 'homepage'",
            'match_id' => "int(11) UNSIGNED DEFAULT NULL",
            'menu_item_id' => "int(11) UNSIGNED DEFAULT NULL",
            'category_id' => "int(11) UNSIGNED DEFAULT NULL",
            'loyalty_campaign' => "varchar(150) DEFAULT NULL",
        ],
    ];
}

function adminEnsureModuleOptionalColumns(string $table): void {
    $columns = adminModuleOptionalColumns()[$table] ?? [];
    if (!$columns || !adminTableExists($table)) return;
    ensureTableColumns($table, $columns);
}

function adminEnsureModuleTables(array $config): void {
    $missing = [];
    foreach (adminModuleRequiredTables($config) as $table) {
        if (!adminTableExists($table)) $missing[] = $table;
    }
    if ($missing) ensureAdminCanonicalTables(adminDb(), $missing);
    foreach (adminModuleRequiredTables($config) as $table) {
        adminEnsureModuleOptionalColumns($table);
    }
}

function adminModulePrefix(array $config): string {
    return !empty($config['alias']) ? $config['alias'] . '.' : '';
}

function adminOptionRows(string $type): array {
    $queries = [
        'category' => ['table'=>'menu_categories', 'sql'=>'SELECT id, name_fa AS title FROM menu_categories ORDER BY sort_order, name_fa'],
        'match' => ['table'=>'matches', 'sql'=>"SELECT id, CONCAT(team_a, ' - ', team_b, ' (', match_date, ')') AS title FROM matches ORDER BY match_date DESC"],
        'survey_form' => ['table'=>'dynamic_forms', 'sql'=>'SELECT id, form_title_fa AS title FROM dynamic_forms ORDER BY display_order, id DESC'],
        'banner' => ['table'=>'hero_banners', 'sql'=>'SELECT id, title FROM hero_banners ORDER BY display_order, id DESC'],
        'menu_item' => ['table'=>'menu_items', 'sql'=>'SELECT id, name_fa AS title FROM menu_items ORDER BY sort_order, id DESC'],
    ];
    if (!isset($queries[$type]) || !adminTableExists($queries[$type]['table'])) return [];
    try { return adminDb()->query($queries[$type]['sql'])->fetchAll(); } catch (Throwable $e) { safeAdminLog('Option lookup failed: ' . $e->getMessage()); return []; }
}

function adminModuleNormalizeConfig(array $config): array {
    $allowed = adminModuleExistingColumnNames($config['table']);
    $config['fields'] = array_filter($config['fields'], static fn($field) => in_array($field, $allowed, true), ARRAY_FILTER_USE_KEY);
    $virtualColumns = ['match_title','category_title','form_title','prediction_count','winner_count','prediction_team_one','prediction_team_two','prediction_score_one','prediction_score_two','final_score_one','final_score_two','crm_status','team_one_display','team_two_display','match_start_display','prediction_start_display','prediction_end_display','points_reward_display','user_phone','user_full_name','order_number','order_total','predicted_scores'];
    $config['columns'] = array_values(array_filter($config['columns'], static fn($col) => in_array($col, $allowed, true) || in_array($col, $virtualColumns, true)));
    $config['filters'] = array_values(array_filter($config['filters'] ?? [], static fn($col) => in_array($col, $allowed, true)));
    $config['search'] = array_values(array_filter($config['search'] ?? [], static fn($col) => in_array($col, $allowed, true)));
    return $config;
}


function adminModuleUploadImage(string $field, string $folder, string $current = ''): string {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $current;
    }
    $validation = validateUploadedFile($_FILES[$field], ALLOWED_IMAGE_EXTENSIONS, ALLOWED_IMAGE_TYPES);
    if (!$validation['valid']) {
        throw new RuntimeException($validation['message']);
    }
    $dir = UPLOAD_PATH . '/' . trim($folder, '/');
    return optimizeUploadedImage($_FILES[$field]['tmp_name'], $dir, uniqid($field . '_', true));
}

function adminModuleImageSrc($value, string $folder): string {
    $value = ltrim((string)$value, '/');
    if ($value === '') return '';
    if (str_starts_with($value, 'uploads/')) return '../' . $value;
    return '../uploads/' . trim($folder, '/') . '/' . $value;
}

function adminModuleCollectData(array $config, array $current = [], ?array $admin = null): array {
    $data = [];
    foreach ($config['fields'] as $name => $meta) {
        if (!empty($meta['readonly'])) continue;
        $type = $meta['type'];
        if ($type === 'image') {
            $data[$name] = adminModuleUploadImage($name, $meta['folder'] ?? 'admin', (string)($current[$name] ?? ''));
            if (!empty($meta['required']) && empty($data[$name])) {
                throw new RuntimeException('فیلد «' . ($meta['label'] ?? $name) . '» الزامی است.');
            }
            continue;
        }
        if ($type === 'checkbox') {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }
        $value = $_POST[$name] ?? ($meta['default'] ?? null);
        if ($type === 'mobile') $value = normalizeMobile($value);
        if ($type === 'date') $value = parsePersianDate($value, false);
        if ($type === 'datetime') $value = parsePersianDate($value, true);
        if ($type === 'json' && $value !== null && trim((string)$value) !== '') {
            $decodedJson = json_decode((string)$value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('فیلد «' . ($meta['label'] ?? $name) . '» باید JSON معتبر باشد.');
            }
            $value = json_encode($decodedJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($type === 'number' && $value !== null && $value !== '' && !is_numeric($value)) {
            throw new RuntimeException('فیلد «' . ($meta['label'] ?? $name) . '» باید عددی باشد.');
        }
        if ($type === 'number' && $value === '') $value = null;
        $data[$name] = $value === '' ? null : $value;
        if (!empty($meta['required']) && ($data[$name] === null || $data[$name] === '')) {
            throw new RuntimeException('فیلد «' . ($meta['label'] ?? $name) . '» الزامی است.');
        }
    }
    if ($config['table'] === 'dynamic_forms' && !$current && adminColumnExists('dynamic_forms', 'created_by')) {
        $data['created_by'] = $admin['id'] ?? null;
    }
    return $data;
}

function adminSlugFromText($value): string {
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: '';
    return trim($slug, '-') ?: ('item-' . date('YmdHis'));
}

function adminDateTimeIsValid($value, bool $dateOnly = false): bool {
    if ($value === null || $value === '') return true;
    $format = $dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s';
    $dt = DateTime::createFromFormat($format, (string)$value);
    return $dt && $dt->format($format) === (string)$value;
}

function adminTimeIsValid($value): bool {
    if ($value === null || $value === '') return true;
    return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', (string)$value);
}

function adminAssertExists(string $table, int $id, string $label): void {
    if ($id <= 0) throw new RuntimeException('فیلد «' . $label . '» الزامی است.');
    $stmt = adminDb()->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('مقدار انتخاب‌شده برای «' . $label . '» معتبر نیست.');
}

function adminAssertUnique(string $table, string $column, $value, int $id, string $message): void {
    if ($value === null || $value === '') return;
    $stmt = adminDb()->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE `' . str_replace('`', '``', $column) . '` = :value AND id <> :id LIMIT 1');
    $stmt->execute(['value' => $value, 'id' => $id]);
    if ($stmt->fetchColumn()) throw new RuntimeException($message);
}

function adminValidateJsonString($value, string $label): void {
    if ($value === null || trim((string)$value) === '') return;
    json_decode((string)$value, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException('فیلد «' . $label . '» باید JSON معتبر باشد.');
}

function adminModulePrepareData(array $config, array $data, array $current = []): array {
    $table = $config['table'] ?? '';
    $id = (int)($current['id'] ?? 0);

    if ($table === 'matches') {
        if (empty($data['team_one_name']) && !empty($data['team_a'])) $data['team_one_name'] = $data['team_a'];
        if (empty($data['team_two_name']) && !empty($data['team_b'])) $data['team_two_name'] = $data['team_b'];
        if (empty($data['match_start_at']) && !empty($data['match_date']) && !empty($data['kickoff_time'])) $data['match_start_at'] = trim((string)$data['match_date'] . ' ' . (string)$data['kickoff_time']);
        if (empty($data['prediction_start_at']) && !empty($data['prediction_open_at'])) $data['prediction_start_at'] = $data['prediction_open_at'];
        if (empty($data['prediction_end_at']) && !empty($data['prediction_close_at'])) $data['prediction_end_at'] = $data['prediction_close_at'];
        foreach ([['final_team_one_score','final_score_team_a'], ['final_team_two_score','final_score_team_b'], ['points_reward','reward_points']] as [$a,$b]) {
            $value = $data[$a] ?? $data[$b] ?? null;
            if ($value !== null && $value !== '') { $data[$a] = $value; $data[$b] = $value; }
        }
        if (!adminDateTimeIsValid($data['match_date'] ?? null, true)) throw new RuntimeException('تاریخ مسابقه معتبر نیست.');
        if (!adminTimeIsValid($data['kickoff_time'] ?? null)) throw new RuntimeException('ساعت شروع مسابقه معتبر نیست.');
        if (!empty($data['prediction_open_at']) && !empty($data['prediction_close_at']) && strtotime($data['prediction_close_at']) <= strtotime($data['prediction_open_at'])) throw new RuntimeException('پایان پیش‌بینی باید بعد از شروع پیش‌بینی باشد.');
    }

    if ($table === 'predictions') {
        $mobile = $data['mobile'] ?: ($data['customer_mobile'] ?? null);
        if ($mobile) { $data['mobile'] = normalizeMobile($mobile); $data['customer_mobile'] = normalizeMobile($mobile); }
        foreach ([['predicted_team_one_score','predicted_score_team_a'], ['predicted_team_two_score','predicted_score_team_b'], ['wants_reservation','reserve_table_interest'], ['crm_match','crm_matched']] as [$a,$b]) {
            $value = $data[$a] ?? $data[$b] ?? null;
            if ($value !== null && $value !== '') { $data[$a] = $value; $data[$b] = $value; }
        }
        if (!empty($data['match_id'])) {
            adminAssertExists('matches', (int)$data['match_id'], 'مسابقه');
            $stmt = adminDb()->prepare('SELECT team_a, team_b, team_one_name, team_two_name FROM matches WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$data['match_id']]);
            $match = $stmt->fetch() ?: [];
            if (empty($data['team_one_name'])) $data['team_one_name'] = $match['team_one_name'] ?: ($match['team_a'] ?? null);
            if (empty($data['team_two_name'])) $data['team_two_name'] = $match['team_two_name'] ?: ($match['team_b'] ?? null);
        }
        if (empty($data['mobile'])) throw new RuntimeException('فیلد «موبایل» الزامی است.');
        if (($data['predicted_score_team_a'] ?? null) === null || ($data['predicted_score_team_a'] ?? '') === '') throw new RuntimeException('فیلد «گل تیم اول» الزامی است.');
        if (($data['predicted_score_team_b'] ?? null) === null || ($data['predicted_score_team_b'] ?? '') === '') throw new RuntimeException('فیلد «گل تیم دوم» الزامی است.');
        if (!empty($data['mobile']) && !empty($data['match_id'])) {
            $stmt = adminDb()->prepare('SELECT id FROM predictions WHERE mobile = :mobile AND match_id = :match_id AND id <> :id LIMIT 1');
            $stmt->execute(['mobile'=>$data['mobile'], 'match_id'=>(int)$data['match_id'], 'id'=>$id]);
            if ($stmt->fetchColumn()) throw new RuntimeException('برای این موبایل و مسابقه قبلاً پیش‌بینی ثبت شده است.');
        }
    }

    if ($table === 'menu_categories') {
        if (empty($data['slug'])) $data['slug'] = adminSlugFromText($data['name_en'] ?? '');
        adminAssertUnique('menu_categories', 'slug', $data['slug'] ?? null, $id, 'این اسلاگ قبلاً برای دسته‌بندی دیگری ثبت شده است.');
        if (!empty($data['parent_id']) && (int)$data['parent_id'] === $id) throw new RuntimeException('دسته‌بندی والد نمی‌تواند خود رکورد باشد.');
    }

    if ($table === 'menu_items') {
        if (empty($data['slug'])) $data['slug'] = adminSlugFromText($data['name_en'] ?? '');
        adminAssertUnique('menu_items', 'slug', $data['slug'] ?? null, $id, 'این اسلاگ قبلاً برای آیتم دیگری ثبت شده است.');
        adminAssertExists('menu_categories', (int)($data['category_id'] ?? 0), 'دسته‌بندی');
        adminValidateJsonString($data['gallery_images'] ?? null, 'گالری تصاویر JSON');
    }

    if ($table === 'dynamic_forms') {
        if (empty($data['form_schema'])) $data['form_schema'] = '{"fields":[]}';
        adminValidateJsonString($data['form_schema'], 'ساختار فرم JSON');
        if (!empty($data['start_date']) && !empty($data['end_date']) && strtotime($data['end_date']) <= strtotime($data['start_date'])) throw new RuntimeException('تاریخ پایان فرم باید بعد از تاریخ شروع باشد.');
    }

    if ($table === 'survey_responses') {
        adminAssertExists('dynamic_forms', (int)($data['form_id'] ?? 0), 'فرم');
        if (empty($data['response_data'])) $data['response_data'] = '{}';
        adminValidateJsonString($data['response_data'], 'داده پاسخ JSON');
    }

    if ($table === 'crm_customers') {
        foreach (['total_orders','total_purchase_volume','surveys_completed_count','points_balance'] as $column) if ($data[$column] === null || $data[$column] === '') $data[$column] = 0;
        adminAssertUnique('crm_customers', 'mobile', $data['mobile'] ?? null, $id, 'این شماره موبایل قبلاً در CRM ثبت شده است.');
    }

    if ($table === 'hero_banners') {
        if (!empty($data['start_date']) && !empty($data['end_date']) && strtotime($data['end_date']) <= strtotime($data['start_date'])) throw new RuntimeException('تاریخ پایان بنر باید بعد از تاریخ شروع باشد.');
    }

    if (!empty($config['unique'])) adminAssertUnique($table, $config['unique'], $data[$config['unique']] ?? null, $id, 'مقدار «' . adminModuleLabel($config, $config['unique']) . '» تکراری است.');

    $allowed = adminModuleExistingColumnNames($table);
    return array_intersect_key($data, array_flip($allowed));
}

function adminModuleSave(array $config, array $data, int $id = 0): int {
    $db = adminDb();
    if ($id > 0) {
        if (!$data) return $id;
        $sets = [];
        foreach ($data as $column => $value) $sets[] = '`' . str_replace('`', '``', $column) . '` = :' . $column;
        $data['id'] = $id;
        $db->prepare('UPDATE `' . str_replace('`', '``', $config['table']) . '` SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($data);
        return $id;
    }
    $columns = array_keys($data);
    $quoted = array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', $columns);
    $placeholders = array_map(static fn($column) => ':' . $column, $columns);
    $db->prepare('INSERT INTO `' . str_replace('`', '``', $config['table']) . '` (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', $placeholders) . ')')->execute($data);
    return (int)$db->lastInsertId();
}

function adminModuleDelete(array $config, int $id): string {
    $table = $config['table'];
    $softDelete = adminModuleSoftDeleteColumn($table);
    if ($softDelete !== null) {
        [$column, $value] = $softDelete;
        adminDb()->prepare('UPDATE `' . str_replace('`', '``', $table) . '` SET `' . str_replace('`', '``', $column) . '` = :value WHERE id = :id')->execute(['value' => $value, 'id' => $id]);
        return 'deactivated';
    }

    adminDb()->prepare('DELETE FROM `' . str_replace('`', '``', $table) . '` WHERE id = ?')->execute([$id]);
    return 'deleted';
}

function adminModuleFetchRow(array $config, int $id): ?array {
    $stmt = adminDb()->prepare('SELECT * FROM `' . str_replace('`', '``', $config['table']) . '` WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function adminModuleRows(array $config, int $perPage = 20): array {
    $db = adminDb();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    $base = $config['join'] ?? ('SELECT * FROM `' . str_replace('`', '``', $config['table']) . '`');
    $prefix = adminModulePrefix($config);
    $where = ['1=1']; $params = [];
    if (trim((string)($_GET['q'] ?? '')) !== '') {
        $ors = [];
        foreach ($config['search'] ?? [] as $column) $ors[] = $prefix . '`' . str_replace('`', '``', $column) . '` LIKE :q';
        if ($ors) { $where[] = '(' . implode(' OR ', $ors) . ')'; $params['q'] = '%' . trim((string)$_GET['q']) . '%'; }
    }
    foreach ($config['filters'] ?? [] as $filter) {
        if (isset($_GET[$filter]) && $_GET[$filter] !== '') { $where[] = $prefix . '`' . str_replace('`', '``', $filter) . '` = :' . $filter; $params[$filter] = $_GET[$filter]; }
    }
    $dateColumn = $config['date_column'] ?? null;
    if ($dateColumn && adminColumnExists($config['table'], $dateColumn)) {
        $dateMeta = adminModuleColumnMeta($config['table'], $dateColumn);
        $isDateOnly = strtolower((string)($dateMeta['Type'] ?? '')) === 'date';
        if (!empty($_GET['date_from'])) {
            $where[] = $prefix . '`' . str_replace('`', '``', $dateColumn) . '` >= :date_from';
            $parsedFrom = parsePersianDate($_GET['date_from'], false);
            $params['date_from'] = $isDateOnly ? $parsedFrom : $parsedFrom . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $where[] = $prefix . '`' . str_replace('`', '``', $dateColumn) . '` <= :date_to';
            $parsedTo = parsePersianDate($_GET['date_to'], false);
            $params['date_to'] = $isDateOnly ? $parsedTo : $parsedTo . ' 23:59:59';
        }
    }
    $whereSql = implode(' AND ', $where);
    $sort = (string)($_GET['sort'] ?? '');
    $allowedColumns = adminModuleExistingColumnNames($config['table']);
    $orderColumn = in_array($sort, $allowedColumns, true) ? $sort : (adminColumnExists($config['table'], 'id') ? 'id' : ($dateColumn ?: '1'));
    $orderDirection = strtolower((string)($_GET['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $stmt = $db->prepare($base . ' WHERE ' . $whereSql . ' ORDER BY ' . $prefix . '`' . str_replace('`', '``', $orderColumn) . '` ' . $orderDirection . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $count = $db->prepare('SELECT COUNT(*) AS total FROM (' . $base . ' WHERE ' . $whereSql . ') x');
    $count->execute($params);
    return ['rows'=>$rows, 'page'=>$page, 'perPage'=>$perPage, 'total'=>(int)($count->fetch()['total'] ?? 0)];
}

function adminModuleRenderField(string $name, array $meta, $value = null): void {
    $type = $meta['type'];
    if (in_array($type, ['date','datetime'], true) && $value) $value = formatJalaliDateTime($value, $type === 'datetime');
    echo '<div class="form-group"><label>' . h($meta['label']) . (!empty($meta['required']) ? ' *' : '') . '</label>';
    $required = !empty($meta['required']) ? 'required' : '';
    if ($type === 'textarea' || $type === 'json') {
        echo '<textarea class="form-control" name="' . h($name) . '" ' . $required . '>' . h($value ?? $meta['default'] ?? '') . '</textarea>';
    } elseif ($type === 'select') {
        echo '<select class="form-control" name="' . h($name) . '" ' . $required . '>';
        foreach (($meta['options'] ?? []) as $key => $label) echo '<option value="' . h($key) . '" ' . ((string)$value === (string)$key ? 'selected' : '') . '>' . h($label) . '</option>';
        echo '</select>';
    } elseif (in_array($type, ['category','match','survey_form','banner','menu_item'], true)) {
        echo '<select class="form-control" name="' . h($name) . '" ' . $required . '><option value="">انتخاب کنید</option>';
        foreach (adminOptionRows($type) as $option) echo '<option value="' . h($option['id']) . '" ' . ((string)$value === (string)$option['id'] ? 'selected' : '') . '>' . h($option['title']) . '</option>';
        echo '</select>';
    } elseif ($type === 'checkbox') {
        echo '<label><input type="checkbox" name="' . h($name) . '" value="1" ' . ($value ? 'checked' : '') . '> فعال</label>';
    } elseif ($type === 'image') {
        echo '<input class="form-control" type="file" name="' . h($name) . '" accept="image/*">';
        if ($value) echo '<p><img src="' . h(adminModuleImageSrc($value, $meta['folder'] ?? 'admin')) . '" style="max-width:140px;border-radius:8px"></p>';
    } else {
        $htmlType = $type === 'number' ? 'number' : ($type === 'time' ? 'time' : 'text');
        echo '<input class="form-control" type="' . h($htmlType) . '" name="' . h($name) . '" value="' . h($value ?? $meta['default'] ?? '') . '" ' . $required . '>';
    }
    if (in_array($type, ['date','datetime'], true)) echo '<small class="text-muted">فرمت شمسی: 1405/03/10' . ($type === 'datetime' ? ' 18:30' : '') . '</small>';
    echo '</div>';
}

function adminModuleFormatValue(string $column, $value): string {
    if ($value === null || $value === '') return '';
    if (str_ends_with($column, '_date')) return formatJalaliDateTime($value, false);
    if (str_ends_with($column, '_at') || $column === 'submitted_at') return formatJalaliDateTime($value, true);
    if ((string)$value === '1') return '✓';
    if ((string)$value === '0') return '✗';
    return (string)$value;
}


function adminModuleExportCsv(array $config): void {
    $data = adminModuleRows($config, 10000);
    $filename = $config['table'] . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, array_map(static fn($column) => adminModuleLabel($config, $column), $config['columns']));
    foreach ($data['rows'] as $row) {
        fputcsv($out, array_map(static fn($column) => adminModuleFormatValue($column, $row[$column] ?? ''), $config['columns']));
    }
    exit;
}

function adminRecalculatePredictionsForMatch(int $matchId): void {
    if (!adminTableExists('matches') || !adminTableExists('predictions')) return;
    $stmt = adminDb()->prepare('SELECT final_score_team_a, final_score_team_b, final_team_one_score, final_team_two_score, match_finished, reward_points, points_reward FROM matches WHERE id = ? LIMIT 1');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    $scoreOne = $match['final_team_one_score'] ?? $match['final_score_team_a'] ?? null;
    $scoreTwo = $match['final_team_two_score'] ?? $match['final_score_team_b'] ?? null;
    if (!$match || !(int)($match['match_finished'] ?? 0) || $scoreOne === null || $scoreTwo === null) return;

    $sets = [
        'is_correct_prediction = CASE WHEN COALESCE(predicted_team_one_score, predicted_score_team_a) = :a AND COALESCE(predicted_team_two_score, predicted_score_team_b) = :b THEN 1 ELSE 0 END',
    ];
    if (adminColumnExists('predictions', 'is_winner')) {
        $sets[] = 'is_winner = CASE WHEN COALESCE(predicted_team_one_score, predicted_score_team_a) = :a AND COALESCE(predicted_team_two_score, predicted_score_team_b) = :b THEN 1 ELSE 0 END';
    }
    if (adminColumnExists('predictions', 'evaluated_at')) {
        $sets[] = 'evaluated_at = NOW()';
    }
    $params = ['a' => $scoreOne, 'b' => $scoreTwo, 'id' => $matchId];
    if (adminColumnExists('predictions', 'points_awarded')) {
        $sets[] = 'points_awarded = CASE WHEN COALESCE(predicted_team_one_score, predicted_score_team_a) = :a AND COALESCE(predicted_team_two_score, predicted_score_team_b) = :b THEN :points ELSE 0 END';
        $params['points'] = (int)($match['points_reward'] ?? $match['reward_points'] ?? 0);
    }
    adminDb()->prepare('UPDATE predictions SET ' . implode(', ', $sets) . ' WHERE match_id = :id')
        ->execute($params);

    if (adminColumnExists('matches', 'final_result_status')) {
        adminDb()->prepare('UPDATE matches SET final_result_status = :status WHERE id = :id')->execute(['status' => 'evaluated', 'id' => $matchId]);
    }
}

function ensureAdminVisitorAnalyticsSchema(): void {
    $db = adminDb();
    $db->exec("CREATE TABLE IF NOT EXISTS `visitor_analytics_logs` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_id` varchar(64) NOT NULL,
        `user_id` int(11) UNSIGNED DEFAULT NULL,
        `customer_id` int(11) UNSIGNED DEFAULT NULL,
        `source_type` varchar(100) DEFAULT NULL,
        `source_name` varchar(150) DEFAULT NULL,
        `campaign_type` varchar(100) DEFAULT NULL,
        `entry_link` varchar(500) DEFAULT NULL,
        `referrer_url` varchar(500) DEFAULT NULL,
        `utm_source` varchar(100) DEFAULT NULL,
        `utm_medium` varchar(100) DEFAULT NULL,
        `utm_campaign` varchar(150) DEFAULT NULL,
        `landing_page` varchar(500) DEFAULT NULL,
        `current_page` varchar(500) DEFAULT NULL,
        `next_page` varchar(500) DEFAULT NULL,
        `related_module` varchar(100) DEFAULT NULL,
        `related_record_id` int(11) UNSIGNED DEFAULT NULL,
        `event_type` enum('external_entry','page_view','banner_view','banner_click','match_view','prediction_start','prediction_submit','category_view','menu_item_view','survey_view','survey_start','survey_submit','crm_link_entry','exit') NOT NULL DEFAULT 'page_view',
        `target_action` varchar(100) DEFAULT NULL,
        `device_type` varchar(50) DEFAULT NULL,
        `browser` varchar(100) DEFAULT NULL,
        `operating_system` varchar(100) DEFAULT NULL,
        `ip_address` varchar(64) DEFAULT NULL,
        `branch_id` int(11) UNSIGNED DEFAULT NULL,
        `is_new_visitor` tinyint(1) NOT NULL DEFAULT 0,
        `is_converted` tinyint(1) NOT NULL DEFAULT 0,
        `duration_seconds` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_visitor_logs_session` (`session_id`),
        KEY `idx_visitor_logs_source` (`source_type`, `source_name`),
        KEY `idx_visitor_logs_pages` (`landing_page`(191), `current_page`(191), `next_page`(191)),
        KEY `idx_visitor_logs_action` (`target_action`, `is_converted`),
        KEY `idx_visitor_logs_related` (`related_module`, `related_record_id`),
        KEY `idx_visitor_logs_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensureTableColumns('visitor_analytics_logs', [
        'campaign_type' => "varchar(100) DEFAULT NULL AFTER source_name",
        'entry_link' => "varchar(500) DEFAULT NULL AFTER campaign_type",
    ]);
    if (!adminIndexExists('visitor_analytics_logs', 'idx_visitor_logs_related')) {
        $db->exec('ALTER TABLE `visitor_analytics_logs` ADD INDEX `idx_visitor_logs_related` (`related_module`, `related_record_id`)');
    }
}

function adminVisitorAnalyticsSources(): array {
    return ['Instagram','Telegram','WhatsApp','SMS','Google Search','Google Maps','QR Code','CRM Campaign Link','Referral Website','Paid Ads','Direct Entry','Unknown'];
}

function adminModuleWriteActivity(?array $admin, string $action, array $config, int $entityId): void {
    if (!adminTableExists('activity_log')) return;
    try {
        $stmt = adminDb()->prepare('INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (:admin_id, :action, :entity_type, :entity_id, :description, :ip_address, :user_agent)');
        $stmt->execute([
            'admin_id' => $admin['id'] ?? null,
            'action' => $action,
            'entity_type' => $config['table'] ?? '',
            'entity_id' => $entityId,
            'description' => ($config['title'] ?? '') . ' #' . $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        safeAdminLog('Activity log failed: ' . $e->getMessage());
    }
}

function adminRenderModulePage(string $moduleKey): void {
    $config = adminModuleDefinition($moduleKey);
    if (!$config) {
        http_response_code(404);
        exit('Module not found.');
    }

    $currentAdmin = adminGuard($config['min_role'] ?? 'employee');
    ensureAdminSchema();
    $pageTitle = $config['title'];
    $message = '';
    $error = '';
    $action = $_GET['action'] ?? 'list';
    $editRow = null;

    try {
        adminEnsureModuleTables($config);
        $config = adminModuleNormalizeConfig($config);
        if (empty($config['fields'])) {
            throw new RuntimeException(adminModuleConfigError($config['table'] ?? 'unknown'));
        }
        if (($_GET['export'] ?? '') === 'csv') adminModuleExportCsv($config);
    } catch (Throwable $e) {
        $error = 'آماده‌سازی صفحه انجام نشد: ' . $e->getMessage();
        safeAdminLog('Admin module bootstrap failed (' . $moduleKey . '): ' . $e->getMessage());
        $action = 'list';
    }

    if (!in_array($action, ['list', 'add', 'edit'], true)) $action = 'list';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $crudAction = $_POST['crud_action'] ?? '';
        $postedId = (int)($_POST['id'] ?? 0);
        try {
            requireValidCsrf();
            if ($crudAction === 'delete') {
                $deleteMode = adminModuleDelete($config, $postedId);
                adminModuleWriteActivity($currentAdmin, $deleteMode === 'deactivated' ? 'deactivate' : 'delete', $config, $postedId);
                redirectTo(basename($_SERVER['PHP_SELF']) . ($deleteMode === 'deactivated' ? '?deactivated=1' : '?deleted=1'));
            }
            if ($crudAction === 'save') {
                $current = $postedId ? (adminModuleFetchRow($config, $postedId) ?: []) : [];
                $data = adminModuleCollectData($config, $current, $currentAdmin);
                $data = adminModulePrepareData($config, $data, $current);
                $savedId = adminModuleSave($config, $data, $postedId);
                if (($config['table'] ?? '') === 'matches') adminRecalculatePredictionsForMatch($savedId);
                adminModuleWriteActivity($currentAdmin, $postedId ? 'update' : 'create', $config, $savedId);
                redirectTo(basename($_SERVER['PHP_SELF']) . '?saved=1');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            if ($crudAction === 'save') {
                $action = $postedId ? 'edit' : 'add';
                $editRow = $postedId ? (adminModuleFetchRow($config, $postedId) ?: []) : [];
                foreach ($config['fields'] as $field => $meta) {
                    if (($meta['type'] ?? '') === 'checkbox') {
                        $editRow[$field] = isset($_POST[$field]) ? 1 : 0;
                    } elseif (isset($_POST[$field])) {
                        $editRow[$field] = $_POST[$field];
                    }
                }
                if ($postedId) $editRow['id'] = $postedId;
            }
        }
    }

    if ($action === 'edit' && !$editRow && !$error) {
        $editRow = adminModuleFetchRow($config, (int)($_GET['id'] ?? 0));
        if (!$editRow) {
            $error = 'رکورد مورد نظر یافت نشد.';
            $action = 'list';
        }
    }

    try {
        $data = ($action === 'list') ? adminModuleRows($config) : ['rows'=>[], 'page'=>1, 'perPage'=>20, 'total'=>0];
    } catch (Throwable $e) {
        $data = ['rows'=>[], 'page'=>1, 'perPage'=>20, 'total'=>0];
        $error = 'داده‌ها قابل نمایش نیستند. جزئیات خطا در لاگ سیستم ثبت شد.';
        safeAdminLog('Admin module rows failed (' . $moduleKey . '): ' . $e->getMessage());
    }

    include __DIR__ . '/../includes/header.php';
    ?>
    <?php if (!empty($_GET['saved'])): ?><div class="alert alert-info">تغییرات ذخیره شد.</div><?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?><div class="alert alert-info">رکورد حذف شد.</div><?php endif; ?>
    <?php if (!empty($_GET['deactivated'])): ?><div class="alert alert-info">رکورد غیرفعال شد.</div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

    <?php if (in_array($action, ['add', 'edit'], true)): ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="crud_action" value="save">
            <input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? 0); ?>">
            <div class="card">
                <div class="card-header"><h2><?php echo h($action === 'edit' ? 'ویرایش ' . $config['title'] : ($config['add_label'] ?? ('افزودن ' . $config['title']))); ?></h2></div>
                <div class="card-body">
                    <?php foreach ($config['fields'] as $field => $meta): ?>
                        <?php adminModuleRenderField($field, $meta, $editRow[$field] ?? null); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-success" type="submit">ذخیره</button>
            <a class="btn" href="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">بازگشت</a>
        </form>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2><?php echo h($config['title']); ?></h2>
                <div><a class="btn btn-primary" href="?action=add"><?php echo h($config['add_label'] ?? 'افزودن'); ?></a> <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>">خروجی CSV</a></div>
            </div>
            <div class="card-body">
                <form class="admin-filter" method="get">
                    <input class="form-control" name="q" placeholder="جستجو" value="<?php echo h($_GET['q'] ?? ''); ?>">
                    <input class="form-control" name="date_from" placeholder="از تاریخ" value="<?php echo h($_GET['date_from'] ?? ''); ?>">
                    <input class="form-control" name="date_to" placeholder="تا تاریخ" value="<?php echo h($_GET['date_to'] ?? ''); ?>">
                    <?php foreach (($config['filters'] ?? []) as $filter): $field = $config['fields'][$filter] ?? ['label' => adminModuleLabel($config, $filter), 'type' => 'checkbox']; $value = $_GET[$filter] ?? ''; ?>
                        <?php if (($field['type'] ?? '') === 'select'): ?>
                            <select class="form-control" name="<?php echo h($filter); ?>"><option value=""><?php echo h($field['label']); ?></option><?php foreach (($field['options'] ?? []) as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)$value === (string)$key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                        <?php elseif (in_array(($field['type'] ?? ''), ['category','match','survey_form','banner','menu_item'], true)): ?>
                            <select class="form-control" name="<?php echo h($filter); ?>"><option value=""><?php echo h($field['label']); ?></option><?php foreach (adminOptionRows($field['type']) as $option): ?><option value="<?php echo h($option['id']); ?>" <?php echo (string)$value === (string)$option['id'] ? 'selected' : ''; ?>><?php echo h($option['title']); ?></option><?php endforeach; ?></select>
                        <?php else: ?>
                            <select class="form-control" name="<?php echo h($filter); ?>"><option value=""><?php echo h(adminModuleLabel($config, $filter)); ?></option><option value="1" <?php echo (string)$value === '1' ? 'selected' : ''; ?>>بله</option><option value="0" <?php echo (string)$value === '0' ? 'selected' : ''; ?>>خیر</option></select>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button class="btn btn-primary" type="submit">فیلتر</button>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><?php foreach ($config['columns'] as $column): ?><th><a href="?<?php echo h(http_build_query(array_merge($_GET, ['sort' => $column, 'order' => (($_GET['sort'] ?? '') === $column && ($_GET['order'] ?? 'desc') === 'desc') ? 'asc' : 'desc']))); ?>"><?php echo h(adminModuleLabel($config, $column)); ?></a></th><?php endforeach; ?><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($data['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($config['columns'] as $column): ?><td><?php echo h(adminModuleFormatValue($column, $row[$column] ?? '')); ?></td><?php endforeach; ?>
                                <td>
                                    <a class="btn btn-sm btn-info" href="?action=edit&id=<?php echo h($row['id']); ?>">ویرایش</a>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                        <input type="hidden" name="crud_action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('آیا مطمئنید؟')">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$data['rows']): ?><tr><td colspan="<?php echo count($config['columns']) + 1; ?>" class="text-center text-muted">رکوردی یافت نشد.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $pages = max(1, (int)ceil($data['total'] / $data['perPage'])); ?>
                <p class="text-muted">صفحه <?php echo h($data['page']); ?> از <?php echo h($pages); ?> (کل: <?php echo h($data['total']); ?> رکورد)</p>
                <?php if ($data['page'] > 1): ?><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => 1]))); ?>">اول</a> <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => $data['page'] - 1]))); ?>">قبلی</a><?php endif; ?>
                <?php if ($data['page'] < $pages): ?><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => $data['page'] + 1]))); ?>">بعدی</a> <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => $pages]))); ?>">آخر</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <?php
}
