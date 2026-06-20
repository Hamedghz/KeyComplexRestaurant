<?php
require_once __DIR__ . '/lib/hr_evaluation_service.php';

$currentAdmin = adminGuard('employee');
$pageTitle = 'نتایج ارزیابی';
$message = '';
$error = '';

try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
    hrSyncAssessmentCatalogToForms($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'Employee assessments bootstrap failed: ' . $e->getMessage());
    return;
}

$canManageResults = adminPermissionAllows($currentAdmin, 'employee_assessment_results', ['manager', 'admin', 'super_admin']);
$canViewTeamResults = adminPermissionAllows($currentAdmin, 'employee_performance', ['manager', 'admin', 'super_admin']);
$visibilityLevels = hrVisibilityLevels();
$assessmentCategories = hrAssessmentCategories();
$tests = hrFetchAssessmentTests($db, true);

$employeeWhere = ["is_active = 1", "role IN ('employee','manager','admin')"];
$employeeParams = [];
if (!$canViewTeamResults) {
    $employeeWhere[] = 'id = ?';
    $employeeParams[] = (int)$currentAdmin['id'];
}
$employeeStmt = $db->prepare('SELECT id, username, full_name, role, department FROM admins WHERE ' . implode(' AND ', $employeeWhere) . ' ORDER BY department, full_name, username');
$employeeStmt->execute($employeeParams);
$employees = $employeeStmt->fetchAll();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        if (!$canManageResults) {
            throw new RuntimeException('مجوز ثبت یا ویرایش نتیجه آزمون برای حساب شما فعال نیست.');
        }

        $action = (string)($_POST['assessment_action'] ?? 'save');
        if ($action !== 'save') {
            if ($action === 'assign_test') {
                $testId = (int)($_POST['test_id'] ?? 0);
                $employeeId = (int)($_POST['employee_id'] ?? 0);
                $department = trim((string)($_POST['department'] ?? ''));
                $role = trim((string)($_POST['role'] ?? ''));
                if (!$testId || (!$employeeId && $department === '' && $role === '')) {
                    throw new RuntimeException('برای اختصاص آزمون، آزمون و حداقل یک محدوده هدف لازم است.');
                }
                $dueDate = parsePersianDate($_POST['due_date'] ?? '', false) ?: null;
                $periodId = ($_POST['period_id'] ?? '') === '' ? null : (int)$_POST['period_id'];
                $duplicate = $db->prepare('SELECT id FROM hr_test_assignments WHERE test_id = ? AND COALESCE(employee_id,0) = ? AND COALESCE(department,"") = ? AND COALESCE(role,"") = ? AND status = "active" LIMIT 1');
                $duplicate->execute([$testId, $employeeId, $department, $role]);
                if ($duplicate->fetchColumn() && empty($_POST['allow_duplicate'])) {
                    throw new RuntimeException('این آزمون قبلا برای همین محدوده اختصاص داده شده است.');
                }
                $db->prepare('INSERT INTO hr_test_assignments (test_id,employee_id,department,role,period_id,due_date,status,allow_retake,assigned_by) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$testId, $employeeId ?: null, $department ?: null, $role ?: null, $periodId, $dueDate, 'active', isset($_POST['allow_retake']) ? 1 : 0, (int)$currentAdmin['id']]);
                redirectTo('employee-assessments.php?assigned=1');
            }
            throw new RuntimeException('عملیات درخواستی معتبر نیست.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $testId = (int)($_POST['test_id'] ?? 0);
        $completionDate = parsePersianDate($_POST['completion_date'] ?? '', false) ?: date('Y-m-d');
        $visibility = isset($visibilityLevels[$_POST['visibility'] ?? 'private']) ? (string)$_POST['visibility'] : 'private';
        $currentAttachment = '';

        if ($id > 0) {
            $stmt = $db->prepare('SELECT attachment_path FROM hr_assessment_results WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $currentAttachment = (string)($stmt->fetchColumn() ?: '');
        }

        $employeeAllowed = false;
        foreach ($employees as $employee) {
            if ((int)$employee['id'] === $employeeId) {
                $employeeAllowed = true;
                break;
            }
        }
        if (!$employeeAllowed) {
            throw new RuntimeException('کارمند انتخاب شده معتبر نیست.');
        }

        $testAllowed = false;
        foreach ($tests as $test) {
            if ((int)$test['id'] === $testId) {
                $testAllowed = true;
                break;
            }
        }
        if (!$testAllowed) {
            throw new RuntimeException('آزمون انتخاب شده معتبر نیست.');
        }

        $attachmentPath = hrUploadAssessmentAttachment('attachment', $currentAttachment);
        $data = [
            'employee_id' => $employeeId,
            'test_id' => $testId,
            'completion_date' => $completionDate,
            'result_summary' => trim((string)($_POST['result_summary'] ?? '')) ?: null,
            'score_value' => trim((string)($_POST['score_value'] ?? '')) ?: null,
            'result_type' => trim((string)($_POST['result_type'] ?? '')) ?: null,
            'attachment_path' => $attachmentPath ?: null,
            'hr_notes' => trim((string)($_POST['hr_notes'] ?? '')) ?: null,
            'visibility' => $visibility,
            'recorded_by' => (int)$currentAdmin['id'],
        ];

        if ($id > 0) {
            $data['id'] = $id;
            $db->prepare('UPDATE hr_assessment_results SET employee_id=:employee_id,test_id=:test_id,completion_date=:completion_date,result_summary=:result_summary,score_value=:score_value,result_type=:result_type,attachment_path=:attachment_path,hr_notes=:hr_notes,visibility=:visibility,recorded_by=:recorded_by WHERE id=:id')
                ->execute($data);
        } else {
            $db->prepare('INSERT INTO hr_assessment_results (employee_id,test_id,completion_date,result_summary,score_value,result_type,attachment_path,hr_notes,visibility,recorded_by) VALUES (:employee_id,:test_id,:completion_date,:result_summary,:score_value,:result_type,:attachment_path,:hr_notes,:visibility,:recorded_by)')
                ->execute($data);
        }
        redirectTo('employee-assessments.php?saved=1&employee_id=' . urlencode((string)$employeeId));
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    safeAdminLog('Employee assessment result save failed: ' . $e->getMessage());
    $error = 'ذخیره نتیجه آزمون انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
}

if (isset($_GET['saved'])) {
    $message = 'نتیجه آزمون ذخیره شد.';
}
if (isset($_GET['assigned'])) {
    $message = 'آزمون با موفقیت اختصاص داده شد.';
}

$employeeFilterId = (int)($_GET['employee_id'] ?? 0);
$testFilterId = (int)($_GET['test_id'] ?? 0);
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$visibilityFilter = trim((string)($_GET['visibility'] ?? ''));
$editResult = null;

if (($_GET['edit'] ?? '') !== '' && $canManageResults) {
    $stmt = $db->prepare('SELECT * FROM hr_assessment_results WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_GET['edit']]);
    $editResult = $stmt->fetch() ?: null;
}

$where = [];
$params = [];
if ($canViewTeamResults) {
    $where[] = '1=1';
} else {
    $where[] = "r.employee_id = ?";
    $params[] = (int)$currentAdmin['id'];
    $where[] = "r.visibility = 'employee'";
}
if ($employeeFilterId > 0) {
    $where[] = 'r.employee_id = ?';
    $params[] = $employeeFilterId;
}
if ($testFilterId > 0) {
    $where[] = 'r.test_id = ?';
    $params[] = $testFilterId;
}
if ($categoryFilter !== '' && isset($assessmentCategories[$categoryFilter])) {
    $where[] = 't.category = ?';
    $params[] = $categoryFilter;
}
if ($visibilityFilter !== '' && isset($visibilityLevels[$visibilityFilter])) {
    $where[] = 'r.visibility = ?';
    $params[] = $visibilityFilter;
}

$stmt = $db->prepare('
    SELECT r.*, t.title AS test_title, t.test_code, t.category, t.scoring_method_type, a.full_name, a.username, a.department, a.role, recorder.full_name AS recorded_by_name, recorder.username AS recorded_by_username
    FROM hr_assessment_results r
    JOIN hr_assessment_tests t ON t.id = r.test_id
    JOIN admins a ON a.id = r.employee_id
    LEFT JOIN admins recorder ON recorder.id = r.recorded_by
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY r.completion_date DESC, r.id DESC
    LIMIT 200
');
$stmt->execute($params);
$results = $stmt->fetchAll();
$periods = hrFetchPeriods($db, false);
$departments = $db->query("SELECT DISTINCT department FROM admins WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$roles = ['' => 'همه نقش ها', 'employee' => 'Employee', 'manager' => 'Manager', 'admin' => 'Admin'];

include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>نتایج آزمون ها و سنجش های سازمانی</h2>
        <?php if (adminPermissionAllows($currentAdmin, 'employee_evaluation_settings', ['admin','super_admin'])): ?><a class="btn btn-sm btn-primary" href="evaluation-builder.php">ساخت</a><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="get" class="admin-filter">
            <select class="form-control" name="employee_id"><option value="">همه کارکنان</option><?php foreach ($employees as $employee): ?><option value="<?php echo h($employee['id']); ?>" <?php echo $employeeFilterId === (int)$employee['id'] ? 'selected' : ''; ?>><?php echo h(hrEmployeeDisplayName($employee)); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="test_id"><option value="">همه آزمون ها</option><?php foreach ($tests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo $testFilterId === (int)$test['id'] ? 'selected' : ''; ?>><?php echo h($test['title']); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="category"><option value="">همه دسته ها</option><?php foreach ($assessmentCategories as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $categoryFilter === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="visibility"><option value="">همه سطوح مشاهده</option><?php foreach ($visibilityLevels as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $visibilityFilter === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary">نمایش</button>
        </form>
    </div>
</div>

<?php if ($canManageResults): ?>
<div class="card">
    <div class="card-header"><h2>اختصاص آزمون به کارکنان</h2></div>
    <div class="card-body">
        <form method="post" class="admin-filter">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="assessment_action" value="assign_test">
            <select class="form-control" name="test_id" required><option value="">آزمون</option><?php foreach ($tests as $test): ?><option value="<?php echo h($test['id']); ?>"><?php echo h($test['title'] . ' / ' . $test['test_code']); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="employee_id"><option value="">همه کارکنان محدوده</option><?php foreach ($employees as $employee): ?><option value="<?php echo h($employee['id']); ?>"><?php echo h(hrEmployeeDisplayName($employee)); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="department"><option value="">همه دپارتمان ها</option><?php foreach ($departments as $dept): ?><option value="<?php echo h($dept); ?>"><?php echo h($dept); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="role"><?php foreach ($roles as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select>
            <select class="form-control" name="period_id"><option value="">بدون دوره</option><?php foreach ($periods as $period): ?><option value="<?php echo h($period['id']); ?>"><?php echo h($period['title']); ?></option><?php endforeach; ?></select>
            <input class="form-control" name="due_date" placeholder="مهلت YYYY-MM-DD">
            <label><input type="checkbox" name="allow_retake" value="1"> اجازه تکرار</label>
            <label><input type="checkbox" name="allow_duplicate" value="1"> ثبت تکراری آگاهانه</label>
            <button class="btn btn-success">اختصاص آزمون</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?php echo $editResult ? 'ویرایش نتیجه آزمون' : 'ثبت نتیجه آزمون'; ?></h2></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="assessment_action" value="save">
            <input type="hidden" name="id" value="<?php echo h($editResult['id'] ?? 0); ?>">
            <div class="admin-filter">
                <select class="form-control" name="employee_id" required><option value="">کارمند</option><?php foreach ($employees as $employee): ?><option value="<?php echo h($employee['id']); ?>" <?php echo (int)($editResult['employee_id'] ?? $employeeFilterId) === (int)$employee['id'] ? 'selected' : ''; ?>><?php echo h(hrEmployeeDisplayName($employee) . ' - ' . ($employee['department'] ?: '-')); ?></option><?php endforeach; ?></select>
                <select class="form-control" name="test_id" required><option value="">آزمون</option><?php foreach ($tests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo (int)($editResult['test_id'] ?? $testFilterId) === (int)$test['id'] ? 'selected' : ''; ?>><?php echo h($test['title'] . ' / ' . $test['test_code']); ?></option><?php endforeach; ?></select>
                <input class="form-control" name="completion_date" placeholder="تاریخ انجام YYYY-MM-DD" value="<?php echo h($editResult['completion_date'] ?? date('Y-m-d')); ?>">
                <input class="form-control" name="score_value" placeholder="امتیاز / مقدار نتیجه" value="<?php echo h($editResult['score_value'] ?? ''); ?>">
                <input class="form-control" name="result_type" placeholder="پروفایل / نوع نتیجه" value="<?php echo h($editResult['result_type'] ?? ''); ?>">
                <select class="form-control" name="visibility"><?php foreach ($visibilityLevels as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editResult['visibility'] ?? 'private') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <input class="form-control" type="file" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp">
            </div>
            <textarea class="form-control" name="result_summary" placeholder="خلاصه نتیجه / خروجی پروفایل"><?php echo h($editResult['result_summary'] ?? ''); ?></textarea>
            <textarea class="form-control mt-3" name="hr_notes" placeholder="یادداشت HR"><?php echo h($editResult['hr_notes'] ?? ''); ?></textarea>
            <?php if (!empty($editResult['attachment_path'])): ?><p class="text-muted mt-3">پیوست فعلی: <a href="../<?php echo h($editResult['attachment_path']); ?>" target="_blank" rel="noopener">مشاهده</a></p><?php endif; ?>
            <div class="mt-3"><button class="btn btn-success">ذخیره نتیجه</button><?php if ($editResult): ?> <a class="btn" href="employee-assessments.php">لغو</a><?php endif; ?></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2>تاریخچه نتایج</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>کارمند</th><th>آزمون</th><th>تاریخ</th><th>نتیجه</th><th>خلاصه</th><th>یادداشت HR</th><th>مشاهده</th><th>پیوست</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?php echo h($result['full_name'] ?: $result['username']); ?><br><small class="text-muted"><?php echo h(($result['department'] ?: '-') . ' / ' . $result['role']); ?></small></td>
                <td><?php echo h($result['test_title']); ?><br><small class="text-muted"><?php echo h($result['test_code'] . ' / ' . ($assessmentCategories[$result['category']] ?? $result['category'])); ?></small></td>
                <td><?php echo h($result['completion_date']); ?></td>
                <td><?php echo h(trim((string)($result['score_value'] ?? '') . ' ' . (string)($result['result_type'] ?? '')) ?: '-'); ?></td>
                <td><?php echo h($result['result_summary'] ?: '-'); ?></td>
                <td><?php echo $canViewTeamResults ? h($result['hr_notes'] ?: '-') : '-'; ?></td>
                <td><?php echo h($visibilityLevels[$result['visibility']] ?? $result['visibility']); ?></td>
                <td><?php echo !empty($result['attachment_path']) ? '<a href="../' . h($result['attachment_path']) . '" target="_blank" rel="noopener">مشاهده</a>' : '-'; ?></td>
                <td><?php if ($canManageResults): ?><a class="btn btn-sm btn-primary" href="?edit=<?php echo h($result['id']); ?>">ویرایش</a><?php else: ?>-<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$results): ?><tr><td colspan="9" class="text-center text-muted">نتیجه ای برای فیلتر انتخاب شده ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
