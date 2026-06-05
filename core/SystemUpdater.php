<?php

class SystemUpdater {
    private string $root;

    public function __construct($root = ROOT_PATH) {
        $this->root = rtrim((string)$root, DIRECTORY_SEPARATOR);
    }

    public function disabledShellFunctions(): array {
        $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
        $disabled = array_map('strtolower', $disabled);
        $watched = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
        return array_values(array_intersect($watched, $disabled));
    }

    public function shellCommandsAvailable(): bool {
        return false;
    }

    public function currentVersion(): string {
        $version = $this->readVersionFile();
        return $version['version'] ?? 'unknown';
    }

    public function githubUrl(): string {
        return 'external deployment';
    }

    public function check(): array {
        $version = $this->readVersionFile();
        return [
            'current' => $version['version'] ?? 'unknown',
            'build' => $version['build'] ?? 'unknown',
            'app' => $version['app'] ?? '',
            'latest' => 'external deployment',
            'latest_release' => 'external deployment',
            'commits_behind' => 0,
            'update_available' => false,
            'github_url' => 'external deployment',
            'changelog' => '',
            'release_notes' => '',
        ];
    }

    public function migrationStatus(): array {
        $files = $this->migrationFiles();
        return [
            'directory' => $this->root . '/database/migrations',
            'files' => array_map('basename', $files),
            'version_table' => 'schema_migrations',
        ];
    }

    public function updateLogs(): array {
        return [];
    }

    public function backup(): string {
        throw new RuntimeException('File/database backup from this panel is disabled on shared hosting. Use external deployment backups.');
    }

    public function apply(): array {
        throw new RuntimeException('Code deployment is external. This panel only manages database migration status.');
    }

    public function rollback(?string $systemFile = null): string {
        throw new RuntimeException('Rollback is external. Use your hosting or deployment provider backup workflow.');
    }

    private function readVersionFile(): array {
        $file = $this->root . '/version.json';
        if (!is_readable($file)) {
            return ['version' => 'unknown', 'build' => 'unknown', 'app' => ''];
        }
        $json = file_get_contents($file);
        if ($json === false) {
            return ['version' => 'unknown', 'build' => 'unknown', 'app' => ''];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : ['version' => 'unknown', 'build' => 'unknown', 'app' => ''];
    }

    private function migrationFiles(): array {
        $dir = $this->root . '/database/migrations';
        $files = is_dir($dir) ? glob($dir . '/*.sql') : [];
        $files = $files ?: [];
        sort($files, SORT_STRING);
        return $files;
    }
}
