<?php
/**
 * Reconciles an existing database with the canonical schema without using
 * MySQL-8-only ALTER/INDEX IF NOT EXISTS syntax.
 */

require_once __DIR__ . '/db_schema_helper.php';

class SchemaSynchronizer {
    public static function sync(PDO $pdo, ?string $schemaFile = null): array {
        $schemaFile = $schemaFile ?: dirname(__DIR__) . '/database/schema.sql';
        if (!is_readable($schemaFile)) {
            throw new RuntimeException('Canonical schema file is missing.');
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Canonical schema file is empty.');
        }

        $changes = [];
        foreach (self::createTableStatements($sql) as $table => $statement) {
            if (!schemaTableExists($pdo, $table)) {
                $pdo->exec($statement);
                $changes[] = 'created table ' . $table;
                continue;
            }

            $definition = self::tableDefinitionBody($statement);
            if ($definition === null) {
                continue;
            }

            foreach (self::splitClauses($definition) as $clause) {
                $clause = trim($clause);
                if ($clause === '') {
                    continue;
                }

                if (preg_match('/^`([^`]+)`\s+(.+)$/s', $clause, $m)) {
                    $column = $m[1];
                    if (!schemaColumnExists($pdo, $table, $column)) {
                        self::safeExec($pdo, 'ALTER TABLE `' . self::quoteIdentifier($table) . '` ADD COLUMN `' . self::quoteIdentifier($column) . '` ' . $m[2]);
                        $changes[] = 'added column ' . $table . '.' . $column;
                    }
                    continue;
                }

                if (preg_match('/^(UNIQUE\s+)?KEY\s+`([^`]+)`\s*(\(.+\))$/is', $clause, $m)) {
                    $unique = trim((string)$m[1]) !== '' ? 'UNIQUE ' : '';
                    $index = $m[2];
                    if (!schemaIndexExists($pdo, $table, $index)) {
                        self::safeExec($pdo, 'ALTER TABLE `' . self::quoteIdentifier($table) . '` ADD ' . $unique . 'KEY `' . self::quoteIdentifier($index) . '` ' . $m[3]);
                        $changes[] = 'added index ' . $table . '.' . $index;
                    }
                }
            }
        }

        return $changes;
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
        $length = strlen($statement);
        for ($i = $start; $i < $length; $i++) {
            $char = $statement[$i];
            if ($quote !== null) {
                if ($char === $quote && ($i === 0 || $statement[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($statement, $start + 1, $i - $start - 1);
                }
            }
        }
        return null;
    }

    private static function splitStatements(string $sql): array {
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

    private static function splitClauses(string $sql): array {
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

    private static function safeExec(PDO $pdo, string $sql): void {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $code = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
            if (!in_array($code, [1050, 1060, 1061, 1062, 1091, 1215, 1826], true)) {
                throw $e;
            }
        }
    }

    private static function quoteIdentifier(string $identifier): string {
        return str_replace('`', '``', $identifier);
    }
}
