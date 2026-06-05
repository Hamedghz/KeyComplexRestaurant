<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Geographic Analytics';
$error = '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$rows = [];
try {
    if (adminTableExists('analytics_visitors') && adminTableExists('analytics_pageviews')) {
        $stmt = $db->prepare('SELECT COALESCE(NULLIF(v.country, ""), "Unknown") AS country, COALESCE(NULLIF(v.city, ""), "Unknown") AS city, COUNT(p.id) AS visits, COUNT(DISTINCT p.session_uuid) AS unique_sessions FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE DATE(p.viewed_at) BETWEEN :from AND :to GROUP BY country, city ORDER BY visits DESC LIMIT 200');
        $stmt->execute(['from'=>$from,'to'=>$to]);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) { safeAdminLog('Geographic analytics failed: ' . $e->getMessage()); $error = 'گزارش جغرافیایی در دسترس نیست.'; }
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Geographic Analytics</h2><a class="btn btn-primary" href="analytics-export.php?type=geographic&date_from=<?php echo h($from); ?>&date_to=<?php echo h($to); ?>">Export CSV</a></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form class="admin-filter"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">Filter</button></form><div class="table-responsive"><table class="table"><thead><tr><th>Country</th><th>City</th><th>Visits</th><th>Unique Sessions</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="4" class="text-muted">No geographic analytics data has been collected.</td></tr><?php endif; ?><?php foreach($rows as $row): ?><tr><td><?php echo h($row['country']); ?></td><td><?php echo h($row['city']); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($row['unique_sessions']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
