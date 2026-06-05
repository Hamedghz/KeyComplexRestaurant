<?php
require_once __DIR__ . '/lib/admin_schema.php';
require_once __DIR__ . '/../core/models/Setting.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$settingModel = new Setting();
$pageTitle = 'مدیریت درباره KEY';
$error = '';
$success = '';

function saveContentSetting(PDO $db, string $key, string $value, string $type = 'text'): void {
    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, category, is_public) VALUES (:k, :v, :t, :c, 1) ON DUPLICATE KEY UPDATE setting_value = :uv, setting_type = :ut, category = :uc, is_public = 1');
    $stmt->execute([
        'k' => $key,
        'v' => $value,
        't' => $type,
        'c' => 'content',
        'uv' => $value,
        'ut' => $type,
        'uc' => 'content',
    ]);
}

$story = [
    'title' => (string)$settingModel->get('about_title_fa', 'درباره مجموعه'),
    'description' => (string)$settingModel->get('about_content_fa', '<p>روایت طعم‌های اصیل، قهوه‌های منتخب و میزبانی گرم در فضایی لوکس و آرام.</p>'),
    'image' => (string)$settingModel->get('about_image', ''),
    'active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrf();
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $image = trim((string)($_POST['image'] ?? $story['image']));

        if ($title === '') {
            throw new RuntimeException('عنوان درباره الزامی است.');
        }
        if ($description === '') {
            throw new RuntimeException('متن درباره الزامی است.');
        }

        $image = uploadAdminImage('image_upload', 'key-story', $image);

        saveContentSetting($db, 'about_title_fa', $title, 'text');
        saveContentSetting($db, 'about_content_fa', $description, 'text');
        saveContentSetting($db, 'about_image', $image, 'url');

        $success = 'بخش درباره صفحه اصلی با موفقیت ذخیره شد.';
        $story = ['title' => $title, 'description' => $description, 'image' => $image, 'active' => 1];
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
        <p class="text-muted">این صفحه مقدارهای <code>about_title_fa</code>، <code>about_content_fa</code> و <code>about_image</code> را مدیریت می‌کند و صفحه اصلی مستقیماً همین کلیدها را نمایش می‌دهد.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <div class="form-section">
                <h3>اطلاعات اصلی</h3>
                <div class="form-group"><label class="form-label">عنوان درباره</label><input type="text" name="title" class="form-control" value="<?php echo h($story['title']); ?>" required></div>
                <div class="form-group"><label class="form-label">متن درباره</label><textarea name="description" class="form-control" rows="8" required><?php echo h($story['description']); ?></textarea><small>HTML امن مانند پاراگراف، لیست و لینک در صفحه اصلی پشتیبانی می‌شود.</small></div>
            </div>
            <div class="form-section">
                <h3>تصویر درباره</h3>
                <div class="form-group"><label class="form-label">آپلود تصویر جدید</label><input type="file" name="image_upload" class="form-control" accept="image/jpeg,image/png,image/webp"><small>فرمت‌های مجاز: JPG, PNG, WEBP</small></div>
                <div class="form-group"><label class="form-label">یا آدرس تصویر</label><input type="text" name="image" class="form-control" value="<?php echo h($story['image']); ?>" placeholder="uploads/key-story/image.jpg"></div>
                <?php if ($story['image']): ?><div class="preview-section"><p><strong>پیش‌نمایش تصویر فعلی:</strong></p><img src="/<?php echo h(ltrim($story['image'], '/')); ?>" alt="KEY About" class="preview-image"></div><?php endif; ?>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-success">💾 ذخیره بخش درباره</button><a href="dashboard.php" class="btn btn-secondary">بازگشت</a></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
