<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Traffic Sources';
$error = '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$sources = [];
$daily = [];
try {
    if (adminTableExists('analytics_sessions')) {
        $params = ['from' => $from, 'to' => $to];
        $stmt = $db->prepare('SELECT COALESCE(NULLIF(source, ""), "unknown") AS source_name, COALESCE(NULLIF(medium, ""), "unknown") AS source_type, COUNT(*) AS visits, MIN(DATE(started_at)) AS first_seen, MAX(DATE(last_activity_at)) AS last_seen FROM analytics_sessions WHERE DATE(started_at) BETWEEN :from AND :to GROUP BY source_name, source_type ORDER BY visits DESC, source_name ASC');
        $stmt->execute($params);
        $sources = $stmt->fetchAll();
        $dailyStmt = $db->prepare('SELECT DATE(started_at) AS date, COUNT(*) AS visits FROM analytics_sessions WHERE DATE(started_at) BETWEEN :from AND :to GROUP BY DATE(started_at) ORDER BY date ASC');
        $dailyStmt->execute($params);
        $daily = $dailyStmt->fetchAll();
    }
} catch (Throwable $e) { safeAdminLog('Traffic source analytics failed: ' . $e->getMessage()); $error = 'گزارش منابع ترافیک در دسترس نیست.'; }
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Traffic Sources</h2><a class="btn btn-primary" href="analytics-export.php?type=traffic_sources&date_from=<?php echo h($from); ?>&date_to=<?php echo h($to); ?>">Export CSV</a></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form class="admin-filter" method="get"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">Filter</button></form>
<div class="stats-row"><div class="stat-card stat-primary"><div class="stat-icon">🧭</div><div class="stat-content"><h3><?php echo h((string)array_sum(array_map(fn($r)=>(int)$r['visits'], $sources))); ?></h3><p>Total sessions</p></div></div><div class="stat-card stat-success"><div class="stat-icon">📣</div><div class="stat-content"><h3><?php echo h((string)count($sources)); ?></h3><p>Sources</p></div></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Source</th><th>Medium</th><th>Sessions</th><th>First Seen</th><th>Last Seen</th></tr></thead><tbody><?php if (!$sources): ?><tr><td colspan="5" class="text-muted">No traffic source data has been collected.</td></tr><?php endif; ?><?php foreach($sources as $row): ?><tr><td><?php echo h($row['source_name']); ?></td><td><?php echo h($row['source_type']); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($row['first_seen']); ?></td><td><?php echo h($row['last_seen']); ?></td></tr><?php endforeach; ?></tbody></table></div>
<h3 class="mt-3">Daily Trend</h3><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Sessions</th></tr></thead><tbody><?php if (!$daily): ?><tr><td colspan="2" class="text-muted">No daily source data has been collected.</td></tr><?php endif; ?><?php foreach($daily as $row): ?><tr><td><?php echo h($row['date']); ?></td><td><?php echo h($row['visits']); ?></td></tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
