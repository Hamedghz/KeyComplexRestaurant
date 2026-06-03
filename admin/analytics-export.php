<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$type = (string)($_GET['type'] ?? '');
$from = trim((string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$exports = [
    'visitor_logs' => ['sql' => 'SELECT id,session_id,ip_address,country,city,isp,referrer,landing_page,browser,os,device,language,visit_duration,pages_viewed,is_bot,created_at FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to ORDER BY id DESC', 'file' => 'visitor-logs'],
    'traffic_sources' => ['sql' => 'SELECT source_name,source_type,SUM(visits_count) AS visits,MIN(`date`) AS first_seen,MAX(`date`) AS last_seen FROM traffic_sources WHERE `date` BETWEEN :from AND :to GROUP BY source_name,source_type ORDER BY visits DESC', 'file' => 'traffic-sources'],
    'geographic' => ['sql' => 'SELECT COALESCE(country,"Unknown") AS country,COALESCE(city,"Unknown") AS city,COUNT(*) AS visits,COUNT(DISTINCT session_id) AS unique_sessions FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY country,city ORDER BY visits DESC', 'file' => 'geographic-analytics'],
];
if ($type === 'device') {
    $group = in_array(($_GET['group'] ?? 'device'), ['device','browser','os','language'], true) ? $_GET['group'] : 'device';
    $exports['device'] = ['sql' => "SELECT COALESCE(`{$group}`,'Unknown') AS label,COUNT(*) AS visits,COUNT(DISTINCT session_id) AS unique_sessions,ROUND(AVG(COALESCE(visit_duration,0)),0) AS avg_duration FROM traffic_logs WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY label ORDER BY visits DESC", 'file' => 'device-analytics'];
}
if (!isset($exports[$type])) {
    $pageTitle = 'Export Center';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="card"><div class="card-header"><h2>Export Center</h2></div><div class="card-body"><p>Choose an export:</p><div class="quick-actions"><a class="quick-action-btn" href="?type=visitor_logs"><span class="icon">📈</span><span>Visitor Logs</span></a><a class="quick-action-btn" href="?type=traffic_sources"><span class="icon">🧭</span><span>Traffic Sources</span></a><a class="quick-action-btn" href="?type=geographic"><span class="icon">🌍</span><span>Geographic</span></a><a class="quick-action-btn" href="?type=device"><span class="icon">📱</span><span>Device</span></a></div></div></div>
    <?php include __DIR__ . '/includes/footer.php'; exit;
}
$stmt = $db->prepare($exports[$type]['sql']);
$stmt->execute(['from' => $from, 'to' => $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $exports[$type]['file'] . '-' . date('Ymd-His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
if ($rows) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $row) fputcsv($out, $row); }
else { fputcsv($out, ['No records']); }
fclose($out);
