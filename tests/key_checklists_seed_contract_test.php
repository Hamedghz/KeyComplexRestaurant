<?php

$root = dirname(__DIR__);
require_once $root . '/database/seeds/seed_key_checklists.php';

$templates = keyChecklistTemplateDefinitions();
if (count($templates) !== 6) {
    throw new RuntimeException('Expected 6 KEY checklist templates.');
}

$expectedCounts = [
    'internal_manager_daily_visit_checklist' => 15,
    'cashier_daily_shift_checklist' => 16,
    'hall_captain_daily_checklist' => 12,
    'delivery_rider_operational_checklist' => 13,
    'kitchen_daily_checklist' => 8,
    'marketing_sales_daily_weekly_checklist' => 6,
];

$templateCodes = [];
$totalItems = 0;
foreach ($templates as $template) {
    $templateCode = $template['template_code'] ?? '';
    if ($templateCode === '' || ($template['role_code'] ?? '') === '' || ($template['title'] ?? '') === '') {
        throw new RuntimeException('Checklist template has required empty fields.');
    }
    $templateCodes[] = $templateCode;
    $items = $template['items'] ?? [];
    if (count($items) !== ($expectedCounts[$templateCode] ?? -1)) {
        throw new RuntimeException('Unexpected item count for ' . $templateCode);
    }
    $itemCodes = [];
    foreach ($items as $item) {
        [$itemCode, $title, $phase, $isRequired, $hasQualityScore, $maxQualityScore, $hasNote, $canCreatePlannerTask] = $item;
        if ($itemCode === '' || $title === '' || $phase === '') {
            throw new RuntimeException('Checklist item has required empty fields.');
        }
        $itemCodes[] = $itemCode;
    }
    if (count($itemCodes) !== count(array_unique($itemCodes))) {
        throw new RuntimeException('Duplicate item_code in template ' . $templateCode);
    }
    $totalItems += count($items);
}
if (count($templateCodes) !== count(array_unique($templateCodes))) {
    throw new RuntimeException('Duplicate template_code found.');
}
if ($totalItems !== 70) {
    throw new RuntimeException('Expected 70 checklist items.');
}

$seed = file_get_contents($root . '/database/seeds/seed_key_checklists.php') ?: '';
foreach (['hr_checklist_templates', 'hr_checklist_items', 'template_code', 'item_code', 'seedKeyChecklists'] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('Checklist seed missing required token: ' . $needle);
    }
}
if (preg_match('/INSERT\s+INTO\s+`?(hr_checklist_assignments|hr_checklist_submissions|planner_tasks|admins|users)/i', $seed)) {
    throw new RuntimeException('Checklist seed must not create submissions, assignments, planner tasks, users, or admins.');
}

$migration = file_get_contents($root . '/database/migrations/2026_07_13_key_checklists_seed.sql') ?: '';
foreach (['CREATE TABLE IF NOT EXISTS `hr_checklist_templates`', 'CREATE TABLE IF NOT EXISTS `hr_checklist_items`', '`template_code`', '`item_code`'] as $needle) {
    if (strpos($migration, $needle) === false) {
        throw new RuntimeException('Checklist migration missing required token: ' . $needle);
    }
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Checklist migration must not contain destructive SQL.');
}

$doc = file_get_contents($root . '/docs/hr-imports/checklist-source-map.md') ?: '';
foreach (['6 templates', '70 checklist items', 'internal_manager_daily_visit_checklist', 'marketing_sales_daily_weekly_checklist'] as $needle) {
    if (strpos($doc, $needle) === false) {
        throw new RuntimeException('Checklist source map missing ' . $needle);
    }
}

echo "key_checklists_seed_contract_test: OK\n";
