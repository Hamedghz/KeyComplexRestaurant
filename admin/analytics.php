<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Visitor Logs';
$error = '';
$emptyState = 'هنوز داده‌ای ثبت نشده است. صفحه عمومی سایت را باز کنید تا اولین بازدید ثبت شود.';

$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : '';
$sourceType = trim(substr((string)($_GET['source_type'] ?? ''), 0, 80));
$deviceType = trim(substr((string)($_GET['device_type'] ?? ''), 0, 80));
$export = ($_GET['export'] ?? '') === 'csv';
$rows = [];
$total = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$limit = $export ? 10000 : $perPage;
$offset = $export ? 0 : (($page - 1) * $perPage);

try {
    $hasPathLogs = adminTableExists('visitor_analytics_logs') && (int)$db->query('SELECT COUNT(*) FROM visitor_analytics_logs')->fetchColumn() > 0;
    if ($hasPathLogs) {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(session_id LIKE :q_session OR current_page LIKE :q_page OR referrer_url LIKE :q_referrer OR source_name LIKE :q_source OR event_type LIKE :q_event OR target_action LIKE :q_target)'; $like = '%' . $q . '%'; $params += ['q_session' => $like, 'q_page' => $like, 'q_referrer' => $like, 'q_source' => $like, 'q_event' => $like, 'q_target' => $like]; }
        if ($sourceType !== '') { $where[] = '(source_type = :source_type_filter OR campaign_type = :campaign_type_filter OR source_name = :source_name_filter)'; $params += ['source_type_filter' => $sourceType, 'campaign_type_filter' => $sourceType, 'source_name_filter' => $sourceType]; }
        if ($deviceType !== '') { $where[] = 'device_type = :device_type'; $params['device_type'] = $deviceType; }
        if ($dateFrom !== '') { $where[] = 'created_at >= :date_from'; $params['date_from'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== '') { $where[] = 'created_at <= :date_to'; $params['date_to'] = $dateTo . ' 23:59:59'; }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT created_at AS time, session_id, current_page, referrer_url AS referrer, COALESCE(NULLIF(source_name,''), NULLIF(source_type,''), 'direct') AS source, event_type, target_action, device_type, browser, operating_system AS os FROM visitor_analytics_logs WHERE {$whereSql} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM visitor_analytics_logs WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    } elseif (adminTableExists('analytics_pageviews') && adminTableExists('analytics_sessions') && adminTableExists('analytics_visitors')) {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(p.session_uuid LIKE :q_session OR p.page_path LIKE :q_page OR p.referrer LIKE :q_referrer OR s.source LIKE :q_source OR s.medium LIKE :q_medium)'; $like = '%' . $q . '%'; $params += ['q_session' => $like, 'q_page' => $like, 'q_referrer' => $like, 'q_source' => $like, 'q_medium' => $like]; }
        if ($sourceType !== '') { $where[] = '(s.source = :source_filter OR s.medium = :medium_filter)'; $params += ['source_filter' => $sourceType, 'medium_filter' => $sourceType]; }
        if ($deviceType !== '') { $where[] = 'v.device_type = :device_type'; $params['device_type'] = $deviceType; }
        if ($dateFrom !== '') { $where[] = 'p.viewed_at >= :date_from'; $params['date_from'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== '') { $where[] = 'p.viewed_at <= :date_to'; $params['date_to'] = $dateTo . ' 23:59:59'; }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT p.viewed_at AS time, p.session_uuid AS session_id, p.page_path AS current_page, p.referrer, COALESCE(NULLIF(s.source,''), 'direct') AS source, 'page_view' AS event_type, 'page_view' AS target_action, v.device_type, v.browser, v.os FROM analytics_pageviews p JOIN analytics_sessions s ON s.session_uuid = p.session_uuid JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql} ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM analytics_pageviews p JOIN analytics_sessions s ON s.session_uuid = p.session_uuid JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    }
} catch (Throwable $e) {
    safeAdminLog('Visitor logs failed: ' . $e->getMessage());
    $error = 'گزارش بازدیدکنندگان در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visitor-logs-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['time','session_id','page','referrer','source','event_type','target_action','device_type','browser','os']);
    foreach ($rows as $row) fputcsv($out, [$row['time'],$row['session_id'],$row['current_page'],$row['referrer'],$row['source'],$row['event_type'],$row['target_action'],$row['device_type'],$row['browser'],$row['os']]);
    exit;
}

$pages = max(1, (int)ceil($total / $perPage));
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Visitor Logs</h2><a class="btn btn-primary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv', 'page'=>null]))); ?>">Export CSV</a></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form method="get" class="admin-filter">
  <input class="form-control" name="q" placeholder="Search" value="<?php echo h($q); ?>">
  <input class="form-control" type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
  <input class="form-control" type="date" name="date_to" value="<?php echo h($dateTo); ?>">
  <input class="form-control" name="source_type" placeholder="Source type" value="<?php echo h($sourceType); ?>">
  <input class="form-control" name="device_type" placeholder="Device" value="<?php echo h($deviceType); ?>">
  <button class="btn btn-primary">Filter</button>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Time</th><th>Session</th><th>Page</th><th>Referrer / Source</th><th>Event</th><th>Target</th><th>Device</th><th>Browser</th><th>OS</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="9" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?php echo h($row['time']); ?></td><td><?php echo h($row['session_id']); ?></td><td><?php echo h($row['current_page']); ?></td><td><?php echo h(trim(($row['source'] ?? '') . (($row['referrer'] ?? '') ? ' / ' . $row['referrer'] : ''), ' /')); ?></td><td><?php echo h($row['event_type']); ?></td><td><?php echo h($row['target_action']); ?></td><td><?php echo h($row['device_type'] ?: 'Unknown'); ?></td><td><?php echo h($row['browser'] ?: 'Unknown'); ?></td><td><?php echo h($row['os'] ?: 'Unknown'); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><div class="pagination"><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">Previous</a><span><?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></span><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($pages, $page + 1), 'export' => null]))); ?>">Next</a></div><?php else: ?><p>Page <?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></p><?php endif; ?>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
