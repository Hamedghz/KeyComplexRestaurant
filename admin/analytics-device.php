<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Device Analytics';
$from = trim((string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$group = in_array(($_GET['group'] ?? 'device'), ['device','browser','os','language'], true) ? $_GET['group'] : 'device';
$stmt = $db->prepare("SELECT COALESCE(`{$group}`, 'Unknown') AS label, COUNT(*) AS visits, COUNT(DISTINCT session_id) AS unique_sessions, ROUND(AVG(COALESCE(visit_duration,0)), 0) AS avg_duration FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY label ORDER BY visits DESC");
$stmt->execute(['from'=>$from,'to'=>$to]);
$rows = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>Device Analytics</h2><a class="btn btn-primary" href="analytics-export.php?type=device&group=<?php echo h($group); ?>&date_from=<?php echo h($from); ?>&date_to=<?php echo h($to); ?>">Export CSV</a></div><div class="card-body"><form class="admin-filter"><input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>"><input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>"><select class="form-control" name="group"><?php foreach(['device'=>'Device','browser'=>'Browser','os'=>'OS','language'=>'Language'] as $k=>$v): ?><option value="<?php echo h($k); ?>" <?php echo $group===$k?'selected':''; ?>><?php echo h($v); ?></option><?php endforeach; ?></select><button class="btn btn-primary">Filter</button></form><div class="table-responsive"><table class="table"><thead><tr><th><?php echo h(ucfirst($group)); ?></th><th>Visits</th><th>Unique Sessions</th><th>Avg Duration</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?php echo h($row['label']); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($row['unique_sessions']); ?></td><td><?php echo h($row['avg_duration']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
