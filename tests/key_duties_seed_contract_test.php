<?php

$root = dirname(__DIR__);
require_once $root . '/database/seeds/seed_key_duties.php';

$definitions = keyRestaurantDutyDefinitions();
if (count($definitions) !== 67) {
    throw new RuntimeException('Expected 67 KEY restaurant duties.');
}

$expectedByRole = [
    'internal_manager' => 10,
    'cashier' => 8,
    'hall_captain' => 7,
    'waiter' => 5,
    'head_chef' => 6,
    'assistant_chef' => 5,
    'hall_service' => 5,
    'kitchen_service' => 5,
    'delivery_rider' => 5,
    'marketing_sales_manager' => 6,
    'page_admin' => 3,
    'purchasing_manager' => 2,
];

$codes = [];
$counts = [];
foreach ($definitions as $duty) {
    [$dutyCode, $roleCode, $title, $description, $responsibilityType, $priority] = $duty;
    if ($dutyCode === '' || $roleCode === '' || $title === '' || $description === '') {
        throw new RuntimeException('Duty definition has required empty fields.');
    }
    if (!in_array($responsibilityType, ['daily','shift','weekly','monthly','as_needed'], true)) {
        throw new RuntimeException('Invalid responsibility type: ' . $responsibilityType);
    }
    if (!in_array($priority, ['low','medium','high','critical'], true)) {
        throw new RuntimeException('Invalid priority: ' . $priority);
    }
    $codes[] = $dutyCode;
    $counts[$roleCode] = ($counts[$roleCode] ?? 0) + 1;
}
if (count($codes) !== count(array_unique($codes))) {
    throw new RuntimeException('Duplicate duty_code found.');
}
foreach ($expectedByRole as $roleCode => $expectedCount) {
    if (($counts[$roleCode] ?? 0) !== $expectedCount) {
        throw new RuntimeException('Unexpected duty count for ' . $roleCode);
    }
}

$seed = file_get_contents($root . '/database/seeds/seed_key_duties.php') ?: '';
foreach (['hr_role_duties', 'duty_code', 'seedKeyDuties', 'keyRestaurantDutyDefinitions'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('Seed missing required token: ' . $needle);
    }
}
if (preg_match('/INSERT\s+INTO\s+`?(hr_checklist|hr_kpi|admins|users)/i', $seed)) {
    throw new RuntimeException('Duty seed must not create checklists, KPIs, users, or admins.');
}

$migration = file_get_contents($root . '/database/migrations/2026_07_12_key_duties_seed.sql') ?: '';
foreach (['CREATE TABLE IF NOT EXISTS `hr_role_duties`', '`duty_code`', '`responsibility_type`', '`priority`'] as $needle) {
    if (strpos($migration, $needle) === false) {
        throw new RuntimeException('Duty migration missing required token: ' . $needle);
    }
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Duty migration must not contain destructive SQL.');
}

$doc = file_get_contents($root . '/docs/hr-imports/duties-source-map.md') ?: '';
foreach (['internal_manager', 'cashier', 'hall_captain', 'purchasing_manager', '67 duties'] as $needle) {
    if (strpos($doc, $needle) === false) {
        throw new RuntimeException('Duty source map missing ' . $needle);
    }
}

echo "key_duties_seed_contract_test: OK\n";
