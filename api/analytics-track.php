<?php
require_once __DIR__ . '/../core/bootstrap.php';

function analyticsTrackJson(array $payload): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function analyticsTrackString($value, int $max = 255): string {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return substr($value, 0, $max);
}

function analyticsTrackUuid($value): string {
    $value = strtolower(analyticsTrackString($value, 64));
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
    if (stripos($ua, 'bot') !== false || stripos($ua, 'crawl') !== false || stripos($ua, 'spider') !== false) $device = 'bot';
    elseif (stripos($ua, 'tablet') !== false || stripos($ua, 'iPad') !== false) $device = 'tablet';
    elseif (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false) $device = 'mobile';

    return ['browser' => $browser, 'os' => $os, 'device_type' => $device];
}

function analyticsTrackClassifySource(array $payload): array {
    $utmSource = analyticsTrackString($payload['utm_source'] ?? '', 100);
    $utmMedium = analyticsTrackString($payload['utm_medium'] ?? '', 100);
    $utmCampaign = analyticsTrackString($payload['utm_campaign'] ?? '', 150);
    $referrer = analyticsTrackString($payload['referrer'] ?? '', 500);

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
    if (str_contains($path, 'prediction')) return ['prediction_submit', 'predictions'];
    if (str_contains($path, 'survey')) return ['survey_start', 'surveys'];
    if (str_contains($path, 'menu-item') || str_contains($path, 'item')) return ['menu_item_view', 'menu-items'];
    if (str_contains($path, 'category') || str_contains($path, 'menu')) return ['category_view', 'categories'];
    if (str_contains($path, 'match') || str_contains($path, 'campaign')) return ['match_view', 'matches'];
    if (str_contains($path, 'banner')) return ['banner_interaction', 'banners'];
    if (str_contains($path, 'crm')) return ['crm_link_entry', 'crm'];
    return ['menu_view', 'home'];
}

function analyticsTrackEnsurePathTable(PDO $db): void {
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
        KEY `idx_visitor_logs_action` (`target_action`, `is_converted`),
        KEY `idx_visitor_logs_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function analyticsTrackEnsureTables(PDO $db): void {
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
            $db->exec(substr($statement, $match[0][1]));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    analyticsTrackJson(['ok' => false, 'error' => 'tracking_failed']);
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '{}', true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid payload');
    }

    $pagePath = analyticsTrackString($payload['page_path'] ?? '/', 500);
    if (analyticsTrackIgnoredPath($pagePath)) {
        analyticsTrackJson(['ok' => true]);
    }

    $db = Database::getInstance()->getConnection();
    analyticsTrackEnsureTables($db);
    analyticsTrackEnsurePathTable($db);

    $now = date('Y-m-d H:i:s');
    $visitorUuid = analyticsTrackUuid($payload['visitor_uuid'] ?? '');
    $sessionUuid = analyticsTrackUuid($payload['session_uuid'] ?? '');
    $ua = analyticsTrackString($_SERVER['HTTP_USER_AGENT'] ?? '', 1000);
    $parsed = analyticsTrackUserAgent($ua);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = $ip !== '' ? hash('sha256', $ip . '|' . DB_NAME) : null;
    $source = analyticsTrackClassifySource($payload);

    $pageUrl = analyticsTrackString($payload['page_url'] ?? '', 1000);
    $pageTitle = analyticsTrackString($payload['page_title'] ?? '', 255);
    $referrer = analyticsTrackString($payload['referrer'] ?? '', 500);
    $language = analyticsTrackString($payload['language'] ?? '', 50);
    $timezone = analyticsTrackString($payload['timezone'] ?? '', 100);
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
        'utm_source' => analyticsTrackString($payload['utm_source'] ?? '', 100),
        'utm_medium' => analyticsTrackString($payload['utm_medium'] ?? '', 100),
        'utm_campaign' => analyticsTrackString($payload['utm_campaign'] ?? '', 150),
        'utm_term' => analyticsTrackString($payload['utm_term'] ?? '', 150),
        'utm_content' => analyticsTrackString($payload['utm_content'] ?? '', 150),
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
    $isConverted = in_array($targetAction, ['prediction_submit','survey_submit','menu_item_view','banner_interaction','campaign_click'], true) ? 1 : 0;
    $eventType = $previous ? 'page_view' : 'external_entry';
    if ($targetAction === 'prediction_submit') $eventType = 'prediction_submit';
    elseif ($targetAction === 'survey_start') $eventType = 'survey_view';
    elseif ($targetAction === 'menu_item_view') $eventType = 'menu_item_view';
    elseif ($targetAction === 'category_view') $eventType = 'category_view';
    elseif ($targetAction === 'match_view') $eventType = 'match_view';
    elseif ($targetAction === 'banner_interaction') $eventType = 'banner_click';
    elseif ($targetAction === 'crm_link_entry') $eventType = 'crm_link_entry';
    $pathStmt = $db->prepare('INSERT INTO visitor_analytics_logs (session_id, source_type, source_name, campaign_type, entry_link, referrer_url, utm_source, utm_medium, utm_campaign, landing_page, current_page, related_module, event_type, target_action, device_type, browser, operating_system, ip_address, is_new_visitor, is_converted, created_at) VALUES (:session_id, :source_type, :source_name, :campaign_type, :entry_link, :referrer_url, :utm_source, :utm_medium, :utm_campaign, :landing_page, :current_page, :related_module, :event_type, :target_action, :device_type, :browser, :operating_system, :ip_address, :is_new_visitor, :is_converted, :created_at)');
    $pathStmt->execute([
        'session_id' => $sessionUuid,
        'source_type' => analyticsTrackDetectSourceLabel($source, $payload),
        'source_name' => $source['source'],
        'campaign_type' => $source['medium'],
        'entry_link' => $pageUrl,
        'referrer_url' => $referrer,
        'utm_source' => analyticsTrackString($payload['utm_source'] ?? '', 100),
        'utm_medium' => analyticsTrackString($payload['utm_medium'] ?? '', 100),
        'utm_campaign' => analyticsTrackString($payload['utm_campaign'] ?? '', 150),
        'landing_page' => $previous ? (($previous['landing_page'] ?? '') ?: $pagePath) : $pagePath,
        'current_page' => $pagePath,
        'related_module' => $relatedModule,
        'event_type' => $eventType,
        'target_action' => $targetAction,
        'device_type' => $parsed['device_type'],
        'browser' => $parsed['browser'],
        'operating_system' => $parsed['os'],
        'ip_address' => $ipHash,
        'is_new_visitor' => $previous ? 0 : 1,
        'is_converted' => $isConverted,
        'created_at' => $now,
    ]);

    analyticsTrackJson(['ok' => true]);
} catch (Throwable $e) {
    error_log('Analytics tracking failed: ' . $e->getMessage());
    analyticsTrackJson(['ok' => false, 'error' => 'tracking_failed']);
}
