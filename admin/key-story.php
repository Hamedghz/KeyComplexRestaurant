<?php
require_once __DIR__ . '/lib/admin_crud.php';
$currentAdmin = adminGuard('manager');
$db = adminDb();
$pageTitle = 'مدیریت داستان KEY';
$error = '';
$success = '';

// Ensure table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `key_story_settings` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NULL,
        `subtitle` VARCHAR(255) NULL,
        `description` TEXT NULL,
        `image` VARCHAR(500) NULL,
        `gallery` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // Table might already exist
}

// Get current story or create default
$stmt = $db->query("SELECT * FROM key_story_settings ORDER BY id DESC LIMIT 1");
$story = $stmt->fetch();

if (!$story) {
    $db->exec("INSERT INTO key_story_settings (title, subtitle, description, active) VALUES 
        ('داستان KEY', 'سفری در دل طعم و معنا', 'KEY رستوران و کافه، جایی که هر لحظه، خاطره‌ای تازه می‌سازیم. از بهترین مواد اولیه تا خدمات بی‌نظیر، همه چیز اینجا برای شما است.', 1)");
    $story = $db->query("SELECT * FROM key_story_settings ORDER BY id DESC LIMIT 1")->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $gallery = trim($_POST['gallery'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    
    try {
        // Handle image upload
        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/key-story/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extension = strtolower(pathinfo($_FILES['image_upload']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($extension, $allowedExtensions)) {
                $filename = 'key-story-' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $uploadPath)) {
                    $image = 'uploads/key-story/' . $filename;
                }
            }
        }
        
        $stmt = $db->prepare("UPDATE key_story_settings SET 
            title = ?, 
            subtitle = ?, 
            description = ?, 
            image = ?, 
            gallery = ?, 
            active = ? 
            WHERE id = ?");
        
        $stmt->execute([$title, $subtitle, $description, $image, $gallery, $active, $story['id']]);
        
        $success = 'تغییرات با موفقیت ذخیره شد.';
        
        // Refresh data
        $story = $db->query("SELECT * FROM key_story_settings WHERE id = " . (int)$story['id'])->fetch();
        
    } catch (Throwable $e) {
        $error = 'خطا در ذخیره: ' . $e->getMessage();
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
.form-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent);
}

.preview-section {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
}

.preview-image {
    max-width: 100%;
    max-height: 400px;
    border-radius: 10px;
    margin: 20px 0;
}

.gallery-preview {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.gallery-thumb {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.checkbox-wrapper input[type="checkbox"] {
    width: 20px;
    height: 20px;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>تنظیمات داستان KEY</h2>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            
            <div class="form-section">
                <h3>اطلاعات اصلی</h3>
                
                <div class="form-group">
                    <label class="form-label">عنوان</label>
                    <input type="text" name="title" class="form-control" value="<?php echo h($story['title']); ?>" placeholder="داستان KEY">
                </div>
                
                <div class="form-group">
                    <label class="form-label">زیرعنوان</label>
                    <input type="text" name="subtitle" class="form-control" value="<?php echo h($story['subtitle']); ?>" placeholder="سفری در دل طعم و معنا">
                </div>
                
                <div class="form-group">
                    <label class="form-label">توضیحات</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="شرح کامل داستان KEY..."><?php echo h($story['description']); ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>تصویر اصلی</h3>
                
                <div class="form-group">
                    <label class="form-label">آپلود تصویر جدید</label>
                    <input type="file" name="image_upload" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small>فرمت‌های مجاز: JPG, PNG, WEBP</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">یا آدرس تصویر</label>
                    <input type="text" name="image" class="form-control" value="<?php echo h($story['image']); ?>" placeholder="uploads/key-story/image.jpg">
                </div>
                
                <?php if ($story['image']): ?>
                    <div class="preview-section">
                        <p><strong>پیش‌نمایش تصویر فعلی:</strong></p>
                        <img src="/<?php echo h($story['image']); ?>" alt="KEY Story" class="preview-image">
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-section">
                <h3>گالری تصاویر</h3>
                
                <div class="form-group">
                    <label class="form-label">آدرس تصاویر (با کاما جدا شوند)</label>
                    <textarea name="gallery" class="form-control" rows="3" placeholder="uploads/gallery/1.jpg, uploads/gallery/2.jpg, uploads/gallery/3.jpg"><?php echo h($story['gallery']); ?></textarea>
                    <small>هر آدرس را با کاما (,) از هم جدا کنید</small>
                </div>
                
                <?php if ($story['gallery']): ?>
                    <div class="gallery-preview">
                        <?php 
                        $galleryImages = array_filter(array_map('trim', explode(',', $story['gallery'])));
                        foreach ($galleryImages as $img): 
                        ?>
                            <img src="/<?php echo h($img); ?>" alt="Gallery" class="gallery-thumb">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-section">
                <h3>وضعیت نمایش</h3>
                
                <div class="checkbox-wrapper">
                    <input type="checkbox" name="active" id="active" value="1" <?php echo $story['active'] ? 'checked' : ''; ?>>
                    <label for="active">نمایش در وب‌سایت</label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">💾 ذخیره تغییرات</button>
                <a href="dashboard.php" class="btn btn-secondary">بازگشت</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
