<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Visitor Analytics';
$error = '';
$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? (string)$_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? (string)$_GET['date_to'] : '';
$device = trim((string)($_GET['device'] ?? ''));
$country = trim((string)($_GET['country'] ?? ''));
$rows = [];
$total = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
try {
    if (adminTableExists('analytics_pageviews') && adminTableExists('analytics_sessions') && adminTableExists('analytics_visitors')) {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(p.page_path LIKE :q OR p.page_title LIKE :q OR s.referrer LIKE :q OR s.source LIKE :q OR v.city LIKE :q OR v.country LIKE :q)'; $params['q'] = '%' . $q . '%'; }
        if ($device !== '') { $where[] = 'v.device_type = :device'; $params['device'] = $device; }
        if ($country !== '') { $where[] = 'v.country = :country'; $params['country'] = $country; }
        if ($dateFrom !== '') { $where[] = 'p.viewed_at >= :date_from'; $params['date_from'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== '') { $where[] = 'p.viewed_at <= :date_to'; $params['date_to'] = $dateTo . ' 23:59:59'; }
        $whereSql = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT p.id, p.session_uuid, p.page_path, p.page_title, p.referrer AS page_referrer, p.browser_language, p.timezone, p.viewed_at, s.landing_page, s.source, s.medium, v.ip_hash, v.country, v.city, v.browser, v.os, v.device_type FROM analytics_pageviews p JOIN analytics_sessions s ON s.session_uuid = p.session_uuid JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql} ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM analytics_pageviews p JOIN analytics_sessions s ON s.session_uuid = p.session_uuid JOIN analytics_visitors v ON v.visitor_uuid = p.visitor_uuid WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)($countStmt->fetch()['total'] ?? 0);
    }
} catch (Throwable $e) { safeAdminLog('Visitor analytics failed: ' . $e->getMessage()); $error = 'گزارش بازدیدکنندگان در دسترس نیست.'; }
$pages = max(1, (int)ceil($total / $perPage));
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header"><h2>Visitor Analytics</h2></div>
  <div class="card-body">
    <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
    <form method="get" class="admin-filter">
      <input class="form-control" name="q" placeholder="Search" value="<?php echo h($q); ?>">
      <input class="form-control" type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
      <input class="form-control" type="date" name="date_to" value="<?php echo h($dateTo); ?>">
      <input class="form-control" name="device" placeholder="Device" value="<?php echo h($device); ?>">
      <input class="form-control" name="country" placeholder="Country" value="<?php echo h($country); ?>">
      <button class="btn btn-primary">Filter</button>
      <a class="btn" href="analytics-export.php?type=visitor_logs">Export</a>
    </form>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>ID</th><th>IP Hash</th><th>Country</th><th>City</th><th>Source</th><th>Landing</th><th>Page</th><th>Title</th><th>Browser</th><th>OS</th><th>Device</th><th>Lang</th><th>Timezone</th><th>Date</th></tr></thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="14" class="text-muted">No analytics data has been collected.</td></tr><?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <tr><td><?php echo h($row['id']); ?></td><td><?php echo h($row['ip_hash']); ?></td><td><?php echo h($row['country'] ?: 'Unknown'); ?></td><td><?php echo h($row['city'] ?: 'Unknown'); ?></td><td><?php echo h(trim(($row['source'] ?? '') . ' / ' . ($row['medium'] ?? ''), ' /')); ?></td><td><?php echo h($row['landing_page']); ?></td><td><?php echo h($row['page_path']); ?></td><td><?php echo h($row['page_title']); ?></td><td><?php echo h($row['browser'] ?: 'Unknown'); ?></td><td><?php echo h($row['os'] ?: 'Unknown'); ?></td><td><?php echo h($row['device_type'] ?: 'Unknown'); ?></td><td><?php echo h($row['browser_language']); ?></td><td><?php echo h($row['timezone']); ?></td><td><?php echo h($row['viewed_at']); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p>Page <?php echo h((string)$page); ?> / <?php echo h((string)$pages); ?> - Total <?php echo h((string)$total); ?></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
