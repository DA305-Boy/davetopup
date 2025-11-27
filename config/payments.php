<?php
/**
 * Payment Gateway Configuration
 * API keys and endpoints for all payment processors
 */

// ===== STRIPE =====
define('STRIPE_PUBLIC_KEY', 'pk_live_YOUR_PUBLIC_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_live_YOUR_SECRET_KEY_HERE');
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET_HERE');
define('STRIPE_SDK_PATH', __DIR__ . '/../vendor/stripe/stripe-php');

// ===== PAYPAL =====
define('PAYPAL_CLIENT_ID', 'YOUR_PAYPAL_CLIENT_ID');
define('PAYPAL_SECRET', 'YOUR_PAYPAL_SECRET');
define('PAYPAL_WEBHOOK_ID', 'YOUR_PAYPAL_WEBHOOK_ID');
define('PAYPAL_API_URL', 'https://api.sandbox.paypal.com'); // Change to https://api.paypal.com for production

// ===== BINANCE PAY =====
define('BINANCE_API_KEY', 'YOUR_BINANCE_API_KEY');
define('BINANCE_SECRET', 'YOUR_BINANCE_SECRET');
define('BINANCE_MERCHANT_ID', 'YOUR_MERCHANT_ID');
define('BINANCE_CHECKOUT_URL', 'https://pay.binance.com/web/checkout');

// ===== COINBASE =====
define('COINBASE_API_KEY', 'YOUR_COINBASE_API_KEY');
define('COINBASE_API_URL', 'https://api.commerce.coinbase.com');

// ===== SKRILL =====
define('SKRILL_API_KEY', 'YOUR_SKRILL_API_KEY');
define('SKRILL_SECRET', 'YOUR_SKRILL_SECRET');
define('SKRILL_API_URL', 'https://pay.skrill.com/rest/pay');

// ===== FLUTTERWAVE =====
define('FLUTTERWAVE_PUBLIC_KEY', 'YOUR_FLUTTERWAVE_PUBLIC_KEY');
define('FLUTTERWAVE_SECRET_KEY', 'YOUR_FLUTTERWAVE_SECRET_KEY');
define('FLUTTERWAVE_API_URL', 'https://api.flutterwave.com/v3');

// ===== CRYPTO PAYMENT =====
define('CRYPTO_SECRET_KEY', 'YOUR_CRYPTO_SECRET_KEY');
define('CRYPTO_WALLET_ADDRESS', 'YOUR_ETHEREUM_WALLET_ADDRESS');
define('SUPPORTED_CRYPTO_CURRENCIES', ['BTC', 'ETH', 'USDT', 'USDC']);

// ===== SECURITY =====
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// ===== EMAIL =====
define('MAIL_FROM', 'noreply@davetopup.com');
define('MAIL_FROM_NAME', 'Dave TopUp');
define('SUPPORT_EMAIL', 'support@davetopup.com');

// ===== SITE =====
define('SITE_URL', 'https://www.davetopup.com');
define('API_URL', 'https://www.davetopup.com/api');
define('SHOP_NAME', 'Dave TopUp');

// ===== LOGGING =====
define('LOG_DIR', __DIR__ . '/../logs');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
?>
