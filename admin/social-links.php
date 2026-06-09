<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('admin');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'مدیریت شبکه‌های اجتماعی';
$message = '';
$error = '';
$edit = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = $_POST['action'] ?? 'save';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            $db->prepare('UPDATE social_links SET active = 0 WHERE id = ?')->execute([$id]);
            redirectTo('social-links.php?deactivated=1');
        }
        $data = [
            'title' => trim((string)$_POST['title']),
            'icon' => trim((string)$_POST['icon']),
            'url' => trim((string)$_POST['url']),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        if ($data['title'] === '' || $data['icon'] === '' || $data['url'] === '') {
            throw new RuntimeException('عنوان، آیکن و لینک الزامی است.');
        }
        if ($id > 0) {
            $data['id'] = $id;
            $db->prepare('UPDATE social_links SET title=:title, icon=:icon, url=:url, sort_order=:sort_order, active=:active WHERE id=:id')->execute($data);
        } else {
            $db->prepare('INSERT INTO social_links (title, icon, url, sort_order, active) VALUES (:title, :icon, :url, :sort_order, :active)')->execute($data);
        }
        redirectTo('social-links.php?saved=1');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['action'] ?? 'save') !== 'delete')) {
        $edit = [
            'id' => (int)($_POST['id'] ?? 0),
            'title' => trim((string)($_POST['title'] ?? '')),
            'icon' => trim((string)($_POST['icon'] ?? '')),
            'url' => trim((string)($_POST['url'] ?? '')),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }
}

if (($_GET['action'] ?? '') === 'edit') {
    $stmt = $db->prepare('SELECT * FROM social_links WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $edit = $stmt->fetch();
}
$links = $db->query('SELECT * FROM social_links ORDER BY sort_order ASC, id ASC')->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_GET['deactivated'])): ?><div class="alert alert-info">لینک اجتماعی غیرفعال شد.</div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2><?php echo $edit ? 'ویرایش شبکه' : 'افزودن شبکه اجتماعی'; ?></h2></div><div class="card-body">
<form method="post" class="admin-filter">
<input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
<input type="hidden" name="id" value="<?php echo h($edit['id'] ?? ''); ?>">
<input class="form-control" name="title" placeholder="عنوان مثل Instagram" required value="<?php echo h($edit['title'] ?? ''); ?>">
<input class="form-control" name="icon" placeholder="آیکن/کلاس/ایموجی" required value="<?php echo h($edit['icon'] ?? ''); ?>">
<input class="form-control" name="url" placeholder="URL" required value="<?php echo h($edit['url'] ?? ''); ?>">
<input class="form-control" type="number" name="sort_order" placeholder="ترتیب" value="<?php echo h($edit['sort_order'] ?? '0'); ?>">
<label><input type="checkbox" name="active" value="1" <?php echo (($edit['active'] ?? 1) ? 'checked' : ''); ?>> فعال</label>
<button class="btn btn-success" name="action" value="save">ذخیره</button>
<?php if ($edit): ?><a class="btn" href="social-links.php">لغو</a><?php endif; ?>
</form></div></div>
<div class="card"><div class="card-header"><h2>لینک‌ها</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>عنوان</th><th>آیکن</th><th>URL</th><th>ترتیب</th><th>فعال</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($links as $link): ?><tr><td><?php echo h($link['id']); ?></td><td><?php echo h($link['title']); ?></td><td><?php echo h($link['icon']); ?></td><td><a href="<?php echo h($link['url']); ?>" target="_blank" rel="noopener"><?php echo h($link['url']); ?></a></td><td><?php echo h($link['sort_order']); ?></td><td><?php echo $link['active'] ? '✅' : '❌'; ?></td><td><a class="btn btn-sm btn-primary" href="?action=edit&id=<?php echo h($link['id']); ?>">ویرایش</a> <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($link['id']); ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">حذف</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
