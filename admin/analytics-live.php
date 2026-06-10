<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Live Visitors';
$error = '';
$emptyState = 'هنوز داده‌ای ثبت نشده است. صفحه عمومی سایت را باز کنید تا اولین بازدید ثبت شود.';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$sourceType = trim(substr((string)($_GET['source_type'] ?? ''), 0, 80));
$deviceType = trim(substr((string)($_GET['device_type'] ?? ''), 0, 80));
$export = ($_GET['export'] ?? '') === 'csv';
$rows = [];

try {
    $params = ['from' => $from, 'to' => $to];
    $hasLegacyLive = false;
    if (adminTableExists('visitor_sessions')) {
        $hasLegacyLive = (int)$db->query('SELECT COUNT(*) FROM visitor_sessions WHERE last_activity >= (NOW() - INTERVAL 5 MINUTE)')->fetchColumn() > 0;
    }

    if ($hasLegacyLive) {
        $where = ['last_activity >= (NOW() - INTERVAL 5 MINUTE)', 'DATE(last_activity) BETWEEN :from AND :to'];
        if ($q !== '') { $where[] = '(session_id LIKE :q_session OR current_page LIKE :q_page OR source_name LIKE :q_source)'; $like = '%' . $q . '%'; $params += ['q_session' => $like, 'q_page' => $like, 'q_source' => $like]; }
        if ($sourceType !== '') { $where[] = 'source_name = :source_type'; $params['source_type'] = $sourceType; }
        if ($deviceType !== '') { $where[] = 'device_type = :device_type'; $params['device_type'] = $deviceType; }
        $stmt = $db->prepare('SELECT session_id, current_page, source_name AS source, device_type AS device, browser, os, started_at, last_activity FROM visitor_sessions WHERE ' . implode(' AND ', $where) . ' ORDER BY last_activity DESC LIMIT 1000');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } elseif (adminTableExists('analytics_sessions') && adminTableExists('analytics_visitors')) {
        $where = ['s.last_activity_at >= (NOW() - INTERVAL 5 MINUTE)', 'DATE(s.last_activity_at) BETWEEN :from AND :to'];
        if ($q !== '') { $where[] = '(s.session_uuid LIKE :q_session OR s.landing_page LIKE :q_page OR s.source LIKE :q_source OR s.medium LIKE :q_medium)'; $like = '%' . $q . '%'; $params += ['q_session' => $like, 'q_page' => $like, 'q_source' => $like, 'q_medium' => $like]; }
        if ($sourceType !== '') { $where[] = '(s.source = :source_filter OR s.medium = :medium_filter)'; $params += ['source_filter' => $sourceType, 'medium_filter' => $sourceType]; }
        if ($deviceType !== '') { $where[] = 'v.device_type = :device_type'; $params['device_type'] = $deviceType; }
        $stmt = $db->prepare('SELECT s.session_uuid AS session_id, s.landing_page AS current_page, COALESCE(NULLIF(s.source, ""), "direct") AS source, v.device_type AS device, v.browser, v.os, s.started_at, s.last_activity_at AS last_activity FROM analytics_sessions s LEFT JOIN analytics_visitors v ON v.visitor_uuid = s.visitor_uuid WHERE ' . implode(' AND ', $where) . ' ORDER BY s.last_activity_at DESC LIMIT 1000');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    safeAdminLog('Live analytics failed: ' . $e->getMessage());
    $error = 'گزارش زنده در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="live-visitors-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['session_id','current_page','source','device','browser','os','started_at','last_activity']);
    foreach ($rows as $row) fputcsv($out, [$row['session_id'],$row['current_page'],$row['source'],$row['device'],$row['browser'],$row['os'],$row['started_at'],$row['last_activity']]);
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<meta http-equiv="refresh" content="5">
<div class="card"><div class="card-header"><h2>Live Visitors</h2><a class="btn btn-primary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv']))); ?>">Export CSV</a></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form class="admin-filter" method="get"><input class="form-control" name="q" placeholder="Search" value="<?php echo h($q); ?>"><input class="form-control" name="source_type" placeholder="Source" value="<?php echo h($sourceType); ?>"><input class="form-control" name="device_type" placeholder="Device" value="<?php echo h($deviceType); ?>"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">Filter</button></form>
<div class="stats-row"><div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h((string)count($rows)); ?></h3><p>Active sessions</p></div></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>Current Page</th><th>Source</th><th>Device</th><th>Browser</th><th>OS</th><th>Started</th><th>Last Activity</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
<?php foreach($rows as $row): ?><tr><td><?php echo h($row['session_id']); ?></td><td><?php echo h($row['current_page'] ?: 'Unknown'); ?></td><td><?php echo h($row['source'] ?: 'direct'); ?></td><td><?php echo h($row['device'] ?: 'Unknown'); ?></td><td><?php echo h($row['browser'] ?: 'Unknown'); ?></td><td><?php echo h($row['os'] ?: 'Unknown'); ?></td><td><?php echo h($row['started_at']); ?></td><td><?php echo h($row['last_activity']); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
