<?php
require_once __DIR__ . '/lib/hr_evaluation_service.php';
if (!defined('HR_EVALUATION_BUILD_PAGE')) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    redirectTo('evaluation-builder.php' . ($query !== '' ? '?' . $query : ''));
}
$currentAdmin = adminGuard('admin');
$pageTitle = 'ساخت ارزیابی';
$message = '';
$error = '';

try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
    hrSyncAssessmentCatalogToForms($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'HR evaluation settings bootstrap failed: ' . $e->getMessage());
    return;
}

$formTypes = hrEvaluationFormTypes();
$inputTypes = hrEvaluationInputTypes();
$periodTypes = hrEvaluationPeriodTypes();
$periodStatuses = hrEvaluationStatuses();
$visibilityLevels = hrVisibilityLevels();
$assessmentCategories = hrAssessmentCategories();
$assessmentScoringMethods = hrAssessmentScoringMethods();
$testAnswerTypes = hrTestAnswerTypes();
$roles = ['' => 'همه نقش ها', 'employee' => 'Employee', 'manager' => 'Manager', 'admin' => 'Admin'];

function hrRedirectSettings(string $suffix = ''): void {
    redirectTo('evaluation-builder.php' . ($suffix !== '' ? '?' . $suffix : ''));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = (string)($_POST['settings_action'] ?? '');

        if ($action === 'seed_restaurant_hr_tests') {
            $seedResult = hrSeedRestaurantProfessionalTests($db, (int)$currentAdmin['id']);
            hrSyncAssessmentCatalogToForms($db);
            hrRedirectSettings('seeded=1&tests=' . urlencode((string)$seedResult['tests']) . '&questions=' . urlencode((string)$seedResult['questions']));
        }

        if ($action === 'save_hr_settings') {
            $value = isset($_POST['hr_allow_self_evaluation']) ? '1' : '0';
            $db->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, category, is_public) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), category=VALUES(category)')
                ->execute(['hr_allow_self_evaluation', $value, 'boolean', 'hr']);
            $weights = [
                'manager' => max(0, (float)($_POST['weight_manager'] ?? 35)),
                'peer' => max(0, (float)($_POST['weight_peer'] ?? 25)),
                'attendance' => max(0, (float)($_POST['weight_attendance'] ?? 20)),
                'department_kpi' => max(0, (float)($_POST['weight_department_kpi'] ?? 20)),
            ];
            if (array_sum($weights) <= 0) {
                throw new RuntimeException('مجموع وزن های امتیاز نهایی باید بیشتر از صفر باشد.');
            }
            $db->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, category, is_public) VALUES (?,?,?,?,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), category=VALUES(category)')
                ->execute(['hr_performance_source_weights', hrJsonEncode($weights), 'json', 'hr']);
            hrRedirectSettings('saved=1');
        }

        if ($action === 'save_category') {
            $id = (int)($_POST['id'] ?? 0);
            $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['code'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            if ($code === '' || $title === '') {
                throw new RuntimeException('کد و عنوان دسته الزامی است.');
            }
            $formType = isset($formTypes[$_POST['form_type'] ?? 'employee_performance']) ? (string)$_POST['form_type'] : 'employee_performance';
            $data = [
                'code' => $code,
                'title' => $title,
                'form_type' => $formType,
                'allow_self_evaluation' => isset($_POST['allow_self_evaluation']) ? 1 : 0,
                'prevent_duplicate_responses' => isset($_POST['prevent_duplicate_responses']) ? 1 : 0,
                'manual_result_entry' => isset($_POST['manual_result_entry']) ? 1 : 0,
                'external_link' => trim((string)($_POST['external_link'] ?? '')) ?: null,
                'age_guidance' => trim((string)($_POST['age_guidance'] ?? '')) ?: null,
                'question_count' => ($_POST['question_count'] ?? '') === '' ? null : max(0, (int)$_POST['question_count']),
                'intended_use' => trim((string)($_POST['intended_use'] ?? '')) ?: null,
                'description' => trim((string)($_POST['description'] ?? '')) ?: null,
                'applicable_role' => trim((string)($_POST['applicable_role'] ?? '')) ?: null,
                'department' => trim((string)($_POST['department'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($id > 0) {
                $data['id'] = $id;
                $db->prepare('UPDATE hr_evaluation_categories SET code=:code,title=:title,form_type=:form_type,allow_self_evaluation=:allow_self_evaluation,prevent_duplicate_responses=:prevent_duplicate_responses,manual_result_entry=:manual_result_entry,external_link=:external_link,age_guidance=:age_guidance,question_count=:question_count,intended_use=:intended_use,description=:description,applicable_role=:applicable_role,department=:department,is_active=:is_active,sort_order=:sort_order WHERE id=:id')
                    ->execute($data);
            } else {
                $db->prepare('INSERT INTO hr_evaluation_categories (code,title,form_type,allow_self_evaluation,prevent_duplicate_responses,manual_result_entry,external_link,age_guidance,question_count,intended_use,description,applicable_role,department,is_active,sort_order) VALUES (:code,:title,:form_type,:allow_self_evaluation,:prevent_duplicate_responses,:manual_result_entry,:external_link,:age_guidance,:question_count,:intended_use,:description,:applicable_role,:department,:is_active,:sort_order)')
                    ->execute($data);
            }
            hrRedirectSettings('saved=1');
        }

        if ($action === 'save_criterion') {
            $id = (int)($_POST['id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['code'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $inputType = (string)($_POST['input_type'] ?? 'numeric');
            if (!$categoryId || $code === '' || $title === '' || !isset($inputTypes[$inputType])) {
                throw new RuntimeException('دسته، کد، عنوان و نوع ورودی معیار معتبر نیست.');
            }
            $data = [
                'category_id' => $categoryId,
                'code' => $code,
                'title' => $title,
                'description' => trim((string)($_POST['description'] ?? '')) ?: null,
                'input_type' => $inputType,
                'options_json' => hrNormalizeCriterionOptions((string)($_POST['options_json'] ?? '')) ?: null,
                'weight' => hrClampScore($_POST['weight'] ?? 0, 0, 1000),
                'max_score' => max(1, hrClampScore($_POST['max_score'] ?? 100, 1, 1000)),
                'include_in_score' => isset($_POST['include_in_score']) ? 1 : 0,
                'visibility' => isset($visibilityLevels[$_POST['visibility'] ?? 'manager']) ? (string)$_POST['visibility'] : 'manager',
                'applicable_role' => trim((string)($_POST['applicable_role'] ?? '')) ?: null,
                'department' => trim((string)($_POST['department'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($id > 0) {
                $data['id'] = $id;
                $db->prepare('UPDATE hr_evaluation_criteria SET category_id=:category_id,code=:code,title=:title,description=:description,input_type=:input_type,options_json=:options_json,weight=:weight,max_score=:max_score,include_in_score=:include_in_score,visibility=:visibility,applicable_role=:applicable_role,department=:department,is_active=:is_active,sort_order=:sort_order WHERE id=:id')
                    ->execute($data);
            } else {
                $db->prepare('INSERT INTO hr_evaluation_criteria (category_id,code,title,description,input_type,options_json,weight,max_score,include_in_score,visibility,applicable_role,department,is_active,sort_order) VALUES (:category_id,:code,:title,:description,:input_type,:options_json,:weight,:max_score,:include_in_score,:visibility,:applicable_role,:department,:is_active,:sort_order)')
                    ->execute($data);
            }
            hrRedirectSettings('saved=1');
        }

        if ($action === 'save_period') {
            $id = (int)($_POST['id'] ?? 0);
            $type = isset($periodTypes[$_POST['period_type'] ?? 'monthly']) ? (string)$_POST['period_type'] : 'monthly';
            $status = isset($periodStatuses[$_POST['status'] ?? 'draft']) ? (string)$_POST['status'] : 'draft';
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('عنوان دوره الزامی است.');
            }
            $startDate = parsePersianDate($_POST['start_date'] ?? '', false);
            $endDate = parsePersianDate($_POST['end_date'] ?? '', false);
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                throw new RuntimeException('تاریخ شروع نباید بعد از تاریخ پایان باشد.');
            }
            $data = [
                'title' => $title,
                'period_type' => $type,
                'period_key' => trim((string)($_POST['period_key'] ?? '')) ?: null,
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
                'status' => $status,
                'visibility' => isset($visibilityLevels[$_POST['visibility'] ?? 'manager']) ? (string)$_POST['visibility'] : 'manager',
                'description' => trim((string)($_POST['description'] ?? '')) ?: null,
                'created_by' => (int)$currentAdmin['id'],
            ];
            if ($id > 0) {
                $data['id'] = $id;
                $db->prepare('UPDATE hr_evaluation_periods SET title=:title,period_type=:period_type,period_key=:period_key,start_date=:start_date,end_date=:end_date,status=:status,visibility=:visibility,description=:description WHERE id=:id')
                    ->execute(array_diff_key($data, ['created_by' => true]));
            } else {
                $db->prepare('INSERT INTO hr_evaluation_periods (title,period_type,period_key,start_date,end_date,status,visibility,description,created_by) VALUES (:title,:period_type,:period_key,:start_date,:end_date,:status,:visibility,:description,:created_by)')
                    ->execute($data);
            }
            hrRedirectSettings('saved=1');
        }

        if ($action === 'save_assessment_test') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $testCode = hrNormalizeAssessmentCode((string)($_POST['test_code'] ?? ''), $title);
            $category = isset($assessmentCategories[$_POST['category'] ?? 'other']) ? (string)$_POST['category'] : 'other';
            $scoringMethod = isset($assessmentScoringMethods[$_POST['scoring_method_type'] ?? 'manual']) ? (string)$_POST['scoring_method_type'] : 'manual';
            $analysisType = in_array((string)($_POST['analysis_type'] ?? 'positive'), ['positive','risk'], true) ? (string)$_POST['analysis_type'] : 'positive';
            if ($title === '') {
                throw new RuntimeException('عنوان آزمون الزامی است.');
            }
            $data = [
                'title' => $title,
                'test_code' => $testCode,
                'category' => $category,
                'age_guidance' => trim((string)($_POST['age_guidance'] ?? '')) ?: null,
                'question_count' => ($_POST['question_count'] ?? '') === '' ? null : max(0, (int)$_POST['question_count']),
                'description' => trim((string)($_POST['description'] ?? '')) ?: null,
                'external_link' => trim((string)($_POST['external_link'] ?? '')) ?: null,
                'source_url' => trim((string)($_POST['source_url'] ?? '')) ?: null,
                'source_license' => trim((string)($_POST['source_license'] ?? '')) ?: null,
                'scoring_method_type' => $scoringMethod,
                'analysis_type' => $analysisType,
                'intended_use' => trim((string)($_POST['intended_use'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($id > 0) {
                $data['id'] = $id;
                $db->prepare('UPDATE hr_assessment_tests SET title=:title,test_code=:test_code,category=:category,age_guidance=:age_guidance,question_count=:question_count,description=:description,external_link=:external_link,source_url=:source_url,source_license=:source_license,scoring_method_type=:scoring_method_type,analysis_type=:analysis_type,intended_use=:intended_use,is_active=:is_active,sort_order=:sort_order,updated_by=:updated_by WHERE id=:id')
                    ->execute($data + ['updated_by'=>(int)$currentAdmin['id']]);
            } else {
                $db->prepare('INSERT INTO hr_assessment_tests (title,test_code,category,age_guidance,question_count,description,external_link,source_url,source_license,scoring_method_type,analysis_type,intended_use,is_active,sort_order,created_by,updated_by) VALUES (:title,:test_code,:category,:age_guidance,:question_count,:description,:external_link,:source_url,:source_license,:scoring_method_type,:analysis_type,:intended_use,:is_active,:sort_order,:created_by,:updated_by)')
                    ->execute($data + ['created_by'=>(int)$currentAdmin['id'],'updated_by'=>(int)$currentAdmin['id']]);
                $id = (int)$db->lastInsertId();
            }
            hrTestAudit($db, 'save', 'test', $id, ['test_code'=>$testCode], (int)$currentAdmin['id']);
            hrSyncAssessmentCatalogToForms($db);
            hrRedirectSettings('saved=1');
        }

        if ($action === 'import_assessment_catalog') {
            $url = trim((string)($_POST['catalog_url'] ?? ''));
            if ($url === '') {
                throw new RuntimeException('آدرس JSON کاتالوگ الزامی است.');
            }
            $categoryId = hrImportAssessmentCatalogFromUrl($db, $url);
            hrRedirectSettings('imported=1&edit_category=' . urlencode((string)$categoryId));
        }

        if ($action === 'save_test_dimension') {
            $testId = (int)($_POST['test_id'] ?? 0);
            $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['code'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            if (!$testId || $code === '' || $title === '') {
                throw new RuntimeException('آزمون، کد و عنوان بعد الزامی است.');
            }
            $db->prepare('INSERT INTO hr_test_dimensions (test_id,code,title,description,positive_label,negative_label,sort_order) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),positive_label=VALUES(positive_label),negative_label=VALUES(negative_label),sort_order=VALUES(sort_order)')
                ->execute([
                    $testId,
                    $code,
                    $title,
                    trim((string)($_POST['description'] ?? '')) ?: null,
                    trim((string)($_POST['positive_label'] ?? '')) ?: null,
                    trim((string)($_POST['negative_label'] ?? '')) ?: null,
                    (int)($_POST['sort_order'] ?? 0),
                ]);
            $dimensionAuditStmt=$db->prepare('SELECT id FROM hr_test_dimensions WHERE test_id=? AND code=? LIMIT 1');
            $dimensionAuditStmt->execute([$testId,$code]);
            hrTestAudit($db, 'save', 'dimension', (int)$dimensionAuditStmt->fetchColumn(), ['test_id'=>$testId], (int)$currentAdmin['id']);
            hrRedirectSettings('saved=1&test_id=' . urlencode((string)$testId));
        }

        if ($action === 'save_test_question') {
            $testId = (int)($_POST['test_id'] ?? 0);
            $dimensionId = (int)($_POST['dimension_id'] ?? 0);
            $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['code'] ?? ''));
            $text = trim((string)($_POST['question_text'] ?? ''));
            $answerType = isset($testAnswerTypes[$_POST['answer_type'] ?? 'scale_5']) ? (string)$_POST['answer_type'] : 'scale_5';
            $direction = in_array((string)($_POST['scoring_direction'] ?? 'positive'), ['positive', 'negative'], true) ? (string)$_POST['scoring_direction'] : 'positive';
            if (!$testId || $code === '' || $text === '') {
                throw new RuntimeException('آزمون، کد و متن سوال الزامی است.');
            }
            $db->prepare('INSERT INTO hr_test_questions (test_id,dimension_id,code,question_text,answer_type,question_type,options_json,weight,scoring_direction,score_direction,is_reverse_scored,is_required,is_critical,role_visibility,is_active,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE dimension_id=VALUES(dimension_id),question_text=VALUES(question_text),answer_type=VALUES(answer_type),question_type=VALUES(question_type),options_json=VALUES(options_json),weight=VALUES(weight),scoring_direction=VALUES(scoring_direction),score_direction=VALUES(score_direction),is_reverse_scored=VALUES(is_reverse_scored),is_required=VALUES(is_required),is_critical=VALUES(is_critical),role_visibility=VALUES(role_visibility),is_active=VALUES(is_active),status=VALUES(status),sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
                ->execute([
                    $testId,
                    $dimensionId ?: null,
                    $code,
                    $text,
                    $answerType,
                    $answerType,
                    hrNormalizeCriterionOptions((string)($_POST['options_json'] ?? '')) ?: null,
                    max(0, (float)($_POST['weight'] ?? 1)),
                    $direction,
                    $direction,
                    $direction === 'negative' ? 1 : 0,
                    isset($_POST['is_required']) ? 1 : 0,
                    isset($_POST['is_critical']) ? 1 : 0,
                    trim((string)($_POST['role_visibility'] ?? '')) ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    isset($_POST['is_active']) ? 'active' : 'inactive',
                    (int)($_POST['sort_order'] ?? 0),
                    (int)$currentAdmin['id'],
                    (int)$currentAdmin['id'],
                ]);
            $questionIdStmt=$db->prepare('SELECT id FROM hr_test_questions WHERE test_id=? AND code=? LIMIT 1');
            $questionIdStmt->execute([$testId,$code]);
            hrTestAudit($db, 'save', 'question', (int)$questionIdStmt->fetchColumn(), ['test_id'=>$testId], (int)$currentAdmin['id']);
            hrRedirectSettings('saved=1&test_id=' . urlencode((string)$testId));
        }
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    safeAdminLog('HR evaluation settings save failed: ' . $e->getMessage());
    $error = 'ذخیره انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
}

if (isset($_GET['saved'])) {
    $message = 'تنظیمات ذخیره شد.';
}
if (isset($_GET['imported'])) {
    $message = 'کاتالوگ آزمون با رعایت محدودیت کپی رایت وارد و به فرم ارزیابی تبدیل شد.';
}
if (isset($_GET['seeded'])) {
    $message = 'Seed حرفه‌ای با موفقیت همگام شد: ' . (int)($_GET['tests'] ?? 0) . ' آزمون و ' . (int)($_GET['questions'] ?? 0) . ' سؤال.';
}

$categories = hrFetchCategories($db);
$criteria = hrFetchCriteria($db);
$periods = hrFetchPeriods($db);
$assessmentTests = hrFetchAssessmentTests($db);
$selectedTestId = (int)($_GET['test_id'] ?? ($assessmentTests[0]['id'] ?? 0));
$selectedTestDimensions = $selectedTestId ? hrFetchTestDimensions($db, $selectedTestId) : [];
$selectedTestQuestions = $selectedTestId ? hrFetchTestQuestions($db, $selectedTestId) : [];
$allowSelf = (bool)hrSetting($db, 'hr_allow_self_evaluation', false);
$sourceWeights = hrPerformanceSourceWeights($db);
$editCategory = null;
$editCriterion = null;
$editPeriod = null;
$editAssessmentTest = null;

if (($_GET['edit_category'] ?? '') !== '') {
    $stmt = $db->prepare('SELECT * FROM hr_evaluation_categories WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_category']]);
    $editCategory = $stmt->fetch() ?: null;
}
if (($_GET['edit_criterion'] ?? '') !== '') {
    $stmt = $db->prepare('SELECT * FROM hr_evaluation_criteria WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_criterion']]);
    $editCriterion = $stmt->fetch() ?: null;
}
if (($_GET['edit_period'] ?? '') !== '') {
    $editPeriod = hrFindPeriod($db, (int)$_GET['edit_period']);
}
if (($_GET['edit_test'] ?? '') !== '') {
    $stmt = $db->prepare('SELECT * FROM hr_assessment_tests WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_test']]);
    $editAssessmentTest = $stmt->fetch() ?: null;
}

$weightSums = [];
foreach ($criteria as $criterion) {
    if ((int)$criterion['is_active'] === 1 && (int)$criterion['include_in_score'] === 1) {
        $weightSums[(int)$criterion['category_id']] = ($weightSums[(int)$criterion['category_id']] ?? 0) + (float)$criterion['weight'];
    }
}

include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>ساخت، ارزیابی و مشاهده</h2>
        <div>
            <a class="btn btn-sm btn-primary" href="employee-evaluations.php">ارزیابی</a>
            <a class="btn btn-sm" href="employee-performance.php">مشاهده</a>
        </div>
    </div>
    <div class="card-body text-muted">
        این صفحه تنها محل ساخت و پیکربندی فرم های ارزیابی، دوره ها، بخش ها، معیارها، سوال ها، گزینه ها، وزن ها، نوع ورودی، وضعیت و ترتیب نمایش است. آزمون های سازمانی و شغلی نیز همین جا به عنوان فرم ارزیابی ساخته می شوند.
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>قواعد عمومی ارزیابی</h2></div>
    <div class="card-body">
        <form method="post" class="admin-filter">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="save_hr_settings">
            <label><input type="checkbox" name="hr_allow_self_evaluation" value="1" <?php echo $allowSelf ? 'checked' : ''; ?>> اجازه خودارزیابی</label>
            <input class="form-control" type="number" step="0.01" min="0" name="weight_manager" value="<?php echo h($sourceWeights['manager'] ?? 35); ?>" placeholder="وزن مدیر">
            <input class="form-control" type="number" step="0.01" min="0" name="weight_peer" value="<?php echo h($sourceWeights['peer'] ?? 25); ?>" placeholder="وزن همکاران">
            <input class="form-control" type="number" step="0.01" min="0" name="weight_attendance" value="<?php echo h($sourceWeights['attendance'] ?? 20); ?>" placeholder="وزن حضور">
            <input class="form-control" type="number" step="0.01" min="0" name="weight_department_kpi" value="<?php echo h($sourceWeights['department_kpi'] ?? 20); ?>" placeholder="وزن KPI">
            <button class="btn btn-success" type="submit">ذخیره قواعد</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?php echo $editCategory ? 'ویرایش فرم / بخش ارزیابی' : 'ایجاد فرم / بخش ارزیابی'; ?></h2></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="save_category">
            <input type="hidden" name="id" value="<?php echo h($editCategory['id'] ?? 0); ?>">
            <div class="admin-filter">
                <input class="form-control" name="code" placeholder="کد انگلیسی" required value="<?php echo h($editCategory['code'] ?? ''); ?>">
                <input class="form-control" name="title" placeholder="عنوان فرم / بخش" required value="<?php echo h($editCategory['title'] ?? ''); ?>">
                <select class="form-control" name="form_type"><?php foreach ($formTypes as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editCategory['form_type'] ?? 'employee_performance') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <select class="form-control" name="applicable_role"><?php foreach ($roles as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editCategory['applicable_role'] ?? '') === (string)$key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <input class="form-control" name="department" placeholder="دپارتمان اختیاری" value="<?php echo h($editCategory['department'] ?? ''); ?>">
                <input class="form-control" type="url" name="external_link" placeholder="لینک خارجی آزمون" value="<?php echo h($editCategory['external_link'] ?? ''); ?>">
                <input class="form-control" name="age_guidance" placeholder="راهنمای سن" value="<?php echo h($editCategory['age_guidance'] ?? ''); ?>">
                <input class="form-control" type="number" min="0" name="question_count" placeholder="تعداد سوال" value="<?php echo h($editCategory['question_count'] ?? ''); ?>">
                <input class="form-control" name="intended_use" placeholder="کاربرد آزمون / فرم" value="<?php echo h($editCategory['intended_use'] ?? ''); ?>">
                <input class="form-control" type="number" name="sort_order" placeholder="ترتیب" value="<?php echo h($editCategory['sort_order'] ?? 0); ?>">
                <label><input type="checkbox" name="allow_self_evaluation" value="1" <?php echo ((int)($editCategory['allow_self_evaluation'] ?? 0) === 1) ? 'checked' : ''; ?>> اجازه خودارزیابی</label>
                <label><input type="checkbox" name="prevent_duplicate_responses" value="1" <?php echo ((int)($editCategory['prevent_duplicate_responses'] ?? 1) === 1) ? 'checked' : ''; ?>> کنترل پاسخ تکراری</label>
                <label><input type="checkbox" name="manual_result_entry" value="1" <?php echo ((int)($editCategory['manual_result_entry'] ?? 0) === 1) ? 'checked' : ''; ?>> ورود نتیجه دستی</label>
                <label><input type="checkbox" name="is_active" value="1" <?php echo ((int)($editCategory['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>> فعال</label>
            </div>
            <textarea class="form-control" name="description" placeholder="توضیح"><?php echo h($editCategory['description'] ?? ''); ?></textarea>
            <div class="mt-3"><button class="btn btn-success">ذخیره فرم</button><?php if ($editCategory): ?> <a class="btn" href="evaluation-builder.php">لغو</a><?php endif; ?></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>فرم ها / بخش ها و مجموع وزن فعال</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>عنوان</th><th>نوع</th><th>کد</th><th>نقش/دپارتمان</th><th>وزن فعال</th><th>کاتالوگ</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($categories as $category): $sum = $weightSums[(int)$category['id']] ?? 0; ?>
            <tr>
                <td><?php echo h($category['title']); ?></td>
                <td><?php echo h($formTypes[$category['form_type'] ?? 'employee_performance'] ?? ($category['form_type'] ?? '-')); ?></td>
                <td><?php echo h($category['code']); ?></td>
                <td><?php echo h(trim(($category['applicable_role'] ?: 'همه') . ' / ' . ($category['department'] ?: 'همه'))); ?></td>
                <td><span class="badge <?php echo abs((float)$sum - 100.0) < 0.01 ? 'badge-success' : 'badge-warning'; ?>"><?php echo h($sum); ?>%</span></td>
                <td><?php echo h(trim((string)($category['question_count'] ?? '') . ' ' . (string)($category['age_guidance'] ?? '')) ?: '-'); ?> <?php echo !empty($category['external_link']) ? '<a href="' . h($category['external_link']) . '" target="_blank" rel="noopener">لینک</a>' : ''; ?></td>
                <td><?php echo (int)$category['is_active'] === 1 ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-danger">غیرفعال</span>'; ?></td>
                <td><a class="btn btn-sm btn-primary" href="?edit_category=<?php echo h($category['id']); ?>">ویرایش</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?><tr><td colspan="8" class="text-center text-muted">فرمی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
        <p class="text-muted">برای دسته هایی که در امتیاز نهایی استفاده می شوند، مجموع وزن معیارهای فعال بهتر است 100 باشد. محاسبه مرکزی وزن ها را نرمال می کند، اما این هشدار برای کنترل تنظیمات نمایش داده می شود.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?php echo $editAssessmentTest ? 'ویرایش کاتالوگ آزمون' : 'کاتالوگ آزمون های سازمانی'; ?></h2></div>
    <div class="card-body">
        <form method="post" class="admin-filter" onsubmit="return confirm('Seed حرفه‌ای با کدهای پایدار همگام شود؟ داده‌ای حذف نخواهد شد.');">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="seed_restaurant_hr_tests">
            <button class="btn btn-primary" type="submit">همگام‌سازی ۱۴ آزمون حرفه‌ای رستوران</button>
            <span class="text-muted">عملیات idempotent است؛ رکوردهای موجود حذف نمی‌شوند.</span>
        </form>
        <form method="post">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="save_assessment_test">
            <input type="hidden" name="id" value="<?php echo h($editAssessmentTest['id'] ?? 0); ?>">
            <div class="admin-filter">
                <input class="form-control" name="title" required placeholder="عنوان آزمون مثل DISC" value="<?php echo h($editAssessmentTest['title'] ?? ''); ?>">
                <input class="form-control" name="test_code" placeholder="کد آزمون" value="<?php echo h($editAssessmentTest['test_code'] ?? ''); ?>">
                <select class="form-control" name="category"><?php foreach ($assessmentCategories as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editAssessmentTest['category'] ?? 'other') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <select class="form-control" name="scoring_method_type"><?php foreach ($assessmentScoringMethods as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editAssessmentTest['scoring_method_type'] ?? 'manual') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <select class="form-control" name="analysis_type"><option value="positive" <?php echo ($editAssessmentTest['analysis_type'] ?? 'positive') === 'positive' ? 'selected' : ''; ?>>مثبت‌محور</option><option value="risk" <?php echo ($editAssessmentTest['analysis_type'] ?? '') === 'risk' ? 'selected' : ''; ?>>ریسک‌محور</option></select>
                <input class="form-control" name="age_guidance" placeholder="راهنمای سن" value="<?php echo h($editAssessmentTest['age_guidance'] ?? ''); ?>">
                <input class="form-control" type="number" min="0" name="question_count" placeholder="تعداد سوال" value="<?php echo h($editAssessmentTest['question_count'] ?? ''); ?>">
                <input class="form-control" name="intended_use" placeholder="کاربرد: استخدام / توسعه / آموزش / HR insight" value="<?php echo h($editAssessmentTest['intended_use'] ?? ''); ?>">
                <input class="form-control" type="url" name="external_link" placeholder="لینک مرجع خارجی" value="<?php echo h($editAssessmentTest['external_link'] ?? ''); ?>">
                <input class="form-control" type="url" name="source_url" placeholder="آدرس منبع عمومی / GitHub" value="<?php echo h($editAssessmentTest['source_url'] ?? ''); ?>">
                <input class="form-control" name="source_license" placeholder="مجوز مثل MIT / CC0 / CC-BY" value="<?php echo h($editAssessmentTest['source_license'] ?? ''); ?>">
                <input class="form-control" type="number" name="sort_order" placeholder="ترتیب" value="<?php echo h($editAssessmentTest['sort_order'] ?? 0); ?>">
                <label><input type="checkbox" name="is_active" value="1" <?php echo ((int)($editAssessmentTest['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>> فعال</label>
            </div>
            <textarea class="form-control" name="description" placeholder="توضیح آزمون، بدون ادعای اعتبار بالینی"><?php echo h($editAssessmentTest['description'] ?? ''); ?></textarea>
            <div class="mt-3"><button class="btn btn-success">ذخیره آزمون در کاتالوگ</button><?php if ($editAssessmentTest): ?> <a class="btn" href="evaluation-builder.php">لغو</a><?php endif; ?></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>وارد کردن ساختار آزمون از GitHub / JSON عمومی</h2></div>
    <div class="card-body">
        <form method="post" class="admin-filter">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="import_assessment_catalog">
            <input class="form-control" type="url" name="catalog_url" required placeholder="https://raw.githubusercontent.com/.../test-schema.json">
            <button class="btn btn-primary">وارد کردن ساختار امن</button>
        </form>
        <p class="text-muted">فقط JSON عمومی با مجوز امن پذیرفته می شود. متن سوال های دارای کپی رایت ذخیره نمی شود و فقط ابعاد، گزینه ها، وزن ها و متادیتا به فرم داخلی تبدیل می شوند.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>فهرست کاتالوگ آزمون ها</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>آزمون</th><th>دسته</th><th>روش امتیازدهی</th><th>کاربرد</th><th>مجوز / منبع</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($assessmentTests as $test): ?>
            <tr>
                <td><?php echo h($test['title']); ?><br><small class="text-muted"><?php echo h($test['test_code']); ?></small></td>
                <td><?php echo h($assessmentCategories[$test['category'] ?? 'other'] ?? ($test['category'] ?? '-')); ?></td>
                <td><?php echo h($assessmentScoringMethods[$test['scoring_method_type'] ?? 'manual'] ?? ($test['scoring_method_type'] ?? '-')); ?></td>
                <td><?php echo h($test['intended_use'] ?: '-'); ?><br><small class="text-muted"><?php echo h(trim((string)($test['question_count'] ?? '') . ' ' . (string)($test['age_guidance'] ?? '')) ?: '-'); ?></small></td>
                <td><?php echo h($test['source_license'] ?: '-'); ?> <?php echo !empty($test['source_url']) ? '<a href="' . h($test['source_url']) . '" target="_blank" rel="noopener">منبع</a>' : ''; ?></td>
                <td><?php echo (int)$test['is_active'] === 1 ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-danger">غیرفعال</span>'; ?></td>
                <td><a class="btn btn-sm btn-primary" href="?edit_test=<?php echo h($test['id']); ?>">ویرایش</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$assessmentTests): ?><tr><td colspan="7" class="text-center text-muted">آزمونی در کاتالوگ ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>بانک سوال و ابعاد آزمون</h2></div>
    <div class="card-body">
        <form method="get" class="admin-filter">
            <select class="form-control" name="test_id">
                <?php foreach ($assessmentTests as $test): ?><option value="<?php echo h($test['id']); ?>" <?php echo $selectedTestId === (int)$test['id'] ? 'selected' : ''; ?>><?php echo h($test['title'] . ' / ' . $test['test_code']); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary">انتخاب آزمون</button>
        </form>
        <?php if (!$selectedTestId): ?>
            <p class="text-muted text-center">ابتدا یک آزمون در کاتالوگ بسازید.</p>
        <?php else: ?>
            <div class="admin-filter">
                <form method="post">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                    <input type="hidden" name="settings_action" value="save_test_dimension">
                    <input type="hidden" name="test_id" value="<?php echo h($selectedTestId); ?>">
                    <input class="form-control" name="code" required placeholder="کد بعد مثل D یا empathy">
                    <input class="form-control" name="title" required placeholder="عنوان بعد">
                    <input class="form-control" name="positive_label" placeholder="برچسب مثبت">
                    <input class="form-control" name="negative_label" placeholder="برچسب مقابل">
                    <input class="form-control" type="number" name="sort_order" placeholder="ترتیب">
                    <textarea class="form-control" name="description" placeholder="توضیح بعد"></textarea>
                    <button class="btn btn-success">افزودن بعد</button>
                </form>
                <form method="post">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                    <input type="hidden" name="settings_action" value="save_test_question">
                    <input type="hidden" name="test_id" value="<?php echo h($selectedTestId); ?>">
                    <select class="form-control" name="dimension_id"><option value="">بدون بعد</option><?php foreach ($selectedTestDimensions as $dimension): ?><option value="<?php echo h($dimension['id']); ?>"><?php echo h($dimension['title']); ?></option><?php endforeach; ?></select>
                    <input class="form-control" name="code" required placeholder="کد سوال">
                    <textarea class="form-control" name="question_text" required placeholder="متن سوال فارسی / سفارشی"></textarea>
                    <select class="form-control" name="answer_type"><?php foreach ($testAnswerTypes as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select>
                    <textarea class="form-control" name="options_json" placeholder="برای چندگزینه‌ای: هر خط «برچسب|امتیاز»"></textarea>
                    <input class="form-control" type="number" step="0.01" min="0" name="weight" value="1" placeholder="وزن">
                    <select class="form-control" name="scoring_direction"><option value="positive">مثبت</option><option value="negative">معکوس</option></select>
                    <input class="form-control" name="role_visibility" placeholder="نقش‌های مجاز، با کاما (اختیاری)">
                    <label><input type="checkbox" name="is_required" value="1" checked> الزامی</label>
                    <label><input type="checkbox" name="is_critical" value="1"> بحرانی</label>
                    <input class="form-control" type="number" name="sort_order" placeholder="ترتیب">
                    <label><input type="checkbox" name="is_active" value="1" checked> فعال</label>
                    <button class="btn btn-success">افزودن سوال</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table"><thead><tr><th>بعد</th><th>کد</th><th>عنوان</th><th>ترتیب</th></tr></thead><tbody>
                <?php foreach ($selectedTestDimensions as $dimension): ?><tr><td><?php echo h($dimension['title']); ?></td><td><?php echo h($dimension['code']); ?></td><td><?php echo h(trim((string)$dimension['positive_label'] . ' / ' . (string)$dimension['negative_label'], ' /')); ?></td><td><?php echo h($dimension['sort_order']); ?></td></tr><?php endforeach; ?>
                <?php if (!$selectedTestDimensions): ?><tr><td colspan="4" class="text-center text-muted">بعدی تعریف نشده است.</td></tr><?php endif; ?>
                </tbody></table>
                <table class="table"><thead><tr><th>بعد</th><th>سوال</th><th>نوع</th><th>وزن</th><th>جهت</th><th>وضعیت</th></tr></thead><tbody>
                <?php foreach ($selectedTestQuestions as $question): ?><tr><td><?php echo h($question['dimension_title'] ?: '-'); ?></td><td><?php echo h($question['question_text']); ?><br><small class="text-muted"><?php echo h($question['code']); ?></small></td><td><?php echo h($testAnswerTypes[$question['answer_type']] ?? $question['answer_type']); ?></td><td><?php echo h($question['weight']); ?></td><td><?php echo h($question['scoring_direction']); ?></td><td><?php echo (int)$question['is_active'] === 1 ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-danger">غیرفعال</span>'; ?></td></tr><?php endforeach; ?>
                <?php if (!$selectedTestQuestions): ?><tr><td colspan="6" class="text-center text-muted">سوالی تعریف نشده است.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?php echo $editCriterion ? 'ویرایش معیار / سوال' : 'ایجاد معیار / سوال'; ?></h2></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="save_criterion">
            <input type="hidden" name="id" value="<?php echo h($editCriterion['id'] ?? 0); ?>">
            <div class="admin-filter">
                <select class="form-control" name="category_id" required><option value="">فرم / بخش</option><?php foreach ($categories as $category): ?><option value="<?php echo h($category['id']); ?>" <?php echo (int)($editCriterion['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : ''; ?>><?php echo h($category['title']); ?></option><?php endforeach; ?></select>
                <input class="form-control" name="code" placeholder="کد انگلیسی" required value="<?php echo h($editCriterion['code'] ?? ''); ?>">
                <input class="form-control" name="title" placeholder="عنوان معیار / متن سوال" required value="<?php echo h($editCriterion['title'] ?? ''); ?>">
                <select class="form-control" name="input_type"><?php foreach ($inputTypes as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editCriterion['input_type'] ?? 'numeric') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <input class="form-control" type="number" step="0.01" min="0" name="weight" placeholder="وزن" value="<?php echo h($editCriterion['weight'] ?? 0); ?>">
                <input class="form-control" type="number" step="0.01" min="1" name="max_score" placeholder="حداکثر امتیاز" value="<?php echo h($editCriterion['max_score'] ?? 100); ?>">
                <select class="form-control" name="visibility"><?php foreach ($visibilityLevels as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editCriterion['visibility'] ?? 'manager') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <select class="form-control" name="applicable_role"><?php foreach ($roles as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editCriterion['applicable_role'] ?? '') === (string)$key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <input class="form-control" name="department" placeholder="دپارتمان اختیاری" value="<?php echo h($editCriterion['department'] ?? ''); ?>">
                <input class="form-control" type="number" name="sort_order" placeholder="ترتیب" value="<?php echo h($editCriterion['sort_order'] ?? 0); ?>">
                <label><input type="checkbox" name="include_in_score" value="1" <?php echo ((int)($editCriterion['include_in_score'] ?? 1) === 1) ? 'checked' : ''; ?>> محاسبه در امتیاز</label>
                <label><input type="checkbox" name="is_active" value="1" <?php echo ((int)($editCriterion['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>> فعال</label>
            </div>
            <textarea class="form-control" name="options_json" placeholder="گزینه های پاسخ برای چند گزینه ای: هر خط «برچسب|امتیاز»"><?php echo h($editCriterion['options_json'] ?? ''); ?></textarea>
            <textarea class="form-control mt-3" name="description" placeholder="توضیح معیار"><?php echo h($editCriterion['description'] ?? ''); ?></textarea>
            <div class="mt-3"><button class="btn btn-success">ذخیره معیار / سوال</button><?php if ($editCriterion): ?> <a class="btn" href="evaluation-builder.php">لغو</a><?php endif; ?></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>معیارها، سوال ها و گزینه ها</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>دسته</th><th>معیار</th><th>نوع</th><th>وزن</th><th>محدوده</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($criteria as $criterion): ?>
            <tr>
                <td><?php echo h($criterion['category_title']); ?></td>
                <td><?php echo h($criterion['title']); ?><br><small class="text-muted"><?php echo h($criterion['code']); ?></small></td>
                <td><?php echo h($inputTypes[$criterion['input_type']] ?? $criterion['input_type']); ?></td>
                <td><?php echo h($criterion['weight']); ?></td>
                <td><?php echo h(($criterion['applicable_role'] ?: 'همه نقش ها') . ' / ' . ($criterion['department'] ?: 'همه دپارتمان ها')); ?></td>
                <td><?php echo (int)$criterion['is_active'] === 1 ? '<span class="badge badge-success">فعال</span>' : '<span class="badge badge-danger">غیرفعال</span>'; ?> <?php echo (int)$criterion['include_in_score'] === 1 ? '<span class="badge badge-info">امتیازی</span>' : '<span class="badge badge-warning">غیرامتیازی</span>'; ?></td>
                <td><a class="btn btn-sm btn-primary" href="?edit_criterion=<?php echo h($criterion['id']); ?>">ویرایش</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$criteria): ?><tr><td colspan="7" class="text-center text-muted">معیاری ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?php echo $editPeriod ? 'ویرایش دوره ارزیابی' : 'ایجاد دوره ارزیابی'; ?></h2></div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="settings_action" value="save_period">
            <input type="hidden" name="id" value="<?php echo h($editPeriod['id'] ?? 0); ?>">
            <div class="admin-filter">
                <input class="form-control" name="title" required placeholder="عنوان دوره" value="<?php echo h($editPeriod['title'] ?? ''); ?>">
                <select class="form-control" name="period_type"><?php foreach ($periodTypes as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editPeriod['period_type'] ?? 'monthly') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <input class="form-control" name="period_key" placeholder="شناسه مثل 2026-06" value="<?php echo h($editPeriod['period_key'] ?? date('Y-m')); ?>">
                <input class="form-control" name="start_date" placeholder="شروع YYYY-MM-DD یا تاریخ شمسی" value="<?php echo h($editPeriod['start_date'] ?? ''); ?>">
                <input class="form-control" name="end_date" placeholder="پایان YYYY-MM-DD یا تاریخ شمسی" value="<?php echo h($editPeriod['end_date'] ?? ''); ?>">
                <select class="form-control" name="status"><?php foreach ($periodStatuses as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editPeriod['status'] ?? 'draft') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <select class="form-control" name="visibility"><?php foreach ($visibilityLevels as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($editPeriod['visibility'] ?? 'manager') === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
            </div>
            <textarea class="form-control" name="description" placeholder="توضیح دوره"><?php echo h($editPeriod['description'] ?? ''); ?></textarea>
            <div class="mt-3"><button class="btn btn-success">ذخیره دوره</button><?php if ($editPeriod): ?> <a class="btn" href="evaluation-builder.php">لغو</a><?php endif; ?></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>دوره های ارزیابی</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>عنوان</th><th>نوع</th><th>شناسه</th><th>بازه</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($periods as $period): ?>
            <tr>
                <td><?php echo h($period['title']); ?></td>
                <td><?php echo h($periodTypes[$period['period_type']] ?? $period['period_type']); ?></td>
                <td><?php echo h($period['period_key']); ?></td>
                <td><?php echo h(($period['start_date'] ?: '-') . ' تا ' . ($period['end_date'] ?: '-')); ?></td>
                <td><span class="badge badge-<?php echo h($period['status'] === 'active' ? 'success' : ($period['status'] === 'closed' ? 'warning' : ($period['status'] === 'archived' ? 'danger' : 'info'))); ?>"><?php echo h($periodStatuses[$period['status']] ?? $period['status']); ?></span></td>
                <td><a class="btn btn-sm btn-primary" href="?edit_period=<?php echo h($period['id']); ?>">ویرایش</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$periods): ?><tr><td colspan="6" class="text-center text-muted">دوره ای ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
