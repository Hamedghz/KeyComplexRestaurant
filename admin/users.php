<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('admin');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'کاربران و RBAC';
$message = '';
$error = '';
$roles = ['super_admin'=>'Super Admin','admin'=>'Admin','manager'=>'Manager','employee'=>'Employee'];
$departments = ['restaurant'=>'Restaurant','technology'=>'Technology','marketing'=>'Marketing','office'=>'Office','management'=>'Management'];
$permissionCatalog = [
    'dashboard' => 'Dashboard',
    'crm' => 'CRM',
    'orders' => 'Orders',
    'menu' => 'Menu Management',
    'media' => 'Media Library',
    'settings' => 'Settings',
    'employee_evaluations' => 'Peer Evaluations',
    'employee_evaluation_settings' => 'Evaluation Settings',
    'employee_performance' => 'Employee Performance',
    'employee_recalculate_scores' => 'Recalculate Employee Scores',
    'employee_closed_period_override' => 'Closed Period Override',
    'employee_assessment_catalog' => 'Assessment Catalog',
    'employee_assessment_results' => 'Assessment Results',
    'analytics' => 'Analytics',
    'system_update' => 'System Update',
];

function selectedPermissionsFromPost(array $permissionCatalog): ?string {
    $selected = $_POST['permissions'] ?? [];
    if (!is_array($selected)) {
        $decoded = json_decode((string)$selected, true);
        $selected = is_array($decoded) ? array_keys(array_filter($decoded)) : [];
    }
    $payload = [];
    foreach (array_keys($permissionCatalog) as $permission) {
        $payload[$permission] = in_array($permission, $selected, true);
    }
    return json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function decodeUserPermissions($raw): array {
    $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
    return is_array($decoded) ? $decoded : [];
}

function roleCanManage(string $actorRole, string $targetRole): bool {
    $rank = ['employee'=>0, 'manager'=>1, 'admin'=>2, 'super_admin'=>3];
    if ($actorRole === 'super_admin') {
        return true;
    }
    return ($rank[$targetRole] ?? 99) < ($rank[$actorRole] ?? -1);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = $_POST['user_action'] ?? 'save';
        $id = (int)($_POST['id'] ?? 0);
        $actorRole = (string)($currentAdmin['role'] ?? 'employee');

        if (in_array($action, ['delete','deactivate','activate','reset_password'], true)) {
            $stmt = $db->prepare('SELECT id, role FROM admins WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new RuntimeException('کاربر پیدا نشد.');
            }
            if ((int)$target['id'] === (int)$currentAdmin['id'] && in_array($action, ['delete','deactivate'], true)) {
                throw new RuntimeException('نمی‌توانید حساب خودتان را حذف یا غیرفعال کنید.');
            }
            if (!roleCanManage($actorRole, (string)$target['role'])) {
                throw new RuntimeException('سطح دسترسی برای مدیریت این کاربر کافی نیست.');
            }
        }

        if ($action === 'delete') {
            $db->prepare('DELETE FROM admins WHERE id=?')->execute([$id]);
            redirectTo('users.php?deleted=1');
        } elseif ($action === 'deactivate') {
            $db->prepare('UPDATE admins SET is_active=0 WHERE id=?')->execute([$id]);
            redirectTo('users.php?deactivated=1');
        } elseif ($action === 'activate') {
            $db->prepare('UPDATE admins SET is_active=1 WHERE id=?')->execute([$id]);
            redirectTo('users.php?activated=1');
        } elseif ($action === 'reset_password') {
            $password = (string)($_POST['new_password'] ?? '');
            if (strlen($password) < 8) {
                throw new RuntimeException('رمز جدید باید حداقل ۸ کاراکتر باشد.');
            }
            $db->prepare('UPDATE admins SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            redirectTo('users.php?password_reset=1');
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = (string)($_POST['role'] ?? 'employee');
            if ($username === '') {
                throw new RuntimeException('نام کاربری الزامی است.');
            }
            if (!isset($roles[$role])) {
                throw new RuntimeException('نقش انتخاب‌شده معتبر نیست.');
            }
            if (!roleCanManage($actorRole, $role) && !($id === (int)$currentAdmin['id'] && $role === $actorRole)) {
                throw new RuntimeException('اجازه تخصیص این نقش را ندارید.');
            }
            $data = [
                'username' => $username,
                'email' => $email === '' ? null : $email,
                'full_name' => trim((string)($_POST['full_name'] ?? '')) ?: null,
                'role' => $role,
                'department' => trim((string)($_POST['department'] ?? '')) ?: null,
                'permissions' => selectedPermissionsFromPost($permissionCatalog),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $stmt = $db->prepare('SELECT role FROM admins WHERE id=?');
                $stmt->execute([$id]);
                $existingRole = (string)$stmt->fetchColumn();
                if ($existingRole === '' || !roleCanManage($actorRole, $existingRole) && $id !== (int)$currentAdmin['id']) {
                    throw new RuntimeException('اجازه ویرایش این کاربر را ندارید.');
                }
                $sql = 'UPDATE admins SET username=:username,email=:email,full_name=:full_name,role=:role,department=:department,permissions=:permissions,is_active=:is_active';
                if (!empty($_POST['password'])) {
                    if (strlen((string)$_POST['password']) < 8) {
                        throw new RuntimeException('رمز عبور باید حداقل ۸ کاراکتر باشد.');
                    }
                    $sql .= ',password=:password';
                    $data['password'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
            } else {
                if (empty($_POST['password']) || strlen((string)$_POST['password']) < 8) {
                    throw new RuntimeException('رمز عبور حداقل ۸ کاراکتری برای کاربر جدید الزامی است.');
                }
                $data['password'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO admins (username,email,password,full_name,role,department,permissions,is_active) VALUES (:username,:email,:password,:full_name,:role,:department,:permissions,:is_active)')->execute($data);
            }
            redirectTo('users.php?saved=1');
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

foreach (['saved'=>'کاربر ذخیره شد.','deleted'=>'کاربر حذف شد.','deactivated'=>'کاربر غیرفعال شد.','activated'=>'کاربر فعال شد.','password_reset'=>'رمز عبور بازنشانی شد.'] as $key => $text) {
    if (isset($_GET[$key])) {
        $message = $text;
    }
}

$edit = null;
if (($_GET['action'] ?? '') === 'edit') {
    $stmt = $db->prepare('SELECT * FROM admins WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $edit = $stmt->fetch() ?: null;
}
$editPermissions = decodeUserPermissions($edit['permissions'] ?? null);
$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = '1=1';
if ($q !== '') {
    $where = '(username LIKE :q OR email LIKE :q OR full_name LIKE :q OR department LIKE :q OR role LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$stmt = $db->prepare('SELECT id,username,email,full_name,role,department,permissions,is_active,last_login,created_at FROM admins WHERE ' . $where . ' ORDER BY id DESC');
$stmt->execute($params);
$users = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($message): ?><div class="alert alert-info"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2><?php echo $edit ? 'ویرایش کاربر / RBAC' : 'ایجاد مدیر/کارمند'; ?></h2></div><div class="card-body">
<form method="post">
<input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>">
<input type="hidden" name="id" value="<?php echo h($edit['id'] ?? ''); ?>">
<div class="admin-filter">
<input class="form-control" name="username" placeholder="نام کاربری *" required value="<?php echo h($edit['username'] ?? ''); ?>">
<input class="form-control" type="email" name="email" placeholder="ایمیل" value="<?php echo h($edit['email'] ?? ''); ?>">
<input class="form-control" name="full_name" placeholder="نام کامل" value="<?php echo h($edit['full_name'] ?? ''); ?>">
<select class="form-control" name="role"><?php foreach($roles as $k=>$v): ?><option value="<?php echo h($k); ?>" <?php echo (($edit['role'] ?? 'employee')===$k?'selected':''); ?>><?php echo h($v); ?></option><?php endforeach; ?></select>
<select class="form-control" name="department"><option value="">بدون دپارتمان</option><?php foreach($departments as $k=>$v): ?><option value="<?php echo h($k); ?>" <?php echo (($edit['department'] ?? '')===$k?'selected':''); ?>><?php echo h($v); ?></option><?php endforeach; ?></select>
<input class="form-control" type="password" name="password" minlength="8" placeholder="رمز عبور <?php echo $edit ? '(خالی = بدون تغییر)' : '*'; ?>">
<label><input type="checkbox" name="is_active" value="1" <?php echo (($edit['is_active'] ?? 1) ? 'checked' : ''); ?>> فعال</label>
</div>
<h3>Permissions Assignment</h3>
<div class="admin-filter">
<?php foreach($permissionCatalog as $key=>$label): ?>
    <label><input type="checkbox" name="permissions[]" value="<?php echo h($key); ?>" <?php echo !empty($editPermissions[$key]) ? 'checked' : ''; ?>> <?php echo h($label); ?></label>
<?php endforeach; ?>
</div>
<button class="btn btn-success" name="user_action" value="save">ذخیره</button>
<?php if ($edit): ?><a class="btn" href="users.php">لغو</a><?php endif; ?>
</form></div></div>
<div class="card"><div class="card-header"><h2>لیست کاربران</h2></div><div class="card-body"><form class="admin-filter"><input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="جستجو"><button class="btn btn-primary">جستجو</button></form><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>نام</th><th>ایمیل</th><th>نقش</th><th>دپارتمان</th><th>Permissions</th><th>فعال</th><th>آخرین ورود</th><th>عملیات</th></tr></thead><tbody>
<?php foreach($users as $u): $perms = array_keys(array_filter(decodeUserPermissions($u['permissions'] ?? null))); ?><tr><td><?php echo h($u['id']); ?></td><td><?php echo h($u['full_name'] ?: $u['username']); ?></td><td><?php echo h($u['email']); ?></td><td><?php echo h($u['role']); ?></td><td><?php echo h($u['department']); ?></td><td><?php echo h(implode(', ', $perms)); ?></td><td><?php echo $u['is_active'] ? '✅' : '❌'; ?></td><td><?php echo h($u['last_login']); ?></td><td><a class="btn btn-sm btn-primary" href="?action=edit&id=<?php echo h($u['id']); ?>">ویرایش</a> <?php if ((int)$u['id'] !== (int)$currentAdmin['id']): ?><form method="post" style="display:inline"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($u['id']); ?>"><button class="btn btn-sm btn-warning" name="user_action" value="<?php echo $u['is_active'] ? 'deactivate' : 'activate'; ?>"><?php echo $u['is_active'] ? 'غیرفعال' : 'فعال'; ?></button><button class="btn btn-sm btn-danger" name="user_action" value="delete" onclick="return confirm('حذف شود؟')">حذف</button></form><?php endif; ?><form method="post" class="admin-filter" style="margin-top:6px"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($u['id']); ?>"><input class="form-control" type="password" name="new_password" minlength="8" placeholder="رمز جدید"><button class="btn btn-sm btn-success" name="user_action" value="reset_password">Reset</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
