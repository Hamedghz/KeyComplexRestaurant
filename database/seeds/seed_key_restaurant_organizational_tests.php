<?php

// Canonical setup adapter. The legacy seed_restaurant_hr_tests.php is intentionally excluded.
require_once __DIR__ . '/seed_key_organizational_tests.php';

function seedKeyRestaurantOrganizationalTests(PDO $db, int $actorId = 0): array {
    $result = seedKeyOrganizationalTests($db, $actorId);
    $result['inserted'] = (int)($result['tests'] ?? 0);
    $result['updated'] = (int)($result['updated'] ?? 0);
    $result['skipped'] = (int)($result['skipped'] ?? 0);
    $result['messages'] = array_merge($result['messages'] ?? [], ['Restaurant organizational tests wrapper delegates to the canonical KEY test bank; legacy seed_restaurant_hr_tests.php is excluded.']);
    return $result;
}
