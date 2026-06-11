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

function adminResolveSchemaHelperArgs(array $args, int $expectedWithoutDb, string $helperName): ?array {
    if (count($args) === $expectedWithoutDb + 1 && $args[0] instanceof PDO) {
        return array_merge([$args[0]], array_map('strval', array_slice($args, 1)));
    }
    if (count($args) === $expectedWithoutDb) {
        return array_merge([adminDb()], array_map('strval', $args));
    }

    safeAdminLog($helperName . ' called with invalid arguments.');
    return null;
}

function adminTableExists(...$args): bool {
    try {
        $resolved = adminResolveSchemaHelperArgs($args, 1, 'adminTableExists');
        if ($resolved === null) {
            return false;
        }

        [$db, $table] = $resolved;
        $table = trim((string)$table);
        if ($table === '') {
            return false;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        safeAdminLog('adminTableExists failed: ' . $e->getMessage());
        return false;
    }
}

function adminColumnExists(...$args): bool {
    try {
        $resolved = adminResolveSchemaHelperArgs($args, 2, 'adminColumnExists');
        if ($resolved === null) {
            return false;
        }

        [$db, $table, $column] = $resolved;
        $table = trim((string)$table);
        $column = trim((string)$column);

        if ($table === '' || $column === '') {
            return false;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        safeAdminLog('adminColumnExists failed: ' . $e->getMessage());
        return false;
    }
}


function adminIndexExists(...$args): bool {
    try {
        $resolved = adminResolveSchemaHelperArgs($args, 2, 'adminIndexExists');
        if ($resolved === null) {
            return false;
        }

        [$db, $table, $index] = $resolved;
        $table = trim((string)$table);
        $index = trim((string)$index);

        if ($table === '' || $index === '') {
            return false;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ");
        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        safeAdminLog('adminIndexExists failed: ' . $e->getMessage());
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

function adminEnsureIndex(string $table, string $index, string $columns): bool {
    if (!adminTableExists($table) || adminIndexExists($table, $index)) {
        return true;
    }

    try {
        adminDb()->exec("ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD INDEX `{$index}` {$columns}");
        return true;
    } catch (Throwable $e) {
        safeAdminLog("Schema index repair failed for {$table}.{$index}: " . $e->getMessage());
        return false;
    }
}

function adminRepairResult(string $table, array $requiredColumns, array $changes): array {
    $existing = adminModuleExistingColumnNames($table);
    $missing = array_values(array_diff(array_keys($requiredColumns), $existing));
    if ($missing) {
        safeAdminLog(ucfirst($table) . ' schema repair incomplete. Missing columns: ' . implode(', ', $missing));
    }
    return [
        'ok' => !$missing && adminTableExists($table),
        'changes' => $changes,
        'missing_after_repair' => $missing,
    ];
}

function adminMatchesColumnDefinitions(): array {
    return [
        'title' => 'varchar(200) DEFAULT NULL',
        'description' => 'text DEFAULT NULL',
        'rules' => 'text DEFAULT NULL',
        'participation_conditions' => 'text DEFAULT NULL',
        'team_a' => 'varchar(120) DEFAULT NULL',
        'team_b' => 'varchar(120) DEFAULT NULL',
        'team_one_name' => 'varchar(120) DEFAULT NULL',
        'team_two_name' => 'varchar(120) DEFAULT NULL',
        'team_one_logo' => 'varchar(255) DEFAULT NULL',
        'team_two_logo' => 'varchar(255) DEFAULT NULL',
        'match_date' => 'date DEFAULT NULL',
        'kickoff_time' => 'time DEFAULT NULL',
        'broadcast_time' => 'time DEFAULT NULL',
        'final_score_team_a' => 'int(11) DEFAULT NULL',
        'final_score_team_b' => 'int(11) DEFAULT NULL',
        'final_team_one_score' => 'int(11) DEFAULT NULL',
        'final_team_two_score' => 'int(11) DEFAULT NULL',
        'final_result_status' => 'varchar(50) DEFAULT NULL',
        'match_finished' => 'tinyint(1) NOT NULL DEFAULT 0',
        'prediction_open_at' => 'datetime DEFAULT NULL',
        'prediction_close_at' => 'datetime DEFAULT NULL',
        'prediction_start_at' => 'datetime DEFAULT NULL',
        'prediction_end_at' => 'datetime DEFAULT NULL',
        'match_start_at' => 'datetime DEFAULT NULL',
        'match_end_at' => 'datetime DEFAULT NULL',
        'start_date' => 'datetime DEFAULT NULL',
        'end_date' => 'datetime DEFAULT NULL',
        'status' => "varchar(50) NOT NULL DEFAULT 'active'",
        'campaign_status' => "varchar(50) NOT NULL DEFAULT 'active'",
        'participant_count' => 'int(11) NOT NULL DEFAULT 0',
        'banner_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'menu_item_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'campaign_target' => 'varchar(150) DEFAULT NULL',
        'reward_title' => 'varchar(200) DEFAULT NULL',
        'points_reward' => 'int(11) NOT NULL DEFAULT 0',
        'reward_points' => 'int(11) NOT NULL DEFAULT 0',
        'reward_description' => 'text DEFAULT NULL',
        'is_active' => 'tinyint(1) NOT NULL DEFAULT 1',
        'active_for_prediction' => 'tinyint(1) NOT NULL DEFAULT 1',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];
}

function adminPredictionsColumnDefinitions(): array {
    return [
        'customer_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'customer_name' => 'varchar(150) DEFAULT NULL',
        'customer_last_name' => 'varchar(150) DEFAULT NULL',
        'customer_mobile' => 'varchar(20) DEFAULT NULL',
        'mobile' => 'varchar(20) DEFAULT NULL',
        'match_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'team_one_name' => 'varchar(120) DEFAULT NULL',
        'team_two_name' => 'varchar(120) DEFAULT NULL',
        'predicted_team_one_score' => 'tinyint UNSIGNED DEFAULT NULL',
        'predicted_team_two_score' => 'tinyint UNSIGNED DEFAULT NULL',
        'predicted_score_team_a' => 'tinyint UNSIGNED DEFAULT NULL',
        'predicted_score_team_b' => 'tinyint UNSIGNED DEFAULT NULL',
        'prediction_content' => 'text DEFAULT NULL',
        'status' => "varchar(50) NOT NULL DEFAULT 'pending'",
        'is_winner' => 'tinyint(1) NOT NULL DEFAULT 0',
        'evaluated_at' => 'datetime DEFAULT NULL',
        'points_awarded' => 'int(11) NOT NULL DEFAULT 0',
        'crm_follow_up' => 'tinyint(1) NOT NULL DEFAULT 0',
        'wants_reservation' => 'tinyint(1) NOT NULL DEFAULT 0',
        'reserve_table_interest' => 'tinyint(1) NOT NULL DEFAULT 0',
        'source' => 'varchar(150) DEFAULT NULL',
        'ip_address' => 'varchar(45) DEFAULT NULL',
        'user_agent' => 'varchar(255) DEFAULT NULL',
        'crm_matched' => 'tinyint(1) NOT NULL DEFAULT 0',
        'customer_exists' => 'tinyint(1) NOT NULL DEFAULT 0',
        'attended_match_time' => 'tinyint(1) NOT NULL DEFAULT 0',
        'is_correct_prediction' => 'tinyint(1) NOT NULL DEFAULT 0',
        'crm_match' => 'tinyint(1) NOT NULL DEFAULT 0',
        'attended_match' => 'tinyint(1) NOT NULL DEFAULT 0',
        'submitted_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];
}

function adminHeroBannersColumnDefinitions(): array {
    return [
        'title' => 'varchar(200) DEFAULT NULL',
        'subtitle' => 'varchar(255) DEFAULT NULL',
        'description' => 'text DEFAULT NULL',
        'button_text' => 'varchar(100) DEFAULT NULL',
        'button_link' => 'varchar(255) DEFAULT NULL',
        'image' => 'varchar(255) DEFAULT NULL',
        'mobile_image' => 'varchar(255) DEFAULT NULL',
        'display_location' => "varchar(50) NOT NULL DEFAULT 'homepage'",
        'match_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'menu_item_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'category_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'loyalty_campaign' => 'varchar(150) DEFAULT NULL',
        'display_order' => 'int(11) NOT NULL DEFAULT 0',
        'active_status' => 'tinyint(1) NOT NULL DEFAULT 1',
        'start_date' => 'datetime DEFAULT NULL',
        'end_date' => 'datetime DEFAULT NULL',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];
}

function adminCreateTableIfMissing(string $table, array $columns): bool {
    if (adminTableExists($table)) {
        return true;
    }

    $parts = ['`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT'];
    foreach ($columns as $column => $definition) {
        $parts[] = '`' . str_replace('`', '``', $column) . '` ' . $definition;
    }
    $parts[] = 'PRIMARY KEY (`id`)';

    try {
        adminDb()->exec("CREATE TABLE IF NOT EXISTS `" . str_replace('`', '``', $table) . "` (\n  " . implode(",\n  ", $parts) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    } catch (Throwable $e) {
        safeAdminLog("Critical table create failed for {$table}: " . $e->getMessage());
        return false;
    }
}

function adminRepairMatchesSchema(): array {
    $changes = [];
    $columns = adminMatchesColumnDefinitions();
    if (!adminTableExists('matches')) {
        if (adminCreateTableIfMissing('matches', $columns)) {
            $changes[] = 'created matches table';
        }
    }
    if (!adminTableExists('matches')) {
        return ['ok' => false, 'changes' => $changes, 'missing_after_repair' => array_keys($columns)];
    }

    ensureTableColumns('matches', $columns);
    foreach ([
        'idx_matches_date' => '(`match_date`)',
        'idx_matches_status' => '(`status`)',
        'idx_matches_prediction_start' => '(`prediction_start_at`)',
        'idx_matches_prediction_end' => '(`prediction_end_at`)',
        'idx_matches_match_start' => '(`match_start_at`)',
        'idx_matches_finished' => '(`match_finished`)',
        'idx_matches_active_status' => '(`is_active`, `active_for_prediction`, `status`)',
    ] as $index => $definition) {
        if (adminEnsureIndex('matches', $index, $definition)) {
            $changes[] = "ensured index {$index}";
        }
    }
    return adminRepairResult('matches', $columns, $changes);
}

function adminRepairPredictionsSchema(): array {
    $changes = [];
    $columns = adminPredictionsColumnDefinitions();
    if (!adminTableExists('predictions')) {
        if (adminCreateTableIfMissing('predictions', $columns)) {
            $changes[] = 'created predictions table';
        }
    }
    if (!adminTableExists('predictions')) {
        return ['ok' => false, 'changes' => $changes, 'missing_after_repair' => array_keys($columns)];
    }

    ensureTableColumns('predictions', $columns);
    foreach ([
        'idx_predictions_mobile' => '(`mobile`)',
        'idx_predictions_customer_mobile' => '(`customer_mobile`)',
        'idx_predictions_customer_id' => '(`customer_id`)',
        'idx_predictions_match' => '(`match_id`)',
        'idx_predictions_winner' => '(`is_winner`)',
        'idx_predictions_created_at' => '(`created_at`)',
        'idx_predictions_submitted_at' => '(`submitted_at`)',
    ] as $index => $definition) {
        if (adminEnsureIndex('predictions', $index, $definition)) {
            $changes[] = "ensured index {$index}";
        }
    }
    return adminRepairResult('predictions', $columns, $changes);
}

function adminRepairHeroBannersSchema(): array {
    $changes = [];
    $columns = adminHeroBannersColumnDefinitions();
    if (!adminTableExists('hero_banners')) {
        if (adminCreateTableIfMissing('hero_banners', $columns)) {
            $changes[] = 'created hero_banners table';
        }
    }
    if (!adminTableExists('hero_banners')) {
        return ['ok' => false, 'changes' => $changes, 'missing_after_repair' => array_keys($columns)];
    }

    ensureTableColumns('hero_banners', $columns);
    foreach ([
        'idx_hero_active_order' => '(`active_status`, `display_order`)',
        'idx_hero_start_end' => '(`start_date`, `end_date`)',
        'idx_hero_display_location' => '(`display_location`)',
    ] as $index => $definition) {
        if (adminEnsureIndex('hero_banners', $index, $definition)) {
            $changes[] = "ensured index {$index}";
        }
    }
    try {
        adminDb()->exec("UPDATE `hero_banners` SET `display_location` = 'homepage' WHERE `display_location` IS NULL OR `display_location` = ''");
        $changes[] = 'normalized empty display_location';
    } catch (Throwable $e) {
        safeAdminLog('Hero banner display_location repair failed: ' . $e->getMessage());
    }
    return adminRepairResult('hero_banners', $columns, $changes);
}

function adminRepairCriticalModuleSchema(array $config): array {
    $table = (string)($config['table'] ?? '');
    if ($table === 'matches') {
        return adminRepairMatchesSchema();
    }
    if ($table === 'predictions') {
        return adminRepairPredictionsSchema();
    }
    if ($table === 'hero_banners') {
        return adminRepairHeroBannersSchema();
    }
    return ['ok' => true, 'changes' => [], 'missing_after_repair' => []];
}

function adminEnsureMatchesSchema(): void {
    adminRepairMatchesSchema();
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
        if (!adminColumnExists('crm_customers', 'email')) {
            $run("ALTER TABLE `crm_customers` ADD COLUMN `email` varchar(150) DEFAULT NULL AFTER `mobile`", 'افزودن email به CRM');
        }
        if (!adminIndexExists('crm_customers', 'idx_crm_email')) {
            $run("ALTER TABLE `crm_customers` ADD INDEX `idx_crm_email` (`email`)", 'ایندکس ایمیل CRM');
        }
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

    adminEnsureMatchesSchema();

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
        `pool_name` varchar(100) DEFAULT NULL,
        `acquisition_source` varchar(100) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `status` enum('new','contacted','converted','rejected') NOT NULL DEFAULT 'new',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pool_mobile` (`mobile`),
        KEY `idx_pool_leads_pool_name` (`pool_name`),
        KEY `idx_pool_source` (`acquisition_source`),
        KEY `idx_pool_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table pool_leads');

    if (adminTableExists('pool_leads')) {
        ensureTableColumns('pool_leads', [
            'pool_name' => 'varchar(100) DEFAULT NULL AFTER `mobile`',
        ]);
        if (!adminIndexExists('pool_leads', 'idx_pool_leads_pool_name')) {
            $run("ALTER TABLE `pool_leads` ADD INDEX `idx_pool_leads_pool_name` (`pool_name`)", 'ایندکس استخر لیدها');
        }
    }

    $run("CREATE TABLE IF NOT EXISTS `traffic_logs` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_id` varchar(64) NOT NULL,
        `ip_address` varchar(64) DEFAULT NULL,
        `country` varchar(100) DEFAULT 'Unknown',
        `city` varchar(100) DEFAULT 'Unknown',
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
        `source_type` varchar(50) NOT NULL DEFAULT 'unknown',
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
        `ip_address` varchar(64) DEFAULT NULL,
        `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `current_page` varchar(500) DEFAULT NULL,
        `source_name` varchar(150) DEFAULT NULL,
        `device_type` varchar(50) DEFAULT NULL,
        `browser` varchar(100) DEFAULT NULL,
        `os` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_session` (`session_id`),
        KEY `idx_session_active` (`is_active`, `last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table visitor_sessions');

    $run("CREATE TABLE IF NOT EXISTS `visitor_locations` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `country` varchar(100) NOT NULL DEFAULT 'Unknown',
        `city` varchar(100) NOT NULL DEFAULT 'Unknown',
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

    ensureAdminAnalyticsSchema();

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
        'crm' => [
            'title' => 'CRM مشتریان', 'min_role' => 'manager', 'table' => 'crm_customers', 'unique' => 'mobile',
            'search' => ['full_name','mobile','email','tags','acquisition_source'], 'filters' => ['acquisition_source','customer_status','attended_match_event'], 'date_column' => 'created_at',
            'fields' => [
                'full_name' => ['label'=>'نام کامل','type'=>'text','required'=>true],
                'mobile' => ['label'=>'موبایل','type'=>'mobile','required'=>true],
                'email' => ['label'=>'ایمیل','type'=>'text'],
                'birth_date' => ['label'=>'تولد','type'=>'date'],
                'first_purchase_date' => ['label'=>'اولین خرید','type'=>'date'],
                'total_orders' => ['label'=>'تعداد سفارش','type'=>'number'],
                'total_purchase_volume' => ['label'=>'حجم خرید','type'=>'number'],
                'reminder_date' => ['label'=>'یادآوری','type'=>'date'],
                'acquisition_source' => ['label'=>'منبع جذب','type'=>'select','options'=>adminAcquisitionSourceOptions()],
                'notes' => ['label'=>'یادداشت','type'=>'textarea'],
                'surveys_completed_count' => ['label'=>'تعداد نظرسنجی','type'=>'number'],
                'last_visit_date' => ['label'=>'آخرین مراجعه','type'=>'date'],
                'tags' => ['label'=>'برچسب‌ها','type'=>'text'],
                'customer_status' => ['label'=>'وضعیت مشتری','type'=>'select','options'=>['new_customer'=>'مشتری جدید','loyal_customer'=>'وفادار','vip'=>'VIP','dissatisfied_customer'=>'ناراضی','churn_risk'=>'ریسک ریزش']],
                'points_balance' => ['label'=>'امتیازها','type'=>'number'],
                'rewards_notes' => ['label'=>'یادداشت پاداش‌ها','type'=>'textarea'],
                'follow_up_notes' => ['label'=>'یادداشت پیگیری اپراتور','type'=>'textarea'],
                'attended_match_event' => ['label'=>'حضور در رویداد مسابقه','type'=>'checkbox'],
            ],
            'columns' => ['id','full_name','mobile','email','customer_status','points_balance','total_orders','total_purchase_volume','acquisition_source','attended_match_event','tags','created_at'],
        ],
        'matches' => [
            'title' => 'مدیریت مسابقات', 'min_role' => 'manager', 'table' => 'matches', 'search' => ['title','team_a','team_b','team_one_name','team_two_name'], 'filters' => ['status','is_active','active_for_prediction','match_finished'], 'date_column' => 'match_start_at',
            'join' => 'SELECT m.*, COALESCE(m.team_one_name, m.team_a) AS team_one_display, COALESCE(m.team_two_name, m.team_b) AS team_two_display, COALESCE(m.match_start_at, CONCAT(m.match_date, " ", m.kickoff_time)) AS match_start_display, COALESCE(m.prediction_start_at, m.prediction_open_at) AS prediction_start_display, COALESCE(m.prediction_end_at, m.prediction_close_at) AS prediction_end_display, COALESCE(m.points_reward, m.reward_points, 0) AS points_reward_display, (SELECT COUNT(*) FROM predictions p WHERE p.match_id = m.id) AS prediction_count, (SELECT COUNT(*) FROM predictions p WHERE p.match_id = m.id AND COALESCE(p.is_winner, p.is_correct_prediction, 0) = 1) AS winner_count FROM matches m', 'alias' => 'm', 'required_tables' => ['predictions'],
            'fields' => [
                'title' => ['label'=>'عنوان کمپین/مسابقه','type'=>'text'], 'description' => ['label'=>'توضیح','type'=>'textarea'], 'rules' => ['label'=>'قوانین','type'=>'textarea'], 'participation_conditions' => ['label'=>'شرایط مشارکت','type'=>'textarea'],
                'team_a' => ['label'=>'نام تیم اول','type'=>'text','required'=>true], 'team_b' => ['label'=>'نام تیم دوم','type'=>'text','required'=>true],
                'team_one_logo' => ['label'=>'لوگو/تصویر تیم اول','type'=>'image','folder'=>'matches'], 'team_two_logo' => ['label'=>'لوگو/تصویر تیم دوم','type'=>'image','folder'=>'matches'],
                'match_start_at' => ['label'=>'زمان شروع مسابقه','type'=>'datetime','required'=>true], 'match_end_at' => ['label'=>'زمان پایان مسابقه','type'=>'datetime'],
                'prediction_start_at' => ['label'=>'شروع پیش‌بینی','type'=>'datetime','required'=>true], 'prediction_end_at' => ['label'=>'پایان پیش‌بینی','type'=>'datetime','required'=>true],
                'status' => ['label'=>'وضعیت مسابقه','type'=>'select','options'=>['active'=>'فعال','inactive'=>'غیرفعال','archived'=>'آرشیو','scheduled'=>'برنامه‌ریزی شده','live'=>'زنده','finished'=>'تمام شده','cancelled'=>'لغو شده']],
                'reward_title' => ['label'=>'عنوان پاداش','type'=>'text'], 'points_reward' => ['label'=>'امتیاز پاداش','type'=>'number'], 'reward_description' => ['label'=>'شرح پاداش','type'=>'textarea'],
                'is_active' => ['label'=>'فعال','type'=>'checkbox'], 'active_for_prediction' => ['label'=>'فعال برای پیش‌بینی','type'=>'checkbox'],
                'final_team_one_score' => ['label'=>'نتیجه نهایی تیم اول','type'=>'number'], 'final_team_two_score' => ['label'=>'نتیجه نهایی تیم دوم','type'=>'number'], 'match_finished' => ['label'=>'مسابقه تمام شده','type'=>'checkbox'],
            ],
            'columns' => ['id','title','team_one_display','team_two_display','match_start_display','prediction_start_display','prediction_end_display','status','points_reward_display','prediction_count','winner_count','match_finished','created_at'],
        ],
        'predictions' => [
            'title' => 'پیش‌بینی‌ها', 'min_role' => 'manager', 'table' => 'predictions', 'readonly_create' => true, 'allow_delete' => false, 'search' => ['customer_name','customer_last_name','mobile','customer_mobile'], 'filters' => ['match_id','status','is_winner','wants_reservation','customer_exists','crm_match'], 'date_column' => 'submitted_at',
            'filter_fields' => [
                'match_id' => ['label'=>'مسابقه','type'=>'match'],
            ],
            'fields' => [
                'status' => ['label'=>'وضعیت','type'=>'select','options'=>['pending'=>'در انتظار','approved'=>'تایید','rejected'=>'رد']], 'is_winner' => ['label'=>'برنده','type'=>'checkbox'], 'points_awarded' => ['label'=>'امتیاز اعطا شده','type'=>'number'], 'wants_reservation' => ['label'=>'علاقه‌مند به رزرو','type'=>'checkbox'], 'crm_follow_up' => ['label'=>'ارسال به CRM','type'=>'checkbox'],
                'crm_match' => ['label'=>'تطابق CRM','type'=>'checkbox'], 'attended_match' => ['label'=>'حضور در مسابقه','type'=>'checkbox'], 'is_correct_prediction' => ['label'=>'پیش‌بینی صحیح','type'=>'checkbox'],
            ],
            'join' => 'SELECT p.*, CONCAT(COALESCE(m.team_one_name, m.team_a), " - ", COALESCE(m.team_two_name, m.team_b)) AS match_title, COALESCE(p.team_one_name, m.team_one_name, m.team_a) AS prediction_team_one, COALESCE(p.team_two_name, m.team_two_name, m.team_b) AS prediction_team_two, COALESCE(p.predicted_team_one_score, p.predicted_score_team_a) AS prediction_score_one, COALESCE(p.predicted_team_two_score, p.predicted_score_team_b) AS prediction_score_two, COALESCE(m.final_team_one_score, m.final_score_team_a) AS final_score_one, COALESCE(m.final_team_two_score, m.final_score_team_b) AS final_score_two, CASE WHEN c.id IS NULL THEN "missing" ELSE "exists" END AS crm_status FROM predictions p LEFT JOIN matches m ON p.match_id = m.id LEFT JOIN crm_customers c ON c.mobile = COALESCE(p.customer_mobile, p.mobile)', 'alias' => 'p', 'required_tables' => ['matches','crm_customers'],
            'columns' => ['id','match_title','prediction_team_one','prediction_team_two','prediction_score_one','prediction_score_two','final_score_one','final_score_two','customer_name','customer_last_name','customer_mobile','wants_reservation','is_winner','crm_status','submitted_at'],
        ],
        'banners' => [
            'title' => 'بنرهای اصلی', 'min_role' => 'manager', 'table' => 'hero_banners', 'search' => ['title','subtitle'], 'filters' => ['active_status'], 'date_column' => 'created_at',
            'fields' => [
                'title' => ['label'=>'عنوان','type'=>'text','required'=>true], 'subtitle' => ['label'=>'زیرعنوان','type'=>'text'], 'description' => ['label'=>'توضیح','type'=>'textarea'],
                'button_text' => ['label'=>'متن دکمه','type'=>'text'], 'button_link' => ['label'=>'لینک دکمه','type'=>'text'], 'image' => ['label'=>'تصویر','type'=>'image','folder'=>'banners'], 'mobile_image' => ['label'=>'تصویر موبایل','type'=>'image','folder'=>'banners'],
                'display_order' => ['label'=>'ترتیب','type'=>'number'], 'active_status' => ['label'=>'فعال','type'=>'checkbox'], 'start_date' => ['label'=>'شروع','type'=>'datetime'], 'end_date' => ['label'=>'پایان','type'=>'datetime'],
            ],
            'columns' => ['id','title','image','display_order','active_status','start_date','end_date','created_at'],
        ],
        'categories' => [
            'title' => 'فیلترها و دسته‌بندی منو', 'min_role' => 'manager', 'table' => 'menu_categories', 'unique' => 'slug', 'search' => ['name_fa','name_en','slug'], 'filters' => ['is_active','visible_qr_menu','visible_website','visible_kiosk'], 'date_column' => 'created_at',
            'fields' => [
                'name_fa' => ['label'=>'نام فارسی','type'=>'text','required'=>true], 'name_en' => ['label'=>'نام انگلیسی','type'=>'text','required'=>true], 'slug' => ['label'=>'اسلاگ','type'=>'text','required'=>true],
                'description_fa' => ['label'=>'توضیح فارسی','type'=>'textarea'], 'description_en' => ['label'=>'توضیح انگلیسی','type'=>'textarea'], 'icon' => ['label'=>'آیکن','type'=>'text'], 'image' => ['label'=>'تصویر','type'=>'image','folder'=>'categories'], 'parent_id' => ['label'=>'دسته والد','type'=>'category'], 'sepid_id' => ['label'=>'شناسه Sepid','type'=>'text'],
                'visible_qr_menu' => ['label'=>'نمایش در QR Menu','type'=>'checkbox'], 'visible_website' => ['label'=>'نمایش در وب‌سایت','type'=>'checkbox'], 'visible_kiosk' => ['label'=>'نمایش در کیوسک','type'=>'checkbox'],
                'sort_order' => ['label'=>'ترتیب','type'=>'number'], 'is_active' => ['label'=>'فعال','type'=>'checkbox'],
            ],
            'columns' => ['id','name_fa','slug','icon','sort_order','visible_qr_menu','visible_website','visible_kiosk','is_active','created_at'],
        ],
        'menu-items' => [
            'title' => 'آیتم‌های منو', 'min_role' => 'manager', 'table' => 'menu_items', 'unique' => 'slug', 'search' => ['name_fa','name_en','slug','description_fa'], 'filters' => ['category_id','availability_status','is_available','is_featured','visible_qr_menu','visible_website'], 'date_column' => 'created_at',
            'join' => 'SELECT mi.*, mc.name_fa AS category_title FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id = mc.id', 'alias' => 'mi', 'required_tables' => ['menu_categories'],
            'fields' => [
                'category_id' => ['label'=>'دسته‌بندی','type'=>'category','required'=>true], 'name_fa' => ['label'=>'عنوان فارسی','type'=>'text','required'=>true], 'name_en' => ['label'=>'عنوان انگلیسی','type'=>'text','required'=>true], 'slug' => ['label'=>'اسلاگ','type'=>'text','required'=>true],
                'description_fa' => ['label'=>'توضیح فارسی','type'=>'textarea'], 'description_en' => ['label'=>'توضیح انگلیسی','type'=>'textarea'], 'price' => ['label'=>'قیمت','type'=>'number','required'=>true], 'discount_price' => ['label'=>'قیمت تخفیف','type'=>'number'],
                'image' => ['label'=>'تصویر','type'=>'image','folder'=>'menu'], 'gallery_images' => ['label'=>'گالری JSON','type'=>'json'], 'ingredients_fa' => ['label'=>'مواد اولیه فارسی','type'=>'textarea'], 'ingredients_en' => ['label'=>'مواد اولیه انگلیسی','type'=>'textarea'],
                'calories' => ['label'=>'کالری','type'=>'number'], 'preparation_time' => ['label'=>'زمان آماده‌سازی','type'=>'number'],
                'availability_status' => ['label'=>'وضعیت موجودی','type'=>'select','options'=>['available'=>'موجود','unavailable'=>'ناموجود','limited'=>'محدود']],
                'visible_qr_menu' => ['label'=>'نمایش در QR Menu','type'=>'checkbox'], 'visible_website' => ['label'=>'نمایش در وب‌سایت','type'=>'checkbox'], 'visible_kiosk' => ['label'=>'نمایش در کیوسک','type'=>'checkbox'], 'visible_loyalty' => ['label'=>'نمایش در وفاداری','type'=>'checkbox'],
                'campaign_price' => ['label'=>'قیمت کمپین','type'=>'number'], 'promo_text' => ['label'=>'متن تبلیغاتی','type'=>'text'], 'promo_image' => ['label'=>'تصویر تبلیغاتی','type'=>'image','folder'=>'menu'], 'sepid_id' => ['label'=>'شناسه Sepid','type'=>'text'],
                'is_available' => ['label'=>'فعال','type'=>'checkbox'], 'is_featured' => ['label'=>'ویژه','type'=>'checkbox'], 'is_vegetarian' => ['label'=>'گیاهی','type'=>'checkbox'], 'is_spicy' => ['label'=>'تند','type'=>'checkbox'], 'sort_order' => ['label'=>'ترتیب','type'=>'number'],
            ],
            'columns' => ['id','category_title','name_fa','price','campaign_price','availability_status','visible_qr_menu','visible_website','is_featured','sort_order'],
        ],
        'surveys' => [
            'title' => 'فرم‌های نظرسنجی', 'min_role' => 'admin', 'table' => 'dynamic_forms', 'unique' => 'form_name', 'search' => ['form_name','form_title_fa'], 'filters' => ['is_active','branch_id'], 'date_column' => 'created_at',
            'fields' => [
                'form_name' => ['label'=>'نام سیستمی','type'=>'text','required'=>true], 'form_title_fa' => ['label'=>'عنوان فارسی','type'=>'text','required'=>true], 'form_title_en' => ['label'=>'عنوان انگلیسی','type'=>'text'],
                'form_description_fa' => ['label'=>'توضیح فارسی','type'=>'textarea'], 'form_description_en' => ['label'=>'توضیح انگلیسی','type'=>'textarea'], 'form_schema' => ['label'=>'ساختار فرم JSON','type'=>'json','default'=>'{"fields":[]}','required'=>true],
                'start_date' => ['label'=>'شروع انتشار','type'=>'datetime'], 'end_date' => ['label'=>'پایان انتشار','type'=>'datetime'], 'publishing_channels' => ['label'=>'کانال‌های انتشار','type'=>'text'], 'branch_id' => ['label'=>'شعبه','type'=>'number'], 'survey_version' => ['label'=>'نسخه','type'=>'number'],
                'is_active' => ['label'=>'فعال','type'=>'checkbox'], 'display_order' => ['label'=>'ترتیب','type'=>'number'],
            ],
            'columns' => ['id','form_name','form_title_fa','is_active','publishing_channels','branch_id','survey_version','display_order','created_at'],
        ],
        'survey-responses' => [
            'title' => 'پاسخ‌های نظرسنجی', 'min_role' => 'manager', 'table' => 'survey_responses', 'readonly_create' => true, 'search' => ['customer_name','customer_phone','customer_email'], 'filters' => ['form_id','branch_id','is_dissatisfied','crm_follow_up'], 'date_column' => 'submitted_at',
            'join' => 'SELECT sr.*, df.form_title_fa AS form_title, u.phone AS user_phone, u.full_name AS user_full_name, o.order_number, o.total AS order_total FROM survey_responses sr LEFT JOIN dynamic_forms df ON sr.form_id = df.id LEFT JOIN users u ON sr.user_id = u.id LEFT JOIN orders o ON sr.order_id = o.id', 'alias' => 'sr', 'required_tables' => ['dynamic_forms','users','orders'],
            'fields' => [
                'form_id' => ['label'=>'فرم','type'=>'survey_form','required'=>true], 'customer_name' => ['label'=>'نام مشتری','type'=>'text'], 'customer_phone' => ['label'=>'موبایل','type'=>'mobile'], 'customer_email' => ['label'=>'ایمیل','type'=>'text'], 'response_data' => ['label'=>'داده پاسخ JSON','type'=>'textarea','default'=>'{}'],
                'branch_id' => ['label'=>'شعبه','type'=>'number'], 'satisfaction_score' => ['label'=>'امتیاز رضایت','type'=>'number'], 'is_dissatisfied' => ['label'=>'مشتری ناراضی','type'=>'checkbox'], 'crm_follow_up' => ['label'=>'پیگیری CRM','type'=>'checkbox'],
            ],
            'columns' => ['id','form_title','customer_name','customer_phone','user_phone','order_number','satisfaction_score','is_dissatisfied','crm_follow_up','submitted_at'],
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

function adminModuleOptionalColumns(): array {
    return [
        'matches' => [
            'title' => "varchar(200) DEFAULT NULL",
            'description' => "text DEFAULT NULL",
            'rules' => "text DEFAULT NULL",
            'participation_conditions' => "text DEFAULT NULL",
            'team_a' => "varchar(120) DEFAULT NULL",
            'team_b' => "varchar(120) DEFAULT NULL",
            'team_one_name' => "varchar(120) DEFAULT NULL",
            'team_two_name' => "varchar(120) DEFAULT NULL",
            'team_one_logo' => "varchar(255) DEFAULT NULL",
            'team_two_logo' => "varchar(255) DEFAULT NULL",
            'match_date' => "date DEFAULT NULL",
            'kickoff_time' => "time DEFAULT NULL",
            'broadcast_time' => "time DEFAULT NULL",
            'final_score_team_a' => "int(11) DEFAULT NULL",
            'final_score_team_b' => "int(11) DEFAULT NULL",
            'final_team_one_score' => "int(11) DEFAULT NULL",
            'final_team_two_score' => "int(11) DEFAULT NULL",
            'final_result_status' => "varchar(50) DEFAULT NULL",
            'match_finished' => "tinyint(1) NOT NULL DEFAULT 0",
            'prediction_open_at' => "datetime DEFAULT NULL",
            'prediction_close_at' => "datetime DEFAULT NULL",
            'prediction_start_at' => "datetime DEFAULT NULL",
            'prediction_end_at' => "datetime DEFAULT NULL",
            'match_start_at' => "datetime DEFAULT NULL",
            'match_end_at' => "datetime DEFAULT NULL",
            'start_date' => "datetime DEFAULT NULL",
            'end_date' => "datetime DEFAULT NULL",
            'status' => "varchar(50) NOT NULL DEFAULT 'active'",
            'campaign_status' => "varchar(50) NOT NULL DEFAULT 'active'",
            'participant_count' => "int(11) NOT NULL DEFAULT 0",
            'banner_id' => "int(11) UNSIGNED DEFAULT NULL",
            'menu_item_id' => "int(11) UNSIGNED DEFAULT NULL",
            'campaign_target' => "varchar(150) DEFAULT NULL",
            'reward_title' => "varchar(200) DEFAULT NULL",
            'points_reward' => "int(11) NOT NULL DEFAULT 0",
            'reward_points' => "int(11) NOT NULL DEFAULT 0",
            'reward_description' => "text DEFAULT NULL",
            'is_active' => "tinyint(1) NOT NULL DEFAULT 1",
            'active_for_prediction' => "tinyint(1) NOT NULL DEFAULT 1",
            'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
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
            'status' => "varchar(50) NOT NULL DEFAULT 'pending'",
            'is_winner' => "tinyint(1) NOT NULL DEFAULT 0",
            'evaluated_at' => "datetime DEFAULT NULL",
            'points_awarded' => "int(11) NOT NULL DEFAULT 0",
            'crm_follow_up' => "tinyint(1) NOT NULL DEFAULT 0",
            'wants_reservation' => "tinyint(1) NOT NULL DEFAULT 0",
            'reserve_table_interest' => "tinyint(1) NOT NULL DEFAULT 0",
            'crm_match' => "tinyint(1) NOT NULL DEFAULT 0",
            'crm_matched' => "tinyint(1) NOT NULL DEFAULT 0",
            'customer_exists' => "tinyint(1) NOT NULL DEFAULT 0",
            'attended_match' => "tinyint(1) NOT NULL DEFAULT 0",
            'attended_match_time' => "tinyint(1) NOT NULL DEFAULT 0",
            'is_correct_prediction' => "tinyint(1) NOT NULL DEFAULT 0",
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
            'email' => "varchar(150) DEFAULT NULL",
            'customer_status' => "enum('new_customer','loyal_customer','vip','dissatisfied_customer','churn_risk') NOT NULL DEFAULT 'new_customer'",
            'points_balance' => "int(11) NOT NULL DEFAULT 0",
            'rewards_notes' => "text DEFAULT NULL",
            'follow_up_notes' => "text DEFAULT NULL",
        ],
        'hero_banners' => [
            'display_location' => "varchar(50) NOT NULL DEFAULT 'homepage'",
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
    if (($config['table'] ?? '') === 'matches') {
        adminEnsureMatchesSchema();
    }

    $missing = [];
    foreach (adminModuleRequiredTables($config) as $table) {
        if (!adminTableExists($table)) $missing[] = $table;
    }
    if ($missing) ensureAdminCanonicalTables(adminDb(), $missing);
    if (($config['table'] ?? '') === 'matches') {
        adminEnsureMatchesSchema();
    }
    foreach (adminModuleRequiredTables($config) as $table) {
        adminEnsureModuleOptionalColumns($table);
    }
    $audit = adminModuleSchemaAudit($config);
    if (!empty($audit['missing_fields'])) {
        safeAdminLog('Admin module repair could not find configured fields for ' . ($config['table'] ?? 'unknown') . ': ' . implode(', ', $audit['missing_fields']));
    }
    if (($config['table'] ?? '') === 'hero_banners' && adminTableExists('hero_banners') && adminColumnExists('hero_banners', 'display_location')) {
        try {
            adminDb()->exec("UPDATE `hero_banners` SET `display_location` = 'homepage' WHERE `display_location` IS NULL OR `display_location` = ''");
        } catch (Throwable $e) {
            safeAdminLog('Hero banner display_location repair failed: ' . $e->getMessage());
        }
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

function adminModuleVirtualColumns(): array {
    return [
        'match_title','category_title','form_title','prediction_count','winner_count',
        'prediction_team_one','prediction_team_two','prediction_score_one','prediction_score_two',
        'final_score_one','final_score_two','crm_status','team_one_display','team_two_display',
        'match_start_display','prediction_start_display','prediction_end_display','points_reward_display',
        'user_phone','user_full_name','order_number','order_total',
    ];
}

function adminModuleSchemaAudit(array $config): array {
    $table = (string)($config['table'] ?? '');
    $existingColumns = $table !== '' ? adminModuleExistingColumnNames($table) : [];
    $virtualColumns = adminModuleVirtualColumns();
    $configuredFields = array_keys($config['fields'] ?? []);
    $configuredColumns = array_values($config['columns'] ?? []);
    $configuredFilters = array_values($config['filters'] ?? []);
    $configuredSearch = array_values($config['search'] ?? []);

    $missingFields = array_values(array_diff($configuredFields, $existingColumns));
    $missingColumns = array_values(array_filter($configuredColumns, static fn($column) => !in_array($column, $existingColumns, true) && !in_array($column, $virtualColumns, true)));
    $missingFilters = array_values(array_diff($configuredFilters, $existingColumns));
    $missingSearch = array_values(array_diff($configuredSearch, $existingColumns));

    return [
        'module' => (string)($config['key'] ?? ''),
        'table' => $table,
        'existing_columns' => $existingColumns,
        'configured_fields' => $configuredFields,
        'missing_fields' => $missingFields,
        'configured_columns' => $configuredColumns,
        'missing_columns' => $missingColumns,
        'virtual_columns' => array_values(array_intersect($configuredColumns, $virtualColumns)),
        'configured_filters' => $configuredFilters,
        'filters' => $configuredFilters,
        'missing_filters' => $missingFilters,
        'configured_search' => $configuredSearch,
        'search' => $configuredSearch,
        'missing_search' => $missingSearch,
    ];
}

function adminModuleSchemaAuditAll(?array $moduleKeys = null): array {
    $audits = [];
    $modules = adminModuleDefinitions();
    $moduleKeys = $moduleKeys ?: ['matches','predictions','banners','categories','menu-items','surveys'];
    foreach ($moduleKeys as $key) {
        if (!isset($modules[$key])) continue;
        $config = $modules[$key];
        $config['key'] = $key;
        $audits[$key] = adminModuleSchemaAudit($config);
    }
    return $audits;
}

function adminModuleSchemaDiagnostics(?array $admin = null): array {
    $debugAllowed = defined('APP_DEBUG') && APP_DEBUG;
    $superAdminAllowed = (string)($admin['role'] ?? '') === 'super_admin';
    if (!$debugAllowed && !$superAdminAllowed) {
        safeAdminLog('Blocked admin module schema diagnostics request.');
        return ['ok' => false, 'error' => 'not_allowed'];
    }
    return ['ok' => true, 'generated_at' => date('c'), 'modules' => adminModuleSchemaAuditAll()];
}

function adminModuleNormalizeConfig(array $config): array {
    $audit = adminModuleSchemaAudit($config);
    $allowed = $audit['existing_columns'];
    $virtualColumns = adminModuleVirtualColumns();
    $configuredFields = $config['fields'] ?? [];

    if (!empty($audit['missing_fields'])) {
        safeAdminLog('Admin module configured fields missing from table ' . ($config['table'] ?? 'unknown') . ': ' . implode(', ', $audit['missing_fields']));
    }
    if (!empty($audit['missing_columns'])) {
        safeAdminLog('Admin module list columns missing from table ' . ($config['table'] ?? 'unknown') . ': ' . implode(', ', $audit['missing_columns']));
    }

    $config['fields'] = array_filter($configuredFields, static fn($field) => in_array($field, $allowed, true), ARRAY_FILTER_USE_KEY);
    $config['columns'] = array_values(array_filter($config['columns'] ?? [], static fn($col) => in_array($col, $allowed, true) || in_array($col, $virtualColumns, true)));
    $config['filters'] = array_values(array_filter($config['filters'] ?? [], static fn($col) => in_array($col, $allowed, true)));
    $config['search'] = array_values(array_filter($config['search'] ?? [], static fn($col) => in_array($col, $allowed, true)));
    $config['_schema_audit'] = $audit;

    if (!empty($audit['missing_fields']) && empty($config['readonly_create'])) {
        $missing = $audit['missing_fields'] ? implode(', ', $audit['missing_fields']) : 'هیچ فیلد قابل ویرایشی پیدا نشد';
        $config['schema_error'] = 'فرم این صفحه با ستون‌های واقعی جدول «' . ($config['table'] ?? 'نامشخص') . '» همگام نیست. فیلدهای ناموجود: ' . $missing;
        safeAdminLog('Admin module schema error for ' . ($config['table'] ?? 'unknown') . ': ' . $missing);
    } elseif (empty($config['fields']) && empty($config['readonly_create'])) {
        $config['schema_error'] = 'فرم این صفحه با ستون‌های واقعی جدول «' . ($config['table'] ?? 'نامشخص') . '» همگام نیست. فیلدهای ناموجود: هیچ فیلد قابل ویرایشی پیدا نشد';
        safeAdminLog('Admin module schema error for ' . ($config['table'] ?? 'unknown') . ': no editable fields');
    }

    return $config;
}

function adminAssertParsedDateValue($value, bool $withTime): void {
    if ($value === null || $value === '') {
        return;
    }
    $pattern = $withTime
        ? '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'
        : '/^\d{4}-\d{2}-\d{2}$/';
    if (!is_string($value) || !preg_match($pattern, $value)) {
        throw new RuntimeException('تاریخ وارد شده معتبر نیست.');
    }
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
    $filename = optimizeUploadedImage($_FILES[$field]['tmp_name'], $dir, uniqid($field . '_', true));
    return 'uploads/' . trim($folder, '/') . '/' . basename($filename);
}

function adminModuleImageSrc($value, string $folder): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^https?:\/\//i', $value)) return $value;
    $value = ltrim($value, '/');
    if (str_starts_with($value, 'uploads/')) return '../' . $value;
    return '../uploads/' . trim($folder, '/') . '/' . $value;
}

function adminModuleFilterMeta(array $config, string $filter): array {
    return $config['filter_fields'][$filter]
        ?? $config['fields'][$filter]
        ?? ['label' => adminModuleLabel($config, $filter), 'type' => 'checkbox'];
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
        if ($type === 'date') {
            $value = parsePersianDate($value, false);
            adminAssertParsedDateValue($value, false);
        }
        if ($type === 'datetime') {
            $value = parsePersianDate($value, true);
            adminAssertParsedDateValue($value, true);
        }
        if ($type === 'time') {
            $value = parsePersianTime($value);
            if ($value !== null && $value !== '' && (!is_string($value) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $value))) {
                throw new RuntimeException('تاریخ وارد شده معتبر نیست.');
            }
        }
        if ($type === 'json' && $value !== null && trim((string)$value) !== '') {
            json_decode((string)$value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($name === 'form_schema') {
                    throw new RuntimeException('ساختار فرم JSON معتبر نیست.');
                }
                throw new RuntimeException('فیلد «' . ($meta['label'] ?? $name) . '» باید JSON معتبر باشد.');
            }
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

function adminModulePrepareData(array $config, array $data, array $current = []): array {
    if (($config['table'] ?? '') === 'hero_banners' && adminColumnExists('hero_banners', 'display_location')) {
        $data['display_location'] = 'homepage';
    }

    if (($config['table'] ?? '') === 'matches') {
        $teamOne = $data['team_a'] ?? $data['team_one_name'] ?? $current['team_a'] ?? $current['team_one_name'] ?? null;
        $teamTwo = $data['team_b'] ?? $data['team_two_name'] ?? $current['team_b'] ?? $current['team_two_name'] ?? null;
        if ($teamOne !== null) {
            if (array_key_exists('team_a', $data) || adminColumnExists('matches', 'team_a')) $data['team_a'] = $teamOne;
            if (adminColumnExists('matches', 'team_one_name')) $data['team_one_name'] = $teamOne;
        }
        if ($teamTwo !== null) {
            if (array_key_exists('team_b', $data) || adminColumnExists('matches', 'team_b')) $data['team_b'] = $teamTwo;
            if (adminColumnExists('matches', 'team_two_name')) $data['team_two_name'] = $teamTwo;
        }

        if (!empty($data['match_start_at'])) {
            $timestamp = strtotime((string)$data['match_start_at']);
            if ($timestamp) {
                if (adminColumnExists('matches', 'match_date')) $data['match_date'] = date('Y-m-d', $timestamp);
                if (adminColumnExists('matches', 'kickoff_time')) $data['kickoff_time'] = date('H:i:s', $timestamp);
                if (adminColumnExists('matches', 'broadcast_time') && empty($data['broadcast_time'])) $data['broadcast_time'] = date('H:i:s', $timestamp);
            }
        } elseif (!empty($data['match_date']) && !empty($data['kickoff_time']) && adminColumnExists('matches', 'match_start_at')) {
            $data['match_start_at'] = trim((string)$data['match_date'] . ' ' . (string)$data['kickoff_time']);
        }

        foreach ([['prediction_start_at', 'prediction_open_at'], ['prediction_end_at', 'prediction_close_at'], ['final_team_one_score', 'final_score_team_a'], ['final_team_two_score', 'final_score_team_b'], ['points_reward', 'reward_points']] as [$preferred, $legacy]) {
            $value = $data[$preferred] ?? $data[$legacy] ?? $current[$preferred] ?? $current[$legacy] ?? null;
            if ($value !== null && $value !== '') {
                if (adminColumnExists('matches', $preferred)) $data[$preferred] = $value;
                if (adminColumnExists('matches', $legacy)) $data[$legacy] = $value;
            }
        }

        $status = (string)($data['status'] ?? $current['status'] ?? 'active');
        if (adminColumnExists('matches', 'is_active') && !array_key_exists('is_active', $data)) {
            $data['is_active'] = in_array($status, ['active', 'scheduled', 'live'], true) ? 1 : (isset($data['is_active']) ? (int)$data['is_active'] : 0);
        }
        if (adminColumnExists('matches', 'active_for_prediction') && !array_key_exists('active_for_prediction', $data)) {
            $data['active_for_prediction'] = in_array($status, ['active', 'scheduled', 'live'], true) ? 1 : (isset($data['active_for_prediction']) ? (int)$data['active_for_prediction'] : 0);
        }
        if (($data['final_team_one_score'] ?? $data['final_score_team_a'] ?? null) !== null && ($data['final_team_two_score'] ?? $data['final_score_team_b'] ?? null) !== null) {
            if (adminColumnExists('matches', 'match_finished')) $data['match_finished'] = 1;
            if (adminColumnExists('matches', 'final_result_status')) $data['final_result_status'] = 'entered';
        }
    }

    if (($config['table'] ?? '') === 'predictions') {
        $mobile = $data['customer_mobile'] ?? $data['mobile'] ?? $current['customer_mobile'] ?? $current['mobile'] ?? null;
        if ($mobile !== null) {
            $mobile = normalizeMobile($mobile);
            if (adminColumnExists('predictions', 'mobile')) $data['mobile'] = $mobile;
            if (adminColumnExists('predictions', 'customer_mobile')) $data['customer_mobile'] = $mobile;
        }
        foreach ([['predicted_team_one_score', 'predicted_score_team_a'], ['predicted_team_two_score', 'predicted_score_team_b'], ['wants_reservation', 'reserve_table_interest'], ['crm_match', 'crm_matched'], ['attended_match', 'attended_match_time']] as [$preferred, $legacy]) {
            $value = $data[$preferred] ?? $data[$legacy] ?? $current[$preferred] ?? $current[$legacy] ?? null;
            if ($value !== null && $value !== '') {
                if (adminColumnExists('predictions', $preferred)) $data[$preferred] = $value;
                if (adminColumnExists('predictions', $legacy)) $data[$legacy] = $value;
            }
        }
    }

    $allowed = adminModuleExistingColumnNames($config['table']);
    return array_intersect_key($data, array_flip($allowed));
}

function adminModuleAssertReferencedId(string $table, int $id, string $message): void {
    if ($id <= 0 || !adminTableExists($table)) {
        throw new RuntimeException($message);
    }
    $stmt = adminDb()->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException($message);
    }
}

function adminModuleValidateData(array $config, array $data, int $id = 0): void {
    $table = (string)($config['table'] ?? '');
    foreach (($config['fields'] ?? []) as $field => $meta) {
        if (($meta['type'] ?? '') === 'number' && array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '' && !is_numeric($data[$field])) {
            throw new RuntimeException('فیلد «' . ($meta['label'] ?? $field) . '» باید عددی باشد.');
        }
    }

    $unique = (string)($config['unique'] ?? '');
    if ($unique !== '' && array_key_exists($unique, $data) && $data[$unique] !== null && $data[$unique] !== '') {
        $stmt = adminDb()->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE `' . str_replace('`', '``', $unique) . '` = :value AND id <> :id LIMIT 1');
        $stmt->execute(['value' => $data[$unique], 'id' => $id]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('مقدار فیلد «' . adminModuleLabel($config, $unique) . '» تکراری است.');
        }
    }

    if ($table === 'menu_categories' && !empty($data['parent_id'])) {
        $parentId = (int)$data['parent_id'];
        if ($id > 0 && $parentId === $id) {
            throw new RuntimeException('دسته والد نمی‌تواند خود رکورد باشد.');
        }
        adminModuleAssertReferencedId('menu_categories', $parentId, 'دسته والد معتبر نیست.');
    }

    if ($table === 'menu_items') {
        if (!empty($data['category_id'])) {
            adminModuleAssertReferencedId('menu_categories', (int)$data['category_id'], 'دسته‌بندی معتبر نیست.');
        }
        foreach (['price','discount_price','campaign_price'] as $priceField) {
            if (array_key_exists($priceField, $data) && $data[$priceField] !== null && $data[$priceField] !== '' && (float)$data[$priceField] < 0) {
                throw new RuntimeException('مبلغ نمی‌تواند منفی باشد.');
            }
        }
        if (!empty($data['gallery_images'])) {
            $gallery = json_decode((string)$data['gallery_images'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($gallery)) {
                throw new RuntimeException('فیلد «گالری JSON» باید JSON معتبر باشد.');
            }
        }
    }

    if ($table === 'dynamic_forms') {
        $schemaRaw = $data['form_schema'] ?? null;
        $schema = is_string($schemaRaw) ? json_decode($schemaRaw, true) : null;
        if (!is_array($schema) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('ساختار فرم JSON معتبر نیست.');
        }
        if (!array_key_exists('fields', $schema) || !is_array($schema['fields'])) {
            throw new RuntimeException('ساختار فرم JSON معتبر نیست.');
        }
        if (!empty($data['start_date']) && !empty($data['end_date']) && strtotime((string)$data['end_date']) < strtotime((string)$data['start_date'])) {
            throw new RuntimeException('تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.');
        }
    }
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

function adminModuleDelete(array $config, int $id): void {
    adminDb()->prepare('DELETE FROM `' . str_replace('`', '``', $config['table']) . '` WHERE id = ?')->execute([$id]);
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
        if (!empty($_GET['date_from'])) { $where[] = $prefix . '`' . $dateColumn . '` >= :date_from'; $params['date_from'] = parsePersianDate($_GET['date_from'], false) . ' 00:00:00'; }
        if (!empty($_GET['date_to'])) { $where[] = $prefix . '`' . $dateColumn . '` <= :date_to'; $params['date_to'] = parsePersianDate($_GET['date_to'], false) . ' 23:59:59'; }
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
    if ($column === 'crm_status') {
        return (string)$value === 'exists' ? 'موجود در CRM' : ((string)$value === 'missing' ? 'ثبت نشده در CRM' : (string)$value);
    }
    if ($column === 'wants_reservation') {
        return (string)$value === '1' ? 'بله - درخواست رزرو' : 'خیر';
    }
    if (str_ends_with($column, '_date')) return formatJalaliDateTime($value, false);
    if (str_ends_with($column, '_at') || $column === 'submitted_at') return formatJalaliDateTime($value, true);
    if ((string)$value === '1') return 'بله';
    if ((string)$value === '0') return 'خیر';
    return (string)$value;
}

function adminModuleRenderValue(array $config, string $column, $value, array $row = []): string {
    $meta = $config['fields'][$column] ?? [];
    if (($meta['type'] ?? '') === 'image') {
        $src = adminModuleImageSrc($value, $meta['folder'] ?? 'admin');
        if ($src === '') return '';
        return '<img src="' . h($src) . '" alt="" style="width:72px;height:44px;object-fit:cover;border-radius:6px">';
    }

    if ($column === 'crm_status') {
        $label = adminModuleFormatValue($column, $value);
        $bg = (string)$value === 'exists' ? '#d1e7dd' : '#fff3cd';
        $color = (string)$value === 'exists' ? '#0f5132' : '#664d03';
        return '<span style="display:inline-block;padding:3px 8px;border-radius:999px;background:' . $bg . ';color:' . $color . '">' . h($label) . '</span>';
    }

    if ($column === 'wants_reservation') {
        if ((string)$value === '1') {
            return '<span style="display:inline-block;padding:3px 8px;border-radius:999px;background:#fff3cd;color:#664d03">بله - درخواست رزرو</span>';
        }
        return 'خیر';
    }

    return h(adminModuleFormatValue($column, $value));
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
    ensureAdminAnalyticsSchema();
}

function adminEnsureIndexes(string $table, array $indexes): void {
    if (!adminTableExists($table)) {
        return;
    }

    $db = adminDb();
    foreach ($indexes as $index => $definition) {
        if (!adminIndexExists($table, $index)) {
            try {
                $db->exec("ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD {$definition}");
            } catch (Throwable $e) {
                safeAdminLog("Schema index ensure failed for {$table}.{$index}: " . $e->getMessage());
            }
        }
    }
}

function ensureAdminAnalyticsSchema(): void {
    $db = adminDb();
    $run = function (string $sql, string $label) use ($db) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            safeAdminLog($label . ' failed: ' . $e->getMessage());
        }
    };

    $run("CREATE TABLE IF NOT EXISTS `analytics_visitors` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_uuid` varchar(64) NOT NULL,
        `first_seen_at` datetime NOT NULL,
        `last_seen_at` datetime NOT NULL,
        `ip_hash` char(64) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `browser` varchar(100) DEFAULT NULL,
        `os` varchar(100) DEFAULT NULL,
        `device_type` varchar(50) DEFAULT NULL,
        `country` varchar(100) DEFAULT 'Unknown',
        `city` varchar(100) DEFAULT 'Unknown',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_analytics_visitor_uuid` (`visitor_uuid`),
        KEY `idx_analytics_visitors_device` (`device_type`),
        KEY `idx_analytics_visitors_browser` (`browser`),
        KEY `idx_analytics_visitors_os` (`os`),
        KEY `idx_analytics_visitors_country` (`country`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table analytics_visitors');

    $run("CREATE TABLE IF NOT EXISTS `analytics_sessions` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_uuid` varchar(64) NOT NULL,
        `visitor_uuid` varchar(64) NOT NULL,
        `started_at` datetime NOT NULL,
        `last_activity_at` datetime NOT NULL,
        `landing_page` varchar(500) DEFAULT NULL,
        `referrer` varchar(500) DEFAULT NULL,
        `source` varchar(100) DEFAULT NULL,
        `medium` varchar(100) DEFAULT NULL,
        `campaign` varchar(150) DEFAULT NULL,
        `utm_source` varchar(100) DEFAULT NULL,
        `utm_medium` varchar(100) DEFAULT NULL,
        `utm_campaign` varchar(150) DEFAULT NULL,
        `utm_term` varchar(150) DEFAULT NULL,
        `utm_content` varchar(150) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_analytics_session_uuid` (`session_uuid`),
        KEY `idx_analytics_sessions_visitor` (`visitor_uuid`),
        KEY `idx_analytics_sessions_started` (`started_at`),
        KEY `idx_analytics_sessions_activity` (`last_activity_at`),
        KEY `idx_analytics_sessions_source` (`source`),
        KEY `idx_analytics_sessions_medium` (`medium`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table analytics_sessions');

    $run("CREATE TABLE IF NOT EXISTS `analytics_pageviews` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_uuid` varchar(64) NOT NULL,
        `session_uuid` varchar(64) NOT NULL,
        `page_url` varchar(1000) DEFAULT NULL,
        `page_path` varchar(500) DEFAULT NULL,
        `page_title` varchar(255) DEFAULT NULL,
        `referrer` varchar(500) DEFAULT NULL,
        `screen_width` int(11) DEFAULT NULL,
        `screen_height` int(11) DEFAULT NULL,
        `browser_language` varchar(50) DEFAULT NULL,
        `timezone` varchar(100) DEFAULT NULL,
        `viewed_at` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_analytics_pageviews_visitor` (`visitor_uuid`),
        KEY `idx_analytics_pageviews_session` (`session_uuid`),
        KEY `idx_analytics_pageviews_viewed` (`viewed_at`),
        KEY `idx_analytics_pageviews_path` (`page_path`(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table analytics_pageviews');

    $run("CREATE TABLE IF NOT EXISTS `visitor_analytics_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table visitor_analytics_logs');

    $run("CREATE TABLE IF NOT EXISTS `traffic_logs` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_id` varchar(64) NOT NULL,
        `ip_address` varchar(64) DEFAULT NULL,
        `country` varchar(100) DEFAULT 'Unknown',
        `city` varchar(100) DEFAULT 'Unknown',
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
        `source_type` varchar(50) NOT NULL DEFAULT 'unknown',
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
        `ip_address` varchar(64) DEFAULT NULL,
        `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `current_page` varchar(500) DEFAULT NULL,
        `source_name` varchar(150) DEFAULT NULL,
        `device_type` varchar(50) DEFAULT NULL,
        `browser` varchar(100) DEFAULT NULL,
        `os` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_session` (`session_id`),
        KEY `idx_session_active` (`is_active`, `last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'ensure table visitor_sessions');

    $run("CREATE TABLE IF NOT EXISTS `visitor_locations` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `country` varchar(100) NOT NULL DEFAULT 'Unknown',
        `city` varchar(100) NOT NULL DEFAULT 'Unknown',
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

    ensureTableColumns('analytics_visitors', [
        'visitor_uuid' => "varchar(64) NOT NULL DEFAULT ''",
        'first_seen_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'last_seen_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'ip_hash' => 'char(64) DEFAULT NULL',
        'user_agent' => 'text DEFAULT NULL',
        'browser' => 'varchar(100) DEFAULT NULL',
        'os' => 'varchar(100) DEFAULT NULL',
        'device_type' => 'varchar(50) DEFAULT NULL',
        'country' => "varchar(100) DEFAULT 'Unknown'",
        'city' => "varchar(100) DEFAULT 'Unknown'",
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);
    ensureTableColumns('analytics_sessions', [
        'session_uuid' => "varchar(64) NOT NULL DEFAULT ''",
        'visitor_uuid' => "varchar(64) NOT NULL DEFAULT ''",
        'started_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'last_activity_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'landing_page' => 'varchar(500) DEFAULT NULL',
        'referrer' => 'varchar(500) DEFAULT NULL',
        'source' => 'varchar(100) DEFAULT NULL',
        'medium' => 'varchar(100) DEFAULT NULL',
        'campaign' => 'varchar(150) DEFAULT NULL',
        'utm_source' => 'varchar(100) DEFAULT NULL',
        'utm_medium' => 'varchar(100) DEFAULT NULL',
        'utm_campaign' => 'varchar(150) DEFAULT NULL',
        'utm_term' => 'varchar(150) DEFAULT NULL',
        'utm_content' => 'varchar(150) DEFAULT NULL',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ]);
    ensureTableColumns('analytics_pageviews', [
        'visitor_uuid' => "varchar(64) NOT NULL DEFAULT ''",
        'session_uuid' => "varchar(64) NOT NULL DEFAULT ''",
        'page_url' => 'varchar(1000) DEFAULT NULL',
        'page_path' => 'varchar(500) DEFAULT NULL',
        'page_title' => 'varchar(255) DEFAULT NULL',
        'referrer' => 'varchar(500) DEFAULT NULL',
        'screen_width' => 'int(11) DEFAULT NULL',
        'screen_height' => 'int(11) DEFAULT NULL',
        'browser_language' => 'varchar(50) DEFAULT NULL',
        'timezone' => 'varchar(100) DEFAULT NULL',
        'viewed_at' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    ensureTableColumns('visitor_analytics_logs', [
        'session_id' => "varchar(64) NOT NULL DEFAULT ''",
        'user_id' => "int(11) UNSIGNED DEFAULT NULL",
        'customer_id' => "int(11) UNSIGNED DEFAULT NULL",
        'source_type' => "varchar(100) DEFAULT NULL",
        'source_name' => "varchar(150) DEFAULT NULL",
        'campaign_type' => "varchar(100) DEFAULT NULL AFTER source_name",
        'entry_link' => "varchar(500) DEFAULT NULL AFTER campaign_type",
        'referrer_url' => "varchar(500) DEFAULT NULL",
        'utm_source' => "varchar(100) DEFAULT NULL",
        'utm_medium' => "varchar(100) DEFAULT NULL",
        'utm_campaign' => "varchar(150) DEFAULT NULL",
        'landing_page' => "varchar(500) DEFAULT NULL",
        'current_page' => "varchar(500) DEFAULT NULL",
        'next_page' => "varchar(500) DEFAULT NULL",
        'related_module' => "varchar(100) DEFAULT NULL",
        'related_record_id' => "int(11) UNSIGNED DEFAULT NULL",
        'event_type' => "enum('external_entry','page_view','banner_view','banner_click','match_view','prediction_start','prediction_submit','category_view','menu_item_view','survey_view','survey_start','survey_submit','crm_link_entry','exit') NOT NULL DEFAULT 'page_view'",
        'target_action' => "varchar(100) DEFAULT NULL",
        'device_type' => "varchar(50) DEFAULT NULL",
        'browser' => "varchar(100) DEFAULT NULL",
        'operating_system' => "varchar(100) DEFAULT NULL",
        'ip_address' => "varchar(64) DEFAULT NULL",
        'branch_id' => "int(11) UNSIGNED DEFAULT NULL",
        'is_new_visitor' => "tinyint(1) NOT NULL DEFAULT 0",
        'is_converted' => "tinyint(1) NOT NULL DEFAULT 0",
        'duration_seconds' => "int(11) DEFAULT NULL",
        'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ]);
    ensureTableColumns('traffic_logs', [
        'session_id' => "varchar(64) NOT NULL DEFAULT ''",
        'ip_address' => 'varchar(64) DEFAULT NULL',
        'country' => "varchar(100) DEFAULT 'Unknown'",
        'city' => "varchar(100) DEFAULT 'Unknown'",
        'isp' => 'varchar(255) DEFAULT NULL',
        'referrer' => 'varchar(500) DEFAULT NULL',
        'landing_page' => 'varchar(500) DEFAULT NULL',
        'user_agent' => 'text DEFAULT NULL',
        'browser' => 'varchar(100) DEFAULT NULL',
        'os' => 'varchar(100) DEFAULT NULL',
        'device' => 'varchar(50) DEFAULT NULL',
        'language' => 'varchar(20) DEFAULT NULL',
        'visit_duration' => 'int(11) DEFAULT NULL',
        'pages_viewed' => 'int(11) NOT NULL DEFAULT 1',
        'is_bot' => 'tinyint(1) NOT NULL DEFAULT 0',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    ensureTableColumns('traffic_sources', [
        'source_name' => "varchar(100) NOT NULL DEFAULT 'unknown'",
        'source_type' => "varchar(50) NOT NULL DEFAULT 'unknown'",
        'visits_count' => 'int(11) NOT NULL DEFAULT 0',
        'date' => "date NOT NULL DEFAULT '1970-01-01'",
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    ensureTableColumns('visitor_sessions', [
        'session_id' => "varchar(64) NOT NULL DEFAULT ''",
        'ip_address' => 'varchar(64) DEFAULT NULL',
        'started_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'last_activity' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'is_active' => 'tinyint(1) NOT NULL DEFAULT 1',
        'current_page' => 'varchar(500) DEFAULT NULL',
        'source_name' => 'varchar(150) DEFAULT NULL',
        'device_type' => 'varchar(50) DEFAULT NULL',
        'browser' => 'varchar(100) DEFAULT NULL',
        'os' => 'varchar(100) DEFAULT NULL',
    ]);
    ensureTableColumns('visitor_locations', [
        'country' => "varchar(100) NOT NULL DEFAULT 'Unknown'",
        'city' => "varchar(100) NOT NULL DEFAULT 'Unknown'",
        'visits_count' => 'int(11) NOT NULL DEFAULT 0',
        'date' => "date NOT NULL DEFAULT '1970-01-01'",
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    ensureTableColumns('traffic_statistics', [
        'stat_date' => "date NOT NULL DEFAULT '1970-01-01'",
        'total_visits' => 'int(11) NOT NULL DEFAULT 0',
        'unique_visitors' => 'int(11) NOT NULL DEFAULT 0',
        'total_page_views' => 'int(11) NOT NULL DEFAULT 0',
        'bounce_rate' => 'decimal(5,2) DEFAULT NULL',
        'avg_duration' => 'int(11) DEFAULT NULL',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);

    adminEnsureIndexes('analytics_visitors', [
        'uniq_analytics_visitor_uuid' => 'UNIQUE KEY `uniq_analytics_visitor_uuid` (`visitor_uuid`)',
        'idx_analytics_visitors_device' => 'INDEX `idx_analytics_visitors_device` (`device_type`)',
        'idx_analytics_visitors_browser' => 'INDEX `idx_analytics_visitors_browser` (`browser`)',
        'idx_analytics_visitors_os' => 'INDEX `idx_analytics_visitors_os` (`os`)',
        'idx_analytics_visitors_country' => 'INDEX `idx_analytics_visitors_country` (`country`)',
    ]);
    adminEnsureIndexes('analytics_sessions', [
        'uniq_analytics_session_uuid' => 'UNIQUE KEY `uniq_analytics_session_uuid` (`session_uuid`)',
        'idx_analytics_sessions_visitor' => 'INDEX `idx_analytics_sessions_visitor` (`visitor_uuid`)',
        'idx_analytics_sessions_started' => 'INDEX `idx_analytics_sessions_started` (`started_at`)',
        'idx_analytics_sessions_activity' => 'INDEX `idx_analytics_sessions_activity` (`last_activity_at`)',
        'idx_analytics_sessions_source' => 'INDEX `idx_analytics_sessions_source` (`source`)',
        'idx_analytics_sessions_medium' => 'INDEX `idx_analytics_sessions_medium` (`medium`)',
    ]);
    adminEnsureIndexes('analytics_pageviews', [
        'idx_analytics_pageviews_visitor' => 'INDEX `idx_analytics_pageviews_visitor` (`visitor_uuid`)',
        'idx_analytics_pageviews_session' => 'INDEX `idx_analytics_pageviews_session` (`session_uuid`)',
        'idx_analytics_pageviews_viewed' => 'INDEX `idx_analytics_pageviews_viewed` (`viewed_at`)',
        'idx_analytics_pageviews_path' => 'INDEX `idx_analytics_pageviews_path` (`page_path`(191))',
    ]);
    adminEnsureIndexes('visitor_analytics_logs', [
        'idx_visitor_logs_session' => 'INDEX `idx_visitor_logs_session` (`session_id`)',
        'idx_visitor_logs_source' => 'INDEX `idx_visitor_logs_source` (`source_type`, `source_name`)',
        'idx_visitor_logs_pages' => 'INDEX `idx_visitor_logs_pages` (`landing_page`(191), `current_page`(191), `next_page`(191))',
        'idx_visitor_logs_action' => 'INDEX `idx_visitor_logs_action` (`target_action`, `is_converted`)',
        'idx_visitor_logs_related' => 'INDEX `idx_visitor_logs_related` (`related_module`, `related_record_id`)',
        'idx_visitor_logs_created' => 'INDEX `idx_visitor_logs_created` (`created_at`)',
    ]);
    adminEnsureIndexes('traffic_logs', [
        'idx_traffic_session' => 'INDEX `idx_traffic_session` (`session_id`)',
        'idx_traffic_date' => 'INDEX `idx_traffic_date` (`created_at`)',
        'idx_traffic_country' => 'INDEX `idx_traffic_country` (`country`)',
    ]);
    adminEnsureIndexes('traffic_sources', [
        'uniq_source_date' => 'UNIQUE KEY `uniq_source_date` (`source_name`, `date`)',
        'idx_source_type' => 'INDEX `idx_source_type` (`source_type`)',
        'idx_source_date' => 'INDEX `idx_source_date` (`date`)',
    ]);
    adminEnsureIndexes('visitor_sessions', [
        'uniq_session' => 'UNIQUE KEY `uniq_session` (`session_id`)',
        'idx_session_active' => 'INDEX `idx_session_active` (`is_active`, `last_activity`)',
    ]);
    adminEnsureIndexes('visitor_locations', [
        'uniq_location_date' => 'UNIQUE KEY `uniq_location_date` (`country`, `city`, `date`)',
        'idx_location_date' => 'INDEX `idx_location_date` (`date`)',
    ]);
    adminEnsureIndexes('traffic_statistics', [
        'uniq_stat_date' => 'UNIQUE KEY `uniq_stat_date` (`stat_date`)',
    ]);
}

function adminVisitorAnalyticsSources(): array {
    return ['Instagram','Telegram','WhatsApp','SMS','Google Search','Google Maps','QR Code','CRM Campaign Link','Referral Website','Paid Ads','Direct Entry','Unknown'];
}
