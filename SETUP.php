<?php
/**
 * PHP Configuration and Setup Guide
 * Install these files in the root directory
 */

// Environment file template (.env)
$env_template = <<<'ENV'
# Application Settings
APP_NAME="Dave TopUp"
APP_URL="https://www.davetopup.com"
APP_ENV=production
DEBUG=false

# Database Configuration
DB_HOST=localhost
DB_USER=davetopup_user
DB_PASS=SECURE_PASSWORD_HERE
DB_NAME=davetopup_checkout
DB_PORT=3306

# Stripe Configuration
STRIPE_PUBLIC_KEY=pk_live_YOUR_PUBLIC_KEY
STRIPE_SECRET_KEY=sk_live_YOUR_SECRET_KEY
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# PayPal Configuration
PAYPAL_CLIENT_ID=YOUR_CLIENT_ID
PAYPAL_SECRET=YOUR_SECRET
PAYPAL_WEBHOOK_ID=YOUR_WEBHOOK_ID

# Binance Pay Configuration
BINANCE_API_KEY=YOUR_API_KEY
BINANCE_SECRET=YOUR_SECRET
BINANCE_MERCHANT_ID=YOUR_MERCHANT_ID

# Coinbase Configuration
COINBASE_API_KEY=YOUR_API_KEY

# Crypto Configuration
CRYPTO_SECRET_KEY=YOUR_SECRET
CRYPTO_WALLET_ADDRESS=YOUR_WALLET

# Email Configuration
MAIL_FROM=noreply@davetopup.com
MAIL_FROM_NAME=Dave TopUp
SUPPORT_EMAIL=support@davetopup.com

# Security
SESSION_TIMEOUT=3600
CSRF_TOKEN_LENGTH=32
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_DURATION=900

# Logging
LOG_DIR=/var/www/davetopup/logs
LOG_LEVEL=INFO
ENV;

echo "=== Dave TopUp Payment System Setup ===" . PHP_EOL;
echo "Please follow these steps:" . PHP_EOL . PHP_EOL;

echo "1. Create directory structure:" . PHP_EOL;
echo "   - /api/checkout.php" . PHP_EOL;
echo "   - /api/webhooks/handlers.php" . PHP_EOL;
echo "   - /config/database.php" . PHP_EOL;
echo "   - /config/payments.php" . PHP_EOL;
echo "   - /utils/security.php" . PHP_EOL;
echo "   - /utils/logger.php" . PHP_EOL;
echo "   - /logs/" . PHP_EOL;
echo "   - /database/schema.sql" . PHP_EOL;
echo "   - /public/checkout.html" . PHP_EOL;
echo "   - /public/success.html" . PHP_EOL;
echo "   - /public/failed.html" . PHP_EOL;
echo "   - /public/checkout.js" . PHP_EOL . PHP_EOL;

echo "2. Configure database:" . PHP_EOL;
echo "   - Create MySQL database: davetopup_checkout" . PHP_EOL;
echo "   - Create user: davetopup_user" . PHP_EOL;
echo "   - Import schema.sql" . PHP_EOL;
echo "   - Update /config/database.php with credentials" . PHP_EOL . PHP_EOL;

echo "3. Configure payment gateways:" . PHP_EOL;
echo "   - Sign up for Stripe, PayPal, Binance Pay, Coinbase" . PHP_EOL;
echo "   - Get API keys and webhooks secrets" . PHP_EOL;
echo "   - Update /config/payments.php" . PHP_EOL;
echo "   - Add webhook URLs to each provider" . PHP_EOL . PHP_EOL;

echo "4. Configure webhooks in payment providers:" . PHP_EOL;
echo "   Stripe: https://www.davetopup.com/api/webhooks/stripe.php" . PHP_EOL;
echo "   PayPal: https://www.davetopup.com/api/webhooks/paypal.php" . PHP_EOL;
echo "   Binance: https://www.davetopup.com/api/webhooks/binance.php" . PHP_EOL . PHP_EOL;

echo "5. Install Composer dependencies:" . PHP_EOL;
echo "   composer require stripe/stripe-php" . PHP_EOL . PHP_EOL;

echo "6. File permissions:" . PHP_EOL;
echo "   chmod 755 /api" . PHP_EOL;
echo "   chmod 755 /config" . PHP_EOL;
echo "   chmod 755 /utils" . PHP_EOL;
echo "   chmod 755 /logs" . PHP_EOL;
echo "   chmod 644 /config/*.php" . PHP_EOL . PHP_EOL;

echo "7. SSL Certificate:" . PHP_EOL;
echo "   - Ensure HTTPS is enabled on your server" . PHP_EOL;
echo "   - Use Let's Encrypt (free SSL)" . PHP_EOL . PHP_EOL;

echo ".env file template:" . PHP_EOL;
echo "---" . PHP_EOL;
echo $env_template;
?>
