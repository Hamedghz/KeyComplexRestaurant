<?php
require_once __DIR__ . '/../core/models/CrmCustomer.php';
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard();
$model = new CrmCustomer();
$customer = $model->find((int)($_GET['id'] ?? 0));
$pageTitle = 'پروفایل مشتری'; include __DIR__.'/includes/header.php';
?>
<div class="card"><div class="card-header"><h2>پروفایل مشتری</h2></div><div class="card-body">
<?php if (!$customer): ?><p>مشتری یافت نشد.</p><?php else: ?>
<h3><?=h($customer['full_name'])?> - <?=h($customer['mobile'])?></h3>
<p>برچسب‌ها: <?=h($customer['tags'])?> | آخرین مراجعه: <?=h(formatJalaliDateTime($customer['last_visit_date'], false))?></p>
<h3>تایم‌لاین</h3><table class="table"><tr><th>نوع</th><th>عنوان</th><th>تاریخ</th></tr><?php foreach($model->timeline($customer['id']) as $t): ?><tr><td><?=h($t['event_type'])?></td><td><?=h($t['title'])?></td><td><?=h(formatJalaliDateTime($t['event_date']))?></td></tr><?php endforeach; ?></table>
<h3>تاریخچه خرید</h3><table class="table"><tr><th>سفارش</th><th>مبلغ</th><th>تاریخ</th></tr><?php foreach($model->purchases($customer['mobile']) as $o): ?><tr><td><?=h($o['order_number'])?></td><td><?=h(number_format((float)$o['total']))?></td><td><?=h(formatJalaliDateTime($o['created_at']))?></td></tr><?php endforeach; ?></table>
<?php endif; ?></div></div><?php include __DIR__.'/includes/footer.php'; ?>
