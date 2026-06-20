<?php
require_once __DIR__ . '/lib/hr_evaluation_service.php';
$currentAdmin = adminGuard('employee');
$pageTitle = 'ارزیابی کارکنان';
$message = '';
$error = '';

try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'Employee evaluations bootstrap failed: ' . $e->getMessage());
    return;
}

$canEvaluate = adminPermissionAllows($currentAdmin, 'employee_evaluations', ['employee', 'manager', 'admin', 'super_admin']);
$periods = hrFetchPeriods($db, true);
$categories = hrFetchCategories($db, true);
$periodId = (int)($_POST['period_id'] ?? $_GET['period_id'] ?? ($periods[0]['id'] ?? 0));
$categoryId = (int)($_POST['category_id'] ?? $_GET['category_id'] ?? ($categories[0]['id'] ?? 0));
$selectedPeriod = $periodId ? hrFindPeriod($db, $periodId) : null;
$selectedCategory = $categoryId ? hrFindCategory($db, $categoryId) : null;
$employees = $selectedCategory ? hrEligibleEmployees($db, $currentAdmin, $selectedCategory) : [];
$selectedEmployeeId = (int)($_POST['employee_id'] ?? $_GET['employee_id'] ?? ($employees[0]['id'] ?? 0));
$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((int)$employee['id'] === $selectedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}
$criteria = ($selectedCategory && $selectedEmployee) ? hrFetchCriteria($db, $categoryId, true) : [];
if ($selectedEmployee) {
    $criteria = array_values(array_filter($criteria, static fn($criterion) => hrCriterionAppliesToEmployee($criterion, $selectedEmployee)));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        if (!$canEvaluate) {
            throw new RuntimeException('مجوز ارزیابی همکاران برای حساب شما فعال نیست.');
        }
        if (!$selectedPeriod || !$selectedCategory || !$selectedEmployee) {
            throw new RuntimeException('دوره، فرم یا کارمند انتخاب شده معتبر نیست.');
        }
        $answers = is_array($_POST['answer'] ?? null) ? $_POST['answer'] : [];
        $calculated = hrSaveEvaluation($db, $currentAdmin, $periodId, $selectedEmployeeId, $categoryId, $answers, trim((string)($_POST['notes'] ?? '')));
        redirectTo('employee-evaluations.php?saved=1&period_id=' . urlencode((string)$periodId) . '&category_id=' . urlencode((string)$categoryId));
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    safeAdminLog('Employee evaluation save failed: ' . $e->getMessage());
    $error = 'عملیات انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
}

if (isset($_GET['saved'])) {
    $message = 'ارزیابی با موفقیت ذخیره و امتیاز عملکرد به روز شد.';
}

$mine = [];
try {
    $stmt = $db->prepare('
        SELECT ev.*, target.full_name, target.username, p.title AS period_title, c.title AS category_title
        FROM employee_evaluations ev
        JOIN admins target ON target.id = ev.employee_id
        LEFT JOIN hr_evaluation_periods p ON p.id = ev.period_id
        LEFT JOIN hr_evaluation_categories c ON c.id = ev.category_id
        WHERE ev.evaluator_id = ?
        ORDER BY ev.updated_at DESC
        LIMIT 30
    ');
    $stmt->execute([(int)$currentAdmin['id']]);
    $mine = $stmt->fetchAll();
} catch (Throwable $e) {
    safeAdminLog('Employee evaluation list failed: ' . $e->getMessage());
    $error = $error ?: 'داده های ارزیابی در حال حاضر قابل نمایش نیستند.';
}

$inputTypes = hrEvaluationInputTypes();
include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<?php if (!$canEvaluate): ?><div class="alert" style="background:#fff3cd;color:#856404">مجوز ارزیابی همکاران برای شما فعال نیست. با مدیر سیستم تماس بگیرید.</div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>انتخاب دوره و فرم ارزیابی</h2>
        <?php if (adminPermissionAllows($currentAdmin, 'employee_evaluation_settings', ['admin','super_admin'])): ?><a class="btn btn-primary" href="evaluation-builder.php">ساخت ارزیابی</a><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="get" class="admin-filter">
            <select class="form-control" name="period_id" required>
                <?php foreach ($periods as $period): ?><option value="<?php echo h($period['id']); ?>" <?php echo (int)$period['id'] === $periodId ? 'selected' : ''; ?>><?php echo h($period['title'] . ' - ' . ($period['period_key'] ?: $period['status'])); ?></option><?php endforeach; ?>
            </select>
            <select class="form-control" name="category_id" required>
                <?php foreach ($categories as $category): ?><option value="<?php echo h($category['id']); ?>" <?php echo (int)$category['id'] === $categoryId ? 'selected' : ''; ?>><?php echo h($category['title']); ?></option><?php endforeach; ?>
            </select>
            <select class="form-control" name="employee_id">
                <?php foreach ($employees as $employee): ?><option value="<?php echo h($employee['id']); ?>" <?php echo (int)$employee['id'] === $selectedEmployeeId ? 'selected' : ''; ?>><?php echo h(hrEmployeeDisplayName($employee) . ' - ' . ($employee['department'] ?: '-') . ' - ' . $employee['role']); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary">بارگذاری فرم</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>ثبت ارزیابی</h2></div>
    <div class="card-body">
        <?php if (!$periods): ?>
            <p class="text-muted text-center">دوره فعال برای ارزیابی وجود ندارد.</p>
        <?php elseif (!$categories): ?>
            <p class="text-muted text-center">فرم ارزیابی فعال وجود ندارد.</p>
        <?php elseif (!$selectedEmployee): ?>
            <p class="text-muted text-center">کارمند سازگار با این فرم پیدا نشد.</p>
        <?php elseif (!$criteria): ?>
            <p class="text-muted text-center">برای این فرم معیار فعال و قابل نمایش وجود ندارد.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                <input type="hidden" name="period_id" value="<?php echo h($periodId); ?>">
                <input type="hidden" name="category_id" value="<?php echo h($categoryId); ?>">
                <input type="hidden" name="employee_id" value="<?php echo h($selectedEmployeeId); ?>">
                <div class="stats-row">
                    <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo h($selectedPeriod['title'] ?? ''); ?></h3><p>دوره فعال</p></div></div>
                    <div class="stat-card stat-info"><div class="stat-content"><h3><?php echo h($selectedCategory['title'] ?? ''); ?></h3><p>فرم ارزیابی</p></div></div>
                    <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo h(hrEmployeeDisplayName($selectedEmployee)); ?></h3><p>هدف ارزیابی</p></div></div>
                </div>
                <div class="admin-filter">
                    <?php foreach ($criteria as $criterion): ?>
                        <div class="form-group">
                            <label><?php echo h($criterion['title']); ?> <small class="text-muted"><?php echo h($inputTypes[$criterion['input_type']] ?? $criterion['input_type']); ?> / وزن <?php echo h($criterion['weight']); ?></small></label>
                            <?php if ($criterion['input_type'] === 'text'): ?>
                                <textarea class="form-control" name="answer[<?php echo h($criterion['id']); ?>]"></textarea>
                            <?php elseif ($criterion['input_type'] === 'yes_no'): ?>
                                <select class="form-control" name="answer[<?php echo h($criterion['id']); ?>]"><option value="yes">بله</option><option value="no">خیر</option></select>
                            <?php elseif ($criterion['input_type'] === 'multiple_choice'): $options = hrCriterionOptions($criterion); ?>
                                <select class="form-control" name="answer[<?php echo h($criterion['id']); ?>]">
                                    <?php foreach ($options as $index => $option): ?><option value="<?php echo h($index); ?>"><?php echo h($option['label']); ?></option><?php endforeach; ?>
                                    <?php if (!$options): ?><option value="0">بدون گزینه تعریف شده</option><?php endif; ?>
                                </select>
                            <?php else: ?>
                                <input class="form-control" type="number" min="0" max="<?php echo h($criterion['max_score']); ?>" step="0.01" name="answer[<?php echo h($criterion['id']); ?>]" value="<?php echo h($criterion['max_score']); ?>">
                            <?php endif; ?>
                            <?php if ($criterion['description']): ?><small class="text-muted"><?php echo h($criterion['description']); ?></small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-group"><label>یادداشت خصوصی</label><textarea class="form-control" name="notes"></textarea></div>
                <button class="btn btn-success" <?php echo $canEvaluate ? '' : 'disabled'; ?>>ذخیره ارزیابی</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>ارزیابی های ثبت شده توسط شما</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>کارمند</th><th>دوره</th><th>فرم</th><th>منبع</th><th>امتیاز</th><th>خصوصی</th><th>آخرین ویرایش</th></tr></thead><tbody>
        <?php foreach ($mine as $row): ?>
            <tr>
                <td><?php echo h($row['full_name'] ?: $row['username']); ?></td>
                <td><?php echo h($row['period_title'] ?: $row['period_month']); ?></td>
                <td><?php echo h($row['category_title'] ?: $row['category_group']); ?></td>
                <td><?php echo h($row['source_type']); ?></td>
                <td><?php echo h($row['category_score'] ?: max((float)$row['peer_score'], (float)$row['manager_score'])); ?></td>
                <td><?php echo ((int)$row['is_private'] === 1) ? 'بله' : 'خیر'; ?></td>
                <td><?php echo h($row['updated_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$mine): ?><tr><td colspan="7" class="text-center text-muted">هنوز ارزیابی ثبت نکرده اید.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
