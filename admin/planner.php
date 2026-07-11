<?php
require_once __DIR__ . '/lib/hr/planner/planner_page.php';

$mode = (string)($_GET['date_mode'] ?? 'all');
if (!in_array($mode, ['all','today','yesterday','tomorrow','overdue'], true)) {
    $mode = 'all';
}
plannerRenderPage($mode);
