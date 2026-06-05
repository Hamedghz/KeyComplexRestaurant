<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'منابع جذب مشتری';
$error = '';
$edit = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = $_POST['action'] ?? 'save';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            $db->prepare('DELETE FROM acquisition_sources WHERE id=?')->execute([$id]);
            redirectTo('acquisition-sources.php?deleted=1');
        }
        $data = ['title'=>trim((string)($_POST['title'] ?? '')), 'sort_order'=>(int)($_POST['sort_order'] ?? 0), 'active'=>isset($_POST['active']) ? 1 : 0];
        if ($data['title'] === '') throw new RuntimeException('عنوان الزامی است.');
        if ($id) { $data['id']=$id; $db->prepare('UPDATE acquisition_sources SET title=:title, sort_order=:sort_order, active=:active WHERE id=:id')->execute($data); }
        else { $db->prepare('INSERT INTO acquisition_sources (title, sort_order, active) VALUES (:title, :sort_order, :active)')->execute($data); }
        redirectTo('acquisition-sources.php?saved=1');
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
if (($_GET['action'] ?? '') === 'edit') { $stmt=$db->prepare('SELECT * FROM acquisition_sources WHERE id=?'); $stmt->execute([(int)$_GET['id']]); $edit=$stmt->fetch(); }
$sources = $db->query('SELECT * FROM acquisition_sources ORDER BY sort_order ASC, title ASC')->fetchAll();
$analyticsSources = [];
try {
    if (adminTableExists('analytics_sessions')) {
        $stmt = $db->query('SELECT COALESCE(NULLIF(source, ""), "unknown") AS source_name, COALESCE(NULLIF(medium, ""), "unknown") AS source_type, COUNT(*) AS sessions, MIN(started_at) AS first_seen, MAX(last_activity_at) AS last_seen FROM analytics_sessions GROUP BY source_name, source_type ORDER BY sessions DESC, source_name ASC LIMIT 100');
        $analyticsSources = $stmt ? $stmt->fetchAll() : [];
    }
} catch (Throwable $e) {
    safeAdminLog('Acquisition analytics failed: ' . $e->getMessage());
}
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2><?php echo $edit ? 'ویرایش منبع' : 'افزودن منبع جذب'; ?></h2></div><div class="card-body"><form method="post" class="admin-filter"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($edit['id'] ?? ''); ?>"><input class="form-control" name="title" placeholder="Instagram, Walk-in, ..." required value="<?php echo h($edit['title'] ?? ''); ?>"><input class="form-control" type="number" name="sort_order" placeholder="ترتیب" value="<?php echo h($edit['sort_order'] ?? '0'); ?>"><label><input type="checkbox" name="active" value="1" <?php echo (($edit['active'] ?? 1) ? 'checked' : ''); ?>> فعال</label><button class="btn btn-success" name="action" value="save">ذخیره</button></form></div></div>
<div class="card"><div class="card-header"><h2>لیست منابع</h2></div><div class="card-body"><table class="table"><thead><tr><th>ID</th><th>عنوان</th><th>ترتیب</th><th>فعال</th><th>عملیات</th></tr></thead><tbody><?php foreach($sources as $s): ?><tr><td><?php echo h($s['id']); ?></td><td><?php echo h($s['title']); ?></td><td><?php echo h($s['sort_order']); ?></td><td><?php echo $s['active'] ? '✅' : '❌'; ?></td><td><a class="btn btn-sm btn-primary" href="?action=edit&id=<?php echo h($s['id']); ?>">ویرایش</a> <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($s['id']); ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">حذف</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card"><div class="card-header"><h2>منابع واقعی جذب از analytics</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>Source</th><th>Medium</th><th>Sessions</th><th>First Seen</th><th>Last Seen</th></tr></thead><tbody><?php if (!$analyticsSources): ?><tr><td colspan="5" class="text-muted">داده واقعی analytics هنوز ثبت نشده است.</td></tr><?php endif; ?><?php foreach($analyticsSources as $source): ?><tr><td><?php echo h($source['source_name']); ?></td><td><?php echo h($source['source_type']); ?></td><td><?php echo h($source['sessions']); ?></td><td><?php echo h($source['first_seen']); ?></td><td><?php echo h($source['last_seen']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
