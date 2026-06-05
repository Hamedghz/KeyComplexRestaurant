<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Live Visitors';
$error = '';
$rows = [];
try {
    if (adminTableExists('analytics_sessions') && adminTableExists('analytics_visitors')) {
        $stmt = $db->query('SELECT s.session_uuid, s.visitor_uuid, s.started_at, s.last_activity_at, s.landing_page, s.referrer, s.source, s.medium, v.country, v.city, v.browser, v.os, v.device_type FROM analytics_sessions s LEFT JOIN analytics_visitors v ON v.visitor_uuid = s.visitor_uuid WHERE s.last_activity_at >= (NOW() - INTERVAL 5 MINUTE) ORDER BY s.last_activity_at DESC LIMIT 200');
        $rows = $stmt ? $stmt->fetchAll() : [];
    }
} catch (Throwable $e) { safeAdminLog('Live analytics failed: ' . $e->getMessage()); $error = 'گزارش زنده در دسترس نیست.'; }
include __DIR__ . '/includes/header.php';
?>
<meta http-equiv="refresh" content="5">
<div class="card"><div class="card-header"><h2>Live Visitors</h2><span class="badge badge-success">Auto refresh: 5s</span></div><div class="card-body">
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row"><div class="stat-card stat-success"><div class="stat-icon">🟢</div><div class="stat-content"><h3><?php echo h((string)count($rows)); ?></h3><p>Active sessions</p></div></div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>Country</th><th>City</th><th>Device</th><th>Browser</th><th>OS</th><th>Landing</th><th>Referrer</th><th>Source</th><th>Started</th><th>Last Activity</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="11" class="text-muted">No active visitors.</td></tr><?php endif; ?><?php foreach($rows as $row): ?><tr><td><?php echo h($row['session_uuid']); ?></td><td><?php echo h($row['country'] ?: 'Unknown'); ?></td><td><?php echo h($row['city'] ?: 'Unknown'); ?></td><td><?php echo h($row['device_type'] ?: 'Unknown'); ?></td><td><?php echo h($row['browser'] ?: 'Unknown'); ?></td><td><?php echo h($row['os'] ?: 'Unknown'); ?></td><td><?php echo h($row['landing_page']); ?></td><td><?php echo h($row['referrer']); ?></td><td><?php echo h(trim(($row['source'] ?? '') . ' / ' . ($row['medium'] ?? ''), ' /')); ?></td><td><?php echo h($row['started_at']); ?></td><td><?php echo h($row['last_activity_at']); ?></td></tr><?php endforeach; ?></tbody></table></div>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
