<?php

$root = dirname(__DIR__);
require_once $root . '/database/seeds/seed_key_roles.php';

$definitions = keyRestaurantRoleDefinitions();
if (count($definitions) !== 21) {
    throw new RuntimeException('Expected 21 KEY restaurant roles.');
}

$codes = [];
$parents = [];
foreach ($definitions as $role) {
    [$roleCode, $titleFa, $titleEn, $department, $parentRoleCode, $level, $isManagerial, $description] = $role;
    if ($roleCode === '' || $titleFa === '' || $department === '' || $description === '') {
        throw new RuntimeException('Role definition has required empty fields.');
    }
    $codes[] = $roleCode;
    if ($parentRoleCode !== null) {
        $parents[] = $parentRoleCode;
    }
}
if (count($codes) !== count(array_unique($codes))) {
    throw new RuntimeException('Duplicate role_code found.');
}
foreach ($parents as $parentRoleCode) {
    if (!in_array($parentRoleCode, $codes, true)) {
        throw new RuntimeException('Missing parent role_code: ' . $parentRoleCode);
    }
}

$seed = file_get_contents($root . '/database/seeds/seed_key_roles.php') ?: '';
foreach (['hr_roles', 'role_code', 'seedKeyRoles', 'keyRestaurantRoleDefinitions'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('Seed missing required token: ' . $needle);
    }
}
if (preg_match('/INSERT\s+INTO\s+`?(admins|users)`?/i', $seed)) {
    throw new RuntimeException('Role seed must not create users or admins.');
}

$migration = file_get_contents($root . '/database/migrations/2026_07_11_key_roles_seed.sql') ?: '';
if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `hr_roles`') === false) {
    throw new RuntimeException('Role migration must create hr_roles.');
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Role migration must not contain destructive SQL.');
}

$doc = file_get_contents($root . '/docs/hr-imports/roles-source-map.md') ?: '';
foreach (['restaurant_owner', 'restaurant_manager', 'internal_manager', 'marketing_sales_manager'] as $roleCode) {
    if (strpos($doc, $roleCode) === false) {
        throw new RuntimeException('Role source map missing ' . $roleCode);
    }
}

echo "key_roles_seed_contract_test: OK\n";
