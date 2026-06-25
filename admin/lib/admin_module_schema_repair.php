<?php
/**
 * Central non-destructive schema repair wrapper for admin CRUD modules.
 *
 * This file does not drop, truncate, or recreate existing production data.
 * It delegates to the existing safe schema helpers, then returns a compact
 * audit payload that the renderer can use before building forms and tables.
 */
require_once __DIR__ . '/admin_module_registry.php';

if (!function_exists('adminRepairModuleSchema')) {
    function adminRepairModuleSchema(array $config): array {
        $result = [
            'ok' => false,
            'missing_after_repair' => [],
            'changes' => [],
            'audit' => [],
        ];

        try {
            adminEnsureModuleTables($config);
            $audit = adminModuleSchemaAudit($config);

            $missing = array_values(array_unique(array_merge(
                $audit['missing_fields'] ?? [],
                $audit['missing_columns'] ?? [],
                $audit['missing_filters'] ?? [],
                $audit['missing_search'] ?? []
            )));

            $result['audit'] = $audit;
            $result['missing_after_repair'] = $missing;
            $result['ok'] = count($missing) === 0 || !empty($config['readonly_create']);

            if ($missing) {
                safeAdminLog('Admin module schema repair incomplete for ' . ($config['key'] ?? $config['table'] ?? 'unknown') . ': ' . implode(', ', $missing));
            }
        } catch (Throwable $e) {
            $result['ok'] = false;
            $result['missing_after_repair'] = ['repair_exception'];
            safeAdminLog('Admin module schema repair failed for ' . ($config['key'] ?? $config['table'] ?? 'unknown') . ': ' . $e->getMessage());
        }

        return $result;
    }
}
