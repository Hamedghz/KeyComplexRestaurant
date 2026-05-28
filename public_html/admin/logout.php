<?php
/**
 * Admin Logout
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: index.php');
exit;
