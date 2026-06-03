<?php
require_once __DIR__ . '/lib/admin_crud.php';
require_once __DIR__ . '/../core/SystemUpdater.php';
$currentAdmin = adminGuard('super_admin');
$updater = new SystemUpdater();
$pageTitle = 'بروزرسانی سیستم';
$message = '';
$error = '';
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
        $error = $e->getMessage();
    }
}

try {
    $status = $updater->check();
} catch (Throwable $e) {
    $error = $error ?: $e->getMessage();
    $status = ['current' => $updater->currentVersion(), 'latest' => 'unknown', 'update_available' => false];
}
$logs = $updater->updateLogs();
$migrationStatus = $updater->migrationStatus();

include __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header"><h2>System Update from GitHub</h2></div>
    <div class="card-body">
        <?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
        <p>GitHub repository: <strong><?php echo h($status['github_url'] ?? $updater->githubUrl()); ?></strong></p>
        <p>نسخه فعلی: <strong><?php echo h($status['current'] ?? 'unknown'); ?></strong></p>
        <p>آخرین نسخه remote: <strong><?php echo h($status['latest'] ?? 'unknown'); ?></strong></p>
        <p>وضعیت: <?php echo !empty($status['update_available']) ? 'بروزرسانی موجود است' : 'سیستم بروز است یا upstream تنظیم نشده است'; ?></p>
        <form method="post" class="quick-actions">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <button class="quick-action-btn" name="update_action" value="check" type="submit"><span class="icon">🔎</span><span>Check for Updates</span></button>
            <button class="quick-action-btn" name="update_action" value="backup" type="submit"><span class="icon">💾</span><span>Backup current system</span></button>
            <button class="quick-action-btn" name="update_action" value="apply" type="submit" onclick="return confirm('قبل از بروزرسانی بکاپ ساخته می‌شود. ادامه می‌دهید؟')"><span class="icon">⬇️</span><span>Download / Apply update</span></button>
            <button class="quick-action-btn" name="update_action" value="rollback" type="submit" onclick="return confirm('Rollback آخرین بکاپ انجام شود؟')"><span class="icon">↩️</span><span>Rollback</span></button>
        </form>
        <p class="text-muted mt-3">قواعد ایمنی: دیتابیس blind overwrite نمی‌شود؛ قبل از apply بکاپ tar.gz ساخته می‌شود؛ migrationهای SQL جدید در `storage/pending-migrations.txt` ثبت می‌شوند تا در phpMyAdmin بررسی/اعمال شوند.</p>
    </div>
</div>
<div class="card"><div class="card-header"><h2>Migration Status</h2></div><div class="card-body"><p>مسیر migration: <?php echo h($migrationStatus['directory'] ?? ''); ?></p><ul><?php foreach (($migrationStatus['files'] ?? []) as $file): ?><li><?php echo h($file); ?></li><?php endforeach; ?></ul></div></div>
<div class="card"><div class="card-header"><h2>Update Logs</h2></div><div class="card-body"><?php if (!$logs): ?><p class="text-muted">لاگی ثبت نشده است.</p><?php endif; ?><?php foreach ($logs as $name => $content): ?><h3><?php echo h($name); ?></h3><pre style="white-space:pre-wrap;background:#f8f9fa;padding:12px;border-radius:8px"><?php echo h($content); ?></pre><?php endforeach; ?></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
