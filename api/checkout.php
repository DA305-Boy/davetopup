<?php
/**
 * Dave TopUp - Secure Checkout Backend
 * Handles payment processing, validation, and database operations
 * PCI-DSS Compliant - No card data stored on server
 */

// ===== Security Headers =====
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// ===== Environment Setup =====
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/payments.php';
require_once __DIR__ . '/utils/security.php';
require_once __DIR__ . '/utils/logger.php';

// ===== Configuration =====
define('DEBUG_MODE', false);
define('ORDER_TIMEOUT', 3600); // 1 hour

// ===== CORS & Request Validation =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: https://www.davetopup.com');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    http_response_code(200);
    exit;
}

// Enforce HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    sendErrorResponse('HTTPS required', 403);
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Only POST requests allowed', 405);
}

// Get request body
$inputData = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('Invalid JSON input', 400);
}

// ===== Global Response Functions =====
function sendSuccessResponse($data = [], $orderId = null) {
    $response = array_merge(['success' => true], $data);
    if ($orderId) {
        $response['orderId'] = $orderId;
    }
    echo json_encode($response);
    exit;
}

function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'code' => $code,
    ]);
    exit;
}

// ===== Main Checkout Handler =====
try {
    // Validate input data
    $validationResult = validateCheckoutData($inputData);
    if (!$validationResult['valid']) {
        sendErrorResponse($validationResult['errors'][0], 400);
    }

    $sanitizedData = $validationResult['data'];

    // Generate order ID
    $orderId = generateSecureOrderId();

    // Store pending order in database
    $orderResult = storePendingOrder($orderId, $sanitizedData);
    if (!$orderResult) {
        Logger::error("Failed to store order: $orderId");
        sendErrorResponse('Failed to create order. Please try again.', 500);
    }

    Logger::info("Order created: $orderId | Amount: {$sanitizedData['amount']} | Method: {$sanitizedData['paymentMethod']}");

    // Route to payment handler
    $paymentMethod = $sanitizedData['paymentMethod'];
    $response = null;

    switch ($paymentMethod) {
        case 'stripe':
            $response = handleStripePayment($orderId, $sanitizedData);
            break;

        case 'stripe-apple':
            $response = handleApplePayment($orderId, $sanitizedData);
            break;

        case 'google-pay':
            $response = handleGooglePayPayment($orderId, $sanitizedData);
            break;

        case 'paypal':
            $response = handlePayPalPayment($orderId, $sanitizedData);
            break;

        case 'binance':
            $response = handleBinancePayment($orderId, $sanitizedData);
            break;

        case 'coinbase':
            $response = handleCoinbasePayment($orderId, $sanitizedData);
            break;

        case 'crypto':
            $response = handleCryptoPayment($orderId, $sanitizedData);
            break;

        case 'skrill':
        case 'flutterwave':
            $response = handleThirdPartyPayment($orderId, $sanitizedData, $paymentMethod);
            break;

        default:
            sendErrorResponse('Unsupported payment method', 400);
    }

    if ($response['success']) {
        Logger::info("Payment initiated successfully: $orderId");
        sendSuccessResponse($response, $orderId);
    } else {
        Logger::warning("Payment initiation failed: $orderId - {$response['message']}");
        sendErrorResponse($response['message'], $response['code'] ?? 400);
    }
} catch (Exception $e) {
    Logger::error("Checkout exception: " . $e->getMessage());
    sendErrorResponse('An unexpected error occurred. Please contact support.', 500);
}

// ===== Validation Functions =====
function validateCheckoutData($data) {
    $errors = [];
    $sanitized = [];

    // Email validation
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    } else {
        $sanitized['email'] = sanitizeEmail($data['email']);
    }

    // Player ID validation
    if (empty($data['playerId']) || strlen($data['playerId']) < 3 || strlen($data['playerId']) > 50) {
        $errors[] = 'Invalid Player ID (3-50 characters)';
    } else {
        $sanitized['playerId'] = sanitizeInput($data['playerId']);
    }

    // Country validation
    if (empty($data['country']) || strlen($data['country']) !== 2) {
        $errors[] = 'Invalid country code';
    } else {
        $sanitized['country'] = sanitizeInput($data['country']);
    }

    // Amount validation
    if (empty($data['amount']) || !is_numeric($data['amount']) || $data['amount'] < 0.50 || $data['amount'] > 10000) {
        $errors[] = 'Invalid amount (0.50 - 10000 USD)';
    } else {
        $sanitized['amount'] = (float) $data['amount'];
    }

    // Payment method validation
    $validMethods = ['stripe', 'stripe-apple', 'google-pay', 'paypal', 'binance', 'coinbase', 'crypto', 'skrill', 'flutterwave'];
    if (empty($data['paymentMethod']) || !in_array($data['paymentMethod'], $validMethods)) {
        $errors[] = 'Invalid payment method';
    } else {
        $sanitized['paymentMethod'] = $data['paymentMethod'];
    }

    // Currency validation
    if (empty($data['currency'])) {
        $data['currency'] = 'USD';
    }
    $sanitized['currency'] = sanitizeInput($data['currency']);

    // Cart data
    if (empty($data['cartData'])) {
        $errors[] = 'Cart data missing';
    } else {
        $sanitized['cartData'] = $data['cartData']; // Validate cart items separately
    }

    // Additional payment data (optional)
    if (!empty($data['stripePaymentMethodId'])) {
        $sanitized['stripePaymentMethodId'] = sanitizeInput($data['stripePaymentMethodId']);
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $sanitized,
    ];
}

// ===== Payment Handler Functions =====
function handleStripePayment($orderId, $data) {
    try {
        $stripeKey = STRIPE_SECRET_KEY;
        $paymentMethodId = $data['stripePaymentMethodId'] ?? null;

        if (!$paymentMethodId) {
            return ['success' => false, 'message' => 'Payment method required'];
        }

        // Initialize Stripe API
        require_once STRIPE_SDK_PATH . '/init.php';
        \Stripe\Stripe::setApiKey($stripeKey);

        // Create Payment Intent
        $intent = \Stripe\PaymentIntent::create([
            'amount' => (int) ($data['amount'] * 100), // Convert to cents
            'currency' => strtolower($data['currency']),
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'off_session' => false,
            'metadata' => [
                'orderId' => $orderId,
                'email' => $data['email'],
                'playerId' => $data['playerId'],
            ],
            'receipt_email' => $data['email'],
        ]);

        // Handle different intent statuses
        if ($intent->status === 'succeeded') {
            updateOrderStatus($orderId, 'completed', $intent->id);
            sendConfirmationEmail($data['email'], $orderId, $data['amount']);
            return ['success' => true, 'message' => 'Payment successful'];
        } elseif ($intent->status === 'requires_action') {
            updateOrderStatus($orderId, 'requires_authentication', $intent->id);
            return ['success' => false, 'message' => '3D Secure authentication required', 'clientSecret' => $intent->client_secret];
        } else {
            updateOrderStatus($orderId, 'failed', $intent->id);
            return ['success' => false, 'message' => 'Payment declined', 'code' => 402];
        }
    } catch (\Stripe\Exception\CardException $e) {
        Logger::error("Stripe card error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => 'Card declined: ' . $e->getError()->message];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        Logger::error("Stripe API error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => 'Payment processing error'];
    }
}

function handlePayPalPayment($orderId, $data) {
    try {
        $paypalClientId = PAYPAL_CLIENT_ID;
        $paypalSecret = PAYPAL_SECRET;

        // Get access token
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => PAYPAL_API_URL . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "$paypalClientId:$paypalSecret",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $tokenData = json_decode($response, true);
        if (!$tokenData['access_token']) {
            return ['success' => false, 'message' => 'PayPal authentication failed'];
        }

        $accessToken = $tokenData['access_token'];

        // Create PayPal order
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => PAYPAL_API_URL . '/v2/checkout/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $orderId,
                        'amount' => [
                            'currency_code' => $data['currency'],
                            'value' => sprintf('%.2f', $data['amount']),
                        ],
                        'description' => 'Game Top-Up - Order ' . $orderId,
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'return_url' => 'https://www.davetopup.com/public/success.html?orderId=' . $orderId,
                            'cancel_url' => 'https://www.davetopup.com/public/checkout.html?status=cancelled',
                        ],
                    ],
                ],
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $orderData = json_decode($response, true);
        if (!$orderData['id']) {
            return ['success' => false, 'message' => 'Failed to create PayPal order'];
        }

        // Find approval link
        $approvalUrl = null;
        foreach ($orderData['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        updateOrderStatus($orderId, 'pending', $orderData['id']);

        return [
            'success' => true,
            'redirectUrl' => $approvalUrl,
            'paypalOrderId' => $orderData['id'],
        ];
    } catch (Exception $e) {
        Logger::error("PayPal error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => 'PayPal payment failed'];
    }
}

function handleBinancePayment($orderId, $data) {
    try {
        $binanceApiKey = BINANCE_API_KEY;
        $binanceSecret = BINANCE_SECRET;

        $timestamp = round(microtime(true) * 1000);
        $params = [
            'merchantId' => BINANCE_MERCHANT_ID,
            'merchantTradeNo' => $orderId,
            'totalFee' => sprintf('%.2f', $data['amount']),
            'currency' => $data['currency'],
            'goods' => json_encode([
                ['goodsType' => '01', 'goodsCategory' => 'Game TopUp', 'referenceGoodsId' => $orderId],
            ]),
            'buyer' => json_encode(['buyerEmail' => $data['email']]),
            'returnUrl' => 'https://www.davetopup.com/public/success.html?orderId=' . $orderId,
            'cancelUrl' => 'https://www.davetopup.com/public/checkout.html?status=cancelled',
            'webhookUrl' => 'https://www.davetopup.com/api/webhooks/binance.php',
            'requestTime' => $timestamp,
        ];

        // Generate signature
        $paramString = http_build_query($params);
        $signature = strtoupper(hash_hmac('sha256', $paramString, $binanceSecret));
        $params['signature'] = $signature;

        updateOrderStatus($orderId, 'pending', 'binance_' . $orderId);

        return [
            'success' => true,
            'redirectUrl' => BINANCE_CHECKOUT_URL . '?' . http_build_query($params),
        ];
    } catch (Exception $e) {
        Logger::error("Binance error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => 'Binance payment failed'];
    }
}

function handleApplePayPayment($orderId, $data) {
    // Apple Pay uses Stripe backend
    return handleStripePayment($orderId, $data);
}

function handleGooglePayPayment($orderId, $data) {
    // Google Pay uses Stripe backend
    return handleStripePayment($orderId, $data);
}

function handleCoinbasePayment($orderId, $data) {
    try {
        $coinbaseApiKey = COINBASE_API_KEY;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.commerce.coinbase.com/charges',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'X-CC-Api-Key: ' . $coinbaseApiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'name' => 'Game Top-Up - Order ' . $orderId,
                'description' => 'Instant game top-up delivery',
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => sprintf('%.2f', $data['amount']),
                    'currency' => $data['currency'],
                ],
                'metadata' => [
                    'orderId' => $orderId,
                    'email' => $data['email'],
                ],
                'redirect_url' => 'https://www.davetopup.com/public/success.html?orderId=' . $orderId,
                'cancel_url' => 'https://www.davetopup.com/public/checkout.html?status=cancelled',
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $chargeData = json_decode($response, true);
        if (!$chargeData['data']['id']) {
            return ['success' => false, 'message' => 'Failed to create Coinbase charge'];
        }

        updateOrderStatus($orderId, 'pending', $chargeData['data']['id']);

        return [
            'success' => true,
            'redirectUrl' => $chargeData['data']['hosted_url'],
        ];
    } catch (Exception $e) {
        Logger::error("Coinbase error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => 'Coinbase payment failed'];
    }
}

function handleCryptoPayment($orderId, $data) {
    try {
        // Generate unique wallet address for this order
        // This is simplified - use a proper crypto payment processor
        $walletAddress = generateCryptoWallet($orderId);

        updateOrderStatus($orderId, 'pending_crypto', $walletAddress);

        return [
            'success' => true,
            'walletAddress' => $walletAddress,
            'amount' => $data['amount'],
            'currency' => 'USDT', // or other crypto
        ];
    } catch (Exception $e) {
        Logger::error("Crypto error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => 'Crypto payment setup failed'];
    }
}

function handleThirdPartyPayment($orderId, $data, $method) {
    // Generic handler for Skrill, Flutterwave, etc.
    try {
        updateOrderStatus($orderId, 'pending');

        return [
            'success' => true,
            'message' => ucfirst($method) . ' payment initiated',
            'redirectUrl' => 'https://payment-gateway.example.com/pay?order=' . $orderId,
        ];
    } catch (Exception $e) {
        Logger::error("$method error: {$e->getMessage()}");
        updateOrderStatus($orderId, 'failed');
        return ['success' => false, 'message' => "$method payment failed"];
    }
}

// ===== Database Functions =====
function storePendingOrder($orderId, $data) {
    global $db;

    $stmt = $db->prepare(
        "INSERT INTO transactions 
        (order_id, email, player_id, country, payment_method, amount, currency, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        Logger::error("Prepare error: " . $db->error);
        return false;
    }

    $status = 'pending';
    $result = $stmt->bind_param(
        'ssssssds',
        $orderId,
        $data['email'],
        $data['playerId'],
        $data['country'],
        $data['paymentMethod'],
        $data['amount'],
        $data['currency'],
        $status
    );

    if (!$result) {
        Logger::error("Bind error: " . $stmt->error);
        return false;
    }

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function updateOrderStatus($orderId, $status, $transactionId = null) {
    global $db;

    $query = "UPDATE transactions SET status = ?, updated_at = NOW()";
    $types = 's';
    $params = [$status];

    if ($transactionId) {
        $query .= ", transaction_id = ?";
        $types .= 's';
        $params[] = $transactionId;
    }

    $query .= " WHERE order_id = ?";
    $types .= 's';
    $params[] = $orderId;

    $stmt = $db->prepare($query);
    if (!$stmt) {
        Logger::error("Prepare error: " . $db->error);
        return false;
    }

    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function sendConfirmationEmail($email, $orderId, $amount) {
    $subject = "Payment Confirmation - Order $orderId";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Payment Successful!</h2>
        <p>Thank you for your purchase!</p>
        <p><strong>Order ID:</strong> $orderId</p>
        <p><strong>Amount:</strong> \$$amount USD</p>
        <p>Your game top-up will be delivered within minutes.</p>
        <p style='margin-top: 20px; font-size: 12px; color: #666;'>
            Questions? Contact support@davetopup.com
        </p>
    </body>
    </html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@davetopup.com\r\n";

    return mail($email, $subject, $message, $headers);
}

// ===== Utility Functions =====
function generateSecureOrderId() {
    return 'ORD-' . bin2hex(random_bytes(8)) . '-' . time();
}

function generateCryptoWallet($orderId) {
    // Placeholder: Use a real crypto processor API
    return '0x' . hash('sha256', $orderId . CRYPTO_SECRET_KEY);
}
?>
