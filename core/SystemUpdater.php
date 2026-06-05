<?php
require_once __DIR__ . '/bootstrap.php';

class SystemUpdater {
    private string $root;
    private string $githubRepo = 'https://github.com/Hamedghz/KeyComplexRestaurant.git';
    private string $githubApi = 'https://api.github.com/repos/Hamedghz/KeyComplexRestaurant';
    private string $branch = 'main';

    public function __construct($root = ROOT_PATH) {
        $this->root = $root;
    }

    private function run(array $command, &$exitCode = null) {
        $escaped = array_map('escapeshellarg', $command);
        $cmd = 'cd ' . escapeshellarg($this->root) . ' && ' . implode(' ', $escaped) . ' 2>&1';
        $output = [];
        exec($cmd, $output, $exitCode);
        return implode("\n", $output);
    }

    private function log(string $message): void {
        if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0755, true);
        file_put_contents(STORAGE_PATH . '/update.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
    }

    private function githubJson(string $path): array {
        $url = rtrim($this->githubApi, '/') . '/' . ltrim($path, '/');
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'header' => "User-Agent: KEY-System-Updater\r\nAccept: application/vnd.github+json\r\n"]]);
        $json = @file_get_contents($url, false, $context);
        if ($json === false || trim($json) === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function currentVersion() {
        $code = 0;
        $hash = trim($this->run(['git', 'rev-parse', '--short=12', 'HEAD'], $code));
        return $code === 0 ? $hash : 'unknown';
    }

    public function githubUrl(): string {
        $code = 0;
        $remote = trim($this->run(['git', 'config', '--get', 'remote.origin.url'], $code));
        return $remote !== '' ? $remote : $this->githubRepo;
    }

    public function updateLogs() {
        $logFiles = [STORAGE_PATH . '/update.log', STORAGE_PATH . '/pending-migrations.txt', STORAGE_PATH . '/last-backup.txt'];
        $logs = [];
        foreach ($logFiles as $file) {
            if (is_file($file)) {
                $logs[basename($file)] = trim((string)file_get_contents($file));
            }
        }
        return $logs;
    }

    public function migrationStatus() {
        $migrationDirs = [ROOT_PATH . '/database/migrations', ROOT_PATH . '/migrations'];
        $files = [];
        foreach ($migrationDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.sql') ?: [] as $file) {
                $files[] = basename($dir) . '/' . basename($file);
            }
        }
        sort($files, SORT_STRING);

        return [
            'directory' => implode(', ', $migrationDirs),
            'files' => $files,
            'pending_file' => STORAGE_PATH . '/pending-migrations.txt',
            'version_table' => 'system_versions',
        ];
    }

    public function check() {
        $code = 0;
        $this->run(['git', 'remote', 'set-url', 'origin', $this->githubRepo], $code);
        $fetchOutput = $this->run(['git', 'fetch', 'origin', $this->branch, '--tags', '--quiet'], $code);
        $localFull = trim($this->run(['git', 'rev-parse', 'HEAD'], $code));
        $remoteFull = trim($this->run(['git', 'rev-parse', 'origin/' . $this->branch], $code));
        $behind = 0;
        if ($localFull !== '' && $remoteFull !== '') {
            $count = trim($this->run(['git', 'rev-list', '--count', $localFull . '..' . $remoteFull], $code));
            $behind = ctype_digit($count) ? (int)$count : 0;
        }
        $release = $this->githubJson('releases/latest');
        $tags = $this->githubJson('tags?per_page=1');
        $latestRelease = $release['tag_name'] ?? ($tags[0]['name'] ?? 'none');
        $changelog = '';
        if ($localFull !== '' && $remoteFull !== '' && $localFull !== $remoteFull) {
            $changelog = trim($this->run(['git', 'log', '--oneline', '--decorate', $localFull . '..' . $remoteFull], $code));
        }
        $this->log('check: current=' . substr($localFull, 0, 12) . ' latest=' . substr($remoteFull, 0, 12) . ' release=' . $latestRelease . ($fetchOutput ? ' output=' . $fetchOutput : ''));
        return [
            'current' => $localFull !== '' ? substr($localFull, 0, 12) : 'unknown',
            'latest' => $remoteFull !== '' ? substr($remoteFull, 0, 12) : 'unknown',
            'current_commit' => $localFull,
            'latest_commit' => $remoteFull,
            'latest_release' => $latestRelease,
            'release_name' => $release['name'] ?? $latestRelease,
            'release_notes' => $release['body'] ?? '',
            'commits_behind' => $behind,
            'changelog' => $changelog,
            'github_url' => $this->githubRepo,
            'update_available' => $localFull !== '' && $remoteFull !== '' && $localFull !== $remoteFull,
        ];
    }

    public function backupDatabase(?string $targetFile = null): string {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $targetFile = $targetFile ?: $dir . '/database-' . date('Ymd-His') . '.sql';
        $code = 0;
        $dump = $this->run(['mysqldump', '--single-transaction', '--quick', '--default-character-set=utf8mb4', '-h' . DB_HOST, '-u' . DB_USER, DB_PASS === '' ? '--password=' : '-p' . DB_PASS, DB_NAME], $code);
        if ($code === 0 && trim($dump) !== '') {
            file_put_contents($targetFile, $dump, LOCK_EX);
            $this->log('database backup created via mysqldump: ' . $targetFile);
            return $targetFile;
        }
        $pdo = Database::getInstance()->getConnection();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- KEY database backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
            $sql .= "\nDROP TABLE IF EXISTS `{$table}`;\n" . ($create['Create Table'] ?? array_values($create)[1]) . ";\n";
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
                $sql .= 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($targetFile, $sql, LOCK_EX);
        $this->log('database backup created via PDO fallback: ' . $targetFile);
        return $targetFile;
    }

    public function backup() {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $stamp = date('Ymd-His');
        $dbBackup = $this->backupDatabase($dir . '/database-' . $stamp . '.sql');
        $file = $dir . '/system-' . $stamp . '.tar.gz';
        $code = 0;
        $output = $this->run(['tar', '--exclude=.git', '--exclude=node_modules', '--exclude=storage/backups', '-czf', $file, '.'], $code);
        if ($code !== 0) {
            $this->log('backup failed: ' . $output);
            throw new RuntimeException('Backup failed: ' . $output);
        }
        file_put_contents(STORAGE_PATH . '/last-backup.txt', json_encode(['system' => $file, 'database' => $dbBackup], JSON_UNESCAPED_SLASHES), LOCK_EX);
        $this->log('system backup created: ' . $file);
        return $file . ' | DB: ' . $dbBackup;
    }

    public function apply() {
        $backup = $this->backup();
        $code = 0;
        $this->run(['git', 'remote', 'set-url', 'origin', $this->githubRepo], $code);
        $this->run(['git', 'fetch', 'origin', $this->branch, '--tags', '--quiet'], $code);
        $output = $this->run(['git', 'pull', '--ff-only', 'origin', $this->branch], $code);
        if ($code !== 0) {
            $this->log('apply failed before migrations: ' . $output);
            $this->rollback();
            throw new RuntimeException('Update failed and rollback was attempted. Backup: ' . $backup . ': ' . $output);
        }
        try {
            $this->runPendingMigrations();
            $this->clearCache();
        } catch (Throwable $e) {
            $this->log('apply failed after pull: ' . $e->getMessage());
            $this->rollback();
            throw new RuntimeException('Update migration/cache step failed and rollback was attempted: ' . $e->getMessage());
        }
        $this->log('apply completed: ' . $output);
        return ['backup' => $backup, 'output' => $output];
    }

    public function rollback() {
        $raw = is_file(STORAGE_PATH . '/last-backup.txt') ? trim(file_get_contents(STORAGE_PATH . '/last-backup.txt')) : '';
        if ($raw === '') {
            throw new RuntimeException('فایل بکاپ برای rollback پیدا نشد.');
        }
        $decoded = json_decode($raw, true);
        $systemFile = is_array($decoded) ? ($decoded['system'] ?? '') : $raw;
        if ($systemFile === '' || !is_file($systemFile)) {
            throw new RuntimeException('فایل بکاپ سیستم برای rollback پیدا نشد.');
        }
        $code = 0;
        $output = $this->run(['tar', '-xzf', $systemFile, '-C', $this->root], $code);
        if ($code !== 0) {
            $this->log('rollback failed: ' . $output);
            throw new RuntimeException('Rollback failed: ' . $output);
        }
        if (is_array($decoded) && !empty($decoded['database']) && is_file($decoded['database'])) {
            $this->restoreDatabase($decoded['database']);
        }
        $this->log('rollback completed from: ' . $systemFile);
        return $systemFile;
    }

    private function restoreDatabase(string $file): void {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') return;
        $pdo = Database::getInstance()->getConnection();
        $pdo->exec($sql);
        $this->log('database rollback restored: ' . $file);
    }

    private function runPendingMigrations() {
        require_once ROOT_PATH . '/core/MigrationRunner.php';

        $runner = new MigrationRunner(Database::getInstance()->getConnection(), [
            ROOT_PATH . '/database/migrations',
            ROOT_PATH . '/migrations',
        ]);
        $results = $runner->run();
        $log = [];
        foreach ($results as $migration => $status) {
            $log[] = $status . ' ' . $migration;
        }
        file_put_contents(STORAGE_PATH . '/pending-migrations.txt', $log ? implode("\n", $log) : 'No pending migrations at ' . date('c'), LOCK_EX);
    }

    private function clearCache() {
        foreach ([STORAGE_PATH . '/cache', ROOT_PATH . '/cache'] as $cacheDir) {
            if (!is_dir($cacheDir)) continue;
            foreach (glob($cacheDir . '/*') ?: [] as $file) {
                if (is_file($file)) unlink($file);
            }
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        $this->log('cache cleared');
    }
}
