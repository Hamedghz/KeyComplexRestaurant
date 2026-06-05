<?php
/**
 * Shared-hosting compatible SQL migration runner.
 */

require_once __DIR__ . '/db_schema_helper.php';

class MigrationRunner {
    private PDO $pdo;
    /** @var string[] */
    private array $directories;

    public function __construct(PDO $pdo, array $directories) {
        $this->pdo = $pdo;
        $this->directories = $directories;
    }

    public function ensureVersionTable(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `system_versions` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `version_name` varchar(255) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` enum('success','failed') NOT NULL DEFAULT 'success',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_system_versions_name` (`version_name`),
            KEY `idx_system_versions_executed_at` (`executed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!schemaColumnExists($this->pdo, 'system_versions', 'version_name')) {
            $this->pdo->exec("ALTER TABLE `system_versions` ADD COLUMN `version_name` varchar(255) DEFAULT NULL AFTER `id`");
        }
        if (!schemaColumnExists($this->pdo, 'system_versions', 'executed_at')) {
            $this->pdo->exec("ALTER TABLE `system_versions` ADD COLUMN `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        if (!schemaColumnExists($this->pdo, 'system_versions', 'status')) {
            $this->pdo->exec("ALTER TABLE `system_versions` ADD COLUMN `status` enum('success','failed') NOT NULL DEFAULT 'success'");
        }
        if (!schemaIndexExists($this->pdo, 'system_versions', 'uniq_system_versions_name')) {
            try {
                $this->pdo->exec("ALTER TABLE `system_versions` ADD UNIQUE KEY `uniq_system_versions_name` (`version_name`)");
            } catch (PDOException $e) {
                if (!$this->isRecoverableSqlError($e)) {
                    throw $e;
                }
            }
        }
    }

    public function run(): array {
        $this->ensureVersionTable();
        $results = [];
        foreach ($this->migrationFiles() as $file) {
            $versionName = basename(dirname($file)) . '/' . basename($file);
            if ($this->hasSuccessfulVersion($versionName)) {
                $results[$versionName] = 'skipped';
                continue;
            }

            try {
                $this->executeSqlFile($file);
                $this->recordVersion($versionName, 'success');
                $results[$versionName] = 'success';
            } catch (Throwable $e) {
                $this->recordVersion($versionName, 'failed');
                throw new RuntimeException('Migration failed: ' . $versionName);
            }
        }
        return $results;
    }

    public function markApplied(string $versionName, string $status = 'success'): void {
        $this->ensureVersionTable();
        $this->recordVersion($versionName, $status);
    }

    private function migrationFiles(): array {
        $files = [];
        foreach ($this->directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
                $files[] = $file;
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function hasSuccessfulVersion(string $versionName): bool {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `system_versions` WHERE `version_name` = :version_name AND `status` = :status');
        $stmt->execute(['version_name' => $versionName, 'status' => 'success']);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function recordVersion(string $versionName, string $status): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `system_versions` (`version_name`, `status`) VALUES (:version_name, :status)
             ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `executed_at` = CURRENT_TIMESTAMP'
        );
        $stmt->execute(['version_name' => $versionName, 'status' => $status]);
    }

    public function executeSqlFile(string $file): void {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Could not read SQL file.');
        }
        foreach ($this->splitSqlStatements($sql) as $statement) {
            $this->executeStatement($statement);
        }
    }

    private function executeStatement(string $statement): void {
        $statement = trim($statement);
        if ($statement === '') {
            return;
        }

        if (preg_match('/^ALTER\s+TABLE\s+/i', $statement) && preg_match('/\bADD\s+(COLUMN|UNIQUE\s+KEY|UNIQUE\s+INDEX|KEY|INDEX)\b/i', $statement)) {
            if ($this->executeCompatibleAlter($statement)) {
                return;
            }
        }

        try {
            $this->pdo->exec($statement);
        } catch (PDOException $e) {
            if ($this->isRecoverableSqlError($e)) {
                return;
            }
            throw $e;
        }
    }

    private function executeCompatibleAlter(string $statement): bool {
        if (!preg_match('/^ALTER\s+TABLE\s+`?([^`\s]+)`?\s+(.+)$/is', $statement, $m)) {
            return false;
        }
        $table = $m[1];
        $clauses = $this->splitCommaClauses($m[2]);
        if (!$clauses) {
            return false;
        }
        foreach ($clauses as $clause) {
            $clause = trim($clause);
            if ($clause === '') {
                continue;
            }
            if (preg_match('/^ADD\s+COLUMN\s+(IF\s+NOT\s+EXISTS\s+)?`?([^`\s]+)`?\s+(.+)$/is', $clause, $cm)) {
                $column = $cm[2];
                if (schemaColumnExists($this->pdo, $table, $column)) {
                    continue;
                }
                $this->pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $cm[3]);
                continue;
            }
            if (preg_match('/^ADD\s+(UNIQUE\s+)?(KEY|INDEX)\s+`?([^`\s]+)`?\s+(.+)$/is', $clause, $im)) {
                $index = $im[3];
                if (schemaIndexExists($this->pdo, $table, $index)) {
                    continue;
                }
            }
            try {
                $this->pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ' . $clause);
            } catch (PDOException $e) {
                if (!$this->isRecoverableSqlError($e)) {
                    throw $e;
                }
            }
        }
        return true;
    }

    private function splitCommaClauses(string $sql): array {
        $clauses = [];
        $buffer = '';
        $quote = null;
        $depth = 0;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }
            if ($char === ',' && $depth === 0) {
                $clauses[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $clauses[] = $buffer;
        }
        return $clauses;
    }

    private function splitSqlStatements(string $sql): array {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($quote === null && $char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
    }

    private function isRecoverableSqlError(PDOException $e): bool {
        $code = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
        return in_array($code, [1050, 1060, 1061, 1062, 1091], true);
    }
}
