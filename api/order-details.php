<?php
/**
 * Order Details API Endpoint
 * Retrieves order information by order ID
 */

header('Content-Type: application/json; charset=UTF-8');

// Allow CORS for localhost and production
$allowedOrigins = ['https://www.davetopup.com', 'http://localhost:3000', 'http://localhost:5173', 'http://127.0.0.1', 'http://localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || preg_match('/localhost/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/security.php';
require_once __DIR__ . '/utils/logger.php';

// Allow HTTP in development
$isDevelopment = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']);
$isProduction = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if ($isProduction && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'HTTPS required']);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit;
}

// Get order ID
$orderId = isset($_GET['orderId']) ? sanitizeInput($_GET['orderId']) : null;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    global $db;

    // Try fetching from orders table first (product orders from index2.html)
    $stmt = $db->prepare(
        "SELECT order_id, email, customer_name, product_id, product_name, payment_method, amount, currency, status, created_at 
         FROM orders 
         WHERE order_id = ? LIMIT 1"
    );

    if (!$stmt) {
        // Fallback to transactions table (player topup orders)
        $stmt = $db->prepare(
            "SELECT order_id, email, player_id as product_id, NULL as product_name, payment_method, amount, currency, status, created_at 
             FROM transactions 
             WHERE order_id = ? LIMIT 1"
        );

        if (!$stmt) {
            throw new Exception("Prepare error: " . $db->error);
        }
    }

    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = $result->fetch_assoc();
    $stmt->close();

    Logger::info("Order details retrieved: $orderId");

    // Return order details
    echo json_encode([
        'success' => true,
        'data' => [
            'orderId' => $order['order_id'],
            'email' => $order['email'],
            'customerName' => $order['customer_name'] ?? 'Guest',
            'productId' => $order['product_id'],
            'productName' => $order['product_name'],
            'paymentMethod' => $order['payment_method'],
            'amount' => $order['amount'],
            'total' => $order['amount'],
            'currency' => $order['currency'],
            'status' => $order['status'],
            'createdAt' => $order['created_at'],
        ]
    ]);

} catch (Exception $e) {
    Logger::error("Order details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
