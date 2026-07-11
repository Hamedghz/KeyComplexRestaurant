<?php

require_once __DIR__ . '/tests.php';

function hrOrgTestsRenderBankPage(): void {
    [$db, $admin, $pageTitle] = hrOrgTestsStart('آزمون‌های سازمانی KEY', 'admin');
    $message = '';
    $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'seed_key_tests') {
                requireValidCsrf();
                $result = seedKeyOrganizationalTests($db, (int)$admin['id']);
                hrOrgTestsRedirect('hr-tests.php', ['seeded' => 1, 'tests' => $result['tests'], 'questions' => $result['questions']]);
            }
            if ($action === 'save_test') {
                $id = hrOrgTestsSaveTest($db, $admin, $_POST);
                hrOrgTestsRedirect('hr-tests.php', ['saved' => 1, 'id' => $id]);
            }
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        safeAdminLog('Organizational test bank failed: ' . $e->getMessage());
        $error = 'ذخیره آزمون انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    }
    if (isset($_GET['seeded'])) $message = 'آزمون‌های سازمانی KEY آماده‌سازی شد.';
    if (isset($_GET['saved'])) $message = 'آزمون ذخیره شد.';
    $tests = hrFetchAssessmentTests($db, false);
    include dirname(__DIR__) . '/../includes/header.php';
    hrOrgTestsAlert($message);
    hrOrgTestsAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>بانک آزمون</h2><form method="post"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="seed_key_tests"><button class="btn btn-sm btn-success">Seed آزمون‌های KEY</button></form></div>
    <div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_test"><div class="form-group"><label>کد آزمون</label><input class="form-control" name="test_code" required></div><div class="form-group"><label>عنوان</label><input class="form-control" name="title" required></div><div class="form-group"><label>دسته</label><select class="form-control" name="category"><option value="organizational_behavior">رفتار سازمانی</option><option value="restaurant_operations">عملیات رستوران</option><option value="sales_customer_interaction">فروش و مشتری</option><option value="marketing_content">بازاریابی و محتوا</option><option value="kpi_reporting_literacy">KPI و گزارش</option></select></div><div class="form-group"><label>سیاست تکرار</label><select class="form-control" name="retake_policy"><option value="manager_approval_required">نیازمند تایید مدیر</option><option value="free">آزاد</option></select></div><div class="form-group"><label>ترتیب</label><input class="form-control" type="number" name="sort_order" value="100"></div><label><input type="checkbox" name="allow_retake" value="1" checked> امکان تکرار</label><label><input type="checkbox" name="is_active" value="1" checked> فعال</label><div class="form-group" style="grid-column:1/-1"><label>توضیح</label><textarea class="form-control" name="description"></textarea></div><button class="btn btn-primary">ذخیره آزمون</button></form></div></div>
    <div class="card"><div class="card-header"><h2>آزمون‌های موجود</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>عنوان</th><th>کد</th><th>دسته</th><th>تکرار</th><th>زمان</th><th>سوال</th><th>عملیات</th></tr></thead><tbody><?php foreach ($tests as $test): ?><tr><td><?php echo h($test['title']); ?></td><td><?php echo h($test['test_code']); ?></td><td><?php echo h($test['category']); ?></td><td><?php echo h((string)($test['retake_policy'] ?? '-')); ?></td><td><?php echo empty($test['time_limit_minutes']) ? 'بدون زمان' : h((string)$test['time_limit_minutes']); ?></td><td><?php echo h((string)($test['question_count'] ?? '')); ?></td><td><a class="btn btn-sm" href="hr-test-questions.php?test_id=<?php echo h($test['id']); ?>">سوالات</a> <a class="btn btn-sm btn-primary" href="hr-test-assignments.php?test_id=<?php echo h($test['id']); ?>">تخصیص</a></td></tr><?php endforeach; ?></tbody></table><p class="text-muted"><?php echo h(hrAssessmentDisclaimer()); ?></p></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function hrOrgTestsRenderQuestionsPage(): void {
    [$db, $admin, $pageTitle] = hrOrgTestsStart('ابعاد و سوالات آزمون', 'admin');
    $message = '';
    $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'save_dimension') {
                hrOrgTestsSaveDimension($db, $admin, $_POST);
                hrOrgTestsRedirect('hr-test-questions.php', ['test_id' => (int)$_POST['test_id'], 'saved' => 'dimension']);
            }
            if ($action === 'save_question') {
                hrOrgTestsSaveQuestion($db, $admin, $_POST);
                hrOrgTestsRedirect('hr-test-questions.php', ['test_id' => (int)$_POST['test_id'], 'saved' => 'question']);
            }
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        safeAdminLog('Organizational test question save failed: ' . $e->getMessage());
        $error = 'ذخیره سوال انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    }
    if (isset($_GET['saved'])) $message = 'اطلاعات آزمون ذخیره شد.';
    $tests = hrFetchAssessmentTests($db, true);
    $testId = (int)($_GET['test_id'] ?? ($tests[0]['id'] ?? 0));
    $dimensions = $testId ? hrFetchTestDimensions($db, $testId) : [];
    $questions = $testId ? hrFetchTestQuestions($db, $testId, false) : [];
    include dirname(__DIR__) . '/../includes/header.php';
    hrOrgTestsAlert($message);
    hrOrgTestsAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>انتخاب آزمون</h2></div><div class="card-body"><form method="get" class="admin-filter"><select class="form-control" name="test_id"><?php foreach ($tests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo $testId === (int)$test['id'] ? 'selected' : ''; ?>><?php echo h($test['title']); ?></option><?php endforeach; ?></select><button class="btn btn-primary">نمایش</button></form></div></div>
    <?php if ($testId): ?>
    <div class="card"><div class="card-header"><h2>بعد جدید</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_dimension"><input type="hidden" name="test_id" value="<?php echo h($testId); ?>"><input class="form-control" name="code" placeholder="کد" required><input class="form-control" name="title" placeholder="عنوان" required><input class="form-control" name="positive_label" placeholder="برچسب مثبت"><input class="form-control" name="negative_label" placeholder="برچسب نیازمند بهبود"><input class="form-control" type="number" name="sort_order" value="10"><textarea class="form-control" name="description" placeholder="توضیح" style="grid-column:1/-1"></textarea><button class="btn btn-success">ذخیره بعد</button></form></div></div>
    <div class="card"><div class="card-header"><h2>سوال جدید</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="save_question"><input type="hidden" name="test_id" value="<?php echo h($testId); ?>"><select class="form-control" name="dimension_id"><option value="">بدون بعد</option><?php foreach ($dimensions as $dimension): ?><option value="<?php echo h($dimension['id']); ?>"><?php echo h($dimension['title']); ?></option><?php endforeach; ?></select><input class="form-control" name="code" placeholder="کد سوال" required><input class="form-control" type="number" step="0.1" name="weight" value="1" placeholder="وزن"><select class="form-control" name="answer_type"><option value="single_choice">تک گزینه‌ای</option><option value="multi_choice">چند گزینه‌ای</option><option value="scale_5">مقیاس ۵</option><option value="text">متنی</option></select><select class="form-control" name="scoring_direction"><option value="positive">مثبت</option><option value="negative">معکوس</option></select><input class="form-control" type="number" name="sort_order" value="10"><textarea class="form-control" name="question_text" placeholder="متن سوال" required style="grid-column:1/-1"></textarea><textarea class="form-control" name="options_text" placeholder="هر خط: عنوان گزینه | امتیاز" style="grid-column:1/-1">نیازمند آموزش | 25
قابل قبول | 70
حرفه‌ای و پایدار | 100</textarea><button class="btn btn-success">ذخیره سوال</button></form></div></div>
    <div class="card"><div class="card-header"><h2>سوالات</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>کد</th><th>بعد</th><th>سوال</th><th>نوع</th><th>وزن</th><th>وضعیت</th></tr></thead><tbody><?php foreach ($questions as $question): ?><tr><td><?php echo h($question['code']); ?></td><td><?php echo h($question['dimension_title'] ?: '-'); ?></td><td><?php echo h($question['question_text']); ?></td><td><?php echo h($question['answer_type']); ?></td><td><?php echo h((string)$question['weight']); ?></td><td><?php echo h((string)($question['status'] ?? ($question['is_active'] ? 'active' : 'inactive'))); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php endif; include dirname(__DIR__) . '/../includes/footer.php';
}

function hrOrgTestsRenderAssignmentsPage(): void {
    [$db, $admin, $pageTitle] = hrOrgTestsStart('تخصیص آزمون و آزمون مجدد', 'manager');
    $message = '';
    $error = '';
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'assign_test') {
                hrOrgTestsAssign($db, $admin, $_POST);
                hrOrgTestsRedirect('hr-test-assignments.php', ['assigned' => 1]);
            }
            if ($action === 'review_retake') {
                hrOrgTestsReviewRetake($db, $admin, $_POST);
                hrOrgTestsRedirect('hr-test-assignments.php', ['reviewed' => 1]);
            }
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        safeAdminLog('Organizational test assignment failed: ' . $e->getMessage());
        $error = 'عملیات تخصیص آزمون انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    }
    if (isset($_GET['assigned'])) $message = 'آزمون اختصاص داده شد.';
    if (isset($_GET['reviewed'])) $message = 'درخواست آزمون مجدد بررسی شد.';
    $tests = hrFetchAssessmentTests($db, true);
    $employees = hrOrgTestsFetchEmployees($db, $admin);
    $periods = function_exists('hrFetchPeriods') ? hrFetchPeriods($db, false) : [];
    $assignments = hrFetchAllAssignmentsForOrgTests($db, $admin);
    $retakes = hrOrgTestsFetchRetakeRequests($db, $admin);
    $departments = $db->query("SELECT DISTINCT department FROM admins WHERE is_active=1 AND department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    include dirname(__DIR__) . '/../includes/header.php';
    hrOrgTestsAlert($message);
    hrOrgTestsAlert($error, 'danger');
    ?>
    <div class="card"><div class="card-header"><h2>تخصیص آزمون</h2></div><div class="card-body"><form method="post" class="form-grid"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="assign_test"><select class="form-control" name="test_id" required><option value="">آزمون</option><?php foreach ($tests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo (int)($_GET['test_id'] ?? 0)===(int)$test['id']?'selected':''; ?>><?php echo h($test['title']); ?></option><?php endforeach; ?></select><select class="form-control" name="target_type"><option value="employee">کارمند</option><option value="role">نقش</option><option value="department">دپارتمان</option><option value="all">همه</option></select><select class="form-control" name="employee_id"><option value="">کارمند</option><?php foreach ($employees as $employee): ?><option value="<?php echo h($employee['id']); ?>"><?php echo h(hrEmployeeDisplayName($employee)); ?></option><?php endforeach; ?></select><input class="form-control" name="role" placeholder="نقش"><select class="form-control" name="department"><option value="">دپارتمان</option><?php foreach ($departments as $department): ?><option><?php echo h($department); ?></option><?php endforeach; ?></select><select class="form-control" name="period_id"><option value="">دوره</option><?php foreach ($periods as $period): ?><option value="<?php echo h($period['id']); ?>"><?php echo h($period['title']); ?></option><?php endforeach; ?></select><input class="form-control" name="due_date" placeholder="مهلت YYYY-MM-DD"><input class="form-control" type="number" min="1" max="20" name="max_attempts" value="1"><label><input type="checkbox" name="allow_retake" checked> تکرار مجاز</label><label><input type="checkbox" name="show_result_to_employee" checked> نمایش نتیجه به پرسنل</label><textarea class="form-control" name="description" placeholder="توضیح" style="grid-column:1/-1"></textarea><button class="btn btn-success">اختصاص</button></form></div></div>
    <div class="card"><div class="card-header"><h2>درخواست‌های آزمون مجدد</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>پرسنل</th><th>آزمون</th><th>یادداشت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach ($retakes as $request): ?><tr><td><?php echo h($request['full_name'] ?: $request['username']); ?></td><td><?php echo h($request['test_title']); ?></td><td><?php echo h($request['request_note'] ?: '-'); ?></td><td><?php echo h($request['status']); ?></td><td><?php if ($request['status'] === 'pending'): ?><form method="post" style="display:inline"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="review_retake"><input type="hidden" name="id" value="<?php echo h($request['id']); ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success">تایید</button></form> <form method="post" style="display:inline"><input type="hidden" name="<?php echo h(CSRF_TOKEN_NAME); ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="action" value="review_retake"><input type="hidden" name="id" value="<?php echo h($request['id']); ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-danger">رد</button></form><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="card"><div class="card-header"><h2>تخصیص‌های فعال</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>آزمون</th><th>محدوده</th><th>دوره</th><th>مهلت</th><th>نمایش نتیجه</th><th>وضعیت</th></tr></thead><tbody><?php foreach ($assignments as $assignment): ?><tr><td><?php echo h($assignment['title']); ?></td><td><?php echo h($assignment['target_type'] . ': ' . ($assignment['target_id'] ?: '-')); ?></td><td><?php echo h((string)($assignment['period_id'] ?? '-')); ?></td><td><?php echo h($assignment['due_date'] ?: '-'); ?></td><td><?php echo (int)$assignment['show_result_to_employee'] ? 'بله' : 'خیر'; ?></td><td><?php echo h($assignment['status']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}

function hrFetchAllAssignmentsForOrgTests(PDO $db, array $admin): array {
    $where = ['a.deleted_at IS NULL'];
    $params = [];
    if ((string)($admin['role'] ?? '') === 'manager') {
        $where[] = '(a.department = ? OR a.department IS NULL OR a.department = "")';
        $params[] = (string)($admin['department'] ?? '');
    }
    $stmt = $db->prepare('SELECT a.*, t.title FROM hr_test_assignments a JOIN hr_assessment_tests t ON t.id=a.test_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 200');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function hrOrgTestsFetchRetakeRequests(PDO $db, array $admin): array {
    $where = ['1=1'];
    $params = [];
    if ((string)($admin['role'] ?? '') === 'manager') {
        $where[] = 'u.department = ?';
        $params[] = (string)($admin['department'] ?? '');
    }
    $stmt = $db->prepare('SELECT r.*, t.title AS test_title, u.username, u.full_name, u.department, u.role FROM hr_test_retake_requests r JOIN hr_assessment_tests t ON t.id=r.test_id JOIN admins u ON u.id=r.employee_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.id DESC LIMIT 100');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function hrOrgTestsRenderResultsPage(): void {
    [$db, $admin, $pageTitle] = hrOrgTestsStart('نتایج آزمون‌های سازمانی', 'manager');
    $employee = (int)($_GET['employee_id'] ?? 0);
    $testId = (int)($_GET['test_id'] ?? 0);
    $where = ['r.status="final"', 'r.deleted_at IS NULL'];
    $params = [];
    if ((string)($admin['role'] ?? '') === 'manager') {
        $where[] = 'u.department = ?';
        $params[] = (string)($admin['department'] ?? '');
    }
    if ($employee > 0) {
        $where[] = 'r.employee_id = ?';
        $params[] = $employee;
    }
    if ($testId > 0) {
        $where[] = 'r.test_id = ?';
        $params[] = $testId;
    }
    $stmt = $db->prepare('SELECT r.*, t.title AS test_title, t.test_code, u.username, u.full_name, u.department, u.role FROM hr_test_results r JOIN hr_assessment_tests t ON t.id=r.test_id JOIN admins u ON u.id=r.employee_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT 300');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $tests = hrFetchAssessmentTests($db, true);
    include dirname(__DIR__) . '/../includes/header.php';
    ?>
    <div class="card"><div class="card-header"><h2>فیلتر نتایج</h2></div><div class="card-body"><form method="get" class="admin-filter"><select class="form-control" name="test_id"><option value="">همه آزمون‌ها</option><?php foreach ($tests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo $testId===(int)$test['id']?'selected':''; ?>><?php echo h($test['title']); ?></option><?php endforeach; ?></select><input class="form-control" type="number" name="employee_id" value="<?php echo h((string)$employee); ?>" placeholder="شناسه پرسنل"><button class="btn btn-primary">نمایش</button><a class="btn" href="hr-test-report.php">گزارش</a></form></div></div>
    <div class="card"><div class="card-header"><h2>تاریخچه نتایج</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>پرسنل</th><th>آزمون</th><th>امتیاز</th><th>سطح</th><th>پروفایل</th><th>ابعاد</th><th>تاریخ</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo h($row['full_name'] ?: $row['username']); ?><br><small><?php echo h(($row['department'] ?: '-') . ' / ' . ($row['role'] ?: '-')); ?></small></td><td><?php echo h($row['test_title']); ?><br><small><?php echo h($row['test_code']); ?></small></td><td><?php echo h((string)$row['overall_score']); ?>٪</td><td><?php echo h($row['result_level'] ?: '-'); ?></td><td><?php echo h($row['profile_code'] ?: '-'); ?></td><td><?php $dims = hrJsonDecode($row['dimension_scores_json'] ?? ''); echo h((string)count($dims)); ?></td><td><?php echo h($row['created_at']); ?></td></tr><?php endforeach; ?></tbody></table><p class="text-muted"><?php echo h(hrAssessmentDisclaimer()); ?></p></div></div>
    <?php include dirname(__DIR__) . '/../includes/footer.php';
}
