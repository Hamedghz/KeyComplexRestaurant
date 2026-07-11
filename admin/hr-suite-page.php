<?php
require_once __DIR__ . '/lib/hr_performance_suite.php';
hrPerformanceSuiteRender(basename((string)($_SERVER['SCRIPT_NAME'] ?? __FILE__)));
