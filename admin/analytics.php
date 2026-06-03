<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'Visitor Logs';

$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$device = trim((string)($_GET['device'] ?? ''));
$country = trim((string)($_GET['country'] ?? ''));

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(ip_address LIKE :q OR city LIKE :q OR country LIKE :q OR referrer LIKE :q OR landing_page LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($device !== '') { $where[] = 'device = :device'; $params['device'] = $device; }
if ($country !== '') { $where[] = 'country = :country'; $params['country'] = $country; }
if ($dateFrom !== '') { $where[] = 'created_at >= :date_from'; $params['date_from'] = parsePersianDate($dateFrom, false) . ' 00:00:00'; }
if ($dateTo !== '') { $where[] = 'created_at <= :date_to'; $params['date_to'] = parsePersianDate($dateTo, false) . ' 23:59:59'; }

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("SELECT * FROM traffic_logs WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM traffic_logs WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int)($countStmt->fetch()['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));

include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-header"><h2>Visitor Logs</h2></div>
  <div class="card-body">
    <form method="get" class="admin-filter">
      <input class="form-control" name="q" placeholder="Search" value="<?php echo h($q); ?>">
      <input class="form-control" name="date_from" placeholder="از تاریخ" value="<?php echo h($dateFrom); ?>">
      <input class="form-control" name="date_to" placeholder="تا تاریخ" value="<?php echo h($dateTo); ?>">
      <input class="form-control" name="device" placeholder="Device" value="<?php echo h($device); ?>">
      <input class="form-control" name="country" placeholder="Country" value="<?php echo h($country); ?>">
      <button class="btn btn-primary">Filter</button>
      <a class="btn" href="analytics-export.php?type=visitor_logs">Export</a>
    </form>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>ID</th><th>IP</th><th>Country</th><th>City</th><th>ISP</th><th>Referrer</th><th>Landing</th><th>Browser</th><th>OS</th><th>Device</th><th>Lang</th><th>Duration</th><th>Pages</th><th>Bot</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo h($row['id']); ?></td>
              <td><?php echo h($row['ip_address']); ?></td>
              <td><?php echo h($row['country']); ?></td>
              <td><?php echo h($row['city']); ?></td>
              <td><?php echo h($row['isp']); ?></td>
              <td><?php echo h($row['referrer']); ?></td>
              <td><?php echo h($row['landing_page']); ?></td>
              <td><?php echo h($row['browser']); ?></td>
              <td><?php echo h($row['os']); ?></td>
              <td><?php echo h($row['device']); ?></td>
              <td><?php echo h($row['language']); ?></td>
              <td><?php echo h($row['visit_duration']); ?></td>
              <td><?php echo h($row['pages_viewed']); ?></td>
              <td><?php echo ((int)$row['is_bot'] === 1) ? '✓' : '-'; ?></td>
              <td><?php echo h($row['created_at']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p>Page <?php echo h($page); ?> / <?php echo h($pages); ?> - Total <?php echo h($total); ?></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
