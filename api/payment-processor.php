<?php
/**
 * Dave TopUp - Payment Processor Integration
 * Handles all payment method integrations and processing
 * Supports: Stripe, PayPal, Crypto (Bitcoin, Ethereum), Binance, Coinbase, Apple Pay, Google Pay, Skrill, Flutterwave
 */

// Payment Processor Configuration
define('PAYMENT_CONFIG', [
    'stripe' => [
        'name' => 'Stripe',
        'enabled' => !empty(getenv('STRIPE_SECRET_KEY')),
        'test_mode' => getenv('STRIPE_MODE') === 'test',
    ],
    'paypal' => [
        'name' => 'PayPal',
        'enabled' => !empty(getenv('PAYPAL_CLIENT_ID')),
        'mode' => getenv('PAYPAL_MODE') ?? 'sandbox',
    ],
    'binance' => [
        'name' => 'Binance Pay',
        'enabled' => !empty(getenv('BINANCE_API_KEY')),
        'test_mode' => true,
    ],
    'coinbase' => [
        'name' => 'Coinbase Commerce',
        'enabled' => !empty(getenv('COINBASE_API_KEY')),
        'test_mode' => getenv('COINBASE_MODE') === 'test',
    ],
    'crypto' => [
        'name' => 'Crypto Payment',
        'enabled' => true,
        'test_mode' => true,
    ],
    'skrill' => [
        'name' => 'Skrill',
        'enabled' => !empty(getenv('SKRILL_API_KEY')),
        'test_mode' => getenv('SKRILL_MODE') === 'test',
    ],
    'flutterwave' => [
        'name' => 'Flutterwave',
        'enabled' => !empty(getenv('FLUTTERWAVE_API_KEY')),
        'test_mode' => getenv('FLUTTERWAVE_MODE') === 'test',
    ],
    'cashapp' => [
        'name' => 'Cash App',
        'enabled' => !empty(getenv('CASHAPP_API_KEY')),
        'test_mode' => getenv('CASHAPP_MODE') === 'test',
    ],
    'wise' => [
        'name' => 'Wise',
        'enabled' => !empty(getenv('WISE_API_KEY')),
        'test_mode' => getenv('WISE_MODE') === 'test',
    ],
    'zelle' => [
        'name' => 'Zelle',
        'enabled' => !empty(getenv('ZELLE_API_KEY')),
        'test_mode' => getenv('ZELLE_MODE') === 'test',
    ],
    'westernunion' => [
        'name' => 'Western Union',
        'enabled' => !empty(getenv('WU_API_KEY')),
        'test_mode' => getenv('WU_MODE') === 'test',
    ],
    'orangemoney' => [
        'name' => 'Orange Money',
        'enabled' => !empty(getenv('OM_API_KEY')),
        'test_mode' => getenv('OM_MODE') === 'test',
    ],
    'mtn' => [
        'name' => 'MTN',
        'enabled' => !empty(getenv('MTN_API_KEY')),
        'test_mode' => getenv('MTN_MODE') === 'test',
    ],
    'momo' => [
        'name' => 'MoMo',
        'enabled' => !empty(getenv('MOMO_API_KEY')),
        'test_mode' => getenv('MOMO_MODE') === 'test',
    ],
    'venmo' => [
        'name' => 'Venmo',
        'enabled' => !empty(getenv('VENMO_API_KEY')),
        'test_mode' => getenv('VENMO_MODE') === 'test',
    ],
    'alipay' => [
        'name' => 'Alipay',
        'enabled' => !empty(getenv('ALIPAY_API_KEY')),
        'test_mode' => getenv('ALIPAY_MODE') === 'test',
    ],
    'wechatpay' => [
        'name' => 'WeChat Pay',
        'enabled' => !empty(getenv('WECHATPAY_API_KEY')),
        'test_mode' => getenv('WECHATPAY_MODE') === 'test',
    ],
]);

/**
 * ===== STRIPE PAYMENT PROCESSOR =====
 */
function processStripePayment($orderId, $data) {
    try {
        $secretKey = getenv('STRIPE_SECRET_KEY');
        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Stripe not configured',
                'code' => 503,
            ];
        }

        // Initialize cURL for Stripe API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.stripe.com/v1/payment_intents',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $secretKey . ':',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'amount' => (int)($data['amount'] * 100), // Convert to cents
                'currency' => strtolower($data['currency'] ?? 'usd'),
                'payment_method_types[]' => 'card',
                'metadata[orderId]' => $orderId,
                'metadata[email]' => $data['email'],
                'metadata[productId]' => $data['productId'] ?? 'N/A',
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $result['error']['message'] ?? 'Stripe payment failed',
                'code' => 402,
            ];
        }

        // Update order with Stripe payment intent ID
        updateOrderPaymentReference($orderId, 'stripe', $result['id']);

        return [
            'success' => true,
            'message' => 'Payment intent created',
            'paymentIntentId' => $result['id'],
            'clientSecret' => $result['client_secret'],
            'status' => $result['status'],
        ];

    } catch (Exception $e) {
        Logger::error("Stripe payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== PAYPAL PAYMENT PROCESSOR =====
 */
function processPayPalPayment($orderId, $data) {
    try {
        $clientId = getenv('PAYPAL_CLIENT_ID');
        $clientSecret = getenv('PAYPAL_SECRET');
        $mode = getenv('PAYPAL_MODE') ?? 'sandbox';

        if (!$clientId || !$clientSecret) {
            return [
                'success' => false,
                'message' => 'PayPal not configured',
                'code' => 503,
            ];
        }

        $apiUrl = $mode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';

        // Step 1: Get access token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);
        if (!isset($tokenData['access_token'])) {
            return [
                'success' => false,
                'message' => 'Failed to authenticate with PayPal',
                'code' => 401,
            ];
        }

        $accessToken = $tokenData['access_token'];

        // Step 2: Create order
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/v2/checkout/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $orderId,
                        'amount' => [
                            'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                            'value' => number_format($data['amount'], 2, '.', ''),
                        ],
                        'description' => $data['productName'] ?? 'Dave TopUp Purchase',
                    ],
                ],
                'application_context' => [
                    'return_url' => getenv('APP_URL') . '/public/success.html',
                    'cancel_url' => getenv('APP_URL') . '/public/cancel.html',
                    'brand_name' => 'Dave TopUp',
                    'locale' => 'en-US',
                    'user_action' => 'PAY_NOW',
                ],
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $orderData = json_decode($response, true);

        if (!isset($orderData['id'])) {
            return [
                'success' => false,
                'message' => 'Failed to create PayPal order',
                'code' => 402,
            ];
        }

        // Update order with PayPal order ID
        updateOrderPaymentReference($orderId, 'paypal', $orderData['id']);

        // Get approval URL
        $approvalUrl = null;
        foreach ($orderData['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'PayPal order created',
            'paypalOrderId' => $orderData['id'],
            'approvalUrl' => $approvalUrl,
            'status' => $orderData['status'],
        ];

    } catch (Exception $e) {
        Logger::error("PayPal payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== BINANCE PAY PROCESSOR =====
 */
function processBinancePayment($orderId, $data) {
    try {
        $apiKey = getenv('BINANCE_API_KEY');
        $apiSecret = getenv('BINANCE_SECRET');

        if (!$apiKey || !$apiSecret) {
            return [
                'success' => false,
                'message' => 'Binance not configured',
                'code' => 503,
            ];
        }

        $apiUrl = 'https://api.binance.com/api/v3/bpay/createorder';
        $nonce = time() * 1000;

        $orderData = [
            'merchantId' => getenv('BINANCE_MERCHANT_ID'),
            'merchantTradeNo' => $orderId,
            'totalFeeInBp' => (int)($data['amount'] * 100000000), // Convert to satoshis
            'currency' => 'BUSD', // Binance USD
            'description' => $data['productName'] ?? 'Dave TopUp Purchase',
            'goods' => [
                [
                    'goodsType' => '01',
                    'goodsCategory' => 'Z000',
                    'referenceGoodsId' => $data['productId'] ?? 'TOPUP',
                    'goodsName' => $data['productName'] ?? 'Service Credit',
                    'goodsDetail' => $data['productName'] ?? 'Digital Service',
                    'goodsUnitAmount' => $data['amount'],
                    'goodsQuantity' => 1,
                    'goodsUnit' => 'EACH',
                ]
            ],
            'returnUrl' => getenv('APP_URL') . '/public/success.html',
            'cancelUrl' => getenv('APP_URL') . '/public/cancel.html',
            'webhookUrl' => getenv('APP_URL') . '/api/webhooks/binance',
            'requestTime' => $nonce,
        ];

        $query = json_encode($orderData);
        $signature = hash_hmac('SHA256', $query, $apiSecret, true);
        $signature = base64_encode($signature);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'BinancePay-Timestamp: ' . $nonce,
                'BinancePay-Nonce: ' . bin2hex(openssl_random_pseudo_bytes(16)),
                'BinancePay-Signature: ' . $signature,
                'BinancePay-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $query,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result['code'] !== '000000') {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Binance payment failed',
                'code' => 402,
            ];
        }

        updateOrderPaymentReference($orderId, 'binance', $result['data']['prepayId']);

        return [
            'success' => true,
            'message' => 'Binance order created',
            'prepayId' => $result['data']['prepayId'],
            'checkoutUrl' => $result['data']['checkoutUrl'],
            'status' => 'PENDING',
        ];

    } catch (Exception $e) {
        Logger::error("Binance payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== COINBASE COMMERCE PROCESSOR =====
 */
function processCoinbasePayment($orderId, $data) {
    try {
        $apiKey = getenv('COINBASE_API_KEY');

        if (!$apiKey) {
            return [
                'success' => false,
                'message' => 'Coinbase not configured',
                'code' => 503,
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.commerce.coinbase.com/charges',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-CC-Api-Key: ' . $apiKey,
                'X-CC-Version: 2018-03-22',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'name' => $data['productName'] ?? 'Dave TopUp Purchase',
                'description' => $data['productName'] ?? 'Service Credit',
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $data['amount'],
                    'currency' => strtoupper($data['currency'] ?? 'USD'),
                ],
                'metadata' => [
                    'orderId' => $orderId,
                    'email' => $data['email'],
                    'productId' => $data['productId'] ?? 'N/A',
                ],
                'redirect_url' => getenv('APP_URL') . '/public/success.html?orderId=' . $orderId,
                'cancel_url' => getenv('APP_URL') . '/public/cancel.html',
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!isset($result['data']['id'])) {
            return [
                'success' => false,
                'message' => $result['error']['message'] ?? 'Coinbase payment failed',
                'code' => 402,
            ];
        }

        updateOrderPaymentReference($orderId, 'coinbase', $result['data']['id']);

        return [
            'success' => true,
            'message' => 'Coinbase charge created',
            'chargeId' => $result['data']['id'],
            'hostedUrl' => $result['data']['hosted_url'],
            'status' => $result['data']['status'],
        ];

    } catch (Exception $e) {
        Logger::error("Coinbase payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== CRYPTO WALLET PAYMENT PROCESSOR =====
 */
function processCryptoPayment($orderId, $data) {
    try {
        // Generate crypto payment address
        $cryptocurrencies = ['bitcoin', 'ethereum', 'litecoin', 'ripple'];
        $selectedCrypto = strtolower($data['cryptocurrency'] ?? 'bitcoin');

        if (!in_array($selectedCrypto, $cryptocurrencies)) {
            return [
                'success' => false,
                'message' => 'Invalid cryptocurrency',
                'code' => 400,
            ];
        }

        // Generate payment request (in production, use proper address generation)
        $paymentRequest = [
            'orderId' => $orderId,
            'cryptocurrency' => $selectedCrypto,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'timestamp' => time(),
            'expiresAt' => time() + 3600, // 1 hour expiry
        ];

        // Store payment request for verification
        storePaymentRequest($orderId, json_encode($paymentRequest));

        // Get exchange rate
        $exchangeRate = getCryptoExchangeRate($selectedCrypto, $data['currency'] ?? 'USD');
        $cryptoAmount = $data['amount'] / $exchangeRate;

        // Generate payment address (using random for demo)
        $paymentAddress = generateCryptoAddress($selectedCrypto);

        updateOrderPaymentReference($orderId, 'crypto', $paymentAddress);

        return [
            'success' => true,
            'message' => 'Crypto payment address generated',
            'paymentAddress' => $paymentAddress,
            'cryptocurrency' => $selectedCrypto,
            'amount' => number_format($cryptoAmount, 8),
            'usdValue' => $data['amount'],
            'exchangeRate' => $exchangeRate,
            'expiresAt' => date('Y-m-d H:i:s', time() + 3600),
            'status' => 'PENDING',
        ];

    } catch (Exception $e) {
        Logger::error("Crypto payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== SKRILL PAYMENT PROCESSOR =====
 */
function processSkrillPayment($orderId, $data) {
    try {
        $apiKey = getenv('SKRILL_API_KEY');
        $emailId = getenv('SKRILL_EMAIL_ID');

        if (!$apiKey || !$emailId) {
            return [
                'success' => false,
                'message' => 'Skrill not configured',
                'code' => 503,
            ];
        }

        // Generate payment redirect URL
        $skirllUrl = 'https://pay.skrill.com/';
        
        $params = [
            'recipient' => $emailId,
            'transaction_id' => $orderId,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'language' => 'EN',
            'logo_url' => getenv('APP_URL') . '/assets/logo.png',
            'return_url' => getenv('APP_URL') . '/public/success.html?orderId=' . $orderId,
            'return_url_text' => 'Return to Dave TopUp',
            'cancel_url' => getenv('APP_URL') . '/public/cancel.html',
            'status_url' => getenv('APP_URL') . '/api/webhooks/skrill',
        ];

        $paymentUrl = $skirllUrl . '?' . http_build_query($params);

        updateOrderPaymentReference($orderId, 'skrill', $orderId);

        return [
            'success' => true,
            'message' => 'Skrill payment URL generated',
            'paymentUrl' => $paymentUrl,
            'status' => 'PENDING',
        ];

    } catch (Exception $e) {
        Logger::error("Skrill payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== FLUTTERWAVE PAYMENT PROCESSOR =====
 */
function processFlutterwavePayment($orderId, $data) {
    try {
        $apiKey = getenv('FLUTTERWAVE_API_KEY');

        if (!$apiKey) {
            return [
                'success' => false,
                'message' => 'Flutterwave not configured',
                'code' => 503,
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.flutterwave.com/v3/transactions/initialize',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'tx_ref' => $orderId,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'redirect_url' => getenv('APP_URL') . '/public/success.html?orderId=' . $orderId,
                'payment_options' => 'card,account,ussd,banktransfer,mobilemoney',
                'customer' => [
                    'email' => $data['email'],
                    'name' => $data['name'] ?? 'Customer',
                ],
                'customizations' => [
                    'title' => 'Dave TopUp',
                    'logo' => getenv('APP_URL') . '/assets/logo.png',
                    'description' => $data['productName'] ?? 'Service Purchase',
                ],
                'meta' => [
                    'orderId' => $orderId,
                    'productId' => $data['productId'] ?? 'N/A',
                ],
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result['status'] !== 'success') {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Flutterwave payment failed',
                'code' => 402,
            ];
        }

        updateOrderPaymentReference($orderId, 'flutterwave', $result['data']['link']);

        return [
            'success' => true,
            'message' => 'Flutterwave payment initialized',
            'paymentLink' => $result['data']['link'],
            'status' => 'PENDING',
        ];

    } catch (Exception $e) {
        Logger::error("Flutterwave payment error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== APPLE PAY / GOOGLE PAY PROCESSOR =====
 */
function processAppleGooglePayment($orderId, $data, $paymentMethod) {
    try {
        // Apple Pay and Google Pay use Stripe or similar processors
        // Extract token and process through Stripe or PayPal
        
        $paymentToken = $data['paymentToken'] ?? null;
        
        if (!$paymentToken) {
            return [
                'success' => false,
                'message' => 'Payment token required',
                'code' => 400,
            ];
        }

        // Use Stripe as backend processor for Apple/Google Pay
        return processStripePayment($orderId, array_merge($data, [
            'stripePaymentMethodId' => $paymentToken,
        ]));

    } catch (Exception $e) {
        Logger::error("Apple/Google Pay error: {$e->getMessage()}");
        return [
            'success' => false,
            'message' => 'Payment processing error',
            'code' => 500,
        ];
    }
}

/**
 * ===== HELPER FUNCTIONS =====
 */

function updateOrderPaymentReference($orderId, $paymentMethod, $paymentRef) {
    global $db;
    
    $stmt = $db->prepare(
        "UPDATE orders SET payment_method = ?, payment_reference = ?, payment_status = 'pending' 
         WHERE order_id = ?"
    );
    
    if ($stmt) {
        $stmt->bind_param('sss', $paymentMethod, $paymentRef, $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

function storePaymentRequest($orderId, $requestData) {
    global $db;
    
    $stmt = $db->prepare(
        "INSERT INTO payment_requests (order_id, request_data, created_at) 
         VALUES (?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE request_data = VALUES(request_data), created_at = NOW()"
    );
    
    if ($stmt) {
        $stmt->bind_param('ss', $orderId, $requestData);
        $stmt->execute();
        $stmt->close();
    }
}

function getCryptoExchangeRate($cryptocurrency, $targetCurrency) {
    // This would call CoinGecko or similar API
    // For now, return mock rates
    $rates = [
        'bitcoin' => 43000,
        'ethereum' => 2300,
        'litecoin' => 110,
        'ripple' => 0.52,
    ];
    
    return $rates[strtolower($cryptocurrency)] ?? 1;
}

function generateCryptoAddress($cryptocurrency) {
    // In production, generate real addresses using blockchain APIs
    // For now, generate demo addresses
    $prefixes = [
        'bitcoin' => '1',
        'ethereum' => '0x',
        'litecoin' => 'L',
        'ripple' => 'r',
    ];
    
    $prefix = $prefixes[strtolower($cryptocurrency)] ?? '';
    return $prefix . bin2hex(openssl_random_pseudo_bytes(20));
}

function getPaymentStatus($orderId, $paymentMethod, $paymentRef) {
    switch ($paymentMethod) {
        case 'stripe':
            return getStripePaymentStatus($paymentRef);
        case 'paypal':
            return getPayPalPaymentStatus($paymentRef);
        case 'crypto':
            return getCryptoPaymentStatus($orderId);
        case 'binance':
            return getBinancePaymentStatus($paymentRef);
        case 'coinbase':
            return getCoinbasePaymentStatus($paymentRef);
        default:
            return 'pending';
    }
}

function getStripePaymentStatus($paymentIntentId) {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    if (!$secretKey) return 'unknown';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.stripe.com/v1/payment_intents/' . $paymentIntentId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['status'] ?? 'unknown';
}

function getPayPalPaymentStatus($paypalOrderId) {
    $clientId = getenv('PAYPAL_CLIENT_ID');
    $clientSecret = getenv('PAYPAL_SECRET');
    if (!$clientId || !$clientSecret) return 'unknown';
    
    $mode = getenv('PAYPAL_MODE') ?? 'sandbox';
    $apiUrl = $mode === 'live' 
        ? 'https://api.paypal.com' 
        : 'https://api.sandbox.paypal.com';
    
    // Get access token
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? null;
    
    if (!$accessToken) return 'unknown';
    
    // Get order status
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . '/v2/checkout/orders/' . $paypalOrderId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['status'] ?? 'unknown';
}

function getCryptoPaymentStatus($orderId) {
    global $db;
    
    $stmt = $db->prepare(
        "SELECT request_data FROM payment_requests WHERE order_id = ? LIMIT 1"
    );
    
    if (!$stmt) return 'unknown';
    
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows === 0) {
        return 'pending';
    }
    
    $row = $result->fetch_assoc();
    $request = json_decode($row['request_data'], true);
    
    // In production, check blockchain for transaction
    return 'pending';
}

function getBinancePaymentStatus($prepayId) {
    // Implement Binance API call
    return 'pending';
}

function getCoinbasePaymentStatus($chargeId) {
    $apiKey = getenv('COINBASE_API_KEY');
    if (!$apiKey) return 'unknown';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.commerce.coinbase.com/charges/' . $chargeId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-CC-Api-Key: ' . $apiKey,
            'X-CC-Version: 2018-03-22',
        ],
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['data']['status'] ?? 'unknown';
}

/**
 * ===== CASH APP PAYMENT PROCESSOR =====
 */
function processCashAppPayment($orderId, $data) {
    $apiKey = getenv('CASHAPP_API_KEY');
    
    if (!$apiKey) {
        return [
            'success' => false,
            'error' => 'Cash App API key not configured',
            'code' => 'CONFIG_ERROR'
        ];
    }

    try {
        // Generate unique transaction ID
        $transactionId = 'CASHAPP-' . $orderId . '-' . time();
        
        // Cash App payment data
        $amount = (int)($data['amount'] * 100); // Convert to cents
        $currency = $data['currency'] ?? 'USD';
        
        // Create Cash App payment request
        $payload = [
            'idempotency_key' => $transactionId,
            'source_id' => 'CARD',  // Payment source
            'amount_money' => [
                'amount' => $amount,
                'currency' => $currency
            ],
            'order_id' => $orderId,
            'autocomplete' => true,
            'customer_id' => md5($data['email']),  // Create unique customer ID
            'reference_id' => $transactionId,
            'note' => 'Dave TopUp - ' . $data['productName'],
            'receipt' => [
                'title' => 'Dave TopUp Purchase',
                'description' => $data['productName'],
                'url' => getenv('APP_URL') . '/api/receipts/' . $orderId
            ]
        ];

        // Make request to Cash App API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://connect.squareup.com/v2/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Square-Version: 2024-01-18'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result['payment']['id'])) {
            $paymentId = $result['payment']['id'];
            
            // Store payment reference
            updateOrderPaymentReference($orderId, $paymentId);
            
            // Store Cash App payment request
            storeCashAppPaymentRequest($orderId, $paymentId, $amount, $currency);

            return [
                'success' => true,
                'paymentId' => $paymentId,
                'status' => 'pending',
                'cashAppPaymentId' => $paymentId,
                'receiptUrl' => $result['payment']['receipt_url'] ?? null,
                'receiptNumber' => $result['payment']['receipt_number'] ?? null
            ];
        } else {
            $errorMessage = $result['errors'][0]['detail'] ?? 'Payment processing failed';
            Logger::error("Cash App payment error: " . $errorMessage);
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => 'CASHAPP_ERROR',
                'status' => 'failed'
            ];
        }

    } catch (Exception $e) {
        Logger::error("Cash App payment exception: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'Payment processing error: ' . $e->getMessage(),
            'code' => 'EXCEPTION_ERROR',
            'status' => 'failed'
        ];
    }
}

/**
 * Store Cash App Payment Request
 */
function storeCashAppPaymentRequest($orderId, $paymentId, $amount, $currency) {
    global $db;
    
    $stmt = $db->prepare(
        "INSERT INTO cashapp_payments (order_id, payment_id, amount, currency, status, created_at) 
         VALUES (?, ?, ?, ?, 'pending', NOW())"
    );
    
    if ($stmt) {
        $stmt->bind_param('ssss', $orderId, $paymentId, $amount, $currency);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Get Cash App Payment Status
 */
function getCashAppPaymentStatus($paymentId) {
    $apiKey = getenv('CASHAPP_API_KEY');
    
    if (!$apiKey) return 'unknown';
    
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://connect.squareup.com/v2/payments/' . $paymentId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Square-Version: 2024-01-18'
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $status = $result['payment']['status'] ?? 'unknown';
            
            // Map Square payment status to our status
            $statusMap = [
                'COMPLETED' => 'completed',
                'APPROVED' => 'completed',
                'PENDING' => 'pending',
                'CANCELED' => 'failed',
                'FAILED' => 'failed'
            ];

            return $statusMap[$status] ?? 'unknown';
        }

        return 'unknown';
    } catch (Exception $e) {
        Logger::error("Cash App status check failed: " . $e->getMessage());
        return 'unknown';
    }
}

?>

