# DaveTopUp Checkout System - Setup Configuration Script (Windows PowerShell)
# This script helps configure the payment gateways and environment

param(
    [switch]$SkipInteractive = $false
)

Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║     DaveTopUp Checkout System - Configuration Setup          ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Check if we're in the right directory
if (-not (Test-Path "backend\.env.example")) {
    Write-Host "✗ Error: Run this script from the project root directory" -ForegroundColor Red
    exit 1
}

# Helper function for secure password input
function Read-SecureString {
    param([string]$Prompt)
    Write-Host $Prompt -NoNewline -ForegroundColor Yellow
    $secureString = Read-Host -AsSecureString
    $BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureString)
    [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)
}

# Step 1: Environment Selection
Write-Host "Step 1: Environment Configuration" -ForegroundColor Blue
Write-Host "==================================" -ForegroundColor Blue
$env_choice = Read-Host "Select environment (sandbox/production) [sandbox]"
if ([string]::IsNullOrEmpty($env_choice)) { $env_choice = "sandbox" }
if ($env_choice -ne "sandbox" -and $env_choice -ne "production") {
    Write-Host "Invalid environment. Use 'sandbox' or 'production'" -ForegroundColor Red
    exit 1
}
Write-Host ""

# Step 2: Database Configuration
Write-Host "Step 2: Database Configuration" -ForegroundColor Blue
Write-Host "===============================" -ForegroundColor Blue
$db_host = Read-Host "Database host [127.0.0.1]"
if ([string]::IsNullOrEmpty($db_host)) { $db_host = "127.0.0.1" }

$db_port = Read-Host "Database port [3306]"
if ([string]::IsNullOrEmpty($db_port)) { $db_port = "3306" }

$db_name = Read-Host "Database name [davetopup]"
if ([string]::IsNullOrEmpty($db_name)) { $db_name = "davetopup" }

$db_user = Read-Host "Database user [davetopup_user]"
if ([string]::IsNullOrEmpty($db_user)) { $db_user = "davetopup_user" }

$db_pass = Read-SecureString "Database password: "
Write-Host ""

# Step 3: Stripe Configuration
Write-Host "Step 3: Stripe Configuration" -ForegroundColor Blue
Write-Host "============================" -ForegroundColor Blue

if ($env_choice -eq "sandbox") {
    Write-Host "Get test keys from: https://dashboard.stripe.com/test/apikeys" -ForegroundColor Gray
    $stripe_pub = Read-Host "Stripe Public Key (pk_test_...)"
    $stripe_sec = Read-SecureString "Stripe Secret Key (sk_test_...): "
    $stripe_webhook = Read-SecureString "Stripe Webhook Secret (whsec_...): "
} else {
    Write-Host "⚠️  Production keys required" -ForegroundColor Yellow
    Write-Host "Get live keys from: https://dashboard.stripe.com/apikeys" -ForegroundColor Gray
    $stripe_pub = Read-Host "Stripe Public Key (pk_live_...)"
    $stripe_sec = Read-SecureString "Stripe Secret Key (sk_live_...): "
    $stripe_webhook = Read-SecureString "Stripe Webhook Secret (whsec_...): "
}
Write-Host ""

# Step 4: PayPal Configuration
Write-Host "Step 4: PayPal Configuration" -ForegroundColor Blue
Write-Host "============================" -ForegroundColor Blue

if ($env_choice -eq "sandbox") {
    Write-Host "Get sandbox credentials from: https://developer.paypal.com/dashboard/" -ForegroundColor Gray
    $paypal_id = Read-Host "PayPal Client ID"
    $paypal_secret = Read-SecureString "PayPal Secret: "
    $paypal_webhook = Read-Host "PayPal Webhook ID (from Sandbox settings)"
} else {
    Write-Host "Get live credentials from: https://developer.paypal.com/dashboard/" -ForegroundColor Gray
    $paypal_id = Read-Host "PayPal Client ID"
    $paypal_secret = Read-SecureString "PayPal Secret: "
    $paypal_webhook = Read-Host "PayPal Webhook ID"
}
Write-Host ""

# Step 5: Binance Configuration
Write-Host "Step 5: Binance Pay Configuration (Optional)" -ForegroundColor Blue
Write-Host "============================================" -ForegroundColor Blue
$binance_key = Read-Host "Binance API Key (leave blank to skip)"
$binance_secret = ""
$binance_merchant = ""
if (-not [string]::IsNullOrEmpty($binance_key)) {
    $binance_secret = Read-SecureString "Binance Secret Key: "
    $binance_merchant = Read-Host "Binance Merchant ID"
}
Write-Host ""

# Step 6: Email Configuration
Write-Host "Step 6: Email Configuration" -ForegroundColor Blue
Write-Host "===========================" -ForegroundColor Blue
$email_service = Read-Host "Email service (sendgrid/smtp) [sendgrid]"
if ([string]::IsNullOrEmpty($email_service)) { $email_service = "sendgrid" }

if ($email_service -eq "sendgrid") {
    $sendgrid_key = Read-SecureString "SendGrid API Key: "
    $mail_host = "smtp.sendgrid.net"
    $mail_port = "587"
    $mail_user = "apikey"
    $mail_pass = $sendgrid_key
} else {
    $mail_host = Read-Host "SMTP Host"
    $mail_port = Read-Host "SMTP Port [587]"
    if ([string]::IsNullOrEmpty($mail_port)) { $mail_port = "587" }
    $mail_user = Read-Host "SMTP Username"
    $mail_pass = Read-SecureString "SMTP Password: "
}

$from_email = Read-Host "From Email Address [noreply@davetopup.com]"
if ([string]::IsNullOrEmpty($from_email)) { $from_email = "noreply@davetopup.com" }
Write-Host ""

# Step 7: Top-up Provider Configuration
Write-Host "Step 7: Top-up Provider Configuration" -ForegroundColor Blue
Write-Host "=====================================" -ForegroundColor Blue
$topup_url = Read-Host "Top-up Provider API URL"
$topup_key = Read-SecureString "Top-up Provider API Key: "
$topup_merchant = Read-Host "Top-up Provider Merchant ID"
$topup_webhook = Read-SecureString "Top-up Provider Webhook Secret: "
Write-Host ""

# Generate Configuration
Write-Host "Step 8: Generating Configuration" -ForegroundColor Blue
Write-Host "=================================" -ForegroundColor Blue

# Generate random app key
$appKey = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes((Get-Random -Maximum 999999999).ToString().PadLeft(32)))

# Create backend .env file
$envContent = @"
APP_NAME=DaveTopUp
APP_ENV=$env_choice
APP_KEY=base64:$appKey
APP_DEBUG=$(if ($env_choice -eq "production") { "false" } else { "true" })
APP_URL=https://davetopup.com

DB_CONNECTION=mysql
DB_HOST=$db_host
DB_PORT=$db_port
DB_DATABASE=$db_name
DB_USERNAME=$db_user
DB_PASSWORD=$db_pass

CACHE_DRIVER=redis
QUEUE_CONNECTION=database

STRIPE_PUBLIC_KEY=$stripe_pub
STRIPE_SECRET_KEY=$stripe_sec
STRIPE_WEBHOOK_SECRET=$stripe_webhook

PAYPAL_MODE=$(if ($env_choice -eq "sandbox") { "sandbox" } else { "live" })
PAYPAL_CLIENT_ID=$paypal_id
PAYPAL_SECRET=$paypal_secret
PAYPAL_WEBHOOK_ID=$paypal_webhook

BINANCE_API_KEY=$(if ([string]::IsNullOrEmpty($binance_key)) { "your_binance_api_key" } else { $binance_key })
BINANCE_SECRET_KEY=$(if ([string]::IsNullOrEmpty($binance_secret)) { "your_binance_secret_key" } else { $binance_secret })
BINANCE_MERCHANT_ID=$(if ([string]::IsNullOrEmpty($binance_merchant)) { "your_merchant_id" } else { $binance_merchant })

MAIL_MAILER=smtp
MAIL_HOST=$mail_host
MAIL_PORT=$mail_port
MAIL_USERNAME=$mail_user
MAIL_PASSWORD=$mail_pass
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=$from_email
MAIL_FROM_NAME=Dave TopUp

TOPUP_PROVIDER_URL=$topup_url
TOPUP_PROVIDER_API_KEY=$topup_key
TOPUP_PROVIDER_MERCHANT_ID=$topup_merchant
TOPUP_PROVIDER_WEBHOOK_SECRET=$topup_webhook

LOG_CHANNEL=stack
LOG_LEVEL=$(if ($env_choice -eq "production") { "warning" } else { "debug" })

FEATURE_3D_SECURE=true
FEATURE_VOUCHER_AUTO_APPROVAL=true
FEATURE_ASYNC_DELIVERY=true
FEATURE_WEBHOOK_RETRY=true
"@

Set-Content -Path "backend\.env" -Value $envContent -Encoding UTF8
Write-Host "✓ Created backend\.env" -ForegroundColor Green

# Create frontend .env.local file
if (-not (Test-Path "frontend")) {
    New-Item -ItemType Directory -Path "frontend" -Force | Out-Null
}

$apiBase = if ($env_choice -eq "production") { "https://api.davetopup.com" } else { "http://localhost:8000" }
$frontendEnv = @"
REACT_APP_API_BASE=$apiBase
REACT_APP_STRIPE_PUBLIC_KEY=$stripe_pub
REACT_APP_ENVIRONMENT=$env_choice
"@

Set-Content -Path "frontend\.env.local" -Value $frontendEnv -Encoding UTF8
Write-Host "✓ Created frontend\.env.local" -ForegroundColor Green
Write-Host ""

# Display next steps
Write-Host "Step 9: Next Steps" -ForegroundColor Blue
Write-Host "=================" -ForegroundColor Blue
Write-Host ""
Write-Host "Backend Setup:" -ForegroundColor Yellow
Write-Host "  1. cd backend"
Write-Host "  2. composer install"
Write-Host "  3. php artisan migrate"
Write-Host "  4. php artisan serve"
Write-Host ""
Write-Host "Frontend Setup:" -ForegroundColor Yellow
Write-Host "  1. cd frontend"
Write-Host "  2. npm install"
Write-Host "  3. npm start"
Write-Host ""
Write-Host "Test with Stripe:" -ForegroundColor Yellow
Write-Host "  Card: 4242 4242 4242 4242"
Write-Host "  Date: Any future date"
Write-Host "  CVC: Any 3 digits"
Write-Host ""
Write-Host "Configure Webhooks:" -ForegroundColor Yellow
Write-Host "  Stripe: https://dashboard.stripe.com/webhooks"
Write-Host "    - Add: https://api.davetopup.com/webhooks/stripe"
Write-Host "    - Events: payment_intent.succeeded, payment_intent.payment_failed"
Write-Host ""
Write-Host "  PayPal: https://developer.paypal.com/webhooks"
Write-Host "    - Add: https://api.davetopup.com/webhooks/paypal"
Write-Host "    - Events: CHECKOUT.ORDER.COMPLETED, PAYMENT.CAPTURE.COMPLETED"
Write-Host ""
Write-Host "  Binance: https://pay.binance.com/webhooks"
Write-Host "    - Add: https://api.davetopup.com/webhooks/binance"
Write-Host ""
Write-Host "Configuration complete!" -ForegroundColor Green
Write-Host ""
Write-Host "For more information, see:" -ForegroundColor Cyan
Write-Host "  - QUICKSTART.md - Quick start guide"
Write-Host "  - PRODUCTION_SETUP.md - Deployment guide"
Write-Host "  - TESTING_GUIDE.md - Testing procedures"
