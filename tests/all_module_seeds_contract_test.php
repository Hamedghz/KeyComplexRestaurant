<?php

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (file_get_contents($full) ?: '') : '';
};

$requiredSeeds = [
    'key_business_coaching_standards' => 'database/seeds/seed_key_business_coaching_standards.php',
    'key_restaurant_duties_checklists' => 'database/seeds/seed_key_restaurant_duties_checklists.php',
    'key_restaurant_kpis' => 'database/seeds/seed_key_restaurant_kpis.php',
    'key_restaurant_organizational_tests' => 'database/seeds/seed_key_restaurant_organizational_tests.php',
    'key_planner_defaults' => 'database/seeds/seed_key_planner_defaults.php',
    'key_okr_tmo_samples' => 'database/seeds/seed_key_okr_tmo_samples.php',
    'key_hr_permissions' => 'database/seeds/seed_key_hr_permissions.php',
    'key_hr_navigation' => 'database/seeds/seed_key_hr_navigation.php',
    'hr_performance_suite' => 'database/seeds/seed_hr_performance_suite.php',
];

$runner = $read('admin/lib/system_seed_runner.php');
$systemUpdate = $read('admin/system-update.php');
$fa = $read('lang/fa.php');
$en = $read('lang/en.php');

foreach ($requiredSeeds as $seedKey => $file) {
    $content = $read($file);
    $assert($content !== '', 'Required seed file is missing or unreadable: ' . $file);
    $assert(str_contains($runner, "'" . $seedKey . "'"), 'Required seed key is not registered: ' . $seedKey);
    $assert(str_contains($runner, "'" . $file . "'"), 'Required seed file is not registered: ' . $file);
    $assert(!preg_match('/\b(?:TRUNCATE|DROP)\b/i', $content), 'Seed file contains TRUNCATE/DROP: ' . $file);
    $assert(!preg_match('/\bDELETE\s+FROM\b/i', $content), 'Seed file contains DELETE FROM: ' . $file);
    $assert(!preg_match('/\b(?:echo|print|var_dump|die|exit)\b/i', $content), 'Seed file emits output or exits directly: ' . $file);
    foreach (['inserted', 'updated', 'skipped'] as $counter) {
        $assert(str_contains($content, "'" . $counter . "'"), 'Seed does not report counter ' . $counter . ': ' . $file);
    }
    $assert(str_contains($content, "'messages'") || $seedKey === 'hr_performance_suite', 'Seed does not report messages: ' . $file);
}

$assert(!str_contains($runner, 'seed_restaurant_hr_tests.php'), 'Legacy restaurant HR test seed must not be registered.');
$legacySeed = $read('database/seeds/seed_restaurant_hr_tests.php');
$assert(str_contains($legacySeed, 'LEGACY_HR_TESTS_SEED_DISABLED'), 'Legacy restaurant HR test seed is not explicitly disabled.');
$assert(str_contains($legacySeed, 'legacy_disabled'), 'Legacy restaurant HR test seed does not return a disabled result.');

$business = $read('database/seeds/seed_key_business_coaching_standards.php');
foreach (['customer_types','customer_journey','sales_script','fab','listening','objections','voip_call_review_ready','after_sales_support','referral','financial_reporting','bsf','sop_5s','behavior'] as $group) {
    $assert(str_contains($business, "'" . $group . "'"), 'Business standards seed missing group: ' . $group);
}
foreach (['business_standards', 'business_standard_items', 'ON DUPLICATE KEY UPDATE'] as $needle) {
    $assert(str_contains($business, $needle), 'Business standards seed missing token: ' . $needle);
}

$roles = $read('database/seeds/seed_key_roles.php');
$duties = $read('database/seeds/seed_key_duties.php');
$checklists = $read('database/seeds/seed_key_checklists.php');
$dutiesWrapper = $read('database/seeds/seed_key_restaurant_duties_checklists.php');
foreach (['مدیر داخلی','صندوق‌دار','مسئول سالن','سالن‌دار / گارسون','پیک موتوری','سرآشپز','کمک‌آشپز','نیروی خدمات سالن','نیروی خدمات آشپزخانه','مسئول بازاریابی و فروش'] as $roleTitle) {
    $assert(str_contains($roles, $roleTitle), 'Restaurant role seed missing title: ' . $roleTitle);
}
foreach (['internal_manager_daily_visit_checklist','cashier_daily_shift_checklist','hall_captain_daily_checklist','delivery_rider_operational_checklist','internal_manager_daily_kpi_checklist','sop_execution','5s_audit'] as $needle) {
    $assert(str_contains($checklists . $dutiesWrapper, $needle), 'Duties/checklists seed missing template/category: ' . $needle);
}
foreach (['hr_role_duties','hr_checklist_templates','hr_checklist_items','ON DUPLICATE KEY UPDATE'] as $needle) {
    $assert(str_contains($duties . $checklists . $dutiesWrapper, $needle), 'Duties/checklists seed missing idempotent token: ' . $needle);
}

$kpiWrapper = $read('database/seeds/seed_key_restaurant_kpis.php');
$kpis = $read('database/seeds/seed_key_kpis.php');
$assert(str_contains($kpiWrapper, 'seedKeyKpis'), 'Restaurant KPI wrapper must delegate to seedKeyKpis.');
foreach (['bsf_lead_count','bsf_conversion_rate','bsf_purchase_count','bsf_average_purchase','bsf_profit_margin','script_compliance_score','customer_complaint_count','complaint_resolution_time','marketing_roas','marketing_cac','cashier_cash_difference','hall_captain_customer_experience_score'] as $needle) {
    $assert(str_contains($kpis, $needle), 'KPI seed missing required KPI metric: ' . $needle);
}
$assert(str_contains($kpis, 'hr_kpi_definitions'), 'KPI seed must target hr_kpi_definitions.');

$testWrapper = $read('database/seeds/seed_key_restaurant_organizational_tests.php');
$tests = $read('database/seeds/seed_key_organizational_tests.php');
$assert(str_contains($testWrapper, 'seedKeyOrganizationalTests'), 'Restaurant organizational tests wrapper must delegate to seedKeyOrganizationalTests.');
$assert(!str_contains($testWrapper, "require_once __DIR__ . '/seed_restaurant_hr_tests.php'"), 'Restaurant organizational tests wrapper must not call legacy seed.');
foreach (['KEY_ORG_BEHAVIOR','KEY_RESTAURANT_OPERATIONS','KEY_SALES_CUSTOMER_INTERACTION','KEY_MARKETING_CONTENT','KEY_KPI_REPORTING_LITERACY','hr_assessment_tests','hr_test_questions','hr_test_options','ON DUPLICATE KEY UPDATE'] as $needle) {
    $assert(str_contains($tests, $needle), 'Organizational tests seed missing token: ' . $needle);
}
$assert(!str_contains($tests, 'CREATE TABLE IF NOT EXISTS `hr_tests`'), 'Organizational tests seed must not create parallel hr_tests table.');

$planner = $read('database/seeds/seed_key_planner_defaults.php');
foreach (['low','normal','high','urgent','pending','in_progress','done','cancelled','postponed','overdue','manual','checklist','kpi','okr','tmo','customer_followup','complaint_followup','referral_followup'] as $needle) {
    $assert(str_contains($planner, "'" . $needle . "'"), 'Planner defaults seed missing token: ' . $needle);
}
$assert(str_contains($planner, 'hr_module_settings'), 'Planner defaults seed must store settings in hr_module_settings.');

$okr = $read('database/seeds/seed_key_okr_tmo_samples.php');
foreach (['objective','key_results','action','tmo_review','tmo_person_model','tmo_user_id','Template only; no user or TMO is assigned automatically.'] as $needle) {
    $assert(str_contains($okr, $needle), 'OKR/TMO seed missing token: ' . $needle);
}
$assert(!preg_match('/objective_type[^\\n]+tmo/i', $okr), 'OKR/TMO seed must not model TMO as objective type.');

$permissions = $read('database/seeds/seed_key_hr_permissions.php');
foreach (['hr_platform_access','hr_platform_manage','business_standards_view','business_standards_manage','hr_tests_manage','hr_tests_assign','hr_tests_take','hr_tests_view_results','hr_tests_reports','hr_tests_retake_approve','hr_duties_manage','hr_checklists_manage','hr_checklists_submit','hr_checklists_approve_manager','hr_checklists_approve_inspector','hr_checklists_report','hr_kpi_manage','hr_kpi_assign','hr_kpi_entry','hr_kpi_report','planner_access','planner_manage_own','planner_assign','planner_view_team','planner_report','okr_access','okr_manage','okr_review','tmo_access','tmo_manage','tmo_report'] as $permission) {
    $assert(str_contains($permissions, "'" . $permission . "'"), 'Permission seed missing key: ' . $permission);
}
$assert(str_contains($permissions, 'hr_module_settings'), 'Permission seed must not directly overwrite admins/users.');

$navigation = $read('database/seeds/seed_key_hr_navigation.php');
foreach (['hr_performance_goals','hr_tests_bank','hr_role_duties','hr_kpi_definitions','hr_planner_mine','hr_okr_objectives'] as $needle) {
    $assert(str_contains($navigation . $fa . $en, $needle), 'Navigation seed or translations missing token: ' . $needle);
}
foreach (['ارزیابی، عملکرد و اهداف','آزمون‌های سازمانی','شرح وظایف و چک‌لیست','KPI ارزیابی','پلنر کاری','اهداف، OKR و TMO'] as $label) {
    $assert(str_contains($fa, $label), 'Farsi navigation label missing: ' . $label);
}
$assert(!preg_match('/Business Standards|استانداردهای کسب‌وکار.*admin_navigation_items/su', $navigation), 'Navigation seed must not create a visible Business Standards menu.');
foreach (['evaluation_builder','employee_evaluations','employee_performance','employee_assessments','hr_test_report'] as $oldItem) {
    $assert(str_contains($navigation, "'" . $oldItem . "'"), 'Navigation seed must hide old duplicate menu item: ' . $oldItem);
}

$suite = $read('database/seeds/seed_hr_performance_suite.php');
foreach (['seedKeyRoles','seedKeyRestaurantDutiesChecklists','seedKeyKpis','seedKeyBusinessCoachingStandards','Performance suite aggregates existing HR seeds'] as $needle) {
    $assert(str_contains($suite, $needle), 'Performance suite seed missing token: ' . $needle);
}

$forbiddenTables = ['users','admins','orders','menu_items','customers','analytics','matches','predictions','banners','media'];
foreach ($requiredSeeds as $seedKey => $file) {
    $content = $read($file);
    preg_match_all('/\b(?:INSERT\s+INTO|UPDATE|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|ALTER\s+TABLE)\s+`?([A-Za-z0-9_]+)`?/i', $content, $matches);
    foreach ($matches[1] ?? [] as $table) {
        $table = strtolower($table);
        $assert(!in_array($table, $forbiddenTables, true), 'Seed writes to forbidden table ' . $table . ': ' . $file);
    }
}

foreach (['run_pending_seeds','force_rerun_seed','register_default_seeds','run_all_hr_seeds','reset_hr_test_seed_only','reset_all_hr_domain_seed_only','requireValidCsrf()',"adminGuard('super_admin')",'setup_run_logs','seed_registry'] as $needle) {
    $assert(str_contains($systemUpdate, $needle), 'System Update seed UI missing token: ' . $needle);
}
$assert(str_contains($systemUpdate, 'هنوز غیرفعال است'), 'Reset-all HR domain seed action must remain disabled.');
$assert(str_contains($runner, "['completed', 'skipped']"), 'Pending seed runner must skip quarantined legacy seed rows.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "all_module_seeds_contract_test: OK\n";
