<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('manager');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'مدیریت لیدهای استخر';
$error = '';
$success = '';
$poolOptions = [
    'استخر هامون',
    'استخر دهکده المپیک',
    'استخر خانه شنا',
];
$customerTypes = [
    'ادارات',
    'تفریحی',
    'آموزشی',
    'آب درمانی',
];

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
            safeAdminLog('Pool lead status update failed: ' . $e->getMessage());
            $error = 'خطا در به‌روزرسانی وضعیت رخ داد.';
        }
    } elseif ($_POST['action'] === 'update_customer_type') {
        $id = (int)$_POST['id'];
        $customerType = trim((string)($_POST['customer_type'] ?? ''));

        if ($customerType !== '' && !in_array($customerType, $customerTypes, true)) {
            $error = 'نوع مشتری نامعتبر است.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE pool_leads SET customer_type = ? WHERE id = ?");
                $stmt->execute([$customerType !== '' ? $customerType : null, $id]);
                $success = 'نوع مشتری با موفقیت به‌روزرسانی شد.';
            } catch (Throwable $e) {
                safeAdminLog('Pool lead customer type update failed: ' . $e->getMessage());
                $error = 'خطا در به‌روزرسانی نوع مشتری رخ داد.';
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $db->prepare("DELETE FROM pool_leads WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'لید با موفقیت حذف شد.';
        } catch (Throwable $e) {
            safeAdminLog('Pool lead delete failed: ' . $e->getMessage());
            $error = 'خطا در حذف لید رخ داد.';
        }
    } elseif ($_POST['action'] === 'add_note') {
        $id = (int)$_POST['id'];
        $notes = trim($_POST['notes']);
        
        try {
            $stmt = $db->prepare("UPDATE pool_leads SET notes = ? WHERE id = ?");
            $stmt->execute([$notes, $id]);
            $success = 'یادداشت با موفقیت ذخیره شد.';
        } catch (Throwable $e) {
            safeAdminLog('Pool lead note update failed: ' . $e->getMessage());
            $error = 'خطا در ذخیره یادداشت رخ داد.';
        }
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterSource = $_GET['source'] ?? '';
$filterPool = $_GET['pool_name'] ?? '';
$filterCustomerType = $_GET['customer_type'] ?? '';
$search = trim($_GET['search'] ?? '');
$export = (string)($_GET['export'] ?? '') === 'csv';

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

if ($filterPool !== '') {
    $where[] = 'pool_name = ?';
    $params[] = $filterPool;
}

if ($filterCustomerType !== '') {
    $where[] = 'customer_type = ?';
    $params[] = $filterCustomerType;
}

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR mobile LIKE ? OR pool_name LIKE ? OR acquisition_source LIKE ? OR customer_type LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Get leads
$stmt = $db->prepare("SELECT * FROM pool_leads WHERE $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$leads = $stmt->fetchAll();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pool-leads-' . date('Ymd-His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, [
        'id',
        'full_name',
        'mobile',
        'pool_name',
        'customer_type',
        'acquisition_source',
        'status',
        'notes',
        'created_at',
        'updated_at',
    ]);
    foreach ($leads as $lead) {
        fputcsv($out, [
            $lead['id'] ?? '',
            $lead['full_name'] ?? '',
            $lead['mobile'] ?? '',
            $lead['pool_name'] ?? '',
            $lead['customer_type'] ?? '',
            $lead['acquisition_source'] ?? '',
            $lead['status'] ?? '',
            $lead['notes'] ?? '',
            $lead['created_at'] ?? '',
            $lead['updated_at'] ?? '',
        ]);
    }
    exit;
}

// Get acquisition sources
$sourcesStmt = $db->query("SELECT DISTINCT acquisition_source FROM pool_leads WHERE acquisition_source IS NOT NULL ORDER BY acquisition_source");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get pool options, including legacy/custom values if any exist.
$poolStmt = $db->query("SELECT DISTINCT pool_name FROM pool_leads WHERE pool_name IS NOT NULL AND pool_name <> '' ORDER BY pool_name");
$existingPools = $poolStmt->fetchAll(PDO::FETCH_COLUMN);
$poolOptions = array_values(array_unique(array_merge($poolOptions, $existingPools)));

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

$poolStatsStmt = $db->query("
    SELECT COALESCE(NULLIF(pool_name, ''), '-') AS pool_name, COUNT(*) AS total
    FROM pool_leads
    GROUP BY COALESCE(NULLIF(pool_name, ''), '-')
    ORDER BY total DESC, pool_name ASC
");
$poolStats = $poolStatsStmt->fetchAll();

$customerTypeStatsStmt = $db->query("
    SELECT COALESCE(NULLIF(customer_type, ''), '-') AS customer_type, COUNT(*) AS total
    FROM pool_leads
    WHERE customer_type IS NOT NULL AND customer_type <> ''
    GROUP BY COALESCE(NULLIF(customer_type, ''), '-')
    ORDER BY total DESC, customer_type ASC
");
$customerTypeStats = $customerTypeStatsStmt->fetchAll();

$filteredStatsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new,
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS contacted,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
    FROM pool_leads
    WHERE $whereClause
");
$filteredStatsStmt->execute($params);
$filteredStats = $filteredStatsStmt->fetch() ?: ['total' => 0, 'new' => 0, 'contacted' => 0, 'converted' => 0, 'rejected' => 0];

$sourceSummaryStmt = $db->prepare("
    SELECT COALESCE(NULLIF(acquisition_source, ''), '-') AS source, COUNT(*) AS total
    FROM pool_leads
    WHERE $whereClause
    GROUP BY COALESCE(NULLIF(acquisition_source, ''), '-')
    ORDER BY total DESC, source ASC
    LIMIT 12
");
$sourceSummaryStmt->execute($params);
$sourceSummary = $sourceSummaryStmt->fetchAll();

$exportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv']));

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

<?php if ($poolStats): ?>
<div class="stats-row">
    <?php foreach ($poolStats as $poolStat): ?>
        <div class="stat-card">
            <h3><?php echo h($poolStat['total']); ?></h3>
            <p><?php echo h($poolStat['pool_name'] ?: '-'); ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="stats-row">
    <div class="stat-card">
        <h3><?php echo h($filteredStats['total'] ?? 0); ?></h3>
        <p>نتیجه فیلتر فعلی</p>
    </div>
    <div class="stat-card">
        <h3><?php echo h($filteredStats['new'] ?? 0); ?></h3>
        <p>جدید در فیلتر</p>
    </div>
    <div class="stat-card">
        <h3><?php echo h($filteredStats['converted'] ?? 0); ?></h3>
        <p>تبدیل شده در فیلتر</p>
    </div>
</div>

<?php if ($customerTypeStats): ?>
<div class="stats-row">
    <?php foreach ($customerTypeStats as $customerTypeStat): ?>
        <div class="stat-card">
            <h3><?php echo h($customerTypeStat['total']); ?></h3>
            <p>تعداد <?php echo h($customerTypeStat['customer_type'] ?: '-'); ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>فیلترها و جستجو</h2>
        <a class="btn btn-primary" href="<?php echo h($exportUrl); ?>">خروجی CSV</a>
    </div>
    <div class="card-body">
        <form method="get" class="filter-form">
            <input type="text" name="search" class="form-control" placeholder="جستجو نام، موبایل یا استخر" value="<?php echo h($search); ?>" style="flex: 1; min-width: 200px;">
            
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

            <select name="pool_name" class="form-control" style="width: 190px;">
                <option value="">همه استخرها</option>
                <?php foreach ($poolOptions as $poolOption): ?>
                    <option value="<?php echo h($poolOption); ?>" <?php echo $filterPool === $poolOption ? 'selected' : ''; ?>>
                        <?php echo h($poolOption); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="customer_type" class="form-control" style="width: 180px;">
                <option value="">همه نوع مشتری‌ها</option>
                <?php foreach ($customerTypes as $customerType): ?>
                    <option value="<?php echo h($customerType); ?>" <?php echo $filterCustomerType === $customerType ? 'selected' : ''; ?>>
                        <?php echo h($customerType); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
            <a href="pool-leads.php" class="btn btn-secondary">پاک کردن</a>
        </form>
    </div>
</div>

<?php if ($sourceSummary): ?>
<div class="card">
    <div class="card-header"><h2>خلاصه منابع در فیلتر فعلی</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>منبع جذب</th><th>تعداد لید</th></tr></thead>
                <tbody>
                    <?php foreach ($sourceSummary as $sourceRow): ?>
                        <tr><td><?php echo h($sourceRow['source']); ?></td><td><?php echo h($sourceRow['total']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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
                        <th>استخر</th>
                        <th>نوع مشتری</th>
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
                            <td><?php echo h(($lead['pool_name'] ?? '') !== '' ? $lead['pool_name'] : '-'); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
                                    <input type="hidden" name="action" value="update_customer_type">
                                    <input type="hidden" name="id" value="<?php echo h($lead['id']); ?>">
                                    <select name="customer_type" class="form-control" onchange="this.form.submit()">
                                        <option value="" <?php echo ($lead['customer_type'] ?? '') === '' ? 'selected' : ''; ?>>-</option>
                                        <?php foreach ($customerTypes as $customerType): ?>
                                            <option value="<?php echo h($customerType); ?>" <?php echo ($lead['customer_type'] ?? '') === $customerType ? 'selected' : ''; ?>>
                                                <?php echo h($customerType); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo h(($lead['acquisition_source'] ?? '') !== '' ? $lead['acquisition_source'] : '-'); ?></td>
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
