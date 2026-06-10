<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
ensureAdminVisitorAnalyticsSchema();
$db = adminDb();
$pageTitle = 'Visitor Path Analytics';
$error = '';
$emptyState = 'هنوز داده‌ای ثبت نشده است. صفحه عمومی سایت را باز کنید تا اولین بازدید ثبت شود.';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$sourceType = trim(substr((string)($_GET['source_type'] ?? ''), 0, 80));
$deviceType = trim(substr((string)($_GET['device_type'] ?? ''), 0, 80));
$export = ($_GET['export'] ?? '') === 'csv';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$limit = $export ? 10000 : $perPage;
$offset = $export ? 0 : (($page - 1) * $perPage);
$rows = [];
$total = 0;
$summary = ['sessions' => 0, 'pageviews' => 0];

try {
    if (adminTableExists('visitor_analytics_logs')) {
        $where = ['DATE(created_at) BETWEEN :from AND :to'];
        $params = ['from' => $from, 'to' => $to];
        if ($q !== '') { $where[] = '(session_id LIKE :q_session OR landing_page LIKE :q_landing OR current_page LIKE :q_current OR next_page LIKE :q_next OR related_module LIKE :q_module OR target_action LIKE :q_target)'; $like = '%' . $q . '%'; $params += ['q_session' => $like, 'q_landing' => $like, 'q_current' => $like, 'q_next' => $like, 'q_module' => $like, 'q_target' => $like]; }
        if ($sourceType !== '') { $where[] = '(source_type = :source_type_filter OR campaign_type = :campaign_type_filter OR source_name = :source_name_filter)'; $params += ['source_type_filter' => $sourceType, 'campaign_type_filter' => $sourceType, 'source_name_filter' => $sourceType]; }
        if ($deviceType !== '') { $where[] = 'device_type = :device_type'; $params['device_type'] = $deviceType; }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT session_id, COALESCE(NULLIF(landing_page, ''), current_page, 'Unknown') AS landing_page, current_page, next_page, duration_seconds, related_module, target_action, created_at FROM visitor_analytics_logs WHERE {$whereSql} ORDER BY session_id ASC, created_at ASC, id ASC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM visitor_analytics_logs WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $summaryStmt = $db->prepare("SELECT COUNT(DISTINCT session_id) AS sessions, COUNT(*) AS pageviews FROM visitor_analytics_logs WHERE {$whereSql}");
        $summaryStmt->execute($params);
        $summary = $summaryStmt->fetch() ?: $summary;
    }
} catch (Throwable $e) {
    safeAdminLog('Visitor path analytics failed: ' . $e->getMessage());
    $error = 'گزارش مسیر بازدیدکنندگان در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visitor-paths-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['session_id','landing_page','current_page','next_page','duration_seconds','related_module','target_action','created_at']);
    foreach ($rows as $row) fputcsv($out, [$row['session_id'],$row['landing_page'],$row['current_page'],$row['next_page'],$row['duration_seconds'],$row['related_module'],$row['target_action'],$row['created_at']]);
    exit;
}

$pages = max(1, (int)ceil($total / $perPage));
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row"><div class="stat-card"><div class="stat-content"><h3><?php echo h(number_format((int)($summary['sessions'] ?? 0))); ?></h3><p>Sessions</p></div></div><div class="stat-card"><div class="stat-content"><h3><?php echo h(number_format((int)($summary['pageviews'] ?? 0))); ?></h3><p>Path rows</p></div></div></div>
<div class="card"><div class="card-header"><h2>Visitor Path Analytics</h2><a class="btn btn-primary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv', 'page'=>null]))); ?>">Export CSV</a></div><div class="card-body">
<form class="admin-filter" method="get">
    <input class="form-control" name="q" placeholder="Search" value="<?php echo h($q); ?>">
    <input class="form-control" name="source_type" placeholder="Source type" value="<?php echo h($sourceType); ?>">
    <input class="form-control" name="device_type" placeholder="Device" value="<?php echo h($deviceType); ?>">
    <input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>">
    <input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>">
    <button class="btn btn-primary">Filter</button>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>Landing Page</th><th>Current Page</th><th>Next Page</th><th>Duration</th><th>Related Module</th><th>Target Action</th><th>Time</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?php echo h($row['session_id']); ?></td><td><?php echo h($row['landing_page'] ?: 'Unknown'); ?></td><td><?php echo h($row['current_page'] ?: 'Unknown'); ?></td><td><?php echo h($row['next_page'] ?: '—'); ?></td><td><?php echo h($row['duration_seconds'] !== null ? $row['duration_seconds'] . 's' : '—'); ?></td><td><?php echo h($row['related_module'] ?: '—'); ?></td><td><?php echo h($row['target_action'] ?: 'page_view'); ?></td><td><?php echo h($row['created_at']); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><div class="pagination"><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">Previous</a><span><?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></span><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($pages, $page + 1), 'export' => null]))); ?>">Next</a></div><?php else: ?><p>Page <?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></p><?php endif; ?>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
