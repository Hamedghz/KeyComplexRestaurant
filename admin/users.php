<?php
require_once __DIR__ . '/lib/admin_schema.php';
$currentAdmin = adminGuard('admin');
ensureAdminSchema();
$db = adminDb();
$pageTitle = 'کاربران و کارکنان';
$message = '';
$error = '';
$roles = ['super_admin'=>'Super Admin','admin'=>'Admin','manager'=>'Manager','employee'=>'Employee'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrf();
        $action = $_POST['user_action'] ?? 'save';
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete' && $id !== (int)$currentAdmin['id']) {
            $db->prepare('DELETE FROM admins WHERE id=?')->execute([$id]);
            redirectTo('users.php?deleted=1');
        } elseif ($action === 'deactivate' && $id !== (int)$currentAdmin['id']) {
            $db->prepare('UPDATE admins SET is_active=0 WHERE id=?')->execute([$id]);
            redirectTo('users.php?deactivated=1');
        } else {
            $data = [
                'username' => trim((string)$_POST['username']),
                'email' => trim((string)$_POST['email']),
                'full_name' => trim((string)$_POST['full_name']),
                'role' => $_POST['role'] ?? 'employee',
                'department' => trim((string)($_POST['department'] ?? '')),
                'permissions' => trim((string)($_POST['permissions'] ?? '')) ?: null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                $sql = 'UPDATE admins SET username=:username,email=:email,full_name=:full_name,role=:role,department=:department,permissions=:permissions,is_active=:is_active';
                if (!empty($_POST['password'])) { $sql .= ',password=:password'; $data['password'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT); }
                $sql .= ' WHERE id=:id'; $data['id'] = $id;
                $db->prepare($sql)->execute($data);
            } else {
                if (empty($_POST['password'])) throw new RuntimeException('رمز عبور برای کاربر جدید الزامی است.');
                $data['password'] = password_hash((string)$_POST['password'], PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO admins (username,email,password,full_name,role,department,permissions,is_active) VALUES (:username,:email,:password,:full_name,:role,:department,:permissions,:is_active)')->execute($data);
            }
            redirectTo('users.php?saved=1');
        }
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
$edit = null;
if (($_GET['action'] ?? '') === 'edit') { $stmt=$db->prepare('SELECT * FROM admins WHERE id=?'); $stmt->execute([(int)$_GET['id']]); $edit=$stmt->fetch(); }
$q = trim((string)($_GET['q'] ?? ''));
$params=[]; $where='1=1';
if ($q !== '') { $where='username LIKE :q OR email LIKE :q OR full_name LIKE :q OR department LIKE :q'; $params['q']='%'.$q.'%'; }
$stmt=$db->prepare('SELECT id,username,email,full_name,role,department,permissions,is_active,last_login,created_at FROM admins WHERE '.$where.' ORDER BY id DESC'); $stmt->execute($params); $users=$stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24"><?php echo h($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header"><h2><?php echo $edit ? 'ویرایش کاربر' : 'ایجاد مدیر/کارمند'; ?></h2></div><div class="card-body">
<form method="post" class="admin-filter">
<input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($edit['id'] ?? ''); ?>">
<input class="form-control" name="username" placeholder="نام کاربری" required value="<?php echo h($edit['username'] ?? ''); ?>">
<input class="form-control" type="email" name="email" placeholder="ایمیل" required value="<?php echo h($edit['email'] ?? ''); ?>">
<input class="form-control" name="full_name" placeholder="نام کامل" value="<?php echo h($edit['full_name'] ?? ''); ?>">
<select class="form-control" name="role"><?php foreach($roles as $k=>$v): ?><option value="<?php echo h($k); ?>" <?php echo (($edit['role'] ?? 'employee')===$k?'selected':''); ?>><?php echo h($v); ?></option><?php endforeach; ?></select>
<input class="form-control" name="department" placeholder="دپارتمان" value="<?php echo h($edit['department'] ?? ''); ?>">
<input class="form-control" name="permissions" placeholder='Permissions JSON مثل {"media":true}' value="<?php echo h($edit['permissions'] ?? ''); ?>">
<input class="form-control" type="password" name="password" placeholder="رمز عبور <?php echo $edit ? '(خالی = بدون تغییر)' : ''; ?>">
<label><input type="checkbox" name="is_active" value="1" <?php echo (($edit['is_active'] ?? 1) ? 'checked' : ''); ?>> فعال</label>
<button class="btn btn-success" name="user_action" value="save">ذخیره</button>
</form></div></div>
<div class="card"><div class="card-header"><h2>لیست کاربران</h2></div><div class="card-body"><form class="admin-filter"><input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="جستجو"><button class="btn btn-primary">جستجو</button></form><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>نام</th><th>ایمیل</th><th>نقش</th><th>دپارتمان</th><th>فعال</th><th>آخرین ورود</th><th>عملیات</th></tr></thead><tbody>
<?php foreach($users as $u): ?><tr><td><?php echo h($u['id']); ?></td><td><?php echo h($u['full_name'] ?: $u['username']); ?></td><td><?php echo h($u['email']); ?></td><td><?php echo h($u['role']); ?></td><td><?php echo h($u['department']); ?></td><td><?php echo $u['is_active'] ? '✅' : '❌'; ?></td><td><?php echo h($u['last_login']); ?></td><td><a class="btn btn-sm btn-primary" href="?action=edit&id=<?php echo h($u['id']); ?>">ویرایش/Reset</a> <?php if ((int)$u['id'] !== (int)$currentAdmin['id']): ?><form method="post" style="display:inline"><input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h(generateCSRFToken()); ?>"><input type="hidden" name="id" value="<?php echo h($u['id']); ?>"><button class="btn btn-sm btn-warning" name="user_action" value="deactivate">غیرفعال</button><button class="btn btn-sm btn-danger" name="user_action" value="delete" onclick="return confirm('حذف شود؟')">حذف</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
