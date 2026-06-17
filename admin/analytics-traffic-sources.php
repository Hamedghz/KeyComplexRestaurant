<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'گزارش منابع ورودی';
$error = '';
$emptyState = 'در این بازه داده‌ای برای منابع ورودی پیدا نشد. اگر سایت عمومی بازدید داشته، فعال بودن assets/js/analytics-tracker.js و مسیر api/analytics-track.php را بررسی کنید.';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$range = (string)($_GET['range'] ?? ((isset($_GET['date_from']) || isset($_GET['date_to'])) ? 'custom' : 'yesterday'));
$rangeMap = [
    'today' => [$today, $today],
    'yesterday' => [$yesterday, $yesterday],
    'last7' => [date('Y-m-d', strtotime('-6 days')), $today],
    'last30' => [date('Y-m-d', strtotime('-29 days')), $today],
];
if ($range === 'custom') {
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : $yesterday;
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : $yesterday;
} else {
    [$from, $to] = $rangeMap[$range] ?? $rangeMap['yesterday'];
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$sourceType = trim(substr((string)($_GET['source_type'] ?? ''), 0, 80));
$export = (string)($_GET['export'] ?? '') === 'csv';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$limit = $export ? 10000 : $perPage;
$offset = $export ? 0 : (($page - 1) * $perPage);
$sources = [];
$dailyRows = [];
$total = 0;
$totalVisits = 0;
$dataSource = 'none';

try {
    $baseParams = ['from' => $from, 'to' => $to];
    $canonicalRows = 0;
    if (adminTableExists('analytics_sessions')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM analytics_sessions WHERE DATE(started_at) BETWEEN :from AND :to');
        $countStmt->execute($baseParams);
        $canonicalRows = (int)$countStmt->fetchColumn();
    }

    if ($canonicalRows > 0) {
        $dataSource = 'analytics_sessions';
        $params = $baseParams;
        $where = ['DATE(started_at) BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(source LIKE :q_source OR medium LIKE :q_medium OR campaign LIKE :q_campaign OR utm_source LIKE :q_utm_source OR utm_medium LIKE :q_utm_medium OR utm_campaign LIKE :q_utm_campaign OR referrer LIKE :q_referrer OR landing_page LIKE :q_landing)';
            $like = '%' . $q . '%';
            $params += ['q_source' => $like, 'q_medium' => $like, 'q_campaign' => $like, 'q_utm_source' => $like, 'q_utm_medium' => $like, 'q_utm_campaign' => $like, 'q_referrer' => $like, 'q_landing' => $like];
        }
        if ($sourceType !== '') {
            $where[] = "(COALESCE(NULLIF(source, ''), 'direct') = :source_filter OR COALESCE(NULLIF(medium, ''), 'direct') = :medium_filter)";
            $params += ['source_filter' => $sourceType, 'medium_filter' => $sourceType];
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM analytics_sessions WHERE {$whereSql} GROUP BY COALESCE(NULLIF(source, ''), 'direct'), COALESCE(NULLIF(medium, ''), 'direct')) grouped");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalStmt = $db->prepare("SELECT COUNT(*) FROM analytics_sessions WHERE {$whereSql}");
        $totalStmt->execute($params);
        $totalVisits = (int)$totalStmt->fetchColumn();
        $stmt = $db->prepare("SELECT
                COALESCE(NULLIF(source, ''), 'direct') AS source_name,
                COALESCE(NULLIF(medium, ''), 'direct') AS source_type,
                COUNT(*) AS visits_count,
                GROUP_CONCAT(DISTINCT NULLIF(COALESCE(NULLIF(campaign, ''), NULLIF(utm_campaign, '')), '') ORDER BY COALESCE(NULLIF(campaign, ''), NULLIF(utm_campaign, '')) SEPARATOR ', ') AS campaign,
                GROUP_CONCAT(DISTINCT NULLIF(utm_source, '') ORDER BY utm_source SEPARATOR ', ') AS utm_source,
                GROUP_CONCAT(DISTINCT NULLIF(utm_medium, '') ORDER BY utm_medium SEPARATOR ', ') AS utm_medium,
                GROUP_CONCAT(DISTINCT NULLIF(utm_campaign, '') ORDER BY utm_campaign SEPARATOR ', ') AS utm_campaign,
                MIN(started_at) AS first_seen,
                MAX(last_activity_at) AS last_seen,
                MIN(NULLIF(referrer, '')) AS example_referrer,
                MIN(NULLIF(landing_page, '')) AS example_landing_page
            FROM analytics_sessions
            WHERE {$whereSql}
            GROUP BY source_name, source_type
            ORDER BY visits_count DESC, source_name ASC
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $sources = $stmt->fetchAll();

        $dailyStmt = $db->prepare("SELECT
                DATE(started_at) AS report_date,
                COALESCE(NULLIF(source, ''), 'direct') AS source_name,
                COALESCE(NULLIF(medium, ''), 'direct') AS source_type,
                COUNT(*) AS visits_count
            FROM analytics_sessions
            WHERE {$whereSql}
            GROUP BY report_date, source_name, source_type
            ORDER BY report_date DESC, visits_count DESC
            LIMIT 300");
        $dailyStmt->execute($params);
        $dailyRows = $dailyStmt->fetchAll();
    }

    if ($dataSource === 'none' && adminTableExists('visitor_analytics_logs')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM visitor_analytics_logs WHERE DATE(created_at) BETWEEN :from AND :to');
        $countStmt->execute($baseParams);
        if ((int)$countStmt->fetchColumn() > 0) {
            $dataSource = 'visitor_analytics_logs';
            $params = $baseParams;
            $where = ['DATE(created_at) BETWEEN :from AND :to'];
            if ($q !== '') {
                $where[] = '(source_name LIKE :q_source OR source_type LIKE :q_type OR utm_source LIKE :q_utm_source OR utm_medium LIKE :q_utm_medium OR utm_campaign LIKE :q_utm_campaign OR referrer_url LIKE :q_referrer OR landing_page LIKE :q_landing)';
                $like = '%' . $q . '%';
                $params += ['q_source' => $like, 'q_type' => $like, 'q_utm_source' => $like, 'q_utm_medium' => $like, 'q_utm_campaign' => $like, 'q_referrer' => $like, 'q_landing' => $like];
            }
            if ($sourceType !== '') {
                $where[] = "(COALESCE(NULLIF(source_name, ''), 'direct') = :source_filter OR COALESCE(NULLIF(source_type, ''), 'direct') = :type_filter)";
                $params += ['source_filter' => $sourceType, 'type_filter' => $sourceType];
            }
            $whereSql = implode(' AND ', $where);
            $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM visitor_analytics_logs WHERE {$whereSql} GROUP BY COALESCE(NULLIF(source_name, ''), 'direct'), COALESCE(NULLIF(source_type, ''), 'direct')) grouped");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $totalStmt = $db->prepare("SELECT COUNT(DISTINCT session_id) FROM visitor_analytics_logs WHERE {$whereSql}");
            $totalStmt->execute($params);
            $totalVisits = (int)$totalStmt->fetchColumn();
            $stmt = $db->prepare("SELECT
                    COALESCE(NULLIF(source_name, ''), 'direct') AS source_name,
                    COALESCE(NULLIF(source_type, ''), 'direct') AS source_type,
                    COUNT(DISTINCT session_id) AS visits_count,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_campaign, '') ORDER BY utm_campaign SEPARATOR ', ') AS campaign,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_source, '') ORDER BY utm_source SEPARATOR ', ') AS utm_source,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_medium, '') ORDER BY utm_medium SEPARATOR ', ') AS utm_medium,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_campaign, '') ORDER BY utm_campaign SEPARATOR ', ') AS utm_campaign,
                    MIN(created_at) AS first_seen,
                    MAX(created_at) AS last_seen,
                    MIN(NULLIF(referrer_url, '')) AS example_referrer,
                    MIN(NULLIF(landing_page, '')) AS example_landing_page
                FROM visitor_analytics_logs
                WHERE {$whereSql}
                GROUP BY source_name, source_type
                ORDER BY visits_count DESC, source_name ASC
                LIMIT {$limit} OFFSET {$offset}");
            $stmt->execute($params);
            $sources = $stmt->fetchAll();
        }
    }

    if ($dataSource === 'none' && adminTableExists('traffic_sources')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM traffic_sources WHERE `date` BETWEEN :from AND :to');
        $countStmt->execute($baseParams);
        if ((int)$countStmt->fetchColumn() > 0) {
            $dataSource = 'traffic_sources summary';
            $params = $baseParams;
            $where = ['`date` BETWEEN :from AND :to'];
            if ($q !== '') {
                $where[] = 'source_name LIKE :q';
                $params['q'] = '%' . $q . '%';
            }
            if ($sourceType !== '') {
                $where[] = '(source_name = :source_filter OR source_type = :type_filter)';
                $params += ['source_filter' => $sourceType, 'type_filter' => $sourceType];
            }
            $whereSql = implode(' AND ', $where);
            $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM traffic_sources WHERE {$whereSql} GROUP BY source_name, source_type) grouped");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $totalStmt = $db->prepare("SELECT COALESCE(SUM(visits_count), 0) FROM traffic_sources WHERE {$whereSql}");
            $totalStmt->execute($params);
            $totalVisits = (int)$totalStmt->fetchColumn();
            $stmt = $db->prepare("SELECT
                    source_name,
                    source_type,
                    SUM(visits_count) AS visits_count,
                    NULL AS campaign,
                    NULL AS utm_source,
                    NULL AS utm_medium,
                    NULL AS utm_campaign,
                    MIN(`date`) AS first_seen,
                    MAX(`date`) AS last_seen,
                    NULL AS example_referrer,
                    NULL AS example_landing_page
                FROM traffic_sources
                WHERE {$whereSql}
                GROUP BY source_name, source_type
                ORDER BY visits_count DESC, source_name ASC
                LIMIT {$limit} OFFSET {$offset}");
            $stmt->execute($params);
            $sources = $stmt->fetchAll();
        }
    }

    if ($dataSource === 'none' && adminTableExists('traffic_logs')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to');
        $countStmt->execute($baseParams);
        if ((int)$countStmt->fetchColumn() > 0) {
            $dataSource = 'traffic_logs';
            $params = $baseParams;
            $where = ['DATE(created_at) BETWEEN :from AND :to'];
            if ($q !== '') {
                $where[] = '(referrer LIKE :q_referrer OR landing_page LIKE :q_landing)';
                $like = '%' . $q . '%';
                $params += ['q_referrer' => $like, 'q_landing' => $like];
            }
            if ($sourceType !== '') {
                $where[] = "(CASE WHEN referrer IS NULL OR referrer = '' THEN 'direct' ELSE 'referral' END) = :source_type";
                $params['source_type'] = $sourceType;
            }
            $whereSql = implode(' AND ', $where);
            $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM traffic_logs WHERE {$whereSql} GROUP BY COALESCE(NULLIF(referrer, ''), 'direct'), CASE WHEN referrer IS NULL OR referrer = '' THEN 'direct' ELSE 'referral' END) grouped");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
            $totalStmt = $db->prepare("SELECT COUNT(*) FROM traffic_logs WHERE {$whereSql}");
            $totalStmt->execute($params);
            $totalVisits = (int)$totalStmt->fetchColumn();
            $stmt = $db->prepare("SELECT
                    COALESCE(NULLIF(referrer, ''), 'direct') AS source_name,
                    CASE WHEN referrer IS NULL OR referrer = '' THEN 'direct' ELSE 'referral' END AS source_type,
                    COUNT(*) AS visits_count,
                    NULL AS campaign,
                    NULL AS utm_source,
                    NULL AS utm_medium,
                    NULL AS utm_campaign,
                    MIN(created_at) AS first_seen,
                    MAX(created_at) AS last_seen,
                    MIN(NULLIF(referrer, '')) AS example_referrer,
                    MIN(NULLIF(landing_page, '')) AS example_landing_page
                FROM traffic_logs
                WHERE {$whereSql}
                GROUP BY source_name, source_type
                ORDER BY visits_count DESC, source_name ASC
                LIMIT {$limit} OFFSET {$offset}");
            $stmt->execute($params);
            $sources = $stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    safeAdminLog('Traffic source analytics failed: ' . $e->getMessage());
    $error = 'گزارش منابع ورودی در حال حاضر در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="traffic-sources-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['source_name','source_type','visits','percent','campaign','utm_source','utm_medium','utm_campaign','first_seen','last_seen','example_referrer','example_landing_page','date_from','date_to','data_source']);
    foreach ($sources as $row) {
        $percent = $totalVisits > 0 ? number_format(((int)$row['visits_count'] / $totalVisits) * 100, 1) . '%' : '0%';
        fputcsv($out, [$row['source_name'],$row['source_type'],$row['visits_count'],$percent,$row['campaign'],$row['utm_source'],$row['utm_medium'],$row['utm_campaign'],$row['first_seen'],$row['last_seen'],$row['example_referrer'],$row['example_landing_page'],$from,$to,$dataSource]);
    }
    exit;
}

$pages = max(1, (int)ceil($total / $perPage));
$topSource = $sources[0]['source_name'] ?? '-';
$exportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv', 'page' => null, 'date_from' => $from, 'date_to' => $to]));
include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2>گزارش منابع ورودی</h2>
        <a class="btn btn-primary" href="<?php echo h($exportUrl); ?>">خروجی CSV</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <div class="stats-row">
            <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo h((string)$totalVisits); ?></h3><p>ورودی‌ها</p></div></div>
            <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h((string)$total); ?></h3><p>منبع یکتا</p></div></div>
            <div class="stat-card"><div class="stat-content"><h3 style="font-size:18px"><?php echo h($topSource); ?></h3><p>منبع برتر</p></div></div>
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
            <input class="form-control" name="q" placeholder="جستجوی Source، کمپین، UTM، Referrer" value="<?php echo h($q); ?>">
            <input class="form-control" name="source_type" placeholder="Source یا Medium" value="<?php echo h($sourceType); ?>">
            <input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>">
            <input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>">
            <button class="btn btn-primary">فیلتر</button>
        </form>

        <p class="text-muted">منبع داده گزارش: <?php echo h($dataSource === 'none' ? 'داده‌ای پیدا نشد' : $dataSource); ?>. نمای پیش‌فرض روی دیروز است تا ورودی‌های هر Source سریع دیده شود.</p>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Medium</th>
                        <th>ورودی</th>
                        <th>سهم</th>
                        <th>Campaign / UTM</th>
                        <th>اولین مشاهده</th>
                        <th>آخرین مشاهده</th>
                        <th>نمونه Referrer / Landing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$sources): ?><tr><td colspan="8" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
                    <?php foreach ($sources as $row): ?>
                        <?php
                        $percent = $totalVisits > 0 ? number_format(((int)$row['visits_count'] / $totalVisits) * 100, 1) . '%' : '0%';
                        $campaignText = trim(($row['campaign'] ?? '') . ' ' . ($row['utm_source'] ?? '') . ' ' . ($row['utm_medium'] ?? '') . ' ' . ($row['utm_campaign'] ?? ''));
                        $exampleText = trim(($row['example_referrer'] ?? '') . (($row['example_landing_page'] ?? '') ? ' / ' . $row['example_landing_page'] : ''), ' /');
                        ?>
                        <tr>
                            <td><?php echo h($row['source_name'] ?: 'direct'); ?></td>
                            <td><?php echo h($row['source_type'] ?: 'direct'); ?></td>
                            <td><?php echo h($row['visits_count']); ?></td>
                            <td><?php echo h($percent); ?></td>
                            <td><?php echo h($campaignText !== '' ? $campaignText : '-'); ?></td>
                            <td><?php echo h($row['first_seen'] ?: '-'); ?></td>
                            <td><?php echo h($row['last_seen'] ?: '-'); ?></td>
                            <td style="max-width:360px"><?php echo h($exampleText !== '' ? $exampleText : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($dailyRows): ?>
            <h3 style="margin-top:24px">جزئیات روزانه</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>تاریخ</th><th>Source</th><th>Medium</th><th>ورودی</th></tr></thead>
                    <tbody>
                        <?php foreach ($dailyRows as $row): ?>
                            <tr><td><?php echo h($row['report_date']); ?></td><td><?php echo h($row['source_name']); ?></td><td><?php echo h($row['source_type']); ?></td><td><?php echo h($row['visits_count']); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">قبلی</a>
                <span><?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - مجموع <?php echo h((string)$total); ?></span>
                <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($pages, $page + 1), 'export' => null]))); ?>">بعدی</a>
            </div>
        <?php else: ?>
            <p class="text-muted">صفحه <?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - مجموع <?php echo h((string)$total); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
