<?php

require_once __DIR__ . '/db_schema_helper.php';

class MigrationRunner {

    private PDO $pdo;
    private array $directories;

    public function __construct(PDO $pdo, array $directories) {
        $this->pdo = $pdo;
        $this->directories = $directories;
        self::configureBufferedConnection($this->pdo);
    }

    public function ensureVersionTable(): void {
        $this->executePreparedStatement("
            CREATE TABLE IF NOT EXISTS `system_versions` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `version_name` varchar(255) NOT NULL,
                `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `status` enum('success','failed') NOT NULL DEFAULT 'success',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_system_versions_name` (`version_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function run(): array {
        self::configureBufferedConnection($this->pdo);
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
                throw new RuntimeException('Migration failed: ' . $versionName, 0, $e);
            }
        }

        return $results;
    }

    public function markApplied(string $versionName, string $status = 'success'): void {
        self::configureBufferedConnection($this->pdo);
        $this->ensureVersionTable();
        $this->recordVersion($versionName, $status);
    }

    public function executeSqlFile(string $file, ?bool $allowSeedStatements = null): void {
        self::configureBufferedConnection($this->pdo);

        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException('SQL file read failed: ' . basename($file));
        }

        foreach ($this->splitSqlStatements($sql) as $statement) {
            $isSeed = $this->isSeedStatement($statement);
            $table = $isSeed ? $this->seedStatementTable($statement) : '';
            $skipSeed = $isSeed && ($allowSeedStatements === false
                || ($allowSeedStatements === null && $table !== '' && $this->databaseTableHasRows($table)));
            if ($skipSeed) {
                error_log($table !== '' ? 'SEED SKIPPED: ' . $table . ' already contains data' : 'SEED SKIPPED: production data exists');
                continue;
            }
            $this->executeStatement($statement);
        }
    }

    private function isSeedStatement(string $statement): bool {
        return (bool)preg_match('/^\s*(INSERT|REPLACE)\s+/i', $statement);
    }

    private function seedStatementTable(string $statement): string {
        return preg_match('/^\s*(?:INSERT(?:\s+IGNORE)?|REPLACE)\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $match)
            ? $match[1]
            : '';
    }

    private function databaseTableHasRows(string $table): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !schemaTableExists($this->pdo, $table)) return false;
        $stmt = $this->pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        try {
            return (bool)$stmt->fetchColumn();
        } finally {
            $stmt->closeCursor();
        }
    }

    public static function configureBufferedConnection(PDO $pdo): void {
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $pdo->setAttribute(constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY'), true);
        }
    }

    private function migrationFiles(): array {
        $files = [];

        foreach ($this->directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob(rtrim($dir, DIRECTORY_SEPARATOR) . '/*.sql') ?: [] as $file) {
                $files[] = $file;
            }
        }

        sort($files, SORT_STRING);
        return $files;
    }

    private function hasSuccessfulVersion(string $versionName): bool {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM system_versions
            WHERE version_name = :v AND status = 'success'
            LIMIT 1
        ");

        try {
            $stmt->execute(['v' => $versionName]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return $rows !== [];
        } finally {
            $stmt->closeCursor();
        }
    }

    private function recordVersion(string $versionName, string $status): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_versions (version_name, status)
            VALUES (:v, :s)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                executed_at = CURRENT_TIMESTAMP
        ");

        try {
            $stmt->execute([
                'v' => $versionName,
                's' => $status,
            ]);
        } finally {
            $stmt->closeCursor();
        }
    }

    private function executeStatement(string $statement): void {
        $statement = trim($statement);

        if ($statement === '') {
            return;
        }

        try {
            $this->executePreparedStatement($statement);
        } catch (PDOException $e) {
            if (!$this->isRecoverableSqlError($e)) {
                throw $e;
            }
        }
    }

    private function executePreparedStatement(string $sql, array $params = []): void {
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute($params);
            $this->drainStatement($stmt);
        } finally {
            $stmt->closeCursor();
        }
    }

    private function drainStatement(PDOStatement $stmt): void {
        do {
            if ($stmt->columnCount() > 0) {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            try {
                $hasMoreRows = $stmt->nextRowset();
            } catch (PDOException $e) {
                $hasMoreRows = false;
            }
        } while ($hasMoreRows);
    }

    private function splitSqlStatements(string $sql): array {
        $statements = [];
        $buffer = '';
        $quote = null;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $n = $sql[$i + 1] ?? '';

            if ($quote === null && $c === '-' && $n === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($quote === null && $c === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($quote === null && $c === '/' && $n === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $c;
                if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                $buffer .= $c;
                continue;
            }

            if ($c === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $c;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private function isRecoverableSqlError(PDOException $e): bool {
        $code = $e->errorInfo[1] ?? 0;

        return in_array((int)$code, [
            1050,
            1060,
            1061,
            1062,
            1091,
            1215,
            1826,
        ], true);
    }
}
