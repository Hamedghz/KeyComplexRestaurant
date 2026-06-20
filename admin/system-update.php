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
        }
    } catch (Throwable $e) {
        $error = 'درخواست قابل انجام نیست؛ لطفاً لاگ سیستم را بررسی کنید.';
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
