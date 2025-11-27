# DaveTopUp Checkout System - Quick Start Guide

## 📋 Prerequisites

### System Requirements
- **PHP:** 8.0+ (8.1 recommended)
- **Node.js:** 16+ 
- **MySQL:** 5.7+ (8.0 recommended)
- **Composer:** Latest version
- **npm/yarn:** Latest version
- **Git:** For version control

### Required Accounts
- [ ] Stripe account (https://stripe.com)
- [ ] PayPal developer account (https://developer.paypal.com)
- [ ] Binance Pay account (https://pay.binance.com)
- [ ] SendGrid or email provider account
- [ ] Top-up provider account (game currency API)

---

## 🚀 Installation

### 1. Backend Setup (Laravel)

```bash
# Navigate to backend directory
cd backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Create database
mysql -u root -p
CREATE DATABASE davetopup;
CREATE USER 'davetopup_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON davetopup.* TO 'davetopup_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php artisan migrate

# Create admin user (interactive)
php artisan tinker
> \App\Models\User::create(['name' => 'Admin', 'email' => 'admin@davetopup.com', 'password' => bcrypt('secure_password')])

# Start dev server
php artisan serve
# Server runs at http://localhost:8000
```

### 2. Frontend Setup (React)

```bash
# Navigate to frontend directory
cd frontend

# Install dependencies
npm install

# Create environment file
cp .env.example .env.local

# Update .env.local with API URL and Stripe key
# REACT_APP_API_BASE=http://localhost:8000
# REACT_APP_STRIPE_PUBLIC_KEY=pk_test_xxx

# Start development server
npm start
# Runs at http://localhost:3000
```

---

## 🔧 Configuration

### Step 1: Update Backend .env File

Edit `backend/.env` with your credentials:

```env
# Database
DB_HOST=127.0.0.1
DB_DATABASE=davetopup
DB_USERNAME=davetopup_user
DB_PASSWORD=your_secure_password

# Stripe (get from dashboard.stripe.com)
STRIPE_PUBLIC_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# PayPal (get from developer.paypal.com)
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=xxx
PAYPAL_SECRET=xxx

# Binance Pay (get from pay.binance.com)
BINANCE_API_KEY=xxx
BINANCE_SECRET_KEY=xxx
BINANCE_MERCHANT_ID=xxx

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxx

# Top-up Provider
TOPUP_PROVIDER_URL=https://api.provider.com
TOPUP_PROVIDER_API_KEY=xxx
```

### Step 2: Update Frontend .env.local

```env
REACT_APP_API_BASE=http://localhost:8000
REACT_APP_STRIPE_PUBLIC_KEY=pk_test_xxx
REACT_APP_ENVIRONMENT=sandbox
```

---

## 🧪 Testing

### Testing Card Payments (Stripe)

Use these test card numbers in sandbox mode:

| Card Number | Scenario | CVC | Exp |
|---|---|---|---|
| 4242 4242 4242 4242 | Success | Any | Any |
| 4000 0025 0000 3155 | 3D Secure Required | Any | Any |
| 4000 0000 0000 0002 | Decline | Any | Any |
| 5555 5555 5555 4444 | Mastercard | Any | Any |

### Using Postman

1. Import `DaveTopUp-Checkout-API.postman_collection.json`
2. Set variables:
   - `base_url`: http://localhost:8000
   - `admin_token`: Your admin bearer token
3. Test endpoints in order:
   - Create Order → Process Payment → Check Status

### Testing with cURL

```bash
# Create order
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{"id": "ff-100", "name": "100 Diamonds", "game": "Free Fire", "price": 9.99}],
    "email": "test@example.com",
    "playerUid": "123456789",
    "playerNickname": "TestPlayer"
  }'

# Response:
# {
#   "success": true,
#   "orderId": "ORD-abc123-1700000000",
#   "amount": 10.79
# }

# Process payment with Stripe token
curl -X POST http://localhost:8000/api/payments/card \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "ORD-abc123-1700000000",
    "stripeToken": "tok_visa",
    "amount": 10.79,
    "currency": "usd"
  }'
```

---

## 💳 Payment Gateway Setup

### Stripe Setup

1. Go to https://dashboard.stripe.com/apikeys
2. Copy **Publishable key** (pk_test_...) to frontend
3. Copy **Secret key** (sk_test_...) to backend
4. Enable 3D Secure (Settings → Card authentication)
5. Setup webhook:
   - Go to Developers → Webhooks
   - Add endpoint: `https://api.davetopup.com/webhooks/stripe`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
   - Copy webhook secret to `.env`

### PayPal Setup

1. Go to https://developer.paypal.com/dashboard
2. Create app (Sandbox for testing)
3. Copy **Client ID** and **App Secret**
4. Enable Signature webhooks:
   - Settings → Notification preferences
   - Add webhook listener
   - URL: `https://api.davetopup.com/webhooks/paypal`
5. Add webhook events:
   - CHECKOUT.ORDER.COMPLETED
   - PAYMENT.CAPTURE.COMPLETED

### Binance Pay Setup

1. Login to https://pay.binance.com
2. Go to Merchant Settings
3. Copy **API Key**, **Secret Key**, **Merchant ID**
4. Add webhook URL:
   - `https://api.davetopup.com/webhooks/binance`
5. Download merchant certificate for signing

---

## 🌐 Deployment

### Deploy Backend (Laravel)

```bash
# Production server setup
ssh user@davetopup.com

# Clone repository
git clone https://github.com/davetopup/checkout.git
cd checkout/backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Setup environment
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --force
php artisan db:seed

# Setup web server (Nginx example)
sudo cp nginx.conf /etc/nginx/sites-available/davetopup
sudo ln -s /etc/nginx/sites-available/davetopup /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Setup SSL with Let's Encrypt
sudo certbot certonly --nginx -d api.davetopup.com

# Start queue worker (for async delivery)
php artisan queue:work --daemon

# Setup cron for schedule
crontab -e
# * * * * * /usr/bin/php /path/to/artisan schedule:run >> /dev/null 2>&1
```

### Deploy Frontend (React)

```bash
# Build optimized production bundle
npm run build

# Deploy to CDN or hosting
aws s3 sync build/ s3://davetopup-frontend/
# Or use Vercel: vercel deploy
```

---

## 📊 Admin Dashboard

### Accessing Admin Routes

All admin routes require Sanctum authentication token:

```bash
# Login and get token
curl -X POST http://localhost:8000/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@davetopup.com",
    "password": "your_password"
  }'

# Use token in subsequent requests
curl -X GET http://localhost:8000/api/admin/orders \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Admin Functions

1. **List Orders**
   - Filter by status, date range, payment method
   - View customer details and transaction info

2. **Process Refunds**
   - Full or partial refunds
   - Calls payment provider API
   - Updates transaction status

3. **Manual Delivery**
   - Verify orders pending delivery
   - Retry failed deliveries
   - View delivery logs

4. **Voucher Management**
   - Create new vouchers
   - View usage statistics
   - Manually approve/reject pending vouchers

---

## 🔍 Monitoring and Logs

### Application Logs

```bash
# View Laravel logs
tail -f backend/storage/logs/laravel.log

# View queue jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Database Backups

```bash
# Automated daily backup
mysqldump -u davetopup_user -p davetopup > backup_$(date +%Y%m%d).sql

# Setup cron job
crontab -e
# 0 2 * * * mysqldump -u davetopup_user -pPASSWORD davetopup > /backups/daily_$(date +\%Y\%m\%d).sql
```

### Webhook Logs

Check webhook processing:

```bash
# View webhook logs in database
php artisan tinker
> \App\Models\WebhookLog::where('provider', 'stripe')->latest()->first()
```

---

## 🔐 Security Checklist

- [ ] HTTPS enabled on all domains
- [ ] SSL certificate valid (Let's Encrypt)
- [ ] Database password strong (32+ chars, random)
- [ ] API keys stored in environment variables
- [ ] CORS configured for checkout domain only
- [ ] Rate limiting enabled (60 requests/min per IP)
- [ ] Webhook signatures verified
- [ ] No card data logged or stored locally
- [ ] Error messages don't expose sensitive data
- [ ] Database backups encrypted and stored safely
- [ ] Admin panel IP whitelisted (optional)
- [ ] 2FA enabled for admin accounts

---

## ❓ Troubleshooting

### Payment processing fails

```bash
# Check Stripe webhook secret is correct
grep STRIPE_WEBHOOK_SECRET backend/.env

# Verify endpoint signature in Stripe dashboard
# Look for failed deliveries in webhook logs
```

### Database connection error

```bash
# Verify MySQL is running
sudo systemctl status mysql

# Check database credentials
php artisan tinker
> DB::connection()->getPdo()

# Run migrations if needed
php artisan migrate
```

### Email not sending

```bash
# Test email configuration
php artisan tinker
> \Illuminate\Support\Facades\Mail::raw('Test', function($m) { $m->to('test@example.com'); })

# Check SendGrid API key in .env
grep MAIL_PASSWORD backend/.env
```

### Queue jobs not processing

```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Start queue worker
php artisan queue:work

# Or use supervisor for persistent daemon
sudo cp supervisor.conf /etc/supervisor/conf.d/davetopup.conf
sudo supervisorctl reload
```

---

## 📞 Support

- **Documentation**: https://docs.davetopup.com
- **Email**: support@davetopup.com
- **Status**: https://status.davetopup.com
- **Issues**: GitHub Issues

---

## 🎯 Next Steps

1. ✅ Complete installation
2. ✅ Test with sandbox credentials
3. ✅ Deploy to staging environment
4. ✅ Switch to production credentials
5. ✅ Enable monitoring and alerts
6. ✅ Train support team
7. ✅ Go live!

---

**Last Updated:** January 2024  
**Version:** 1.0.0  
**License:** Proprietary - Dave TopUp
