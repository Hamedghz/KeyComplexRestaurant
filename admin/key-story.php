<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'مدیریت درباره KEY';
$error = '';
$success = '';

function loadKeyStory(PDO $db): array {
    $stmt = $db->query('SELECT * FROM key_story_settings ORDER BY active DESC, id ASC LIMIT 1');
    $story = $stmt ? $stmt->fetch() : null;
    return $story ?: [
        'id' => 0,
        'title' => 'درباره مجموعه',
        'subtitle' => 'سفری در دل طعم و معنا',
        'description' => '<p>روایت طعم‌های اصیل، قهوه‌های منتخب و میزبانی گرم در فضایی لوکس و آرام.</p>',
        'image' => '',
        'gallery' => '',
        'active' => 1,
    ];
}

$story = loadKeyStory($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $subtitle = trim((string)($_POST['subtitle'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $image = trim((string)($_POST['image'] ?? $story['image']));
        $gallery = trim((string)($_POST['gallery'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($title === '') {
            throw new RuntimeException('عنوان درباره الزامی است.');
        }
        if ($description === '') {
            throw new RuntimeException('متن درباره الزامی است.');
        }

        $image = uploadAdminImage('image_upload', 'key-story', $image);

        if ($id > 0) {
            $stmt = $db->prepare('UPDATE key_story_settings SET title = :title, subtitle = :subtitle, description = :description, image = :image, gallery = :gallery, active = :active WHERE id = :id');
            $stmt->execute([
                'title' => $title,
                'subtitle' => $subtitle,
                'description' => $description,
                'image' => $image,
                'gallery' => $gallery,
                'active' => $active,
                'id' => $id,
            ]);
        } else {
            $stmt = $db->prepare('INSERT INTO key_story_settings (title, subtitle, description, image, gallery, active) VALUES (:title, :subtitle, :description, :image, :gallery, :active)');
            $stmt->execute([
                'title' => $title,
                'subtitle' => $subtitle,
                'description' => $description,
                'image' => $image,
                'gallery' => $gallery,
                'active' => $active,
            ]);
        }

        $success = 'بخش درباره صفحه اصلی با موفقیت ذخیره شد.';
        $story = loadKeyStory($db);
    } catch (Throwable $e) {
        $error = 'خطا در ذخیره: ' . $e->getMessage();
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
.form-section { background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.form-section h3 { color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--accent); }
.preview-section { background: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center; }
.preview-image { max-width: 100%; max-height: 400px; border-radius: 10px; margin: 20px 0; }
</style>

<?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h2>منبع اصلی بخش درباره صفحه اصلی</h2></div>
    <div class="card-body">
        <p class="text-muted">این صفحه مستقیماً جدول <code>key_story_settings</code> را مدیریت می‌کند و صفحه اصلی رکورد فعال همین جدول را نمایش می‌دهد.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="id" value="<?php echo h($story['id'] ?? 0); ?>">
            <div class="form-section">
                <h3>اطلاعات اصلی</h3>
                <div class="form-group"><label class="form-label">عنوان درباره</label><input type="text" name="title" class="form-control" value="<?php echo h($story['title']); ?>" required></div>
                <div class="form-group"><label class="form-label">زیرعنوان</label><input type="text" name="subtitle" class="form-control" value="<?php echo h($story['subtitle'] ?? ''); ?>"></div>
                <div class="form-group"><label class="form-label">متن درباره</label><textarea name="description" class="form-control" rows="8" required><?php echo h($story['description']); ?></textarea><small>HTML امن مانند پاراگراف، لیست و لینک در صفحه اصلی پشتیبانی می‌شود.</small></div>
                <div class="form-group"><label><input type="checkbox" name="active" value="1" <?php echo ((int)($story['active'] ?? 1) === 1) ? 'checked' : ''; ?>> فعال</label></div>
            </div>
            <div class="form-section">
                <h3>تصویر درباره</h3>
                <div class="form-group"><label class="form-label">آپلود تصویر جدید</label><input type="file" name="image_upload" class="form-control" accept="image/jpeg,image/png,image/webp"><small>فرمت‌های مجاز: JPG, PNG, WEBP</small></div>
                <div class="form-group"><label class="form-label">یا آدرس تصویر</label><input type="text" name="image" class="form-control" value="<?php echo h($story['image']); ?>" placeholder="uploads/key-story/image.jpg"></div>
                <div class="form-group"><label class="form-label">گالری / داده تکمیلی</label><textarea name="gallery" class="form-control" rows="3" placeholder="JSON یا مسیرهای تصویر"><?php echo h($story['gallery'] ?? ''); ?></textarea></div>
                <?php if ($story['image']): ?><div class="preview-section"><p><strong>پیش‌نمایش تصویر فعلی:</strong></p><img src="/<?php echo h(ltrim($story['image'], '/')); ?>" alt="KEY About" class="preview-image"></div><?php endif; ?>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-success">💾 ذخیره بخش درباره</button><a href="dashboard.php" class="btn btn-secondary">بازگشت</a></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
