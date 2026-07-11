<?php

$root = dirname(__DIR__);

foreach ([
    '/admin/lib/hr/okr.php',
    '/admin/lib/hr/okr_pages.php',
    '/admin/okr-objectives.php',
    '/admin/okr-key-results.php',
    '/admin/okr-actions.php',
    '/admin/okr-progress.php',
    '/admin/tmo-review.php',
    '/admin/tmo-dashboard.php',
    '/database/migrations/2026_07_18_okr_tmo_management.sql',
    '/docs/hr-imports/okr-tmo-management.md',
] as $file) {
    if (!is_file($root . $file)) {
        throw new RuntimeException('Missing Phase 8 file: ' . $file);
    }
}

$migration = file_get_contents($root . '/database/migrations/2026_07_18_okr_tmo_management.sql') ?: '';
foreach (['okr_objectives', 'okr_key_results', 'okr_actions', 'okr_kpi_links', 'okr_progress_logs', 'tmo_reviews'] as $table) {
    if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('Phase 8 migration missing table: ' . $table);
    }
}
if (strpos($migration, 'CREATE TABLE IF NOT EXISTS `tmo_objectives`') !== false || strpos($migration, 'CREATE TABLE IF NOT EXISTS `hr_tmo_objectives`') !== false) {
    throw new RuntimeException('TMO must not be implemented as a standalone objective table.');
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $migration)) {
    throw new RuntimeException('Phase 8 migration must not contain destructive SQL.');
}

$service = file_get_contents($root . '/admin/lib/hr/okr.php') ?: '';
foreach (['plannerCreateOkrActionTask', 'hr_kpi_scores', 'okrFinalProgress', 'max(okrClamp($manual), okrClamp($calculated))', 'tmo_user_id', 'okrSeedExamples'] as $needle) {
    if (strpos($service, $needle) === false) {
        throw new RuntimeException('OKR service missing token: ' . $needle);
    }
}

$pages = file_get_contents($root . '/admin/lib/hr/okr_pages.php') ?: '';
foreach (['okrRenderObjectivesPage', 'okrRenderKeyResultsPage', 'okrRenderActionsPage', 'okrRenderProgressPage', 'okrRenderTmoReviewPage', 'okrRenderTmoDashboardPage', 'okrLinkKpi', 'create_planner_task'] as $needle) {
    if (strpos($pages, $needle) === false) {
        throw new RuntimeException('OKR pages missing token: ' . $needle);
    }
}

foreach ([
    '/admin/hr-okr-objectives.php' => 'okr-objectives.php',
    '/admin/hr-okr-key-results.php' => 'okr-key-results.php',
    '/admin/hr-okr-actions.php' => 'okr-actions.php',
    '/admin/hr-okr-progress.php' => 'okr-progress.php',
    '/admin/hr-tmo-reviews.php' => 'tmo-review.php',
] as $file => $target) {
    $content = file_get_contents($root . $file) ?: '';
    if (strpos($content, "redirectTo('$target')") === false) {
        throw new RuntimeException('Legacy OKR route is not a thin redirect: ' . $file);
    }
}

$schema = file_get_contents($root . '/database/schema.sql') ?: '';
foreach (['okr_objectives', 'okr_key_results', 'okr_actions', 'okr_kpi_links', 'okr_progress_logs', 'tmo_reviews'] as $table) {
    if (strpos($schema, 'CREATE TABLE IF NOT EXISTS `' . $table . '`') === false) {
        throw new RuntimeException('schema.sql missing Phase 8 table: ' . $table);
    }
}

echo "okr_tmo_management_contract_test: OK\n";
