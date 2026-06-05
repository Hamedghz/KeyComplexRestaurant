<?php

require_once __DIR__ . '/db_schema_helper.php';

class MigrationRunner {

    private PDO $pdo;
    private array $directories;

    public function __construct(PDO $pdo, array $directories) {
        $this->pdo = $pdo;
        $this->directories = $directories;

        // 🔥 FIX: prevent PDO 2014 unbuffered query crash
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }

    public function ensureVersionTable(): void {

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `system_versions` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `version_name` varchar(255) NOT NULL,
                `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `status` enum('success','failed') NOT NULL DEFAULT 'success',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_system_versions_name` (`version_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->query("SELECT 1")->closeCursor();
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
            if (!is_dir($dir)) continue;

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

        $stmt->execute(['v' => $versionName]);

        $res = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $res !== false;
    }

    private function recordVersion(string $versionName, string $status): void {

        $stmt = $this->pdo->prepare("
            INSERT INTO system_versions (version_name, status)
            VALUES (:v, :s)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                executed_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            'v' => $versionName,
            's' => $status
        ]);

        $stmt->closeCursor();
    }

    public function executeSqlFile(string $file): void {

        $sql = file_get_contents($file);

        if (!$sql) {
            throw new RuntimeException('SQL file read failed');
        }

        foreach ($this->splitSqlStatements($sql) as $statement) {
            $this->executeStatement($statement);
        }
    }

    private function executeStatement(string $statement): void {

        $statement = trim($statement);
        if ($statement === '') return;

        try {
            $this->pdo->exec($statement);

        } catch (PDOException $e) {

            if (!$this->isRecoverableSqlError($e)) {
                throw $e;
            }
        }

        // 🔥 FIX: release implicit cursor state
        $this->pdo->query("SELECT 1")->closeCursor();
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
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            if ($quote === null && $c === '/' && $n === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) $i++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $c;
                if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) $quote = null;
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                $buffer .= $c;
                continue;
            }

            if ($c === ';') {
                $t = trim($buffer);
                if ($t !== '') $statements[] = $t;
                $buffer = '';
                continue;
            }

            $buffer .= $c;
        }

        $t = trim($buffer);
        if ($t !== '') $statements[] = $t;

        return $statements;
    }

    private function isRecoverableSqlError(PDOException $e): bool {

        $code = $e->errorInfo[1] ?? 0;

        return in_array((int)$code, [
            1050,1060,1061,1062,1091,1215,1826
        ], true);
    }
}
