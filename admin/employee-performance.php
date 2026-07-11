<?php
require_once __DIR__ . '/lib/admin_schema.php';
redirectTo('hr-performance-summary.php');
return;
require_once __DIR__ . '/lib/hr_evaluation_service.php';
$currentAdmin = adminGuard('manager');
$pageTitle = 'عملکرد کارکنان';
$message = '';
$error = '';

try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'Employee performance bootstrap failed: ' . $e->getMessage());
    return;
}

$canViewPerformance = adminPermissionAllows($currentAdmin, 'employee_performance', ['manager', 'admin', 'super_admin']);
$periods = hrFetchPeriods($db, false);
$periodId = (int)($_POST['period_id'] ?? $_GET['period_id'] ?? ($periods[0]['id'] ?? 0));
$selectedPeriod = $periodId ? hrFindPeriod($db, $periodId) : null;
$periodMonth = $selectedPeriod ? hrPeriodScoreKey($selectedPeriod) : date('Y-m');
$employeeFilterId = (int)($_GET['employee_id'] ?? 0);
$department = trim((string)($_GET['department'] ?? ''));
$role = trim((string)($_GET['role'] ?? ''));
$roles = ['' => 'همه نقش ها', 'employee' => 'Employee', 'manager' => 'Manager', 'admin' => 'Admin'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        if (!$canViewPerformance) {
            throw new RuntimeException('مجوز مشاهده عملکرد برای حساب شما فعال نیست.');
        }
        $action = (string)($_POST['perf_action'] ?? 'save');
        if ($action === 'recalculate_period') {
            if (!$selectedPeriod) {
                throw new RuntimeException('دوره ارزیابی معتبر نیست.');
            }
            $count = hrRecalculatePeriod($db, $periodId);
            redirectTo('employee-performance.php?recalculated=' . urlencode((string)$count) . '&period_id=' . urlencode((string)$periodId));
        }
        throw new RuntimeException('در صفحه View فقط گزارش و بازمحاسبه مجاز است.');
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    safeAdminLog('Employee performance save failed: ' . $e->getMessage());
    $error = 'ذخیره انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
}

if (isset($_GET['recalculated'])) {
    $message = 'بازمحاسبه برای ' . h((string)$_GET['recalculated']) . ' کارمند انجام شد.';
}

$deptRows = $db->query("SELECT DISTINCT department FROM admins WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$employees = $db->query("SELECT id, full_name, username, role, department FROM admins WHERE is_active = 1 AND role IN ('employee','manager','admin') ORDER BY department, full_name, username")->fetchAll();

$scoreWhere = ['h.period_month = ?'];
$scoreParams = [$periodMonth];
if ($periodId) {
    $scoreWhere[] = '(h.period_id = ? OR h.period_id IS NULL)';
    $scoreParams[] = $periodId;
}
if ($department !== '') {
    $scoreWhere[] = 'a.department = ?';
    $scoreParams[] = $department;
}
if ($role !== '' && isset($roles[$role])) {
    $scoreWhere[] = 'a.role = ?';
    $scoreParams[] = $role;
}
if ($employeeFilterId > 0) {
    $scoreWhere[] = 'h.employee_id = ?';
    $scoreParams[] = $employeeFilterId;
}
$scoreStmt = $db->prepare('
    SELECT h.*, ep.reward, ep.penalty, ep.evaluation_notes, a.full_name, a.username, a.department, a.role,
        (SELECT GROUP_CONCAT(er.title ORDER BY er.reward_date DESC SEPARATOR "، ") FROM employee_rewards er WHERE er.employee_id = h.employee_id) AS reward_titles,
        (SELECT GROUP_CONCAT(ew.title ORDER BY ew.warning_date DESC SEPARATOR "، ") FROM employee_warnings ew WHERE ew.employee_id = h.employee_id) AS warning_titles
    FROM employee_score_history h
    JOIN admins a ON a.id = h.employee_id
    LEFT JOIN employee_performance ep ON ep.admin_id = h.employee_id AND ep.period_month = h.period_month
    WHERE ' . implode(' AND ', $scoreWhere) . '
    ORDER BY h.final_score DESC, a.full_name ASC
');
$scoreStmt->execute($scoreParams);
$scores = $scoreStmt->fetchAll();

$detailWhere = ['ev.period_month = ?'];
$detailParams = [$periodMonth];
if ($periodId) {
    $detailWhere[] = '(ev.period_id = ? OR ev.period_id IS NULL)';
    $detailParams[] = $periodId;
}
if ($department !== '') {
    $detailWhere[] = 'target.department = ?';
    $detailParams[] = $department;
}
if ($role !== '' && isset($roles[$role])) {
    $detailWhere[] = 'target.role = ?';
    $detailParams[] = $role;
}
if ($employeeFilterId > 0) {
    $detailWhere[] = 'ev.employee_id = ?';
    $detailParams[] = $employeeFilterId;
}
$detailStmt = $db->prepare('
    SELECT ev.*, target.full_name, target.username, target.department, target.role, evaluator.full_name AS evaluator_full_name, evaluator.username AS evaluator_username, c.title AS category_title, p.title AS period_title
    FROM employee_evaluations ev
    JOIN admins target ON target.id = ev.employee_id
    JOIN admins evaluator ON evaluator.id = ev.evaluator_id
    LEFT JOIN hr_evaluation_categories c ON c.id = ev.category_id
    LEFT JOIN hr_evaluation_periods p ON p.id = ev.period_id
    WHERE ' . implode(' AND ', $detailWhere) . '
    ORDER BY ev.updated_at DESC
    LIMIT 120
');
$detailStmt->execute($detailParams);
$responseDetails = $detailStmt->fetchAll();

$assessmentWhere = ['1=1'];
$assessmentParams = [];
if ($department !== '') {
    $assessmentWhere[] = 'a.department = ?';
    $assessmentParams[] = $department;
}
if ($role !== '' && isset($roles[$role])) {
    $assessmentWhere[] = 'a.role = ?';
    $assessmentParams[] = $role;
}
if ($employeeFilterId > 0) {
    $assessmentWhere[] = 'r.employee_id = ?';
    $assessmentParams[] = $employeeFilterId;
}
if ($selectedPeriod && !empty($selectedPeriod['start_date'])) {
    $assessmentWhere[] = 'r.completion_date >= ?';
    $assessmentParams[] = $selectedPeriod['start_date'];
}
if ($selectedPeriod && !empty($selectedPeriod['end_date'])) {
    $assessmentWhere[] = 'r.completion_date <= ?';
    $assessmentParams[] = $selectedPeriod['end_date'];
}
$assessmentStmt = $db->prepare('
    SELECT r.*, t.title AS test_title, t.test_code, a.full_name, a.username, a.department, a.role
    FROM hr_assessment_results r
    JOIN hr_assessment_tests t ON t.id = r.test_id
    JOIN admins a ON a.id = r.employee_id
    WHERE ' . implode(' AND ', $assessmentWhere) . '
    ORDER BY r.completion_date DESC, r.id DESC
    LIMIT 80
');
$assessmentStmt->execute($assessmentParams);
$assessmentResults = $assessmentStmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=employee-performance-' . $periodMonth . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['rank', 'employee', 'department', 'role', 'manager', 'peer', 'attendance', 'department_kpi', 'final']);
    foreach ($scores as $i => $row) {
        fputcsv($out, [$i + 1, hrEmployeeDisplayName($row), $row['department'], $row['role'], $row['manager_score'], $row['peer_score'], $row['attendance_score'], $row['department_kpi_score'], $row['final_score']]);
    }
    exit;
}

$trendStmt = $db->query('SELECT a.full_name, a.username, h.period_month, h.final_score FROM employee_score_history h JOIN admins a ON a.id=h.employee_id ORDER BY h.period_month DESC, h.final_score DESC LIMIT 80');
$trends = $trendStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<div class="stats-row">
    <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo h(count($scores)); ?></h3><p>رکورد عملکرد</p></div></div>
    <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h($scores ? $scores[0]['final_score'] : 0); ?></h3><p>بالاترین امتیاز</p></div></div>
    <div class="stat-card stat-warning"><div class="stat-content"><h3><?php echo h(count(array_filter($scores, static fn($s) => trim((string)($s['reward'] ?? '') . (string)($s['reward_titles'] ?? '')) !== ''))); ?></h3><p>پاداش ها</p></div></div>
</div>

<div class="card">
    <div class="card-header"><h2>فیلتر گزارش</h2><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>">خروجی CSV</a></div>
    <div class="card-body">
        <form method="get" class="admin-filter">
            <select class="form-control" name="period_id"><?php foreach ($periods as $period): ?><option value="<?php echo h($period['id']); ?>" <?php echo (int)$period['id'] === $periodId ? 'selected' : ''; ?>><?php echo h($period['title'] . ' - ' . ($period['period_key'] ?: $period['status'])); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="employee_id"><option value="">همه کارکنان</option><?php foreach ($employees as $employee): ?><option value="<?php echo h($employee['id']); ?>" <?php echo $employeeFilterId === (int)$employee['id'] ? 'selected' : ''; ?>><?php echo h(hrEmployeeDisplayName($employee)); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="department"><option value="">همه دپارتمان ها</option><?php foreach ($deptRows as $dept): ?><option value="<?php echo h($dept); ?>" <?php echo $department === (string)$dept ? 'selected' : ''; ?>><?php echo h($dept); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="role"><?php foreach ($roles as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $role === (string)$key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary">نمایش</button>
        </form>
        <?php if ($selectedPeriod && adminPermissionAllows($currentAdmin, 'employee_recalculate_scores', ['admin','super_admin'])): ?>
            <form method="post">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                <input type="hidden" name="period_id" value="<?php echo h($periodId); ?>">
                <button class="btn btn-warning" name="perf_action" value="recalculate_period">بازمحاسبه این دوره</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>رتبه بندی و شکست امتیاز</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>رتبه</th><th>کارمند</th><th>دپارتمان</th><th>مدیر</th><th>همکاران</th><th>حضور</th><th>KPI</th><th>نهایی</th><th>دسته ها</th><th>پاداش/اخطار</th></tr></thead><tbody>
        <?php foreach ($scores as $i => $row): $categories = hrJsonDecode($row['category_breakdown'] ?? ''); ?>
            <tr>
                <td><?php echo h($i + 1); ?></td>
                <td><?php echo h(hrEmployeeDisplayName($row)); ?><br><small class="text-muted"><?php echo h($row['role']); ?></small></td>
                <td><?php echo h($row['department']); ?></td>
                <td><?php echo h($row['manager_score']); ?></td>
                <td><?php echo h($row['peer_score']); ?></td>
                <td><?php echo h($row['attendance_score']); ?></td>
                <td><?php echo h($row['department_kpi_score']); ?></td>
                <td><strong><?php echo h($row['final_score']); ?></strong></td>
                <td><?php foreach ($categories as $cat): ?><span class="badge badge-info"><?php echo h(($cat['title'] ?? '-') . ': ' . ($cat['score'] ?? 0)); ?></span> <?php endforeach; ?></td>
                <td><?php echo h(trim((string)($row['reward'] ?? '') . ' ' . (string)($row['reward_titles'] ?? '') . ' ' . (string)($row['penalty'] ?? '') . ' ' . (string)($row['warning_titles'] ?? '')) ?: '-'); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$scores): ?><tr><td colspan="10" class="text-center text-muted">برای این فیلتر هنوز امتیازی محاسبه نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>جزئیات پاسخ ها</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>کارمند</th><th>ارزیاب</th><th>دوره</th><th>فرم</th><th>منبع</th><th>امتیاز</th><th>آخرین ویرایش</th></tr></thead><tbody>
        <?php foreach ($responseDetails as $row): ?>
            <tr>
                <td><?php echo h($row['full_name'] ?: $row['username']); ?><br><small class="text-muted"><?php echo h($row['department']); ?></small></td>
                <td><?php echo h($row['evaluator_full_name'] ?: $row['evaluator_username']); ?></td>
                <td><?php echo h($row['period_title'] ?: $row['period_month']); ?></td>
                <td><?php echo h($row['category_title'] ?: $row['category_group']); ?></td>
                <td><?php echo h($row['source_type']); ?></td>
                <td><?php echo h($row['category_score'] ?: max((float)$row['peer_score'], (float)$row['manager_score'])); ?></td>
                <td><?php echo h($row['updated_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$responseDetails): ?><tr><td colspan="7" class="text-center text-muted">پاسخی برای این فیلتر ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>نتایج آزمون ها و سنجش ها</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>کارمند</th><th>آزمون</th><th>تاریخ</th><th>نتیجه</th><th>خلاصه</th><th>سطح مشاهده</th></tr></thead><tbody>
        <?php foreach ($assessmentResults as $result): ?>
            <tr>
                <td><?php echo h($result['full_name'] ?: $result['username']); ?><br><small class="text-muted"><?php echo h($result['department']); ?></small></td>
                <td><?php echo h($result['test_title']); ?><br><small class="text-muted"><?php echo h($result['test_code']); ?></small></td>
                <td><?php echo h($result['completion_date']); ?></td>
                <td><?php echo h(trim((string)($result['score_value'] ?? '') . ' ' . (string)($result['result_type'] ?? '')) ?: '-'); ?></td>
                <td><?php echo h($result['result_summary']); ?></td>
                <td><?php echo h($result['visibility']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$assessmentResults): ?><tr><td colspan="6" class="text-center text-muted">نتیجه آزمونی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>روند امتیازها</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>ماه</th><th>کارمند</th><th>امتیاز</th></tr></thead><tbody>
        <?php foreach ($trends as $row): ?><tr><td><?php echo h($row['period_month']); ?></td><td><?php echo h($row['full_name'] ?: $row['username']); ?></td><td><?php echo h($row['final_score']); ?></td></tr><?php endforeach; ?>
        <?php if (!$trends): ?><tr><td colspan="3" class="text-center text-muted">تاریخچه ای وجود ندارد.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
