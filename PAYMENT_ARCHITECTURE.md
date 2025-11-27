# Payment Processing Architecture

Complete technical documentation for Dave TopUp payment system.

## System Overview

```
Frontend (index2.html)
    ↓
Checkout Modal (User selects payment method)
    ↓
API Endpoint (POST /api/checkout)
    ↓
Order Creation (orders table)
    ↓
Payment Processor (payment-processor.php)
    ↓
External Payment Gateway (Stripe, PayPal, etc.)
    ↓
Customer completes payment
    ↓
Webhook Callback (/api/webhooks/{processor})
    ↓
Order Status Update (transactions table)
    ↓
Confirmation Email
    ↓
Frontend redirect to success.html
```

## Database Schema

### Orders Table
```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    user_country VARCHAR(10) NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) NOT NULL,
    payment_processor_reference VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_email (user_email),
    INDEX idx_status (status)
);
```

### Transactions Table (Legacy)
```sql
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50) NOT NULL,
    card_funding VARCHAR(20) DEFAULT NULL, -- 'debit', 'credit', 'prepaid', or NULL
    transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
);
```

### Payment Requests Table (Crypto)
```sql
CREATE TABLE payment_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) NOT NULL,
    payment_address VARCHAR(255) NOT NULL,
    payment_amount DECIMAL(20, 8) NOT NULL,
    crypto_type VARCHAR(20) NOT NULL,
    usd_amount DECIMAL(10, 2) NOT NULL,
    exchange_rate DECIMAL(15, 8) NOT NULL,
    status ENUM('pending', 'confirmed', 'expired', 'cancelled') DEFAULT 'pending',
    expires_at TIMESTAMP NOT NULL,
    confirmed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_payment_address (payment_address),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);
```

### Refunds Table
```sql
CREATE TABLE refunds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) NOT NULL,
    refund_amount DECIMAL(10, 2) NOT NULL,
    payment_processor_reference VARCHAR(255),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status)
);
```

### Cash App Payments Table
```sql
CREATE TABLE cashapp_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) NOT NULL,
    payment_id VARCHAR(255) UNIQUE NOT NULL,
    amount VARCHAR(50) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'completed', 'failed', 'canceled') DEFAULT 'pending',
    receipt_url VARCHAR(512),
    receipt_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status)
);
```

## API Endpoints

### POST /api/checkout
**Purpose**: Create order and initiate payment

**Request**:
```json
{
    "email": "user@example.com",
    "name": "John Doe",
    "country": "HT",
    "productId": 1,
    "productName": "5000 Coins",
    "amount": 9.99,
    "paymentMethod": "stripe"
}
```

**Response** (Success):
```json
{
    "success": true,
    "orderId": "ORD-1234567890",
    "status": "pending",
    "paymentMethod": "stripe",
    "paymentData": {
        "clientSecret": "pi_1234567890...",
        "paymentIntentId": "pi_1234567890...",
        "publishableKey": "pk_test_..."
    }
}
```

**Response** (Error):
```json
{
    "success": false,
    "error": "Invalid email format",
    "code": "VALIDATION_ERROR"
}
```

### GET /api/order-details?orderId={orderId}
**Purpose**: Fetch order status and details

**Response**:
```json
{
    "success": true,
    "order": {
        "orderId": "ORD-1234567890",
        "email": "user@example.com",
        "amount": 9.99,
        "status": "completed",
        "paymentMethod": "stripe",
        "createdAt": "2024-01-15T10:30:00Z",
        "updatedAt": "2024-01-15T10:35:00Z"
    }
}
```

## Payment Processing Flow

### Step 1: Order Creation (checkout.php)

```php
// Validate input
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$amount = (float) $_POST['amount'];

// Check if in valid range ($0.50 - $10,000)
if ($amount < 0.5 || $amount > 10000) {
    return error('Invalid amount');
}

// Create order
$orderId = 'ORD-' . time() . '-' . bin2hex(random_bytes(4));
$stmt = $db->prepare(
    "INSERT INTO orders (order_id, user_email, user_name, user_country, product_id, product_name, amount, payment_method, status) 
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
);
$stmt->bind_param('sssssdss', $orderId, $email, $name, $country, $productId, $productName, $amount, $paymentMethod);
$stmt->execute();
```

### Step 2: Route to Payment Processor (payment-processor.php)

```php
switch ($paymentMethod) {
    case 'stripe':
        $result = processStripePayment($orderId, $data);
        break;
    case 'paypal':
        $result = processPayPalPayment($orderId, $data);
        break;
    case 'binance':
        $result = processBinancePayment($orderId, $data);
        break;
    // ... other processors
}
```

### Step 3: Processor Initialization

**Stripe Example**:
```php
function processStripePayment($orderId, $data) {
    $secretKey = getenv('STRIPE_SECRET_KEY');
    
    $intent = \Stripe\PaymentIntent::create([
        'amount' => (int) ($data['amount'] * 100), // Convert to cents
        'currency' => 'usd',
        'metadata' => ['orderId' => $orderId],
        'description' => $data['productName']
    ]);

    // Store payment reference
    updateOrderPaymentReference($orderId, $intent->id);

    return [
        'success' => true,
        'paymentIntentId' => $intent->id,
        'clientSecret' => $intent->client_secret,
        'status' => 'pending'
    ];
}
```

### Step 4: Frontend Payment Confirmation

```javascript
// index2.html
async function proceedCheckout() {
    const response = await fetch('/api/checkout', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            email: form.email.value,
            name: form.name.value,
            country: form.country.value,
            productId: currentProduct.id,
            productName: currentProduct.name,
            amount: currentProduct.price,
            paymentMethod: form.paymentMethod.value
        })
    });

    const result = await response.json();
    
    if (result.success) {
        // Redirect to payment processor UI based on method
        if (result.paymentMethod === 'stripe') {
            // Use Stripe.js to confirm payment
            stripe.confirmCardPayment(result.paymentData.clientSecret);
        } else if (result.paymentMethod === 'paypal') {
            // Redirect to PayPal approval URL
            window.location.href = result.paymentData.approvalUrl;
        }
        // ... other payment methods
    }
}
```

### Step 5: Webhook Callback

**Stripe Webhook Example**:
```php
// POST /api/webhooks/stripe
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhookSecret);

if ($event->type === 'payment_intent.succeeded') {
    $orderId = $event->data->object->metadata->orderId;
    $transactionId = $event->data->object->id;
    
    // Update order status
    $stmt = $db->prepare(
        "UPDATE orders SET status = 'completed', payment_processor_reference = ? WHERE order_id = ?"
    );
    $stmt->bind_param('ss', $transactionId, $orderId);
    $stmt->execute();
    
    // Send confirmation email
    sendConfirmationEmail($email, $orderId, $amount);
}
```

### Step 6: Order Completion

Order marked as `completed` in database, customer:
- Receives confirmation email
- Redirected to success.html
- Can view order details via GET /api/order-details

## Payment Methods Implementation

### 1. Stripe
- **Type**: Direct API integration
- **Authentication**: Secret API key
- **Flow**: Create payment intent → Customer confirms → Webhook callback
- **Status Tracking**: Real-time via webhooks
- **Test Card**: 4242 4242 4242 4242

### 2. PayPal
- **Type**: OAuth 2.0 + Orders API
- **Authentication**: Client ID + Secret
- **Flow**: Get access token → Create order → Redirect to PayPal → Webhook callback
- **Status Tracking**: Webhook notification
- **Test Account**: Sandbox buyer account

### 3. Binance Pay
- **Type**: HMAC-signed REST API
- **Authentication**: API Key + Secret
- **Flow**: Create prepay order → Redirect to Binance checkout → Webhook callback
- **Status Tracking**: Webhook with HMAC signature verification
- **Test Mode**: Binance testnet available

### 4. Coinbase Commerce
- **Type**: REST API with signatures
- **Authentication**: API Key
- **Flow**: Create charge → Redirect to hosted page → Webhook callback
- **Status Tracking**: Webhook notifications
- **Test Mode**: Sandbox environment

### 5. Skrill
- **Type**: Redirect-based integration
- **Authentication**: Merchant ID + Secret
- **Flow**: Build payment URL with parameters → Redirect → Webhook callback
- **Status Tracking**: Webhook with MD5 signature
- **Test Mode**: Test merchant account available

### 6. Flutterwave
- **Type**: Direct + Hosted integration
- **Authentication**: Public + Secret keys
- **Flow**: Initialize transaction → Redirect to payment form → Webhook callback
- **Status Tracking**: Webhook with HMAC signature
- **Test Mode**: Test account with dummy payments

### 7. Cryptocurrency
- **Type**: Direct wallet transfer
- **Flow**: Generate address → Display QR code → Listen to blockchain → Manual verification
- **Status Tracking**: Blockchain confirmation
- **Supported**: BTC, ETH, LTC, XRP

### 8. Apple/Google Pay
- **Type**: Stripe token-based
- **Authentication**: Merchant ID + Certificate
- **Flow**: Tokenize payment → Send to Stripe → Webhook callback
- **Status Tracking**: Via Stripe webhooks
- **Requirements**: HTTPS + Domain verification

## Security Implementation

### 1. Input Validation
```php
function validateCheckoutData($data) {
    // Email validation
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email');
    }

    // Amount validation (between $0.50 and $10,000)
    $amount = (float) $data['amount'];
    if ($amount < 0.5 || $amount > 10000) {
        throw new Exception('Invalid amount');
    }

    // Country validation (2-3 character country code)
    if (!preg_match('/^[A-Z]{2,3}$/', $data['country'])) {
        throw new Exception('Invalid country code');
    }

    // Payment method whitelist
    $allowed = ['stripe', 'paypal', 'binance', 'coinbase', 'crypto', 'skrill', 'flutterwave'];
    if (!in_array($data['paymentMethod'], $allowed)) {
        throw new Exception('Invalid payment method');
    }
}
```

### 2. CORS Configuration
```php
// Allow frontend to make requests
header('Access-Control-Allow-Origin: ' . getAllowedOrigins());
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 3600');

// Enforce HTTPS in production
if (getenv('APP_ENV') === 'production' && empty($_SERVER['HTTPS'])) {
    http_response_code(403);
    exit('HTTPS required');
}
```

### 3. Webhook Signature Verification
All processors verify webhook authenticity:

```php
// Stripe uses signing secret
$event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);

// PayPal uses HMAC-SHA256
$expectedSig = base64_encode(hash_hmac('sha256', $data, $secret, true));

// Binance uses HMAC-SHA256
$expectedSig = hash_hmac('sha256', $payload . $nonce . $timestamp, $secret);

// All check for signature match before processing
if (!hash_equals($expectedSig, $receivedSig)) {
    throw new Exception('Invalid signature');
}
```

### 4. Database Security
```php
// Prepared statements prevent SQL injection
$stmt = $db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
$stmt->bind_param('ss', $status, $orderId);
$stmt->execute();

// Sensitive data not stored (payment details handled by processors)
// Only store payment processor reference IDs
```

### 5. Environment Variables
```php
// Never hardcode API keys
$stripeKey = getenv('STRIPE_SECRET_KEY'); // Set in .env file
$paypalSecret = getenv('PAYPAL_SECRET');

// .env file should NOT be in version control
// Use: cp .env.example .env && edit .env with real credentials
```

## Error Handling

### Validation Errors (400)
```json
{
    "success": false,
    "error": "Invalid email format",
    "code": "VALIDATION_ERROR",
    "details": ["Invalid email format"]
}
```

### Authentication Errors (401/403)
```json
{
    "success": false,
    "error": "Unauthorized",
    "code": "AUTH_ERROR"
}
```

### Payment Processor Errors (500)
```json
{
    "success": false,
    "error": "Payment processor unavailable",
    "code": "PROCESSOR_ERROR",
    "processorMessage": "Connection timeout"
}
```

### Webhook Errors (400/403)
```json
{
    "success": false,
    "error": "Invalid webhook signature",
    "code": "WEBHOOK_ERROR"
}
```

## Testing

### Unit Tests
Test each payment processor function:
```php
// Test Stripe payment processing
function testProcessStripePayment() {
    $result = processStripePayment('TEST-ORDER-1', [
        'amount' => 9.99,
        'productName' => 'Test Product'
    ]);
    
    assert($result['success'] === true);
    assert(!empty($result['paymentIntentId']));
    assert(!empty($result['clientSecret']));
}
```

### Integration Tests
Test complete checkout flow:
```php
// Test checkout to webhook
function testCompleteCheckoutFlow() {
    // 1. Create order
    $order = createOrder('test@example.com', 'Stripe', 9.99);
    assert($order['status'] === 'pending');
    
    // 2. Process payment
    $payment = processStripePayment($order['orderId'], $order);
    
    // 3. Simulate webhook
    simulateStripeWebhook('payment_intent.succeeded', [
        'id' => $payment['paymentIntentId'],
        'metadata' => ['orderId' => $order['orderId']]
    ]);
    
    // 4. Verify order updated
    $updated = getOrder($order['orderId']);
    assert($updated['status'] === 'completed');
}
```

### Manual Testing Checklist
- [ ] Test each payment method with test credentials
- [ ] Verify order created before payment
- [ ] Confirm customer redirected to processor
- [ ] Test webhook receives event
- [ ] Verify order status updates to "completed"
- [ ] Check confirmation email sent
- [ ] Test refund processing
- [ ] Test error scenarios (declined card, etc.)
- [ ] Verify success.html shows correct order details

## Monitoring & Logging

### Log All Transactions
```php
Logger::info("Order created: $orderId");
Logger::info("Payment initiated: {$paymentMethod} - Amount: \${$amount}");
Logger::info("Webhook received: {$webhookType} - Order: {$orderId}");
Logger::error("Payment failed: {$orderId} - {$error}");
```

### Monitor Webhook Failures
```php
// Retry failed webhooks
CREATE TABLE webhook_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_type VARCHAR(50),
    order_id VARCHAR(50),
    payload JSON,
    status ENUM('success', 'failed', 'pending'),
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_retry_count (retry_count)
);
```

### Key Metrics to Track
- Total transactions processed
- Success rate by payment method
- Average transaction time
- Webhook failure rate
- Email delivery rate
- Payment refund rate

## Deployment Checklist

### Pre-Production
- [ ] All tests passing
- [ ] Environment variables configured
- [ ] HTTPS certificate installed
- [ ] Webhooks pointing to correct URLs
- [ ] Email service configured
- [ ] Database backups enabled
- [ ] Rate limiting configured
- [ ] Logging enabled

### Production
- [ ] Switch to live payment keys
- [ ] Verify SSL certificate validity
- [ ] Monitor first 10 transactions
- [ ] Set up error alerts
- [ ] Configure backup payment method
- [ ] Plan incident response

## Support & Troubleshooting

### Common Issues

**Issue**: Webhook not triggering
- Solution: Verify webhook URL is HTTPS with valid cert
- Check: Webhook is registered with correct secret
- Verify: Firewall allows incoming requests

**Issue**: Order stuck in "pending"
- Solution: Check webhook logs for failures
- Check: Payment processor returned error
- Manual: Update status in database if payment confirmed

**Issue**: Duplicate orders
- Solution: Add unique constraint on order_id
- Verify: Frontend doesn't submit twice
- Check: API returns 409 Conflict if duplicate

**Issue**: Payment amount mismatch
- Solution: Verify amount formatting (cents vs dollars)
- Check: Currency conversion applied correctly
- Compare: Order amount vs payment processor recorded amount

---

**For questions or issues**: support@davetopup.com
