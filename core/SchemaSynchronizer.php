<?php

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

        // 🔥 CRITICAL FIX: buffered queries to avoid PDO 2014
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        $changes = [];

        foreach (self::createTableStatements($sql) as $table => $statement) {

            if (!schemaTableExists($pdo, $table)) {
                $pdo->exec($statement);
                $pdo->query("SELECT 1")->closeCursor();
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

                        self::safeExec(
                            $pdo,
                            'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $m[2]
                        );

                        $pdo->query("SELECT 1")->closeCursor();

                        $changes[] = 'added column ' . $table . '.' . $column;
                    }

                    continue;
                }

                if (preg_match('/^(UNIQUE\s+)?KEY\s+`([^`]+)`\s*(\(.+\))$/is', $clause, $m)) {

                    $unique = trim((string)$m[1]) !== '' ? 'UNIQUE ' : '';
                    $index = $m[2];

                    if (!schemaIndexExists($pdo, $table, $index)) {

                        self::safeExec(
                            $pdo,
                            'ALTER TABLE `' . $table . '` ADD ' . $unique . 'KEY `' . $index . '` ' . $m[3]
                        );

                        $pdo->query("SELECT 1")->closeCursor();

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

        if ($start === false) return null;

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

            if ($c === '(') $depth++;
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
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            if ($quote === null && $c === '/' && $n === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) $i++;
                continue;
            }

            if ($quote !== null) {
                $buf .= $c;
                if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) $quote = null;
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                $buf .= $c;
                continue;
            }

            if ($c === ';') {
                $t = trim($buf);
                if ($t !== '') $out[] = $t;
                $buf = '';
                continue;
            }

            $buf .= $c;
        }

        $t = trim($buf);
        if ($t !== '') $out[] = $t;

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
                if ($c === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) $quote = null;
                continue;
            }

            if (in_array($c, ["'", '"', '`'], true)) {
                $quote = $c;
                $buf .= $c;
                continue;
            }

            if ($c === '(') $depth++;
            if ($c === ')' && $depth > 0) $depth--;

            if ($c === ',' && $depth === 0) {
                $out[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $c;
        }

        if (trim($buf) !== '') $out[] = $buf;

        return $out;
    }

    private static function safeExec(PDO $pdo, string $sql): void {

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {

            $code = $e->errorInfo[1] ?? 0;

            if (!in_array((int)$code, [1050,1060,1061,1062,1091,1215,1826], true)) {
                throw $e;
            }
        }
    }
}
