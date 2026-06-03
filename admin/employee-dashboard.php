<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('employee');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'داشبورد کارمند';
$message = '';
$error = '';
$employeeId = (int)$currentAdmin['id'];
$period = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['period_month'] ?? '')) ? (string)$_GET['period_month'] : date('Y-m');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_action'] ?? '') === 'change_password') {
        requireValidCsrf();
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if (strlen($newPassword) < 8) {
            throw new RuntimeException('رمز جدید باید حداقل ۸ کاراکتر باشد.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('تکرار رمز عبور با رمز جدید برابر نیست.');
        }
        $stmt = $db->prepare('SELECT password FROM admins WHERE id = ? AND is_active = 1');
        $stmt->execute([$employeeId]);
        $hash = (string)$stmt->fetchColumn();
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            throw new RuntimeException('رمز فعلی صحیح نیست.');
        }
        $db->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $employeeId]);
        $message = 'رمز عبور با موفقیت تغییر کرد.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
function employeeCalculateScore(PDO $db, int $employeeId, string $period): array {
    $stmt = $db->prepare('SELECT COALESCE(AVG(NULLIF(manager_score,0)),0) AS manager_score, COALESCE(AVG(peer_score),0) AS peer_score FROM employee_evaluations WHERE employee_id = ? AND period_month = ?');
    $stmt->execute([$employeeId, $period]);
    $eval = $stmt->fetch() ?: ['manager_score'=>0, 'peer_score'=>0];
    $inputStmt = $db->prepare('SELECT manager_score, attendance_score, department_kpi_score FROM employee_monthly_inputs WHERE employee_id = ? AND period_month = ?');
    $inputStmt->execute([$employeeId, $period]);
    $input = $inputStmt->fetch() ?: ['manager_score'=>0, 'attendance_score'=>0, 'department_kpi_score'=>0];
    $manager = max((float)$eval['manager_score'], (float)$input['manager_score']);
    $peer = (float)$eval['peer_score'];
    $attendance = (float)$input['attendance_score'];
    $kpi = (float)$input['department_kpi_score'];
    $final = round(($manager * 0.35) + ($peer * 0.25) + ($attendance * 0.20) + ($kpi * 0.20), 2);
    $stmt = $db->prepare('INSERT INTO employee_score_history (employee_id, period_month, manager_score, peer_score, attendance_score, department_kpi_score, final_score) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE manager_score=VALUES(manager_score), peer_score=VALUES(peer_score), attendance_score=VALUES(attendance_score), department_kpi_score=VALUES(department_kpi_score), final_score=VALUES(final_score), calculated_at=NOW()');
    $stmt->execute([$employeeId, $period, $manager, $peer, $attendance, $kpi, $final]);
    $stmt = $db->prepare('INSERT INTO employee_performance (admin_id, period_month, score, evaluated_by) VALUES (?, ?, ?, NULL) ON DUPLICATE KEY UPDATE score=VALUES(score), updated_at=NOW()');
    $stmt->execute([$employeeId, $period, $final]);
    return ['manager'=>$manager, 'peer'=>$peer, 'attendance'=>$attendance, 'kpi'=>$kpi, 'final'=>$final];
}
$score = employeeCalculateScore($db, $employeeId, $period);
$historyStmt = $db->prepare('SELECT * FROM employee_score_history WHERE employee_id=? ORDER BY period_month DESC LIMIT 12');
$historyStmt->execute([$employeeId]);
$history = $historyStmt->fetchAll();
$rewardStmt = $db->prepare('SELECT * FROM employee_rewards WHERE employee_id=? ORDER BY reward_date DESC LIMIT 20');
$rewardStmt->execute([$employeeId]);
$rewards = $rewardStmt->fetchAll();
$warningStmt = $db->prepare('SELECT * FROM employee_warnings WHERE employee_id=? ORDER BY warning_date DESC LIMIT 20');
$warningStmt->execute([$employeeId]);
$warnings = $warningStmt->fetchAll();
$rankingStmt = $db->prepare('SELECT a.id, a.full_name, a.username, a.department, h.final_score FROM employee_score_history h JOIN admins a ON a.id=h.employee_id WHERE h.period_month=? ORDER BY h.final_score DESC');
$rankingStmt->execute([$period]);
$ranking = $rankingStmt->fetchAll();
$rankNo = 0;
$previousScore = null;
$displayRank = 0;
$myRank = null;
foreach ($ranking as $idx => &$row) {
    $rankNo++;
    if ($previousScore === null || (float)$row['final_score'] !== (float)$previousScore) {
        $displayRank = $rankNo;
        $previousScore = $row['final_score'];
    }
    $row['rank_no'] = $displayRank;
    if ((int)$row['id'] === $employeeId) { $myRank = $displayRank; }
}
unset($row);
include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row"><div class="stat-card stat-primary"><div class="stat-icon">⭐</div><div class="stat-content"><h3><?php echo h($score['final']); ?></h3><p>امتیاز نهایی ماه <?php echo h($period); ?></p></div></div><div class="stat-card stat-success"><div class="stat-icon">🏆</div><div class="stat-content"><h3><?php echo h($myRank ?? '-'); ?></h3><p>رتبه شما</p></div></div><div class="stat-card stat-info"><div class="stat-icon">👤</div><div class="stat-content"><h3><?php echo h($currentAdmin['role']); ?></h3><p><?php echo h($currentAdmin['department'] ?? ''); ?></p></div></div></div>
<div class="card"><div class="card-header"><h2>پروفایل من</h2><a class="btn btn-primary" href="employee-evaluations.php">ارزیابی همکاران</a></div><div class="card-body"><p><strong>نام:</strong> <?php echo h($currentAdmin['full_name'] ?: $currentAdmin['username']); ?></p><p><strong>نام کاربری:</strong> <?php echo h($currentAdmin['username']); ?></p><p><strong>ایمیل:</strong> <?php echo h($currentAdmin['email']); ?></p><p><strong>نقش:</strong> <?php echo h($currentAdmin['role']); ?></p><p><strong>دپارتمان:</strong> <?php echo h($currentAdmin['department'] ?? ''); ?></p></div></div>
<div class="card"><div class="card-header"><h2>تغییر رمز عبور</h2></div><div class="card-body"><form method="post" class="admin-filter"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="profile_action" value="change_password"><input class="form-control" type="password" name="current_password" placeholder="رمز فعلی" required><input class="form-control" type="password" name="new_password" minlength="8" placeholder="رمز جدید" required><input class="form-control" type="password" name="confirm_password" minlength="8" placeholder="تکرار رمز جدید" required><button class="btn btn-success">ذخیره رمز</button></form></div></div>
<div class="card"><div class="card-header"><h2>ترکیب امتیاز ماهانه</h2></div><div class="card-body"><table class="table"><tr><th>Manager</th><th>Peer</th><th>Attendance</th><th>Department KPI</th><th>Final</th></tr><tr><td><?php echo h($score['manager']); ?></td><td><?php echo h($score['peer']); ?></td><td><?php echo h($score['attendance']); ?></td><td><?php echo h($score['kpi']); ?></td><td><?php echo h($score['final']); ?></td></tr></table></div></div>
<div class="card"><div class="card-header"><h2>تاریخچه امتیاز</h2></div><div class="card-body"><table class="table"><thead><tr><th>ماه</th><th>Manager</th><th>Peer</th><th>Attendance</th><th>KPI</th><th>Final</th></tr></thead><tbody><?php foreach($history as $row): ?><tr><td><?php echo h($row['period_month']); ?></td><td><?php echo h($row['manager_score']); ?></td><td><?php echo h($row['peer_score']); ?></td><td><?php echo h($row['attendance_score']); ?></td><td><?php echo h($row['department_kpi_score']); ?></td><td><?php echo h($row['final_score']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-header"><h2>پاداش‌ها</h2></div><div class="card-body"><table class="table"><thead><tr><th>تاریخ</th><th>عنوان</th><th>توضیح</th></tr></thead><tbody><?php foreach($rewards as $row): ?><tr><td><?php echo h($row['reward_date']); ?></td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['description']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-header"><h2>اخطارها</h2></div><div class="card-body"><table class="table"><thead><tr><th>تاریخ</th><th>عنوان</th><th>شدت</th><th>توضیح</th></tr></thead><tbody><?php foreach($warnings as $row): ?><tr><td><?php echo h($row['warning_date']); ?></td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['severity']); ?></td><td><?php echo h($row['description']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><div class="card-header"><h2>رتبه‌بندی ماه <?php echo h($period); ?></h2></div><div class="card-body"><table class="table"><thead><tr><th>رتبه</th><th>کارمند</th><th>دپارتمان</th><th>امتیاز</th></tr></thead><tbody><?php foreach($ranking as $row): ?><tr><td><?php echo h($row['rank_no']); ?></td><td><?php echo h($row['full_name'] ?: $row['username']); ?></td><td><?php echo h($row['department']); ?></td><td><?php echo h($row['final_score']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
