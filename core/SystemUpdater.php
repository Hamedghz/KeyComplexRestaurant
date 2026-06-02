<?php
require_once __DIR__ . '/bootstrap.php';

class SystemUpdater {
    private string $root;

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

    public function currentVersion() {
        $code = 0;
        $hash = trim($this->run(['git', 'rev-parse', '--short', 'HEAD'], $code));
        return $code === 0 ? $hash : 'unknown';
    }

    public function updateLogs() {
        $logFiles = [STORAGE_PATH . '/pending-migrations.txt', STORAGE_PATH . '/last-backup.txt'];
        $logs = [];
        foreach ($logFiles as $file) {
            if (is_file($file)) {
                $logs[basename($file)] = trim((string)file_get_contents($file));
            }
        }
        return $logs;
    }

    public function migrationStatus() {
        $migrationDir = ROOT_PATH . '/database/migrations';
        return [
            'directory' => $migrationDir,
            'files' => is_dir($migrationDir) ? array_map('basename', glob($migrationDir . '/*.sql') ?: []) : [],
            'pending_file' => STORAGE_PATH . '/pending-migrations.txt',
        ];
    }

    public function check() {
        $code = 0;
        $this->run(['git', 'fetch', '--dry-run', '--quiet'], $code);
        $local = trim($this->run(['git', 'rev-parse', 'HEAD'], $code));
        $remote = trim($this->run(['git', 'rev-parse', '@{u}'], $code));

        return [
            'current' => substr($local, 0, 12),
            'latest' => substr($remote, 0, 12),
            'update_available' => $local !== '' && $remote !== '' && $local !== $remote,
        ];
    }

    public function backup() {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/system-' . date('Ymd-His') . '.tar.gz';
        $code = 0;
        $output = $this->run(['tar', '--exclude=.git', '--exclude=node_modules', '--exclude=storage/backups', '-czf', $file, '.'], $code);
        if ($code !== 0) {
            throw new RuntimeException('Backup failed: ' . $output);
        }
        file_put_contents(STORAGE_PATH . '/last-backup.txt', $file, LOCK_EX);
        return $file;
    }

    public function apply() {
        $backup = $this->backup();
        $code = 0;
        $output = $this->run(['git', 'pull', '--ff-only'], $code);
        if ($code !== 0) {
            throw new RuntimeException('Update failed; backup is available at ' . $backup . ': ' . $output);
        }
        $this->runPendingMigrations();
        $this->clearCache();
        return ['backup' => $backup, 'output' => $output];
    }

    public function rollback() {
        $file = is_file(STORAGE_PATH . '/last-backup.txt') ? trim(file_get_contents(STORAGE_PATH . '/last-backup.txt')) : '';
        if ($file === '' || !is_file($file)) {
            throw new RuntimeException('فایل بکاپ برای rollback پیدا نشد.');
        }
        $code = 0;
        $output = $this->run(['tar', '-xzf', $file, '-C', $this->root], $code);
        if ($code !== 0) {
            throw new RuntimeException('Rollback failed: ' . $output);
        }
        return $file;
    }

    private function runPendingMigrations() {
        $migrationDir = ROOT_PATH . '/database/migrations';
        if (!is_dir($migrationDir)) {
            return;
        }
        // Migrations are intentionally not auto-applied without a configured CLI DB client.
        // The update screen records the need to review/import new SQL files in phpMyAdmin.
        file_put_contents(STORAGE_PATH . '/pending-migrations.txt', implode("\n", glob($migrationDir . '/*.sql') ?: []), LOCK_EX);
    }

    private function clearCache() {
        $cacheDir = STORAGE_PATH . '/cache';
        if (!is_dir($cacheDir)) {
            return;
        }
        foreach (glob($cacheDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
