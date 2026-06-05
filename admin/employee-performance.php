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
        $employeeId = (int)$_POST['admin_id'];
        $periodMonth = preg_match('/^\d{4}-\d{2}$/', (string)$_POST['period_month']) ? (string)$_POST['period_month'] : date('Y-m');
        $managerScore = max(0, min(100, (float)($_POST['manager_score'] ?? $_POST['score'] ?? 0)));
        $attendanceScore = max(0, min(100, (float)($_POST['attendance_score'] ?? 0)));
        $kpiScore = max(0, min(100, (float)($_POST['department_kpi_score'] ?? 0)));
        $peerStmt = $db->prepare('SELECT COALESCE(AVG(peer_score),0) FROM employee_evaluations WHERE employee_id=? AND period_month=?');
        $peerStmt->execute([$employeeId, $periodMonth]);
        $peerScore = (float)$peerStmt->fetchColumn();
        $finalScore = round(($managerScore * 0.35) + ($peerScore * 0.25) + ($attendanceScore * 0.20) + ($kpiScore * 0.20), 2);
        $db->prepare('INSERT INTO employee_monthly_inputs (employee_id,period_month,manager_score,attendance_score,department_kpi_score,notes,created_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE manager_score=VALUES(manager_score), attendance_score=VALUES(attendance_score), department_kpi_score=VALUES(department_kpi_score), notes=VALUES(notes), created_by=VALUES(created_by), updated_at=NOW()')->execute([$employeeId,$periodMonth,$managerScore,$attendanceScore,$kpiScore,trim((string)$_POST['evaluation_notes']),$currentAdmin['id']]);
        $db->prepare('INSERT INTO employee_score_history (employee_id,period_month,manager_score,peer_score,attendance_score,department_kpi_score,final_score) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE manager_score=VALUES(manager_score), peer_score=VALUES(peer_score), attendance_score=VALUES(attendance_score), department_kpi_score=VALUES(department_kpi_score), final_score=VALUES(final_score), calculated_at=NOW()')->execute([$employeeId,$periodMonth,$managerScore,$peerScore,$attendanceScore,$kpiScore,$finalScore]);
        $db->prepare('INSERT INTO employee_performance (admin_id,period_month,score,reward,penalty,evaluation_notes,evaluated_by) VALUES (:admin_id,:period_month,:score,:reward,:penalty,:evaluation_notes,:evaluated_by) ON DUPLICATE KEY UPDATE score=:update_score,reward=:update_reward,penalty=:update_penalty,evaluation_notes=:update_evaluation_notes,evaluated_by=:update_evaluated_by')
            ->execute(['admin_id'=>$employeeId, 'period_month'=>$periodMonth, 'score'=>$finalScore, 'reward'=>trim((string)$_POST['reward']), 'penalty'=>trim((string)$_POST['penalty']), 'evaluation_notes'=>trim((string)$_POST['evaluation_notes']), 'evaluated_by'=>$currentAdmin['id'], 'update_score'=>$finalScore, 'update_reward'=>trim((string)$_POST['reward']), 'update_penalty'=>trim((string)$_POST['penalty']), 'update_evaluation_notes'=>trim((string)$_POST['evaluation_notes']), 'update_evaluated_by'=>$currentAdmin['id']]);
        if (trim((string)$_POST['reward']) !== '') { $db->prepare('INSERT INTO employee_rewards (employee_id,title,description,reward_date,created_by) VALUES (?,?,?,?,?)')->execute([$employeeId, trim((string)$_POST['reward']), trim((string)$_POST['evaluation_notes']), date('Y-m-d'), $currentAdmin['id']]); }
        if (trim((string)$_POST['penalty']) !== '') { $db->prepare('INSERT INTO employee_warnings (employee_id,title,description,warning_date,severity,created_by) VALUES (?,?,?,?,?,?)')->execute([$employeeId, trim((string)$_POST['penalty']), trim((string)$_POST['evaluation_notes']), date('Y-m-d'), 'medium', $currentAdmin['id']]); }
        redirectTo('employee-performance.php?saved=1&period_month=' . urlencode((string)$_POST['period_month']));
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
$period = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['period_month'] ?? '')) ? $_GET['period_month'] : date('Y-m');
$employees = $db->query("SELECT id, full_name, username, role, department FROM admins WHERE is_active=1 AND role IN ('employee','manager','admin') ORDER BY full_name, username")->fetchAll();
$stmt=$db->prepare("SELECT ep.*, a.full_name, a.username, a.department, a.role FROM employee_performance ep JOIN admins a ON a.id=ep.admin_id WHERE ep.period_month=? ORDER BY ep.score DESC, a.full_name ASC"); $stmt->execute([$period]); $scores=$stmt->fetchAll();
if (adminTableExists('matches') && adminTableExists('predictions') && adminColumnExists('matches', 'match_finished') && adminColumnExists('matches', 'final_score_team_a') && adminColumnExists('matches', 'final_score_team_b') && adminColumnExists('predictions', 'is_correct_prediction')) {
    try {
        $db->exec("UPDATE predictions p JOIN matches m ON m.id = p.match_id SET p.is_correct_prediction = CASE WHEN m.match_finished = 1 AND m.final_score_team_a IS NOT NULL AND m.final_score_team_b IS NOT NULL AND p.predicted_score_team_a = m.final_score_team_a AND p.predicted_score_team_b = m.final_score_team_b THEN 1 ELSE 0 END");
    } catch (Throwable $e) {
        error_log('Prediction recalculation failed in employee-performance.php: ' . $e->getMessage());
    }
}
$trendStmt=$db->query("SELECT a.full_name, a.username, ep.period_month, ep.score FROM employee_performance ep JOIN admins a ON a.id=ep.admin_id ORDER BY ep.period_month DESC, ep.score DESC LIMIT 80"); $trends=$trendStmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row"><div class="stat-card stat-primary"><div class="stat-icon">🏅</div><div class="stat-content"><h3><?php echo h(count($scores)); ?></h3><p>ارزیابی ماهانه</p></div></div><div class="stat-card stat-success"><div class="stat-icon">📈</div><div class="stat-content"><h3><?php echo h($scores ? $scores[0]['score'] : 0); ?></h3><p>بالاترین امتیاز</p></div></div><div class="stat-card stat-warning"><div class="stat-icon">🎁</div><div class="stat-content"><h3><?php echo h(count(array_filter($scores, fn($s)=>$s['reward']))); ?></h3><p>پاداش‌ها</p></div></div></div>
<div class="card"><div class="card-header"><h2>ثبت ارزیابی</h2></div><div class="card-body"><form method="post" class="admin-filter"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><select class="form-control" name="admin_id"><?php foreach($employees as $e): ?><option value="<?php echo h($e['id']); ?>"><?php echo h(($e['full_name'] ?: $e['username']).' — '.$e['role'].' — '.$e['department']); ?></option><?php endforeach; ?></select><input class="form-control" name="period_month" value="<?php echo h($period); ?>" placeholder="YYYY-MM"><input class="form-control" type="number" step="0.01" min="0" max="100" name="manager_score" placeholder="Manager score"><input class="form-control" type="number" step="0.01" min="0" max="100" name="attendance_score" placeholder="Attendance score"><input class="form-control" type="number" step="0.01" min="0" max="100" name="department_kpi_score" placeholder="Department KPI"><input class="form-control" name="reward" placeholder="پاداش"><input class="form-control" name="penalty" placeholder="جریمه"><textarea class="form-control" name="evaluation_notes" placeholder="سوابق/یادداشت ارزیابی"></textarea><button class="btn btn-success">ذخیره</button></form></div></div>
<div class="card"><div class="card-header"><h2>رتبه‌بندی و لیست امتیاز ماهانه</h2></div><div class="card-body"><form class="admin-filter"><input class="form-control" name="period_month" value="<?php echo h($period); ?>"><button class="btn btn-primary">نمایش</button></form><div class="table-responsive"><table class="table"><thead><tr><th>رتبه</th><th>کارمند</th><th>دپارتمان</th><th>امتیاز</th><th>پاداش</th><th>جریمه</th><th>تاریخچه</th><th>عملیات</th></tr></thead><tbody><?php foreach($scores as $i=>$s): ?><tr><td><?php echo $i+1; ?></td><td><?php echo h($s['full_name'] ?: $s['username']); ?></td><td><?php echo h($s['department']); ?></td><td><?php echo h($s['score']); ?></td><td><?php echo h($s['reward']); ?></td><td><?php echo h($s['penalty']); ?></td><td><?php echo h($s['evaluation_notes']); ?></td><td><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($s['id']); ?>"><button class="btn btn-sm btn-danger" name="perf_action" value="delete" onclick="return confirm('حذف شود؟')">حذف</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="card"><div class="card-header"><h2>روند امتیازها</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>ماه</th><th>کارمند</th><th>امتیاز</th></tr></thead><tbody><?php foreach($trends as $t): ?><tr><td><?php echo h($t['period_month']); ?></td><td><?php echo h($t['full_name'] ?: $t['username']); ?></td><td><?php echo h($t['score']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
