<?php

if (!function_exists('normalizeSchemaIdentifier')) {
    function normalizeSchemaIdentifier(string $identifier): string {
        return str_replace(['`', "\0"], '', trim($identifier));
    }
}

if (!function_exists('schemaTableExists')) {
    function schemaTableExists(PDO $pdo, string $table): bool {

        $stmt = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");

        $stmt->execute([
            'table_name' => normalizeSchemaIdentifier($table)
        ]);

        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $result !== false;
    }
}

if (!function_exists('schemaColumnExists')) {
    function schemaColumnExists(PDO $pdo, string $table, string $column): bool {

        $stmt = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");

        $stmt->execute([
            'table_name' => normalizeSchemaIdentifier($table),
            'column_name' => normalizeSchemaIdentifier($column),
        ]);

        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $result !== false;
    }
}

if (!function_exists('schemaIndexExists')) {
    function schemaIndexExists(PDO $pdo, string $table, string $index): bool {

        $stmt = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
            LIMIT 1
        ");

        $stmt->execute([
            'table_name' => normalizeSchemaIdentifier($table),
            'index_name' => normalizeSchemaIdentifier($index),
        ]);

        $result = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $result !== false;
    }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool {
        return schemaTableExists($pdo, $table);
    }
}

if (!function_exists('columnExists')) {
    function columnExists(PDO $pdo, string $table, string $column): bool {
        return schemaColumnExists($pdo, $table, $column);
    }
}

if (!function_exists('indexExists')) {
    function indexExists(PDO $pdo, string $table, string $index): bool {
        return schemaIndexExists($pdo, $table, $index);
    }
}
