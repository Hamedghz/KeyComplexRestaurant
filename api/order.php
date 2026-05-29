<?php
/**
 * Order API Endpoint
 */

require_once __DIR__ . '/../core/models/Order.php';
require_once __DIR__ . '/../core/models/MenuItem.php';
$method = $method ?? $_SERVER['REQUEST_METHOD'];

$orderModel = new Order();
$menuModel = new MenuItem();

if ($method === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['customer_name', 'customer_phone', 'items'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "فیلد {$field} الزامی است"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Validate items
    if (!is_array($input['items']) || empty($input['items'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'سبد خرید خالی است'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Calculate totals
    $subtotal = 0;
    $orderItems = [];
    
    foreach ($input['items'] as $item) {
        $menuItem = $menuModel->find($item['menu_item_id']);
        
        if (!$menuItem || !$menuItem['is_available']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'یکی از آیتم‌های سفارش در دسترس نیست'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $quantity = (int)$item['quantity'];
        $unitPrice = $menuItem['discount_price'] ?? $menuItem['price'];
        $itemSubtotal = $unitPrice * $quantity;
        
        $orderItems[] = [
            'menu_item_id' => $menuItem['id'],
            'item_name_fa' => $menuItem['name_fa'],
            'item_name_en' => $menuItem['name_en'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $itemSubtotal,
            'notes' => $item['notes'] ?? null
        ];
        
        $subtotal += $itemSubtotal;
    }
    
    // Calculate tax and delivery
    $taxRate = 9; // 9%
    $tax = $subtotal * ($taxRate / 100);
    $deliveryFee = ($input['order_type'] ?? 'takeaway') === 'delivery' ? 15000 : 0;
    $total = $subtotal + $tax + $deliveryFee;
    
    // Create order data
    $orderData = [
        'customer_name' => sanitizeInput($input['customer_name']),
        'customer_phone' => sanitizeInput($input['customer_phone']),
        'customer_email' => sanitizeInput($input['customer_email'] ?? null),
        'delivery_address' => sanitizeInput($input['delivery_address'] ?? null),
        'order_type' => $input['order_type'] ?? 'takeaway',
        'subtotal' => $subtotal,
        'tax' => $tax,
        'delivery_fee' => $deliveryFee,
        'total' => $total,
        'payment_method' => $input['payment_method'] ?? 'cash',
        'notes' => sanitizeInput($input['notes'] ?? null)
    ];
    
    try {
        $orderId = $orderModel->createOrder($orderData, $orderItems);
        
        // Get created order
        $order = $orderModel->getOrderWithItems($orderId);
        
        echo json_encode([
            'success' => true,
            'message' => 'سفارش شما با موفقیت ثبت شد',
            'data' => $order
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'خطا در ثبت سفارش'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
