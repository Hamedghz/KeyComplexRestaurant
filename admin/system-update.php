<?php
require_once __DIR__ . '/lib/admin_schema.php';
require_once __DIR__ . '/lib/system_seed_runner.php';
require_once __DIR__ . '/lib/hr/tests/test_seed_reset_service.php';
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
    $columns = [
        'checksum' => '`checksum` varchar(64) DEFAULT NULL AFTER `migration_name`',
        'batch' => '`batch` int NOT NULL DEFAULT 1 AFTER `checksum`',
        'status' => '`status` varchar(30) NOT NULL DEFAULT \'completed\' AFTER `batch`',
        'error_message' => '`error_message` text DEFAULT NULL AFTER `status`',
    ];
    foreach ($columns as $column => $definition) {
        if (!systemSeedColumnExists($db, 'schema_migrations', $column)) {
            $db->exec('ALTER TABLE `schema_migrations` ADD COLUMN ' . $definition);
        }
    }
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
    $stmt = $db->query("SELECT migration_name FROM schema_migrations WHERE status='completed' ORDER BY migration_name ASC");
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
        $checksum = hash_file('sha256', $file) ?: null;
        try {
            foreach (systemUpdateSplitSql($sql) as $statement) {
                try {
                    $db->exec($statement);
                } catch (PDOException $e) {
                    $driverCode = (int)($e->errorInfo[1] ?? 0);
                    $isSingleRowInsert = preg_match('/^\s*INSERT\s+INTO\b[\s\S]*\bVALUES\s*\([^;]*\)\s*$/i', $statement) === 1
                        && preg_match('/\)\s*,\s*\(/', $statement) !== 1;
                    if ($isSingleRowInsert && $driverCode === 1062) {
                        safeAdminLog('Migration duplicate row safely skipped in ' . $name . ': ' . $e->getMessage());
                        continue;
                    }
                    throw $e;
                }
            }
            $stmt = $db->prepare("INSERT INTO schema_migrations (migration_name,checksum,batch,status,error_message,executed_at) VALUES (:name,:checksum,1,'completed',NULL,NOW()) ON DUPLICATE KEY UPDATE checksum=VALUES(checksum),status='completed',error_message=NULL,executed_at=NOW()");
            $stmt->execute(['name' => $name, 'checksum' => $checksum]);
            $results['ran'][] = $name;
        } catch (Throwable $e) {
            $results['errors'][] = $name . ': اجرای migration ناموفق بود.';
            $stmt = $db->prepare("INSERT INTO schema_migrations (migration_name,checksum,batch,status,error_message,executed_at) VALUES (:name,:checksum,1,'failed',:error,NOW()) ON DUPLICATE KEY UPDATE checksum=VALUES(checksum),status='failed',error_message=VALUES(error_message),executed_at=NOW()");
            $stmt->execute(['name' => $name, 'checksum' => $checksum, 'error' => mb_substr($e->getMessage(), 0, 2000)]);
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
    $failedStmt = $db->query("SELECT migration_name FROM schema_migrations WHERE status='failed' ORDER BY migration_name");
    $failed = $failedStmt ? $failedStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    return [
        'directory' => ROOT_PATH . '/database/migrations',
        'files' => $files,
        'executed' => $executed,
        'pending' => $pending,
        'executed_count' => count($executed),
        'pending_count' => count($pending),
        'failed' => $failed,
        'failed_count' => count($failed),
    ];
}

$message = '';
$error = '';
$runResults = ['ran' => [], 'errors' => []];
$testResetResult = null;
$testResetPreview = [];
$importResult = null;
$limitedModeMessage = 'تابع exec یا توابع مشابه روی هاست غیرفعال است؛ عملیات وابسته به git/tar/mysqldump اجرا نمی‌شود و سیستم در حالت محدود اجرا می‌شود.';
$disabledFunctions = systemUpdateDisabledFunctions();
$version = systemUpdateVersion();
systemUpdateEnsureMigrationTable($db);
ensureSeedRegistrySchema($db);
registerDefaultSeeds($db);

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
            systemSetupLog($db, 'run_pending_migrations', (int)$currentAdmin['id'], $runResults['errors'] ? 'failed' : 'completed', 'اجرای migrationهای در انتظار', $runResults, $runResults['errors'] ? implode(' ', $runResults['errors']) : null);
        } elseif ($action === 'register_default_seeds') {
            registerDefaultSeeds($db);
            systemSetupLog($db, 'register_default_seeds', (int)$currentAdmin['id'], 'completed', 'Seedهای پیش‌فرض ثبت شدند.');
            $message = 'Seedهای پیش‌فرض ثبت یا به‌روزرسانی شدند.';
        } elseif ($action === 'run_pending_seeds') {
            $seedRunResults = runPendingSeeds($db, ['actor_id' => (int)$currentAdmin['id']]);
            systemSetupLog($db, 'run_pending_seeds', (int)$currentAdmin['id'], $seedRunResults['errors'] ? 'failed' : 'completed', 'اجرای Seedهای در انتظار', $seedRunResults, $seedRunResults['errors'] ? implode(' ', $seedRunResults['errors']) : null);
            $message = $seedRunResults['ran'] ? 'Seedهای در انتظار اجرا شدند.' : 'Seed در انتظار اجرا وجود ندارد.';
            if ($seedRunResults['errors']) $error = implode(' ', $seedRunResults['errors']);
        } elseif ($action === 'run_all_hr_seeds') {
            $seedRunResults = ['ran' => [], 'errors' => []];
            foreach (array_keys(systemSeedDefinitions()) as $seedKey) {
                try { $seedRunResults['ran'][$seedKey] = runSeed($db, $seedKey, ['actor_id' => (int)$currentAdmin['id']]); }
                catch (Throwable $e) { $seedRunResults['errors'][$seedKey] = 'اجرای Seed ناموفق بود.'; break; }
            }
            systemSetupLog($db, 'run_all_hr_seeds', (int)$currentAdmin['id'], $seedRunResults['errors'] ? 'failed' : 'completed', 'اجرای مجدد Seedهای HR', $seedRunResults, $seedRunResults['errors'] ? implode(' ', $seedRunResults['errors']) : null);
            $message = 'اجرای Seedهای HR پایان یافت.';
            if ($seedRunResults['errors']) $error = implode(' ', $seedRunResults['errors']);
        } elseif ($action === 'force_rerun_seed') {
            $seedKey = (string)($_POST['seed_key'] ?? '');
            $seedResult = runSeed($db, $seedKey, ['actor_id' => (int)$currentAdmin['id']]);
            systemSetupLog($db, 'force_rerun_seed', (int)$currentAdmin['id'], 'completed', 'اجرای مجدد Seed: ' . $seedKey, $seedResult);
            $message = 'Seed انتخاب‌شده دوباره اجرا شد.';
        } elseif ($action === 'repair_hr_schema') {
            require_once __DIR__ . '/lib/hr/bootstrap.php';
            hrEnsureCoreSchema($db);
            systemSetupLog($db, 'repair_hr_schema', (int)$currentAdmin['id'], 'completed', 'ساختار افزایشی HR بررسی و ترمیم شد.');
            $message = 'ساختار افزایشی HR بررسی و ترمیم شد.';
        } elseif ($action === 'reset_hr_test_seed_only') {
            $testResetResult = hrResetRestaurantOrganizationalTests($db, (int)$currentAdmin['id'], [
                'confirmation' => (string)($_POST['reset_confirmation'] ?? ''),
            ]);
            $message = 'Reset بانک آزمون سازمانی اجرا شد؛ آرشیو، حذف کنترل‌شده و Seed جدید تکمیل شد.';
        } elseif ($action === 'reset_all_hr_domain_seed_only') {
            throw new RuntimeException('Reset کل Seedهای HR هنوز غیرفعال است و باید در فاز جداگانه با آرشیو کامل پیاده‌سازی شود.');
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

try {
    $registeredSeeds = listRegisteredSeeds($db);
    $seedStatus = ['total' => count($registeredSeeds), 'completed' => 0, 'pending' => 0, 'failed' => 0, 'skipped' => 0, 'changed' => 0];
    foreach ($registeredSeeds as $registeredSeed) {
        $status = (string)($registeredSeed['status'] ?? 'pending');
        if (isset($seedStatus[$status])) $seedStatus[$status]++;
        if ($status === 'pending' && !empty($registeredSeed['executed_at'])) $seedStatus['changed']++;
        try {
            if (($registeredSeed['checksum'] ?? '') !== calculateSeedChecksum((string)$registeredSeed['seed_file'])) $seedStatus['changed']++;
        } catch (Throwable $e) { $seedStatus['changed']++; }
    }
    $logsStmt = $db->query('SELECT * FROM setup_run_logs ORDER BY id DESC LIMIT 20');
    $setupLogs = $logsStmt ? $logsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $registeredSeeds = [];
    $seedStatus = ['total' => 0, 'completed' => 0, 'pending' => 0, 'failed' => 0, 'skipped' => 0, 'changed' => 0];
    $setupLogs = [];
    $error = $error ?: 'وضعیت Seedها قابل خواندن نیست.';
    safeAdminLog('Seed status failed: ' . $e->getMessage());
}

try {
    $testResetPreview = hrDetectExistingTestTables($db);
} catch (Throwable $e) {
    $testResetPreview = [];
    safeAdminLog('HR test reset preview failed: ' . $e->getMessage());
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
    <div class="card-header"><h2>وضعیت Schema و Seed</h2></div>
    <div class="card-body">
        <div class="stats-row">
            <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo (int)$seedStatus['total']; ?></h3><p>Seed ثبت‌شده</p></div></div>
            <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo (int)$seedStatus['completed']; ?></h3><p>Seed تکمیل‌شده</p></div></div>
            <div class="stat-card stat-warning"><div class="stat-content"><h3><?php echo (int)$seedStatus['pending']; ?></h3><p>Seed در انتظار</p></div></div>
            <div class="stat-card stat-danger"><div class="stat-content"><h3><?php echo (int)$seedStatus['failed']; ?></h3><p>Seed ناموفق</p></div></div>
        </div>
        <p>Seedهای دارای checksum تغییرکرده: <strong><?php echo (int)$seedStatus['changed']; ?></strong></p>
        <p>schema_migrations: <strong>آماده</strong> | seed_registry: <strong>آماده</strong> | setup_run_logs: <strong>آماده</strong></p>
        <form method="post" class="quick-actions">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <button class="quick-action-btn" name="update_action" value="register_default_seeds"><span>ثبت Seedهای پیش‌فرض</span></button>
            <button class="quick-action-btn" name="update_action" value="run_pending_seeds"><span>اجرای Seedهای در انتظار</span></button>
            <button class="quick-action-btn" name="update_action" value="run_all_hr_seeds"><span>اجرای Seedهای HR</span></button>
            <button class="quick-action-btn" name="update_action" value="repair_hr_schema"><span>ترمیم Schema افزایشی HR</span></button>
        </form>
        <div class="table-responsive"><table class="table"><thead><tr><th>Seed</th><th>فایل</th><th>وضعیت</th><th>Inserted</th><th>Updated</th><th>Skipped</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($registeredSeeds as $seed): ?><tr>
            <td><?php echo h($seed['seed_key']); ?></td><td><?php echo h($seed['seed_file']); ?></td><td><?php echo h($seed['status']); ?></td>
            <td><?php echo (int)$seed['rows_inserted']; ?></td><td><?php echo (int)$seed['rows_updated']; ?></td><td><?php echo (int)$seed['rows_skipped']; ?></td>
            <td><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="seed_key" value="<?php echo h($seed['seed_key']); ?>"><button class="btn btn-sm" name="update_action" value="force_rerun_seed">اجرای مجدد</button></form></td>
        </tr><?php endforeach; ?></tbody></table></div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>اقدامات محافظت‌شده HR</h2></div>
    <div class="card-body">
        <p class="text-muted">Reset بانک آزمون فقط جدول‌های HR/test را لمس می‌کند، قبل از حذف آرشیو می‌سازد، Seed قدیمی را غیرفعال نگه می‌دارد و Seed جدید آزمون‌های سازمانی رستوران KEY را اجرا می‌کند.</p>
        <div class="table-responsive" style="margin-bottom:12px"><table class="table"><thead><tr><th>جدول مجاز</th><th>ردیف فعلی</th></tr></thead><tbody>
            <?php foreach ($testResetPreview as $previewTable => $preview): ?><tr><td><?php echo h($previewTable); ?></td><td><?php echo (int)($preview['rows'] ?? 0); ?></td></tr><?php endforeach; ?>
            <?php if (!$testResetPreview): ?><tr><td colspan="2" class="text-muted">جدول HR/test موجود یا قابل شمارش پیدا نشد.</td></tr><?php endif; ?>
        </tbody></table></div>
        <form method="post" onsubmit="return confirm('Reset بانک آزمون HR اجرا شود؟ این عملیات ابتدا آرشیو می‌سازد و فقط داده‌های HR/test را حذف می‌کند.');" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="text" name="reset_confirmation" placeholder="RESET_HR_TESTS" autocomplete="off" required>
            <button class="btn btn-danger" name="update_action" value="reset_hr_test_seed_only" type="submit">Reset HR Test Seed Only</button>
        </form>
        <button class="btn" type="button" disabled>Reset All HR Seeds Only</button>
        <div class="alert" style="margin-top:12px;background:#fff3cd;color:#856404">حالت Replace Database یک ابزار بازیابی اضطراری و خارج از جریان HR است. هیچ عملیات HR Setup از آن استفاده نمی‌کند.</div>
        <?php if ($testResetResult): ?>
            <div class="alert alert-info" style="margin-top:12px">
                آرشیو: <?php echo (int)($testResetResult['archive']['rows_archived'] ?? 0); ?> ردیف،
                حذف کنترل‌شده: <?php echo (int)($testResetResult['delete']['rows_deleted'] ?? 0); ?> ردیف،
                سوال‌های جدید: <?php echo (int)($testResetResult['new_seed_verification']['questions'] ?? 0); ?>،
                گزینه‌ها: <?php echo (int)($testResetResult['new_seed_verification']['options'] ?? 0); ?>.
                وضعیت تایید: <?php echo !empty($testResetResult['new_seed_verification']['ok']) && !empty($testResetResult['old_seed_verification']['ok']) ? 'موفق' : 'نیازمند بررسی'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="card"><div class="card-header"><h2>گزارش اجرای Setup</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>نوع</th><th>وضعیت</th><th>خلاصه</th><th>زمان</th></tr></thead><tbody><?php foreach ($setupLogs as $log): ?><tr><td><?php echo h($log['run_type']); ?></td><td><?php echo h($log['status']); ?></td><td><?php echo h($log['summary']); ?></td><td><?php echo h($log['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
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
