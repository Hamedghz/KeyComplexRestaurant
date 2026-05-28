<?php
/**
 * Survey Submission API
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/models/Survey.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$formId = $input['form_id'] ?? null;
$responseData = $input['response_data'] ?? [];

if (!$formId || empty($responseData)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'داده‌های ناقص'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$surveyModel = new Survey();

// Validate response
$validation = $surveyModel->validateResponse($formId, $responseData);

if (!$validation['valid']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در اعتبارسنجی',
        'errors' => $validation['errors']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Prepare metadata
$metadata = [
    'order_id' => $input['order_id'] ?? null,
    'user_id' => $input['user_id'] ?? null,
    'customer_name' => $input['customer_name'] ?? null,
    'customer_phone' => $input['customer_phone'] ?? null,
    'customer_email' => $input['customer_email'] ?? null
];

// Submit response
$result = $surveyModel->submitResponse($formId, $responseData, $metadata);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'نظرسنجی با موفقیت ثبت شد'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ثبت نظرسنجی'
    ], JSON_UNESCAPED_UNICODE);
}
