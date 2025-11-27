## Black Friday Redeem Endpoint Test

To redeem all Black Friday orders:

```
curl -X POST http://localhost:8080/api/redeem-black-friday.php
```

Expected response:
```
{
  "success": true,
  "message": "Redeemed N Black Friday orders.",
  "redeemed": N,
  "orders": ["ORD-...", ...]
}
```
# DaveTopUp Checkout - Testing Guide

## 🧪 Test Strategy

This document outlines comprehensive testing procedures for all payment flows.

---

## Manual Testing Checklist

### 1. Order Creation Flow

**Test Case 1.1: Valid order creation**
```
Steps:
1. Navigate to checkout page
2. Fill form:
   - Email: test@example.com
   - Player UID: 123456789
   - Player Nickname: TestPlayer
   - Phone: +1234567890
3. Select 1-3 items
4. Click "Continue"

Expected Result:
✓ Order created successfully
✓ Order ID displayed: ORD-***
✓ Total with tax calculated correctly
✓ Items listed on receipt
```

**Test Case 1.2: Invalid email**
```
Steps:
1. Enter invalid email: "notemail"
2. Click submit

Expected Result:
✗ Form validation error
✓ Error message: "Please enter valid email"
✓ Form not submitted
```

**Test Case 1.3: Missing required fields**
```
Steps:
1. Leave player UID empty
2. Click submit

Expected Result:
✗ Form validation error
✓ Error message: "Player UID required"
✓ Form not submitted
```

---

### 2. Card Payment Testing

**Test Case 2.1: Successful card payment**
```
Steps:
1. Create order
2. Select "Credit/Debit Card"
3. Enter test card: 4242 4242 4242 4242
4. Enter any future date (e.g., 12/25)
5. Enter any CVC (e.g., 123)
6. Click "Pay"

Expected Result:
✓ Payment processed successfully
✓ Success page displayed
✓ Order status: "payment_confirmed"
✓ Transaction ID shown
✓ Receipt email sent
```

**Test Case 2.2: 3D Secure required card**
```
Steps:
1. Create order
2. Select "Credit/Debit Card"
3. Enter test card: 4000 0025 0000 3155
4. Complete 3D Secure authentication
5. Click confirm

Expected Result:
✓ 3D Secure modal appears
✓ Authentication completed
✓ Payment processed after authentication
✓ Success page displayed
```

**Test Case 2.3: Declined card**
```
Steps:
1. Create order
2. Select "Credit/Debit Card"
3. Enter test card: 4000 0000 0000 0002
4. Click "Pay"

Expected Result:
✗ Payment declined
✓ Error message: "Card declined"
✓ Failed page displayed
✓ Order status: "failed"
```

**Test Case 2.4: Expired card**
```
Steps:
1. Create order
2. Select "Credit/Debit Card"
3. Enter test card: 4242 4242 4242 4242
4. Enter past date (e.g., 01/20)
5. Click "Pay"

Expected Result:
✗ Payment failed
✓ Error message: "Card expired"
```

---

### 3. PayPal Testing

**Test Case 3.1: Successful PayPal payment (Sandbox)**
```
Steps:
1. Create order
2. Select "PayPal"
3. Click "Pay with PayPal"
4. Redirected to PayPal sandbox
5. Login: sandbox buyer account
6. Approve payment
7. Redirected back to success page

Expected Result:
✓ PayPal approval page loads
✓ After approval, redirect to success page
✓ Order status: "payment_confirmed"
✓ Transaction ID: PayPal transaction ID
✓ Receipt email sent
```

**Test Case 3.2: PayPal payment cancelled**
```
Steps:
1. Create order
2. Select "PayPal"
3. Click "Pay with PayPal"
4. Login to PayPal
5. Click "Cancel"
6. Return to cancel page

Expected Result:
✓ Redirected to cancel page
✓ Order status remains: "pending"
✓ Payment not charged
```

---

### 4. Binance Pay Testing

**Test Case 4.1: Initiate Binance payment**
```
Steps:
1. Create order
2. Select "Binance Pay"
3. Click "Pay with Binance"
4. Checkout URL generated
5. Scan QR code or open in new tab

Expected Result:
✓ Checkout URL generated
✓ QR code displayed
✓ Redirect to Binance Pay checkout
✓ Payment can be completed
```

---

### 5. Voucher/Gift Card Testing

**Test Case 5.1: Valid voucher redemption**
```
Prerequisites:
- Create voucher in admin: GIFT-CARD-TEST ($50)

Steps:
1. Create order (amount < $50)
2. Select "Gift Card/Voucher"
3. Enter: GIFT-CARD-TEST
4. Click "Redeem"

Expected Result:
✓ Voucher validated
✓ Voucher applied
✓ Order status: "payment_confirmed"
✓ Remaining balance tracked
```

**Test Case 5.2: Insufficient balance**
```
Prerequisites:
- Create voucher: GIFT-LOW ($5)

Steps:
1. Create order ($50)
2. Select "Gift Card/Voucher"
3. Enter: GIFT-LOW
4. Click "Redeem"

Expected Result:
✗ Voucher rejected
✓ Error: "Insufficient balance"
✓ Order status remains: "pending"
```

**Test Case 5.3: Expired voucher**
```
Prerequisites:
- Create expired voucher: GIFT-EXPIRED

Steps:
1. Create order
2. Select "Gift Card/Voucher"
3. Enter: GIFT-EXPIRED
4. Click "Redeem"

Expected Result:
✗ Voucher rejected
✓ Error: "Voucher expired"
```

**Test Case 5.4: Invalid voucher code**
```
Steps:
1. Create order
2. Select "Gift Card/Voucher"
3. Enter: INVALID-CODE-12345
4. Click "Redeem"

Expected Result:
✗ Voucher not found
✓ Error: "Voucher code not found"
```

---

### 6. User Experience Testing

**Test Case 6.1: Mobile responsive design**
```
Steps:
1. Open checkout on mobile device (iPhone, Android)
2. Test form inputs
3. Test payment method selection
4. Complete payment

Expected Result:
✓ Layout responsive and readable
✓ Form inputs accessible
✓ Payment methods stack vertically
✓ Buttons appropriately sized for touch
✓ No horizontal scrolling
```

**Test Case 6.2: Loading states**
```
Steps:
1. Create order
2. Start payment
3. Observe loading indicator

Expected Result:
✓ Loading spinner displays
✓ Submit button disabled during processing
✓ User cannot submit duplicate requests
```

**Test Case 6.3: Error recovery**
```
Steps:
1. Create order
2. Start payment with invalid card
3. Payment fails
4. User corrects card information
5. Retry payment

Expected Result:
✓ Form retains previous valid inputs
✓ Can correct invalid fields
✓ Retry payment succeeds
```

---

## Automated Testing

### Backend Unit Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Feature/OrderControllerTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html=coverage/
```

**Key test files to create:**

1. **tests/Feature/OrderControllerTest.php**
   - Test order creation with valid data
   - Test order validation (email, player UID)
   - Test order retrieval
   - Test idempotency (prevent duplicate orders)

2. **tests/Feature/PaymentControllerTest.php**
   - Test card payment processing
   - Test PayPal payment initiation and capture
   - Test Binance payment initiation
   - Test 3D Secure handling
   - Test webhook processing

3. **tests/Feature/VoucherServiceTest.php**
   - Test voucher validation
   - Test voucher redemption
   - Test balance checking
   - Test expiration validation
   - Test manual verification

4. **tests/Unit/ValidationTest.php**
   - Email validation
   - Phone number validation
   - Player UID format
   - Card number validation

### Frontend Testing

```bash
# Run React tests
npm test

# Run with coverage
npm test -- --coverage

# E2E testing with Playwright
npx playwright test
```

---

## API Testing with Postman

### Import Collection
1. Open Postman
2. Click "Import"
3. Select `DaveTopUp-Checkout-API.postman_collection.json`

### Configure Variables
```json
{
  "base_url": "http://localhost:8000",
  "admin_token": "your_admin_bearer_token",
  "order_id": "ORD-abc123-1700000000"
}
```

### Test Sequence
1. **Create Order** → Copy order_id
2. **Process Payment** → Use returned order_id
3. **Check Order Status** → Verify payment reflected
4. **List Orders (Admin)** → Verify order appears

---

## Webhook Testing

### Stripe Webhook Testing

```bash
# Using Stripe CLI
stripe listen --forward-to localhost:8000/webhooks/stripe

# Get webhook signing secret from output
# Set STRIPE_WEBHOOK_SECRET in .env

# Trigger test events
stripe trigger payment_intent.succeeded
stripe trigger payment_intent.payment_failed
```

### PayPal Webhook Testing

```bash
# Use PayPal's webhook simulator
# Go to: https://developer.paypal.com/dashboard/
# Sandbox → Webhooks → Test Event

# Select event type: CHECKOUT.ORDER.COMPLETED
# Send test event to: http://localhost:8000/webhooks/paypal
```

### Local Webhook Testing

```bash
# Use ngrok to expose local server
ngrok http 8000
# https://abcd-123-456-789.ngrok.io → http://localhost:8000

# Update webhook URLs in dashboard:
# Stripe: https://abcd-123-456-789.ngrok.io/webhooks/stripe
# PayPal: https://abcd-123-456-789.ngrok.io/webhooks/paypal
# Binance: https://abcd-123-456-789.ngrok.io/webhooks/binance
```

---

## Performance Testing

### Load Testing

```bash
# Using Apache Bench
ab -n 1000 -c 10 http://localhost:8000/api/orders

# Using wrk
wrk -t12 -c400 -d30s http://localhost:8000/api/orders
```

### Expected Performance
- Order creation: < 100ms
- Payment processing: < 2s
- Webhook processing: < 500ms

---

## Security Testing

### Input Validation

**Test Case: SQL Injection**
```
Email field: admin@example.com' OR '1'='1
Expected: Input sanitized, no SQL injection
```

**Test Case: XSS Attack**
```
Player Nickname: <script>alert('xss')</script>
Expected: Script escaped, no XSS vulnerability
```

**Test Case: CSRF Protection**
```
POST without CSRF token
Expected: 419 Unauthorized response
```

### Rate Limiting

```bash
# Send multiple requests rapidly
for i in {1..100}; do
  curl -X POST http://localhost:8000/api/orders \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com"}'
done

# Expected: After 60 requests in 1 minute, 429 Too Many Requests
```

---

## Regression Testing

Create test scenarios for each update:

1. **Before deploying**, verify:
   - All payment methods still work
   - All webhooks process correctly
   - Order status transitions work
   - Email sending works
   - Admin functions work

2. **Automated regression suite**
```bash
# Run before each deployment
./vendor/bin/phpunit --testsuite=Regression
```

---

## Test Data

### Sample Orders

```json
{
  "items": [
    {
      "id": "ff-diamonds-100",
      "name": "100 Diamonds",
      "game": "Free Fire",
      "price": 9.99,
      "quantity": 1
    },
    {
      "id": "ff-coins-50",
      "name": "50 Coins",
      "game": "Free Fire",
      "price": 4.99,
      "quantity": 1
    }
  ],
  "email": "test@example.com",
  "playerUid": "123456789",
  "playerNickname": "TestPlayer",
  "phone": "+1234567890"
}
```

### Voucher Codes (for testing)

| Code | Amount | Status | Notes |
|------|--------|--------|-------|
| GIFT-CARD-TEST | $50.00 | Active | Full balance |
| GIFT-CARD-LOW | $5.00 | Active | Low balance |
| GIFT-CARD-EXPIRED | $100.00 | Expired | For expiry testing |
| GIFT-CARD-USED | $20.00 | Used | Max uses reached |

---

## Known Test Issues

- **3D Secure:** Not fully triggerable in all sandbox environments
- **PayPal sandbox:** May require manual approval of seller account first
- **Binance Pay:** Requires certificate setup for webhook verification

---

## Continuous Integration

### GitHub Actions Workflow

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: davetopup_test
    
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
      - name: Install dependencies
        run: composer install
      - name: Run migrations
        run: php artisan migrate --database=testing
      - name: Run tests
        run: ./vendor/bin/phpunit
```

---

## Final Sign-Off

Before going to production, verify:

- [ ] All manual test cases pass
- [ ] Unit tests pass (>80% coverage)
- [ ] Integration tests pass
- [ ] Load tests pass
- [ ] Security tests pass
- [ ] Webhook testing verified
- [ ] Mobile responsive verified
- [ ] Email receipts verified
- [ ] Admin functions verified
- [ ] Database backups verified

**Release ready? ✓**

---

**Last Updated:** January 2024  
**Version:** 1.0.0
