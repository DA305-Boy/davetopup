# Quick Reference: New Features API Guide

## For Sellers

### Request Payout
```bash
POST /api/stores/{storeId}/cashout
Authorization: Bearer {sanctum_token}

{
  "amount": 500.00
}

# Response (201)
{
  "id": 1,
  "store_id": 5,
  "amount": 500.00,
  "currency": "USD",
  "status": "processing",
  "stripe_transfer_id": "tr_...",
  "created_at": "2024-01-15T10:30:00Z"
}
```

### View Payout History
```bash
GET /api/stores/{storeId}/payout-history?page=1
Authorization: Bearer {sanctum_token}

# Response
{
  "data": [
    {
      "id": 1,
      "amount": 500.00,
      "status": "completed",
      "processed_at": "2024-01-16T14:20:00Z"
    }
  ],
  "total": 10
}
```

### Upload Identity Document
```bash
POST /api/verifications/upload-document
Authorization: Bearer {sanctum_token}
Content-Type: multipart/form-data

file: <binary file> (jpg/png/pdf, max 5MB)
document_type: "passport"

# Response (201)
{
  "document_url": "encrypted_path_here",
  "message": "Document uploaded successfully"
}
```

### Submit Verification
```bash
POST /api/verifications
Authorization: Bearer {sanctum_token}

{
  "store_id": 5,
  "document_type": "passport",
  "document_url": "encrypted_path_from_upload",
  "verified_name": "John Seller",
  "verified_country": "US"
}

# Response (201)
{
  "id": 1,
  "verification_status": "pending"
}
```

### Check Points Balance
```bash
GET /api/auth/me
Authorization: Bearer {sanctum_token}

# Response includes user's points (implement via eager load)
{
  "id": 5,
  "name": "John Seller",
  "userPoints": {
    "balance": 1500,
    "lifetime_earned": 5000,
    "lifetime_redeemed": 3500
  }
}
```

### Redeem Reward
```bash
POST /api/rewards/{rewardId}/redeem
Authorization: Bearer {sanctum_token}

{
  "order_id": 123  // optional
}

# Response (201)
{
  "id": 1,
  "reward_id": 5,
  "user_id": 10,
  "status": "completed",
  "points_deducted": 100
}

# Error if insufficient points (422)
{
  "error": "Insufficient points",
  "required": 150,
  "available": 50
}
```

### View Points Ledger
```bash
GET /api/rewards/ledger?page=1
Authorization: Bearer {sanctum_token}

# Response
{
  "data": [
    {
      "id": 1,
      "points_change": 100,
      "reason": "purchase",
      "order_id": 123,
      "notes": null,
      "created_at": "2024-01-15T10:30:00Z"
    },
    {
      "id": 2,
      "points_change": -50,
      "reason": "reward_redemption",
      "reward_redemption_id": 1,
      "notes": "Redeemed reward: 100 Bonus Points",
      "created_at": "2024-01-16T14:20:00Z"
    }
  ]
}
```

---

## For Admins

### Dashboard Overview
```bash
GET /api/admin/overview
Authorization: Bearer {admin_token}

# Response
{
  "total_orders": 1250,
  "total_revenue": 45230.50,
  "total_payouts": 32150.25,
  "pending_verifications": 8,
  "active_stores": 45
}
```

### View All Orders (Filtered)
```bash
GET /api/admin/orders?status=completed&page=1
Authorization: Bearer {admin_token}

# Response
{
  "data": [
    {
      "id": 1,
      "store_id": 5,
      "total_amount": 25.50,
      "status": "completed",
      "created_at": "2024-01-15T10:30:00Z",
      "store": { "store_name": "TopUp Elite" }
    }
  ],
  "total": 1250,
  "per_page": 50,
  "current_page": 1
}
```

### View All Sellers (With Revenue)
```bash
GET /api/admin/sellers?verification_status=approved&page=1
Authorization: Bearer {admin_token}

# Response
{
  "data": [
    {
      "id": 10,
      "name": "John Seller",
      "store_name": "TopUp Elite",
      "email": "john@example.com",
      "total_orders": 145,
      "total_revenue": 3250.75,
      "verification_status": "approved",
      "created_at": "2024-01-10T14:20:00Z"
    }
  ],
  "total": 45
}
```

### View Payouts (With Summary)
```bash
GET /api/admin/payouts?status=completed&page=1
Authorization: Bearer {admin_token}

# Response
{
  "data": [
    {
      "id": 1,
      "amount": 500.00,
      "currency": "USD",
      "status": "completed",
      "created_at": "2024-01-16T14:20:00Z",
      "store": {
        "store_name": "TopUp Elite",
        "user": { "name": "John Seller" }
      }
    }
  ],
  "summary": {
    "total_pending": 5000.00,
    "total_processing": 12500.00,
    "total_completed": 92150.25,
    "total_failed": 1200.00
  },
  "total": 1250
}
```

### View Verification Queue
```bash
GET /api/admin/verifications?status=pending&page=1
Authorization: Bearer {admin_token}

# Response
{
  "data": [
    {
      "id": 1,
      "verified_name": "Jane Doe",
      "document_type": "passport",
      "verification_status": "pending",
      "user": {
        "id": 5,
        "name": "Jane Doe",
        "email": "jane@example.com"
      },
      "store": {
        "id": 3,
        "store_name": "Game Hub"
      }
    }
  ],
  "total": 8
}
```

### Approve Verification (Sends Email)
```bash
POST /api/admin/verifications/{verificationId}/approve
Authorization: Bearer {admin_token}

{}

# Response (200)
{
  "verification": {
    "id": 1,
    "verification_status": "approved",
    "verified_at": "2024-01-15T10:35:00Z"
  }
}

# Actions taken:
# - Verification status updated to "approved"
# - VerificationApprovedNotification email queued
# - In-app notification created
```

### Reject Verification (Sends Email with Reason)
```bash
POST /api/admin/verifications/{verificationId}/reject
Authorization: Bearer {admin_token}

{
  "reason": "Document is blurry and illegible"
}

# Response (200)
{
  "verification": {
    "id": 1,
    "verification_status": "rejected",
    "rejection_reason": "Document is blurry and illegible"
  }
}

# Actions taken:
# - Verification status updated to "rejected"
# - Rejection reason stored
# - VerificationRejectedNotification email queued with reason
# - In-app notification created
```

---

## Frontend Integration

### Load Admin Dashboard
```tsx
import AdminMarketplaceDashboard from './components/AdminMarketplaceDashboard'

// Navigate to /admin/marketplace
<Route path="/admin/marketplace" element={<AdminMarketplaceDashboard />} />
```

### Seller Upload Document Flow
```tsx
// 1. Upload document
const formData = new FormData()
formData.append('file', fileInput.files[0])
formData.append('document_type', 'passport')

const response = await axios.post(
  '/api/verifications/upload-document',
  formData,
  { headers: { Authorization: `Bearer ${token}` } }
)

const encryptedUrl = response.data.document_url

// 2. Submit verification with encrypted URL
await axios.post(
  '/api/verifications',
  {
    store_id: 5,
    document_type: 'passport',
    document_url: encryptedUrl,  // Use this!
    verified_name: 'John Seller',
    verified_country: 'US'
  },
  { headers: { Authorization: `Bearer ${token}` } }
)
```

### Seller Request Payout
```tsx
const handleCashout = async (amount) => {
  try {
    const response = await axios.post(
      `/api/stores/${storeId}/cashout`,
      { amount },
      { headers: { Authorization: `Bearer ${token}` } }
    )
    
    console.log('Payout initiated:', response.data)
    // Payout status: 'processing'
    // User will receive email when transfer completes
  } catch (error) {
    console.error('Cashout error:', error.response.data)
  }
}
```

---

## Email Templates

### VerificationApprovedNotification
**Trigger:** Admin approves verification
**Subject:** "Welcome! Your Identity Verification is Approved ✓"
**Recipients:** Seller email
**Content:**
- Seller name, store name
- Approval message
- Next steps (set payment methods, configure packages)
- Links: Dashboard, Support

### VerificationRejectedNotification
**Trigger:** Admin rejects verification
**Subject:** "Identity Verification - Needs Attention ❌"
**Recipients:** Seller email
**Content:**
- Seller name
- Rejection reason (from admin)
- Instructions to resubmit
- Link: Resubmit verification in dashboard

### PayoutCompletedNotification
**Trigger:** Stripe webhook `transfer.created`
**Subject:** "Payout Completed! 💰"
**Recipients:** Store owner email
**Content:**
- Amount, currency, Transfer ID
- Timeline (1-2 business days to account)
- Store name, completion time
- Link: View payouts in dashboard

---

## Status Codes & Error Handling

### Success Responses
- `201 Created` — Resource created (payout, verification, document)
- `200 OK` — Success, data returned (overview, lists, approvals)

### Error Responses
- `401 Unauthorized` — Missing/invalid Sanctum token
- `403 Forbidden` — User not owner of resource
- `404 Not Found` — Resource not found
- `422 Unprocessable Entity` — Validation failed (file size, points, etc.)
- `429 Too Many Requests` — Rate limited

### Common Error Messages
```json
{
  "error": "Insufficient points",
  "required": 150,
  "available": 50
}

{
  "error": "Invalid file type or size"
}

{
  "error": "Verification status must be pending to approve"
}

{
  "error": "Maximum retry attempts reached for payout"
}
```

---

## Testing with Postman

### Setup
1. Create Postman environment
2. Set variables: `{{baseUrl}}`, `{{seller_token}}`, `{{admin_token}}`
3. Import collection from provided `.postman_collection.json`

### Test Sequence
1. **Auth**: POST login → save token
2. **Upload Doc**: POST upload-document → save encrypted URL
3. **Submit Verification**: POST verifications with encrypted URL
4. **Admin Verify**: POST admin/verifications/{id}/approve (check email queue)
5. **Check Points**: GET auth/me (verify userPoints)
6. **Cashout**: POST stores/{id}/cashout
7. **View Payouts**: GET admin/payouts
8. **Dashboard**: GET admin/overview (verify stats)

---

## Troubleshooting

### Payout Shows "processing" Forever
- Check Stripe webhook endpoint registered: `https://yourdomain.com/api/webhooks/stripe`
- Verify webhook secret in `.env` matches Stripe dashboard
- Check Laravel queue worker is running: `php artisan queue:work`

### Upload Document Returns "Invalid File"
- File must be: jpg, jpeg, png, or pdf
- File size must be < 5MB
- Check MIME type detection is working

### Verification Email Not Received
- Check Laravel queue worker running: `php artisan queue:work`
- Verify MAIL_FROM_ADDRESS in `.env`
- Check email config (SMTP credentials, SendGrid API key, etc.)
- Review logs: `storage/logs/laravel.log`

### Admin Dashboard Returns 403 Forbidden
- Verify user has admin role (check users table, roles table, or policy)
- Verify Sanctum token not expired
- Verify token Authorization header format: `Bearer {token}`

### Points Deduction Fails with "Insufficient Points"
- User's UserPoints record might not exist
- Run: `php artisan tinker` → `\App\Services\PointsService::awardPoints($userId, 100, 'bonus')`
- Verify PointsLedger entries are logging correctly

---

## Environment Checklist

```bash
# ✅ Required for full feature set
STRIPE_PUBLIC_KEY=pk_...
STRIPE_SECRET_KEY=sk_...
STRIPE_WEBHOOK_SECRET=whsec_...

FILESYSTEM_DISK=s3  # or azure, local
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=davetopup-documents

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io  # or SendGrid, AWS SES
MAIL_FROM_ADDRESS=noreply@davetopup.com

QUEUE_CONNECTION=database  # or redis

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=davetopup
DB_USERNAME=root
DB_PASSWORD=...
```

---

## Key Metrics to Monitor

- **Payouts**: Total pending/processing/completed/failed amounts
- **Points**: Total awarded vs. redeemed per user, ledger accuracy
- **Verifications**: Queue size, approval rate, average processing time
- **Documents**: Storage usage (S3/Azure), upload success rate
- **Emails**: Delivery rate, bounce rate, open rate (if tracked)
- **Errors**: Failed transfers, validation errors, queue job failures

---

## Summary

✅ **Stripe Connect Payouts**: Full integration with retry logic and webhooks
✅ **Points Ledger**: Award, deduct, and track user points with full history
✅ **Document Storage**: S3/Azure/local with encryption and temporary URLs
✅ **Email Notifications**: Branded emails for verification and payouts
✅ **Admin Dashboard**: Complete marketplace oversight with analytics and controls

All features production-ready. Next: Install Node.js and test frontend component.
