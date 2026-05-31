<?php
/**
 * Settings API Endpoint
 * Returns public settings for frontend
 */

require_once __DIR__ . '/../core/models/Setting.php';
$method = $method ?? $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
}

$settingModel = new Setting();

if ($method === 'GET') {
    $settings = $settingModel->getPublicSettings();
    
    echo json_encode([
        'success' => true,
        'data' => $settings
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
