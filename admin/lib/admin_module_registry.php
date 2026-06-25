<?php
/**
 * Admin module registry helpers.
 *
 * Keeps page wrappers thin and gives the renderer one stable entry point for
 * loading module configuration from the existing adminModuleDefinitions map.
 */
require_once __DIR__ . '/admin_schema.php';

if (!function_exists('adminModuleLoadConfig')) {
    function adminModuleLoadConfig(string $moduleKey): array {
        $config = adminModuleDefinition($moduleKey);
        if (!$config) {
            http_response_code(404);
            exit('Module not found.');
        }

        $config['key'] = $moduleKey;
        return $config;
    }
}
