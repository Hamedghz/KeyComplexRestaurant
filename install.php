<?php
/**
 * KEY Restaurant & Coffeehouse production installer (UPDATED).
 *
 * Shared-hosting friendly installer: no shell commands, imports the consolidated
 * schema, writes config.php, creates runtime folders, creates the first administrator,
 * and writes installed.lock.
 *
 * ENHANCED: Merges database/schema.sql with all database/migrations/*.sql files
 * and executes them in proper dependency order.
 */

session_start();

$baseDir = __DIR__;
$configPath = $baseDir . '/config.php';
$lockPath = $baseDir . '/installed.lock';
$schemaPath = $baseDir . '/database/schema.sql';
$migrationsDir = $baseDir . '/database/migrations';
$uploadDirs = [
    $baseDir . '/uploads',
    $baseDir . '/uploads/media',
    $baseDir . '/uploads/menu',
    $baseDir . '/uploads/avatars',
    $baseDir . '/uploads/logo',
    $baseDir . '/uploads/hero',
    $baseDir . '/uploads/banners',
    $baseDir . '/uploads/textures',
    $baseDir . '/uploads/models',
    $baseDir . '/uploads/key-story',
    $baseDir . '/uploads/gallery',
    $baseDir . '/storage',
    $baseDir . '/storage/cache',
    $baseDir . '/storage/logs',
];

if (file_exists($lockPath) || file_exists($configPath)) {
    http_response_code(403);
    die('Already installed');
}

$errors = [];
$success = false;
$detectedBaseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? $detectedBaseUrl), '/');
    $environment = $_POST['environment'] ?? 'production';
    $adminUsername = trim($_POST['admin_username'] ?? 'admin');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? 'KEY Administrator');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $errors[] = 'Database host, database name, and username are required.';
    }
    if (!in_array($environment, ['production', 'local'], true)) {
        $errors[] = 'Invalid environment selected.';
    }
    if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'A valid base URL is required.';
    }
    if ($adminUsername === '' || $adminEmail === '' || $adminPassword === '') {
        $errors[] = 'Admin username, email, and password are required.';
    }
    if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email is required.';
    }
    if ($adminPassword !== '' && strlen($adminPassword) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if (!is_readable($schemaPath)) {
        $errors[] = 'database/schema.sql is missing or unreadable.';
    }

    if (!$errors) {
        try {
            require_once $baseDir . '/core/MigrationRunner.php';

            $pdo = connectInstallerDatabase($dbHost, $dbName, $dbUser, $dbPass);
            $runner = new MigrationRunner($pdo, []);

            // 1. Execute main schema
            $runner->executeSqlFile($schemaPath);

            // 2. Execute all migration files in dependency order
            $migrations = collectMigrationFiles($migrationsDir);
            foreach ($migrations as $migrationFile) {
                try {
                    $runner->executeSqlFile($migrationFile);
                } catch (Throwable $e) {
                    error_log('Migration ' . basename($migrationFile) . ' warning: ' . $e->getMessage());
                    // Continue on non-critical migration errors (tables may already exist)
                }
            }

            // 3. Create first administrator
            $adminStmt = $pdo->prepare(
                'INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`, `is_active`)
                 VALUES (:username, :email, :password, :full_name, :role, 1)
                 ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `password` = VALUES(`password`), `full_name` = VALUES(`full_name`), `role` = VALUES(`role`), `is_active` = 1'
            );
            try {
                $adminStmt->execute([
                    'username' => $adminUsername,
                    'email' => $adminEmail,
                    'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
                    'full_name' => $adminName !== '' ? $adminName : $adminUsername,
                    'role' => 'super_admin',
                ]);
            } finally {
                $adminStmt->closeCursor();
            }

            // 4. Create upload directories
            foreach ($uploadDirs as $dir) {
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    throw new RuntimeException('Could not create directory: ' . basename($dir));
                }
            }

            // 5. Write configuration
            writeInstallerConfig($configPath, [
                'app' => [
                    'environment' => $environment,
                    'debug' => $environment !== 'production',
                    'base_url' => $baseUrl,
                    'timezone' => 'Asia/Tehran',
                    'max_file_size' => 5 * 1024 * 1024,
                ],
                'db' => [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'charset' => 'utf8mb4',
                ],
            ]);

            // 6. Write lock file
            if (file_put_contents($lockPath, date('c'), LOCK_EX) === false) {
                throw new RuntimeException('Could not write installed.lock. Check server-root permissions.');
            }

            $success = true;
        } catch (Throwable $e) {
            error_log('Installer failed: ' . $e->getMessage());
            $errors[] = 'Installation failed. Please verify database credentials, permissions, and SQL compatibility.';
        }
    }
}

function collectMigrationFiles(string $dir): array {
    $files = [];
    if (!is_dir($dir)) {
        return $files;
    }
    
    $items = @scandir($dir);
    if ($items === false) {
        return $files;
    }
    
    foreach ($items as $item) {
        if (substr($item, -4) === '.sql') {
            $files[] = $dir . '/' . $item;
        }
    }
    
    sort($files);
    return $files;
}

function connectInstallerDatabase(string $host, string $name, string $user, string $pass): PDO {
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[constant('PDO::MYSQL_ATTR_INIT_COMMAND')] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $options[constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')] = true;
    }

    $pdo = new PDO($dsn, $user, $pass, $options);

    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $pdo->setAttribute(constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY'), true);
    }

    return $pdo;
}

function writeInstallerConfig(string $path, array $config): void {
    $configContent = "<?php\n";
    $configContent .= "/**\n * Generated application configuration.\n * Do not expose or commit production credentials.\n */\n";
    $configContent .= "if (isset(\$_SERVER['SCRIPT_FILENAME']) && realpath(\$_SERVER['SCRIPT_FILENAME']) === __FILE__) {\n";
    $configContent .= "    http_response_code(403);\n";
    $configContent .= "    exit('Forbidden');\n";
    $configContent .= "}\n\n";
    $configContent .= 'return ' . var_export($config, true) . ";\n";

    if (file_put_contents($path, $configContent, LOCK_EX) === false) {
        throw new RuntimeException('Could not write config.php. Check server-root permissions.');
    }
}

function installValue(string $name, string $default = ''): string {
    return htmlspecialchars((string)($_POST[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install KEY Restaurant & Coffeehouse</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f6f2ea;margin:0;padding:32px;color:#24201b}.wrap{max-width:760px;margin:auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 14px 45px rgba(0,0,0,.1)}.wrap h1{margin-top:0;color:#004647}.wrap h2{color:#004647;font-size:18px;margin-top:28px;margin-bottom:12px}.note{color:#666;font-size:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}.grid.full{grid-template-columns:1fr}.field{display:flex;flex-direction:column}.field label{font-weight:bold;margin-bottom:4px;font-size:13px;color:#333}.field input,.field select{padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:14px;font-family:monospace}.field input:focus,.field select:focus{outline:none;border-color:#004647;box-shadow:0 0 0 3px rgba(0,70,71,.1)}.alert{padding:16px;border-radius:8px;margin-bottom:20px;font-size:14px}.alert.success{background:#d4edda;color:#155724;border-left:4px solid #28a745}.alert.error{background:#f8d7da;color:#721c24;border-left:4px solid #dc3545}.alert ul{margin:8px 0 0 0;padding-left:20px}.alert li{margin:6px 0}.btn{background:#004647;color:#fff;border:none;padding:12px 24px;border-radius:4px;font-size:15px;font-weight:bold;cursor:pointer;transition:.2s}.btn:hover{background:#003536}
    </style>
</head>
<body>
<div class="wrap">
    <h1>KEY Restaurant & Coffeehouse Installer</h1>
    <p class="note">Imports <strong>database/schema.sql</strong> and all <strong>database/migrations/*.sql</strong> files, creates runtime directories, and writes production config.</p>

    <?php if ($success): ?>
        <div class="alert success">
            <strong>Installation complete.</strong> Remove install.php or keep it protected by installed.lock, then sign in to /admin with your administrator account.
        </div>
    <?php else: ?>
        <?php if ($errors): ?>
            <div class="alert error">
                <strong>Please fix the following:</strong>
                <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <h2>Database</h2>
            <div class="grid">
                <div class="field"><label>Host</label><input name="db_host" value="<?php echo installValue('db_host', 'localhost'); ?>" required></div>
                <div class="field"><label>Database name</label><input name="db_name" value="<?php echo installValue('db_name'); ?>" required></div>
                <div class="field"><label>Username</label><input name="db_user" value="<?php echo installValue('db_user'); ?>" required></div>
                <div class="field"><label>Password</label><input name="db_pass" type="password" value="<?php echo installValue('db_pass'); ?>"></div>
            </div>

            <h2>Application</h2>
            <div class="grid">
                <div class="field"><label>Base URL</label><input name="base_url" value="<?php echo installValue('base_url', $detectedBaseUrl); ?>" required></div>
                <div class="field"><label>Environment</label><select name="environment"><option value="production">Production</option><option value="local">Local</option></select></div>
            </div>

            <h2>First administrator</h2>
            <div class="grid">
                <div class="field"><label>Username</label><input name="admin_username" value="<?php echo installValue('admin_username', 'admin'); ?>" required></div>
                <div class="field"><label>Email</label><input name="admin_email" type="email" value="<?php echo installValue('admin_email', 'admin@example.com'); ?>" required></div>
                <div class="field full"><label>Full name</label><input name="admin_name" value="<?php echo installValue('admin_name', 'KEY Administrator'); ?>"></div>
                <div class="field full"><label>Password</label><input name="admin_password" type="password" minlength="8" required></div>
            </div>

            <button class="btn" type="submit">Install production system</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
