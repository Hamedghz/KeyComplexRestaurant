<?php
require_once __DIR__ . '/lib/hr_evaluation_service.php';
$currentAdmin = adminGuard('employee');
$pageTitle = 'داشبورد کارمند';
$message = '';
$error = '';
$employeeId = (int)$currentAdmin['id'];

try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'Employee dashboard bootstrap failed: ' . $e->getMessage());
    return;
}

$periods = hrFetchPeriods($db, false);
$periodId = (int)($_GET['period_id'] ?? ($periods[0]['id'] ?? 0));
$selectedPeriod = $periodId ? hrFindPeriod($db, $periodId) : null;
$periodMonth = $selectedPeriod ? hrPeriodScoreKey($selectedPeriod) : date('Y-m');

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
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    safeAdminLog('Employee password change failed: ' . $e->getMessage());
    $error = 'ذخیره انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
}

$score = ['manager_score' => 0, 'peer_score' => 0, 'attendance_score' => 0, 'department_kpi_score' => 0, 'final_score' => 0, 'category_breakdown' => []];
if ($selectedPeriod) {
    try {
        $score = hrRecalculateEmployeeScore($db, $employeeId, $periodId);
    } catch (Throwable $e) {
        safeAdminLog('Employee dashboard score calculation failed: ' . $e->getMessage());
        $error = $error ?: 'محاسبه امتیاز در حال حاضر انجام نشد.';
    }
}

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
$rankingStmt->execute([$periodMonth]);
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
    if ((int)$row['id'] === $employeeId) {
        $myRank = $displayRank;
    }
}
unset($row);

include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="stats-row">
    <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo h($score['final_score']); ?></h3><p>امتیاز نهایی <?php echo h($periodMonth); ?></p></div></div>
    <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h($myRank ?? '-'); ?></h3><p>رتبه شما</p></div></div>
    <div class="stat-card stat-info"><div class="stat-content"><h3><?php echo h($currentAdmin['role']); ?></h3><p><?php echo h($currentAdmin['department'] ?? ''); ?></p></div></div>
</div>

<div class="card">
    <div class="card-header"><h2>فیلتر دوره</h2><a class="btn btn-primary" href="employee-evaluations.php">ارزیابی</a></div>
    <div class="card-body">
        <form method="get" class="admin-filter">
            <select class="form-control" name="period_id"><?php foreach ($periods as $period): ?><option value="<?php echo h($period['id']); ?>" <?php echo (int)$period['id'] === $periodId ? 'selected' : ''; ?>><?php echo h($period['title'] . ' - ' . ($period['period_key'] ?: $period['status'])); ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary">نمایش</button>
        </form>
        <?php if (!$periods): ?><p class="text-center text-muted">هنوز دوره ارزیابی تعریف نشده است.</p><?php endif; ?>
    </div>
</div>

<div class="card"><div class="card-header"><h2>پروفایل من</h2></div><div class="card-body"><p><strong>نام:</strong> <?php echo h($currentAdmin['full_name'] ?: $currentAdmin['username']); ?></p><p><strong>نام کاربری:</strong> <?php echo h($currentAdmin['username']); ?></p><p><strong>ایمیل:</strong> <?php echo h($currentAdmin['email']); ?></p><p><strong>نقش:</strong> <?php echo h($currentAdmin['role']); ?></p><p><strong>دپارتمان:</strong> <?php echo h($currentAdmin['department'] ?? ''); ?></p></div></div>

<div class="card"><div class="card-header"><h2>تغییر رمز عبور</h2></div><div class="card-body"><form method="post" class="admin-filter"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="profile_action" value="change_password"><input class="form-control" type="password" name="current_password" placeholder="رمز فعلی" required><input class="form-control" type="password" name="new_password" minlength="8" placeholder="رمز جدید" required><input class="form-control" type="password" name="confirm_password" minlength="8" placeholder="تکرار رمز جدید" required><button class="btn btn-success">ذخیره رمز</button></form></div></div>

<div class="card">
    <div class="card-header"><h2>ترکیب امتیاز دوره</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><tr><th>مدیر</th><th>همکاران</th><th>حضور</th><th>KPI دپارتمان</th><th>نهایی</th></tr><tr><td><?php echo h($score['manager_score']); ?></td><td><?php echo h($score['peer_score']); ?></td><td><?php echo h($score['attendance_score']); ?></td><td><?php echo h($score['department_kpi_score']); ?></td><td><?php echo h($score['final_score']); ?></td></tr></table>
        <?php if (!empty($score['category_breakdown'])): ?>
            <p class="text-muted">شکست دسته ها:</p>
            <?php foreach ($score['category_breakdown'] as $category): ?><span class="badge badge-info"><?php echo h(($category['title'] ?? '-') . ': ' . ($category['score'] ?? 0)); ?></span> <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card"><div class="card-header"><h2>تاریخچه امتیاز</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>ماه</th><th>مدیر</th><th>همکاران</th><th>حضور</th><th>KPI</th><th>نهایی</th></tr></thead><tbody><?php foreach($history as $row): ?><tr><td><?php echo h($row['period_month']); ?></td><td><?php echo h($row['manager_score']); ?></td><td><?php echo h($row['peer_score']); ?></td><td><?php echo h($row['attendance_score']); ?></td><td><?php echo h($row['department_kpi_score']); ?></td><td><?php echo h($row['final_score']); ?></td></tr><?php endforeach; ?><?php if (!$history): ?><tr><td colspan="6" class="text-center text-muted">تاریخچه ای وجود ندارد.</td></tr><?php endif; ?></tbody></table></div></div>

<div class="card"><div class="card-header"><h2>پاداش ها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>تاریخ</th><th>عنوان</th><th>توضیح</th></tr></thead><tbody><?php foreach($rewards as $row): ?><tr><td><?php echo h($row['reward_date']); ?></td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['description']); ?></td></tr><?php endforeach; ?><?php if (!$rewards): ?><tr><td colspan="3" class="text-center text-muted">پاداشی ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div></div>

<div class="card"><div class="card-header"><h2>اخطارها</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>تاریخ</th><th>عنوان</th><th>شدت</th><th>توضیح</th></tr></thead><tbody><?php foreach($warnings as $row): ?><tr><td><?php echo h($row['warning_date']); ?></td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['severity']); ?></td><td><?php echo h($row['description']); ?></td></tr><?php endforeach; ?><?php if (!$warnings): ?><tr><td colspan="4" class="text-center text-muted">اخطاری ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div></div>

<div class="card"><div class="card-header"><h2>رتبه بندی ماه <?php echo h($periodMonth); ?></h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>رتبه</th><th>کارمند</th><th>دپارتمان</th><th>امتیاز</th></tr></thead><tbody><?php foreach($ranking as $row): ?><tr><td><?php echo h($row['rank_no']); ?></td><td><?php echo h($row['full_name'] ?: $row['username']); ?></td><td><?php echo h($row['department']); ?></td><td><?php echo h($row['final_score']); ?></td></tr><?php endforeach; ?><?php if (!$ranking): ?><tr><td colspan="4" class="text-center text-muted">رتبه بندی هنوز محاسبه نشده است.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
