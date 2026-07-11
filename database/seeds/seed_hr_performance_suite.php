<?php
require_once dirname(__DIR__, 2) . '/admin/lib/admin_schema.php';
require_once __DIR__ . '/seed_key_roles.php';
require_once __DIR__ . '/seed_key_duties.php';
require_once __DIR__ . '/seed_key_checklists.php';
require_once __DIR__ . '/seed_key_kpis.php';
require_once __DIR__ . '/seed_key_restaurant_duties_checklists.php';
require_once __DIR__ . '/seed_key_business_coaching_standards.php';

function seedHrPerformanceSuite(?int $actorId = null): array {
    $db = adminDb();
    $rolesResult = seedKeyRoles($db, (int)($actorId ?? 0));
    $dutiesChecklistsResult = seedKeyRestaurantDutiesChecklists($db, (int)($actorId ?? 0));
    $kpisResult = seedKeyKpis($db, (int)($actorId ?? 0));
    $standardsResult = seedKeyBusinessCoachingStandards($db, (int)($actorId ?? 0));

    return [
        'roles' => $rolesResult,
        'duties_checklists' => $dutiesChecklistsResult,
        'kpis' => $kpisResult,
        'standards' => $standardsResult,
        'inserted' => (int)($rolesResult['inserted'] ?? 0) + (int)($dutiesChecklistsResult['inserted'] ?? 0) + (int)($kpisResult['inserted'] ?? 0) + (int)($standardsResult['inserted'] ?? 0),
        'updated' => (int)($rolesResult['updated'] ?? 0) + (int)($dutiesChecklistsResult['updated'] ?? 0) + (int)($kpisResult['updated'] ?? 0) + (int)($standardsResult['updated'] ?? 0),
        'skipped' => (int)($rolesResult['skipped'] ?? 0) + (int)($dutiesChecklistsResult['skipped'] ?? 0) + (int)($kpisResult['skipped'] ?? 0) + (int)($standardsResult['skipped'] ?? 0),
        'messages' => ['Performance suite aggregates existing HR seeds and does not create operational performance scores.'],
    ];
}
