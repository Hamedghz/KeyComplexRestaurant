<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('employee');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'سفارشات';
$error = '';
$rows = [];
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$allowedStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(order_number LIKE :q OR customer_name LIKE :q OR customer_phone LIKE :q OR customer_email LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'order_status = :status';
    $params['status'] = $status;
}
try {
    $stmt = $db->prepare('SELECT id, order_number, customer_name, customer_phone, total, payment_status, order_status, created_at FROM orders WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 100');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    safeAdminLog('Orders list failed: ' . $e->getMessage());
    $error = 'لیست سفارشات در حال حاضر قابل نمایش نیست. جزئیات خطا در لاگ سیستم ثبت شد.';
}
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card">
    <div class="card-header"><h2>سفارشات ثبت‌شده</h2></div>
    <div class="card-body">
        <form class="admin-filter" method="get">
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="جستجوی شماره، نام یا موبایل">
            <select class="form-control" name="status">
                <option value="">همه وضعیت‌ها</option>
                <?php foreach ($allowedStatuses as $orderStatus): ?><option value="<?php echo h($orderStatus); ?>" <?php echo $status === $orderStatus ? 'selected' : ''; ?>><?php echo h($orderStatus); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary">فیلتر</button>
        </form>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>شماره</th><th>مشتری</th><th>موبایل</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?><tr>
                <td><?php echo h($r['order_number']); ?></td><td><?php echo h($r['customer_name']); ?></td><td><?php echo h($r['customer_phone']); ?></td><td><?php echo h(number_format((float)$r['total'])); ?></td><td><?php echo h($r['payment_status']); ?></td><td><?php echo h($r['order_status']); ?></td><td><?php echo h(formatJalaliDateTime($r['created_at'])); ?></td><td><a class="btn btn-sm btn-info" href="order-detail.php?id=<?php echo h($r['id']); ?>">مشاهده</a></td>
            </tr><?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted">سفارشی یافت نشد.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
