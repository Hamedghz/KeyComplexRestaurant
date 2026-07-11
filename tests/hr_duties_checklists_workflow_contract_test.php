<?php

$root = dirname(__DIR__);

$files = [
    '/admin/lib/hr/checklists.php',
    '/admin/lib/hr/checklist_pages.php',
    '/admin/hr-duties.php',
    '/admin/hr-role-duties.php',
    '/admin/hr-checklist-templates.php',
    '/admin/hr-checklist-assignments.php',
    '/admin/hr-checklist-submissions.php',
    '/admin/hr-checklist-approvals.php',
    '/admin/hr-checklist-report.php',
    '/admin/hr-checklist-progress.php',
    '/database/seeds/seed_key_restaurant_duties_checklists.php',
    '/database/migrations/2026_07_15_hr_duties_checklists.sql',
    '/docs/hr-imports/duties-checklists-workflow.md',
];
foreach ($files as $file) {
    if (!is_file($root . $file)) {
        throw new RuntimeException('Missing Phase 5 file: ' . $file);
    }
}

$library = file_get_contents($root . '/admin/lib/hr/checklists.php') ?: '';
foreach ([
    'hrChecklistEnsureSchema',
    'hr_checklist_submission_items',
    'hr_checklist_approvals',
    'plannerCreateLinkedTask',
    'source_module',
    'checklist',
] as $needle) {
    if (strpos($library, $needle) === false) {
        throw new RuntimeException('Checklist library missing required token: ' . $needle);
    }
}

$seed = file_get_contents($root . '/database/seeds/seed_key_restaurant_duties_checklists.php') ?: '';
foreach ([
    'seedKeyRestaurantDutiesChecklists',
    'hr_checklist_categories',
    'business_coaching_sales_behavior_checklist',
    'bc_starts_with_question',
    'bc_creates_corrective_task',
] as $needle) {
    if (strpos($seed, $needle) === false) {
        throw new RuntimeException('Phase 5 seed missing required token: ' . $needle);
    }
}
if (preg_match('/INSERT\s+INTO\s+`?(admins|users|hr_checklist_submissions|hr_checklist_assignments)/i', $seed)) {
    throw new RuntimeException('Phase 5 seed must not create users, submissions, or assignments.');
}

$migration = file_get_contents($root . '/database/migrations/2026_07_15_hr_duties_checklists.sql') ?: '';
foreach (['hr_role_duties', 'hr_checklist_templates', 'hr_checklist_items', 'hr_checklist_categories', 'hr_checklist_assignments', 'hr_checklist_submissions', 'hr_checklist_submission_items', 'hr_checklist_approvals'] as $table) {
    if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('Phase 5 migration missing table: ' . $table);
    }
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Phase 5 migration must not contain destructive SQL.');
}

$navigation = (file_get_contents($root . '/database/migrations/2026_06_18_admin_navigation.sql') ?: '')
    . "\n" . (file_get_contents($root . '/database/migrations/2026_07_09_hr_core_foundation.sql') ?: '');
if (strpos($navigation, "'business_standards") !== false) {
    throw new RuntimeException('Business standards must not be added to visible navigation.');
}
if (substr_count($navigation, 'hr_role_duties') < 1) {
    throw new RuntimeException('Approved duties/checklist menu route must remain present.');
}

echo "hr_duties_checklists_workflow_contract_test: OK\n";
