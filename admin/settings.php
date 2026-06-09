<?php
require_once __DIR__ . '/lib/admin_schema.php';
require_once __DIR__ . '/../core/models/Setting.php';
$currentAdmin = adminGuard('admin');
ensureAdminSchema();
$settingModel = new Setting();
$db = adminDb();
$pageTitle = 'تنظیمات سایت';
$message = '';
$error = '';

$catalog = [
    'general' => ['title' => 'General', 'fields' => [
        'site_name_fa' => ['label' => 'عنوان سایت', 'type' => 'text'],
        'site_description_fa' => ['label' => 'توضیح سایت', 'type' => 'textarea'],
        'default_language' => ['label' => 'زبان پیش‌فرض', 'type' => 'text'],
        'jalali_calendar_enabled' => ['label' => 'تقویم جلالی فعال', 'type' => 'boolean'],
    ]],
    'branding' => ['title' => 'Branding', 'fields' => [
        'logo_image' => ['label' => 'لوگو', 'type' => 'image'],
        'primary_color' => ['label' => 'رنگ اصلی', 'type' => 'text'],
        'accent_color' => ['label' => 'رنگ مکمل', 'type' => 'text'],
        'lotus_logo_image' => ['label' => 'تصویر لوگو', 'type' => 'image'],
        'lotus_title_fa' => ['label' => 'عنوان', 'type' => 'text'],
    ]],
    'social_networks' => ['title' => 'Social Networks', 'fields' => []],
    'seo' => ['title' => 'SEO', 'fields' => [
        'seo_title_fa' => ['label' => 'عنوان SEO', 'type' => 'text'],
        'seo_description_fa' => ['label' => 'توضیح SEO', 'type' => 'textarea'],
    ]],
    'contact' => ['title' => 'Contact', 'fields' => [
        'phone_number' => ['label' => 'تلفن', 'type' => 'text'],
        'email' => ['label' => 'ایمیل', 'type' => 'email'],
        'address_fa' => ['label' => 'آدرس فارسی', 'type' => 'textarea'],
        'address_en' => ['label' => 'آدرس انگلیسی', 'type' => 'textarea'],
        'opening_hours' => ['label' => 'ساعت کاری JSON', 'type' => 'textarea'],
    ]],
    'map' => ['title' => 'Map', 'fields' => [
        'balad_map_url' => ['label' => 'لینک نقشه بلد', 'type' => 'url'],
        'location_lat' => ['label' => 'Latitude', 'type' => 'text'],
        'location_lng' => ['label' => 'Longitude', 'type' => 'text'],
    ]],
    'advanced' => ['title' => 'Advanced', 'fields' => [
        'footer_quick_links_title_fa' => ['label' => 'عنوان لینک سریع فوتر', 'type' => 'text'],
        'footer_contact_title_fa' => ['label' => 'عنوان تماس فوتر', 'type' => 'text'],
        'footer_copyright_fa' => ['label' => 'متن کپی‌رایت', 'type' => 'text'],
    ]],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrf();
        foreach ($catalog as $category => $group) {
            foreach ($group['fields'] as $key => $meta) {
                $value = $settingModel->get($key, '');
                if ($meta['type'] === 'image') {
                    $value = uploadAdminImage($key, 'settings', (string)$value);
                } elseif ($meta['type'] === 'boolean') {
                    $value = isset($_POST[$key]) ? '1' : '0';
                } else {
                    $value = trim((string)($_POST[$key] ?? ''));
                }
                $type = in_array($meta['type'], ['url','email'], true) ? $meta['type'] : ($meta['type'] === 'boolean' ? 'boolean' : ($key === 'opening_hours' ? 'json' : 'text'));
                if ($type === 'json' && $value !== '') {
                    $decodedJson = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new RuntimeException('فیلد «' . ($meta['label'] ?? $key) . '» باید JSON معتبر باشد.');
                    }
                    $value = json_encode($decodedJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, category, is_public) VALUES (:k,:v,:t,:c,1) ON DUPLICATE KEY UPDATE setting_value=:update_v, setting_type=:update_t, category=:update_c');
                $stmt->execute(['k' => $key, 'v' => $value, 't' => $type, 'c' => $category, 'update_v' => $value, 'update_t' => $type, 'update_c' => $category]);
            }
        }
        $message = 'تنظیمات ذخیره شد.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
    <?php foreach ($catalog as $category => $group): ?>
        <div class="card">
            <div class="card-header"><h2><?php echo h($group['title']); ?></h2></div>
            <div class="card-body">
                <?php if ($category === 'social_networks' && empty($group['fields'])): ?>
                    <p class="text-muted">شبکه‌های اجتماعی از دیتابیس و صفحه مدیریت اختصاصی خوانده می‌شوند؛ هیچ لینک اجتماعی در قالب hardcode نمی‌شود.</p>
                    <a class="btn btn-primary" href="social-links.php">مدیریت شبکه‌های اجتماعی</a>
                <?php endif; ?>
                <?php foreach ($group['fields'] as $key => $meta): $value = $settingModel->get($key, ''); ?>
                    <div class="form-group">
                        <label><?php echo h($meta['label']); ?> <small class="text-muted">(<?php echo h($key); ?>)</small></label>
                        <?php if ($meta['type'] === 'textarea'): ?>
                            <textarea class="form-control" name="<?php echo h($key); ?>"><?php echo h(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value); ?></textarea>
                        <?php elseif ($meta['type'] === 'boolean'): ?>
                            <label><input type="checkbox" name="<?php echo h($key); ?>" value="1" <?php echo $value ? 'checked' : ''; ?>> فعال</label>
                        <?php elseif ($meta['type'] === 'image'): ?>
                            <input class="form-control" type="file" name="<?php echo h($key); ?>" accept="image/*">
                            <?php if ($value): ?><p><img src="../<?php echo h(ltrim((string)$value, '/')); ?>" style="max-width:140px;border-radius:8px"></p><?php endif; ?>
                        <?php else: ?>
                            <input class="form-control" type="<?php echo h($meta['type']); ?>" name="<?php echo h($key); ?>" value="<?php echo h($value); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-success" type="submit">ذخیره تنظیمات</button>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
