<?php
/**
 * Order Details API Endpoint
 * Retrieves order information by order ID
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://www.davetopup.com');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/security.php';
require_once __DIR__ . '/utils/logger.php';

// Enforce HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
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

    // Fetch order details
    $stmt = $db->prepare(
        "SELECT order_id, email, player_id, payment_method, amount, currency, status, created_at 
         FROM transactions 
         WHERE order_id = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception("Prepare error: " . $db->error);
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
        'orderId' => $order['order_id'],
        'email' => $order['email'],
        'playerId' => $order['player_id'],
        'paymentMethod' => $order['payment_method'],
        'amount' => $order['amount'],
        'total' => $order['amount'], // Total = amount (fees already included)
        'currency' => $order['currency'],
        'status' => $order['status'],
        'createdAt' => $order['created_at'],
    ]);

} catch (Exception $e) {
    Logger::error("Order details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
