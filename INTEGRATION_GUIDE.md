# Dave TopUp — Complete Integration Guide

**Last Updated:** November 27, 2025

## Architecture Overview

The platform now supports:
- **User Login:** OAuth (Google/Facebook) via Socialite
- **Seller/Admin Auth:** Laravel Sanctum (email + password)
- **Stores:** Multi-tenant with support for multiple payment methods
- **Payments:** Stripe, PayPal, Binance, Card, Bank, Crypto
- **Seller Verification:** ID/Passport/SSN/Driver's License document verification
- **Payment Links:** Shareable checkout links sellers can distribute
- **Wallets:** Per-store balance tracking and pending payouts
- **Rewards:** Point-based reward system with redemptions

---

## Backend Setup

### Prerequisites
- PHP 8.1+
- Composer
- Laravel 10+
- MySQL/PostgreSQL
- Stripe/PayPal/Binance API keys

### Installation

1. **Clone and install dependencies:**
```bash
cd backend
composer install
cp .env.example .env
```

2. **Configure `.env`:**
```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=davetopup
DB_USERNAME=root
DB_PASSWORD=secret

# Sanctum (SPA auth)
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost

# OAuth (Socialite)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/callback/google

FACEBOOK_CLIENT_ID=your_fb_client_id
FACEBOOK_CLIENT_SECRET=your_fb_secret
FACEBOOK_REDIRECT_URI=http://localhost:8000/auth/callback/facebook

# Stripe
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...

# PayPal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
```

3. **Run migrations:**
```bash
php artisan migrate
```

4. **Start dev server:**
```bash
php artisan serve --host=127.0.0.1 --port=8000
```

---

## Frontend Setup

### Prerequisites
- Node.js 16+ ([Download](https://nodejs.org/))
- npm or yarn

### Installation

1. **Install Node.js** (if not already done):
   - Download from https://nodejs.org/
   - Use default paths during installation
   - Restart PowerShell after installing

2. **Install dependencies:**
```powershell
cd "C:\Users\dawin\Documents\dave top up\frontend"
npm install
```

3. **Create `.env`:**
```
VITE_API_BASE=http://127.0.0.1:8000/api
VITE_STRIPE_KEY=pk_test_...
```

4. **Run dev server:**
```powershell
npm run dev
```

Browser will open at `http://localhost:5173`

---

## API Endpoints

### Auth

**Buyer Login (OAuth):**
- `GET /auth/redirect/{provider}` — Redirect to Google/Facebook
- `GET /auth/callback/{provider}` — Handle OAuth callback

**Seller/Admin Login (Sanctum):**
- `POST /api/auth/login` — Login with email+password
  ```json
  { "email": "seller@example.com", "password": "secret" }
  ```
- `GET /api/auth/me` — Get current user
- `POST /api/auth/logout` — Revoke token

### Payment Methods

**List/Create payment methods:**
- `GET /api/payment-methods` — List user's payment methods
- `POST /api/payment-methods` — Add new method
  ```json
  {
    "type": "card|bank|paypal|binance|crypto",
    "metadata": { "last4": "4242", "brand": "visa" }
  }
  ```
- `POST /api/payment-methods/{id}/set-default` — Make default
- `DELETE /api/payment-methods/{id}` — Remove

### Seller Verification

**Submit identity verification:**
- `POST /api/verifications` — Submit document
  ```json
  {
    "store_id": 1,
    "document_type": "passport|national_id|drivers_license|ssn",
    "document_url": "https://...",
    "verified_name": "John Doe",
    "verified_country": "US"
  }
  ```
- `GET /api/verifications/{id}` — Check status

**Admin endpoints:**
- `POST /api/admin/verifications/{id}/approve`
- `POST /api/admin/verifications/{id}/reject`

### Payment Links

**Create and share payment links:**
- `POST /api/payment-links` — Create link
  ```json
  {
    "store_id": 1,
    "title": "Diamond Pack",
    "amount": 9.99,
    "currency": "USD"
  }
  ```
- `GET /api/payment-links` — List links
- `GET /api/payment-links/public/{token}` — Public fetch (no auth)

### Orders

- `POST /api/orders` — Create order
- `GET /api/orders/{id}` — Get order details
- `GET /api/orders/{id}/status` — Check status

### Payments

- `POST /api/payments/card` — Stripe card payment
- `POST /api/payments/paypal` — PayPal payment
- `POST /api/payments/binance` — Binance Pay

### Stores (Multi-tenant)

- `POST /api/stores` — Create store
- `GET /api/stores/{slug}` — Get store by slug
- `PUT /api/stores/{id}` — Update store
- `POST /api/stores/{id}/cashout` — Request payout (Stripe Connect)

### Rewards

- `GET /api/rewards` — List rewards
- `POST /api/rewards` — Create reward (admin)
- `POST /api/rewards/{id}/redeem` — Redeem reward

---

## Frontend Components

- `frontend/src/components/Checkout/Checkout.tsx` — Payment checkout form
- `frontend/src/components/Auth/Login.tsx` — OAuth buyer login
- `frontend/src/components/Auth/LoginAdmin.tsx` — Sanctum seller login
- `frontend/src/components/Seller/SellerOnboarding.tsx` — Identity verification + payment method setup
- `frontend/src/components/Seller/PaymentLinkGenerator.tsx` — Create and share payment links
- `frontend/src/components/Chat/Chat.tsx` — Community chat with WhatsApp thank-you
- `frontend/src/components/Admin/AdminDashboard.tsx` — Admin dashboard stub

---

## Key Features Implemented

✅ **Multi-tenant stores** — Users can create and manage multiple storefronts
✅ **OAuth authentication** — Google/Facebook login for buyers
✅ **Sanctum auth** — Email/password login for sellers and admins (separate from OAuth)
✅ **Payment methods** — Support for Card, Bank, PayPal, Binance, Crypto
✅ **Seller verification** — Document uploads (ID, Passport, SSN, License)
✅ **Payment links** — Shareable checkout links with custom amounts/items
✅ **Wallets** — Per-store balance and payout tracking
✅ **Admin dashboard** — Order management, verification approval, refunds
✅ **Chat system** — Community chat with WhatsApp share button
✅ **Rewards** — Points-based reward redemption

---

## Next Steps

### To Enable Stripe Connect Payouts
1. Set `STRIPE_ACCOUNT_ID` in Store model
2. Implement `/api/stores/{id}/cashout` to create Stripe Transfers:
   ```php
   $transfer = \Stripe\Transfer::create([
       'amount' => $amount * 100,
       'currency' => 'usd',
       'destination' => $store->stripe_account_id,
   ]);
   ```
3. Add webhook handler for `transfer.created` events

### To Implement Points Ledger for Rewards
1. Create `UserPoints` model with `user_id`, `balance`, `updated_at`
2. Add points ledger/transaction tracking
3. Update `RewardController@redeem` to check balance before allowing redemption

### To Add More OAuth Providers
Edit `backend/routes/web.php`:
```php
Route::get('/auth/redirect/{provider}', 'AuthController@redirectToProvider')
    ->where('provider', 'google|facebook|github|apple');
```

---

## Troubleshooting

### `npm run dev` says Node not found
- **Solution:** Download Node.js from https://nodejs.org/, install, restart terminal

### CORS errors between frontend and backend
- **Check:** `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` in backend `.env`
- **Ensure:** Frontend is running on `localhost:5173`

### Payment provider webhook not firing
- **Stripe:** Use Stripe CLI to forward webhooks locally:
  ```bash
  stripe listen --forward-to localhost:8000/api/webhooks/stripe
  ```

### Database migration error
- **Check:** MySQL is running
- **Verify:** `DB_*` credentials in `.env`

---

## Support

For issues, check `FINAL_CHECKLIST.md` or contact support@davetopup.com
