<?php
/**
 * Menu API Endpoint
 */

require_once __DIR__ . '/../../core/models/MenuItem.php';

$menuModel = new MenuItem();

if ($method === 'GET') {
    $categoryId = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $featured = $_GET['featured'] ?? null;
    
    if ($search) {
        $items = $menuModel->search($search);
    } elseif ($featured) {
        $items = $menuModel->getFeatured();
    } elseif ($categoryId) {
        $items = $menuModel->getByCategory($categoryId);
    } else {
        $items = $menuModel->getAllWithCategories(['is_available' => 1]);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $items
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
