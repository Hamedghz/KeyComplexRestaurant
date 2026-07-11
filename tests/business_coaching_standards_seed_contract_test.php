<?php
$root = dirname(__DIR__);
require_once $root . '/database/seeds/seed_key_business_coaching_standards.php';

$definitions = keyBusinessCoachingStandardsDefinitions();
if (count($definitions) !== 13) {
    throw new RuntimeException('Expected 13 business coaching standard groups.');
}

$expectedCounts = [
    'customer_types' => 13,
    'customer_journey' => 11,
    'sales_script' => 8,
    'fab' => 4,
    'listening' => 5,
    'objections' => 8,
    'voip_call_review_ready' => 9,
    'after_sales_support' => 7,
    'referral' => 5,
    'financial_reporting' => 11,
    'bsf' => 6,
    'sop_5s' => 9,
    'behavior' => 7,
];

$total = 0;
foreach ($expectedCounts as $group => $expectedCount) {
    if (!isset($definitions[$group])) {
        throw new RuntimeException('Missing group: ' . $group);
    }
    $items = $definitions[$group]['items'] ?? [];
    if (count($items) !== $expectedCount) {
        throw new RuntimeException('Unexpected item count for ' . $group . ': ' . count($items));
    }
    $keys = array_map(static fn($item) => $item[0] ?? '', $items);
    if (count($keys) !== count(array_unique($keys))) {
        throw new RuntimeException('Duplicate item key in group: ' . $group);
    }
    $total += count($items);
}
if ($total !== 103) {
    throw new RuntimeException('Expected 103 business coaching standard items.');
}

$seed = file_get_contents($root . '/database/seeds/seed_key_business_coaching_standards.php') ?: '';
foreach (['business_standards', 'business_standard_items', 'ON DUPLICATE KEY UPDATE'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('Seed missing required token: ' . $needle);
    }
}

$navigation = (file_get_contents($root . '/database/migrations/2026_06_18_admin_navigation.sql') ?: '')
    . "\n" . (file_get_contents($root . '/database/migrations/2026_07_09_hr_core_foundation.sql') ?: '');
if (preg_match("/'business_standards[^']*'/", $navigation)) {
    throw new RuntimeException('Business standards must not be a visible navigation item.');
}

echo "business_coaching_standards_seed_contract_test: OK\n";
