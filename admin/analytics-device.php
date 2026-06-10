<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Device Analytics';
$error = '';
$emptyState = 'هنوز داده‌ای ثبت نشده است. صفحه عمومی سایت را باز کنید تا اولین بازدید ثبت شود.';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$deviceType = trim(substr((string)($_GET['device_type'] ?? ''), 0, 80));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$export = (string)($_GET['export'] ?? '') === 'csv';
$rows = [];
$totalRows = 0;
$totalVisits = 0;

try {
    $params = ['from' => $from, 'to' => $to];
    $hasCanonical = false;
    if (adminTableExists('analytics_visitors') && adminTableExists('analytics_pageviews')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE DATE(viewed_at) BETWEEN :from AND :to');
        $countStmt->execute($params);
        $hasCanonical = (int)$countStmt->fetchColumn() > 0;
    }

    if ($hasCanonical) {
        $where = ['DATE(p.viewed_at) BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(v.device_type LIKE :q_device OR v.browser LIKE :q_browser OR v.os LIKE :q_os)';
            $like = '%' . $q . '%';
            $params += ['q_device' => $like, 'q_browser' => $like, 'q_os' => $like];
        }
        if ($deviceType !== '') {
            $where[] = 'v.device_type = :device_type';
            $params['device_type'] = $deviceType;
        }
        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM (SELECT 1 FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql} GROUP BY COALESCE(NULLIF(v.device_type, ''), 'Unknown'), COALESCE(NULLIF(v.browser, ''), 'Unknown'), COALESCE(NULLIF(v.os, ''), 'Unknown')) grouped";
        $count = $db->prepare($countSql);
        $count->execute($params);
        $totalRows = (int)$count->fetchColumn();

        $totalStmt = $db->prepare("SELECT COUNT(p.id) FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql}");
        $totalStmt->execute($params);
        $totalVisits = (int)$totalStmt->fetchColumn();

        $queryLimit = $export ? 10000 : $limit;
        $queryOffset = $export ? 0 : $offset;
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(v.device_type, ''), 'Unknown') AS device, COALESCE(NULLIF(v.browser, ''), 'Unknown') AS browser, COALESCE(NULLIF(v.os, ''), 'Unknown') AS os, COUNT(p.id) AS visits FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql} GROUP BY device, browser, os ORDER BY visits DESC, device ASC, browser ASC LIMIT {$queryLimit} OFFSET {$queryOffset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } elseif (adminTableExists('traffic_logs')) {
        $where = ['DATE(created_at) BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(device LIKE :q_device OR browser LIKE :q_browser OR os LIKE :q_os)';
            $like = '%' . $q . '%';
            $params += ['q_device' => $like, 'q_browser' => $like, 'q_os' => $like];
        }
        if ($deviceType !== '') {
            $where[] = 'device = :device_type';
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
} catch (Throwable $e) {
    safeAdminLog('Device analytics failed: ' . $e->getMessage());
    $error = 'گزارش دستگاه‌ها در حال حاضر در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="device-analytics.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['device','browser','os','visits','percent']);
    foreach ($rows as $row) {
        $percent = $totalVisits > 0 ? number_format(((int)$row['visits'] / $totalVisits) * 100, 1) . '%' : '0%';
        fputcsv($out, [$row['device'],$row['browser'],$row['os'],$row['visits'],$percent]);
    }
    exit;
}

$totalPages = max(1, (int)ceil($totalRows / $limit));
$exportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv', 'page' => null]));
include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header"><h2>Device Analytics</h2><a class="btn btn-primary" href="<?php echo h($exportUrl); ?>">Export CSV</a></div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <form class="admin-filter" method="get">
            <input class="form-control" name="q" placeholder="Device, browser, or OS" value="<?php echo h($q); ?>">
            <input class="form-control" name="device_type" placeholder="Device" value="<?php echo h($deviceType); ?>">
            <input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>">
            <input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>">
            <button class="btn btn-primary">Filter</button>
        </form>
        <div class="table-responsive"><table class="table"><thead><tr><th>Device</th><th>Browser</th><th>OS</th><th>Visits</th><th>Percent</th></tr></thead><tbody>
            <?php if (!$rows): ?><tr><td colspan="5" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
            <?php foreach($rows as $row): $percent = $totalVisits > 0 ? number_format(((int)$row['visits'] / $totalVisits) * 100, 1) . '%' : '0%'; ?><tr><td><?php echo h($row['device'] ?: 'Unknown'); ?></td><td><?php echo h($row['browser'] ?: 'Unknown'); ?></td><td><?php echo h($row['os'] ?: 'Unknown'); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($percent); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($totalPages > 1): ?><div class="pagination"><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">Previous</a><span><?php echo h($page); ?> / <?php echo h($totalPages); ?></span><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1), 'export' => null]))); ?>">Next</a></div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
