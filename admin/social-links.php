<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('admin');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'مدیریت شبکه‌های اجتماعی';
$message = '';
$error = '';
$edit = null;

function uploadSocialIconImage(string $field, string $current = ''): string {
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return $current;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('آپلود تصویر آیکون ناموفق بود.');
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('حجم تصویر آیکون نباید بیشتر از ۲ مگابایت باشد.');
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    if (!in_array($ext, $allowedExtensions, true)) {
        throw new RuntimeException('فرمت تصویر آیکون مجاز نیست.');
    }

    $tmpPath = (string)$file['tmp_name'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($tmpPath);
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/svg'];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('فرمت تصویر آیکون مجاز نیست.');
    }

    $dir = UPLOAD_PATH . '/social-icons';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmpPath, $target)) {
        throw new RuntimeException('آپلود تصویر آیکون ناموفق بود.');
    }

    return 'uploads/social-icons/' . $name;
}

function socialIconAdminPreviewSrc($path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = $_POST['action'] ?? 'save';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            $db->prepare('DELETE FROM social_links WHERE id = ?')->execute([$id]);
            redirectTo('social-links.php?deleted=1');
        }
        $data = [
            'title' => trim((string)$_POST['title']),
            'icon' => trim((string)$_POST['icon']),
            'url' => trim((string)$_POST['url']),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $currentIconImage = '';
        if ($id > 0) {
            $currentStmt = $db->prepare('SELECT icon_image FROM social_links WHERE id = ?');
            $currentStmt->execute([$id]);
            $currentIconImage = (string)($currentStmt->fetchColumn() ?: '');
        }
        $uploadedIconImage = uploadSocialIconImage('icon_image', $currentIconImage);
        $data['icon_image'] = $uploadedIconImage !== '' ? $uploadedIconImage : null;
        if ($data['title'] === '' || $data['url'] === '') {
            throw new RuntimeException('عنوان و لینک الزامی است.');
        }
        if ($data['icon'] === '' && $uploadedIconImage === '') {
            throw new RuntimeException('آیکن یا تصویر آیکون الزامی است.');
        }
        if ($id > 0) {
            $data['id'] = $id;
            $db->prepare('UPDATE social_links SET title=:title, icon=:icon, icon_image=:icon_image, url=:url, sort_order=:sort_order, active=:active WHERE id=:id')->execute($data);
        } else {
            $db->prepare('INSERT INTO social_links (title, icon, icon_image, url, sort_order, active) VALUES (:title, :icon, :icon_image, :url, :sort_order, :active)')->execute($data);
        }
        redirectTo('social-links.php?saved=1');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (($_GET['action'] ?? '') === 'edit') {
    $stmt = $db->prepare('SELECT * FROM social_links WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $edit = $stmt->fetch();
}
$links = $db->query('SELECT * FROM social_links ORDER BY sort_order ASC, id ASC')->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<style>
.social-icon-preview {
    width: 32px;
    height: 32px;
    max-width: 32px;
    max-height: 32px;
    object-fit: contain;
    border-radius: 8px;
    background: rgba(255,255,255,.08);
    padding: 4px;
}
</style>
<div class="card"><div class="card-header"><h2><?php echo $edit ? 'ویرایش شبکه' : 'افزودن شبکه اجتماعی'; ?></h2></div><div class="card-body">
<form method="post" class="admin-filter" enctype="multipart/form-data">
<input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
<input type="hidden" name="id" value="<?php echo h($edit['id'] ?? ''); ?>">
<input class="form-control" name="title" placeholder="عنوان مثل Instagram" required value="<?php echo h($edit['title'] ?? ''); ?>">
<input class="form-control" name="icon" placeholder="آیکن/کلاس/ایموجی" value="<?php echo h($edit['icon'] ?? ''); ?>">
<label class="form-label">تصویر آیکون</label>
<input class="form-control" type="file" name="icon_image" accept="image/jpeg,image/png,image/webp,image/svg+xml">
<?php if (!empty($edit['icon_image'])): ?><img class="social-icon-preview" src="<?php echo h(socialIconAdminPreviewSrc($edit['icon_image'])); ?>" alt="<?php echo h($edit['title'] ?? ''); ?>"><?php endif; ?>
<input class="form-control" name="url" placeholder="URL" required value="<?php echo h($edit['url'] ?? ''); ?>">
<input class="form-control" type="number" name="sort_order" placeholder="ترتیب" value="<?php echo h($edit['sort_order'] ?? '0'); ?>">
<label><input type="checkbox" name="active" value="1" <?php echo (($edit['active'] ?? 1) ? 'checked' : ''); ?>> فعال</label>
<button class="btn btn-success" name="action" value="save">ذخیره</button>
<?php if ($edit): ?><a class="btn" href="social-links.php">لغو</a><?php endif; ?>
</form></div></div>
<div class="card"><div class="card-header"><h2>لینک‌ها</h2></div><div class="card-body"><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>عنوان</th><th>تصویر آیکون</th><th>URL</th><th>ترتیب</th><th>فعال</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($links as $link): ?><tr><td><?php echo h($link['id']); ?></td><td><?php echo h($link['title']); ?></td><td><?php if (!empty($link['icon_image'])): ?><img class="social-icon-preview" src="<?php echo h(socialIconAdminPreviewSrc($link['icon_image'])); ?>" alt="<?php echo h($link['title']); ?>"><?php else: ?><?php echo h($link['icon']); ?><?php endif; ?></td><td><a href="<?php echo h($link['url']); ?>" target="_blank" rel="noopener"><?php echo h($link['url']); ?></a></td><td><?php echo h($link['sort_order']); ?></td><td><?php echo $link['active'] ? '✅' : '❌'; ?></td><td><a class="btn btn-sm btn-primary" href="?action=edit&id=<?php echo h($link['id']); ?>">ویرایش</a> <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($link['id']); ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">حذف</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
