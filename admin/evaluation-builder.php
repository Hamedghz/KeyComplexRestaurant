<?php
require_once __DIR__ . '/lib/admin_schema.php';
redirectTo('hr-tests.php');
return;
define('HR_EVALUATION_BUILD_PAGE', true);
require __DIR__ . '/employee-evaluation-settings.php';
