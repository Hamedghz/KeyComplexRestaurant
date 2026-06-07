<?php
require_once __DIR__ . '/lib/admin_schema.php';

$config = adminModuleDefinition('matches');
if (!$config) {
    http_response_code(404);
    exit('Module not found.');
}
$currentAdmin = adminGuard($config['min_role'] ?? 'employee');
$schemaMessages = ensureAdminSchema();
$db = adminDb();
$pageTitle = $config['title'];
$message = '';
$error = '';

try {
    adminEnsureModuleTables($config);
    $config = adminModuleNormalizeConfig($config);
    if (($_GET['export'] ?? '') === 'csv') {
        adminModuleExportCsv($config);
    }
} catch (Throwable $e) {
    $error = 'آماده‌سازی ساختار داده انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('Admin module bootstrap failed (matches): ' . $e->getMessage());
}

$action = $_GET['action'] ?? 'list';
if ($action === 'add' && !empty($config['readonly_create'])) {
    $action = 'list';
}
$editRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        requireValidCsrf();
        $crudAction = $_POST['crud_action'] ?? '';
        if ($crudAction === 'delete') {
            adminModuleDelete($config, (int)($_POST['id'] ?? 0));
            redirectTo(basename($_SERVER['PHP_SELF']) . '?deleted=1');
        }
        if ($crudAction === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id && !empty($config['readonly_create'])) {
                throw new RuntimeException('ایجاد رکورد جدید برای این صفحه فعال نیست.');
            }
            $current = $id ? (adminModuleFetchRow($config, $id) ?: []) : [];
            $data = adminModuleCollectData($config, $current, $currentAdmin);
            $savedId = adminModuleSave($config, $data, $id);
            if ($config['table'] === 'matches') {
                adminRecalculatePredictionsForMatch($savedId);
            }
            redirectTo(basename($_SERVER['PHP_SELF']) . '?saved=1');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($action === 'edit' && !$error) {
    $editRow = adminModuleFetchRow($config, (int)($_GET['id'] ?? 0));
    if (!$editRow) {
        $error = 'رکورد مورد نظر یافت نشد.';
        $action = 'list';
    }
}

try {
    $data = (!$error && $action === 'list') ? adminModuleRows($config) : ['rows'=>[], 'page'=>1, 'perPage'=>20, 'total'=>0];
} catch (Throwable $e) {
    $data = ['rows'=>[], 'page'=>1, 'perPage'=>20, 'total'=>0];
    $error = 'داده‌ها قابل نمایش نیستند. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('Admin module rows failed (matches): ' . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-info">تغییرات ذخیره شد.</div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="alert alert-info">رکورد حذف شد.</div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<?php if (in_array($action, ['add', 'edit'], true) && !$error): ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
        <input type="hidden" name="crud_action" value="save">
        <input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? 0); ?>">
        <div class="card">
            <div class="card-header"><h2><?php echo h($action === 'edit' ? 'ویرایش ' . $config['title'] : 'افزودن ' . $config['title']); ?></h2></div>
            <div class="card-body">
                <?php foreach ($config['fields'] as $field => $meta): ?>
                    <?php adminModuleRenderField($field, $meta, $editRow[$field] ?? null); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="btn btn-success" type="submit">ذخیره</button>
        <a class="btn" href="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">بازگشت</a>
    </form>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h2><?php echo h($config['title']); ?></h2>
            <div>
                <?php if (empty($config['readonly_create'])): ?><a class="btn btn-primary" href="?action=add">افزودن</a><?php endif; ?> <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>">خروجی CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form class="admin-filter" method="get">
                <input class="form-control" name="q" placeholder="جستجو" value="<?php echo h($_GET['q'] ?? ''); ?>">
                <input class="form-control" name="date_from" placeholder="از تاریخ" value="<?php echo h($_GET['date_from'] ?? ''); ?>">
                <input class="form-control" name="date_to" placeholder="تا تاریخ" value="<?php echo h($_GET['date_to'] ?? ''); ?>">
                <?php foreach (($config['filters'] ?? []) as $filter): $field = $config['fields'][$filter] ?? ['label' => adminModuleLabel($config, $filter), 'type' => 'checkbox']; $value = $_GET[$filter] ?? ''; ?>
                    <?php if (($field['type'] ?? '') === 'select'): ?>
                        <select class="form-control" name="<?php echo h($filter); ?>"><option value=""><?php echo h($field['label']); ?></option><?php foreach (($field['options'] ?? []) as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)$value === (string)$key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                    <?php elseif (in_array(($field['type'] ?? ''), ['category','match','survey_form'], true)): ?>
                        <select class="form-control" name="<?php echo h($filter); ?>"><option value=""><?php echo h($field['label']); ?></option><?php foreach (adminOptionRows($field['type']) as $option): ?><option value="<?php echo h($option['id']); ?>" <?php echo (string)$value === (string)$option['id'] ? 'selected' : ''; ?>><?php echo h($option['title']); ?></option><?php endforeach; ?></select>
                    <?php else: ?>
                        <select class="form-control" name="<?php echo h($filter); ?>"><option value=""><?php echo h(adminModuleLabel($config, $filter)); ?></option><option value="1" <?php echo (string)$value === '1' ? 'selected' : ''; ?>>بله</option><option value="0" <?php echo (string)$value === '0' ? 'selected' : ''; ?>>خیر</option></select>
                    <?php endif; ?>
                <?php endforeach; ?>
                <button class="btn btn-primary" type="submit">فیلتر</button>
            </form>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><?php foreach ($config['columns'] as $column): ?><th><a href="?<?php echo h(http_build_query(array_merge($_GET, ['sort' => $column, 'order' => (($_GET['sort'] ?? '') === $column && ($_GET['order'] ?? 'desc') === 'desc') ? 'asc' : 'desc']))); ?>"><?php echo h(adminModuleLabel($config, $column)); ?></a></th><?php endforeach; ?><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($config['columns'] as $column): ?><td><?php echo h(adminModuleFormatValue($column, $row[$column] ?? '')); ?></td><?php endforeach; ?>
                            <td>
                                <a class="btn btn-sm btn-info" href="?action=edit&id=<?php echo h($row['id']); ?>">ویرایش</a>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                    <input type="hidden" name="crud_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                                    <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('آیا مطمئنید؟')">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$data['rows']): ?><tr><td colspan="<?php echo count($config['columns']) + 1; ?>" class="text-center text-muted">رکوردی یافت نشد.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $pages = max(1, (int)ceil($data['total'] / $data['perPage'])); ?>
            <p class="text-muted">صفحه <?php echo h($data['page']); ?> از <?php echo h($pages); ?> (کل: <?php echo h($data['total']); ?> رکورد)</p>
            <?php if ($data['page'] > 1): ?><a class="btn" href="?page=1">اول</a> <a class="btn" href="?page=<?php echo h($data['page'] - 1); ?>">قبلی</a><?php endif; ?>
            <?php if ($data['page'] < $pages): ?><a class="btn" href="?page=<?php echo h($data['page'] + 1); ?>">بعدی</a> <a class="btn" href="?page=<?php echo h($pages); ?>">آخر</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card mt-3"><div class="card-header"><h2>Schema</h2></div><div class="card-body"><p class="text-muted">این صفحه براساس جدول <?php echo h($config['table']); ?> و الگوی admin/settings.php ساخته شده است.</p><ul><?php foreach ($schemaMessages as $m): ?><li><?php echo h($m); ?></li><?php endforeach; ?></ul></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
