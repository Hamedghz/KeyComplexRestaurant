<?php
require_once __DIR__ . '/lib/admin_schema.php';

$config = adminModuleDefinition('surveys');
if (!$config) {
    http_response_code(404);
    exit('Module not found.');
}

$currentAdmin = adminGuard($config['min_role'] ?? 'admin');
ensureAdminSchema();
adminEnsureModuleTables($config);
$db = adminDb();
$pageTitle = 'فرم‌های نظرسنجی';
$message = '';
$error = '';
$allowedFieldTypes = ['rating', 'radio', 'text', 'mobile', 'date', 'textarea'];

function surveyAdminDateTimeValue($value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function surveyAdminDbDateTime($value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    $timestamp = strtotime(str_replace('T', ' ', $value));
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}

function surveyAdminDecodeSchema($schema): array {
    if (is_array($schema)) return $schema;
    $decoded = json_decode((string)$schema, true);
    return is_array($decoded) ? $decoded : ['fields' => []];
}

function surveyAdminFieldValue(array $fields, int $index, string $key, $default = '') {
    return $fields[$index][$key] ?? $default;
}

function surveyAdminDefaultFields(): array {
    return [
        ['key' => 'customer_name', 'label' => 'نام', 'type' => 'text', 'required' => false, 'options_text' => '', 'sort_order' => 1],
        ['key' => 'customer_mobile', 'label' => 'شماره تماس', 'type' => 'mobile', 'required' => false, 'options_text' => '', 'sort_order' => 2],
        ['key' => 'field_3', 'label' => 'امتیاز شما به خدمات', 'type' => 'rating', 'required' => true, 'options_text' => '', 'sort_order' => 3],
        ['key' => 'field_4', 'label' => 'کیفیت غذا چطور بود؟', 'type' => 'radio', 'required' => true, 'options_text' => "عالی\nخوب\nمتوسط\nضعیف", 'sort_order' => 4],
        ['key' => 'field_5', 'label' => 'تاریخ مراجعه', 'type' => 'date', 'required' => false, 'options_text' => '', 'sort_order' => 5],
        ['key' => 'field_6', 'label' => 'توضیحات و پیشنهادات', 'type' => 'textarea', 'required' => false, 'options_text' => '', 'sort_order' => 6],
    ];
}

function surveyAdminFieldsFromSchema($schema): array {
    $schema = surveyAdminDecodeSchema($schema);
    $fields = [];
    foreach (($schema['fields'] ?? []) as $index => $field) {
        if (!is_array($field)) continue;
        $type = (string)($field['type'] ?? 'text');
        if ($type === 'stars') $type = 'rating';
        if ($type === 'multiple_choice') $type = 'radio';
        $options = [];
        foreach (($field['options'] ?? []) as $option) {
            if (is_array($option)) {
                $options[] = (string)($option['label_fa'] ?? $option['label'] ?? $option['value'] ?? '');
            } else {
                $options[] = (string)$option;
            }
        }
        $fields[] = [
            'key' => (string)($field['key'] ?? $field['id'] ?? 'field_' . ($index + 1)),
            'label' => (string)($field['label'] ?? $field['label_fa'] ?? ''),
            'type' => in_array($type, ['rating', 'radio', 'text', 'mobile', 'date', 'textarea'], true) ? $type : 'text',
            'required' => !empty($field['required']),
            'options_text' => implode("\n", array_filter($options, static fn($option) => trim($option) !== '')),
            'sort_order' => (int)($field['sort_order'] ?? ($index + 1)),
        ];
    }
    usort($fields, static fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return $fields;
}

function surveyAdminCollectFields(array $source, array $allowedFieldTypes): array {
    $labels = $source['field_label'] ?? [];
    $types = $source['field_type'] ?? [];
    $required = $source['field_required'] ?? [];
    $options = $source['field_options'] ?? [];
    $orders = $source['field_sort_order'] ?? [];
    $keys = $source['field_key'] ?? [];
    $fields = [];

    foreach ($labels as $index => $label) {
        $label = trim((string)$label);
        $type = (string)($types[$index] ?? '');
        if ($label === '' && $type === '') continue;
        $fields[] = [
            'key' => trim((string)($keys[$index] ?? '')),
            'label' => $label,
            'type' => $type,
            'required' => isset($required[$index]),
            'options_text' => (string)($options[$index] ?? ''),
            'sort_order' => (int)($orders[$index] ?? (count($fields) + 1)),
        ];
    }

    usort($fields, static fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    $normalized = [];
    foreach ($fields as $index => $field) {
        if ($field['key'] === '') {
            $field['key'] = in_array($field['type'], ['text', 'mobile'], true) && preg_match('/نام/u', $field['label']) ? 'customer_name' : 'field_' . ($index + 1);
            if ($field['type'] === 'mobile') $field['key'] = 'customer_mobile';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $field['key'])) {
            $field['key'] = 'field_' . ($index + 1);
        }
        if (!in_array($field['type'], $allowedFieldTypes, true)) {
            $field['type'] = 'text';
        }
        $field['sort_order'] = $field['sort_order'] > 0 ? $field['sort_order'] : ($index + 1);
        $normalized[] = $field;
    }

    return $normalized;
}

function surveyAdminBuildSchema(array $fields, array $allowedFieldTypes): array {
    if (!$fields) {
        throw new RuntimeException('حداقل یک فیلد برای نظرسنجی لازم است.');
    }

    $schemaFields = [];
    foreach ($fields as $index => $field) {
        $label = trim((string)$field['label']);
        $type = (string)$field['type'];
        if ($label === '') {
            throw new RuntimeException('برچسب همه فیلدها الزامی است.');
        }
        if (!in_array($type, $allowedFieldTypes, true)) {
            throw new RuntimeException('نوع فیلد معتبر نیست.');
        }
        $item = [
            'key' => (string)($field['key'] ?: 'field_' . ($index + 1)),
            'label' => $label,
            'type' => $type,
            'required' => !empty($field['required']),
            'sort_order' => (int)($field['sort_order'] ?: ($index + 1)),
        ];
        if ($type === 'rating') {
            $item['max'] = 5;
        }
        if ($type === 'radio') {
            $options = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$field['options_text'])), static fn($option) => $option !== ''));
            if (count($options) < 2) {
                throw new RuntimeException('فیلدهای رادیویی باید حداقل دو گزینه داشته باشند.');
            }
            $item['options'] = $options;
        }
        $schemaFields[] = $item;
    }

    usort($schemaFields, static fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return ['fields' => $schemaFields];
}

function surveyAdminDeactivateOthers(PDO $db, int $currentId, string $relatedPage): void {
    $stmt = $db->prepare('UPDATE dynamic_forms SET is_active = 0 WHERE related_page = :related_page AND id <> :id');
    $stmt->execute(['related_page' => $relatedPage, 'id' => $currentId]);
}

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$editRow = null;
$formFields = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $postAction = $_POST['survey_action'] ?? '';

        if ($postAction === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare('DELETE FROM dynamic_forms WHERE id = ?')->execute([$id]);
            }
            redirectTo('surveys.php?deleted=1');
        }

        if ($postAction === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $relatedPage = trim((string)($_POST['related_page'] ?? 'survey'));
            if ($relatedPage === '') $relatedPage = 'survey';
            $startDate = surveyAdminDbDateTime($_POST['start_date'] ?? '');
            $endDate = surveyAdminDbDateTime($_POST['end_date'] ?? '');
            if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
                throw new RuntimeException('تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.');
            }

            $formName = trim((string)($_POST['form_name'] ?? ''));
            $formTitle = trim((string)($_POST['form_title_fa'] ?? ''));
            if ($formName === '') throw new RuntimeException('نام سیستمی فرم الزامی است.');
            if ($formTitle === '') throw new RuntimeException('عنوان فارسی فرم الزامی است.');

            $fields = surveyAdminCollectFields($_POST, $allowedFieldTypes);
            $schema = surveyAdminBuildSchema($fields, $allowedFieldTypes);
            $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($schemaJson === false || json_decode($schemaJson, true) === null) {
                throw new RuntimeException('ساختار فرم JSON معتبر نیست.');
            }

            $data = [
                'form_name' => $formName,
                'form_title_fa' => $formTitle,
                'form_title_en' => trim((string)($_POST['form_title_en'] ?? '')) ?: null,
                'form_description_fa' => trim((string)($_POST['form_description_fa'] ?? '')) ?: null,
                'form_description_en' => trim((string)($_POST['form_description_en'] ?? '')) ?: null,
                'form_schema' => $schemaJson,
                'related_page' => $relatedPage,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'display_order' => (int)($_POST['display_order'] ?? 0),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'publishing_channels' => trim((string)($_POST['publishing_channels'] ?? '')) ?: null,
                'branch_id' => ($_POST['branch_id'] ?? '') === '' ? null : (int)$_POST['branch_id'],
                'survey_version' => trim((string)($_POST['survey_version'] ?? '')) ?: null,
            ];

            if ($id > 0) {
                $sets = [];
                foreach ($data as $column => $value) $sets[] = "`{$column}` = :{$column}";
                $data['id'] = $id;
                $db->prepare('UPDATE dynamic_forms SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($data);
                $savedId = $id;
            } else {
                $data['created_by'] = $currentAdmin['id'] ?? null;
                $columns = array_keys($data);
                $placeholders = array_map(static fn($column) => ':' . $column, $columns);
                $db->prepare('INSERT INTO dynamic_forms (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')')->execute($data);
                $savedId = (int)$db->lastInsertId();
            }

            if ((int)$data['is_active'] === 1) {
                surveyAdminDeactivateOthers($db, $savedId, $relatedPage);
            }

            redirectTo('surveys.php?saved=1');
        }
    }
} catch (Throwable $e) {
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'ذخیره انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    if (!($e instanceof RuntimeException)) safeAdminLog('Survey save failed: ' . $e->getMessage());
}

if ($action === 'template') {
    $action = 'add';
    $formFields = surveyAdminDefaultFields();
}

if ($action === 'edit') {
    $stmt = $db->prepare('SELECT * FROM dynamic_forms WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
    if (!$editRow) {
        $error = 'نظرسنجی مورد نظر یافت نشد.';
        $action = 'list';
    } else {
        $formFields = surveyAdminFieldsFromSchema($editRow['form_schema'] ?? '');
    }
}

if ($action === 'add' && !$formFields) {
    $formFields = [['key' => 'field_1', 'label' => '', 'type' => 'rating', 'required' => true, 'options_text' => '', 'sort_order' => 1]];
}

$rows = [];
if ($action === 'list') {
    $rows = $db->query('SELECT df.*, (SELECT COUNT(*) FROM survey_responses sr WHERE sr.form_id = df.id) AS response_count FROM dynamic_forms df ORDER BY df.display_order ASC, df.id DESC')->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>
<style>
    .survey-builder-grid{display:grid;grid-template-columns:1fr;gap:16px}
    .survey-field-row{display:grid;grid-template-columns:1.4fr 150px 110px 120px 1fr 44px;gap:10px;align-items:start;background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:10px}
    .survey-field-row textarea{min-height:42px}
    .survey-field-remove{height:40px}
    @media (max-width:900px){.survey-field-row{grid-template-columns:1fr}.survey-field-remove{width:100%}}
</style>

<?php if (!empty($_GET['saved'])): ?><div class="alert alert-info">نظرسنجی ذخیره شد.</div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="alert alert-info">نظرسنجی حذف شد.</div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<?php if (in_array($action, ['add', 'edit'], true)): ?>
    <form method="post" id="surveyBuilderForm">
        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
        <input type="hidden" name="survey_action" value="save">
        <input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? 0); ?>">

        <div class="card">
            <div class="card-header"><h2><?php echo h($action === 'edit' ? 'ویرایش نظرسنجی' : 'ساخت نظرسنجی'); ?></h2></div>
            <div class="card-body survey-builder-grid">
                <div class="admin-filter">
                    <input class="form-control" name="form_name" placeholder="نام سیستمی" required value="<?php echo h($editRow['form_name'] ?? $_POST['form_name'] ?? 'customer_satisfaction'); ?>">
                    <input class="form-control" name="form_title_fa" placeholder="عنوان نظرسنجی" required value="<?php echo h($editRow['form_title_fa'] ?? $_POST['form_title_fa'] ?? ''); ?>">
                    <input class="form-control" name="related_page" placeholder="صفحه مرتبط" value="<?php echo h($editRow['related_page'] ?? $_POST['related_page'] ?? 'survey'); ?>">
                    <label><input type="checkbox" name="is_active" value="1" <?php echo (int)($editRow['is_active'] ?? 1) === 1 ? 'checked' : ''; ?>> فعال</label>
                </div>

                <textarea class="form-control" name="form_description_fa" rows="3" placeholder="توضیحات"><?php echo h($editRow['form_description_fa'] ?? $_POST['form_description_fa'] ?? ''); ?></textarea>

                <div class="admin-filter">
                    <input class="form-control" type="datetime-local" name="start_date" value="<?php echo h(surveyAdminDateTimeValue($editRow['start_date'] ?? $_POST['start_date'] ?? '')); ?>" placeholder="شروع">
                    <input class="form-control" type="datetime-local" name="end_date" value="<?php echo h(surveyAdminDateTimeValue($editRow['end_date'] ?? $_POST['end_date'] ?? '')); ?>" placeholder="پایان">
                    <input class="form-control" type="number" name="display_order" value="<?php echo h($editRow['display_order'] ?? $_POST['display_order'] ?? 0); ?>" placeholder="ترتیب">
                    <input class="form-control" name="publishing_channels" value="<?php echo h($editRow['publishing_channels'] ?? $_POST['publishing_channels'] ?? ''); ?>" placeholder="کانال انتشار">
                    <input class="form-control" type="number" name="branch_id" value="<?php echo h($editRow['branch_id'] ?? $_POST['branch_id'] ?? ''); ?>" placeholder="شعبه">
                    <input class="form-control" name="survey_version" value="<?php echo h($editRow['survey_version'] ?? $_POST['survey_version'] ?? ''); ?>" placeholder="نسخه">
                </div>

                <input type="hidden" name="form_title_en" value="<?php echo h($editRow['form_title_en'] ?? $_POST['form_title_en'] ?? ''); ?>">
                <input type="hidden" name="form_description_en" value="<?php echo h($editRow['form_description_en'] ?? $_POST['form_description_en'] ?? ''); ?>">
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>فیلدهای نظرسنجی</h2>
                <button class="btn btn-primary" type="button" id="addSurveyField">افزودن فیلد</button>
            </div>
            <div class="card-body">
                <div id="surveyFields">
                    <?php foreach ($formFields as $index => $field): ?>
                        <div class="survey-field-row">
                            <input type="hidden" name="field_key[]" value="<?php echo h(surveyAdminFieldValue($formFields, $index, 'key')); ?>">
                            <input class="form-control" name="field_label[]" placeholder="برچسب فیلد" value="<?php echo h(surveyAdminFieldValue($formFields, $index, 'label')); ?>" required>
                            <select class="form-control" name="field_type[]">
                                <?php foreach (['rating'=>'ستاره‌ای','radio'=>'رادیو باتن','text'=>'متنی کوتاه','mobile'=>'شماره موبایل','date'=>'تاریخ','textarea'=>'متن بلند'] as $type => $label): ?>
                                    <option value="<?php echo h($type); ?>" <?php echo surveyAdminFieldValue($formFields, $index, 'type', 'text') === $type ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label><input type="checkbox" name="field_required[<?php echo h($index); ?>]" <?php echo !empty($field['required']) ? 'checked' : ''; ?>> الزامی</label>
                            <input class="form-control" type="number" name="field_sort_order[]" value="<?php echo h(surveyAdminFieldValue($formFields, $index, 'sort_order', $index + 1)); ?>" placeholder="ترتیب">
                            <textarea class="form-control" name="field_options[]" placeholder="گزینه‌های رادیو، هر گزینه در یک خط"><?php echo h(surveyAdminFieldValue($formFields, $index, 'options_text')); ?></textarea>
                            <button class="btn btn-danger survey-field-remove" type="button">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted">برای رادیو باتن، گزینه‌ها را هر کدام در یک خط وارد کنید. JSON به‌صورت خودکار ساخته می‌شود.</p>
            </div>
        </div>

        <button class="btn btn-success" type="submit">ذخیره نظرسنجی</button>
        <a class="btn" href="surveys.php">بازگشت</a>
    </form>

    <template id="surveyFieldTemplate">
        <div class="survey-field-row">
            <input type="hidden" name="field_key[]" value="">
            <input class="form-control" name="field_label[]" placeholder="برچسب فیلد" required>
            <select class="form-control" name="field_type[]">
                <option value="rating">ستاره‌ای</option>
                <option value="radio">رادیو باتن</option>
                <option value="text">متنی کوتاه</option>
                <option value="mobile">شماره موبایل</option>
                <option value="date">تاریخ</option>
                <option value="textarea">متن بلند</option>
            </select>
            <label><input type="checkbox" name="field_required[]"> الزامی</label>
            <input class="form-control" type="number" name="field_sort_order[]" placeholder="ترتیب">
            <textarea class="form-control" name="field_options[]" placeholder="گزینه‌های رادیو، هر گزینه در یک خط"></textarea>
            <button class="btn btn-danger survey-field-remove" type="button">×</button>
        </div>
    </template>

    <script>
        const container = document.getElementById('surveyFields');
        function refreshRequiredNames() {
            container.querySelectorAll('.survey-field-row').forEach((row, index) => {
                const required = row.querySelector('input[type="checkbox"]');
                required.name = 'field_required[' + index + ']';
                const key = row.querySelector('input[name="field_key[]"]');
                if (!key.value) key.value = 'field_' + (index + 1);
                const order = row.querySelector('input[name="field_sort_order[]"]');
                if (!order.value) order.value = index + 1;
            });
        }
        document.getElementById('addSurveyField').addEventListener('click', () => {
            container.appendChild(document.getElementById('surveyFieldTemplate').content.cloneNode(true));
            refreshRequiredNames();
        });
        container.addEventListener('click', (event) => {
            if (event.target.classList.contains('survey-field-remove')) {
                event.target.closest('.survey-field-row').remove();
                refreshRequiredNames();
            }
        });
        refreshRequiredNames();
    </script>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h2>فرم‌های نظرسنجی</h2>
            <div>
                <a class="btn btn-primary" href="?action=add">افزودن</a>
                <a class="btn btn-success" href="?action=template">ساخت نظرسنجی ساده پیش‌فرض</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>شناسه</th><th>نام</th><th>عنوان</th><th>صفحه مرتبط</th><th>فعال</th><th>شروع</th><th>پایان</th><th>پاسخ‌ها</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo h($row['id']); ?></td>
                            <td><?php echo h($row['form_name']); ?></td>
                            <td><?php echo h($row['form_title_fa']); ?></td>
                            <td><?php echo h($row['related_page'] ?? 'survey'); ?></td>
                            <td><?php echo (int)$row['is_active'] === 1 ? 'بله' : 'خیر'; ?></td>
                            <td><?php echo h($row['start_date']); ?></td>
                            <td><?php echo h($row['end_date']); ?></td>
                            <td><a href="survey-responses.php?form_id=<?php echo h($row['id']); ?>"><?php echo h($row['response_count']); ?></a></td>
                            <td>
                                <a class="btn btn-sm btn-info" href="?action=edit&id=<?php echo h($row['id']); ?>">ویرایش</a>
                                <form method="post" style="display:inline" onsubmit="return confirm('آیا مطمئنید؟')">
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                    <input type="hidden" name="survey_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                                    <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">نظرسنجی ثبت نشده است.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
