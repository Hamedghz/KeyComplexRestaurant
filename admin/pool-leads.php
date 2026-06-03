<?php
require_once __DIR__ . '/lib/admin_crud.php';
$currentAdmin = adminGuard('manager');
$db = adminDb();
$pageTitle = 'مدیریت لیدهای استخر';
$error = '';
$success = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireValidCsrf();
    
    if ($_POST['action'] === 'update_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $db->prepare("UPDATE pool_leads SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $success = 'وضعیت با موفقیت به‌روزرسانی شد.';
        } catch (Throwable $e) {
            $error = 'خطا در به‌روزرسانی: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM pool_leads WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'لید با موفقیت حذف شد.';
        } catch (Throwable $e) {
            $error = 'خطا در حذف: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'add_note') {
        $id = (int)$_POST['id'];
        $notes = trim($_POST['notes']);
        
        try {
            $stmt = $db->prepare("UPDATE pool_leads SET notes = ? WHERE id = ?");
            $stmt->execute([$notes, $id]);
            $success = 'یادداشت با موفقیت ذخیره شد.';
        } catch (Throwable $e) {
            $error = 'خطا در ذخیره یادداشت: ' . $e->getMessage();
        }
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterSource = $_GET['source'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$where = ['1=1'];
$params = [];

if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}

if ($filterSource !== '') {
    $where[] = 'acquisition_source = ?';
    $params[] = $filterSource;
}

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR mobile LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Get leads
$stmt = $db->prepare("SELECT * FROM pool_leads WHERE $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Get acquisition sources
$sourcesStmt = $db->query("SELECT DISTINCT acquisition_source FROM pool_leads WHERE acquisition_source IS NOT NULL ORDER BY acquisition_source");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_COLUMN);

// Statistics
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM pool_leads
");
$stats = $statsStmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.stat-card h3 {
    font-size: 32px;
    margin-bottom: 5px;
}

.stat-card p {
    color: #666;
    font-size: 14px;
}

.stat-new { border-left: 4px solid #17a2b8; }
.stat-contacted { border-left: 4px solid #ffc107; }
.stat-converted { border-left: 4px solid #28a745; }
.stat-rejected { border-left: 4px solid #dc3545; }

.filter-form {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.badge {
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
}

.badge-new { background: #d1ecf1; color: #0c5460; }
.badge-contacted { background: #fff3cd; color: #856404; }
.badge-converted { background: #d4edda; color: #155724; }
.badge-rejected { background: #f8d7da; color: #721c24; }

.notes-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.action-buttons {
    display: flex;
    gap: 5px;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="stats-row">
    <div class="stat-card stat-new">
        <h3><?php echo h($stats['total']); ?></h3>
        <p>کل لیدها</p>
    </div>
    <div class="stat-card stat-new">
        <h3><?php echo h($stats['new']); ?></h3>
        <p>جدید</p>
    </div>
    <div class="stat-card stat-contacted">
        <h3><?php echo h($stats['contacted']); ?></h3>
        <p>تماس گرفته شده</p>
    </div>
    <div class="stat-card stat-converted">
        <h3><?php echo h($stats['converted']); ?></h3>
        <p>تبدیل شده</p>
    </div>
    <div class="stat-card stat-rejected">
        <h3><?php echo h($stats['rejected']); ?></h3>
        <p>رد شده</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>فیلترها و جستجو</h2>
    </div>
    <div class="card-body">
        <form method="get" class="filter-form">
            <input type="text" name="search" class="form-control" placeholder="جستجو نام یا موبایل" value="<?php echo h($search); ?>" style="flex: 1; min-width: 200px;">
            
            <select name="status" class="form-control" style="width: 150px;">
                <option value="">همه وضعیت‌ها</option>
                <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>جدید</option>
                <option value="contacted" <?php echo $filterStatus === 'contacted' ? 'selected' : ''; ?>>تماس گرفته شده</option>
                <option value="converted" <?php echo $filterStatus === 'converted' ? 'selected' : ''; ?>>تبدیل شده</option>
                <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
            </select>
            
            <select name="source" class="form-control" style="width: 150px;">
                <option value="">همه منابع</option>
                <?php foreach ($sources as $source): ?>
                    <option value="<?php echo h($source); ?>" <?php echo $filterSource === $source ? 'selected' : ''; ?>>
                        <?php echo h($source); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
            <a href="pool-leads.php" class="btn btn-secondary">پاک کردن</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>لیست لیدهای استخر (<?php echo count($leads); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام کامل</th>
                        <th>موبایل</th>
                        <th>منبع جذب</th>
                        <th>وضعیت</th>
                        <th>یادداشت</th>
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><?php echo h($lead['id']); ?></td>
                            <td><?php echo h($lead['full_name']); ?></td>
                            <td><a href="tel:<?php echo h($lead['mobile']); ?>"><?php echo h($lead['mobile']); ?></a></td>
                            <td><?php echo h($lead['acquisition_source'] ?? '-'); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?php echo h($lead['id']); ?>">
                                    <select name="status" class="badge badge-<?php echo h($lead['status']); ?>" onchange="this.form.submit()">
                                        <option value="new" <?php echo $lead['status'] === 'new' ? 'selected' : ''; ?>>جدید</option>
                                        <option value="contacted" <?php echo $lead['status'] === 'contacted' ? 'selected' : ''; ?>>تماس گرفته</option>
                                        <option value="converted" <?php echo $lead['status'] === 'converted' ? 'selected' : ''; ?>>تبدیل شده</option>
                                        <option value="rejected" <?php echo $lead['status'] === 'rejected' ? 'selected' : ''; ?>>رد شده</option>
                                    </select>
                                </form>
                            </td>
                            <td class="notes-cell" title="<?php echo h($lead['notes']); ?>"><?php echo h($lead['notes'] ?: '-'); ?></td>
                            <td><?php echo h(date('Y-m-d H:i', strtotime($lead['created_at']))); ?></td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-info" onclick="editNote(<?php echo h($lead['id']); ?>, '<?php echo h(addslashes($lead['notes'] ?? '')); ?>')">📝</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید؟');">
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo h($lead['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editNote(id, currentNote) {
    const note = prompt('یادداشت:', currentNote);
    if (note !== null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="notes" value="${note}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
