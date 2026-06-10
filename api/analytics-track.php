<?php
require_once __DIR__ . '/../core/bootstrap.php';

function analyticsTrackJson(array $payload): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function analyticsString($value, int $max = 255): string {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return substr($value, 0, max(0, $max));
}

function analyticsTrackString($value, int $max = 255): string {
    return analyticsString($value, $max);
}

function analyticsTrackUuid($value): string {
    $value = strtolower(analyticsString($value, 64));
    if (preg_match('/^[a-f0-9-]{32,64}$/', $value)) {
        return $value;
    }
    return bin2hex(random_bytes(16));
}

function analyticsTrackIgnoredPath(string $path): bool {
    $path = '/' . ltrim($path, '/');
    foreach (['/admin/', '/api/', '/assets/', '/uploads/'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }
    return $path === '/install.php';
}

function analyticsTrackUserAgent(string $ua): array {
    $isBot = (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|headless|monitor|uptime|validator/i', $ua);
    $browser = 'Unknown';
    if (stripos($ua, 'Edg/') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Chrome/') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Safari/') !== false) $browser = 'Safari';
    elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';

    $os = 'Unknown';
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
    elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

    $device = 'desktop';
    if ($isBot) $device = 'bot';
    elseif (stripos($ua, 'tablet') !== false || stripos($ua, 'iPad') !== false) $device = 'tablet';
    elseif (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false) $device = 'mobile';

    return ['browser' => $browser, 'os' => $os, 'device_type' => $device, 'is_bot' => $isBot ? 1 : 0];
}

function analyticsTrackClassifySource(array $payload): array {
    $utmSource = analyticsString($payload['utm_source'] ?? '', 150);
    $utmMedium = analyticsString($payload['utm_medium'] ?? '', 150);
    $utmCampaign = analyticsString($payload['utm_campaign'] ?? '', 150);
    $referrer = analyticsString($payload['referrer'] ?? '', 500);

    if ($utmSource !== '' || $utmMedium !== '' || $utmCampaign !== '') {
        return ['source' => $utmSource !== '' ? $utmSource : 'campaign', 'medium' => 'campaign', 'campaign' => $utmCampaign, 'class' => 'campaign'];
    }
    if ($referrer === '') {
        return ['source' => 'direct', 'medium' => 'direct', 'campaign' => '', 'class' => 'direct'];
    }
    $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
    $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || ($currentHost !== '' && $host === $currentHost)) {
        return ['source' => 'direct', 'medium' => 'direct', 'campaign' => '', 'class' => 'direct'];
    }
    $organicHosts = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'yandex.'];
    foreach ($organicHosts as $needle) {
        if (strpos($host, $needle) !== false) {
            return ['source' => $host, 'medium' => 'organic', 'campaign' => '', 'class' => 'organic'];
        }
    }
    $socialHosts = ['instagram.', 'facebook.', 'twitter.', 'x.com', 'linkedin.', 't.me', 'telegram.', 'pinterest.', 'youtube.', 'whatsapp.'];
    foreach ($socialHosts as $needle) {
        if (strpos($host, $needle) !== false) {
            return ['source' => $host, 'medium' => 'social', 'campaign' => '', 'class' => 'social'];
        }
    }
    return ['source' => $host !== '' ? $host : 'unknown', 'medium' => 'referral', 'campaign' => '', 'class' => $host !== '' ? 'referral' : 'unknown'];
}

function analyticsTrackDetectSourceLabel(array $source, array $payload): string {
    $raw = strtolower(($source['source'] ?? '') . ' ' . ($payload['utm_source'] ?? '') . ' ' . ($payload['utm_medium'] ?? '') . ' ' . ($payload['referrer'] ?? ''));
    if (str_contains($raw, 'instagram')) return 'Instagram';
    if (str_contains($raw, 'telegram') || str_contains($raw, 't.me')) return 'Telegram';
    if (str_contains($raw, 'whatsapp')) return 'WhatsApp';
    if (str_contains($raw, 'sms')) return 'SMS';
    if (str_contains($raw, 'maps.google') || str_contains($raw, 'google maps')) return 'Google Maps';
    if (str_contains($raw, 'google')) return 'Google Search';
    if (str_contains($raw, 'qr')) return 'QR Code';
    if (str_contains($raw, 'crm')) return 'CRM Campaign Link';
    if (str_contains($raw, 'cpc') || str_contains($raw, 'paid') || str_contains($raw, 'ads')) return 'Paid Ads';
    if (($source['medium'] ?? '') === 'referral') return 'Referral Website';
    if (($source['medium'] ?? '') === 'direct') return 'Direct Entry';
    return 'Unknown';
}

function analyticsTrackTargetAction(string $path): array {
    $path = strtolower($path);
    if ($path === '' || $path === '/' || $path === '/index.php') return ['page_view', 'home'];
    if (str_contains($path, 'prediction')) return ['prediction_submit', 'predictions'];
    if (str_contains($path, 'survey-submit') || str_contains($path, 'survey/submit')) return ['survey_submit', 'dynamic_forms'];
    if (str_contains($path, 'survey')) return ['survey_view', 'dynamic_forms'];
    if (str_contains($path, 'customer-club') || str_contains($path, 'club') || str_contains($path, 'newsletter')) return ['customer_club_signup', 'crm_customers'];
    if (str_contains($path, 'crm')) return ['crm_link_entry', 'crm_customers'];
    if (str_contains($path, 'menu-item') || str_contains($path, 'item')) return ['menu_item_view', 'menu_items'];
    if (str_contains($path, 'category') || str_contains($path, 'menu')) return ['category_view', 'menu_categories'];
    if (str_contains($path, 'banner')) return ['banner_click', 'hero_banners'];
    if (str_contains($path, 'match') || str_contains($path, 'campaign')) return ['match_view', 'matches'];
    return ['page_view', 'home'];
}

function analyticsTrackEventType(string $targetAction): string {
    $allowed = [
        'page_view' => 'page_view',
        'category_view' => 'category_view',
        'menu_item_view' => 'menu_item_view',
        'banner_click' => 'banner_click',
        'match_view' => 'match_view',
        'prediction_submit' => 'prediction_submit',
        'survey_view' => 'survey_view',
        'survey_submit' => 'survey_submit',
        'crm_link_entry' => 'crm_link_entry',
    ];
    if ($targetAction === 'customer_club_signup') {
        return 'crm_link_entry';
    }
    return $allowed[$targetAction] ?? 'page_view';
}

function analyticsTrackTableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Analytics table check failed: ' . $e->getMessage());
        return false;
    }
}

function analyticsTrackColumnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE :column');
        $stmt->execute(['column' => $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log("Analytics column check failed for {$table}.{$column}: " . $e->getMessage());
        return false;
    }
}

function analyticsTrackIndexExists(PDO $db, string $table, string $index): bool {
    try {
        $stmt = $db->prepare('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = :index_name');
        $stmt->execute(['index_name' => $index]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log("Analytics index check failed for {$table}.{$index}: " . $e->getMessage());
        return false;
    }
}

function analyticsTrackExecSafe(PDO $db, string $sql, string $label): void {
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        error_log("Analytics schema repair failed ({$label}): " . $e->getMessage());
    }
}

function analyticsTrackEnsureColumns(PDO $db, string $table, array $columns): void {
    if (!analyticsTrackTableExists($db, $table)) {
        return;
    }
    foreach ($columns as $column => $definition) {
        if (!analyticsTrackColumnExists($db, $table, $column)) {
            analyticsTrackExecSafe($db, 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . "` {$definition}", "add column {$table}.{$column}");
        }
    }
}

function analyticsTrackEnsureIndexes(PDO $db, string $table, array $indexes): void {
    if (!analyticsTrackTableExists($db, $table)) {
        return;
    }
    foreach ($indexes as $index => $definition) {
        if (!analyticsTrackIndexExists($db, $table, $index)) {
            analyticsTrackExecSafe($db, "ALTER TABLE `" . str_replace('`', '``', $table) . "` ADD {$definition}", "add index {$table}.{$index}");
        }
    }
}

function analyticsTrackEnsureAnalyticsSchema(PDO $db): void {
    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `analytics_visitors` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create analytics_visitors');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `analytics_sessions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create analytics_sessions');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `analytics_pageviews` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create analytics_pageviews');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `visitor_analytics_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create visitor_analytics_logs');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `traffic_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create traffic_logs');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `traffic_sources` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create traffic_sources');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `visitor_sessions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create visitor_sessions');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `visitor_locations` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `country` varchar(100) NOT NULL DEFAULT 'Unknown',
        `city` varchar(100) NOT NULL DEFAULT 'Unknown',
        `visits_count` int(11) NOT NULL DEFAULT 0,
        `date` date NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_location_date` (`country`, `city`, `date`),
        KEY `idx_location_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create visitor_locations');

    analyticsTrackExecSafe($db, "CREATE TABLE IF NOT EXISTS `traffic_statistics` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create traffic_statistics');

    analyticsTrackRepairAnalyticsSchema($db);
}

function analyticsTrackRepairAnalyticsSchema(PDO $db): void {
    analyticsTrackEnsureColumns($db, 'analytics_visitors', [
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
    analyticsTrackEnsureColumns($db, 'analytics_sessions', [
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
    analyticsTrackEnsureColumns($db, 'analytics_pageviews', [
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
    analyticsTrackEnsureColumns($db, 'visitor_analytics_logs', [
        'session_id' => "varchar(64) NOT NULL DEFAULT ''",
        'user_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'customer_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'source_type' => 'varchar(100) DEFAULT NULL',
        'source_name' => 'varchar(150) DEFAULT NULL',
        'campaign_type' => 'varchar(100) DEFAULT NULL',
        'entry_link' => 'varchar(500) DEFAULT NULL',
        'referrer_url' => 'varchar(500) DEFAULT NULL',
        'utm_source' => 'varchar(100) DEFAULT NULL',
        'utm_medium' => 'varchar(100) DEFAULT NULL',
        'utm_campaign' => 'varchar(150) DEFAULT NULL',
        'landing_page' => 'varchar(500) DEFAULT NULL',
        'current_page' => 'varchar(500) DEFAULT NULL',
        'next_page' => 'varchar(500) DEFAULT NULL',
        'related_module' => 'varchar(100) DEFAULT NULL',
        'related_record_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'event_type' => "enum('external_entry','page_view','banner_view','banner_click','match_view','prediction_start','prediction_submit','category_view','menu_item_view','survey_view','survey_start','survey_submit','crm_link_entry','exit') NOT NULL DEFAULT 'page_view'",
        'target_action' => 'varchar(100) DEFAULT NULL',
        'device_type' => 'varchar(50) DEFAULT NULL',
        'browser' => 'varchar(100) DEFAULT NULL',
        'operating_system' => 'varchar(100) DEFAULT NULL',
        'ip_address' => 'varchar(64) DEFAULT NULL',
        'branch_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'is_new_visitor' => 'tinyint(1) NOT NULL DEFAULT 0',
        'is_converted' => 'tinyint(1) NOT NULL DEFAULT 0',
        'duration_seconds' => 'int(11) DEFAULT NULL',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    analyticsTrackEnsureColumns($db, 'traffic_logs', [
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
    analyticsTrackEnsureColumns($db, 'traffic_sources', [
        'source_name' => "varchar(100) NOT NULL DEFAULT 'unknown'",
        'source_type' => "varchar(50) NOT NULL DEFAULT 'unknown'",
        'visits_count' => 'int(11) NOT NULL DEFAULT 0',
        'date' => "date NOT NULL DEFAULT '1970-01-01'",
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    analyticsTrackEnsureColumns($db, 'visitor_sessions', [
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
    analyticsTrackEnsureColumns($db, 'visitor_locations', [
        'country' => "varchar(100) NOT NULL DEFAULT 'Unknown'",
        'city' => "varchar(100) NOT NULL DEFAULT 'Unknown'",
        'visits_count' => 'int(11) NOT NULL DEFAULT 0',
        'date' => "date NOT NULL DEFAULT '1970-01-01'",
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);
    analyticsTrackEnsureColumns($db, 'traffic_statistics', [
        'stat_date' => "date NOT NULL DEFAULT '1970-01-01'",
        'total_visits' => 'int(11) NOT NULL DEFAULT 0',
        'unique_visitors' => 'int(11) NOT NULL DEFAULT 0',
        'total_page_views' => 'int(11) NOT NULL DEFAULT 0',
        'bounce_rate' => 'decimal(5,2) DEFAULT NULL',
        'avg_duration' => 'int(11) DEFAULT NULL',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ]);

    analyticsTrackEnsureIndexes($db, 'analytics_visitors', [
        'uniq_analytics_visitor_uuid' => 'UNIQUE KEY `uniq_analytics_visitor_uuid` (`visitor_uuid`)',
        'idx_analytics_visitors_device' => 'INDEX `idx_analytics_visitors_device` (`device_type`)',
        'idx_analytics_visitors_browser' => 'INDEX `idx_analytics_visitors_browser` (`browser`)',
        'idx_analytics_visitors_os' => 'INDEX `idx_analytics_visitors_os` (`os`)',
        'idx_analytics_visitors_country' => 'INDEX `idx_analytics_visitors_country` (`country`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'analytics_sessions', [
        'uniq_analytics_session_uuid' => 'UNIQUE KEY `uniq_analytics_session_uuid` (`session_uuid`)',
        'idx_analytics_sessions_visitor' => 'INDEX `idx_analytics_sessions_visitor` (`visitor_uuid`)',
        'idx_analytics_sessions_started' => 'INDEX `idx_analytics_sessions_started` (`started_at`)',
        'idx_analytics_sessions_activity' => 'INDEX `idx_analytics_sessions_activity` (`last_activity_at`)',
        'idx_analytics_sessions_source' => 'INDEX `idx_analytics_sessions_source` (`source`)',
        'idx_analytics_sessions_medium' => 'INDEX `idx_analytics_sessions_medium` (`medium`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'analytics_pageviews', [
        'idx_analytics_pageviews_visitor' => 'INDEX `idx_analytics_pageviews_visitor` (`visitor_uuid`)',
        'idx_analytics_pageviews_session' => 'INDEX `idx_analytics_pageviews_session` (`session_uuid`)',
        'idx_analytics_pageviews_viewed' => 'INDEX `idx_analytics_pageviews_viewed` (`viewed_at`)',
        'idx_analytics_pageviews_path' => 'INDEX `idx_analytics_pageviews_path` (`page_path`(191))',
    ]);
    analyticsTrackEnsureIndexes($db, 'visitor_analytics_logs', [
        'idx_visitor_logs_session' => 'INDEX `idx_visitor_logs_session` (`session_id`)',
        'idx_visitor_logs_source' => 'INDEX `idx_visitor_logs_source` (`source_type`, `source_name`)',
        'idx_visitor_logs_pages' => 'INDEX `idx_visitor_logs_pages` (`landing_page`(191), `current_page`(191), `next_page`(191))',
        'idx_visitor_logs_action' => 'INDEX `idx_visitor_logs_action` (`target_action`, `is_converted`)',
        'idx_visitor_logs_related' => 'INDEX `idx_visitor_logs_related` (`related_module`, `related_record_id`)',
        'idx_visitor_logs_created' => 'INDEX `idx_visitor_logs_created` (`created_at`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'traffic_logs', [
        'idx_traffic_session' => 'INDEX `idx_traffic_session` (`session_id`)',
        'idx_traffic_date' => 'INDEX `idx_traffic_date` (`created_at`)',
        'idx_traffic_country' => 'INDEX `idx_traffic_country` (`country`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'traffic_sources', [
        'uniq_source_date' => 'UNIQUE KEY `uniq_source_date` (`source_name`, `date`)',
        'idx_source_type' => 'INDEX `idx_source_type` (`source_type`)',
        'idx_source_date' => 'INDEX `idx_source_date` (`date`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'visitor_sessions', [
        'uniq_session' => 'UNIQUE KEY `uniq_session` (`session_id`)',
        'idx_session_active' => 'INDEX `idx_session_active` (`is_active`, `last_activity`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'visitor_locations', [
        'uniq_location_date' => 'UNIQUE KEY `uniq_location_date` (`country`, `city`, `date`)',
        'idx_location_date' => 'INDEX `idx_location_date` (`date`)',
    ]);
    analyticsTrackEnsureIndexes($db, 'traffic_statistics', [
        'uniq_stat_date' => 'UNIQUE KEY `uniq_stat_date` (`stat_date`)',
    ]);
}

function analyticsTrackEnsurePathTable(PDO $db): void {
    analyticsTrackEnsureAnalyticsSchema($db);
}

function analyticsTrackEnsureTables(PDO $db): void {
    analyticsTrackEnsureAnalyticsSchema($db);

    $schema = ROOT_PATH . '/database/schema.sql';
    if (!is_readable($schema)) {
        return;
    }
    $sql = file_get_contents($schema);
    if ($sql === false) {
        return;
    }
    $allowedTables = [
        'schema_migrations' => true,
        'traffic_logs' => true,
        'traffic_sources' => true,
        'visitor_sessions' => true,
        'visitor_locations' => true,
        'traffic_statistics' => true,
        'analytics_visitors' => true,
        'analytics_sessions' => true,
        'analytics_pageviews' => true,
        'visitor_analytics_logs' => true,
    ];
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $statement, $match, PREG_OFFSET_CAPTURE) && isset($allowedTables[$match[1][0]])) {
            analyticsTrackExecSafe($db, substr($statement, $match[0][1]), 'schema.sql create ' . $match[1][0]);
        }
    }

    analyticsTrackRepairAnalyticsSchema($db);
}

function analyticsTrackTablesStatus(PDO $db): array {
    $tables = [
        'analytics_visitors',
        'analytics_sessions',
        'analytics_pageviews',
        'visitor_analytics_logs',
        'traffic_logs',
        'traffic_sources',
        'visitor_sessions',
        'visitor_locations',
        'traffic_statistics',
    ];
    $status = [];
    foreach ($tables as $table) {
        $status[$table] = analyticsTrackTableExists($db, $table);
    }
    return $status;
}

function analyticsTrackHealth(): void {
    try {
        $db = Database::getInstance()->getConnection();
        analyticsTrackJson([
            'ok' => true,
            'database' => 'connected',
            'tables' => analyticsTrackTablesStatus($db),
        ]);
    } catch (Throwable $e) {
        error_log('Analytics health failed: ' . $e->getMessage());
        analyticsTrackJson([
            'ok' => false,
            'database' => 'unavailable',
            'tables' => [],
        ]);
    }
}

function analyticsTrackRequestContentType(): string {
    return strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''), 2)[0]));
}

function analyticsTrackDecodePayload(): ?array {
    $contentType = analyticsTrackRequestContentType();
    if ($contentType !== '' && !in_array($contentType, ['application/json', 'text/plain'], true)) {
        return null;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '' || strlen($raw) > 20000) {
        return null;
    }

    try {
        $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('Analytics invalid JSON payload: ' . $e->getMessage());
        return null;
    }

    if (!is_array($payload) || array_is_list($payload)) {
        return null;
    }
    if (!array_key_exists('page_path', $payload) && !array_key_exists('page_url', $payload)) {
        return null;
    }

    return $payload;
}

function analyticsTrackIpHash(): ?string {
    $ip = analyticsString($_SERVER['REMOTE_ADDR'] ?? '', 100);
    return $ip !== '' ? hash('sha256', $ip . '|' . DB_NAME) : null;
}

function analyticsTrackWriteLegacyTables(PDO $db, array $data): void {
    try {
        $trafficStmt = $db->prepare('INSERT INTO traffic_logs (session_id, ip_address, country, city, referrer, landing_page, user_agent, browser, os, device, language, pages_viewed, is_bot, created_at) VALUES (:session_id, :ip_address, :country, :city, :referrer, :landing_page, :user_agent, :browser, :os, :device, :language, 1, :is_bot, :created_at)');
        $trafficStmt->execute([
            'session_id' => $data['session_uuid'],
            'ip_address' => $data['ip_hash'],
            'country' => 'Unknown',
            'city' => 'Unknown',
            'referrer' => $data['referrer'],
            'landing_page' => $data['page_path'],
            'user_agent' => $data['user_agent'],
            'browser' => $data['browser'],
            'os' => $data['os'],
            'device' => $data['device_type'],
            'language' => $data['language'],
            'is_bot' => $data['is_bot'],
            'created_at' => $data['now'],
        ]);
    } catch (Throwable $e) {
        error_log('Analytics legacy traffic_logs write failed: ' . $e->getMessage());
    }

    try {
        $sessionStmt = $db->prepare('INSERT INTO visitor_sessions (session_id, ip_address, started_at, last_activity, is_active, current_page, source_name, device_type, browser, os) VALUES (:session_id, :ip_address, :now, :now, 1, :current_page, :source_name, :device_type, :browser, :os) ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity), is_active = 1, current_page = VALUES(current_page), source_name = VALUES(source_name), device_type = VALUES(device_type), browser = VALUES(browser), os = VALUES(os)');
        $sessionStmt->execute([
            'session_id' => $data['session_uuid'],
            'ip_address' => $data['ip_hash'],
            'now' => $data['now'],
            'current_page' => $data['page_path'],
            'source_name' => $data['source_name'],
            'device_type' => $data['device_type'],
            'browser' => $data['browser'],
            'os' => $data['os'],
        ]);
    } catch (Throwable $e) {
        error_log('Analytics legacy visitor_sessions write failed: ' . $e->getMessage());
    }

    try {
        $sourceStmt = $db->prepare('INSERT INTO traffic_sources (source_name, source_type, visits_count, date) VALUES (:source_name, :source_type, 1, :date) ON DUPLICATE KEY UPDATE visits_count = visits_count + 1');
        $sourceStmt->execute([
            'source_name' => $data['source_name'],
            'source_type' => $data['source_type'],
            'date' => $data['stat_date'],
        ]);
    } catch (Throwable $e) {
        error_log('Analytics legacy traffic_sources write failed: ' . $e->getMessage());
    }

    try {
        $locationStmt = $db->prepare('INSERT INTO visitor_locations (country, city, visits_count, date) VALUES (:country, :city, 1, :date) ON DUPLICATE KEY UPDATE visits_count = visits_count + 1');
        $locationStmt->execute([
            'country' => 'Unknown',
            'city' => 'Unknown',
            'date' => $data['stat_date'],
        ]);
    } catch (Throwable $e) {
        error_log('Analytics legacy visitor_locations write failed: ' . $e->getMessage());
    }

    try {
        $statsStmt = $db->prepare('INSERT INTO traffic_statistics (stat_date, total_visits, unique_visitors, total_page_views, bounce_rate, avg_duration) VALUES (:stat_date, 1, :unique_visitors, 1, NULL, NULL) ON DUPLICATE KEY UPDATE total_visits = total_visits + 1, total_page_views = total_page_views + 1, unique_visitors = unique_visitors + :unique_visitors_update');
        $statsStmt->execute([
            'stat_date' => $data['stat_date'],
            'unique_visitors' => $data['is_new_session'],
            'unique_visitors_update' => $data['is_new_session'],
        ]);
    } catch (Throwable $e) {
        error_log('Analytics legacy traffic_statistics write failed: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['health'] ?? '') === '1') {
    analyticsTrackHealth();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    analyticsTrackJson(['ok' => false, 'error' => 'method_not_allowed']);
}

try {
    $payload = analyticsTrackDecodePayload();
    if ($payload === null) {
        analyticsTrackJson(['ok' => false, 'error' => 'invalid_payload']);
    }

    $pagePath = analyticsString($payload['page_path'] ?? '/', 500);
    if (analyticsTrackIgnoredPath($pagePath)) {
        analyticsTrackJson(['ok' => true]);
    }

    $db = Database::getInstance()->getConnection();
    analyticsTrackEnsureTables($db);
    analyticsTrackEnsurePathTable($db);

    $now = date('Y-m-d H:i:s');
    $visitorUuid = analyticsTrackUuid($payload['visitor_uuid'] ?? '');
    $sessionUuid = analyticsTrackUuid($payload['session_uuid'] ?? '');
    $ua = analyticsString($_SERVER['HTTP_USER_AGENT'] ?? '', 1000);
    $parsed = analyticsTrackUserAgent($ua);
    $ipHash = analyticsTrackIpHash();
    $source = analyticsTrackClassifySource($payload);
    $source['source'] = analyticsString($source['source'] ?? 'unknown', 100);
    $source['medium'] = analyticsString($source['medium'] ?? 'unknown', 100);
    $source['campaign'] = analyticsString($source['campaign'] ?? '', 150);

    $pageUrl = analyticsString($payload['page_url'] ?? '', 1000);
    $pageTitle = analyticsString($payload['page_title'] ?? '', 255);
    $referrer = analyticsString($payload['referrer'] ?? '', 500);
    $language = analyticsString($payload['language'] ?? '', 50);
    $timezone = analyticsString($payload['timezone'] ?? '', 100);
    $utmSource = analyticsString($payload['utm_source'] ?? '', 150);
    $utmMedium = analyticsString($payload['utm_medium'] ?? '', 150);
    $utmCampaign = analyticsString($payload['utm_campaign'] ?? '', 150);
    $utmTerm = analyticsString($payload['utm_term'] ?? '', 150);
    $utmContent = analyticsString($payload['utm_content'] ?? '', 150);
    $screenWidth = max(0, min(100000, (int)($payload['screen_width'] ?? 0))) ?: null;
    $screenHeight = max(0, min(100000, (int)($payload['screen_height'] ?? 0))) ?: null;

    $visitorStmt = $db->prepare('INSERT INTO analytics_visitors (visitor_uuid, first_seen_at, last_seen_at, ip_hash, user_agent, browser, os, device_type, country, city) VALUES (:visitor_uuid, :now, :now, :ip_hash, :user_agent, :browser, :os, :device_type, :country, :city) ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at), ip_hash = VALUES(ip_hash), user_agent = VALUES(user_agent), browser = VALUES(browser), os = VALUES(os), device_type = VALUES(device_type), updated_at = CURRENT_TIMESTAMP');
    $visitorStmt->execute([
        'visitor_uuid' => $visitorUuid,
        'now' => $now,
        'ip_hash' => $ipHash,
        'user_agent' => $ua,
        'browser' => $parsed['browser'],
        'os' => $parsed['os'],
        'device_type' => $parsed['device_type'],
        'country' => 'Unknown',
        'city' => 'Unknown',
    ]);

    $sessionStmt = $db->prepare('INSERT INTO analytics_sessions (session_uuid, visitor_uuid, started_at, last_activity_at, landing_page, referrer, source, medium, campaign, utm_source, utm_medium, utm_campaign, utm_term, utm_content) VALUES (:session_uuid, :visitor_uuid, :now, :now, :landing_page, :referrer, :source, :medium, :campaign, :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content) ON DUPLICATE KEY UPDATE last_activity_at = VALUES(last_activity_at), referrer = COALESCE(NULLIF(referrer, ""), VALUES(referrer)), source = VALUES(source), medium = VALUES(medium), campaign = VALUES(campaign), updated_at = CURRENT_TIMESTAMP');
    $sessionStmt->execute([
        'session_uuid' => $sessionUuid,
        'visitor_uuid' => $visitorUuid,
        'now' => $now,
        'landing_page' => $pagePath,
        'referrer' => $referrer,
        'source' => $source['source'],
        'medium' => $source['medium'],
        'campaign' => $source['campaign'],
        'utm_source' => $utmSource,
        'utm_medium' => $utmMedium,
        'utm_campaign' => $utmCampaign,
        'utm_term' => $utmTerm,
        'utm_content' => $utmContent,
    ]);

    $pageStmt = $db->prepare('INSERT INTO analytics_pageviews (visitor_uuid, session_uuid, page_url, page_path, page_title, referrer, screen_width, screen_height, browser_language, timezone, viewed_at) VALUES (:visitor_uuid, :session_uuid, :page_url, :page_path, :page_title, :referrer, :screen_width, :screen_height, :browser_language, :timezone, :viewed_at)');
    $pageStmt->execute([
        'visitor_uuid' => $visitorUuid,
        'session_uuid' => $sessionUuid,
        'page_url' => $pageUrl,
        'page_path' => $pagePath,
        'page_title' => $pageTitle,
        'referrer' => $referrer,
        'screen_width' => $screenWidth,
        'screen_height' => $screenHeight,
        'browser_language' => $language,
        'timezone' => $timezone,
        'viewed_at' => $now,
    ]);

    $previousStmt = $db->prepare('SELECT id, current_page, landing_page, created_at FROM visitor_analytics_logs WHERE session_id = :session_id ORDER BY id DESC LIMIT 1');
    $previousStmt->execute(['session_id' => $sessionUuid]);
    $previous = $previousStmt->fetch();
    if ($previous && !empty($previous['id'])) {
        $duration = max(0, strtotime($now) - strtotime((string)$previous['created_at']));
        $db->prepare('UPDATE visitor_analytics_logs SET next_page = :next_page, duration_seconds = COALESCE(duration_seconds, :duration) WHERE id = :id')
            ->execute(['next_page' => $pagePath, 'duration' => $duration, 'id' => $previous['id']]);
    }
    [$targetAction, $relatedModule] = analyticsTrackTargetAction($pagePath);
    $isNewSession = $previous ? 0 : 1;
    $isConverted = in_array($targetAction, ['prediction_submit','survey_submit','customer_club_signup','menu_item_view','banner_click','campaign_click'], true) ? 1 : 0;
    $eventType = analyticsTrackEventType($targetAction);
    $pathStmt = $db->prepare('INSERT INTO visitor_analytics_logs (session_id, source_type, source_name, campaign_type, entry_link, referrer_url, utm_source, utm_medium, utm_campaign, landing_page, current_page, related_module, event_type, target_action, device_type, browser, operating_system, ip_address, is_new_visitor, is_converted, created_at) VALUES (:session_id, :source_type, :source_name, :campaign_type, :entry_link, :referrer_url, :utm_source, :utm_medium, :utm_campaign, :landing_page, :current_page, :related_module, :event_type, :target_action, :device_type, :browser, :operating_system, :ip_address, :is_new_visitor, :is_converted, :created_at)');
    $pathStmt->execute([
        'session_id' => $sessionUuid,
        'source_type' => analyticsTrackDetectSourceLabel($source, $payload),
        'source_name' => $source['source'],
        'campaign_type' => $source['medium'],
        'entry_link' => $pageUrl,
        'referrer_url' => $referrer,
        'utm_source' => $utmSource,
        'utm_medium' => $utmMedium,
        'utm_campaign' => $utmCampaign,
        'landing_page' => $previous ? (($previous['landing_page'] ?? '') ?: $pagePath) : $pagePath,
        'current_page' => $pagePath,
        'related_module' => $relatedModule,
        'event_type' => $eventType,
        'target_action' => $targetAction,
        'device_type' => $parsed['device_type'],
        'browser' => $parsed['browser'],
        'operating_system' => $parsed['os'],
        'ip_address' => $ipHash,
        'is_new_visitor' => $isNewSession,
        'is_converted' => $isConverted,
        'created_at' => $now,
    ]);

    analyticsTrackWriteLegacyTables($db, [
        'session_uuid' => $sessionUuid,
        'ip_hash' => $ipHash,
        'referrer' => $referrer,
        'page_path' => $pagePath,
        'user_agent' => $ua,
        'browser' => $parsed['browser'],
        'os' => $parsed['os'],
        'device_type' => $parsed['device_type'],
        'language' => $language,
        'is_bot' => $parsed['is_bot'],
        'source_name' => analyticsString($source['source'] ?? 'unknown', 150),
        'source_type' => analyticsString($source['medium'] ?? 'unknown', 50),
        'stat_date' => date('Y-m-d', strtotime($now)),
        'is_new_session' => $isNewSession,
        'now' => $now,
    ]);

    analyticsTrackJson(['ok' => true]);
} catch (Throwable $e) {
    error_log('Analytics tracking failed: ' . $e->getMessage());
    analyticsTrackJson(['ok' => false, 'error' => 'tracking_failed']);
}
