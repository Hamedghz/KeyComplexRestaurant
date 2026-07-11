<?php

require_once __DIR__ . '/seed_key_kpis.php';

function seedKeyRestaurantKpis(PDO $db, int $actorId = 0): array {
    $result = seedKeyKpis($db, $actorId);
    $result['inserted'] = (int)($result['inserted'] ?? 0);
    $result['updated'] = (int)($result['updated'] ?? 0);
    $result['skipped'] = (int)($result['skipped'] ?? 0);
    $result['messages'] = array_merge($result['messages'] ?? [], ['Restaurant KPI wrapper delegates to seed_key_kpis.php without duplicating KPI rows.']);
    return $result;
}
