# DaveTopUp - Production Checkout System
## Complete Implementation Guide

---

## 📋 Table of Contents
1. [System Architecture](#architecture)
2. [Frontend Setup](#frontend)
3. [Backend Setup](#backend)
4. [Database Schema](#database)
5. [Payment Integrations](#payments)
6. [Deployment](#deployment)
7. [API Documentation](#api)
8. [Testing](#testing)

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  Frontend (React + TS)                   │
│  ┌──────────────┐  ┌─────────────┐  ┌──────────────┐   │
│  │   Checkout   │  │   Payment   │  │   Success    │   │
│  │  Component   │  │   Methods   │  │   Page       │   │
│  └──────────────┘  └─────────────┘  └──────────────┘   │
└──────────────────────────────┬──────────────────────────┘
                               │ HTTPS/REST API
┌──────────────────────────────┴──────────────────────────┐
│              Backend (Laravel 9+)                        │
│  ┌──────────────┐  ┌─────────────┐  ┌──────────────┐   │
│  │   Orders     │  │  Payments   │  │  Webhooks    │   │
│  │  Controller  │  │  Service    │  │  Handler     │   │
│  └──────────────┘  └─────────────┘  └──────────────┘   │
└──────────────────────────────┬──────────────────────────┘
                               │
        ┌──────────┬───────────┼───────────┬──────────┐
        │          │           │           │          │
        ▼          ▼           ▼           ▼          ▼
    [Stripe]  [PayPal]    [Binance]   [Database]  [TopUp API]
    [Webhook]  [Webhook]   [Webhook]   [MySQL]     [Delivery]
```

---

## Frontend Setup

### Prerequisites
- Node.js 16+
- npm or yarn

### Installation

```bash
cd frontend
npm install
```

### Environment Configuration

Create `.env.local`:
```env
REACT_APP_API_BASE=https://api.davetopup.com
REACT_APP_STRIPE_PUBLIC_KEY=pk_live_xxxxx
REACT_APP_ENVIRONMENT=production
```

### Build & Run

```bash
# Development
npm start

# Production build
npm run build

# Run tests
npm test
```

### Key Components
- **Checkout.tsx** - Main checkout form with payment method selection
- **OrderSummary** - Order preview component
- **PaymentMethodSelector** - Radio button selection for payment methods
- **CardPaymentForm** - Stripe card element wrapper
- **VoucherForm** - Voucher/gift card redemption

---

## Backend Setup

### Prerequisites
- PHP 8.0+
- Laravel 9+
- MySQL 5.7+
- Composer

### Installation

```bash
cd backend
composer install
php artisan migrate
php artisan seed
```

### Environment Configuration

Create `.env`:
```env
APP_NAME=DaveTopUp
APP_ENV=production
APP_KEY=base64:xxx
APP_DEBUG=false
APP_URL=https://davetopup.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=davetopup
DB_USERNAME=davetopup_user
DB_PASSWORD=secure_password

# Stripe
STRIPE_PUBLIC_KEY=pk_live_xxxxx
STRIPE_SECRET_KEY=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

# PayPal
PAYPAL_MODE=live
PAYPAL_CLIENT_ID=xxxxx
PAYPAL_SECRET=xxxxx

# Binance Pay
BINANCE_API_KEY=xxxxx
BINANCE_SECRET_KEY=xxxxx
BINANCE_MERCHANT_ID=xxxxx

# Top-up Provider
TOPUP_PROVIDER_API_KEY=xxxxx
TOPUP_PROVIDER_URL=https://api.topupprovider.com

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=xxxxx
MAIL_FROM_ADDRESS=noreply@davetopup.com
```

### Database

```bash
# Create database
mysql -u root -p
CREATE DATABASE davetopup;
CREATE USER 'davetopup_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON davetopup.* TO 'davetopup_user'@'localhost';
FLUSH PRIVILEGES;

# Run migrations
php artisan migrate --force

# Seed sample data
php artisan db:seed
```

### Key Services
- **PaymentService** - Stripe, PayPal, Binance integration
- **VoucherService** - Voucher validation and redemption
- **TopUpService** - Game currency delivery
- **WebhookService** - Payment gateway callbacks

---

## Database Schema

### Orders Table
```sql
CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    player_uid VARCHAR(50) NOT NULL,
    player_nickname VARCHAR(50) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'payment_confirmed', 'delivered', 'failed', 'refunded'),
    idempotency_key UUID UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
);

CREATE TABLE order_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    game VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    transaction_id VARCHAR(255) UNIQUE,
    payment_method ENUM('card', 'paypal', 'binance', 'voucher') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'requires_3d_secure', 'requires_verification'),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status)
  card_funding VARCHAR(20),
);

CREATE TABLE vouchers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    used_count INT DEFAULT 0,
    max_uses INT,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
);

CREATE TABLE webhook_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    response_status INT,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider (provider),
    INDEX idx_created_at (created_at)
);
```

---

## Payment Integrations

### Stripe Setup

1. **Account & Keys**
   - Create account at https://stripe.com
   - Get Public & Secret keys from Dashboard
   - Enable 3D Secure

2. **Webhook Setup**
   - URL: `https://api.davetopup.com/webhooks/stripe`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
   - Save webhook secret

3. **Frontend Integration**
   - Use Stripe.js v3 for tokenization
   - Never send raw card data to backend
   - Handle 3D Secure via `clientSecret`

### PayPal Setup

1. **Account & Credentials**
   - Create account at https://developer.paypal.com
   - Get Client ID & Secret
   - Enable Signature-based webhooks

2. **Webhook Setup**
   - URL: `https://api.davetopup.com/webhooks/paypal`
   - Events: `CHECKOUT.ORDER.COMPLETED`, `PAYMENT.CAPTURE.COMPLETED`
   - Save Webhook ID

3. **Flow**
   - Backend creates order → returns approval URL
   - Frontend redirects to PayPal
   - PayPal redirects back → Backend captures payment

### Binance Pay Setup

1. **Account & Credentials**
   - Register at https://pay.binance.com
   - Get API Key & Secret
   - Set Merchant ID

2. **Webhook Setup**
   - URL: `https://api.davetopup.com/webhooks/binance`
   - Sign webhooks with SHA-256

3. **Flow**
   - Backend creates transaction → returns checkout URL
   - Frontend redirects to Binance
   - Binance redirects back → Webhook confirms payment

---

## API Documentation

### Endpoints

#### Create Order
```http
POST /api/orders
Content-Type: application/json

{
  "items": [
    {
      "id": "ff-diamonds-100",
      "name": "100 Diamonds",
      "game": "Free Fire",
      "price": 9.99,
      "quantity": 1
    }
  ],
  "email": "player@example.com",
  "playerUid": "123456789",
  "playerNickname": "PlayerName",
  "phone": "+1234567890"
}

Response 201:
{
  "success": true,
  "orderId": "ORD-abc123-1700000000",
  "amount": 10.79
}
```

#### Process Card Payment
```http
POST /api/payments/card
Content-Type: application/json

{
  "orderId": "ORD-abc123-1700000000",
  "stripeToken": "tok_visa",
  "amount": 10.79,
  "currency": "usd"
}

Response 200:
{
  "success": true,
  "status": "succeeded",
  "transactionId": "ch_1Iv5mkBs6QVGD3xt..."
}
```

#### Initiate PayPal
```http
POST /api/payments/paypal
Content-Type: application/json

{
  "orderId": "ORD-abc123-1700000000",
  "amount": 10.79
}

Response 200:
{
  "success": true,
  "approvalUrl": "https://www.sandbox.paypal.com/cgi-bin/webscr?..."
}
```

#### Redeem Voucher
```http
POST /api/payments/voucher
Content-Type: application/json

{
  "orderId": "ORD-abc123-1700000000",
  "voucherCode": "GIFT-CARD-12345"
}

Response 200:
{
  "success": true,
  "status": "completed",
  "message": "Voucher redeemed successfully"
}
```

#### Get Order Status
```http
GET /api/orders/{orderId}/status

Response 200:
{
  "success": true,
  "status": "payment_confirmed",
  "orderId": "ORD-abc123-1700000000"
}
```

---

## Webhook Signature Verification

### Stripe
```php
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent(
    $payload, $sig_header, $endpoint_secret
);
```

### PayPal
```php
$headers = [
    'Transmission-Id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'],
    'Transmission-Time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'],
    'Cert-Url' => $_SERVER['HTTP_PAYPAL_CERT_URL'],
    'Auth-Algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'],
    'Transmission-Sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'],
];
```

### Binance
```php
$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload, $binance_secret, true);
```

---

## Security Checklist

- [ ] HTTPS enabled with valid SSL certificate
- [ ] CORS configured for checkout domain only
- [ ] CSRF protection on all POST endpoints
- [ ] Rate limiting: 5 requests per minute per IP on checkout
- [ ] Input validation & sanitization on all fields
- [ ] PCI-DSS compliance (no card data storage)
- [ ] Webhook signature verification enabled
- [ ] Idempotency keys prevent duplicate charges
- [ ] Secrets stored in environment variables
- [ ] Database backups automated daily
- [ ] Error logging with masked sensitive data
- [ ] 3D Secure/SCA handling for card payments

---

## Testing

### Unit Tests
```bash
# Frontend
npm test -- --coverage

# Backend
./vendor/bin/phpunit --coverage-html
```

### Integration Tests
```bash
./vendor/bin/phpunit --testsuite=Integration
```

### Postman Collection

Import provided `DaveTopUp-Checkout-API.postman_collection.json`

Test endpoints:
1. Create order
2. Process card payment (test card: 4242 4242 4242 4242)
3. Initiate PayPal (sandbox)
4. Redeem voucher (test code: GIFT-CARD-TEST)
5. Check order status

---

## Deployment

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrated and secured
- [ ] SSL certificate installed
- [ ] Webhook URLs updated with production domain
- [ ] Payment gateway switched to live mode
- [ ] Rate limiting enabled
- [ ] Error monitoring (Sentry) configured
- [ ] Backups scheduled
- [ ] Load balancer configured
- [ ] CDN enabled for static assets
- [ ] Email sending tested
- [ ] Monitoring alerts set up

### Server Requirements
- Ubuntu 20.04+
- PHP 8.0+ with OpenSSL, cURL, JSON
- MySQL 5.7+
- Nginx or Apache
- 2GB+ RAM
- 10GB+ disk space

---

## Support

For issues or questions:
- **Email**: support@davetopup.com
- **Documentation**: https://docs.davetopup.com
- **Status Page**: https://status.davetopup.com

---

## License

Proprietary - Dave TopUp 2025
