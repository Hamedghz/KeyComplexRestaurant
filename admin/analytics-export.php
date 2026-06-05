<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$type = (string)($_GET['type'] ?? '');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : date('Y-m-d');
$deviceGroups = ['device' => 'device_type', 'browser' => 'browser', 'os' => 'os'];
$exports = [
    'visitor_logs' => [
        'headers' => ['id','session_uuid','ip_hash','country','city','source','medium','landing_page','page_path','page_title','browser','os','device_type','browser_language','timezone','viewed_at'],
        'file' => 'visitor-analytics',
        'sql' => 'SELECT p.id,p.session_uuid,v.ip_hash,COALESCE(NULLIF(v.country,""),"Unknown") AS country,COALESCE(NULLIF(v.city,""),"Unknown") AS city,s.source,s.medium,s.landing_page,p.page_path,p.page_title,v.browser,v.os,v.device_type,p.browser_language,p.timezone,p.viewed_at FROM analytics_pageviews p JOIN analytics_sessions s ON s.session_uuid=p.session_uuid JOIN analytics_visitors v ON v.visitor_uuid=p.visitor_uuid WHERE DATE(p.viewed_at) BETWEEN :from AND :to ORDER BY p.id DESC',
        'tables' => ['analytics_pageviews','analytics_sessions','analytics_visitors'],
    ],
    'traffic_sources' => [
        'headers' => ['source_name','source_type','visits','first_seen','last_seen'],
        'file' => 'traffic-sources',
        'sql' => 'SELECT COALESCE(NULLIF(source,""),"unknown") AS source_name,COALESCE(NULLIF(medium,""),"unknown") AS source_type,COUNT(*) AS visits,MIN(DATE(started_at)) AS first_seen,MAX(DATE(last_activity_at)) AS last_seen FROM analytics_sessions WHERE DATE(started_at) BETWEEN :from AND :to GROUP BY source_name,source_type ORDER BY visits DESC',
        'tables' => ['analytics_sessions'],
    ],
    'geographic' => [
        'headers' => ['country','city','visits','unique_sessions'],
        'file' => 'geographic-analytics',
        'sql' => 'SELECT COALESCE(NULLIF(v.country,""),"Unknown") AS country,COALESCE(NULLIF(v.city,""),"Unknown") AS city,COUNT(p.id) AS visits,COUNT(DISTINCT p.session_uuid) AS unique_sessions FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid=p.visitor_uuid WHERE DATE(p.viewed_at) BETWEEN :from AND :to GROUP BY country,city ORDER BY visits DESC',
        'tables' => ['analytics_pageviews','analytics_visitors'],
    ],
];
if ($type === 'device') {
    $requestedGroup = (string)($_GET['group'] ?? 'device');
    $group = array_key_exists($requestedGroup, $deviceGroups) ? $requestedGroup : 'device';
    $column = $deviceGroups[$group];
    $exports['device'] = [
        'headers' => ['label','visits','unique_sessions'],
        'file' => 'device-analytics',
        'sql' => "SELECT COALESCE(NULLIF(v.`{$column}`,''),'Unknown') AS label,COUNT(p.id) AS visits,COUNT(DISTINCT p.session_uuid) AS unique_sessions FROM analytics_pageviews p JOIN analytics_visitors v ON v.visitor_uuid=p.visitor_uuid WHERE DATE(p.viewed_at) BETWEEN :from AND :to GROUP BY label ORDER BY visits DESC",
        'tables' => ['analytics_pageviews','analytics_visitors'],
    ];
}
if (!isset($exports[$type])) {
    $pageTitle = 'Export Center';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="card"><div class="card-header"><h2>Export Center</h2></div><div class="card-body"><p>Choose an export:</p><div class="quick-actions"><a class="quick-action-btn" href="?type=visitor_logs"><span class="icon">📈</span><span>Visitor Analytics</span></a><a class="quick-action-btn" href="?type=traffic_sources"><span class="icon">🧭</span><span>Traffic Sources</span></a><a class="quick-action-btn" href="?type=geographic"><span class="icon">🌍</span><span>Geographic</span></a><a class="quick-action-btn" href="?type=device"><span class="icon">📱</span><span>Device</span></a></div></div></div>
    <?php include __DIR__ . '/includes/footer.php'; exit;
}
$config = $exports[$type];
$rows = [];
try {
    $tablesReady = true;
    foreach ($config['tables'] as $table) { if (!adminTableExists($table)) { $tablesReady = false; break; } }
    if ($tablesReady) {
        $stmt = $db->prepare($config['sql']);
        $stmt->execute(['from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    safeAdminLog('Analytics export failed: ' . $e->getMessage());
    $rows = [];
}
$filename = preg_replace('/[^a-z0-9\-]/i', '-', $config['file']) . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $config['headers']);
foreach ($rows as $row) {
    $line = [];
    foreach ($config['headers'] as $header) { $line[] = $row[$header] ?? ''; }
    fputcsv($out, $line);
}
fclose($out);
exit;
