<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
$db = adminDb();
$pageTitle = 'کتابخانه رسانه';
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = $_POST['media_action'] ?? 'upload';
        if ($action === 'delete') {
            $id=(int)$_POST['id']; $stmt=$db->prepare('SELECT file_path FROM media WHERE id=?'); $stmt->execute([$id]); $file=$stmt->fetchColumn();
            if ($file && is_file(ROOT_PATH.'/'.ltrim($file,'/'))) unlink(ROOT_PATH.'/'.ltrim($file,'/'));
            $db->prepare('DELETE FROM media WHERE id=?')->execute([$id]); redirectTo('media.php?deleted=1');
        } elseif ($action === 'edit') {
            $db->prepare('UPDATE media SET alt_text_fa=?, alt_text_en=?, category=? WHERE id=?')->execute([trim((string)$_POST['alt_text_fa']), trim((string)$_POST['alt_text_en']), $_POST['category'] ?? 'other', (int)$_POST['id']]); redirectTo('media.php?saved=1');
        } else {
            if (empty($_FILES['media_file']['name'])) throw new RuntimeException('فایل را انتخاب کنید.');
            $rel = uploadAdminImage('media_file', 'media'); $abs = ROOT_PATH . '/' . $rel; $info = @getimagesize($abs) ?: [null,null,'mime'=>$_FILES['media_file']['type']];
            $db->prepare('INSERT INTO media (filename, original_name, file_path, file_type, file_size, mime_type, width, height, alt_text_fa, alt_text_en, category, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([basename($rel), $_FILES['media_file']['name'], $rel, 'image', filesize($abs), $info['mime'] ?? $_FILES['media_file']['type'], $info[0], $info[1], trim((string)$_POST['alt_text_fa']), trim((string)$_POST['alt_text_en']), $_POST['category'] ?? 'other', $currentAdmin['id']]);
            redirectTo('media.php?uploaded=1');
        }
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
$categories = ['logo','hero','menu','texture','model','icon','other'];
$q=trim((string)($_GET['q']??'')); $where='1=1'; $params=[]; if($q!==''){ $where='original_name LIKE :q OR alt_text_fa LIKE :q OR category LIKE :q'; $params['q']='%'.$q.'%'; }
$stmt=$db->prepare('SELECT * FROM media WHERE '.$where.' ORDER BY id DESC LIMIT 200'); $stmt->execute($params); $items=$stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2>آپلود رسانه</h2></div><div class="card-body"><form method="post" enctype="multipart/form-data" class="admin-filter"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input class="form-control" type="file" name="media_file" accept="image/*" required><input class="form-control" name="alt_text_fa" placeholder="Alt فارسی"><input class="form-control" name="alt_text_en" placeholder="Alt English"><select class="form-control" name="category"><?php foreach($categories as $c): ?><option><?php echo h($c); ?></option><?php endforeach; ?></select><button class="btn btn-success">Upload + Compress</button></form><p class="text-muted">در صورت وجود پشتیبانی GD/Imagick در سرور، فشرده‌سازی می‌تواند به pipeline آپلود متصل شود؛ مسیر و metadata در جدول media ذخیره می‌شود.</p></div></div>
<div class="card"><div class="card-header"><h2>رسانه‌ها</h2></div><div class="card-body"><form class="admin-filter"><input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="جستجو"><button class="btn btn-primary">جستجو</button></form><div class="menu-grid">
<?php foreach($items as $m): ?><div class="card"><div class="card-body"><img src="../<?php echo h($m['file_path']); ?>" alt="<?php echo h($m['alt_text_fa']); ?>" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px"><p><?php echo h($m['original_name']); ?></p><small><?php echo h($m['category'].' / '.$m['file_size'].' bytes'); ?></small><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($m['id']); ?>"><input class="form-control" name="alt_text_fa" value="<?php echo h($m['alt_text_fa']); ?>"><input class="form-control" name="alt_text_en" value="<?php echo h($m['alt_text_en']); ?>"><select class="form-control" name="category"><?php foreach($categories as $c): ?><option <?php echo $m['category']===$c?'selected':''; ?>><?php echo h($c); ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary" name="media_action" value="edit">ذخیره</button><button class="btn btn-sm btn-danger" name="media_action" value="delete" onclick="return confirm('حذف شود؟')">حذف</button></form></div></div><?php endforeach; ?>
</div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
