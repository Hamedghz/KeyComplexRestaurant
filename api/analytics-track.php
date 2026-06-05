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

function analyticsTrackEnsureTables(PDO $db): void {
    $schema = ROOT_PATH . '/database/migrations/2026_06_05_runtime_analytics.sql';
    if (!is_readable($schema)) {
        return;
    }
    $sql = file_get_contents($schema);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if (stripos($statement, 'CREATE TABLE') === 0) {
            $db->exec($statement);
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

    analyticsTrackJson(['ok' => true]);
} catch (Throwable $e) {
    error_log('Analytics tracking failed: ' . $e->getMessage());
    analyticsTrackJson(['ok' => false, 'error' => 'tracking_failed']);
}
