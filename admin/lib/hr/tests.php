<?php

require_once dirname(__DIR__) . '/hr_evaluation_service.php';
require_once dirname(__DIR__, 3) . '/database/seeds/seed_key_organizational_tests.php';

function hrOrgTestsEnsureSchema(?PDO $db = null): void {
    $db = $db ?: adminDb();
    hrEnsureEvaluationSchema($db);

    foreach ([
        dirname(__DIR__, 3) . '/database/migrations/2026_06_30_professional_hr_tests.sql',
        dirname(__DIR__, 3) . '/database/migrations/2026_07_17_hr_organizational_tests.sql',
    ] as $migration) {
        if (!is_readable($migration)) {
            continue;
        }
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($migration)) ?: '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $db->exec($statement);
            } catch (Throwable $e) {
                safeAdminLog('Phase 7 organizational test schema statement failed: ' . $e->getMessage());
            }
        }
    }

    ensureTableColumns('hr_assessment_tests', [
        'retake_policy' => "enum('free','manager_approval_required') NOT NULL DEFAULT 'manager_approval_required' AFTER `allow_retake`",
        'show_disclaimer' => 'tinyint(1) NOT NULL DEFAULT 1 AFTER `retake_policy`',
        'created_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `show_disclaimer`',
        'updated_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `created_by`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_at`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
    ensureTableColumns('hr_test_assignments', [
        'target_type' => "varchar(40) NOT NULL DEFAULT 'employee' AFTER `test_id`",
        'target_id' => 'varchar(120) DEFAULT NULL AFTER `target_type`',
        'max_attempts' => 'int(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `allow_retake`',
        'show_result_to_employee' => 'tinyint(1) NOT NULL DEFAULT 1 AFTER `max_attempts`',
        'description' => 'text DEFAULT NULL AFTER `show_result_to_employee`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_at`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
    ensureTableColumns('hr_test_responses', [
        'attempt_id' => 'int(11) UNSIGNED DEFAULT NULL AFTER `assignment_id`',
        'deleted_at' => 'datetime DEFAULT NULL AFTER `updated_at`',
        'deleted_by' => 'int(11) UNSIGNED DEFAULT NULL AFTER `deleted_at`',
    ]);
}

function hrOrgTestsStart(string $title, string $role = 'manager'): array {
    $admin = adminGuard($role);
    $db = adminDb();
    hrOrgTestsEnsureSchema($db);
    $pageTitle = $title;
    return [$db, $admin, $pageTitle];
}

function hrOrgTestsRedirect(string $route, array $query = []): void {
    redirectTo($route . ($query ? '?' . http_build_query($query) : ''));
}

function hrOrgTestsAlert(string $message, string $type = 'info'): void {
    if ($message !== '') {
        echo '<div class="alert alert-' . h($type) . '">' . h($message) . '</div>';
    }
}

function hrOrgTestsSaveTest(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $title = trim((string)($input['title'] ?? ''));
    $code = hrNormalizeAssessmentCode((string)($input['test_code'] ?? ''), $title);
    if ($title === '') {
        throw new RuntimeException('عنوان آزمون الزامی است.');
    }
    $category = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['category'] ?? 'organizational_behavior')) ?: 'organizational_behavior';
    $retakePolicy = in_array((string)($input['retake_policy'] ?? 'manager_approval_required'), ['free', 'manager_approval_required'], true) ? (string)$input['retake_policy'] : 'manager_approval_required';
    $data = [
        'title' => $title,
        'test_code' => $code,
        'category' => $category,
        'description' => trim((string)($input['description'] ?? '')) ?: null,
        'intended_use' => 'ابزار آموزشی، مدیریتی و سازمانی ویژه پرسنل رستوران KEY',
        'allow_retake' => isset($input['allow_retake']) ? 1 : 0,
        'retake_policy' => $retakePolicy,
        'is_active' => isset($input['is_active']) ? 1 : 0,
        'sort_order' => (int)($input['sort_order'] ?? 0),
        'updated_by' => (int)$admin['id'],
    ];
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        $data['id'] = $id;
        $db->prepare('UPDATE hr_assessment_tests SET title=:title,test_code=:test_code,category=:category,description=:description,intended_use=:intended_use,time_limit_minutes=NULL,allow_retake=:allow_retake,retake_policy=:retake_policy,show_disclaimer=1,is_active=:is_active,sort_order=:sort_order,updated_by=:updated_by WHERE id=:id')
            ->execute($data);
    } else {
        $data['created_by'] = (int)$admin['id'];
        $db->prepare('INSERT INTO hr_assessment_tests (title,test_code,category,description,scoring_method_type,intended_use,time_limit_minutes,allow_retake,retake_policy,show_disclaimer,is_active,sort_order,created_by,updated_by) VALUES (:title,:test_code,:category,:description,"calculated",:intended_use,NULL,:allow_retake,:retake_policy,1,:is_active,:sort_order,:created_by,:updated_by) ON DUPLICATE KEY UPDATE title=VALUES(title),category=VALUES(category),description=VALUES(description),intended_use=VALUES(intended_use),time_limit_minutes=NULL,allow_retake=VALUES(allow_retake),retake_policy=VALUES(retake_policy),show_disclaimer=1,is_active=VALUES(is_active),sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
            ->execute($data);
        $stmt = $db->prepare('SELECT id FROM hr_assessment_tests WHERE test_code=? LIMIT 1');
        $stmt->execute([$code]);
        $id = (int)$stmt->fetchColumn();
    }
    hrTestAudit($db, 'save_test', 'test', $id, ['test_code' => $code], (int)$admin['id']);
    return $id;
}

function hrOrgTestsSaveDimension(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $testId = (int)($input['test_id'] ?? 0);
    $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['code'] ?? ''));
    $title = trim((string)($input['title'] ?? ''));
    if ($testId <= 0 || $code === '' || $title === '') {
        throw new RuntimeException('آزمون، کد و عنوان بعد الزامی است.');
    }
    $db->prepare('INSERT INTO hr_test_dimensions (test_id,code,title,description,positive_label,negative_label,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,"active",?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),positive_label=VALUES(positive_label),negative_label=VALUES(negative_label),status="active",sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
        ->execute([
            $testId,
            $code,
            $title,
            trim((string)($input['description'] ?? '')) ?: null,
            trim((string)($input['positive_label'] ?? '')) ?: null,
            trim((string)($input['negative_label'] ?? '')) ?: null,
            (int)($input['sort_order'] ?? 0),
            (int)$admin['id'],
            (int)$admin['id'],
        ]);
    $stmt = $db->prepare('SELECT id FROM hr_test_dimensions WHERE test_id=? AND code=? LIMIT 1');
    $stmt->execute([$testId, $code]);
    $id = (int)$stmt->fetchColumn();
    hrTestAudit($db, 'save_dimension', 'dimension', $id, ['test_id' => $testId], (int)$admin['id']);
    return $id;
}

function hrOrgTestsParseOptions(string $raw): array {
    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    $options = [];
    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        $options[] = ['label' => $parts[0], 'score' => isset($parts[1]) ? (float)$parts[1] : 0.0];
    }
    if (!$options) {
        $options = [
            ['label' => 'نیازمند آموزش', 'score' => 25],
            ['label' => 'قابل قبول', 'score' => 70],
            ['label' => 'حرفه‌ای و پایدار', 'score' => 100],
        ];
    }
    return $options;
}

function hrOrgTestsSaveQuestion(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $testId = (int)($input['test_id'] ?? 0);
    $dimensionId = (int)($input['dimension_id'] ?? 0);
    $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['code'] ?? ''));
    $text = trim((string)($input['question_text'] ?? ''));
    if ($testId <= 0 || $code === '' || $text === '') {
        throw new RuntimeException('آزمون، کد و متن سوال الزامی است.');
    }
    $options = hrOrgTestsParseOptions((string)($input['options_text'] ?? ''));
    $answerType = in_array((string)($input['answer_type'] ?? 'single_choice'), ['single_choice', 'multi_choice', 'scale_5', 'text'], true) ? (string)$input['answer_type'] : 'single_choice';
    $direction = in_array((string)($input['scoring_direction'] ?? 'positive'), ['positive', 'negative'], true) ? (string)$input['scoring_direction'] : 'positive';
    $db->prepare('INSERT INTO hr_test_questions (test_id,dimension_id,code,question_text,answer_type,question_type,options_json,weight,scoring_direction,score_direction,is_reverse_scored,is_required,is_critical,role_visibility,is_active,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,0,NULL,1,"active",?,?,?) ON DUPLICATE KEY UPDATE dimension_id=VALUES(dimension_id),question_text=VALUES(question_text),answer_type=VALUES(answer_type),question_type=VALUES(question_type),options_json=VALUES(options_json),weight=VALUES(weight),scoring_direction=VALUES(scoring_direction),score_direction=VALUES(score_direction),is_reverse_scored=VALUES(is_reverse_scored),is_active=1,status="active",sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
        ->execute([
            $testId,
            $dimensionId ?: null,
            $code,
            $text,
            $answerType,
            'organizational',
            hrJsonEncode($options),
            max(0, (float)($input['weight'] ?? 1)),
            $direction,
            $direction,
            $direction === 'negative' ? 1 : 0,
            (int)($input['sort_order'] ?? 0),
            (int)$admin['id'],
            (int)$admin['id'],
        ]);
    $stmt = $db->prepare('SELECT id FROM hr_test_questions WHERE test_id=? AND code=? LIMIT 1');
    $stmt->execute([$testId, $code]);
    $questionId = (int)$stmt->fetchColumn();
    foreach ($options as $index => $option) {
        $db->prepare('INSERT INTO hr_test_options (question_id,title,slug,score_value,is_correct,status,sort_order,created_by,updated_by) VALUES (?,?,?,?,?,"active",?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),score_value=VALUES(score_value),is_correct=VALUES(is_correct),status="active",sort_order=VALUES(sort_order),updated_by=VALUES(updated_by)')
            ->execute([$questionId, $option['label'], 'option_' . ($index + 1), (float)$option['score'], (float)$option['score'] >= 100 ? 1 : 0, ($index + 1) * 10, (int)$admin['id'], (int)$admin['id']]);
    }
    hrTestAudit($db, 'save_question', 'question', $questionId, ['test_id' => $testId], (int)$admin['id']);
    return $questionId;
}

function hrOrgTestsAssign(PDO $db, array $admin, array $input): int {
    requireValidCsrf();
    $testId = (int)($input['test_id'] ?? 0);
    $scopeType = in_array((string)($input['target_type'] ?? 'employee'), ['employee', 'role', 'department', 'all'], true) ? (string)$input['target_type'] : 'employee';
    $employeeId = $scopeType === 'employee' ? (int)($input['employee_id'] ?? 0) : 0;
    $department = $scopeType === 'department' ? trim((string)($input['department'] ?? '')) : '';
    $role = $scopeType === 'role' ? trim((string)($input['role'] ?? '')) : '';
    if ($testId <= 0 || ($scopeType === 'employee' && $employeeId <= 0) || ($scopeType === 'department' && $department === '') || ($scopeType === 'role' && $role === '')) {
        throw new RuntimeException('آزمون و محدوده تخصیص معتبر الزامی است.');
    }
    $targetId = $scopeType === 'employee' ? (string)$employeeId : ($scopeType === 'department' ? $department : ($scopeType === 'role' ? $role : 'all'));
    $dueDate = parsePersianDate($input['due_date'] ?? '', false) ?: null;
    $periodId = ($input['period_id'] ?? '') === '' ? null : (int)$input['period_id'];
    $db->prepare('INSERT INTO hr_test_assignments (test_id,target_type,target_id,employee_id,department,role,period_id,due_date,status,allow_retake,max_attempts,show_result_to_employee,description,assigned_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $testId,
            $scopeType,
            $targetId,
            $employeeId ?: null,
            $department ?: null,
            $role ?: null,
            $periodId,
            $dueDate,
            'active',
            isset($input['allow_retake']) ? 1 : 0,
            max(1, (int)($input['max_attempts'] ?? 1)),
            isset($input['show_result_to_employee']) ? 1 : 0,
            trim((string)($input['description'] ?? '')) ?: null,
            (int)$admin['id'],
        ]);
    $id = (int)$db->lastInsertId();
    hrTestAudit($db, 'assign_test', 'assignment', $id, ['test_id' => $testId, 'target_type' => $scopeType, 'target_id' => $targetId], (int)$admin['id']);
    return $id;
}

function hrOrgTestsRequestRetake(PDO $db, array $employee, int $assignmentId, string $note = ''): int {
    $stmt = $db->prepare('SELECT a.*, t.id AS canonical_test_id, t.retake_policy FROM hr_test_assignments a JOIN hr_assessment_tests t ON t.id=a.test_id WHERE a.id=? AND a.status="active" AND a.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch();
    if (!$assignment || !hrAssignmentAppliesToEmployee($assignment, $employee)) {
        throw new RuntimeException('آزمون انتخاب شده برای درخواست تکرار معتبر نیست.');
    }
    if ((int)($assignment['allow_retake'] ?? 0) !== 1) {
        throw new RuntimeException('برای این آزمون امکان درخواست تکرار فعال نیست.');
    }
    $status = (string)($assignment['retake_policy'] ?? '') === 'free' ? 'approved' : 'pending';
    $db->prepare('INSERT INTO hr_test_retake_requests (assignment_id,test_id,employee_id,request_note,status,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$assignmentId, (int)$assignment['test_id'], (int)$employee['id'], trim($note) ?: null, $status, $status === 'approved' ? (int)$employee['id'] : null, $status === 'approved' ? date('Y-m-d H:i:s') : null]);
    $id = (int)$db->lastInsertId();
    hrTestAudit($db, 'request_retake', 'retake_request', $id, ['assignment_id' => $assignmentId, 'status' => $status], (int)$employee['id']);
    return $id;
}

function hrOrgTestsReviewRetake(PDO $db, array $admin, array $input): void {
    requireValidCsrf();
    $id = (int)($input['id'] ?? 0);
    $status = in_array((string)($input['status'] ?? ''), ['approved', 'rejected'], true) ? (string)$input['status'] : '';
    if ($id <= 0 || $status === '') {
        throw new RuntimeException('درخواست آزمون مجدد معتبر نیست.');
    }
    $db->prepare('UPDATE hr_test_retake_requests SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status="pending"')
        ->execute([$status, trim((string)($input['review_note'] ?? '')) ?: null, (int)$admin['id'], $id]);
    hrTestAudit($db, 'review_retake', 'retake_request', $id, ['status' => $status], (int)$admin['id']);
}

function hrOrgTestsFetchEmployees(PDO $db, array $admin): array {
    $where = ['is_active = 1'];
    $params = [];
    if ((string)($admin['role'] ?? '') === 'manager') {
        $where[] = 'department = ?';
        $params[] = (string)($admin['department'] ?? '');
    }
    $stmt = $db->prepare('SELECT id, username, full_name, role, department FROM admins WHERE ' . implode(' AND ', $where) . ' ORDER BY department, full_name, username');
    $stmt->execute($params);
    return $stmt->fetchAll();
}
