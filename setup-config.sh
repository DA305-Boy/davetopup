#!/bin/bash

# DaveTopUp Checkout System - Setup Configuration Script
# This script helps configure the payment gateways and environment

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     DaveTopUp Checkout System - Configuration Setup          ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "backend/.env.example" ]; then
    echo -e "${RED}✗ Error: Run this script from the project root directory${NC}"
    exit 1
fi

echo -e "${BLUE}Step 1: Environment Configuration${NC}"
echo "=================================="
read -p "Select environment (sandbox/production): " ENV
if [ "$ENV" != "sandbox" ] && [ "$ENV" != "production" ]; then
    echo -e "${RED}Invalid environment. Use 'sandbox' or 'production'${NC}"
    exit 1
fi
echo ""

# Database Configuration
echo -e "${BLUE}Step 2: Database Configuration${NC}"
echo "==============================="
read -p "Database host [127.0.0.1]: " DB_HOST
DB_HOST=${DB_HOST:-127.0.0.1}
read -p "Database port [3306]: " DB_PORT
DB_PORT=${DB_PORT:-3306}
read -p "Database name [davetopup]: " DB_NAME
DB_NAME=${DB_NAME:-davetopup}
read -p "Database user [davetopup_user]: " DB_USER
DB_USER=${DB_USER:-davetopup_user}
read -sp "Database password: " DB_PASS
echo ""
echo ""

# Stripe Configuration
echo -e "${BLUE}Step 3: Stripe Configuration${NC}"
echo "============================="

if [ "$ENV" = "sandbox" ]; then
    echo "Get test keys from: https://dashboard.stripe.com/test/apikeys"
    read -p "Stripe Public Key (pk_test_...): " STRIPE_PUB
    read -sp "Stripe Secret Key (sk_test_...): " STRIPE_SEC
    echo ""
    read -sp "Stripe Webhook Secret (whsec_...): " STRIPE_WEBHOOK
    echo ""
else
    echo "⚠️  Production keys required"
    echo "Get live keys from: https://dashboard.stripe.com/apikeys"
    read -p "Stripe Public Key (pk_live_...): " STRIPE_PUB
    read -sp "Stripe Secret Key (sk_live_...): " STRIPE_SEC
    echo ""
    read -sp "Stripe Webhook Secret (whsec_...): " STRIPE_WEBHOOK
    echo ""
fi
echo ""

# PayPal Configuration
echo -e "${BLUE}Step 4: PayPal Configuration${NC}"
echo "============================="

if [ "$ENV" = "sandbox" ]; then
    echo "Get sandbox credentials from: https://developer.paypal.com/dashboard/"
    read -p "PayPal Client ID: " PAYPAL_ID
    read -sp "PayPal Secret: " PAYPAL_SECRET
    echo ""
    read -p "PayPal Webhook ID (from Sandbox settings): " PAYPAL_WEBHOOK
    echo ""
else
    echo "Get live credentials from: https://developer.paypal.com/dashboard/"
    read -p "PayPal Client ID: " PAYPAL_ID
    read -sp "PayPal Secret: " PAYPAL_SECRET
    echo ""
    read -p "PayPal Webhook ID: " PAYPAL_WEBHOOK
    echo ""
fi
echo ""

# Binance Configuration
echo -e "${BLUE}Step 5: Binance Pay Configuration (Optional)${NC}"
echo "=============================================="
read -p "Binance API Key (leave blank to skip): " BINANCE_KEY
if [ ! -z "$BINANCE_KEY" ]; then
    read -sp "Binance Secret Key: " BINANCE_SECRET
    echo ""
    read -p "Binance Merchant ID: " BINANCE_MERCHANT
fi
echo ""

# Email Configuration
echo -e "${BLUE}Step 6: Email Configuration${NC}"
echo "============================"
read -p "Email service (sendgrid/smtp): " EMAIL_SERVICE
if [ "$EMAIL_SERVICE" = "sendgrid" ]; then
    read -sp "SendGrid API Key: " SENDGRID_KEY
    echo ""
    MAIL_HOST="smtp.sendgrid.net"
    MAIL_USER="apikey"
    MAIL_PASS="$SENDGRID_KEY"
else
    read -p "SMTP Host: " MAIL_HOST
    read -p "SMTP Port [587]: " MAIL_PORT
    MAIL_PORT=${MAIL_PORT:-587}
    read -p "SMTP Username: " MAIL_USER
    read -sp "SMTP Password: " MAIL_PASS
    echo ""
fi
read -p "From Email Address [noreply@davetopup.com]: " FROM_EMAIL
FROM_EMAIL=${FROM_EMAIL:-noreply@davetopup.com}
echo ""

# Top-up Provider Configuration
echo -e "${BLUE}Step 7: Top-up Provider Configuration${NC}"
echo "======================================"
read -p "Top-up Provider API URL: " TOPUP_URL
read -sp "Top-up Provider API Key: " TOPUP_KEY
echo ""
read -p "Top-up Provider Merchant ID: " TOPUP_MERCHANT
read -sp "Top-up Provider Webhook Secret: " TOPUP_WEBHOOK
echo ""
echo ""

# Generate Configuration
echo -e "${BLUE}Step 8: Generating Configuration${NC}"
echo "================================"

# Create backend .env file
cat > backend/.env << EOF
APP_NAME=DaveTopUp
APP_ENV=$ENV
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=$([ "$ENV" = "production" ] && echo "false" || echo "true")
APP_URL=https://davetopup.com

DB_CONNECTION=mysql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

CACHE_DRIVER=redis
QUEUE_CONNECTION=database

STRIPE_PUBLIC_KEY=$STRIPE_PUB
STRIPE_SECRET_KEY=$STRIPE_SEC
STRIPE_WEBHOOK_SECRET=$STRIPE_WEBHOOK

PAYPAL_MODE=$([ "$ENV" = "sandbox" ] && echo "sandbox" || echo "live")
PAYPAL_CLIENT_ID=$PAYPAL_ID
PAYPAL_SECRET=$PAYPAL_SECRET
PAYPAL_WEBHOOK_ID=$PAYPAL_WEBHOOK

BINANCE_API_KEY=${BINANCE_KEY:-your_binance_api_key}
BINANCE_SECRET_KEY=${BINANCE_SECRET:-your_binance_secret_key}
BINANCE_MERCHANT_ID=${BINANCE_MERCHANT:-your_merchant_id}

MAIL_MAILER=smtp
MAIL_HOST=$MAIL_HOST
MAIL_PORT=$MAIL_PORT
MAIL_USERNAME=$MAIL_USER
MAIL_PASSWORD=$MAIL_PASS
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=$FROM_EMAIL
MAIL_FROM_NAME="Dave TopUp"

TOPUP_PROVIDER_URL=$TOPUP_URL
TOPUP_PROVIDER_API_KEY=$TOPUP_KEY
TOPUP_PROVIDER_MERCHANT_ID=$TOPUP_MERCHANT
TOPUP_PROVIDER_WEBHOOK_SECRET=$TOPUP_WEBHOOK

LOG_CHANNEL=stack
LOG_LEVEL=$([ "$ENV" = "production" ] && echo "warning" || echo "debug")

FEATURE_3D_SECURE=true
FEATURE_VOUCHER_AUTO_APPROVAL=true
FEATURE_ASYNC_DELIVERY=true
FEATURE_WEBHOOK_RETRY=true
EOF

echo -e "${GREEN}✓ Created backend/.env${NC}"

# Create frontend .env.local file
mkdir -p frontend
cat > frontend/.env.local << EOF
REACT_APP_API_BASE=$([ "$ENV" = "production" ] && echo "https://api.davetopup.com" || echo "http://localhost:8000")
REACT_APP_STRIPE_PUBLIC_KEY=$STRIPE_PUB
REACT_APP_ENVIRONMENT=$ENV
EOF

echo -e "${GREEN}✓ Created frontend/.env.local${NC}"
echo ""

# Next steps
echo -e "${BLUE}Step 9: Next Steps${NC}"
echo "=================="
echo ""
echo -e "${YELLOW}Backend Setup:${NC}"
echo "  1. cd backend"
echo "  2. composer install"
echo "  3. php artisan migrate"
echo "  4. php artisan serve"
echo ""
echo -e "${YELLOW}Frontend Setup:${NC}"
echo "  1. cd frontend"
echo "  2. npm install"
echo "  3. npm start"
echo ""
echo -e "${YELLOW}Test with Stripe:${NC}"
echo "  Card: 4242 4242 4242 4242"
echo "  Date: Any future date"
echo "  CVC: Any 3 digits"
echo ""
echo -e "${YELLOW}Configure Webhooks:${NC}"
echo "  Stripe: https://dashboard.stripe.com/webhooks"
echo "    - Add: https://api.davetopup.com/webhooks/stripe"
echo "    - Events: payment_intent.succeeded, payment_intent.payment_failed"
echo ""
echo "  PayPal: https://developer.paypal.com/webhooks"
echo "    - Add: https://api.davetopup.com/webhooks/paypal"
echo "    - Events: CHECKOUT.ORDER.COMPLETED, PAYMENT.CAPTURE.COMPLETED"
echo ""
echo "  Binance: https://pay.binance.com/webhooks"
echo "    - Add: https://api.davetopup.com/webhooks/binance"
echo ""
echo -e "${GREEN}Configuration complete!${NC}"
echo ""
echo "For more information, see:"
echo "  - QUICKSTART.md - Quick start guide"
echo "  - PRODUCTION_SETUP.md - Deployment guide"
echo "  - TESTING_GUIDE.md - Testing procedures"
echo ""
