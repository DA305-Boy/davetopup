### Track Order

**GET** `/api/track-order.php?orderId=ORD-...`

Returns order/payment status for the given order ID.

### Google Pay Debit Card Support

Google Pay payments now record card type (debit/credit) in the `transactions.card_funding` column.
### Black Friday Redeem

**POST** `/api/redeem-black-friday.php`

Redeems all orders placed on 2025-11-28 (Black Friday). Returns summary of updated orders.

**Example:**
```
curl -X POST http://localhost:8080/api/redeem-black-friday.php
```
# Dave TopUp - Secure Checkout System

## Store Description (100-150 Words)

**Welcome to Dave TopUp - Your Trusted Game Top-Up Store**

Dave TopUp is a fast, secure, and reliable platform for gaming credits and top-ups. We support popular games like Free Fire, PUBG Mobile, Mobile Legends, Call of Duty Mobile, and more. With instant delivery powered by verified APIs, your game credits appear in your account within minutes, not hours. 

Our secure payment system accepts multiple methods: Visa, Mastercard, PayPal, Binance Pay, and 15+ alternative payment options. All transactions are encrypted with SSL technology and comply with PCI-DSS security standards—your card data never touches our servers.

Experience 24/7 customer support, transparent pricing with no hidden fees, and competitive rates. Join thousands of happy gamers who trust Dave TopUp for quick, hassle-free gaming purchases. Shop now and play instantly!

---

## System Architecture

### Frontend Stack
- **HTML5** - Semantic markup for better accessibility
- **CSS3** - Modern responsive design with Flexbox/Grid
- **TypeScript** - Type-safe form validation and API handling
- **Vanilla JavaScript** - No framework dependencies for faster load

### Backend Stack
- **PHP 7.4+** - RESTful API endpoints
- **MySQL/MariaDB** - Transactional data storage
- **Composer** - PHP package management

### Payment Gateways
1. **Stripe** - Credit/Debit cards, Apple Pay, Google Pay
2. **PayPal** - Digital wallet integration
3. **Binance Pay** - Cryptocurrency payments
4. **Coinbase Commerce** - Additional crypto options
5. **Skrill/Flutterwave** - Regional payment methods

---

## Installation & Setup

### Prerequisites
- PHP 7.4+ with OpenSSL, cURL, JSON extensions
- MySQL 5.7+ or MariaDB 10.2+
- Composer
- HTTPS/SSL certificate
- Nginx or Apache web server

### Step-by-Step Installation

1. **Clone repository to web root:**
   ```bash
   cd /var/www/davetopup
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Create database:**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

4. **Configure environment variables:**
   - Copy `.env.example` to `.env`
   - Update database credentials
   - Add payment gateway API keys

5. **Set file permissions:**
   ```bash
   chmod 755 api config utils logs
   chmod 644 config/*.php
   ```

6. **Configure HTTPS (Let's Encrypt):**
   ```bash
   certbot certonly --webroot -w /var/www/davetopup -d www.davetopup.com
   ```

7. **Test checkout system:**
   - Navigate to `https://www.davetopup.com/public/checkout.html`
   - Test payment flow in sandbox mode first

---

## API Endpoints

### POST `/api/checkout.php`
**Process payment and create order**

Request:
```json
{
  "email": "user@example.com",
  "playerId": "123456789",
  "country": "US",
  "amount": 9.99,
  "currency": "USD",
  "paymentMethod": "stripe",
  "stripePaymentMethodId": "pm_1234567890",
  "cartData": {
    "itemName": "Free Fire Diamonds",
    "quantity": 1,
    "price": 9.99,
    "uid": "123456"
  }
}
```

Response (Success):
```json
{
  "success": true,
  "orderId": "ORD-abc123-1700000000",
  "message": "Payment successful"
}
```

### POST `/api/webhooks/stripe.php`
**Handle Stripe webhook events**

- Listens for: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`

### POST `/api/webhooks/paypal.php`
**Handle PayPal webhook events**

- Listens for: `CHECKOUT.ORDER.COMPLETED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`

### POST `/api/webhooks/binance.php`
**Handle Binance Pay webhook events**

- Listens for payment status updates

---

## Database Schema

### Transactions Table
```sql
CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id VARCHAR(100) UNIQUE,
  transaction_id VARCHAR(255),
  email VARCHAR(255),
  player_id VARCHAR(100),
  country CHAR(2),
  payment_method VARCHAR(50),
   card_funding VARCHAR(20),
  amount DECIMAL(10, 2),
  currency CHAR(3),
  status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Security Best Practices

### 1. Input Validation
- Email validation with RFC 5322
- Card number validation using Luhn algorithm
- Country code validation against ISO 3166-1 alpha-2
- Amount range validation (0.50 - 10000 USD)

### 2. SQL Injection Prevention
- Use prepared statements exclusively
- Parameterized queries with type binding
- Input sanitization with htmlspecialchars()

### 3. HTTPS/TLS
- Enforce SSL/TLS for all requests
- Add HSTS header: `Strict-Transport-Security: max-age=31536000`
- Redirect HTTP to HTTPS

### 4. PCI-DSS Compliance
- **Never store** raw card data on server
- Use tokenized payments (Stripe, PayPal)
- Implement 3D Secure verification
- Regular security audits

### 5. CSRF Protection
- Generate unique tokens per session
- Validate tokens on all state-changing operations
- Use `SameSite=Strict` cookie flag

### 6. Rate Limiting
- Limit checkout attempts: 5 per 5 minutes
- Lock account after 5 failed login attempts
- Implement progressive delays on failures

### 7. Logging & Monitoring
- Log all payment events (success/failure)
- Monitor for suspicious patterns
- Alert on high failure rates
- Store logs separately from web root

---

## Payment Gateway Integration

### Stripe Setup

1. **Create Stripe account at https://stripe.com**
2. **Get API keys:**
   - Public Key: `pk_live_...`
   - Secret Key: `sk_live_...`
3. **Create webhook endpoint:**
   - URL: `https://www.davetopup.com/api/webhooks/stripe.php`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
   - Get webhook secret: `whsec_...`
4. **Add to config/payments.php:**
   ```php
   define('STRIPE_PUBLIC_KEY', 'pk_live_...');
   define('STRIPE_SECRET_KEY', 'sk_live_...');
   define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
   ```

### PayPal Setup

1. **Create PayPal Developer account at https://developer.paypal.com**
2. **Get credentials:**
   - Client ID
   - Secret
3. **Create webhook endpoint:**
   - URL: `https://www.davetopup.com/api/webhooks/paypal.php`
   - Get Webhook ID
4. **Add to config/payments.php**

### Binance Pay Setup

1. **Register at https://pay.binance.com**
2. **Get merchant credentials:**
   - API Key
   - Secret Key
   - Merchant ID
3. **Configure webhook URL**

---

## Testing Checklist

- [ ] Frontend form validation works
- [ ] All payment methods display correctly
- [ ] Stripe test card accepted: 4242 4242 4242 4242
- [ ] PayPal sandbox testing successful
- [ ] SSL certificate valid and active
- [ ] Database connections working
- [ ] Email notifications sending
- [ ] Webhook endpoints receiving events
- [ ] Order status updates correctly
- [ ] Error messages display properly
- [ ] Responsive design works on mobile
- [ ] CORS headers configured correctly

---

## Troubleshooting

### "Database connection failed"
- Check credentials in `/config/database.php`
- Verify MySQL service is running
- Ensure user has database privileges

### "Stripe API error"
- Verify API keys in `/config/payments.php`
- Check webhook secret matches
- Test with Stripe test cards first

### "Payment status not updating"
- Check webhook URLs in payment provider dashboards
- Verify webhook secrets are correct
- Check server logs for webhook responses

### "SSL certificate error"
- Ensure HTTPS is enabled
- Install valid SSL certificate
- Clear browser cache
- Test with: `https://www.ssllabs.com/ssltest/`

---

## Frontend Features

### Checkout Page (`public/checkout.html`)
- Order summary with item details
- User information form (email, player ID, country)
- 9 payment method options
- Promo code support
- Real-time validation
- Responsive design (mobile-first)
- Security badges and lock icon

### Success Page (`public/success.html`)
- Order confirmation display
- Auto-redirect after 10 seconds
- Order details fetched from backend
- Email confirmation notice
- Next steps guidance

### Failed Payment Page (`public/failed.html`)
- Clear error explanation
- Common failure reasons
- Retry payment button
- Contact support link

---

## Performance Optimization

1. **Frontend:**
   - Minify JavaScript and CSS
   - Use lazy loading for images
   - Cache static assets (1 year for versioned)
   - Compress responses with gzip

2. **Backend:**
   - Use prepared statements (already cached)
   - Implement database indexes on frequently queried fields
   - Cache payment gateway responses
   - Use CDN for static assets

3. **Database:**
   - Create indexes on: `order_id`, `email`, `status`, `created_at`
   - Archive old transactions regularly
   - Monitor query performance

---

## Support & Maintenance

### Regular Tasks
- Monitor error logs daily
- Test payment methods weekly
- Backup database daily
- Update dependencies monthly
- Security audit quarterly

### Contact & Support
- Email: support@davetopup.com
- Support Hours: 24/7
- Response Time: < 1 hour

---

## License & Terms

This checkout system is built for Dave TopUp and must not be redistributed without permission. All code is proprietary and protected.

---

## Version History

**v1.0.0** (2025-11-27)
- Initial release
- 9 payment method integration
- Secure webhook handlers
- Database schema and security utilities
- Responsive frontend with TypeScript backend
