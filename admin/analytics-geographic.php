<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'تحلیل جغرافیایی';
$error = '';
$emptyState = 'هنوز داده‌ای ثبت نشده است. صفحه عمومی سایت را باز کنید تا اولین بازدید ثبت شود.';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$export = ($_GET['export'] ?? '') === 'csv';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$limit = $export ? 10000 : $perPage;
$offset = $export ? 0 : (($page - 1) * $perPage);
$rows = [];
$total = 0;

try {
    $baseParams = ['from' => $from, 'to' => $to];
    $hasLegacy = false;
    if (adminTableExists('visitor_locations')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM visitor_locations WHERE `date` BETWEEN :from AND :to');
        $countStmt->execute($baseParams);
        $hasLegacy = (int)$countStmt->fetchColumn() > 0;
    }
    if ($hasLegacy) {
        $params = $baseParams;
        $where = ['`date` BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(country LIKE :q_country OR city LIKE :q_city)';
            $like = '%' . $q . '%';
            $params += ['q_country' => $like, 'q_city' => $like];
        }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(country, ''), 'Unknown') AS country, COALESCE(NULLIF(city, ''), 'Unknown') AS city, visits_count, `date` FROM visitor_locations WHERE {$whereSql} ORDER BY `date` DESC, visits_count DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM visitor_locations WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    } elseif (adminTableExists('analytics_visitors') && adminTableExists('analytics_pageviews')) {
        $params = $baseParams;
        $where = ['DATE(p.viewed_at) BETWEEN :from AND :to'];
        if ($q !== '') {
            $where[] = '(v.country LIKE :q_country OR v.city LIKE :q_city)';
            $like = '%' . $q . '%';
            $params += ['q_country' => $like, 'q_city' => $like];
        }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(v.country, ''), 'Unknown') AS country, COALESCE(NULLIF(v.city, ''), 'Unknown') AS city, COUNT(p.id) AS visits_count, DATE(p.viewed_at) AS `date` FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql} GROUP BY country, city, `date` ORDER BY `date` DESC, visits_count DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql} GROUP BY COALESCE(NULLIF(v.country, ''), 'Unknown'), COALESCE(NULLIF(v.city, ''), 'Unknown'), DATE(p.viewed_at)) grouped");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    }
} catch (Throwable $e) {
    safeAdminLog('Geographic analytics failed: ' . $e->getMessage());
    $error = 'گزارش جغرافیایی در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="geographic-analytics-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['country','city','visits_count','date']);
    foreach ($rows as $row) fputcsv($out, [$row['country'],$row['city'],$row['visits_count'],$row['date']]);
    exit;
}

$pages = max(1, (int)ceil($total / $perPage));
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>تحلیل جغرافیایی</h2><a class="btn btn-primary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv', 'page'=>null]))); ?>">خروجی CSV</a></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form class="admin-filter" method="get"><input class="form-control" name="q" placeholder="کشور یا شهر" value="<?php echo h($q); ?>"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">فیلتر</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Country</th><th>City</th><th>Visits</th><th>Date</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="4" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
<?php foreach($rows as $row): ?><tr><td><?php echo h($row['country'] ?: 'Unknown'); ?></td><td><?php echo h($row['city'] ?: 'Unknown'); ?></td><td><?php echo h($row['visits_count']); ?></td><td><?php echo h($row['date']); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><div class="pagination"><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">Previous</a><span><?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></span><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($pages, $page + 1), 'export' => null]))); ?>">Next</a></div><?php else: ?><p>Page <?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></p><?php endif; ?>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
