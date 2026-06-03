<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Geographic Analytics';
$from = trim((string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$stmt = $db->prepare('SELECT COALESCE(country, "Unknown") AS country, COALESCE(city, "Unknown") AS city, COUNT(*) AS visits, COUNT(DISTINCT session_id) AS unique_sessions FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY country, city ORDER BY visits DESC LIMIT 200');
$stmt->execute(['from'=>$from,'to'=>$to]);
$rows = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Geographic Analytics</h2><a class="btn btn-primary" href="analytics-export.php?type=geographic&date_from=<?php echo h($from); ?>&date_to=<?php echo h($to); ?>">Export CSV</a></div><div class="card-body"><form class="admin-filter"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><button class="btn btn-primary">Filter</button></form><div class="table-responsive"><table class="table"><thead><tr><th>Country</th><th>City</th><th>Visits</th><th>Unique Sessions</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?php echo h($row['country']); ?></td><td><?php echo h($row['city']); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($row['unique_sessions']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
