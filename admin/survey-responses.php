<?php
require_once __DIR__ . '/lib/admin_schema.php';

$config = adminModuleDefinition('survey-responses');
if (!$config) {
    http_response_code(404);
    exit('Module not found.');
}

adminGuard($config['min_role'] ?? 'manager');
ensureAdminSchema();
adminEnsureModuleTables($config);
$db = adminDb();
$pageTitle = 'پاسخ‌های نظرسنجی';
$error = '';

function surveyResultsDecodeJson($value): array {
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function surveyResultsFieldsByKey($schema): array {
    $decoded = surveyResultsDecodeJson($schema);
    $labels = [];
    foreach (($decoded['fields'] ?? []) as $index => $field) {
        if (!is_array($field)) continue;
        $key = (string)($field['key'] ?? $field['id'] ?? 'field_' . ($index + 1));
        $labels[$key] = (string)($field['label'] ?? $field['label_fa'] ?? $key);
    }
    return $labels;
}

function surveyResultsDate($value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $timestamp = strtotime($value);
    return $timestamp ? date('Y/m/d H:i', $timestamp) : $value;
}

function surveyResultsCsv(array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="survey-responses-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['id', 'survey title', 'customer_name', 'customer_mobile', 'satisfaction_score', 'is_dissatisfied', 'submitted_at', 'response_data']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'] ?? '',
            $row['form_title'] ?? '',
            $row['customer_name'] ?? '',
            $row['customer_mobile_display'] ?? $row['customer_mobile'] ?? '',
            $row['satisfaction_score'] ?? '',
            $row['is_dissatisfied'] ?? '',
            $row['submitted_at'] ?? '',
            $row['response_data'] ?? '',
        ]);
    }
    exit;
}

function surveyResultsRows(PDO $db, array $filters): array {
    $where = [];
    $params = [];
    $mobileExpr = adminColumnExists('survey_responses', 'customer_phone')
        ? "COALESCE(NULLIF(sr.customer_mobile, ''), sr.customer_phone)"
        : 'sr.customer_mobile';

    if (($filters['form_id'] ?? '') !== '') {
        $where[] = 'sr.form_id = :form_id';
        $params['form_id'] = (int)$filters['form_id'];
    }
    if (($filters['date_from'] ?? '') !== '') {
        $where[] = 'sr.submitted_at >= :date_from';
        $params['date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if (($filters['date_to'] ?? '') !== '') {
        $where[] = 'sr.submitted_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    if (($filters['satisfaction_score'] ?? '') !== '') {
        $where[] = 'sr.satisfaction_score = :satisfaction_score';
        $params['satisfaction_score'] = (int)$filters['satisfaction_score'];
    }
    if (($filters['is_dissatisfied'] ?? '') !== '') {
        $where[] = 'sr.is_dissatisfied = :is_dissatisfied';
        $params['is_dissatisfied'] = (int)$filters['is_dissatisfied'];
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(' . $mobileExpr . ' LIKE :q OR sr.customer_name LIKE :q OR sr.response_data LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sql = "
        SELECT sr.*, {$mobileExpr} AS customer_mobile_display, df.form_title_fa AS form_title, df.form_schema
        FROM survey_responses sr
        LEFT JOIN dynamic_forms df ON sr.form_id = df.id
    ";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY sr.submitted_at DESC, sr.id DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$filters = [
    'form_id' => $_GET['form_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'satisfaction_score' => $_GET['satisfaction_score'] ?? '',
    'is_dissatisfied' => $_GET['is_dissatisfied'] ?? '',
    'q' => $_GET['q'] ?? '',
];

$rows = [];
$detail = null;
try {
    $rows = surveyResultsRows($db, $filters);
    if (($_GET['export'] ?? '') === 'csv') {
        surveyResultsCsv($rows);
    }
    if (isset($_GET['view']) && ctype_digit((string)$_GET['view'])) {
        $mobileExpr = adminColumnExists('survey_responses', 'customer_phone')
            ? "COALESCE(NULLIF(sr.customer_mobile, ''), sr.customer_phone)"
            : 'sr.customer_mobile';
        $stmt = $db->prepare("SELECT sr.*, {$mobileExpr} AS customer_mobile_display, df.form_title_fa AS form_title, df.form_schema FROM survey_responses sr LEFT JOIN dynamic_forms df ON sr.form_id = df.id WHERE sr.id = ? LIMIT 1");
        $stmt->execute([(int)$_GET['view']]);
        $detail = $stmt->fetch() ?: null;
    }
} catch (Throwable $e) {
    $error = 'داده‌ها قابل نمایش نیستند. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('Survey results failed: ' . $e->getMessage());
}

$forms = adminOptionRows('survey_form');

include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<?php if ($detail): ?>
    <?php $answers = surveyResultsDecodeJson($detail['response_data'] ?? ''); $labels = surveyResultsFieldsByKey($detail['form_schema'] ?? ''); ?>
    <div class="card">
        <div class="card-header">
            <h2>جزئیات پاسخ #<?php echo h($detail['id']); ?></h2>
            <a class="btn" href="survey-responses.php?<?php echo h(http_build_query($filters)); ?>">بازگشت</a>
        </div>
        <div class="card-body">
            <p><strong>نظرسنجی:</strong> <?php echo h($detail['form_title'] ?? ''); ?></p>
            <p><strong>تاریخ ثبت:</strong> <?php echo h(surveyResultsDate($detail['submitted_at'] ?? '')); ?></p>
            <p><strong>نام:</strong> <?php echo h($detail['customer_name'] ?? ''); ?> | <strong>موبایل:</strong> <?php echo h($detail['customer_mobile_display'] ?? $detail['customer_mobile'] ?? ''); ?> | <strong>امتیاز:</strong> <?php echo h($detail['satisfaction_score'] ?? ''); ?></p>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>پرسش</th><th>پاسخ</th></tr></thead>
                    <tbody>
                    <?php foreach ($answers as $key => $answer): ?>
                        <tr><td><?php echo h($labels[$key] ?? $key); ?></td><td><?php echo h(is_scalar($answer) ? (string)$answer : json_encode($answer, JSON_UNESCAPED_UNICODE)); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$answers): ?><tr><td colspan="2" class="text-muted">داده پاسخ قابل خواندن نیست.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted"><strong>IP:</strong> <?php echo h($detail['ip_address'] ?? ''); ?></p>
            <p class="text-muted"><strong>User Agent:</strong> <?php echo h($detail['user_agent'] ?? ''); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>پاسخ‌های نظرسنجی</h2>
        <a class="btn" href="?<?php echo h(http_build_query(array_merge($filters, ['export' => 'csv']))); ?>">خروجی CSV</a>
    </div>
    <div class="card-body">
        <form class="admin-filter" method="get">
            <select class="form-control" name="form_id">
                <option value="">همه نظرسنجی‌ها</option>
                <?php foreach ($forms as $form): ?><option value="<?php echo h($form['id']); ?>" <?php echo (string)$filters['form_id'] === (string)$form['id'] ? 'selected' : ''; ?>><?php echo h($form['title']); ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="date_from" value="<?php echo h($filters['date_from']); ?>" placeholder="از تاریخ">
            <input class="form-control" type="date" name="date_to" value="<?php echo h($filters['date_to']); ?>" placeholder="تا تاریخ">
            <input class="form-control" type="number" min="1" max="5" name="satisfaction_score" value="<?php echo h($filters['satisfaction_score']); ?>" placeholder="امتیاز">
            <select class="form-control" name="is_dissatisfied">
                <option value="">وضعیت نارضایتی</option>
                <option value="1" <?php echo (string)$filters['is_dissatisfied'] === '1' ? 'selected' : ''; ?>>ناراضی</option>
                <option value="0" <?php echo (string)$filters['is_dissatisfied'] === '0' ? 'selected' : ''; ?>>غیرناراضی</option>
            </select>
            <input class="form-control" name="q" value="<?php echo h($filters['q']); ?>" placeholder="جستجوی موبایل/نام">
            <button class="btn btn-primary" type="submit">فیلتر</button>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>شناسه</th><th>عنوان نظرسنجی</th><th>نام</th><th>شماره تماس</th><th>امتیاز</th><th>وضعیت نارضایتی</th><th>تاریخ ثبت</th><th>مشاهده پاسخ‌ها</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo h($row['id']); ?></td>
                        <td><?php echo h($row['form_title'] ?? ''); ?></td>
                        <td><?php echo h($row['customer_name'] ?? ''); ?></td>
                        <td><?php echo h($row['customer_mobile_display'] ?? $row['customer_mobile'] ?? ''); ?></td>
                        <td><?php echo h($row['satisfaction_score'] ?? ''); ?></td>
                        <td><?php echo (int)($row['is_dissatisfied'] ?? 0) === 1 ? 'ناراضی' : 'عادی'; ?></td>
                        <td><?php echo h(surveyResultsDate($row['submitted_at'] ?? '')); ?></td>
                        <td><a class="btn btn-sm btn-info" href="?<?php echo h(http_build_query(array_merge($filters, ['view' => $row['id']]))); ?>">مشاهده</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted">پاسخی یافت نشد.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
