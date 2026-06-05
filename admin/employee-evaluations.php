<?php
require_once __DIR__ . '/lib/admin_crud.php';
$currentAdmin = adminGuard('employee');
$pageTitle = 'پرتال ارزیابی همکاران';
try {
    ensureAdminSchema();
    $db = adminDb();
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'Employee evaluations bootstrap failed: ' . $e->getMessage());
    return;
}
if (!adminTableExists('admins') || !adminTableExists('employee_evaluations')) {
    try {
        ensureAdminCanonicalTables($db, ['admins', 'employee_evaluations']);
    } catch (Throwable $e) {
        safeAdminLog('Targeted employee evaluations table repair failed: ' . $e->getMessage());
    }
}
if (!adminTableExists('admins') || !adminTableExists('employee_evaluations')) {
    adminRenderSafeError($pageTitle, 'Employee evaluations required table is missing.');
    return;
}
$error = '';
$message = '';
$period = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['period_month'] ?? $_GET['period_month'] ?? '')) ? (string)($_POST['period_month'] ?? $_GET['period_month']) : date('Y-m');

$categoryGroups = [
    'common' => [
        'discipline' => 'انضباط',
        'teamwork' => 'کار تیمی',
        'responsibility' => 'مسئولیت‌پذیری',
        'communication' => 'ارتباطات',
        'honesty' => 'صداقت',
        'key_culture' => 'فرهنگ KEY',
    ],
    'restaurant' => [
        'service_speed' => 'سرعت سرویس',
        'hygiene' => 'بهداشت',
        'order_accuracy' => 'دقت سفارش',
    ],
    'technology' => [
        'uptime' => 'Uptime',
        'maintenance' => 'نگهداری',
        'documentation' => 'مستندسازی',
        'security' => 'امنیت',
    ],
    'marketing' => [
        'content_calendar' => 'تقویم محتوا',
        'reporting' => 'گزارش‌دهی',
        'content_quality' => 'کیفیت محتوا',
        'engagement' => 'تعامل',
        'transparency' => 'شفافیت',
    ],
];
$allCategories = [];
foreach ($categoryGroups as $group) {
    $allCategories += $group;
}
$canEvaluate = adminPermissionAllows($currentAdmin, 'employee_evaluations', ['employee','manager','admin','super_admin']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        if (!$canEvaluate) {
            throw new RuntimeException('مجوز ارزیابی همکاران برای حساب شما فعال نیست.');
        }
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if ($employeeId === (int)$currentAdmin['id']) {
            throw new RuntimeException('کارمند نمی‌تواند خودش را ارزیابی کند.');
        }
        $stmt = $db->prepare("SELECT id, role FROM admins WHERE id = ? AND role IN ('employee','manager','admin') AND is_active = 1");
        $stmt->execute([$employeeId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('کارمند انتخاب‌شده معتبر نیست.');
        }
        $group = isset($categoryGroups[$_POST['category_group'] ?? 'common']) ? (string)$_POST['category_group'] : 'common';
        $scoreKeys = array_keys($categoryGroups['common'] + $categoryGroups[$group]);
        $scores = [];
        foreach ($scoreKeys as $key) {
            $scores[$key] = max(0, min(100, (float)($_POST['score'][$key] ?? 0)));
        }
        $peerScore = count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
        $managerScore = in_array($currentAdmin['role'], ['manager','admin','super_admin'], true) ? $peerScore : 0;
        $payload = json_encode($scores, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO employee_evaluations (evaluator_id, employee_id, period_month, category_group, scores, peer_score, manager_score, notes, is_private) VALUES (:evaluator_id, :employee_id, :period_month, :category_group, :scores, :peer_score, :manager_score, :notes, 1) ON DUPLICATE KEY UPDATE category_group=:category_group2, scores=:scores2, peer_score=:peer_score2, manager_score=:manager_score2, notes=:notes2, updated_at=NOW()');
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        $stmt->execute([
            'evaluator_id' => $currentAdmin['id'],
            'employee_id' => $employeeId,
            'period_month' => $period,
            'category_group' => $group,
            'scores' => $payload,
            'peer_score' => $peerScore,
            'manager_score' => $managerScore,
            'notes' => $notes,
            'category_group2' => $group,
            'scores2' => $payload,
            'peer_score2' => $peerScore,
            'manager_score2' => $managerScore,
            'notes2' => $notes,
        ]);
        $message = 'ارزیابی به‌صورت خصوصی ذخیره شد.';
    }
} catch (Throwable $e) {
    $error = 'عملیات انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('Employee evaluation save failed: ' . $e->getMessage());
}

$employees = [];
$mine = [];
try {
$employeesStmt = $db->prepare("SELECT id, username, full_name, role, department FROM admins WHERE is_active=1 AND role IN ('employee','manager','admin') AND id <> ? ORDER BY department, full_name, username");
$employeesStmt->execute([$currentAdmin['id']]);
$employees = $employeesStmt->fetchAll();
$myStmt = $db->prepare('SELECT ev.*, a.full_name, a.username FROM employee_evaluations ev JOIN admins a ON a.id = ev.employee_id WHERE ev.evaluator_id = ? ORDER BY ev.updated_at DESC LIMIT 20');
$myStmt->execute([$currentAdmin['id']]);
$mine = $myStmt->fetchAll();
} catch (Throwable $e) {
    $error = 'داده‌های ارزیابی در حال حاضر قابل نمایش نیستند. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('Employee evaluation list failed: ' . $e->getMessage());
}
include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<?php if (!$canEvaluate): ?><div class="alert" style="background:#fff3cd;color:#856404">مجوز ارزیابی همکاران برای شما فعال نیست. با مدیر سیستم تماس بگیرید.</div><?php endif; ?>
<div class="card"><div class="card-header"><h2>ثبت ارزیابی خصوصی</h2></div><div class="card-body"><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><div class="admin-filter"><input class="form-control" name="period_month" value="<?php echo h($period); ?>" pattern="\d{4}-\d{2}" required><select class="form-control" name="employee_id" required><option value="">انتخاب همکار</option><?php foreach($employees as $emp): ?><option value="<?php echo h($emp['id']); ?>"><?php echo h(($emp['full_name'] ?: $emp['username']) . ' - ' . $emp['department'] . ' - ' . $emp['role']); ?></option><?php endforeach; ?></select><select class="form-control" name="category_group"><?php foreach($categoryGroups as $groupKey => $labels): ?><option value="<?php echo h($groupKey); ?>"><?php echo h(ucfirst($groupKey)); ?></option><?php endforeach; ?></select></div><div class="admin-filter"><?php foreach($allCategories as $key=>$label): ?><label><?php echo h($label); ?><input class="form-control" type="number" name="score[<?php echo h($key); ?>]" min="0" max="100" value="80"></label><?php endforeach; ?></div><div class="form-group"><label>یادداشت خصوصی</label><textarea class="form-control" name="notes"></textarea></div><button class="btn btn-success" <?php echo $canEvaluate ? '' : 'disabled'; ?>>ذخیره ارزیابی</button></form></div></div>
<div class="card"><div class="card-header"><h2>ارزیابی‌های ثبت‌شده توسط شما</h2></div><div class="card-body"><table class="table"><thead><tr><th>کارمند</th><th>ماه</th><th>گروه</th><th>Peer Score</th><th>Manager Score</th><th>خصوصی</th><th>ویرایش</th></tr></thead><tbody><?php foreach($mine as $row): ?><tr><td><?php echo h($row['full_name'] ?: $row['username']); ?></td><td><?php echo h($row['period_month']); ?></td><td><?php echo h($row['category_group']); ?></td><td><?php echo h($row['peer_score']); ?></td><td><?php echo h($row['manager_score']); ?></td><td><?php echo ((int)$row['is_private'] === 1) ? '✅' : '❌'; ?></td><td><?php echo h($row['updated_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
