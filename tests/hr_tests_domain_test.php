<?php
require_once dirname(__DIR__) . '/database/seeds/seed_restaurant_hr_tests.php';

function assertSameValue($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected=' . var_export($expected, true) . ', actual=' . var_export($actual, true));
    }
}

$definitions = hrRestaurantTestDefinitions();
assertSameValue(14, count($definitions), 'Professional test count');
$codes = array_column($definitions, 0);
assertSameValue(14, count(array_unique($codes)), 'Test codes must be unique');
assertSameValue(347, array_sum(array_column($definitions, 4)), 'Seeded question count');
foreach ($definitions as [$code, $title, $category, $analysisType, $questionCount, $dimensions]) {
    if ($code === '' || $title === '' || !$dimensions || $questionCount < count($dimensions)) {
        throw new RuntimeException('Invalid definition: ' . $code);
    }
    if (!in_array($analysisType, ['positive', 'risk'], true)) {
        throw new RuntimeException('Invalid analysis type: ' . $code);
    }
}
echo "hr_tests_domain_test: OK\n";
