<?php

require_once __DIR__ . '/db_schema_helper.php';

class SchemaSynchronizer {

    private const RECOVERABLE_MYSQL_ERRORS = [1050, 1060, 1061, 1062, 1091, 1215, 1826];

    public static function sync(PDO $pdo, ?string $schemaFile = null): array {
        self::configureBufferedConnection($pdo);

        $schemaFile = $schemaFile ?: dirname(__DIR__) . '/database/schema.sql';

        if (!is_readable($schemaFile)) {
            throw new RuntimeException('Canonical schema file is missing.');
        }

        $sql = file_get_contents($schemaFile);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Canonical schema file is empty.');
        }

        $changes = [];
        $tables = self::createTableStatements($sql);

        foreach ($tables as $table => $statement) {
            if (!self::tableExists($pdo, $table)) {
                self::safeExecute($pdo, $statement);
                $changes[] = 'created table ' . $table;
                continue;
            }

            $definition = self::tableDefinitionBody($statement);

            if ($definition === null) {
                continue;
            }

            $clauses = self::splitClauses($definition);

            foreach ($clauses as $clause) {
                $clause = trim($clause);

                if ($clause === '') {
                    continue;
                }

                if (preg_match('/^`([^`]+)`\s+(.+)$/s', $clause, $m)) {
                    $column = $m[1];

                    if (!self::columnExists($pdo, $table, $column)) {
                        self::safeExecute(
                            $pdo,
                            'ALTER TABLE ' . self::quoteIdentifier($table)
                            . ' ADD COLUMN ' . self::quoteIdentifier($column) . ' ' . $m[2]
                        );
                        $changes[] = 'added column ' . $table . '.' . $column;
                    }

                    continue;
                }

                if (preg_match('/^(UNIQUE\s+)?KEY\s+`([^`]+)`\s*(\(.+\))$/is', $clause, $m)) {
                    $unique = trim((string)$m[1]) !== '' ? 'UNIQUE ' : '';
                    $index = $m[2];

                    if (!self::indexExists($pdo, $table, $index)) {
                        self::safeExecute(
                            $pdo,
                            'ALTER TABLE ' . self::quoteIdentifier($table)
                            . ' ADD ' . $unique . 'KEY ' . self::quoteIdentifier($index) . ' ' . $m[3]
                        );
                        $changes[] = 'added index ' . $table . '.' . $index;
                    }
                }
            }
        }

        return $changes;
    }

    private static function configureBufferedConnection(PDO $pdo): void {
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $pdo->setAttribute(constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY'), true);
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool {
        return self::informationSchemaExists($pdo, "
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ", [
            'table_name' => self::normalizeIdentifier($table),
        ]);
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool {
        return self::informationSchemaExists($pdo, "
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ", [
            'table_name' => self::normalizeIdentifier($table),
            'column_name' => self::normalizeIdentifier($column),
        ]);
    }

    private static function indexExists(PDO $pdo, string $table, string $index): bool {
        return self::informationSchemaExists($pdo, "
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
            LIMIT 1
        ", [
            'table_name' => self::normalizeIdentifier($table),
            'index_name' => self::normalizeIdentifier($index),
        ]);
    }

    private static function informationSchemaExists(PDO $pdo, string $sql, array $params): bool {
        self::configureBufferedConnection($pdo);

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return $rows !== [];
        } finally {
            $stmt->closeCursor();
        }
    }

    private static function safeExecute(PDO $pdo, string $sql): void {
        try {
            self::executeAndDrain($pdo, $sql);
        } catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? 0;

            if (!in_array((int)$code, self::RECOVERABLE_MYSQL_ERRORS, true)) {
                throw $e;
            }
        }
    }

    private static function executeAndDrain(PDO $pdo, string $sql): void {
        self::configureBufferedConnection($pdo);

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute();
            self::drainStatement($stmt);
        } finally {
            $stmt->closeCursor();
        }
    }

    private static function drainStatement(PDOStatement $stmt): void {
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

    private static function createTableStatements(string $sql): array {
        $statements = [];

        foreach (self::splitStatements($sql) as $statement) {
            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $statement, $m)) {
                $statements[$m[1]] = $statement;
            }
        }

        return $statements;
    }

    private static function tableDefinitionBody(string $statement): ?string {
        $start = strpos($statement, '(');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $quote = null;
        $len = strlen($statement);

        for ($i = $start; $i < $len; $i++) {
            $c = $statement[$i];

            if ($quote !== null) {
                if ($c === $quote && ($i === 0 || $statement[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                continue;
            }

            if ($c === '(') {
                $depth++;
            }

            if ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($statement, $start + 1, $i - $start - 1);
                }
            }
        }

        return null;
    }

    private static function splitStatements(string $sql): array {
        $out = [];
        $buf = '';
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
                $buf .= $c;
                if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                $buf .= $c;
                continue;
            }

            if ($c === ';') {
                $statement = trim($buf);
                if ($statement !== '') {
                    $out[] = $statement;
                }
                $buf = '';
                continue;
            }

            $buf .= $c;
        }

        $statement = trim($buf);
        if ($statement !== '') {
            $out[] = $statement;
        }

        return $out;
    }

    private static function splitClauses(string $sql): array {
        $out = [];
        $buf = '';
        $quote = null;
        $depth = 0;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($quote !== null) {
                $buf .= $c;
                if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                $buf .= $c;
                continue;
            }

            if ($c === '(') {
                $depth++;
            }

            if ($c === ')' && $depth > 0) {
                $depth--;
            }

            if ($c === ',' && $depth === 0) {
                $out[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $c;
        }

        if (trim($buf) !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    private static function normalizeIdentifier(string $identifier): string {
        if (function_exists('normalizeSchemaIdentifier')) {
            return normalizeSchemaIdentifier($identifier);
        }

        return str_replace(['`', "\0"], '', trim($identifier));
    }

    private static function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', self::normalizeIdentifier($identifier)) . '`';
    }
}
