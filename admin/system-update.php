<?php
require_once __DIR__ . '/lib/admin_crud.php';
require_once __DIR__ . '/../core/SystemUpdater.php';
$currentAdmin = adminGuard('super_admin');
$updater = new SystemUpdater();
$pageTitle = 'بروزرسانی سیستم';
$message = '';
$error = '';
$shellWarning = $updater->shellCommandsAvailable() ? '' : 'تابع exec روی هاست غیرفعال است؛ عملیات وابسته به git/tar/mysqldump اجرا نمی‌شود و صفحه بدون خطای ۵۰۰ فقط وضعیت محدود را نمایش می‌دهد.';
$status = [];
$logs = [];
$migrationStatus = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrf();
        $action = $_POST['update_action'] ?? 'check';
        if ($action === 'backup') {
            $message = 'بکاپ ساخته شد: ' . $updater->backup();
        } elseif ($action === 'apply') {
            $result = $updater->apply();
            $message = 'بروزرسانی انجام شد. بکاپ: ' . $result['backup'];
        } elseif ($action === 'rollback') {
            $message = 'Rollback از بکاپ انجام شد: ' . $updater->rollback();
        }
    } catch (Throwable $e) {
        $error = 'عملیات بروزرسانی انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
        safeAdminLog('System update action failed: ' . $e->getMessage());
    }
}

try {
    $status = $updater->check();
} catch (Throwable $e) {
    $error = $error ?: 'بررسی بروزرسانی انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.';
    safeAdminLog('System update check failed: ' . $e->getMessage());
    $status = ['current' => $updater->currentVersion(), 'latest' => 'unknown', 'update_available' => false];
}
try {
    $logs = $updater->updateLogs();
} catch (Throwable $e) {
    safeAdminLog('System update logs failed: ' . $e->getMessage());
    $logs = [];
}
try {
    $migrationStatus = $updater->migrationStatus();
} catch (Throwable $e) {
    safeAdminLog('System update migration status failed: ' . $e->getMessage());
    $migrationStatus = [];
}

include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header"><h2>System Update from GitHub</h2></div>
    <div class="card-body">
        <?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($shellWarning): ?><div class="alert" style="background:#fff3cd;color:#856404"><?php echo h($shellWarning); ?></div><?php endif; ?>
        <p>GitHub repository: <strong><?php echo h($status['github_url'] ?? $updater->githubUrl()); ?></strong></p>
        <p>نسخه فعلی: <strong><?php echo h($status['current'] ?? 'unknown'); ?></strong></p>
        <p>آخرین نسخه remote: <strong><?php echo h($status['latest'] ?? 'unknown'); ?></strong></p>
        <p>آخرین Release/Tag: <strong><?php echo h($status['latest_release'] ?? 'none'); ?></strong></p>
        <p>تعداد commitهای عقب‌تر از GitHub: <strong><?php echo h($status['commits_behind'] ?? 0); ?></strong></p>
        <p>وضعیت: <?php echo !empty($status['update_available']) ? 'بروزرسانی موجود است' : 'سیستم بروز است یا upstream تنظیم نشده است'; ?></p>
        <?php if (!empty($status['changelog']) || !empty($status['release_notes'])): ?>
            <h3>Changelog</h3>
            <pre style="white-space:pre-wrap;background:#f8f9fa;padding:12px;border-radius:8px;direction:ltr;text-align:left"><?php echo h(trim(($status['changelog'] ?? '') . "\n\n" . ($status['release_notes'] ?? ''))); ?></pre>
        <?php endif; ?>
        <form method="post" class="quick-actions">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <button class="quick-action-btn" name="update_action" value="check" type="submit"><span class="icon">🔎</span><span>Check for Updates</span></button>
            <button class="quick-action-btn" name="update_action" value="backup" type="submit"><span class="icon">💾</span><span>Backup current system</span></button>
            <button class="quick-action-btn" name="update_action" value="check" type="submit"><span class="icon">📦</span><span>Download Update Metadata</span></button>
            <button class="quick-action-btn" name="update_action" value="apply" type="submit" onclick="return confirm('قبل از بروزرسانی بکاپ فایل و دیتابیس ساخته می‌شود. ادامه می‌دهید؟')"><span class="icon">⬇️</span><span>Apply Update</span></button>
            <button class="quick-action-btn" name="update_action" value="rollback" type="submit" onclick="return confirm('Rollback آخرین بکاپ انجام شود؟')"><span class="icon">↩️</span><span>Rollback</span></button>
        </form>
        <p class="text-muted mt-3">قواعد ایمنی: قبل از apply بکاپ فایل و دیتابیس ساخته می‌شود؛ migrationهای SQL جدید به‌صورت کنترل‌شده اجرا و در system_versions ثبت می‌شوند؛ cache پاک می‌شود؛ در صورت شکست update یا migration، rollback خودکار اجرا می‌شود.</p>
    </div>
</div>
<div class="card"><div class="card-header"><h2>Migration Status</h2></div><div class="card-body"><p>مسیر migration: <?php echo h($migrationStatus['directory'] ?? ''); ?></p><ul><?php foreach (($migrationStatus['files'] ?? []) as $file): ?><li><?php echo h($file); ?></li><?php endforeach; ?></ul></div></div>
<div class="card"><div class="card-header"><h2>Update Logs</h2></div><div class="card-body"><?php if (!$logs): ?><p class="text-muted">لاگی ثبت نشده است.</p><?php endif; ?><?php foreach ($logs as $name => $content): ?><h3><?php echo h($name); ?></h3><pre style="white-space:pre-wrap;background:#f8f9fa;padding:12px;border-radius:8px"><?php echo h($content); ?></pre><?php endforeach; ?></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
