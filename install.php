<?php
/**
 * KEY Restaurant & Coffeehouse production installer.
 *
 * This installer is designed to run directly from the server root.
 * It collects database credentials, validates the connection, loads the SQL
 * schema files, writes /config.php, and creates installed.lock.
 */

session_start();

$baseDir = __DIR__;
$configPath = $baseDir . '/config.php';
$lockPath = $baseDir . '/installed.lock';
$schemaFiles = [
    $baseDir . '/database/schema.sql',
    $baseDir . '/database/survey_schema.sql',
];
$uploadDirs = [
    $baseDir . '/uploads',
    $baseDir . '/uploads/menu',
    $baseDir . '/uploads/logo',
    $baseDir . '/uploads/hero',
    $baseDir . '/uploads/textures',
    $baseDir . '/uploads/models',
    $baseDir . '/storage',
];

if (file_exists($lockPath) || file_exists($configPath)) {
    http_response_code(403);
    die('Already installed');
}

$errors = [];
$success = false;
$detectedBaseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? $detectedBaseUrl), '/');
    $environment = $_POST['environment'] ?? 'production';

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $errors[] = 'Database host, database name, and username are required.';
    }

    if (!in_array($environment, ['production', 'local'], true)) {
        $errors[] = 'Invalid environment selected.';
    }

    if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'A valid base URL is required.';
    }

    foreach ($schemaFiles as $schemaFile) {
        if (!is_readable($schemaFile)) {
            $errors[] = 'Schema file is missing or unreadable: ' . basename($schemaFile);
        }
    }

    if (!$errors) {
        try {
            $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]);

            foreach ($schemaFiles as $schemaFile) {
                $sql = file_get_contents($schemaFile);
                if ($sql === false) {
                    throw new RuntimeException('Could not read schema file: ' . basename($schemaFile));
                }
                $pdo->exec($sql);
            }

            $adminStmt = $pdo->prepare(
                'INSERT IGNORE INTO admins (username, email, password, full_name, role, is_active) '
                . 'VALUES (:username, :email, :password, :full_name, :role, 1)'
            );
            $adminStmt->execute([
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'full_name' => 'KEY Administrator',
                'role' => 'super_admin',
            ]);

            foreach ($uploadDirs as $dir) {
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    throw new RuntimeException('Could not create directory: ' . basename($dir));
                }
            }

            $config = [
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
            ];

            $configContent = "<?php\n";
            $configContent .= "/**\n * Generated application configuration.\n * Do not expose or commit production credentials.\n */\n";
            $configContent .= "if (isset(\$_SERVER['SCRIPT_FILENAME']) && realpath(\$_SERVER['SCRIPT_FILENAME']) === __FILE__) {\n";
            $configContent .= "    http_response_code(403);\n";
            $configContent .= "    exit('Forbidden');\n";
            $configContent .= "}\n\n";
            $configContent .= 'return ' . var_export($config, true) . ";\n";

            if (file_put_contents($configPath, $configContent, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config.php. Check server-root permissions.');
            }

            if (file_put_contents($lockPath, date('c'), LOCK_EX) === false) {
                throw new RuntimeException('Could not write installed.lock. Check server-root permissions.');
            }

            $success = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

function oldValue($name, $fallback = '') {
    return htmlspecialchars($_POST[$name] ?? $fallback, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KEY Installer</title>
    <style>
        :root { --primary: #004647; --accent: #D4AF37; --danger: #b42318; --ok: #067647; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Tahoma, Arial, sans-serif; background: linear-gradient(135deg, var(--primary), #001f20); color: #17202a; padding: 24px; }
        main { width: min(760px, 100%); background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 24px 70px rgba(0,0,0,.35); }
        h1 { margin-top: 0; color: var(--primary); }
        label { display: block; margin: 16px 0 6px; font-weight: 700; }
        input, select { width: 100%; padding: 12px 14px; border: 1px solid #d0d5dd; border-radius: 12px; font-size: 16px; direction: ltr; }
        .hint { color: #667085; font-size: 13px; margin-top: 4px; }
        .alert { border-radius: 14px; padding: 14px 16px; margin: 16px 0; }
        .alert--error { background: #fef3f2; color: var(--danger); border: 1px solid #fecdca; }
        .alert--success { background: #ecfdf3; color: var(--ok); border: 1px solid #abefc6; }
        button, .button { display: inline-block; width: 100%; border: 0; border-radius: 14px; background: var(--primary); color: white; padding: 14px 18px; font-size: 18px; text-decoration: none; text-align: center; cursor: pointer; margin-top: 22px; }
        code { direction: ltr; display: inline-block; background: #f2f4f7; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
<main>
    <?php if ($success): ?>
        <h1>Installation complete</h1>
        <div class="alert alert--success">
            <p>Database tables were created, <code>config.php</code> was written, and <code>installed.lock</code> now protects the installer.</p>
            <p>For additional security, remove write permissions from <code>config.php</code> after confirming the site works.</p>
        </div>
        <a class="button" href="index.php">Open site</a>
        <a class="button" href="admin/" style="background: var(--accent); color: #1d2939;">Open admin</a>
    <?php else: ?>
        <h1>KEY Restaurant Production Installer</h1>
        <p>This installer configures the site directly in the server root and detects the base URL from the current host.</p>

        <?php if ($errors): ?>
            <div class="alert alert--error">
                <strong>Installation failed:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="install.php" autocomplete="off">
            <label for="db_host">Database host</label>
            <input id="db_host" name="db_host" value="<?php echo oldValue('db_host'); ?>" required placeholder="Database host from your hosting panel">

            <label for="db_name">Database name</label>
            <input id="db_name" name="db_name" value="<?php echo oldValue('db_name'); ?>" required placeholder="Database name">

            <label for="db_user">Database username</label>
            <input id="db_user" name="db_user" value="<?php echo oldValue('db_user'); ?>" required placeholder="Database username">

            <label for="db_pass">Database password</label>
            <input id="db_pass" name="db_pass" type="password" value="<?php echo oldValue('db_pass'); ?>" placeholder="Database password">

            <label for="base_url">Base URL</label>
            <input id="base_url" name="base_url" value="<?php echo oldValue('base_url', $detectedBaseUrl); ?>" required>
            <div class="hint">Use the public URL without a trailing slash.</div>

            <label for="environment">Environment</label>
            <select id="environment" name="environment">
                <option value="production" <?php echo oldValue('environment', 'production') === 'production' ? 'selected' : ''; ?>>Production</option>
                <option value="local" <?php echo oldValue('environment') === 'local' ? 'selected' : ''; ?>>Local development</option>
            </select>

            <button type="submit">Install and lock</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
