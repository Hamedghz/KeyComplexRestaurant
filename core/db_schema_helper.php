<?php
/**
 * Database schema inspection helpers.
 *
 * These helpers use prepared statements and INFORMATION_SCHEMA so installer and
 * migrations can safely check structure on MySQL 5.7/shared-hosting servers
 * without relying on unsupported IF NOT EXISTS ALTER/INDEX syntax.
 */

if (!function_exists('normalizeSchemaIdentifier')) {
    function normalizeSchemaIdentifier(string $identifier): string {
        return str_replace(['`', "\0"], '', trim($identifier));
    }
}

if (!function_exists('schemaTableExists')) {
    function schemaTableExists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => normalizeSchemaIdentifier($table)]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('schemaColumnExists')) {
    function schemaColumnExists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => normalizeSchemaIdentifier($table),
            'column_name' => normalizeSchemaIdentifier($column),
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('schemaIndexExists')) {
    function schemaIndexExists(PDO $pdo, string $table, string $index): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
        );
        $stmt->execute([
            'table_name' => normalizeSchemaIdentifier($table),
            'index_name' => normalizeSchemaIdentifier($index),
        ]);
        return (int)$stmt->fetchColumn() > 0;
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
