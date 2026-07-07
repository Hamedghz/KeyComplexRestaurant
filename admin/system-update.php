<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('super_admin');
$pageTitle = 'بروزرسانی سیستم';
$db = adminDb();

function systemUpdateDisabledFunctions(): array {
    $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    $disabled = array_map('strtolower', $disabled);
    $shellFunctions = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
    return array_values(array_intersect($shellFunctions, $disabled));
}

function systemUpdateVersion(): array {
    $empty = ['app' => '', 'version' => 'unknown', 'build' => 'unknown'];
    $file = ROOT_PATH . '/version.json';
    if (!is_readable($file)) {
        return $empty;
    }
    $json = file_get_contents($file);
    if ($json === false) {
        return $empty;
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $empty;
    }
    return [
        'app' => is_string($data['app'] ?? null) ? $data['app'] : '',
        'version' => is_string($data['version'] ?? null) ? $data['version'] : 'unknown',
        'build' => is_string($data['build'] ?? null) ? $data['build'] : 'unknown',
    ];
}

function systemUpdateEnsureMigrationTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration_name` varchar(255) NOT NULL,
        `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_schema_migrations_name` (`migration_name`),
        KEY `idx_schema_migrations_executed_at` (`executed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function systemUpdateMigrationFiles(): array {
    $dir = ROOT_PATH . '/database/migrations';
    $files = is_dir($dir) ? glob($dir . '/*.sql') : [];
    $files = $files ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function systemUpdateExecutedMigrations(PDO $db): array {
    systemUpdateEnsureMigrationTable($db);
    $stmt = $db->query('SELECT migration_name FROM schema_migrations ORDER BY migration_name ASC');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
}

function systemUpdateSplitSql(string $sql): array {
    $statements = [];
    $buffer = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';
        if ($quote === null && $char === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            continue;
        }
        if ($quote === null && $char === '#') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $i += 2;
            while ($i < $len && !(($sql[$i] ?? '') === '*' && ($sql[$i + 1] ?? '') === '/')) { $i++; }
            $i++;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = null;
            }
            continue;
        }
        if (in_array($char, ["'", '"', '`'], true)) {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function systemUpdateQuoteIdentifier(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function systemUpdateReplaceDatabase(PDO $db): int {
    $stmt = $db->query(
        "SELECT TABLE_NAME, TABLE_TYPE
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY CASE WHEN TABLE_TYPE = 'VIEW' THEN 0 ELSE 1 END, TABLE_NAME"
    );
    $objects = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $dropped = 0;

    foreach ($objects as $object) {
        $name = (string)($object['TABLE_NAME'] ?? '');
        if ($name === '') {
            continue;
        }
        $type = strtoupper((string)($object['TABLE_TYPE'] ?? '')) === 'VIEW' ? 'VIEW' : 'TABLE';
        $db->exec('DROP ' . $type . ' IF EXISTS ' . systemUpdateQuoteIdentifier($name));
        $dropped++;
    }

    return $dropped;
}

function systemUpdateImportSqlFile(PDO $db, array $file, bool $replaceDatabase = false): array {
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم فایل از محدودیت تنظیم‌شده روی سرور بیشتر است.',
            UPLOAD_ERR_FORM_SIZE => 'حجم فایل از حداکثر مجاز بیشتر است.',
            UPLOAD_ERR_PARTIAL => 'آپلود فایل کامل نشده است؛ دوباره تلاش کنید.',
            UPLOAD_ERR_NO_FILE => 'لطفاً یک فایل SQL انتخاب کنید.',
        ];
        throw new RuntimeException($messages[$uploadError] ?? 'آپلود فایل SQL ناموفق بود.');
    }

    $originalName = basename((string)($file['name'] ?? ''));
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $originalName) ?? $originalName;
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $maxSize = 10 * 1024 * 1024;

    if ($name === '' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
        throw new RuntimeException('فقط فایل با پسوند .sql پذیرفته می‌شود.');
    }
    if ($tmpPath === '' || !is_uploaded_file($tmpPath) || !is_readable($tmpPath)) {
        throw new RuntimeException('فایل آپلودشده معتبر یا قابل خواندن نیست.');
    }
    $size = filesize($tmpPath);
    if ($size === false || $size <= 0 || $size > $maxSize) {
        throw new RuntimeException('فایل SQL باید غیرخالی و حداکثر ۱۰ مگابایت باشد.');
    }

    $sql = file_get_contents($tmpPath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('فایل SQL خالی یا قابل خواندن نیست.');
    }

    return systemUpdateExecuteSql($db, $sql, $name, $replaceDatabase);
}

function systemUpdateImportServerFile(PDO $db, bool $replaceDatabase = false): array {
    $path = ROOT_PATH . '/storage/database-import.sql';
    $maxSize = 10 * 1024 * 1024;

    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('فایل storage/database-import.sql روی سرور پیدا نشد یا قابل خواندن نیست.');
    }
    $size = filesize($path);
    if ($size === false || $size <= 0 || $size > $maxSize) {
        throw new RuntimeException('فایل SQL روی سرور باید غیرخالی و حداکثر ۱۰ مگابایت باشد.');
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('فایل SQL روی سرور خالی یا قابل خواندن نیست.');
    }

    $result = systemUpdateExecuteSql($db, $sql, basename($path), $replaceDatabase);
    if (!@unlink($path)) {
        safeAdminLog('Imported SQL file could not be removed: ' . $path);
        $result['cleanup_warning'] = true;
    }
    return $result;
}

function systemUpdateExecuteSql(PDO $db, string $sql, string $name, bool $replaceDatabase = false): array {
    $statements = systemUpdateSplitSql($sql);
    if (!$statements) {
        throw new RuntimeException('هیچ دستور SQL قابل اجرایی در فایل پیدا نشد.');
    }

    if ($replaceDatabase && !preg_match('/\bCREATE\s+TABLE\b/i', $sql)) {
        throw new RuntimeException('حالت جایگزینی فقط برای بکاپ کامل شامل CREATE TABLE مجاز است.');
    }

    $foreignKeyChecks = (int)$db->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn();
    $droppedObjects = 0;

    try {
        if ($replaceDatabase) {
            $db->exec('SET SESSION FOREIGN_KEY_CHECKS = 0');
            $droppedObjects = systemUpdateReplaceDatabase($db);
        }

        foreach ($statements as $index => $statement) {
            $db->exec($statement);
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $failedStatement = isset($index) ? $index + 1 : 0;
        safeAdminLog('Manual SQL import failed for ' . $name . ' at statement ' . $failedStatement . ': ' . $e->getMessage());
        $detail = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');
        throw new RuntimeException(
            'اجرای دستور شماره ' . $failedStatement . ' ناموفق بود: ' . $detail
            . ' دستورهای قبلی ممکن است اجرا شده باشند.'
        );
    } finally {
        $db->exec('SET SESSION FOREIGN_KEY_CHECKS = ' . ($foreignKeyChecks === 0 ? '0' : '1'));
    }

    $mode = $replaceDatabase ? 'replace' : 'merge';
    safeAdminLog('Manual SQL import completed: ' . $name . ' (' . count($statements) . ' statements, mode: ' . $mode . ')');
    return [
        'name' => $name,
        'statement_count' => count($statements),
        'mode' => $mode,
        'dropped_objects' => $droppedObjects,
    ];
}

function systemUpdateRunPendingMigrations(PDO $db): array {
    systemUpdateEnsureMigrationTable($db);
    $executed = array_flip(systemUpdateExecutedMigrations($db));
    $results = ['ran' => [], 'errors' => []];
    foreach (systemUpdateMigrationFiles() as $file) {
        $name = basename($file);
        if (isset($executed[$name])) {
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            $results['errors'][] = $name . ': فایل migration قابل خواندن نیست.';
            break;
        }
        try {
            foreach (systemUpdateSplitSql($sql) as $statement) {
                if (preg_match('/^\s*(?:INSERT(?:\s+IGNORE)?|REPLACE)\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $seedMatch)
                    && adminTableHasRows($seedMatch[1])) {
                    safeAdminLog('SEED SKIPPED: ' . $seedMatch[1] . ' already contains data');
                    continue;
                }
                $db->exec($statement);
            }
            $stmt = $db->prepare('INSERT INTO schema_migrations (migration_name) VALUES (:name)');
            $stmt->execute(['name' => $name]);
            $results['ran'][] = $name;
        } catch (Throwable $e) {
            $results['errors'][] = $name . ': اجرای migration ناموفق بود.';
            safeAdminLog('Migration failed in system update for ' . $name . ': ' . $e->getMessage());
            break;
        }
    }
    return $results;
}

function systemUpdateMigrationStatus(PDO $db): array {
    $files = array_map('basename', systemUpdateMigrationFiles());
    $executed = systemUpdateExecutedMigrations($db);
    $pending = array_values(array_diff($files, $executed));
    return [
        'directory' => ROOT_PATH . '/database/migrations',
        'files' => $files,
        'executed' => $executed,
        'pending' => $pending,
        'executed_count' => count($executed),
        'pending_count' => count($pending),
    ];
}

$message = '';
$error = '';
$runResults = ['ran' => [], 'errors' => []];
$importResult = null;
$limitedModeMessage = 'تابع exec یا توابع مشابه روی هاست غیرفعال است؛ عملیات وابسته به git/tar/mysqldump اجرا نمی‌شود و سیستم در حالت محدود اجرا می‌شود.';
$disabledFunctions = systemUpdateDisabledFunctions();
$version = systemUpdateVersion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrf();
        $action = (string)($_POST['update_action'] ?? 'status');
        if ($action === 'run_migrations') {
            $runResults = systemUpdateRunPendingMigrations($db);
            $message = $runResults['ran'] ? 'Migrationهای جدید اجرا شدند.' : 'Migration در انتظار اجرا وجود ندارد.';
            if ($runResults['errors']) {
                $error = implode(' ', $runResults['errors']);
            }
        } elseif ($action === 'import_sql') {
            if (($_POST['confirm_sql_import'] ?? '') !== '1') {
                throw new RuntimeException('برای اجرای فایل، تأیید ایمپورت SQL الزامی است.');
            }
            $replaceDatabase = ($_POST['replace_database'] ?? '') === '1';
            if ($replaceDatabase && trim((string)($_POST['replace_confirmation'] ?? '')) !== 'REPLACE') {
                throw new RuntimeException('برای جایگزینی کامل دیتابیس، عبارت REPLACE را دقیق وارد کنید.');
            }
            $importResult = systemUpdateImportSqlFile($db, $_FILES['sql_file'] ?? [], $replaceDatabase);
            $message = 'فایل «' . $importResult['name'] . '» با موفقیت ایمپورت شد؛ '
                . $importResult['statement_count'] . ' دستور اجرا شد.';
            if ($importResult['mode'] === 'replace') {
                $message .= ' دیتابیس قبلی به‌طور کامل با این بکاپ جایگزین شد.';
            }
            if (!empty($importResult['cleanup_warning'])) {
                $message .= ' فایل SQL را برای امنیت به‌صورت دستی از storage حذف کنید.';
            }
        } elseif ($action === 'import_server_sql') {
            if (($_POST['confirm_sql_import'] ?? '') !== '1') {
                throw new RuntimeException('برای اجرای فایل، تأیید ایمپورت SQL الزامی است.');
            }
            $replaceDatabase = ($_POST['replace_database'] ?? '') === '1';
            if ($replaceDatabase && trim((string)($_POST['replace_confirmation'] ?? '')) !== 'REPLACE') {
                throw new RuntimeException('برای جایگزینی کامل دیتابیس، عبارت REPLACE را دقیق وارد کنید.');
            }
            $importResult = systemUpdateImportServerFile($db, $replaceDatabase);
            $message = 'فایل SQL موجود در storage با موفقیت اجرا شد؛ '
                . $importResult['statement_count'] . ' دستور اجرا شد.';
            if ($importResult['mode'] === 'replace') {
                $message .= ' دیتابیس قبلی به‌طور کامل با این بکاپ جایگزین شد.';
            }
            if (!empty($importResult['cleanup_warning'])) {
                $message .= ' فایل SQL را برای امنیت به‌صورت دستی از storage حذف کنید.';
            }
        }
    } catch (Throwable $e) {
        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'درخواست قابل انجام نیست؛ لطفاً لاگ سیستم را بررسی کنید.';
        safeAdminLog('System update request failed: ' . $e->getMessage());
    }
}

try {
    $migrationStatus = systemUpdateMigrationStatus($db);
} catch (Throwable $e) {
    $migrationStatus = ['directory' => ROOT_PATH . '/database/migrations', 'files' => [], 'executed' => [], 'pending' => [], 'executed_count' => 0, 'pending_count' => 0];
    $error = $error ?: 'وضعیت migrationها قابل خواندن نیست.';
    safeAdminLog('System update status failed: ' . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header"><h2>به‌روزرسانی سیستم</h2></div>
    <div class="card-body">
        <?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($disabledFunctions): ?><div class="alert" style="background:#fff3cd;color:#856404"><?php echo h($limitedModeMessage); ?></div><?php endif; ?>
        <?php if (!$disabledFunctions): ?><div class="alert alert-info">این پنل از دستورهای shell استفاده نمی‌کند و فقط وضعیت و migrationهای دیتابیس را مدیریت می‌کند.</div><?php endif; ?>
        <p class="text-muted">استقرار کد خارج از این پنل انجام می‌شود؛ برای نمونه با GitHub Actions و FTP/SFTP. این صفحه فقط وضعیت نسخه و migrationهای دیتابیس را با PDO مدیریت می‌کند.</p>
        <div class="stats-row">
            <div class="stat-card stat-primary"><div class="stat-icon">🏷️</div><div class="stat-content"><h3><?php echo h($version['version']); ?></h3><p>Version</p></div></div>
            <div class="stat-card stat-success"><div class="stat-icon">🧱</div><div class="stat-content"><h3><?php echo h($version['build']); ?></h3><p>Build</p></div></div>
            <div class="stat-card stat-warning"><div class="stat-icon">✅</div><div class="stat-content"><h3><?php echo h((string)$migrationStatus['executed_count']); ?></h3><p>Executed migrations</p></div></div>
            <div class="stat-card stat-danger"><div class="stat-icon">⏳</div><div class="stat-content"><h3><?php echo h((string)$migrationStatus['pending_count']); ?></h3><p>Pending migrations</p></div></div>
        </div>
        <h3>Hosting capability status</h3>
        <p>Shell update capability: <strong>Disabled / not used</strong></p>
        <p>Disabled shell functions: <strong><?php echo h($disabledFunctions ? implode(', ', $disabledFunctions) : 'none listed by hosting'); ?></strong></p>
        <form method="post" class="quick-actions">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <button class="quick-action-btn" name="update_action" value="run_migrations" type="submit"><span class="icon">🗄️</span><span>Run pending migrations</span></button>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>ایمپورت از فایل روی سرور</h2></div>
    <div class="card-body">
        <p>اگر آپلود SQL توسط فایروال هاست قطع می‌شود، فایل را با File Manager یا FTP دقیقاً با نام <code>storage/database-import.sql</code> قرار دهید و این فرم را اجرا کنید. فایل پس از ایمپورت موفق حذف می‌شود.</p>
        <form method="post">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="update_action" value="import_server_sql">
            <label style="display:flex;align-items:flex-start;gap:8px">
                <input type="checkbox" name="confirm_sql_import" value="1" required>
                <span>اجرای فایل <code>storage/database-import.sql</code> را تأیید می‌کنم.</span>
            </label>
            <div class="form-group" style="margin-top:12px;padding:14px;border:1px solid #dc3545;border-radius:8px;background:#fff5f5">
                <label style="display:flex;align-items:flex-start;gap:8px;color:#721c24">
                    <input type="checkbox" name="replace_database" value="1">
                    <span><strong>جایگزینی کامل دیتابیس:</strong> همه جدول‌ها و اطلاعات فعلی حذف شوند.</span>
                </label>
                <label for="server_replace_confirmation" style="display:block;margin-top:12px">در حالت جایگزینی عبارت <code>REPLACE</code> را وارد کنید:</label>
                <input id="server_replace_confirmation" name="replace_confirmation" type="text" autocomplete="off" placeholder="REPLACE" style="margin-top:6px">
            </div>
            <button class="btn btn-primary" type="submit" onclick="return confirm('فایل SQL موجود روی سرور اجرا شود؟')">اجرای فایل روی سرور</button>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>ایمپورت فایل SQL</h2></div>
    <div class="card-body">
        <p>یک فایل <code>.sql</code> را مستقیماً روی پایگاه داده فعلی اجرا کنید. حداکثر حجم فایل ۱۰ مگابایت است و فایل پس از اجرا روی سرور ذخیره نمی‌شود.</p>
        <div class="alert" style="background:#fff3cd;color:#856404">قبل از اجرا از پایگاه داده نسخه پشتیبان تهیه کنید. بعضی دستورهای SQL مانند تغییر ساختار جدول قابل بازگشت نیستند.</div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="update_action" value="import_sql">
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
            <div class="form-group">
                <label for="sql_file">فایل SQL</label>
                <input id="sql_file" name="sql_file" type="file" accept=".sql,application/sql,text/sql,text/plain" required>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:flex-start;gap:8px">
                    <input type="checkbox" name="confirm_sql_import" value="1" required>
                    <span>تأیید می‌کنم که این فایل روی پایگاه داده فعلی اجرا شود.</span>
                </label>
            </div>
            <div class="form-group" style="padding:14px;border:1px solid #dc3545;border-radius:8px;background:#fff5f5">
                <label style="display:flex;align-items:flex-start;gap:8px;color:#721c24">
                    <input id="replace_database" type="checkbox" name="replace_database" value="1">
                    <span><strong>جایگزینی کامل دیتابیس:</strong> همه جدول‌ها و اطلاعات فعلی حذف و سپس بکاپ وارد شود.</span>
                </label>
                <label for="replace_confirmation" style="display:block;margin-top:12px">برای فعال‌کردن این حالت، عبارت <code>REPLACE</code> را وارد کنید:</label>
                <input id="replace_confirmation" name="replace_confirmation" type="text" autocomplete="off" placeholder="REPLACE" style="margin-top:6px">
            </div>
            <button class="btn btn-primary" type="submit" onclick="return confirm('فایل SQL روی پایگاه داده فعلی اجرا شود؟')">ایمپورت و اجرای SQL</button>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>وضعیت مهاجرت‌های پایگاه داده</h2></div>
    <div class="card-body">
        <p>مسیر migration: <?php echo h($migrationStatus['directory']); ?></p>
        <h3>Pending</h3>
        <?php if (!$migrationStatus['pending']): ?><p class="text-muted">Migration در انتظار اجرا وجود ندارد.</p><?php endif; ?>
        <ul><?php foreach ($migrationStatus['pending'] as $file): ?><li><?php echo h($file); ?></li><?php endforeach; ?></ul>
        <h3>Executed</h3>
        <?php if (!$migrationStatus['executed']): ?><p class="text-muted">هنوز migration ثبت‌شده‌ای وجود ندارد.</p><?php endif; ?>
        <ul><?php foreach ($migrationStatus['executed'] as $file): ?><li><?php echo h($file); ?></li><?php endforeach; ?></ul>
        <?php if ($runResults['ran']): ?><h3>Result</h3><ul><?php foreach ($runResults['ran'] as $file): ?><li><?php echo h($file); ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
