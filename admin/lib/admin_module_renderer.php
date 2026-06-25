<?php
/**
 * Central renderer for admin CRUD modules.
 *
 * Page wrappers should only require admin_crud.php and call adminRenderModulePage().
 * The renderer owns bootstrap order, safe schema repair, POST handling, filters,
 * table output, forms, pagination, and clean Persian error handling.
 */
require_once __DIR__ . '/admin_module_schema_repair.php';

if (!function_exists('adminModulePageName')) {
    function adminModulePageName(): string {
        return basename((string)($_SERVER['PHP_SELF'] ?? 'index.php'));
    }
}

if (!function_exists('adminModuleCanDeleteRecord')) {
    function adminModuleCanDeleteRecord(array $config, int $id): void {
        if (($config['delete_enabled'] ?? true) === false) {
            throw new RuntimeException('حذف برای این صفحه فعال نیست.');
        }

        if (($config['table'] ?? '') === 'menu_categories' && $id > 0 && adminTableExists('menu_items') && adminColumnExists('menu_items', 'category_id')) {
            $stmt = adminDb()->prepare('SELECT COUNT(*) FROM `menu_items` WHERE `category_id` = ?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('این دسته‌بندی دارای آیتم منو است و قابل حذف نیست.');
            }
        }
    }
}

if (!function_exists('adminModuleHandleRequest')) {
    function adminModuleHandleRequest(array $config, ?array $currentAdmin, string &$error): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $error) {
            return;
        }

        try {
            requireValidCsrf();
            $crudAction = $_POST['crud_action'] ?? '';

            if ($crudAction === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                adminModuleCanDeleteRecord($config, $id);
                adminModuleDelete($config, $id);
                redirectTo(adminModulePageName() . '?deleted=1');
            }

            if ($crudAction === 'save') {
                $id = (int)($_POST['id'] ?? 0);

                if (!$id && !empty($config['readonly_create'])) {
                    throw new RuntimeException('ایجاد رکورد جدید برای این صفحه فعال نیست.');
                }

                if (empty($config['fields'])) {
                    throw new RuntimeException('فرم این صفحه با ستون‌های واقعی جدول همگام نیست.');
                }

                $current = $id ? (adminModuleFetchRow($config, $id) ?: []) : [];
                $data = adminModuleCollectData($config, $current, $currentAdmin);
                $data = adminModulePrepareData($config, $data, $current);
                adminModuleValidateData($config, $data, $id);
                $savedId = adminModuleSave($config, $data, $id);

                if (($config['table'] ?? '') === 'matches') {
                    adminRecalculatePredictionsForMatch($savedId);
                }

                redirectTo(adminModulePageName() . '?saved=1');
            }
        } catch (PDOException $e) {
            safeAdminLog('Admin module save failed (' . ($config['key'] ?? $config['table'] ?? 'unknown') . '): ' . $e->getMessage());
            $error = 'ذخیره انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            safeAdminLog('Admin module request failed (' . ($config['key'] ?? $config['table'] ?? 'unknown') . '): ' . $e->getMessage());
            $error = 'درخواست قابل انجام نیست. جزئیات خطا در لاگ سیستم ثبت شد.';
        }
    }
}

if (!function_exists('adminModuleLoadRowsForRender')) {
    function adminModuleLoadRowsForRender(array $config, string &$error): array {
        try {
            return adminModuleRows($config);
        } catch (Throwable $e) {
            safeAdminLog('Admin module rows failed (' . ($config['key'] ?? $config['table'] ?? 'unknown') . '): ' . $e->getMessage());
            $error = 'داده‌ها قابل نمایش نیستند. جزئیات خطا در لاگ سیستم ثبت شد.';
            return ['rows' => [], 'page' => 1, 'perPage' => 20, 'total' => 0];
        }
    }
}

if (!function_exists('adminModuleRenderMessages')) {
    function adminModuleRenderMessages(string $message, string $error): void {
        if (!empty($_GET['saved'])) echo '<div class="alert alert-info">تغییرات ذخیره شد.</div>';
        if (!empty($_GET['deleted'])) echo '<div class="alert alert-info">رکورد حذف شد.</div>';
        if ($message !== '') echo '<div class="alert alert-info">' . h($message) . '</div>';
        if ($error !== '') echo '<div class="alert" style="background:#f8d7da;color:#721c24">' . h($error) . '</div>';
    }
}

if (!function_exists('adminModuleRenderFormView')) {
    function adminModuleRenderFormView(array $config, string $action, ?array $editRow): void {
        ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="crud_action" value="save">
            <input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? 0); ?>">
            <div class="card">
                <div class="card-header"><h2><?php echo h($action === 'edit' ? 'ویرایش ' . $config['title'] : 'افزودن ' . $config['title']); ?></h2></div>
                <div class="card-body">
                    <?php foreach (($config['fields'] ?? []) as $field => $meta): ?>
                        <?php adminModuleRenderField($field, $meta, $editRow[$field] ?? null); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-success" type="submit">ذخیره</button>
            <a class="btn" href="<?php echo h(adminModulePageName()); ?>">بازگشت</a>
        </form>
        <?php
    }
}

if (!function_exists('adminModuleRenderFilterField')) {
    function adminModuleRenderFilterField(array $config, string $filter): void {
        $field = adminModuleFilterMeta($config, $filter);
        $value = $_GET[$filter] ?? '';
        $type = $field['type'] ?? '';

        if ($type === 'select') {
            echo '<select class="form-control" name="' . h($filter) . '"><option value="">' . h($field['label']) . '</option>';
            foreach (($field['options'] ?? []) as $key => $label) {
                echo '<option value="' . h($key) . '" ' . ((string)$value === (string)$key ? 'selected' : '') . '>' . h($label) . '</option>';
            }
            echo '</select>';
            return;
        }

        if (in_array($type, ['category', 'match', 'survey_form'], true)) {
            echo '<select class="form-control" name="' . h($filter) . '"><option value="">' . h($field['label']) . '</option>';
            foreach (adminOptionRows($type) as $option) {
                echo '<option value="' . h($option['id']) . '" ' . ((string)$value === (string)$option['id'] ? 'selected' : '') . '>' . h($option['title']) . '</option>';
            }
            echo '</select>';
            return;
        }

        if ($type === 'number') {
            echo '<input class="form-control" type="number" name="' . h($filter) . '" placeholder="' . h($field['label']) . '" value="' . h($value) . '">';
            return;
        }

        echo '<select class="form-control" name="' . h($filter) . '"><option value="">' . h(adminModuleLabel($config, $filter)) . '</option><option value="1" ' . ((string)$value === '1' ? 'selected' : '') . '>بله</option><option value="0" ' . ((string)$value === '0' ? 'selected' : '') . '>خیر</option></select>';
    }
}

if (!function_exists('adminModuleRenderListView')) {
    function adminModuleRenderListView(array $config, array $data): void {
        $queryWithExport = http_build_query(array_merge($_GET, ['export' => 'csv']));
        ?>
        <div class="card">
            <div class="card-header">
                <h2><?php echo h($config['title']); ?></h2>
                <div>
                    <?php if (empty($config['readonly_create'])): ?><a class="btn btn-primary" href="?action=add">افزودن</a><?php endif; ?>
                    <a class="btn" href="?<?php echo h($queryWithExport); ?>">خروجی CSV</a>
                </div>
            </div>
            <div class="card-body">
                <details class="admin-filter-wrap" <?php echo !empty($_GET['q']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) ? 'open' : ''; ?>>
                    <summary class="btn">فیلتر و جستجو</summary>
                    <form class="admin-filter" method="get">
                        <input class="form-control" name="q" placeholder="جستجو" value="<?php echo h($_GET['q'] ?? ''); ?>">
                        <input class="form-control" name="date_from" placeholder="از تاریخ" value="<?php echo h($_GET['date_from'] ?? ''); ?>">
                        <input class="form-control" name="date_to" placeholder="تا تاریخ" value="<?php echo h($_GET['date_to'] ?? ''); ?>">
                        <?php foreach (($config['filters'] ?? []) as $filter): ?>
                            <?php adminModuleRenderFilterField($config, $filter); ?>
                        <?php endforeach; ?>
                        <button class="btn btn-primary" type="submit">اعمال فیلتر</button>
                        <a class="btn" href="<?php echo h(adminModulePageName()); ?>">پاک‌سازی</a>
                    </form>
                </details>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <?php foreach (($config['columns'] ?? []) as $column): ?>
                                    <th><a href="?<?php echo h(http_build_query(array_merge($_GET, ['sort' => $column, 'order' => (($_GET['sort'] ?? '') === $column && ($_GET['order'] ?? 'desc') === 'desc') ? 'asc' : 'desc']))); ?>"><?php echo h(adminModuleLabel($config, $column)); ?></a></th>
                                <?php endforeach; ?>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($data['rows'] ?? []) as $row): ?>
                            <tr>
                                <?php foreach (($config['columns'] ?? []) as $column): ?>
                                    <td><?php echo adminModuleRenderValue($config, $column, $row[$column] ?? '', $row); ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <a class="btn btn-sm btn-info" href="?action=edit&id=<?php echo h($row['id'] ?? 0); ?>">ویرایش</a>
                                    <?php if (($config['delete_enabled'] ?? true) !== false): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                            <input type="hidden" name="crud_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo h($row['id'] ?? 0); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('آیا مطمئنید؟')">حذف</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($data['rows'])): ?><tr><td colspan="<?php echo count($config['columns'] ?? []) + 1; ?>" class="text-center text-muted">رکوردی یافت نشد.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php $pages = max(1, (int)ceil(($data['total'] ?? 0) / max(1, (int)($data['perPage'] ?? 20)))); ?>
                <p class="text-muted">صفحه <?php echo h($data['page'] ?? 1); ?> از <?php echo h($pages); ?> (کل: <?php echo h($data['total'] ?? 0); ?> رکورد)</p>
                <?php if (($data['page'] ?? 1) > 1): ?><a class="btn" href="?page=1">اول</a> <a class="btn" href="?page=<?php echo h(((int)$data['page']) - 1); ?>">قبلی</a><?php endif; ?>
                <?php if (($data['page'] ?? 1) < $pages): ?><a class="btn" href="?page=<?php echo h(((int)$data['page']) + 1); ?>">بعدی</a> <a class="btn" href="?page=<?php echo h($pages); ?>">آخر</a><?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('adminRenderModulePage')) {
    function adminRenderModulePage(string $moduleKey): void {
        $config = adminModuleLoadConfig($moduleKey);
        $currentAdmin = adminGuard($config['min_role'] ?? 'employee');
        ensureAdminSchema();

        $pageTitle = $config['title'];
        $message = '';
        $error = '';

        try {
            adminRepairModuleSchema($config);
            $config = adminModuleNormalizeConfig($config);

            if (!empty($config['schema_error'])) {
                $error = $config['schema_error'];
            }

            if (empty($config['fields'])) {
                $config['readonly_create'] = true;
                safeAdminLog('Admin module has no editable fields after schema normalization (' . adminModulePageName() . ' -> ' . ($config['table'] ?? 'unknown') . ')');
            }

            if (($_GET['export'] ?? '') === 'csv') {
                adminModuleExportCsv($config);
            }
        } catch (Throwable $e) {
            $error = 'آماده‌سازی ساختار داده انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
            safeAdminLog('Admin module bootstrap failed (' . $moduleKey . '): ' . $e->getMessage());
        }

        $action = $_GET['action'] ?? 'list';
        if ($action === 'add' && !empty($config['readonly_create'])) {
            $action = 'list';
        }
        if (in_array($action, ['add', 'edit'], true) && empty($config['fields'])) {
            if (!$error) $error = 'فرم این صفحه با ستون‌های واقعی جدول همگام نیست.';
            $action = 'list';
        }

        adminModuleHandleRequest($config, $currentAdmin, $error);

        $editRow = null;
        if ($action === 'edit' && !$error) {
            $editRow = adminModuleFetchRow($config, (int)($_GET['id'] ?? 0));
            if (!$editRow) {
                $error = 'رکورد مورد نظر یافت نشد.';
                $action = 'list';
            }
        }

        $data = (!$error && $action === 'list')
            ? adminModuleLoadRowsForRender($config, $error)
            : ['rows' => [], 'page' => 1, 'perPage' => 20, 'total' => 0];

        include dirname(__DIR__) . '/includes/header.php';
        adminModuleRenderMessages($message, $error);

        if (in_array($action, ['add', 'edit'], true) && !$error) {
            adminModuleRenderFormView($config, $action, $editRow);
        } else {
            adminModuleRenderListView($config, $data);
        }

        include dirname(__DIR__) . '/includes/footer.php';
    }
}
