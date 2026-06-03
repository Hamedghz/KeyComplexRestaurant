<?php
require_once __DIR__ . '/bootstrap.php';

class SystemUpdater {
    private string $root;
    private string $githubRepo = 'https://github.com/Hamedghz/KeyComplexRestaurant.git';

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

    private function log(string $message): void {
        if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0755, true);
        file_put_contents(STORAGE_PATH . '/update.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
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
        $this->run(['git', 'remote', 'set-url', 'origin', $this->githubRepo], $code);
        $fetchOutput = $this->run(['git', 'fetch', 'origin', 'main', '--quiet'], $code);
        $local = trim($this->run(['git', 'rev-parse', 'HEAD'], $code));
        $remote = trim($this->run(['git', 'rev-parse', 'origin/main'], $code));
        $this->log('check: current=' . substr($local, 0, 12) . ' latest=' . substr($remote, 0, 12) . ($fetchOutput ? ' output=' . $fetchOutput : ''));
        return [
            'current' => $local !== '' ? substr($local, 0, 12) : 'unknown',
            'latest' => $remote !== '' ? substr($remote, 0, 12) : 'unknown',
            'github_url' => $this->githubRepo,
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
            $this->log('backup failed: ' . $output);
            throw new RuntimeException('Backup failed: ' . $output);
        }
        file_put_contents(STORAGE_PATH . '/last-backup.txt', $file, LOCK_EX);
        $this->log('backup created: ' . $file);
        return $file;
    }

    public function apply() {
        $backup = $this->backup();
        $code = 0;
        $this->run(['git', 'remote', 'set-url', 'origin', $this->githubRepo], $code);
        $output = $this->run(['git', 'pull', '--ff-only', 'origin', 'main'], $code);
        if ($code !== 0) {
            $this->log('apply failed: ' . $output);
            throw new RuntimeException('Update failed; backup is available at ' . $backup . ': ' . $output);
        }
        $this->runPendingMigrations();
        $this->clearCache();
        $this->log('apply completed: ' . $output);
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
            $this->log('rollback failed: ' . $output);
            throw new RuntimeException('Rollback failed: ' . $output);
        }
        $this->log('rollback completed from: ' . $file);
        return $file;
    }

    private function runPendingMigrations() {
        $migrationDir = ROOT_PATH . '/database/migrations';
        if (!is_dir($migrationDir)) {
            return;
        }
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
