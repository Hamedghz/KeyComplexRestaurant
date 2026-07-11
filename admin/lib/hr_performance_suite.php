<?php
require_once __DIR__ . '/admin_schema.php';

function hrPerformanceSuitePages(): array {
    return [
        'hr-tests-bank.php' => ['domain' => 'tests', 'title' => 'بانک آزمون‌ها', 'role' => 'admin'],
        'hr-test-questions.php' => ['domain' => 'tests', 'title' => 'سوالات و ابعاد آزمون', 'role' => 'admin'],
        'hr-test-assignments.php' => ['domain' => 'tests', 'title' => 'تخصیص آزمون', 'role' => 'manager'],
        'hr-my-tests.php' => ['domain' => 'tests', 'title' => 'آزمون‌های من', 'role' => 'employee'],
        'hr-test-results.php' => ['domain' => 'tests', 'title' => 'نتایج آزمون‌ها', 'role' => 'manager'],
        'hr-test-personnel-report.php' => ['domain' => 'tests', 'title' => 'گزارش آزمون‌های پرسنل', 'role' => 'manager'],
        'hr-role-duties.php' => ['domain' => 'duties', 'title' => 'شرح وظایف نقش‌ها', 'role' => 'manager'],
        'hr-checklist-templates.php' => ['domain' => 'duties', 'title' => 'قالب چک‌لیست‌ها', 'role' => 'manager'],
        'hr-checklist-assignments.php' => ['domain' => 'duties', 'title' => 'تخصیص چک‌لیست', 'role' => 'manager'],
        'hr-checklist-submissions.php' => ['domain' => 'duties', 'title' => 'ثبت انجام چک‌لیست', 'role' => 'employee'],
        'hr-checklist-approvals.php' => ['domain' => 'duties', 'title' => 'تایید مدیر / بازرس', 'role' => 'manager'],
        'hr-checklist-progress.php' => ['domain' => 'duties', 'title' => 'گزارش پیشرفت چک‌لیست‌ها', 'role' => 'manager'],
        'hr-kpi-definitions.php' => ['domain' => 'kpi', 'title' => 'تعریف KPI', 'role' => 'manager'],
        'hr-kpi-assignments.php' => ['domain' => 'kpi', 'title' => 'تخصیص KPI', 'role' => 'manager'],
        'hr-kpi-entries.php' => ['domain' => 'kpi', 'title' => 'ورود مقدار KPI', 'role' => 'employee'],
        'hr-kpi-scores.php' => ['domain' => 'kpi', 'title' => 'محاسبه امتیاز', 'role' => 'manager'],
        'hr-kpi-reports.php' => ['domain' => 'kpi', 'title' => 'گزارش عملکرد KPI', 'role' => 'manager'],
        'hr-planner-mine.php' => ['domain' => 'planner', 'title' => 'پلنر من', 'role' => 'employee'],
        'hr-planner-today.php' => ['domain' => 'planner', 'title' => 'پلنر امروز', 'role' => 'employee'],
        'hr-planner-tomorrow.php' => ['domain' => 'planner', 'title' => 'تسک‌های فردا', 'role' => 'employee'],
        'hr-planner-overdue.php' => ['domain' => 'planner', 'title' => 'تسک‌های عقب‌افتاده', 'role' => 'employee'],
        'hr-planner-referred.php' => ['domain' => 'planner', 'title' => 'تسک‌های ارجاع‌شده', 'role' => 'employee'],
        'hr-planner-reports.php' => ['domain' => 'planner', 'title' => 'گزارش تسک‌ها', 'role' => 'manager'],
        'hr-okr-objectives.php' => ['domain' => 'okr', 'title' => 'اهداف ماهیانه مجموعه', 'role' => 'manager'],
        'hr-okr-key-results.php' => ['domain' => 'okr', 'title' => 'KR / نتایج کلیدی', 'role' => 'manager'],
        'hr-okr-actions.php' => ['domain' => 'okr', 'title' => 'اقدامات', 'role' => 'manager'],
        'hr-okr-task-links.php' => ['domain' => 'okr', 'title' => 'اتصال اقدام به تسک', 'role' => 'manager'],
        'hr-okr-progress.php' => ['domain' => 'okr', 'title' => 'ثبت پیشرفت', 'role' => 'employee'],
        'hr-tmo-reviews.php' => ['domain' => 'okr', 'title' => 'بازبینی TMO', 'role' => 'manager'],
    ];
}

function hrPerformanceSuiteDomainTitles(): array {
    return [
        'tests' => 'آزمون‌های سازمانی',
        'duties' => 'شرح وظایف و چک‌لیست',
        'kpi' => 'KPI ارزیابی',
        'planner' => 'پلنر کاری',
        'okr' => 'اهداف، OKR و TMO',
    ];
}

function hrPerformanceSuiteStandards(): array {
    return [
        'tests' => ['Customer understanding', 'Sales script', 'FAB', 'Professional listening', 'Behavioral standards'],
        'duties' => ['Sales script', 'FAB', 'Professional listening', 'Objection management', 'SOP / 5S'],
        'kpi' => ['Customer journey', 'After-sales', 'Referral', 'Financial templates', 'BSF formula', 'VoIP hooks'],
        'planner' => ['Customer follow-up', 'Objection tasks', 'After-sales corrective actions', 'Journey actions'],
        'okr' => ['Monthly objectives', 'KR progress', 'TMO ownership', 'BSF and financial KPI links'],
    ];
}

function hrPerformanceSuiteCount(string $table): ?int {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !adminTableExists($table)) {
        return null;
    }
    try {
        return (int)adminDb()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    } catch (Throwable $e) {
        safeAdminLog('HR suite count failed for ' . $table . ': ' . $e->getMessage());
        return null;
    }
}

function hrPerformanceSuiteRender(string $pageKey): void {
    $pages = hrPerformanceSuitePages();
    $page = $pages[$pageKey] ?? $pages['hr-tests-bank.php'];
    $currentAdmin = adminGuard($page['role']);
    $pageTitle = $page['title'];
    $domains = hrPerformanceSuiteDomainTitles();
    $standards = hrPerformanceSuiteStandards();
    $domain = $page['domain'];
    $counts = [
        'استانداردها' => hrPerformanceSuiteCount('business_standards'),
        'شرح وظایف' => hrPerformanceSuiteCount('hr_role_duties'),
        'چک‌لیست‌ها' => hrPerformanceSuiteCount('hr_checklist_templates'),
        'KPI' => hrPerformanceSuiteCount('hr_kpi_definitions'),
        'تسک‌ها' => hrPerformanceSuiteCount('hr_planner_tasks'),
        'اهداف' => hrPerformanceSuiteCount('hr_monthly_objectives'),
    ];

    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="stats-row">
        <?php foreach ($counts as $label => $count): ?>
            <div class="stat-card stat-info">
                <div class="stat-icon">•</div>
                <div class="stat-content">
                    <h3><?php echo h($count === null ? '-' : (string)$count); ?></h3>
                    <p><?php echo h($label); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><?php echo h($domains[$domain] ?? $pageTitle); ?></h2>
            <span class="badge badge-info">ساختار جدید منابع انسانی و عملکرد</span>
        </div>
        <div class="card-body">
            <p class="text-muted">
                این صفحه به ساختار تاییدشده «ارزیابی، عملکرد و اهداف» متصل است. داده‌ها از جداول افزایشی HR خوانده می‌شوند و استانداردهای کوچینگ به عنوان seed، KPI، چک‌لیست، تسک و رابطه OKR استفاده می‌شوند؛ منوی مستقل برای آن‌ها ساخته نشده است.
            </p>
            <div class="quick-actions mt-3">
                <?php foreach ($pages as $url => $item): ?>
                    <?php if ($item['domain'] === $domain): ?>
                        <a class="quick-action-btn" href="<?php echo h($url); ?>">
                            <span class="icon">▣</span>
                            <strong><?php echo h($item['title']); ?></strong>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>استانداردهای اعمال‌شده در این بخش</h2></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>استاندارد</th><th>محل استفاده</th></tr></thead>
                <tbody>
                    <?php foreach (($standards[$domain] ?? []) as $standard): ?>
                        <tr>
                            <td><?php echo h($standard); ?></td>
                            <td><?php echo h($domains[$domain] ?? $pageTitle); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($standards[$domain])): ?>
                        <tr><td colspan="2" class="text-muted"><?php echo h(t('no_records')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
}
