<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
$schemaMessages = ensureAdminSchema();
ensureAdminVisitorAnalyticsSchema();
$db = adminDb();
$pageTitle = 'Visitor Path Analytics';
$error = '';

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? $_GET['date_to'] : date('Y-m-d');
$filters = ['source_type','campaign_type','entry_link','landing_page','next_page','target_action','device_type','browser','branch_id','is_new_visitor','is_converted'];
$where = ['DATE(created_at) BETWEEN :from AND :to'];
$params = ['from' => $from, 'to' => $to];
foreach ($filters as $filter) {
    if (isset($_GET[$filter]) && $_GET[$filter] !== '') {
        $where[] = '`' . str_replace('`', '``', $filter) . '` = :' . $filter;
        $params[$filter] = $_GET[$filter];
    }
}
$whereSql = implode(' AND ', $where);

try {
    if (($_GET['export'] ?? '') === 'csv') {
        $stmt = $db->prepare('SELECT source_type,campaign_type,entry_link,landing_page,current_page,next_page,target_action,event_type,device_type,browser,operating_system,branch_id,is_new_visitor,is_converted,duration_seconds,created_at FROM visitor_analytics_logs WHERE ' . $whereSql . ' ORDER BY id DESC LIMIT 10000');
        $stmt->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="visitor-analytics-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['External source','Campaign type','Entry link','Landing page','Current page','Next page','Target action','Event type','Device','Browser','OS','Branch','New visitor','Converted','Duration','Created']);
        foreach ($stmt->fetchAll() as $row) fputcsv($out, $row);
        exit;
    }

    $statsStmt = $db->prepare('SELECT COUNT(*) total_entries, COUNT(DISTINCT session_id) unique_visitors, AVG(duration_seconds) avg_duration, AVG(is_new_visitor) new_rate, AVG(1-is_new_visitor) returning_rate, AVG(is_converted) conversion_rate FROM visitor_analytics_logs WHERE ' . $whereSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch() ?: [];

    $bestSourceStmt = $db->prepare('SELECT COALESCE(source_type,"Unknown") label, COUNT(*) total FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY label ORDER BY total DESC LIMIT 1');
    $bestSourceStmt->execute($params);
    $bestSource = $bestSourceStmt->fetch();

    $weakLandingStmt = $db->prepare('SELECT COALESCE(landing_page,"Unknown") label, AVG(CASE WHEN next_page IS NULL OR next_page = "" THEN 1 ELSE 0 END) exit_rate, COUNT(*) total FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY label HAVING total > 0 ORDER BY exit_rate DESC, total DESC LIMIT 1');
    $weakLandingStmt->execute($params);
    $weakLanding = $weakLandingStmt->fetch();

    $bestPathStmt = $db->prepare('SELECT CONCAT(COALESCE(source_type,"Unknown"), " → ", COALESCE(landing_page,"Unknown"), " → ", COALESCE(next_page,"Exit"), " → ", COALESCE(target_action,"none")) path, AVG(is_converted) rate, COUNT(*) total FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY path HAVING total > 0 ORDER BY rate DESC, total DESC LIMIT 1');
    $bestPathStmt->execute($params);
    $bestPath = $bestPathStmt->fetch();

    $mainStmt = $db->prepare('SELECT source_type,campaign_type,entry_link,landing_page,next_page,COUNT(*) total_entries,COUNT(DISTINCT session_id) unique_visitors,AVG(CASE WHEN next_page IS NULL OR next_page = "" THEN 1 ELSE 0 END) first_exit_rate,AVG(CASE WHEN next_page IS NOT NULL AND next_page <> "" THEN 1 ELSE 0 END) second_transition_rate,COALESCE(target_action,"none") final_action,AVG(is_converted) conversion_rate,device_type,branch_id,MAX(created_at) last_entry_date FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY source_type,campaign_type,entry_link,landing_page,next_page,final_action,device_type,branch_id ORDER BY total_entries DESC, last_entry_date DESC LIMIT 200');
    $mainStmt->execute($params);
    $rows = $mainStmt->fetchAll();

    $sourceReport = $db->prepare('SELECT COALESCE(source_type,"Unknown") label, COUNT(*) total, COUNT(DISTINCT session_id) visitors, AVG(is_converted) conversion_rate FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY label ORDER BY total DESC LIMIT 50');
    $sourceReport->execute($params);
    $sources = $sourceReport->fetchAll();

    $landingReport = $db->prepare('SELECT COALESCE(landing_page,"Unknown") label, COUNT(*) total, AVG(CASE WHEN next_page IS NULL OR next_page = "" THEN 1 ELSE 0 END) exit_rate, AVG(is_converted) conversion_rate FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY label ORDER BY total DESC LIMIT 50');
    $landingReport->execute($params);
    $landings = $landingReport->fetchAll();

    $pathReport = $db->prepare('SELECT COALESCE(source_type,"Unknown") source_type, COALESCE(landing_page,"Unknown") landing_page, COALESCE(next_page,"Exit") next_page, COALESCE(target_action,"none") target_action, COUNT(*) total, AVG(is_converted) conversion_rate FROM visitor_analytics_logs WHERE ' . $whereSql . ' GROUP BY source_type, landing_page, next_page, target_action ORDER BY total DESC LIMIT 50');
    $pathReport->execute($params);
    $paths = $pathReport->fetchAll();
} catch (Throwable $e) {
    safeAdminLog('Visitor path analytics failed: ' . $e->getMessage());
    $error = 'گزارش مسیر بازدیدکنندگان در دسترس نیست.';
    $stats = []; $bestSource = $weakLanding = $bestPath = null; $rows = $sources = $landings = $paths = [];
}

$card = static fn($title, $value) => '<div class="stat-card"><div class="stat-content"><h3>' . h($value) . '</h3><p>' . h($title) . '</p></div></div>';
$percent = static fn($value) => number_format(((float)$value) * 100, 1) . '%';
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row">
    <?php echo $card('Total external entries', number_format((int)($stats['total_entries'] ?? 0))); ?>
    <?php echo $card('Unique visitors', number_format((int)($stats['unique_visitors'] ?? 0))); ?>
    <?php echo $card('New visitor rate', $percent($stats['new_rate'] ?? 0)); ?>
    <?php echo $card('Returning visitor rate', $percent($stats['returning_rate'] ?? 0)); ?>
    <?php echo $card('Average session duration', number_format((float)($stats['avg_duration'] ?? 0), 1) . 's'); ?>
    <?php echo $card('Target conversion rate', $percent($stats['conversion_rate'] ?? 0)); ?>
    <?php echo $card('Best source', $bestSource ? $bestSource['label'] : '—'); ?>
    <?php echo $card('Weakest landing page', $weakLanding ? $weakLanding['label'] : '—'); ?>
    <?php echo $card('Best converting path', $bestPath ? $bestPath['path'] : '—'); ?>
</div>
<div class="card"><div class="card-header"><h2>Filters</h2><a class="btn btn-primary" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv']))); ?>">Export CSV</a></div><div class="card-body">
<form class="admin-filter" method="get">
    <input class="form-control" type="date" name="date_from" value="<?php echo h($from); ?>">
    <input class="form-control" type="date" name="date_to" value="<?php echo h($to); ?>">
    <select class="form-control" name="source_type"><option value="">External source</option><?php foreach (adminVisitorAnalyticsSources() as $source): ?><option value="<?php echo h($source); ?>" <?php echo ($_GET['source_type'] ?? '') === $source ? 'selected' : ''; ?>><?php echo h($source); ?></option><?php endforeach; ?></select>
    <?php foreach (['campaign_type'=>'Campaign type','entry_link'=>'Entry link','landing_page'=>'Landing page','next_page'=>'Next page','target_action'=>'Target action','device_type'=>'Device','browser'=>'Browser','branch_id'=>'Branch'] as $name => $label): ?>
        <input class="form-control" name="<?php echo h($name); ?>" placeholder="<?php echo h($label); ?>" value="<?php echo h($_GET[$name] ?? ''); ?>">
    <?php endforeach; ?>
    <select class="form-control" name="is_new_visitor"><option value="">New/returning</option><option value="1" <?php echo ($_GET['is_new_visitor'] ?? '') === '1' ? 'selected' : ''; ?>>New visitor</option><option value="0" <?php echo ($_GET['is_new_visitor'] ?? '') === '0' ? 'selected' : ''; ?>>Returning visitor</option></select>
    <select class="form-control" name="is_converted"><option value="">Converted?</option><option value="1" <?php echo ($_GET['is_converted'] ?? '') === '1' ? 'selected' : ''; ?>>Converted</option><option value="0" <?php echo ($_GET['is_converted'] ?? '') === '0' ? 'selected' : ''; ?>>Not converted</option></select>
    <button class="btn btn-primary">Filter</button>
</form>
</div></div>
<div class="card"><div class="card-header"><h2>External source → landing page → internal path → final action</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>External source</th><th>Campaign type</th><th>Entry link</th><th>Landing page</th><th>Next page</th><th>Total entries</th><th>Unique visitors</th><th>First-page exit rate</th><th>Second-page transition rate</th><th>Final action</th><th>Conversion rate</th><th>Device</th><th>Branch</th><th>Last entry date</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="14" class="text-muted">No visitor path analytics data has been collected yet.</td></tr><?php endif; ?><?php foreach ($rows as $row): ?><tr><td><?php echo h($row['source_type']); ?></td><td><?php echo h($row['campaign_type']); ?></td><td><?php echo h($row['entry_link']); ?></td><td><?php echo h($row['landing_page']); ?></td><td><?php echo h($row['next_page']); ?></td><td><?php echo h($row['total_entries']); ?></td><td><?php echo h($row['unique_visitors']); ?></td><td><?php echo h($percent($row['first_exit_rate'])); ?></td><td><?php echo h($percent($row['second_transition_rate'])); ?></td><td><?php echo h($row['final_action']); ?></td><td><?php echo h($percent($row['conversion_rate'])); ?></td><td><?php echo h($row['device_type']); ?></td><td><?php echo h($row['branch_id']); ?></td><td><?php echo h(formatJalaliDateTime($row['last_entry_date'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="stats-row">
    <div class="card"><div class="card-header"><h2>Source performance report</h2></div><div class="card-body"><table class="table"><tr><th>Source</th><th>Entries</th><th>Visitors</th><th>Conversion</th></tr><?php foreach ($sources as $row): ?><tr><td><?php echo h($row['label']); ?></td><td><?php echo h($row['total']); ?></td><td><?php echo h($row['visitors']); ?></td><td><?php echo h($percent($row['conversion_rate'])); ?></td></tr><?php endforeach; ?></table></div></div>
    <div class="card"><div class="card-header"><h2>Landing page performance report</h2></div><div class="card-body"><table class="table"><tr><th>Landing</th><th>Entries</th><th>Exit</th><th>Conversion</th></tr><?php foreach ($landings as $row): ?><tr><td><?php echo h($row['label']); ?></td><td><?php echo h($row['total']); ?></td><td><?php echo h($percent($row['exit_rate'])); ?></td><td><?php echo h($percent($row['conversion_rate'])); ?></td></tr><?php endforeach; ?></table></div></div>
</div>
<div class="card"><div class="card-header"><h2>User path / conversion funnel / weak landing pages report</h2></div><div class="card-body"><table class="table"><tr><th>Source</th><th>Landing</th><th>Next page</th><th>Target action</th><th>Entries</th><th>Conversion</th></tr><?php foreach ($paths as $row): ?><tr><td><?php echo h($row['source_type']); ?></td><td><?php echo h($row['landing_page']); ?></td><td><?php echo h($row['next_page']); ?></td><td><?php echo h($row['target_action']); ?></td><td><?php echo h($row['total']); ?></td><td><?php echo h($percent($row['conversion_rate'])); ?></td></tr><?php endforeach; ?></table></div></div>
<div class="card mt-3"><div class="card-header"><h2>Connected modules</h2></div><div class="card-body"><p class="text-muted">This report connects visitor paths to banners, matches, predictions, categories, menu items, surveys, survey responses, and CRM through related_module / related_record_id and target_action values.</p><ul><?php foreach ($schemaMessages as $m): ?><li><?php echo h($m); ?></li><?php endforeach; ?></ul></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
