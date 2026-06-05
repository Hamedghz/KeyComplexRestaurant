<?php

if (!function_exists('normalizeSchemaIdentifier')) {
    function normalizeSchemaIdentifier(string $identifier): string {
        return str_replace(['`', "\0"], '', trim($identifier));
    }
}

if (!function_exists('schemaConfigureBufferedConnection')) {
    function schemaConfigureBufferedConnection(PDO $pdo): void {
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $pdo->setAttribute(constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY'), true);
        }
    }
}

if (!function_exists('schemaInformationExists')) {
    function schemaInformationExists(PDO $pdo, string $sql, array $params): bool {
        schemaConfigureBufferedConnection($pdo);

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            return $rows !== [];
        } finally {
            $stmt->closeCursor();
        }
    }
}

if (!function_exists('schemaTableExists')) {
    function schemaTableExists(PDO $pdo, string $table): bool {
        return schemaInformationExists($pdo, "
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ", [
            'table_name' => normalizeSchemaIdentifier($table),
        ]);
    }
}

if (!function_exists('schemaColumnExists')) {
    function schemaColumnExists(PDO $pdo, string $table, string $column): bool {
        return schemaInformationExists($pdo, "
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ", [
            'table_name' => normalizeSchemaIdentifier($table),
            'column_name' => normalizeSchemaIdentifier($column),
        ]);
    }
}

if (!function_exists('schemaIndexExists')) {
    function schemaIndexExists(PDO $pdo, string $table, string $index): bool {
        return schemaInformationExists($pdo, "
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
            LIMIT 1
        ", [
            'table_name' => normalizeSchemaIdentifier($table),
            'index_name' => normalizeSchemaIdentifier($index),
        ]);
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
