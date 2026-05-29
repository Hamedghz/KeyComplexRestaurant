<?php
/**
 * API Router
 * KEY Restaurant & Coffeehouse
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/bootstrap.php';

// Get request path
$request = $_GET['request'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
try {
    switch ($request) {
        case 'menu':
            require_once __DIR__ . '/menu.php';
            break;
            
        case 'menu/featured':
            require_once __DIR__ . '/menu-featured.php';
            break;
            
        case 'menu/categories':
            require_once __DIR__ . '/categories.php';
            break;
            
        case 'order':
            require_once __DIR__ . '/order.php';
            break;
            
        case 'settings':
            require_once __DIR__ . '/settings.php';
            break;
            
        case 'feedback':
            require_once __DIR__ . '/feedback.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
