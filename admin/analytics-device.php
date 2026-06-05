<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Device Analytics';
$error = '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$allowedGroups = ['device' => 'device_type', 'browser' => 'browser', 'os' => 'os'];
$group = array_key_exists((string)($_GET['group'] ?? 'device'), $allowedGroups) ? (string)$_GET['group'] : 'device';
$column = $allowedGroups[$group];
$labels = ['device' => 'Device', 'browser' => 'Browser', 'os' => 'OS'];
$rows = [];
try {
    if (adminTableExists('analytics_visitors') && adminTableExists('analytics_sessions') && adminTableExists('analytics_pageviews')) {
        $stmt = $db->prepare("SELECT COALESCE(NULLIF(v.`{$column}`, ''), 'Unknown') AS label, COUNT(p.id) AS visits, COUNT(DISTINCT s.session_uuid) AS unique_sessions FROM analytics_pageviews p JOIN analytics_sessions s ON s.session_uuid = p.session_uuid JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE DATE(p.viewed_at) BETWEEN :from AND :to GROUP BY label ORDER BY visits DESC");
        $stmt->execute(['from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    safeAdminLog('Device analytics failed: ' . $e->getMessage());
    $error = 'گزارش دستگاه‌ها در حال حاضر در دسترس نیست.';
}
include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header"><h2>Device Analytics</h2><a class="btn btn-primary" href="analytics-export.php?type=device&group=<?php echo h($group); ?>&date_from=<?php echo h($from); ?>&date_to=<?php echo h($to); ?>">Export CSV</a></div>
    <div class="card-body">
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <form class="admin-filter">
            <input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>">
            <input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>">
            <select class="form-control" name="group"><?php foreach($labels as $k=>$v): ?><option value="<?php echo h($k); ?>" <?php echo $group===$k?'selected':''; ?>><?php echo h($v); ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary">Filter</button>
        </form>
        <div class="table-responsive"><table class="table"><thead><tr><th><?php echo h($labels[$group]); ?></th><th>Visits</th><th>Unique Sessions</th></tr></thead><tbody>
            <?php if (!$rows): ?><tr><td colspan="3" class="text-muted">No analytics data has been collected for this period.</td></tr><?php endif; ?>
            <?php foreach($rows as $row): ?><tr><td><?php echo h($row['label']); ?></td><td><?php echo h($row['visits']); ?></td><td><?php echo h($row['unique_sessions']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
