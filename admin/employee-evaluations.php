<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('employee');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'پرتال ارزیابی همکاران';
$error = '';
$message = '';
$period = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['period_month'] ?? $_GET['period_month'] ?? '')) ? (string)($_POST['period_month'] ?? $_GET['period_month']) : date('Y-m');
$categories = [
    'discipline' => 'انضباط', 'teamwork' => 'کار تیمی', 'responsibility' => 'مسئولیت‌پذیری', 'honesty' => 'صداقت', 'communication' => 'ارتباطات', 'key_culture' => 'فرهنگ KEY',
    'content_calendar' => 'اجرای تقویم محتوا', 'content_quality' => 'کیفیت محتوا', 'weekly_reporting' => 'گزارش هفتگی', 'monthly_reporting' => 'گزارش ماهانه', 'engagement_results' => 'نتایج تعامل', 'transparency' => 'شفافیت',
    'uptime' => 'Uptime', 'maintenance' => 'نگهداری', 'issue_resolution' => 'حل مسئله', 'documentation' => 'مستندسازی', 'security' => 'امنیت',
];
$departmentHints = ['marketing'=>['content_calendar','content_quality','weekly_reporting','monthly_reporting','engagement_results','transparency'], 'technology'=>['uptime','maintenance','issue_resolution','documentation','security']];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if ($employeeId === (int)$currentAdmin['id']) throw new RuntimeException('کارمند نمی‌تواند خودش را ارزیابی کند.');
        $stmt = $db->prepare("SELECT id, role FROM admins WHERE id = ? AND role IN ('employee','manager') AND is_active = 1");
        $stmt->execute([$employeeId]);
        if (!$stmt->fetch()) throw new RuntimeException('کارمند انتخاب‌شده معتبر نیست.');
        $scores = [];
        foreach ($categories as $key => $label) {
            $scores[$key] = max(0, min(100, (float)($_POST['score'][$key] ?? 0)));
        }
        $peerScore = count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
        $managerScore = in_array($currentAdmin['role'], ['manager','admin','super_admin'], true) ? $peerScore : 0;
        $payload = json_encode($scores, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO employee_evaluations (evaluator_id, employee_id, period_month, category_group, scores, peer_score, manager_score, notes, is_private) VALUES (:evaluator_id, :employee_id, :period_month, :category_group, :scores, :peer_score, :manager_score, :notes, 1) ON DUPLICATE KEY UPDATE category_group=:category_group2, scores=:scores2, peer_score=:peer_score2, manager_score=:manager_score2, notes=:notes2, updated_at=NOW()');
        $stmt->execute(['evaluator_id'=>$currentAdmin['id'], 'employee_id'=>$employeeId, 'period_month'=>$period, 'category_group'=>$_POST['category_group'] ?? 'common', 'scores'=>$payload, 'peer_score'=>$peerScore, 'manager_score'=>$managerScore, 'notes'=>trim((string)($_POST['notes'] ?? '')), 'category_group2'=>$_POST['category_group'] ?? 'common', 'scores2'=>$payload, 'peer_score2'=>$peerScore, 'manager_score2'=>$managerScore, 'notes2'=>trim((string)($_POST['notes'] ?? ''))]);
        $message = 'ارزیابی به‌صورت خصوصی ذخیره شد.';
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
$employeesStmt = $db->prepare("SELECT id, username, full_name, role, department FROM admins WHERE is_active=1 AND role IN ('employee','manager') AND id <> ? ORDER BY department, full_name, username");
$employeesStmt->execute([$currentAdmin['id']]);
$employees = $employeesStmt->fetchAll();
$myStmt = $db->prepare('SELECT ev.*, a.full_name, a.username FROM employee_evaluations ev JOIN admins a ON a.id = ev.employee_id WHERE ev.evaluator_id = ? ORDER BY ev.updated_at DESC LIMIT 20');
$myStmt->execute([$currentAdmin['id']]);
$mine = $myStmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2>ثبت ارزیابی خصوصی</h2></div><div class="card-body"><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><div class="admin-filter"><input class="form-control" name="period_month" value="<?php echo h($period); ?>" pattern="\d{4}-\d{2}" required><select class="form-control" name="employee_id" required><option value="">انتخاب همکار</option><?php foreach($employees as $emp): ?><option value="<?php echo h($emp['id']); ?>"><?php echo h(($emp['full_name'] ?: $emp['username']) . ' - ' . $emp['department'] . ' - ' . $emp['role']); ?></option><?php endforeach; ?></select><select class="form-control" name="category_group"><option value="common">Common</option><option value="restaurant">Restaurant</option><option value="marketing">Social Media / Marketing</option><option value="technology">Technology</option><option value="office">Office</option></select></div><div class="admin-filter"><?php foreach($categories as $key=>$label): ?><label><?php echo h($label); ?><input class="form-control" type="number" name="score[<?php echo h($key); ?>]" min="0" max="100" value="80"></label><?php endforeach; ?></div><div class="form-group"><label>یادداشت خصوصی</label><textarea class="form-control" name="notes"></textarea></div><button class="btn btn-success">ذخیره ارزیابی</button></form></div></div>
<div class="card"><div class="card-header"><h2>ارزیابی‌های ثبت‌شده توسط شما</h2></div><div class="card-body"><table class="table"><thead><tr><th>کارمند</th><th>ماه</th><th>امتیاز Peer</th><th>آخرین بروزرسانی</th></tr></thead><tbody><?php foreach($mine as $row): ?><tr><td><?php echo h($row['full_name'] ?: $row['username']); ?></td><td><?php echo h($row['period_month']); ?></td><td><?php echo h($row['peer_score']); ?></td><td><?php echo h($row['updated_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
