<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Traffic Sources';
$from = trim((string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$params = ['from' => $from, 'to' => $to];
$stmt = $db->prepare('SELECT source_name, source_type, SUM(visits_count) AS visits, MIN(`date`) AS first_seen, MAX(`date`) AS last_seen FROM traffic_sources WHERE `date` BETWEEN :from AND :to GROUP BY source_name, source_type ORDER BY visits DESC, source_name ASC');
$stmt->execute($params);
$sources = $stmt->fetchAll();
$dailyStmt = $db->prepare('SELECT `date`, SUM(visits_count) AS visits FROM traffic_sources WHERE `date` BETWEEN :from AND :to GROUP BY `date` ORDER BY `date` ASC');
$dailyStmt->execute($params);
$daily = $dailyStmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Traffic Sources</h2><a class="btn btn-primary" href="analytics-export.php?type=traffic_sources&date_from=<?php echo h($from); ?>&date_to=<?php echo h($to); ?>">Export CSV</a></div><div class="card-body">
<form class="admin-filter" method="get"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">Filter</button></form>
<div class="stats-row"><div class="stat-card stat-primary"><div class="stat-icon">🧭</div><div class="stat-content"><h3><?php echo h(array_sum(array_map(fn($r)=>(int)$r['visits'], $sources))); ?></h3><p>Total visits</p></div></div><div class="stat-card stat-success"><div class="stat-icon">📣</div><div class="stat-content"><h3><?php echo h(count($sources)); ?></h3><p>Sources</p></div></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Source</th><th>Type</th><th>Visits</th><th>First Seen</th><th>Last Seen</th></tr></thead><tbody><?php foreach($sources as $row): ?><tr><td><?php echo h($row['source_name']); ?></td><td><?php echo h($row['source_type']); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($row['first_seen']); ?></td><td><?php echo h($row['last_seen']); ?></td></tr><?php endforeach; ?></tbody></table></div>
<h3 class="mt-3">Daily Trend</h3><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Visits</th></tr></thead><tbody><?php foreach($daily as $row): ?><tr><td><?php echo h($row['date']); ?></td><td><?php echo h($row['visits']); ?></td></tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
