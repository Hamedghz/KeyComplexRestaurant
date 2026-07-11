<?php
require_once __DIR__ . '/lib/hr/bootstrap.php';

$currentAdmin = adminGuard('employee');
$pageTitle = 'ارزیابی، عملکرد و اهداف';
$db = adminDb();

try {
    hrEnsureCoreSchema($db);
} catch (Throwable $e) {
    safeAdminLog('HR dashboard schema bootstrap failed: ' . $e->getMessage());
}

function hrDashboardCount(string $table, string $where = '1=1', array $params = []): int {
    try {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !adminTableExists($table)) {
            return 0;
        }
        $stmt = adminDb()->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        safeAdminLog('HR dashboard count failed for ' . $table . ': ' . $e->getMessage());
        return 0;
    }
}

$today = hrToday();
$periodMonth = hrCurrentMonthKey();
$cards = [
    ['label' => 'آزمون‌های فعال', 'value' => hrDashboardCount('hr_assessment_tests', 'is_active = 1'), 'url' => 'hr-tests-bank.php'],
    ['label' => 'چک‌لیست‌های امروز', 'value' => hrDashboardCount('hr_checklist_assignments', 'status IN ("assigned","in_progress") AND (due_date IS NULL OR due_date = ?)', [$today]), 'url' => 'hr-checklist-submissions.php'],
    ['label' => 'KPIهای دوره جاری', 'value' => hrDashboardCount('hr_kpi_assignments', 'status = "active" AND period_month = ?', [$periodMonth]), 'url' => 'hr-kpi-entries.php'],
    ['label' => 'تسک‌های امروز', 'value' => hrDashboardCount('hr_planner_tasks', 'status IN ("open","in_progress") AND (due_date IS NULL OR due_date = ?)', [$today]), 'url' => 'hr-planner-today.php'],
    ['label' => 'اهداف ماه جاری', 'value' => hrDashboardCount('hr_monthly_objectives', 'status = "active" AND period_month = ?', [$periodMonth]), 'url' => 'hr-okr-objectives.php'],
    ['label' => 'موارد نیازمند تایید مدیر/بازرس/TMO', 'value' => hrDashboardCount('hr_checklist_submissions', 'approval_status = "pending"') + hrDashboardCount('hr_tmo_reviews', 'review_status = "pending"'), 'url' => 'hr-checklist-approvals.php'],
];

include __DIR__ . '/includes/header.php';
?>
<div class="stats-row">
    <?php foreach ($cards as $card): ?>
        <a class="stat-card stat-info" href="<?php echo h($card['url']); ?>" style="text-decoration:none;color:inherit;">
            <div class="stat-icon">•</div>
            <div class="stat-content">
                <h3><?php echo h((string)$card['value']); ?></h3>
                <p><?php echo h($card['label']); ?></p>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h2>نمای کلی HR</h2></div>
    <div class="card-body">
        <p class="text-muted">این داشبورد لایه مشترک ارزیابی، عملکرد، پلنر، KPI و OKR/TMO را بدون تغییر صفحات قدیمی آماده می‌کند.</p>
        <div class="quick-actions">
            <a class="quick-action-btn" href="hr-tests-bank.php"><span class="icon">▣</span><strong>آزمون‌های سازمانی</strong></a>
            <a class="quick-action-btn" href="hr-role-duties.php"><span class="icon">▣</span><strong>شرح وظایف و چک‌لیست</strong></a>
            <a class="quick-action-btn" href="hr-kpi-definitions.php"><span class="icon">▣</span><strong>KPI ارزیابی</strong></a>
            <a class="quick-action-btn" href="hr-planner-mine.php"><span class="icon">▣</span><strong>پلنر کاری</strong></a>
            <a class="quick-action-btn" href="hr-okr-objectives.php"><span class="icon">▣</span><strong>اهداف، OKR و TMO</strong></a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
