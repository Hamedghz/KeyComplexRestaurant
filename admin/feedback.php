<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'بازخوردها';
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE feedback SET admin_response=?, is_approved=?, is_featured=?, responded_at=NOW() WHERE id=?')->execute([trim((string)$_POST['admin_response']), isset($_POST['is_approved']) ? 1 : 0, isset($_POST['is_featured']) ? 1 : 0, $id]);
        redirectTo('feedback.php?saved=1');
    }
    if (($_GET['action'] ?? '') === 'export') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="feedback.xls"');
        echo "<html><meta charset='utf-8'><body><table border='1'><tr><th>source</th><th>name</th><th>phone</th><th>email</th><th>rating/data</th><th>text</th><th>date</th></tr>";
        foreach (loadFeedbackRows($db) as $r) echo '<tr><td>'.h($r['source']).'</td><td>'.h($r['customer_name']).'</td><td>'.h($r['customer_phone']).'</td><td>'.h($r['customer_email']).'</td><td>'.h($r['rating']).'</td><td>'.h($r['review_text']).'</td><td>'.h($r['created_at']).'</td></tr>';
        echo '</table></body></html>'; exit;
    }
} catch (Throwable $e) { safeAdminLog('Feedback admin failed: ' . $e->getMessage()); $error = 'عملیات بازخورد انجام نشد. جزئیات خطا در لاگ سیستم ثبت شد.'; }
function loadFeedbackRows(PDO $db): array {
    $q = trim((string)($_GET['q'] ?? '')); $source = $_GET['source'] ?? ''; $rows = [];
    if ($source === '' || $source === 'feedback') {
        $where='1=1'; $params=[]; if($q!==''){ $where='customer_name LIKE :q OR customer_phone LIKE :q OR customer_email LIKE :q OR review_text LIKE :q'; $params['q']='%'.$q.'%'; }
        $stmt=$db->prepare("SELECT 'feedback' source,id,customer_name,customer_phone,customer_email,rating,review_text,admin_response,is_approved,is_featured,created_at FROM feedback WHERE $where ORDER BY created_at DESC LIMIT 300"); $stmt->execute($params); $rows=array_merge($rows,$stmt->fetchAll());
    }
    if (($source === '' || $source === 'survey') && adminTableExists('survey_responses')) {
        $where='1=1'; $params=[]; if($q!==''){ $where='sr.customer_name LIKE :q OR sr.customer_phone LIKE :q OR sr.customer_email LIKE :q OR sr.response_data LIKE :q'; $params['q']='%'.$q.'%'; }
        $stmt=$db->prepare("SELECT 'survey' source,sr.id,sr.customer_name,sr.customer_phone,sr.customer_email,'' rating,CAST(sr.response_data AS CHAR) review_text,'' admin_response,1 is_approved,0 is_featured,sr.submitted_at created_at FROM survey_responses sr WHERE $where ORDER BY sr.submitted_at DESC LIMIT 300"); $stmt->execute($params); $rows=array_merge($rows,$stmt->fetchAll());
    }
    usort($rows, fn($a,$b)=>strcmp((string)$b['created_at'], (string)$a['created_at']));
    return $rows;
}
$rows = loadFeedbackRows($db);
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2>بازخورد مشتری، نظرسنجی و فرم تماس</h2><a class="btn" href="?action=export&source=<?php echo h($_GET['source'] ?? ''); ?>&q=<?php echo h($_GET['q'] ?? ''); ?>">Export Excel</a></div><div class="card-body"><form class="admin-filter"><select class="form-control" name="source"><option value="">همه منابع</option><option value="feedback">Feedback</option><option value="survey">Survey</option></select><input class="form-control" name="q" value="<?php echo h($_GET['q'] ?? ''); ?>" placeholder="جستجو"><button class="btn btn-primary">فیلتر</button></form><div class="table-responsive"><table class="table"><thead><tr><th>منبع</th><th>نام</th><th>تماس</th><th>امتیاز</th><th>متن/داده</th><th>تاریخ</th><th>پاسخ مدیر</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?php echo h($r['source']); ?></td><td><?php echo h($r['customer_name']); ?></td><td><?php echo h($r['customer_phone'].' '.$r['customer_email']); ?></td><td><?php echo h($r['rating']); ?></td><td style="max-width:360px"><?php echo h(mb_substr((string)$r['review_text'],0,300)); ?></td><td><?php echo h($r['created_at']); ?></td><td><?php if($r['source']==='feedback'): ?><form method="post"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($r['id']); ?>"><textarea class="form-control" name="admin_response"><?php echo h($r['admin_response']); ?></textarea><label><input type="checkbox" name="is_approved" <?php echo $r['is_approved']?'checked':''; ?>> تایید</label><label><input type="checkbox" name="is_featured" <?php echo $r['is_featured']?'checked':''; ?>> ویژه</label><button class="btn btn-sm btn-success">ذخیره</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
