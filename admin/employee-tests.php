<?php
require_once __DIR__ . '/lib/hr_evaluation_service.php';

$currentAdmin = adminGuard('employee');
$pageTitle = 'آزمون های من';
$message = '';
$error = '';

try {
    ensureAdminSchema();
    $db = adminDb();
    hrEnsureEvaluationSchema($db);
} catch (Throwable $e) {
    adminRenderSafeError($pageTitle, 'Employee tests bootstrap failed: ' . $e->getMessage());
    return;
}

$employeeId = (int)$currentAdmin['id'];
$employee = $db->prepare('SELECT id, username, full_name, role, department FROM admins WHERE id = ? LIMIT 1');
$employee->execute([$employeeId]);
$employee = $employee->fetch() ?: $currentAdmin;
$assignments = hrFetchAssignedTests($db, $employee);
$assignmentId = (int)($_GET['assignment_id'] ?? $_POST['assignment_id'] ?? 0);
$selectedAssignment = null;
foreach ($assignments as $assignment) {
    if ((int)$assignment['id'] === $assignmentId) {
        $selectedAssignment = $assignment;
        break;
    }
}
if (!$selectedAssignment && $assignments) {
    $selectedAssignment = $assignments[0];
    $assignmentId = (int)$selectedAssignment['id'];
}

$questions = $selectedAssignment ? hrFetchTestQuestions($db, (int)$selectedAssignment['test_id'], true) : [];
$savedAnswers = [];
if ($selectedAssignment && !empty($selectedAssignment['response_id'])) {
    $stmt = $db->prepare('SELECT answers_json FROM hr_test_responses WHERE id = ? AND employee_id = ? LIMIT 1');
    $stmt->execute([(int)$selectedAssignment['response_id'], $employeeId]);
    $savedAnswers = hrJsonDecode($stmt->fetchColumn() ?: '');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        if (!$selectedAssignment) {
            throw new RuntimeException('آزمون انتخاب شده معتبر نیست.');
        }
        $answers = is_array($_POST['answer'] ?? null) ? $_POST['answer'] : [];
        $submit = (string)($_POST['test_action'] ?? 'save') === 'submit';
        hrSaveTestResponse($db, $employee, $assignmentId, $answers, $submit);
        redirectTo('employee-tests.php?' . ($submit ? 'submitted=1' : 'saved=1') . '&assignment_id=' . urlencode((string)$assignmentId));
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    safeAdminLog('Employee test response save failed: ' . $e->getMessage());
    $error = 'ذخیره پاسخ آزمون انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
}

if (isset($_GET['saved'])) {
    $message = 'پیشرفت آزمون ذخیره شد.';
}
if (isset($_GET['submitted'])) {
    $message = 'آزمون با موفقیت ثبت نهایی شد.';
}

include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>آزمون های اختصاص داده شده</h2></div>
    <div class="card-body table-responsive">
        <table class="table"><thead><tr><th>آزمون</th><th>مهلت</th><th>وضعیت</th><th>نتیجه</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($assignments as $assignment): ?>
            <tr>
                <td><?php echo h($assignment['title']); ?><br><small class="text-muted"><?php echo h($assignment['test_code']); ?></small></td>
                <td><?php echo h($assignment['due_date'] ?: '-'); ?></td>
                <td><?php echo h($assignment['response_status'] ?: 'در انتظار شروع'); ?></td>
                <td><?php echo h(trim((string)($assignment['normalized_score'] ?? '') . ' ' . (string)($assignment['profile_output'] ?? '')) ?: '-'); ?></td>
                <td><a class="btn btn-sm btn-primary" href="?assignment_id=<?php echo h($assignment['id']); ?>"><?php echo ($assignment['response_status'] ?? '') === 'in_progress' ? 'ادامه' : 'شروع'; ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$assignments): ?><tr><td colspan="5" class="text-center text-muted">آزمون فعالی برای شما اختصاص داده نشده است.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>

<?php if ($selectedAssignment): ?>
<div class="card">
    <div class="card-header">
        <h2><?php echo h($selectedAssignment['title']); ?></h2>
        <span class="badge badge-info"><?php echo h(count($questions)); ?> سوال</span>
    </div>
    <div class="card-body">
        <?php if (($selectedAssignment['response_status'] ?? '') === 'submitted' && !(int)$selectedAssignment['allow_retake'] && !(int)$selectedAssignment['test_allow_retake']): ?>
            <p class="text-muted text-center">این آزمون قبلا ثبت نهایی شده است.</p>
        <?php elseif (!$questions): ?>
            <p class="text-muted text-center">برای این آزمون هنوز سوال فعالی تعریف نشده است.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                <input type="hidden" name="assignment_id" value="<?php echo h($assignmentId); ?>">
                <?php foreach ($questions as $index => $question): $answer = $savedAnswers[(int)$question['id']] ?? ''; ?>
                    <div class="form-group">
                        <label><?php echo h(($index + 1) . '. ' . $question['question_text']); ?></label>
                        <?php if ($question['answer_type'] === 'text'): ?>
                            <textarea class="form-control" name="answer[<?php echo h($question['id']); ?>]"><?php echo h($answer); ?></textarea>
                        <?php else: $options = hrTestQuestionOptions($question); ?>
                            <select class="form-control" name="answer[<?php echo h($question['id']); ?>]" required>
                                <option value="">انتخاب پاسخ</option>
                                <?php foreach ($options as $optionIndex => $option): ?><option value="<?php echo h($optionIndex); ?>" <?php echo (string)$answer === (string)$optionIndex ? 'selected' : ''; ?>><?php echo h($option['label']); ?></option><?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if ($question['dimension_title']): ?><small class="text-muted"><?php echo h($question['dimension_title']); ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-primary" name="test_action" value="save">ذخیره پیشرفت</button>
                <button class="btn btn-success" name="test_action" value="submit" onclick="return confirm('ثبت نهایی شود؟')">ثبت نهایی</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
