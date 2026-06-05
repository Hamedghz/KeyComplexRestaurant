<?php
/**
 * Centralized JSON API router for KEY Restaurant & Coffeehouse.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/bootstrap.php';

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $route = apiRoute();

    switch ($route) {
        case 'orders':
        case 'order':
            apiOrders($db, $method);
            break;
        case 'predictions':
            apiPredictions($db, $method);
            break;
        case 'surveys':
            apiSurveys($db, $method);
            break;
        case 'customers':
            apiCustomers($db, $method);
            break;
        case 'analytics':
            apiAnalytics($db, $method);
            break;
        case 'menu':
            apiMenu($db, $method);
            break;
        case 'settings':
            apiSettings($db, $method);
            break;
        default:
            apiRespond(['success' => false, 'message' => 'Endpoint not found'], 404);
    }
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    apiRespond(['success' => false, 'message' => 'Internal server error'], 500);
}

function apiRoute(): string {
    $request = trim((string)($_GET['request'] ?? ''), '/');
    if ($request !== '') {
        return $request;
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'), '/');
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = trim($path, '/');
    if ($path === 'index.php') {
        return '';
    }
    $path = preg_replace('#^index\.php/?#', '', $path);
    return trim((string)$path, '/');
}

function apiInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ($_POST ?: []);
}

function apiRespond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiLimit(): int {
    $limit = (int)($_GET['limit'] ?? 50);
    return max(1, min(100, $limit));
}

function apiOrders(PDO $db, string $method): void {
    if ($method === 'GET') {
        $limit = apiLimit();
        $status = trim((string)($_GET['status'] ?? ''));
        if ($status !== '') {
            $stmt = $db->prepare('SELECT * FROM `orders` WHERE `order_status` = :status ORDER BY `created_at` DESC LIMIT :limit');
            $stmt->bindValue(':status', $status);
        } else {
            $stmt = $db->prepare('SELECT * FROM `orders` ORDER BY `created_at` DESC LIMIT :limit');
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        apiRespond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($method !== 'POST') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    require_once __DIR__ . '/../core/models/MenuItem.php';
    require_once __DIR__ . '/../core/models/Order.php';

    $input = apiInput();
    foreach (['customer_name', 'customer_phone', 'items'] as $field) {
        if (empty($input[$field])) {
            apiRespond(['success' => false, 'message' => "فیلد {$field} الزامی است"], 400);
        }
    }
    if (!is_array($input['items'])) {
        apiRespond(['success' => false, 'message' => 'سبد خرید نامعتبر است'], 400);
    }

    $menuModel = new MenuItem();
    $orderModel = new Order();
    $subtotal = 0.0;
    $items = [];
    foreach ($input['items'] as $item) {
        $menuItem = $menuModel->find((int)($item['menu_item_id'] ?? 0));
        if (!$menuItem || empty($menuItem['is_available'])) {
            apiRespond(['success' => false, 'message' => 'یکی از آیتم‌های سفارش در دسترس نیست'], 400);
        }
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $unitPrice = (float)($menuItem['discount_price'] ?: $menuItem['price']);
        $lineTotal = $unitPrice * $quantity;
        $items[] = [
            'menu_item_id' => $menuItem['id'],
            'item_name_fa' => $menuItem['name_fa'],
            'item_name_en' => $menuItem['name_en'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $lineTotal,
            'notes' => sanitizeInput($item['notes'] ?? null),
        ];
        $subtotal += $lineTotal;
    }

    $tax = $subtotal * 0.09;
    $deliveryFee = ($input['order_type'] ?? 'takeaway') === 'delivery' ? 15000 : 0;
    $orderId = $orderModel->createOrder([
        'customer_name' => sanitizeInput($input['customer_name']),
        'customer_phone' => normalizeMobile($input['customer_phone']),
        'customer_email' => sanitizeInput($input['customer_email'] ?? null),
        'delivery_address' => sanitizeInput($input['delivery_address'] ?? null),
        'order_type' => $input['order_type'] ?? 'takeaway',
        'subtotal' => $subtotal,
        'tax' => $tax,
        'delivery_fee' => $deliveryFee,
        'total' => $subtotal + $tax + $deliveryFee,
        'payment_method' => $input['payment_method'] ?? 'cash',
        'notes' => sanitizeInput($input['notes'] ?? null),
    ], $items);

    apiRespond(['success' => true, 'message' => 'سفارش شما با موفقیت ثبت شد', 'data' => $orderModel->getOrderWithItems($orderId)], 201);
}

function apiPredictions(PDO $db, string $method): void {
    if ($method === 'GET') {
        $stmt = $db->prepare('SELECT p.*, m.team_a, m.team_b, m.match_date FROM `predictions` p JOIN `matches` m ON m.id = p.match_id ORDER BY p.created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', apiLimit(), PDO::PARAM_INT);
        $stmt->execute();
        apiRespond(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($method !== 'POST') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    require_once __DIR__ . '/../core/models/Prediction.php';
    $input = apiInput();
    foreach (['customer_name', 'mobile', 'match_id', 'predicted_score_team_a', 'predicted_score_team_b'] as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            apiRespond(['success' => false, 'message' => "فیلد {$field} الزامی است"], 400);
        }
    }
    $model = new Prediction();
    $id = $model->createWithCrmMatch([
        'customer_name' => sanitizeInput($input['customer_name']),
        'mobile' => $input['mobile'],
        'match_id' => (int)$input['match_id'],
        'predicted_score_team_a' => (int)$input['predicted_score_team_a'],
        'predicted_score_team_b' => (int)$input['predicted_score_team_b'],
    ]);
    apiRespond(['success' => true, 'data' => ['id' => $id]], 201);
}

function apiSurveys(PDO $db, string $method): void {
    if ($method === 'GET') {
        $stmt = $db->prepare('SELECT `id`, `form_name`, `form_title_fa`, `form_title_en`, `form_description_fa`, `form_schema` FROM `dynamic_forms` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `id` ASC LIMIT :limit');
        $stmt->bindValue(':limit', apiLimit(), PDO::PARAM_INT);
        $stmt->execute();
        apiRespond(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    if ($method !== 'POST') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $input = apiInput();
    if (empty($input['form_id']) || empty($input['response_data']) || !is_array($input['response_data'])) {
        apiRespond(['success' => false, 'message' => 'اطلاعات نظرسنجی نامعتبر است'], 400);
    }
    $stmt = $db->prepare('INSERT INTO `survey_responses` (`form_id`, `order_id`, `user_id`, `response_data`, `customer_name`, `customer_phone`, `customer_email`, `ip_address`, `user_agent`) VALUES (:form_id, :order_id, :user_id, :response_data, :customer_name, :customer_phone, :customer_email, :ip_address, :user_agent)');
    $stmt->execute([
        'form_id' => (int)$input['form_id'],
        'order_id' => isset($input['order_id']) ? (int)$input['order_id'] : null,
        'user_id' => isset($input['user_id']) ? (int)$input['user_id'] : null,
        'response_data' => json_encode($input['response_data'], JSON_UNESCAPED_UNICODE),
        'customer_name' => sanitizeInput($input['customer_name'] ?? null),
        'customer_phone' => isset($input['customer_phone']) ? normalizeMobile($input['customer_phone']) : null,
        'customer_email' => sanitizeInput($input['customer_email'] ?? null),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    apiRespond(['success' => true, 'data' => ['id' => (int)$db->lastInsertId()]], 201);
}

function apiCustomers(PDO $db, string $method): void {
    if ($method !== 'GET') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $query = trim((string)($_GET['q'] ?? ''));
    $stmt = $db->prepare('SELECT * FROM `crm_customers` WHERE (:q = "" OR `full_name` LIKE :like_q OR `mobile` LIKE :like_q) ORDER BY `updated_at` DESC LIMIT :limit');
    $stmt->bindValue(':q', $query);
    $stmt->bindValue(':like_q', '%' . $query . '%');
    $stmt->bindValue(':limit', apiLimit(), PDO::PARAM_INT);
    $stmt->execute();
    apiRespond(['success' => true, 'data' => $stmt->fetchAll()]);
}

function apiAnalytics(PDO $db, string $method): void {
    if ($method !== 'GET') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $today = date('Y-m-d');
    $stmt = $db->prepare('SELECT COALESCE(SUM(`total_visits`),0) AS total_visits, COALESCE(SUM(`unique_visitors`),0) AS unique_visitors, COALESCE(SUM(`total_page_views`),0) AS total_page_views FROM `traffic_statistics` WHERE `stat_date` >= DATE_SUB(:today, INTERVAL 30 DAY)');
    $stmt->execute(['today' => $today]);
    $summary = $stmt->fetch() ?: [];
    $sourceStmt = $db->prepare('SELECT `source_name`, `source_type`, SUM(`visits_count`) AS visits_count FROM `traffic_sources` WHERE `date` >= DATE_SUB(:today, INTERVAL 30 DAY) GROUP BY `source_name`, `source_type` ORDER BY visits_count DESC LIMIT 20');
    $sourceStmt->execute(['today' => $today]);
    apiRespond(['success' => true, 'data' => ['summary' => $summary, 'sources' => $sourceStmt->fetchAll()]]);
}

function apiMenu(PDO $db, string $method): void {
    if ($method !== 'GET') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $stmt = $db->prepare('SELECT mi.*, mc.name_fa AS category_name_fa, mc.name_en AS category_name_en FROM `menu_items` mi JOIN `menu_categories` mc ON mc.id = mi.category_id WHERE mi.is_available = 1 AND mc.is_active = 1 ORDER BY mc.sort_order ASC, mi.sort_order ASC LIMIT :limit');
    $stmt->bindValue(':limit', apiLimit(), PDO::PARAM_INT);
    $stmt->execute();
    apiRespond(['success' => true, 'data' => $stmt->fetchAll()]);
}

function apiSettings(PDO $db, string $method): void {
    if ($method !== 'GET') {
        apiRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
    $stmt = $db->prepare('SELECT `setting_key`, `setting_value`, `setting_type`, `category` FROM `settings` WHERE `is_public` = 1 ORDER BY `category`, `setting_key`');
    $stmt->execute();
    apiRespond(['success' => true, 'data' => $stmt->fetchAll()]);
}
