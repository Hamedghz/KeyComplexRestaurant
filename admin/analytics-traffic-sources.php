<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Traffic Sources';
$error = '';
$emptyState = 'هنوز داده‌ای ثبت نشده است. صفحه عمومی سایت را باز کنید تا اولین بازدید ثبت شود.';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$q = trim(substr((string)($_GET['q'] ?? ''), 0, 150));
$sourceType = trim(substr((string)($_GET['source_type'] ?? ''), 0, 80));
$export = ($_GET['export'] ?? '') === 'csv';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$limit = $export ? 10000 : $perPage;
$offset = $export ? 0 : (($page - 1) * $perPage);
$sources = [];
$total = 0;

try {
    $params = ['from' => $from, 'to' => $to];
    $hasLegacy = false;
    if (adminTableExists('traffic_sources')) {
        $countStmt = $db->prepare('SELECT COUNT(*) FROM traffic_sources WHERE `date` BETWEEN :from AND :to');
        $countStmt->execute($params);
        $hasLegacy = (int)$countStmt->fetchColumn() > 0;
    }

    if ($hasLegacy) {
        $where = ['`date` BETWEEN :from AND :to'];
        if ($q !== '') { $where[] = 'source_name LIKE :q'; $params['q'] = '%' . $q . '%'; }
        if ($sourceType !== '') { $where[] = 'source_type = :source_type'; $params['source_type'] = $sourceType; }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT source_name, source_type, visits_count, `date`, NULL AS campaign, NULL AS utm_source, NULL AS utm_medium, NULL AS utm_campaign FROM traffic_sources WHERE {$whereSql} ORDER BY `date` DESC, visits_count DESC, source_name ASC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $sources = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM traffic_sources WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    } elseif (adminTableExists('analytics_sessions')) {
        $where = ['DATE(started_at) BETWEEN :from AND :to'];
        if ($q !== '') { $where[] = '(source LIKE :q_source OR medium LIKE :q_medium OR campaign LIKE :q_campaign OR utm_campaign LIKE :q_utm_campaign)'; $like = '%' . $q . '%'; $params += ['q_source' => $like, 'q_medium' => $like, 'q_campaign' => $like, 'q_utm_campaign' => $like]; }
        if ($sourceType !== '') { $where[] = '(source = :source_filter OR medium = :medium_filter)'; $params += ['source_filter' => $sourceType, 'medium_filter' => $sourceType]; }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(source, ''), 'unknown') AS source_name, COALESCE(NULLIF(medium, ''), 'unknown') AS source_type, COUNT(*) AS visits_count, DATE(started_at) AS `date`, COALESCE(NULLIF(campaign, ''), NULLIF(utm_campaign, '')) AS campaign, utm_source, utm_medium, utm_campaign FROM analytics_sessions WHERE {$whereSql} GROUP BY source_name, source_type, `date`, campaign, utm_source, utm_medium, utm_campaign ORDER BY `date` DESC, visits_count DESC, source_name ASC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $sources = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) FROM (SELECT 1 FROM analytics_sessions WHERE {$whereSql} GROUP BY COALESCE(NULLIF(source, ''), 'unknown'), COALESCE(NULLIF(medium, ''), 'unknown'), DATE(started_at), COALESCE(NULLIF(campaign, ''), NULLIF(utm_campaign, '')), utm_source, utm_medium, utm_campaign) grouped");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
    }
} catch (Throwable $e) {
    safeAdminLog('Traffic source analytics failed: ' . $e->getMessage());
    $error = 'گزارش منابع ترافیک در دسترس نیست.';
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="traffic-sources-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['source_name','source_type','visits_count','date','campaign','utm_source','utm_medium','utm_campaign']);
    foreach ($sources as $row) fputcsv($out, [$row['source_name'],$row['source_type'],$row['visits_count'],$row['date'],$row['campaign'],$row['utm_source'],$row['utm_medium'],$row['utm_campaign']]);
    exit;
}

$pages = max(1, (int)ceil($total / $perPage));
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Traffic Sources</h2><a class="btn btn-primary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv', 'page'=>null]))); ?>">Export CSV</a></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form class="admin-filter" method="get"><input class="form-control" name="q" placeholder="Search" value="<?php echo h($q); ?>"><input class="form-control" name="source_type" placeholder="Source type" value="<?php echo h($sourceType); ?>"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">Filter</button></form>
<div class="stats-row"><div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo h((string)array_sum(array_map(static fn($r)=>(int)$r['visits_count'], $sources))); ?></h3><p>Visible visits</p></div></div><div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h((string)$total); ?></h3><p>Source rows</p></div></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Source</th><th>Type / Medium</th><th>Visits</th><th>Date</th><th>Campaign / UTM</th></tr></thead><tbody>
<?php if (!$sources): ?><tr><td colspan="5" class="text-muted"><?php echo h($emptyState); ?></td></tr><?php endif; ?>
<?php foreach($sources as $row): ?><tr><td><?php echo h($row['source_name'] ?: 'unknown'); ?></td><td><?php echo h($row['source_type'] ?: 'unknown'); ?></td><td><?php echo h($row['visits_count']); ?></td><td><?php echo h($row['date']); ?></td><td><?php echo h(trim(($row['campaign'] ?? '') . ' ' . ($row['utm_source'] ?? '') . ' ' . ($row['utm_medium'] ?? '') . ' ' . ($row['utm_campaign'] ?? '')) ?: '—'); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><div class="pagination"><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => max(1, $page - 1), 'export' => null]))); ?>">Previous</a><span><?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></span><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['page' => min($pages, $page + 1), 'export' => null]))); ?>">Next</a></div><?php else: ?><p>Page <?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></p><?php endif; ?>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
