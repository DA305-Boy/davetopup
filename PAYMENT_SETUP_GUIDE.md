## Track Order API

**Endpoint:** `/api/track-order.php` (GET/POST)

Returns order and payment status by `orderId`.

**Example:**
```
curl http://localhost:8080/api/track-order.php?orderId=ORD-...
```

**Response:**
```
{
  "success": true,
  "orderId": "ORD-...",
  "status": "completed",
  ...
}
```

---

### Google Pay Debit Card Support

Google Pay payments now record the underlying card type (debit/credit/prepaid) in the `transactions.card_funding` column.
## Black Friday Redeem Endpoint

**Endpoint:** `/api/redeem-black-friday.php` (POST)

Redeems all orders placed on Black Friday (2025-11-28) by updating their status to `redeemed`.

**Request:**
```
POST /api/redeem-black-friday.php
```

**Response Example:**
```
{
  "success": true,
  "message": "Redeemed 42 Black Friday orders.",
  "redeemed": 42,
  "orders": ["ORD-...", ...]
}
```

Use for mass fulfillment or promotional actions on Black Friday orders.
# Payment Processor Setup Guide

Complete guide for configuring each payment processor for Dave TopUp.

## Table of Contents
1. [Stripe](#stripe)
2. [PayPal](#paypal)
3. [Binance Pay](#binance-pay)
4. [Coinbase Commerce](#coinbase-commerce)
5. [Skrill](#skrill)
6. [Flutterwave](#flutterwave)
7. [Cash App](#cash-app)
8. [Crypto Payments](#crypto-payments)
9. [Apple/Google Pay](#applegoogle-pay)

---

## Stripe

### 1. Create Stripe Account
- Go to https://stripe.com
- Sign up for a new account
- Complete business verification

### 2. Get API Keys
- Navigate to **Developers > API Keys**
- Copy **Publishable Key** (starts with `pk_`)
- Copy **Secret Key** (starts with `sk_`)
- In test mode, use `pk_test_` and `sk_test_` versions

### 3. Configure Webhook
- Go to **Developers > Webhooks**
- Click **Add endpoint**
- Endpoint URL: `https://yourdomain.com/api/webhooks/stripe`
- Select events:
  - `payment_intent.succeeded`
  - `payment_intent.payment_failed`
  - `charge.refunded`
- Copy **Webhook Signing Secret** (starts with `whsec_`)

### 4. Set Environment Variables
```
STRIPE_PUBLIC_KEY=pk_test_xxxxx
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

### 5. Test
- Use test card: `4242 4242 4242 4242`
- Any future expiration date
- Any 3-digit CVC

---

## PayPal

### 1. Create PayPal Developer Account
- Go to https://developer.paypal.com
- Sign up and verify email
- Sandbox environment auto-created

### 2. Get API Credentials
- Navigate to **Apps & Credentials**
- Select **Sandbox** mode
- Under "Business Account", click **View/Edit**
- Copy **Client ID** and **Secret**

### 3. Configure Webhook
- Go to **Settings > Webhooks**
- Click **Create webhook**
- Webhook URL: `https://yourdomain.com/api/webhooks/paypal`
- Event types:
  - CHECKOUT.ORDER.COMPLETED
  - PAYMENT.CAPTURE.COMPLETED
  - PAYMENT.CAPTURE.REFUNDED
- Copy **Webhook ID**

### 4. Set Environment Variables
```
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=AXxxxxxx...
PAYPAL_SECRET=EKxxxxxx...
PAYPAL_WEBHOOK_ID=xxxxx...
```

### 5. Test
- Use sandbox credentials
- Test buyer account auto-created in Developer Dashboard

---

## Binance Pay

### 1. Create Binance Account
- Go to https://www.binance.com
- Complete KYC verification

### 2. Enable Binance Pay
- Navigate to **Wallet > Fiat and Spot**
- Go to **API Management**
- Create new API key for merchant

### 3. Get API Credentials
- Copy **API Key**
- Copy **Secret Key**
- Note your **Merchant ID** (UID number)

### 4. Configure Webhook
- Go to **Merchant Settings > Webhooks**
- Add webhook URL: `https://yourdomain.com/api/webhooks/binance`
- Events: Payment success/failure
- Copy webhook secret if provided

### 5. Set Environment Variables
```
BINANCE_API_KEY=xxxxxxxx
BINANCE_SECRET=xxxxxxxx
BINANCE_WEBHOOK_SECRET=xxxxxxxx
BINANCE_RETURN_URL=https://yourdomain.com/success.html
BINANCE_CANCEL_URL=https://yourdomain.com/cancel.html
```

### 6. Test
- Use sandbox: https://testnet.binance.org
- Create test API key on testnet

---

## Coinbase Commerce

### 1. Create Coinbase Commerce Account
- Go to https://commerce.coinbase.com
- Sign up with Coinbase account
- Complete business information

### 2. Get API Key
- Navigate to **Settings > API Keys**
- Click **Create new key**
- Select appropriate permissions
- Copy **API Key**

### 3. Configure Webhook
- Go to **Settings > Webhooks**
- Click **Add endpoint**
- Endpoint URL: `https://yourdomain.com/api/webhooks/coinbase`
- Select events:
  - charge.confirmed
  - charge.received
  - charge.failed
- Copy **Webhook secret**

### 4. Set Environment Variables
```
COINBASE_API_KEY=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
COINBASE_WEBHOOK_SECRET=xxxxxxxxxxxxxxxx
COINBASE_RETURN_URL=https://yourdomain.com/success.html
COINBASE_CANCEL_URL=https://yourdomain.com/cancel.html
```

### 5. Test
- Sandbox mode available
- Use testnet cryptocurrencies

---

## Skrill

### 1. Create Skrill Merchant Account
- Go to https://www.skrill.com
- Sign up as merchant
- Complete KYC verification

### 2. Get Merchant Credentials
- Navigate to **Merchant Settings**
- Copy **Merchant ID**
- Generate **Secret Key** in settings
- Note your **Email address** associated with account

### 3. Configure Webhook
- In Merchant Settings, set **Notification/Webhook URL**
- URL: `https://yourdomain.com/api/webhooks/skrill`
- Enable POST notifications

### 4. Set Environment Variables
```
SKRILL_MERCHANT_ID=123456
SKRILL_SECRET_KEY=xxxxxxxxxxxxx
SKRILL_RETURN_URL=https://yourdomain.com/success.html
SKRILL_CANCEL_URL=https://yourdomain.com/cancel.html
```

### 5. Test
- Create test merchant account
- Use sandbox payment methods

---

## Flutterwave

### 1. Create Flutterwave Account
- Go to https://www.flutterwave.com
- Sign up and verify email
- Complete KYC

### 2. Get API Keys
- Navigate to **Settings > General**
- Copy **Public Key** (FLWPUBK_...)
- Copy **Secret Key** (FLWSECK_...)
- Both test and live keys available

### 3. Configure Webhook
- Go to **Settings > Webhooks**
- Add webhook URL: `https://yourdomain.com/api/webhooks/flutterwave`
- Set **Webhook Secret Hash**
- Events: Successful payment, failed payment

### 4. Set Environment Variables
```
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST_xxxxx
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST_xxxxx
FLUTTERWAVE_SECRET_HASH=xxxxxxxxxxxxxxxx
FLUTTERWAVE_RETURN_URL=https://yourdomain.com/success.html
FLUTTERWAVE_CANCEL_URL=https://yourdomain.com/cancel.html
```

### 5. Test
- Sandbox mode available
- Test payment methods provided in dashboard

---

## Cash App

### 1. Create Square Account (Cash App Parent Company)
- Go to https://squareup.com
- Sign up for a Square account
- Complete business verification
- Enable Cash App Payments in your account

### 2. Get API Credentials
- Navigate to **Developer > API Keys** in Square Dashboard
- Copy **API Key** (starts with `sq_test_` or `sq_live_`)
- Generate **Access Token** for authentication
- Note your **Location ID**

### 3. Configure Webhook
- Go to **Developer > Webhooks**
- Click **Add Endpoint**
- Webhook URL: `https://yourdomain.com/api/webhooks/cashapp`
- Select events:
  - `payment.updated` (main event for payment status)
  - `payment.created` (payment initiated)
- Copy **Webhook Signing Secret** for signature verification

### 4. Set Environment Variables
```
CASHAPP_API_KEY=sq_test_xxxxxxxxxxxxx
CASHAPP_WEBHOOK_SECRET=xxxxxxxxxxxxxxxxxxxxx
CASHAPP_MODE=test
CASHAPP_RETURN_URL=https://yourdomain.com/success.html
CASHAPP_CANCEL_URL=https://yourdomain.com/cancel.html
```

### 5. Test Payment Flow
**Test Cards in Sandbox:**
- **Success**: 4111 1111 1111 1111
- **Decline**: 4000 0200 0000 0000
- **3D Secure**: 4000 0002 0000 0002
- **Expiration**: Any future date
- **CVV**: Any 3 digits

**Test Cash App:**
- Use Square's test mode
- Sandbox environment available
- No real funds charged

### 6. Webhook Verification
- Square signs all webhooks with HMAC-SHA256
- Verify `X-Square-HMAC-SHA256` header
- Use webhook secret to compute expected signature
- Compare with `hash_equals()` for security

---

## Crypto Payments
````
### 1. Set Up Wallet Infrastructure
Choose a provider:
- **Infura** (for Ethereum/EVM chains)
- **Alchemy** (for Ethereum/EVM)
- **BlockScout** (open source)

### 2. Generate Crypto Addresses
```
BTC Address: https://blockchain.info (or use address you control)
ETH Address: Use MetaMask or similar wallet
LTC Address: Litecoin wallet
XRP Address: Ripple wallet
```

### 3. Get Exchange Rate Data
- Use **CoinGecko API** (free): https://api.coingecko.com/api/v3/
- Or **CoinMarketCap API** (paid)

### 4. Set Environment Variables
```
CRYPTO_NETWORK_RPC_URL=https://mainnet.infura.io/v3/YOUR_PROJECT_ID
CRYPTO_WALLET_ADDRESS=0xxxxxx...
CRYPTO_PRIVATE_KEY=xxxxxx... (for sending crypto)
CRYPTO_RECEIVE_ADDRESS_BTC=1xxxxxx...
CRYPTO_RECEIVE_ADDRESS_ETH=0xxxxxx...
CRYPTO_RECEIVE_ADDRESS_LTC=Lxxxxxx...
CRYPTO_RECEIVE_ADDRESS_XRP=rxxxxxx...
```

### 5. Store Payment Requests
- Payments stored in `payment_requests` table
- Track: address, amount, crypto type, expiration
- Use blockchain listeners to verify payments

---

## Apple/Google Pay

### 1. Apple Pay Setup
- Register domain with Apple
- Create merchant certificate
- Upload certificate to Apple
- Configure in Merchant Center

### 2. Google Pay Setup
- Create Google Merchant account
- Enable Google Pay in Payment Methods
- Configure allowed currencies and payment methods

### 3. Set Environment Variables
```
APPLE_PAY_MERCHANT_ID=merchant.xxxxx
APPLE_PAY_DOMAIN=yourdomain.com
APPLE_PAY_CERTIFICATE_PATH=/path/to/cert.pem
GOOGLE_PAY_MERCHANT_ID=123456789
GOOGLE_PAY_MERCHANT_NAME=Dave TopUp
```

### 4. Integration
- Both route through Stripe backend in our implementation
- Ensure Stripe is configured first
- Apple/Google Pay handled by Stripe tokenization

---

## Testing Checklist

### Before Going Live
- [ ] Test each payment method with test credentials
- [ ] Verify webhooks receive events
- [ ] Confirm order status updates correctly
- [ ] Test refund processing
- [ ] Test error scenarios (declined card, etc.)
- [ ] Verify emails send correctly
- [ ] Check HTTPS certificate validity
- [ ] Ensure CORS allows your frontend domain

### Test Credentials
- **Stripe**: `4242 4242 4242 4242` / any future date / any CVC
- **PayPal**: Sandbox buyer account (auto-created)
- **Binance**: Testnet credentials
- **Coinbase**: Sandbox environment
- **Skrill**: Test merchant account
- **Flutterwave**: Test account with dummy payments
- **Crypto**: Use testnet addresses initially

---

## Production Deployment

### 1. Migrate to Live Keys
- Get LIVE API keys from each service
- Update `.env` with production keys
- Do NOT commit `.env` to version control

### 2. Enable HTTPS
- All webhook endpoints must use HTTPS
- Set `HTTPS_REQUIRED=true` in `.env`
- Install valid SSL certificate

### 3. Configure Webhooks
- Update all webhook URLs to production domain
- Register production URLs with each payment processor
- Update webhook secrets with production values

### 4. Test Production
- Process a real payment with small amount
- Verify order created correctly
- Confirm webhook received and processed
- Check email notification sent
- Verify order details accessible

### 5. Monitor
- Set up error logging and alerts
- Monitor webhook failures
- Track payment success rates
- Set up support contact points

---

## Environment Variable Summary

```bash
# Copy .env.example to .env
cp .env.example .env

# Fill in these for each provider:
# Stripe
STRIPE_PUBLIC_KEY=pk_test_xxxxx
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

# PayPal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=AXxxxxx
PAYPAL_SECRET=EKxxxxx
PAYPAL_WEBHOOK_ID=xxxxx

# Binance
BINANCE_API_KEY=xxxxx
BINANCE_SECRET=xxxxx
BINANCE_WEBHOOK_SECRET=xxxxx

# Coinbase
COINBASE_API_KEY=xxxxx
COINBASE_WEBHOOK_SECRET=xxxxx

# Skrill
SKRILL_MERCHANT_ID=xxxxx
SKRILL_SECRET_KEY=xxxxx

# Flutterwave
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST_xxxxx
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST_xxxxx
FLUTTERWAVE_SECRET_HASH=xxxxx

# Crypto
CRYPTO_NETWORK_RPC_URL=https://mainnet.infura.io/v3/xxxxx
CRYPTO_RECEIVE_ADDRESS_BTC=1xxxxx
CRYPTO_RECEIVE_ADDRESS_ETH=0xxxxx
```

---

## Common Issues

### Webhook Not Triggering
- Verify endpoint URL is correct
- Check HTTPS certificate validity
- Ensure firewall allows incoming requests
- Verify webhook secret matches

### Payment Shows as Failed
- Check API key validity
- Verify business is not restricted
- Check payment amount is within limits
- Review error logs for details

### Email Not Sending
- Verify SMTP credentials
- Check firewall allows SMTP port
- Verify email address format
- Check spam folder

---

## Support Resources

- **Stripe**: https://stripe.com/docs
- **PayPal**: https://developer.paypal.com/docs
- **Binance**: https://developers.binance.com
- **Coinbase**: https://commerce.coinbase.com/docs
- **Skrill**: https://www.skrill.com/en/business/merchant
- **Flutterwave**: https://developer.flutterwave.com
- **Dave TopUp Support**: support@davetopup.com
