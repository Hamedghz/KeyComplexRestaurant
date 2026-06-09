<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('employee');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'جزئیات سفارش';
$error = '';
$order = null;
$items = [];
$id = (int)($_GET['id'] ?? 0);
try {
    if ($id <= 0) {
        throw new RuntimeException('شناسه سفارش معتبر نیست.');
    }
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('سفارش مورد نظر یافت نشد.');
    }
    $itemStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemStmt->execute([$id]);
    $items = $itemStmt->fetchAll();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<?php if ($order): ?>
<div class="card">
    <div class="card-header"><h2>سفارش <?php echo h($order['order_number']); ?></h2><a class="btn" href="orders.php">بازگشت به سفارشات</a></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6"><p><strong>مشتری:</strong> <?php echo h($order['customer_name']); ?></p><p><strong>موبایل:</strong> <?php echo h($order['customer_phone']); ?></p><p><strong>ایمیل:</strong> <?php echo h($order['customer_email']); ?></p><p><strong>آدرس:</strong> <?php echo h($order['delivery_address']); ?></p></div>
            <div class="col-md-6"><p><strong>نوع سفارش:</strong> <?php echo h($order['order_type']); ?></p><p><strong>وضعیت پرداخت:</strong> <?php echo h($order['payment_status']); ?></p><p><strong>وضعیت سفارش:</strong> <?php echo h($order['order_status']); ?></p><p><strong>تاریخ:</strong> <?php echo h(formatJalaliDateTime($order['created_at'])); ?></p></div>
        </div>
        <p><strong>یادداشت مشتری:</strong> <?php echo h($order['notes']); ?></p>
        <p><strong>یادداشت مدیریت:</strong> <?php echo h($order['admin_notes']); ?></p>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>آیتم‌های سفارش</h2></div>
    <div class="card-body"><div class="table-responsive"><table class="table">
        <thead><tr><th>آیتم</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th><th>یادداشت</th></tr></thead><tbody>
        <?php foreach ($items as $item): ?><tr><td><?php echo h($item['item_name_fa'] ?: $item['item_name_en']); ?></td><td><?php echo h($item['quantity']); ?></td><td><?php echo h(number_format((float)$item['unit_price'])); ?></td><td><?php echo h(number_format((float)$item['subtotal'])); ?></td><td><?php echo h($item['notes']); ?></td></tr><?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="text-center text-muted">آیتمی برای این سفارش ثبت نشده است.</td></tr><?php endif; ?>
        </tbody><tfoot><tr><th colspan="3">مبلغ کل</th><th><?php echo h(number_format((float)$order['total'])); ?></th><th></th></tr></tfoot>
    </table></div></div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
