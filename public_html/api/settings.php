<?php
/**
 * Settings API Endpoint
 * Returns public settings for frontend
 */

require_once __DIR__ . '/../../core/models/Setting.php';

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
