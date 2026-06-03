<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Live Visitors';
$db->exec("UPDATE visitor_sessions SET is_active = 0 WHERE last_activity < (NOW() - INTERVAL 5 MINUTE)");
$rows = $db->query("SELECT vs.*, tl.country, tl.city, tl.browser, tl.os, tl.device, tl.landing_page, tl.referrer FROM visitor_sessions vs LEFT JOIN (SELECT session_id, MAX(id) AS max_log_id FROM traffic_logs GROUP BY session_id) latest ON latest.session_id = vs.session_id LEFT JOIN traffic_logs tl ON tl.id = latest.max_log_id WHERE vs.is_active = 1 AND vs.last_activity >= (NOW() - INTERVAL 5 MINUTE) ORDER BY vs.last_activity DESC LIMIT 200")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<meta http-equiv="refresh" content="5">
<div class="card"><div class="card-header"><h2>Live Visitors</h2><span class="badge badge-success">Auto refresh: 5s</span></div><div class="card-body">
<div class="stats-row"><div class="stat-card stat-success"><div class="stat-icon">🟢</div><div class="stat-content"><h3><?php echo h(count($rows)); ?></h3><p>Active sessions</p></div></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>IP</th><th>Country</th><th>City</th><th>Device</th><th>Browser</th><th>OS</th><th>Landing</th><th>Referrer</th><th>Started</th><th>Last Activity</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?php echo h($row['session_id']); ?></td><td><?php echo h($row['ip_address']); ?></td><td><?php echo h($row['country']); ?></td><td><?php echo h($row['city']); ?></td><td><?php echo h($row['device']); ?></td><td><?php echo h($row['browser']); ?></td><td><?php echo h($row['os']); ?></td><td><?php echo h($row['landing_page']); ?></td><td><?php echo h($row['referrer']); ?></td><td><?php echo h($row['started_at']); ?></td><td><?php echo h($row['last_activity']); ?></td></tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
