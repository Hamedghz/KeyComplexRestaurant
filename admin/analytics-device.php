<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'گزارش دستگاه‌ها';
$error = '';
$emptyState = 'در این بازه داده‌ای برای دستگاه‌ها پیدا نشد. اگر سایت عمومی بازدید داشته، فعال بودن assets/js/analytics-tracker.js و مسیر api/analytics-track.php را بررسی کنید.';
$range = (string)($_GET['range'] ?? ((isset($_GET['date_from']) || isset($_GET['date_to'])) ? 'custom' : 'last30'));
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$rangeMap = [
    'today' => [$today, $today],
    'yesterday' => [$yesterday, $yesterday],
    'last7' => [date('Y-m-d', strtotime('-6 days')), $today],
    'last30' => [date('Y-m-d', strtotime('-29 days')), $today],
];
if ($range === 'custom') {
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : $rangeMap['last30'][0];
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : $today;
} else {
    [$from, $to] = $rangeMap[$range] ?? $rangeMap['last30'];
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$deviceType = trim(substr((string)($_GET['device_type'] ?? ''), 0, 80));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$export = (string)($_GET['export'] ?? '') === 'csv';
$rows = [];
$totalRows = 0;
$totalVisits = 0;
$dataSource = 'none';
$availableRows = [
    'analytics_pageviews' => 0,
    'visitor_analytics_logs' => 0,
    'traffic_logs' => 0,
];
$visitorSummary = [];
$backfilledVisitors = 0;
$canViewFullIp = (string)($currentAdmin['role'] ?? '') === 'super_admin';

function adminDeviceParseUserAgent(string $ua): array {
    $ua = trim($ua);
    if ($ua === '') {
        return ['device_type' => 'Unknown', 'browser' => 'Unknown', 'os' => 'Unknown'];
    }
    if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|headless|monitor|uptime|validator/i', $ua)) {
        return ['device_type' => 'Bot', 'browser' => 'Bot', 'os' => 'Bot'];
    }

    $browser = 'Unknown';
    if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'SamsungBrowser/') !== false) $browser = 'Samsung Internet';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Firefox/') !== false || stripos($ua, 'FxiOS/') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS/') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Safari/') !== false) $browser = 'Safari';

    $os = 'Unknown';
    if (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
    elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

    $device = 'Desktop';
    if (stripos($ua, 'tablet') !== false || stripos($ua, 'iPad') !== false || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) $device = 'Tablet';
    elseif (stripos($ua, 'Mobile') !== false || stripos($ua, 'iPhone') !== false || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') !== false)) $device = 'Mobile';

    return ['device_type' => $device, 'browser' => $browser, 'os' => $os];
}

function adminDeviceBackfillUnknownVisitors(PDO $db): int {
    if (!adminTableExists('analytics_visitors') || !adminColumnExists('analytics_visitors', 'user_agent')) {
        return 0;
    }

    $stmt = $db->query("SELECT id, user_agent FROM analytics_visitors WHERE user_agent IS NOT NULL AND user_agent <> '' AND (device_type IS NULL OR device_type = '' OR device_type = 'Unknown' OR browser IS NULL OR browser = '' OR browser = 'Unknown' OR os IS NULL OR os = '' OR os = 'Unknown') LIMIT 500");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!$rows) {
        return 0;
    }

    $updated = 0;
    $update = $db->prepare("UPDATE analytics_visitors SET device_type = CASE WHEN device_type IS NULL OR device_type = '' OR device_type = 'Unknown' THEN :device_type ELSE device_type END, browser = CASE WHEN browser IS NULL OR browser = '' OR browser = 'Unknown' THEN :browser ELSE browser END, os = CASE WHEN os IS NULL OR os = '' OR os = 'Unknown' THEN :os ELSE os END WHERE id = :id");
    foreach ($rows as $row) {
        $parsed = adminDeviceParseUserAgent((string)($row['user_agent'] ?? ''));
        if ($parsed['device_type'] === 'Unknown' && $parsed['browser'] === 'Unknown' && $parsed['os'] === 'Unknown') {
            continue;
        }
        $update->execute([
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'id' => $row['id'],
        ]);
        $updated += $update->rowCount() > 0 ? 1 : 0;
    }
    return $updated;
}

try {
    $backfilledVisitors = adminDeviceBackfillUnknownVisitors($db);
    if ($backfilledVisitors > 0) {
        safeAdminLog('Device analytics backfilled visitors from stored user_agent: ' . $backfilledVisitors);
    }

    $params = ['from' => $from, 'to' => $to];
    $hasCanonical = false;
    if (adminTableExists('analytics_visitors') && adminTableExists('analytics_pageviews')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE DATE(viewed_at) BETWEEN :from AND :to');
        $countStmt->execute($params);
        $availableRows['analytics_pageviews'] = (int)$countStmt->fetchColumn();
        $hasCanonical = $availableRows['analytics_pageviews'] > 0;
    }

    if ($hasCanonical) {
        $dataSource = 'analytics_pageviews + analytics_visitors';
        $where = ['DATE(p.viewed_at) BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(v.device_type LIKE :q_device OR v.browser LIKE :q_browser OR v.os LIKE :q_os)';
            $like = '%' . $q . '%';
            $params += ['q_device' => $like, 'q_browser' => $like, 'q_os' => $like];
        }
        if ($deviceType !== '') {
            $where[] = "COALESCE(NULLIF(v.device_type, ''), 'Unknown') = :device_type";
            $params['device_type'] = $deviceType;
        }
        $whereSql = implode(' AND ', $where);
        $join = 'analytics_pageviews p LEFT JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid';
        $countSql = "SELECT COUNT(*) FROM (SELECT 1 FROM {$join} WHERE {$whereSql} GROUP BY COALESCE(NULLIF(v.device_type, ''), 'Unknown'), COALESCE(NULLIF(v.browser, ''), 'Unknown'), COALESCE(NULLIF(v.os, ''), 'Unknown')) grouped";
        $count = $db->prepare($countSql);
        $count->execute($params);
        $totalRows = (int)$count->fetchColumn();

        $totalStmt = $db->prepare("SELECT COUNT(p.id) FROM {$join} WHERE {$whereSql}");
        $totalStmt->execute($params);
        $totalVisits = (int)$totalStmt->fetchColumn();

        $queryLimit = $export ? 10000 : $limit;
        $queryOffset = $export ? 0 : $offset;
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(v.device_type, ''), 'Unknown') AS device, COALESCE(NULLIF(v.browser, ''), 'Unknown') AS browser, COALESCE(NULLIF(v.os, ''), 'Unknown') AS os, COUNT(p.id) AS visits FROM {$join} WHERE {$whereSql} GROUP BY device, browser, os ORDER BY visits DESC, device ASC, browser ASC LIMIT {$queryLimit} OFFSET {$queryOffset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    if ($dataSource === 'none' && adminTableExists('visitor_analytics_logs')) {
        $logCountStmt = $db->prepare('SELECT COUNT(*) FROM visitor_analytics_logs WHERE DATE(created_at) BETWEEN :from AND :to');
        $logCountStmt->execute(['from' => $from, 'to' => $to]);
        $availableRows['visitor_analytics_logs'] = (int)$logCountStmt->fetchColumn();
        if ($availableRows['visitor_analytics_logs'] > 0) {
            $dataSource = 'visitor_analytics_logs';
            $params = ['from' => $from, 'to' => $to];
            $where = ['DATE(created_at) BETWEEN :from AND :to'];
            if ($q !== '') {
                $where[] = '(device_type LIKE :q_device OR browser LIKE :q_browser OR operating_system LIKE :q_os)';
                $like = '%' . $q . '%';
                $params += ['q_device' => $like, 'q_browser' => $like, 'q_os' => $like];
            }
            if ($deviceType !== '') {
                $where[] = "COALESCE(NULLIF(device_type, ''), 'Unknown') = :device_type";
                $params['device_type'] = $deviceType;
            }
            $whereSql = implode(' AND ', $where);
            $countSql = "SELECT COUNT(*) FROM (SELECT 1 FROM visitor_analytics_logs WHERE {$whereSql} GROUP BY COALESCE(NULLIF(device_type, ''), 'Unknown'), COALESCE(NULLIF(browser, ''), 'Unknown'), COALESCE(NULLIF(operating_system, ''), 'Unknown')) grouped";
            $count = $db->prepare($countSql);
            $count->execute($params);
            $totalRows = (int)$count->fetchColumn();
            $totalStmt = $db->prepare("SELECT COUNT(*) FROM visitor_analytics_logs WHERE {$whereSql}");
            $totalStmt->execute($params);
            $totalVisits = (int)$totalStmt->fetchColumn();
            $queryLimit = $export ? 10000 : $limit;
            $queryOffset = $export ? 0 : $offset;
            $stmt = $db->prepare("SELECT COALESCE(NULLIF(device_type, ''), 'Unknown') AS device, COALESCE(NULLIF(browser, ''), 'Unknown') AS browser, COALESCE(NULLIF(operating_system, ''), 'Unknown') AS os, COUNT(*) AS visits FROM visitor_analytics_logs WHERE {$whereSql} GROUP BY device, browser, os ORDER BY visits DESC, device ASC, browser ASC LIMIT {$queryLimit} OFFSET {$queryOffset}");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        }
    }

    if ($dataSource === 'none' && adminTableExists('traffic_logs')) {
        $legacyCountStmt = $db->prepare('SELECT COUNT(*) FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to');
        $legacyCountStmt->execute(['from' => $from, 'to' => $to]);
        $availableRows['traffic_logs'] = (int)$legacyCountStmt->fetchColumn();
        if ($availableRows['traffic_logs'] > 0) {
            $dataSource = 'traffic_logs';
        }
        $params = ['from' => $from, 'to' => $to];
        $where = ['DATE(created_at) BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(device LIKE :q_device OR browser LIKE :q_browser OR os LIKE :q_os)';
            $like = '%' . $q . '%';
            $params += ['q_device' => $like, 'q_browser' => $like, 'q_os' => $like];
        }
        if ($deviceType !== '') {
            $where[] = "COALESCE(NULLIF(device, ''), 'Unknown') = :device_type";
            $params['device_type'] = $deviceType;
        }
        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM (SELECT 1 FROM traffic_logs WHERE {$whereSql} GROUP BY COALESCE(NULLIF(device, ''), 'Unknown'), COALESCE(NULLIF(browser, ''), 'Unknown'), COALESCE(NULLIF(os, ''), 'Unknown')) grouped";
        $count = $db->prepare($countSql);
        $count->execute($params);
        $totalRows = (int)$count->fetchColumn();

        $totalStmt = $db->prepare("SELECT COUNT(*) FROM traffic_logs WHERE {$whereSql}");
        $totalStmt->execute($params);
        $totalVisits = (int)$totalStmt->fetchColumn();

        $queryLimit = $export ? 10000 : $limit;
        $queryOffset = $export ? 0 : $offset;
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(device, ''), 'Unknown') AS device, COALESCE(NULLIF(browser, ''), 'Unknown') AS browser, COALESCE(NULLIF(os, ''), 'Unknown') AS os, COUNT(*) AS visits FROM traffic_logs WHERE {$whereSql} GROUP BY device, browser, os ORDER BY visits DESC, device ASC, browser ASC LIMIT {$queryLimit} OFFSET {$queryOffset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    if (!$export && adminTableExists('analytics_visitors') && adminTableExists('analytics_pageviews')) {
        $ipSelect = "'-' AS ip_display";
        $hasMaskedIp = adminColumnExists('analytics_visitors', 'masked_ip');
        if ($canViewFullIp && adminColumnExists('analytics_visitors', 'ip_address') && $hasMaskedIp) {
            $ipSelect = "COALESCE(NULLIF(v.ip_address, ''), NULLIF(v.masked_ip, ''), '-') AS ip_display";
        } elseif ($hasMaskedIp) {
            $ipSelect = "COALESCE(NULLIF(v.masked_ip, ''), '-') AS ip_display";
        } elseif (adminColumnExists('analytics_visitors', 'ip_hash')) {
            $ipSelect = "CASE WHEN v.ip_hash IS NULL OR v.ip_hash = '' THEN '-' ELSE CONCAT(LEFT(v.ip_hash, 10), '...') END AS ip_display";
        }
        $visitorStmt = $db->prepare("SELECT
                {$ipSelect},
                COUNT(p.id) AS visits,
                MIN(p.viewed_at) AS first_seen,
                MAX(p.viewed_at) AS last_seen,
                COALESCE(NULLIF(v.device_type, ''), 'Unknown') AS device,
                COALESCE(NULLIF(v.browser, ''), 'Unknown') AS browser,
                COALESCE(NULLIF(v.os, ''), 'Unknown') AS os,
                MAX(NULLIF(p.page_path, '')) AS last_page
            FROM analytics_pageviews p
            LEFT JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid
            WHERE DATE(p.viewed_at) BETWEEN :from AND :to
            GROUP BY p.visitor_uuid, ip_display, device, browser, os
            ORDER BY last_seen DESC
            LIMIT 25");
        $visitorStmt->execute(['from' => $from, 'to' => $to]);
        $visitorSummary = $visitorStmt->fetchAll();
    }
} catch (Throwable $e) {
    safeAdminLog('Device analytics failed: ' . $e->getMessage());
    $error = 'گزارش دستگاه‌ها در حال حاضر در دسترس نیست.';
}

$unknownVisits = 0;
foreach ($rows as $row) {
    if (($row['device'] ?? 'Unknown') === 'Unknown' || ($row['browser'] ?? 'Unknown') === 'Unknown' || ($row['os'] ?? 'Unknown') === 'Unknown') {
        $unknownVisits += (int)($row['visits'] ?? 0);
    }
}
$unknownPercent = $totalVisits > 0 ? ($unknownVisits / $totalVisits) * 100 : 0;

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="device-analytics-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['device','browser','os','visits','percent','date_from','date_to','data_source']);
    foreach ($rows as $row) {
        $percent = $totalVisits > 0 ? number_format(((int)$row['visits'] / $totalVisits) * 100, 1) . '%' : '0%';
        fputcsv($out, [$row['device'],$row['browser'],$row['os'],$row['visits'],$percent,$from,$to,$dataSource]);
    }
    exit;
}

$totalPages = max(1, (int)ceil($totalRows / $limit));
$exportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv', 'page' => null, 'date_from' => $from, 'date_to' => $to]));
include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header"><h2>گزارش دستگاه‌ها</h2><a class="btn btn-primary" href="<?php echo h($exportUrl); ?>">خروجی CSV</a></div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <div class="stats-row">
            <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo h((string)$totalVisits); ?></h3><p>بازدید در بازه انتخابی</p></div></div>
            <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h((string)$totalRows); ?></h3><p>ترکیب‌های دستگاه</p></div></div>
            <div class="stat-card"><div class="stat-content"><h3 style="font-size:18px"><?php echo h($from . ' تا ' . $to); ?></h3><p>بازه گزارش</p></div></div>
        </div>
        <form class="admin-filter" method="get">
            <select class="form-control" name="range" onchange="this.form.submit()">
                <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>>امروز</option>
                <option value="yesterday" <?php echo $range === 'yesterday' ? 'selected' : ''; ?>>دیروز</option>
                <option value="last7" <?php echo $range === 'last7' ? 'selected' : ''; ?>>۷ روز اخیر</option>
                <option value="last30" <?php echo $range === 'last30' ? 'selected' : ''; ?>>۳۰ روز اخیر</option>
                <option value="custom" <?php echo $range === 'custom' ? 'selected' : ''; ?>>بازه دلخواه</option>
            </select>
            <input class="form-control" name="q" placeholder="جستجوی دستگاه، مرورگر یا سیستم‌عامل" value="<?php echo h($q); ?>">
            <input class="form-control" name="device_type" placeholder="نوع دستگاه" value="<?php echo h($deviceType); ?>">
            <input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>">
            <input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>">
            <button class="btn btn-primary">فیلتر</button>
        </form>
        <p class="text-muted">منبع گزارش: <?php echo h($dataSource === 'none' ? 'داده‌ای پیدا نشد' : $dataSource); ?></p>
        <?php if ($backfilledVisitors > 0): ?><div class="alert alert-success"><?php echo h((string)$backfilledVisitors); ?> رکورد visitor از روی User-Agent ذخیره‌شده تکمیل شد.</div><?php endif; ?>
        <?php if ($totalVisits > 0 && $unknownPercent >= 50): ?><div class="alert" style="background:#fff3cd;color:#856404">بخش بزرگی از این بازه هنوز Unknown است. برای بازدیدهای قدیمی اگر User-Agent ذخیره نشده باشد قابل بازسازی نیست؛ بازدیدهای جدید بعد از اصلاح tracker با دستگاه، مرورگر و سیستم‌عامل واقعی ثبت می‌شوند.</div><?php endif; ?>
        <div class="table-responsive"><table class="table"><thead><tr><th>دستگاه</th><th>مرورگر</th><th>سیستم‌عامل</th><th>بازدید</th><th>سهم</th></tr></thead><tbody>
            <?php if (!$rows): ?><tr><td colspan="5" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
            <?php foreach($rows as $row): $percent = $totalVisits > 0 ? number_format(((int)$row['visits'] / $totalVisits) * 100, 1) . '%' : '0%'; ?><tr><td><?php echo h($row['device'] ?: 'Unknown'); ?></td><td><?php echo h($row['browser'] ?: 'Unknown'); ?></td><td><?php echo h($row['os'] ?: 'Unknown'); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($percent); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>

        <?php if ($visitorSummary): ?>
            <h3 style="margin-top:24px">خلاصه Visitor و IP</h3>
            <p class="text-muted"><?php echo $canViewFullIp ? 'IP کامل فقط برای super_admin نمایش داده می‌شود.' : 'برای این سطح دسترسی فقط IP ماسک‌شده یا خلاصه hash نمایش داده می‌شود.'; ?></p>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>IP</th><th>بازدید</th><th>اولین مشاهده</th><th>آخرین مشاهده</th><th>دستگاه</th><th>مرورگر</th><th>سیستم‌عامل</th><th>آخرین صفحه</th></tr></thead>
                    <tbody>
                        <?php foreach ($visitorSummary as $visitor): ?>
                            <tr>
                                <td><?php echo h($visitor['ip_display']); ?></td>
                                <td><?php echo h($visitor['visits']); ?></td>
                                <td><?php echo h($visitor['first_seen']); ?></td>
                                <td><?php echo h($visitor['last_seen']); ?></td>
                                <td><?php echo h($visitor['device']); ?></td>
                                <td><?php echo h($visitor['browser']); ?></td>
                                <td><?php echo h($visitor['os']); ?></td>
                                <td><?php echo h($visitor['last_page'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?><div class="pagination"><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">قبلی</a><span><?php echo h($page); ?> / <?php echo h($totalPages); ?></span><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1), 'export' => null]))); ?>">بعدی</a></div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
