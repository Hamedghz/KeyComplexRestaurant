<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'عملکرد کارکنان';
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        if (($_POST['perf_action'] ?? 'save') === 'delete') {
            $db->prepare('DELETE FROM employee_performance WHERE id=?')->execute([(int)$_POST['id']]);
            redirectTo('employee-performance.php?deleted=1');
        }
        $db->prepare('INSERT INTO employee_performance (admin_id,period_month,score,reward,penalty,evaluation_notes,evaluated_by) VALUES (:admin_id,:period_month,:score,:reward,:penalty,:evaluation_notes,:evaluated_by) ON DUPLICATE KEY UPDATE score=:score,reward=:reward,penalty=:penalty,evaluation_notes=:evaluation_notes,evaluated_by=:evaluated_by')
            ->execute(['admin_id'=>(int)$_POST['admin_id'], 'period_month'=>preg_match('/^\d{4}-\d{2}$/', (string)$_POST['period_month']) ? $_POST['period_month'] : date('Y-m'), 'score'=>(float)$_POST['score'], 'reward'=>trim((string)$_POST['reward']), 'penalty'=>trim((string)$_POST['penalty']), 'evaluation_notes'=>trim((string)$_POST['evaluation_notes']), 'evaluated_by'=>$currentAdmin['id']]);
        redirectTo('employee-performance.php?saved=1&period_month=' . urlencode((string)$_POST['period_month']));
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
$period = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['period_month'] ?? '')) ? $_GET['period_month'] : date('Y-m');
$employees = $db->query("SELECT id, full_name, username, role, department FROM admins WHERE is_active=1 AND role IN ('employee','manager','admin') ORDER BY full_name, username")->fetchAll();
$stmt=$db->prepare("SELECT ep.*, a.full_name, a.username, a.department, a.role FROM employee_performance ep JOIN admins a ON a.id=ep.admin_id WHERE ep.period_month=? ORDER BY ep.score DESC, a.full_name ASC"); $stmt->execute([$period]); $scores=$stmt->fetchAll();
$trendStmt=$db->query("SELECT a.full_name, a.username, ep.period_month, ep.score FROM employee_performance ep JOIN admins a ON a.id=ep.admin_id ORDER BY ep.period_month DESC, ep.score DESC LIMIT 80"); $trends=$trendStmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row"><div class="stat-card stat-primary"><div class="stat-icon">🏅</div><div class="stat-content"><h3><?php echo h(count($scores)); ?></h3><p>ارزیابی ماهانه</p></div></div><div class="stat-card stat-success"><div class="stat-icon">📈</div><div class="stat-content"><h3><?php echo h($scores ? $scores[0]['score'] : 0); ?></h3><p>بالاترین امتیاز</p></div></div><div class="stat-card stat-warning"><div class="stat-icon">🎁</div><div class="stat-content"><h3><?php echo h(count(array_filter($scores, fn($s)=>$s['reward']))); ?></h3><p>پاداش‌ها</p></div></div></div>
<div class="card"><div class="card-header"><h2>ثبت ارزیابی</h2></div><div class="card-body"><form method="post" class="admin-filter"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><select class="form-control" name="admin_id"><?php foreach($employees as $e): ?><option value="<?php echo h($e['id']); ?>"><?php echo h(($e['full_name'] ?: $e['username']).' — '.$e['role'].' — '.$e['department']); ?></option><?php endforeach; ?></select><input class="form-control" name="period_month" value="<?php echo h($period); ?>" placeholder="YYYY-MM"><input class="form-control" type="number" step="0.01" min="0" max="100" name="score" placeholder="امتیاز"><input class="form-control" name="reward" placeholder="پاداش"><input class="form-control" name="penalty" placeholder="جریمه"><textarea class="form-control" name="evaluation_notes" placeholder="سوابق/یادداشت ارزیابی"></textarea><button class="btn btn-success">ذخیره</button></form></div></div>
<div class="card"><div class="card-header"><h2>رتبه‌بندی و لیست امتیاز ماهانه</h2></div><div class="card-body"><form class="admin-filter"><input class="form-control" name="period_month" value="<?php echo h($period); ?>"><button class="btn btn-primary">نمایش</button></form><div class="table-responsive"><table class="table"><thead><tr><th>رتبه</th><th>کارمند</th><th>دپارتمان</th><th>امتیاز</th><th>پاداش</th><th>جریمه</th><th>تاریخچه</th><th>عملیات</th></tr></thead><tbody><?php foreach($scores as $i=>$s): ?><tr><td><?php echo $i+1; ?></td><td><?php echo h($s['full_name'] ?: $s['username']); ?></td><td><?php echo h($s['department']); ?></td><td><?php echo h($s['score']); ?></td><td><?php echo h($s['reward']); ?></td><td><?php echo h($s['penalty']); ?></td><td><?php echo h($s['evaluation_notes']); ?></td><td><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($s['id']); ?>"><button class="btn btn-sm btn-danger" name="perf_action" value="delete" onclick="return confirm('حذف شود؟')">حذف</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="card"><div class="card-header"><h2>روند امتیازها</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>ماه</th><th>کارمند</th><th>امتیاز</th></tr></thead><tbody><?php foreach($trends as $t): ?><tr><td><?php echo h($t['period_month']); ?></td><td><?php echo h($t['full_name'] ?: $t['username']); ?></td><td><?php echo h($t['score']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
