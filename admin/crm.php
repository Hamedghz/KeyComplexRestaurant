<?php
require_once __DIR__ . '/lib/admin_schema.php';
require_once __DIR__ . '/lib/crm_data.php';

$config = adminModuleDefinition('crm');
if (!$config) { http_response_code(404); exit('Module not found.'); }
$currentAdmin = adminGuard($config['min_role'] ?? 'employee');
$db = adminDb();
$pageTitle = $config['title'];
$message = '';
$error = '';
$importReport = null;

try {
    ensureAdminSchema();
    adminEnsureModuleTables($config);
    // Reload after schema creation so status options come from the dynamic table.
    $config = adminModuleNormalizeConfig(adminModuleDefinition('crm'));
    if (($_GET['export'] ?? '') === 'csv') crmExportCsv($_GET);
} catch (Throwable $e) {
    $error = 'آماده‌سازی ساختار CRM انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('CRM bootstrap failed: ' . $e->getMessage());
}

$returnQuery = $_GET;
unset($returnQuery['action'], $returnQuery['id'], $returnQuery['export'], $returnQuery['debug_headers']);
$returnUrl = basename($_SERVER['PHP_SELF']) . ($returnQuery ? '?' . http_build_query($returnQuery) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        requireValidCsrf();
        $crudAction = (string)($_POST['crud_action'] ?? '');
        if ($crudAction === 'import') {
            $importReport = crmImportFile($_FILES['crm_file'] ?? []);
            $message = 'پردازش فایل به پایان رسید.';
        } elseif ($crudAction === 'inline_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = crmUtf8($_POST['customer_status'] ?? '');
            if (!$id || !array_key_exists($status, crmCustomerStatusOptions())) throw new RuntimeException('وضعیت انتخاب‌شده معتبر نیست.');
            $db->prepare('UPDATE crm_customers SET customer_status = ? WHERE id = ?')->execute([$status, $id]);
            redirectTo($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'status_saved=1');
        } elseif ($crudAction === 'status_save') {
            $id = (int)($_POST['status_id'] ?? 0);
            $titleFa = crmUtf8($_POST['title_fa'] ?? '');
            $titleEn = crmUtf8($_POST['title_en'] ?? '');
            $color = crmUtf8($_POST['color'] ?? '');
            $sort = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($titleFa === '' || $titleEn === '') throw new RuntimeException('عنوان فارسی و کلید/عنوان انگلیسی وضعیت الزامی است.');
            if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $titleEn)) throw new RuntimeException('کلید انگلیسی فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.');
            if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) throw new RuntimeException('رنگ باید به فرمت #RRGGBB باشد.');
            if ($id) {
                $old = $db->prepare('SELECT title_en FROM crm_customer_statuses WHERE id = ?'); $old->execute([$id]); $oldKey = $old->fetchColumn();
                $db->beginTransaction();
                $db->prepare('UPDATE crm_customer_statuses SET title_fa=?, title_en=?, color=?, sort_order=?, is_active=? WHERE id=?')->execute([$titleFa,$titleEn,$color ?: null,$sort,$active,$id]);
                if ($oldKey && $oldKey !== $titleEn) $db->prepare('UPDATE crm_customers SET customer_status=? WHERE customer_status=?')->execute([$titleEn,$oldKey]);
                $db->commit();
            } else {
                $db->prepare('INSERT INTO crm_customer_statuses (title_fa,title_en,color,sort_order,is_active) VALUES (?,?,?,?,?)')->execute([$titleFa,$titleEn,$color ?: null,$sort,$active]);
            }
            redirectTo('crm.php?statuses_saved=1');
        } elseif ($crudAction === 'delete') {
            adminModuleDelete($config, (int)($_POST['id'] ?? 0));
            redirectTo('crm.php?deleted=1');
        } elseif ($crudAction === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $current = $id ? (adminModuleFetchRow($config, $id) ?: []) : [];
            $data = adminModuleCollectData($config, $current, $currentAdmin);
            $data['mobile'] = crmNormalizeMobile($data['mobile'] ?? null);
            $data['email'] = strtolower(crmUtf8($data['email'] ?? '')) ?: null;
            $data['tags'] = crmNormalizeTags($data['tags'] ?? '');
            $data['full_name'] = crmUtf8($data['full_name'] ?? '');
            if ($data['full_name'] === '' && !$data['mobile']) throw new RuntimeException('حداقل نام یا موبایل الزامی است.');
            if (!empty($data['customer_status']) && !array_key_exists((string)$data['customer_status'], crmCustomerStatusOptions())) throw new RuntimeException('وضعیت مشتری معتبر نیست.');
            adminModuleSave($config, $data, $id);
            redirectTo('crm.php?saved=1');
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

$action = (string)($_GET['action'] ?? 'list');
$editRow = null;
if ($action === 'edit' && !$error) {
    $editRow = adminModuleFetchRow($config, (int)($_GET['id'] ?? 0));
    if (!$editRow) { $error = 'رکورد مورد نظر یافت نشد.'; $action = 'list'; }
    elseif (isset($editRow['tags'])) $editRow['tags'] = crmTagsForExport($editRow['tags']);
}

try {
    $data = (!$error && $action === 'list') ? adminModuleRows($config) : ['rows'=>[], 'page'=>1, 'perPage'=>20, 'total'=>0];
    $statuses = crmCustomerStatuses(false);
    $activeStatusOptions = crmCustomerStatusOptions();
    $statusByKey = [];
    foreach ($statuses as $status) $statusByKey[(string)$status['title_en']] = $status;
} catch (Throwable $e) {
    $data = ['rows'=>[], 'page'=>1, 'perPage'=>20, 'total'=>0]; $statuses = []; $activeStatusOptions = []; $statusByKey = [];
    $error = 'داده‌های CRM قابل نمایش نیستند.'; safeAdminLog('CRM rows failed: ' . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_GET['saved']) || !empty($_GET['status_saved']) || !empty($_GET['statuses_saved'])): ?><div class="alert alert-info">تغییرات ذخیره شد.</div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="alert alert-info">رکورد حذف شد.</div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>

<?php if ($importReport): ?>
<div class="card"><div class="card-header"><h2>گزارش ورود اطلاعات</h2></div><div class="card-body">
    <p>افزوده‌شده: <strong><?php echo h($importReport['inserted']); ?></strong> — به‌روزشده: <strong><?php echo h($importReport['updated']); ?></strong> — ردشده: <strong><?php echo h($importReport['skipped']); ?></strong></p>
    <?php if ($importReport['errors']): ?><div class="table-responsive"><table class="table"><thead><tr><th>ردیف</th><th>دلیل</th></tr></thead><tbody><?php foreach ($importReport['errors'] as $row => $reason): ?><tr><td><?php echo h($row); ?></td><td><?php echo h($reason); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></div>
<?php endif; ?>

<?php if (in_array($action, ['add','edit'], true) && !$error): ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
    <input type="hidden" name="crud_action" value="save"><input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? 0); ?>">
    <div class="card"><div class="card-header"><h2><?php echo $action === 'edit' ? 'ویرایش مشتری' : 'افزودن مشتری'; ?></h2></div><div class="card-body">
        <p class="text-muted">حداقل یکی از فیلدهای نام یا موبایل را وارد کنید. برچسب‌ها را با ویرگول جدا کنید.</p>
        <?php foreach ($config['fields'] as $field => $meta) adminModuleRenderField($field, $meta, $editRow[$field] ?? null); ?>
    </div></div>
    <button class="btn btn-success" type="submit">ذخیره</button> <a class="btn" href="crm.php">بازگشت</a>
</form>
<?php else: ?>
<div class="card">
    <div class="card-header"><h2>ورود و خروج Excel</h2></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="crud_action" value="import">
            <input class="form-control" style="max-width:420px" type="file" name="crm_file" accept=".csv,.xlsx" required>
            <button class="btn btn-primary" type="submit">ورود CSV / XLSX</button>
            <span class="text-muted">حداکثر ۲۰ مگابایت؛ ردیف‌های نامعتبر جداگانه گزارش می‌شوند.</span>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?php echo h($config['title']); ?></h2><div><a class="btn btn-primary" href="?action=add">افزودن</a> <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv']))); ?>">خروجی فیلترشده CSV</a> <a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET, ['export'=>'csv','debug_headers'=>1]))); ?>">خروجی با کلیدهای فنی</a></div></div>
    <div class="card-body">
        <form class="admin-filter" method="get">
            <input class="form-control" name="q" placeholder="نام، موبایل یا ایمیل" value="<?php echo h($_GET['q'] ?? ''); ?>">
            <select class="form-control" name="customer_status"><option value="">همه وضعیت‌ها</option><?php foreach ($statuses as $status): ?><option value="<?php echo h($status['title_en']); ?>" <?php echo (string)($_GET['customer_status'] ?? '') === (string)$status['title_en'] ? 'selected' : ''; ?>><?php echo h($status['title_fa']); ?><?php echo !$status['is_active'] ? ' (غیرفعال)' : ''; ?></option><?php endforeach; ?></select>
            <select class="form-control" name="acquisition_source"><option value="">همه منابع جذب</option><?php foreach (adminAcquisitionSourceOptions() as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo (string)($_GET['acquisition_source'] ?? '') === (string)$key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
            <input class="form-control" name="date_from" placeholder="آخرین مراجعه از تاریخ" value="<?php echo h($_GET['date_from'] ?? ''); ?>">
            <input class="form-control" name="date_to" placeholder="آخرین مراجعه تا تاریخ" value="<?php echo h($_GET['date_to'] ?? ''); ?>">
            <button class="btn btn-primary" type="submit">اعمال فیلتر</button> <a class="btn" href="crm.php">پاک کردن</a>
        </form>
        <div class="table-responsive"><table class="table table-striped">
            <thead><tr><?php foreach ($config['columns'] as $column): ?><th><a href="?<?php echo h(http_build_query(array_merge($_GET, ['sort'=>$column,'order'=>(($_GET['sort'] ?? '') === $column && ($_GET['order'] ?? 'desc') === 'desc') ? 'asc' : 'desc']))); ?>"><?php echo h(adminModuleLabel($config, $column)); ?></a></th><?php endforeach; ?><th>عملیات سریع</th></tr></thead>
            <tbody><?php foreach ($data['rows'] as $row): ?><tr>
                <?php foreach ($config['columns'] as $column): ?><td><?php if ($column === 'customer_status'): $s=$statusByKey[(string)($row[$column] ?? '')] ?? null; ?><span style="display:inline-block;padding:3px 8px;border-radius:999px;background:<?php echo h($s['color'] ?? '#6c757d'); ?>;color:#fff"><?php echo h($s['title_fa'] ?? ($row[$column] ?? '')); ?></span><?php elseif ($column === 'tags'): ?><?php echo h(crmTagsForExport($row[$column] ?? '')); ?><?php else: ?><?php echo h(adminModuleFormatValue($column, $row[$column] ?? '')); ?><?php endif; ?></td><?php endforeach; ?>
                <td style="min-width:260px"><a class="btn btn-sm btn-info" href="?action=edit&id=<?php echo h($row['id']); ?>">ویرایش</a>
                    <form method="post" style="display:inline-flex;gap:4px"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="crud_action" value="inline_status"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><select name="customer_status" class="form-control" style="width:auto"><?php foreach ($activeStatusOptions as $key=>$label): ?><option value="<?php echo h($key); ?>" <?php echo (string)$row['customer_status']===(string)$key?'selected':''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select><button class="btn btn-sm" type="submit">تغییر وضعیت</button></form>
                    <form method="post" style="display:inline"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" value="<?php echo h($row['id']); ?>"><button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('آیا مطمئنید؟')">حذف</button></form>
                </td>
            </tr><?php endforeach; ?><?php if (!$data['rows']): ?><tr><td colspan="<?php echo count($config['columns'])+1; ?>" class="text-center text-muted">رکوردی یافت نشد.</td></tr><?php endif; ?></tbody>
        </table></div>
        <?php $pages=max(1,(int)ceil($data['total']/$data['perPage'])); ?><p class="text-muted">صفحه <?php echo h($data['page']); ?> از <?php echo h($pages); ?> (کل: <?php echo h($data['total']); ?>)</p>
        <?php if ($data['page']>1): ?><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET,['page'=>$data['page']-1]))); ?>">قبلی</a><?php endif; ?> <?php if ($data['page']<$pages): ?><a class="btn" href="?<?php echo h(http_build_query(array_merge($_GET,['page'=>$data['page']+1]))); ?>">بعدی</a><?php endif; ?>
    </div>
</div>

<div class="card"><div class="card-header"><h2>تعریف وضعیت‌های مشتری</h2></div><div class="card-body">
    <p class="text-muted">کلید انگلیسی مقدار ذخیره‌شده در CRM است؛ تغییر آن، رکوردهای قبلی را نیز در یک تراکنش به‌روزرسانی می‌کند.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>فارسی</th><th>کلید انگلیسی</th><th>رنگ</th><th>ترتیب</th><th>فعال</th><th>ذخیره</th></tr></thead><tbody>
    <?php foreach (array_merge($statuses, [['id'=>0,'title_fa'=>'','title_en'=>'','color'=>'#6c757d','sort_order'=>count($statuses)*10+10,'is_active'=>1]]) as $status): $formId='crm-status-form-'.(int)$status['id']; ?><tr><td><input form="<?php echo h($formId); ?>" class="form-control" name="title_fa" value="<?php echo h($status['title_fa']); ?>" required></td><td><input form="<?php echo h($formId); ?>" class="form-control" dir="ltr" name="title_en" value="<?php echo h($status['title_en']); ?>" required></td><td><input form="<?php echo h($formId); ?>" type="color" name="color" value="<?php echo h($status['color'] ?: '#6c757d'); ?>"></td><td><input form="<?php echo h($formId); ?>" class="form-control" type="number" name="sort_order" value="<?php echo h($status['sort_order']); ?>"></td><td><input form="<?php echo h($formId); ?>" type="checkbox" name="is_active" value="1" <?php echo $status['is_active']?'checked':''; ?>></td><td><form id="<?php echo h($formId); ?>" method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="crud_action" value="status_save"><input type="hidden" name="status_id" value="<?php echo h($status['id']); ?>"><button class="btn btn-sm btn-success" type="submit"><?php echo $status['id']?'ذخیره':'افزودن'; ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div></div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
