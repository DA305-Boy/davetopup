<?php
/**
 * Webhook Handlers for Payment Gateway Integration
 * Stripe, PayPal, Binance Pay, Coinbase, Skrill, Flutterwave
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/security.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get the raw payload
$payload = file_get_contents('php://input');
$inputData = json_decode($payload, true);

// Determine which webhook processor to use
$pathParts = explode('/', $_SERVER['REQUEST_URI']);
$webhookType = end($pathParts);

try {
    switch ($webhookType) {
        case 'stripe':
            handleStripeWebhook($payload);
            break;
        case 'paypal':
            handlePayPalWebhook($inputData);
            break;
        case 'binance':
            handleBinanceWebhook($inputData);
            break;
        case 'coinbase':
            handleCoinbaseWebhook($inputData);
            break;
        case 'skrill':
            handleSkrillWebhook($inputData);
            break;
        case 'flutterwave':
            handleFlutterwaveWebhook($inputData);
            break;
        case 'cashapp':
            handleCashAppWebhook($inputData);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Webhook not found']);
            exit;
    }

} catch (Exception $e) {
    Logger::error("Webhook error: {$e->getMessage()}");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Processing error']);
    exit;
}

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Webhook processed']);

// ===== Stripe Webhook Handler =====
function handleStripeWebhook() {
    $payload = file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try {
        require_once STRIPE_SDK_PATH . '/init.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            STRIPE_WEBHOOK_SECRET
        );

        Logger::info("Stripe webhook received: {$event->type}");

        switch ($event->type) {
            case 'payment_intent.succeeded':
                handleStripePaymentSuccess($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                handleStripePaymentFailed($event->data->object);
                break;

            case 'charge.refunded':
                handleStripeRefund($event->data->object);
                break;

            default:
                Logger::warning("Unhandled Stripe event: {$event->type}");
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (\UnexpectedValueException $e) {
        Logger::error("Stripe webhook error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        Logger::error("Stripe signature verification failed: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
    }
}

function handleStripePaymentSuccess($paymentIntent) {
    global $db;

    $orderId = $paymentIntent->metadata->orderId;
    $transactionId = $paymentIntent->id;

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $transactionId, $orderId);
    $stmt->execute();
    $stmt->close();

    // Get order details and send confirmation
    $result = $db->query("SELECT email, amount FROM transactions WHERE order_id = '$orderId'");
    if ($row = $result->fetch_assoc()) {
        sendConfirmationEmail($row['email'], $orderId, $row['amount']);
        Logger::info("Payment confirmed for order: $orderId");
    }
}

function handleStripePaymentFailed($paymentIntent) {
    global $db;

    $orderId = $paymentIntent->metadata->orderId;
    $errorMessage = $paymentIntent->last_payment_error->message ?? 'Unknown error';

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    Logger::warning("Payment failed for order: $orderId - $errorMessage");
}

function handleStripeRefund($charge) {
    global $db;

    $metadata = (array) $charge->metadata;
    if (empty($metadata['orderId'])) return;

    $orderId = $metadata['orderId'];
    $refundAmount = $charge->amount_refunded / 100;

    $stmt = $db->prepare(
        "INSERT INTO refunds (order_id, refund_amount, status) VALUES (?, ?, 'completed')"
    );
    $stmt->bind_param('sd', $orderId, $refundAmount);
    $stmt->execute();
    $stmt->close();

    Logger::info("Refund processed for order: $orderId - Amount: \$$refundAmount");
}

// ===== PayPal Webhook Handler =====
function handlePayPalWebhook() {
    $payload = file_get_contents('php://input');
    $webhookData = json_decode($payload, true);

    $clientId = PAYPAL_CLIENT_ID;
    $secret = PAYPAL_SECRET;

    // Verify webhook signature
    $webhookId = PAYPAL_WEBHOOK_ID;
    $transmissionId = $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '';
    $transmissionTime = $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '';
    $certUrl = $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '';
    $authAlgo = $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '';
    $transmissionSig = $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '';

    try {
        // Verify signature
        $expectedSig = base64_encode(hash_hmac(
            'sha256',
            $transmissionId . '|' . $transmissionTime . '|' . $webhookId . '|' . hash('sha256', $payload),
            $secret,
            true
        ));

        if (!hash_equals($expectedSig, $transmissionSig)) {
            throw new Exception('Invalid signature');
        }

        Logger::info("PayPal webhook received: {$webhookData['event_type']}");

        switch ($webhookData['event_type']) {
            case 'CHECKOUT.ORDER.COMPLETED':
                handlePayPalOrderCompleted($webhookData['resource']);
                break;

            case 'PAYMENT.CAPTURE.COMPLETED':
                handlePayPalPaymentCompleted($webhookData['resource']);
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
                handlePayPalRefund($webhookData['resource']);
                break;

            default:
                Logger::warning("Unhandled PayPal event: {$webhookData['event_type']}");
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        Logger::error("PayPal webhook error: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handlePayPalOrderCompleted($resource) {
    global $db;

    $orderId = $resource['purchase_units'][0]['reference_id'];
    $paypalOrderId = $resource['id'];

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $paypalOrderId, $orderId);
    $stmt->execute();
    $stmt->close();

    $result = $db->query("SELECT email, amount FROM transactions WHERE order_id = '$orderId'");
    if ($row = $result->fetch_assoc()) {
        sendConfirmationEmail($row['email'], $orderId, $row['amount']);
        Logger::info("PayPal payment completed for order: $orderId");
    }
}

function handlePayPalPaymentCompleted($resource) {
    handlePayPalOrderCompleted(['id' => $resource['id'], 'purchase_units' => [['reference_id' => $resource['supplementary_data']['related_ids']['order_id']]]]);
}

function handlePayPalRefund($resource) {
    global $db;

    $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'];
    $refundAmount = (float) $resource['amount']['value'];

    $result = $db->query("SELECT order_id FROM transactions WHERE transaction_id = '$paypalOrderId'");
    if ($row = $result->fetch_assoc()) {
        $orderId = $row['order_id'];
        $stmt = $db->prepare(
            "INSERT INTO refunds (order_id, refund_amount, status) VALUES (?, ?, 'completed')"
        );
        $stmt->bind_param('sd', $orderId, $refundAmount);
        $stmt->execute();
        $stmt->close();

        Logger::info("PayPal refund processed for order: $orderId");
    }
}

// ===== Binance Pay Webhook Handler =====
function handleBinanceWebhook() {
    $payload = file_get_contents('php://input');
    $webhookData = json_decode($payload, true);

    $binanceSecret = BINANCE_SECRET;

    try {
        // Verify Binance signature
        $nonce = $_SERVER['HTTP_BINANCEPAY_NONCE'] ?? '';
        $timestamp = $_SERVER['HTTP_BINANCEPAY_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_BINANCEPAY_SIGNATURE'] ?? '';

        $payload_str = $payload . $nonce . $timestamp;
        $expectedSig = strtoupper(hash_hmac('sha256', $payload_str, $binanceSecret));

        if ($expectedSig !== $signature) {
            throw new Exception('Invalid Binance signature');
        }

        Logger::info("Binance webhook received: {$webhookData['bizType']}");

        if ($webhookData['bizType'] === 'PAY') {
            $data = $webhookData['data'];
            if ($data['status'] === 'SUCCESS') {
                handleBinancePaymentSuccess($data);
            } elseif ($data['status'] === 'CANCELED' || $data['status'] === 'EXPIRED') {
                handleBinancePaymentFailed($data);
            }
        }

        http_response_code(200);
        echo json_encode(['code' => '000000']);
    } catch (Exception $e) {
        Logger::error("Binance webhook error: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['code' => '000001', 'message' => $e->getMessage()]);
    }
}

function handleBinancePaymentSuccess($data) {
    global $db;

    $orderId = $data['merchantTradeNo'];
    $binanceOrderId = $data['prepayId'];

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $binanceOrderId, $orderId);
    $stmt->execute();
    $stmt->close();

    $result = $db->query("SELECT email, amount FROM transactions WHERE order_id = '$orderId'");
    if ($row = $result->fetch_assoc()) {
        sendConfirmationEmail($row['email'], $orderId, $row['amount']);
        Logger::info("Binance payment completed for order: $orderId");
    }
}

function handleBinancePaymentFailed($data) {
    global $db;

    $orderId = $data['merchantTradeNo'];

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    Logger::warning("Binance payment failed for order: $orderId");
}

// ===== Utility Functions =====
function sendConfirmationEmail($email, $orderId, $amount) {
    $subject = "Payment Confirmed - Order $orderId";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
        <div style='background: white; padding: 30px; border-radius: 10px; max-width: 600px;'>
            <h2 style='color: #007bff;'>Payment Successful!</h2>
            <p>Thank you for your purchase at Dave TopUp!</p>
            
            <div style='background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px;'>
                <p><strong>Order ID:</strong> $orderId</p>
                <p><strong>Amount:</strong> \$$amount USD</p>
                <p><strong>Status:</strong> Confirmed</p>
            </div>
            
            <p>Your game top-up will be delivered to your account within <strong>5-30 minutes</strong>.</p>
            
            <p style='color: #666; font-size: 14px; margin-top: 30px;'>
                Questions? Contact us at support@davetopup.com
            </p>
        </div>
    </body>
    </html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@davetopup.com\r\n";
    $headers .= "Reply-To: support@davetopup.com\r\n";

    return mail($email, $subject, $message, $headers);
}

// ===== Coinbase Webhook Handler =====
function handleCoinbaseWebhook($webhookData) {
    global $db;

    $webhookSecret = getenv('COINBASE_WEBHOOK_SECRET');
    $signature = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ?? '';

    try {
        // Verify signature
        $payload = file_get_contents('php://input');
        $expectedSig = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

        if (!hash_equals($expectedSig, $signature)) {
            throw new Exception('Invalid Coinbase signature');
        }

        Logger::info("Coinbase webhook received: {$webhookData['type']}");

        if ($webhookData['type'] === 'charge:confirmed' || $webhookData['type'] === 'charge:received') {
            handleCoinbasePaymentSuccess($webhookData['data']);
        } elseif ($webhookData['type'] === 'charge:failed' || $webhookData['type'] === 'charge:unresolved') {
            handleCoinbasePaymentFailed($webhookData['data']);
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        Logger::error("Coinbase webhook error: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleCoinbasePaymentSuccess($charge) {
    global $db;

    $orderId = $charge['metadata']['orderId'] ?? null;
    if (!$orderId) return;

    $coinbaseChargeId = $charge['id'];

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $coinbaseChargeId, $orderId);
    $stmt->execute();
    $stmt->close();

    $result = $db->query("SELECT email, amount FROM transactions WHERE order_id = '$orderId'");
    if ($row = $result->fetch_assoc()) {
        sendConfirmationEmail($row['email'], $orderId, $row['amount']);
        Logger::info("Coinbase payment completed for order: $orderId");
    }
}

function handleCoinbasePaymentFailed($charge) {
    global $db;

    $orderId = $charge['metadata']['orderId'] ?? null;
    if (!$orderId) return;

    $failureReason = $charge['failure_reason'] ?? 'Unknown error';

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    Logger::warning("Coinbase payment failed for order: $orderId - $failureReason");
}

// ===== Skrill Webhook Handler =====
function handleSkrillWebhook($webhookData) {
    global $db;

    try {
        // Verify Skrill signature
        $secret = getenv('SKRILL_SECRET_KEY');
        $receivedSignature = $_SERVER['HTTP_X_SKRILL_SIGNATURE'] ?? '';

        $payload = file_get_contents('php://input');
        $expectedSig = hash_hmac('md5', $payload, $secret);

        if ($expectedSig !== $receivedSignature) {
            throw new Exception('Invalid Skrill signature');
        }

        Logger::info("Skrill webhook received");

        // Skrill sends status via GET/POST with transaction_id
        $transactionId = $webhookData['transaction_id'] ?? null;
        $status = $webhookData['status'] ?? null;

        if (!$transactionId) return;

        // Query order by transaction_id
        $result = $db->query("SELECT order_id FROM transactions WHERE transaction_id = '$transactionId'");
        if ($row = $result->fetch_assoc()) {
            $orderId = $row['order_id'];

            // Skrill status: 2 = SUCCESS, -1 = FAILED, 0 = PENDING
            if ($status == '2') {
                handleSkrillPaymentSuccess($orderId, $transactionId);
            } else {
                handleSkrillPaymentFailed($orderId, $status);
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        Logger::error("Skrill webhook error: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleSkrillPaymentSuccess($orderId, $transactionId) {
    global $db;

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $transactionId, $orderId);
    $stmt->execute();
    $stmt->close();

    $result = $db->query("SELECT email, amount FROM transactions WHERE order_id = '$orderId'");
    if ($row = $result->fetch_assoc()) {
        sendConfirmationEmail($row['email'], $orderId, $row['amount']);
        Logger::info("Skrill payment completed for order: $orderId");
    }
}

function handleSkrillPaymentFailed($orderId, $status) {
    global $db;

    $statusMap = [
        '-1' => 'Failed',
        '0' => 'Pending',
        '-3' => 'Cancelled'
    ];

    $statusText = $statusMap[$status] ?? 'Unknown status';

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    Logger::warning("Skrill payment failed for order: $orderId - Status: $statusText");
}

// ===== Flutterwave Webhook Handler =====
function handleFlutterwaveWebhook($webhookData) {
    global $db;

    try {
        // Verify Flutterwave signature
        $secret = getenv('FLUTTERWAVE_SECRET_HASH');
        $receivedHash = $_SERVER['HTTP_VERIFF_HASH'] ?? $_SERVER['HTTP_X_FLUTTERWAVE_SIGNATURE'] ?? '';

        $payload = file_get_contents('php://input');
        $expectedHash = hash_hmac('sha256', $payload, $secret);

        if ($expectedHash !== $receivedHash) {
            throw new Exception('Invalid Flutterwave signature');
        }

        Logger::info("Flutterwave webhook received");

        // Webhook event types: charge.completed, charge.failed, etc.
        if ($webhookData['data']['status'] === 'successful') {
            handleFlutterwavePaymentSuccess($webhookData['data']);
        } else {
            handleFlutterwavePaymentFailed($webhookData['data']);
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        Logger::error("Flutterwave webhook error: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleFlutterwavePaymentSuccess($chargeData) {
    global $db;

    // Get order ID from metadata or transaction reference
    $transactionRef = $chargeData['tx_ref'] ?? null;
    if (!$transactionRef) return;

    $flutterwaveId = $chargeData['id'] ?? null;
    $amount = $chargeData['amount'] ?? 0;

    // Find order by transaction reference
    $result = $db->query("SELECT order_id, email FROM transactions WHERE transaction_id = '$transactionRef' OR order_id = '$transactionRef'");
    if ($row = $result->fetch_assoc()) {
        $orderId = $row['order_id'];

        $stmt = $db->prepare(
            "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
        );
        $stmt->bind_param('ss', $flutterwaveId, $orderId);
        $stmt->execute();
        $stmt->close();

        sendConfirmationEmail($row['email'], $orderId, $amount);
        Logger::info("Flutterwave payment completed for order: $orderId");
    }
}

function handleFlutterwavePaymentFailed($chargeData) {
    global $db;

    $transactionRef = $chargeData['tx_ref'] ?? null;
    if (!$transactionRef) return;

    $failureReason = $chargeData['processor_response'] ?? 'Payment failed';

    // Find order by transaction reference
    $result = $db->query("SELECT order_id FROM transactions WHERE transaction_id = '$transactionRef' OR order_id = '$transactionRef'");
    if ($row = $result->fetch_assoc()) {
        $orderId = $row['order_id'];

        $stmt = $db->prepare(
            "UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE order_id = ?"
        );
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $stmt->close();

        Logger::warning("Flutterwave payment failed for order: $orderId - $failureReason");
    }
}

// ===== Cash App Webhook Handler =====
function handleCashAppWebhook($webhookData) {
    global $db;

    try {
        // Verify Cash App signature
        $webhookSecret = getenv('CASHAPP_WEBHOOK_SECRET');
        $receivedSignature = $_SERVER['HTTP_X_SQUARE_HMAC_SHA256'] ?? '';
        
        $payload = file_get_contents('php://input');
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookSecret, true));

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            throw new Exception('Invalid Cash App signature');
        }

        Logger::info("Cash App webhook received: {$webhookData['type']}");

        // Handle different webhook event types
        $eventType = $webhookData['type'] ?? '';
        
        if ($eventType === 'payment.updated') {
            $paymentData = $webhookData['data']['object']['payment'] ?? null;
            
            if ($paymentData) {
                if ($paymentData['status'] === 'COMPLETED') {
                    handleCashAppPaymentSuccess($paymentData);
                } elseif (in_array($paymentData['status'], ['CANCELED', 'FAILED'])) {
                    handleCashAppPaymentFailed($paymentData);
                }
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        Logger::error("Cash App webhook error: " . $e->getMessage());
        http_response_code(403);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleCashAppPaymentSuccess($paymentData) {
    global $db;

    $paymentId = $paymentData['id'] ?? null;
    $referenceId = $paymentData['reference_id'] ?? null;
    
    if (!$paymentId || !$referenceId) return;

    // Extract order ID from reference
    $orderId = explode('-', $referenceId)[0] . '-' . explode('-', $referenceId)[1];

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $paymentId, $orderId);
    $stmt->execute();
    $stmt->close();

    // Also update orders table if exists
    $stmt = $db->prepare(
        "UPDATE orders SET status = 'completed', payment_processor_reference = ? WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $paymentId, $orderId);
    $stmt->execute();
    $stmt->close();

    // Get order details and send confirmation
    $result = $db->query("SELECT email, amount FROM transactions WHERE order_id = '$orderId'");
    if ($row = $result->fetch_assoc()) {
        sendConfirmationEmail($row['email'], $orderId, $row['amount']);
        Logger::info("Cash App payment completed for order: $orderId");
    }
}

function handleCashAppPaymentFailed($paymentData) {
    global $db;

    $paymentId = $paymentData['id'] ?? null;
    $referenceId = $paymentData['reference_id'] ?? null;
    $failureReason = $paymentData['failure_reason'] ?? 'Unknown reason';
    
    if (!$paymentId || !$referenceId) return;

    // Extract order ID from reference
    $orderId = explode('-', $referenceId)[0] . '-' . explode('-', $referenceId)[1];

    $stmt = $db->prepare(
        "UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE order_id = ?"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    // Also update orders table if exists
    $stmt = $db->prepare(
        "UPDATE orders SET status = 'failed' WHERE order_id = ?"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();

    Logger::warning("Cash App payment failed for order: $orderId - $failureReason");
}

?>
