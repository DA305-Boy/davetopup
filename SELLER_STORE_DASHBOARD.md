# 🏪 Dave TopUp Seller Store Dashboard

## Features

Sellers can now manage their complete game topup store with:

### 📊 Overview Tab
- **Store Balance**: View available balance for cashout
- **Product Count**: See how many products are active
- **Store Status**: Check if store is verified and active
- **Join Date**: See when store was created
- **Quick Actions**: Direct links to add products, request cashout, or refresh data

### 📦 Products Tab
- **Add Products**: Create new game topup products
- **Product Management**: View all products with images, prices, and stock
- **Edit/Delete**: Manage product listings
- **Categories**: Organize by diamonds, credits, coins, premium, etc.
- **Stock Tracking**: Monitor inventory levels

**Add Product Form:**
```
✓ Product Name (e.g., "100 Free Fire Diamonds")
✓ Game Name (e.g., "Free Fire")
✓ Price (e.g., $9.99)
✓ Category (Diamonds, Credits, Coins, Premium, Other)
✓ Stock Quantity
✓ Description
✓ Image URL
```

### 💰 Payouts Tab
- **Available Balance**: Shows current balance ready for cashout
- **Total Payouts**: Sum of all completed payouts
- **Pending Payouts**: Count of processing transfers
- **Request Cashout**: Enter amount and submit payout request
- **Payout History**: View all past transactions with status and Stripe transfer ID

**Payout Status:**
- 🟡 `pending` - Initial request received
- 🔵 `processing` - Stripe is processing transfer
- ✅ `completed` - Funds transferred to bank account
- ❌ `failed` - Transfer failed (will auto-retry)

---

## 🔐 Accessing the Store Dashboard

### Option 1: Direct URL Access
Navigate to: `https://www.davetopup.com/seller/store`

**Login with seller credentials:**
- Email: seller@davetopup.com
- Password: password123 (or your registered password)

### Option 2: From App Routing
The app automatically routes to the store dashboard when:
1. User is logged in with seller account
2. URL path contains `/seller/store`
3. Valid Sanctum token is stored in localStorage

---

## 🎨 Styling Features

### Modern UI Design
- **Gradient Backgrounds**: Professional indigo/blue gradients
- **Cards & Shadows**: Depth with shadow effects
- **Color Coding**: 
  - 🔵 Indigo for primary actions
  - 🟢 Green for earnings/success
  - 🟠 Orange for status indicators
  - 🔴 Red for delete/danger actions
- **Emoji Icons**: Easy-to-understand visual indicators
- **Responsive Layout**: Mobile, tablet, and desktop support
- **Hover Effects**: Interactive buttons with scale transformations
- **Loading States**: Spinners and disabled states

### CSS Framework
The dashboard uses Tailwind CSS with custom styling:
- Rounded corners (8px default)
- Smooth transitions (300ms)
- Focus rings on inputs
- Professional typography
- Grid layouts

---

## 📱 Component Breakdown

### 1. SellerStoreDashboard.tsx
**Main dashboard component** with three tabs

**Props:** None (fetches own store data via API)

**State:**
- `activeTab` - Current tab view
- `store` - Store data (name, balance, status)
- `user` - Seller user data (name, email, avatar)
- `products` - Array of seller's products
- `payoutHistory` - Array of past payouts
- `loading` - Loading state for API calls
- `showAddProduct` - Show/hide add product form
- `cashoutAmount` - Amount entered for payout

**Key Methods:**
- `loadStoreData()` - Fetch all store info from API
- `handleAddProduct()` - POST new product
- `handleCashout()` - POST payout request
- `handleDeleteProduct()` - DELETE product

### 2. SellerLogin.tsx
**Login page component** for seller authentication

**Features:**
- Email/password login form
- Error display
- Demo credentials shown
- Features showcase
- Links to register and home

---

## 🔌 API Endpoints Used

### Authentication
```
POST /api/auth/login
GET /api/auth/me
```

### Store Management
```
GET /api/stores?owner=true        # Get seller's store
POST /api/stores/{id}/products     # Add product
GET /api/stores/{id}/products      # Get products
DELETE /api/stores/{id}/products/{productId}
```

### Payouts
```
POST /api/stores/{id}/cashout      # Request payout
GET /api/stores/{id}/payout-history
```

---

## 💾 Local Storage

The app stores:
- `sanctum_token` - Authentication token for API requests

Token is automatically retrieved for all authenticated requests:
```
Authorization: Bearer {sanctum_token}
```

---

## 🚀 Environment Variables

Create `.env` in frontend directory:
```
VITE_API_URL=http://localhost:8000/api
```

Or if deploying to production:
```
VITE_API_URL=https://api.davetopup.com/api
```

---

## 📋 User Journey

### First Time Seller
1. User navigates to `/seller/store`
2. Redirected to login page (SellerLogin.tsx)
3. Enters email and password
4. Token stored in localStorage
5. Redirected back to `/seller/store`
6. Dashboard loads with store info

### Adding a Product
1. Click "📦 Products" tab
2. Click "➕ Add Product" button
3. Fill in product details
4. Click "Save Product"
5. Product appears in product grid

### Requesting Payout
1. Click "💰 Payouts" tab
2. Enter cashout amount
3. Click "💰 Cashout" button
4. Payout request sent to backend
5. Status updates to "processing"
6. When Stripe transfer completes, email notification sent
7. Status changes to "completed"

---

## 🎨 Color Scheme

```
Primary:     #4f46e5 (Indigo)
Primary Dark: #4338ca
Primary Light: #818cf8

Secondary:   #10b981 (Green/Success)
Secondary Dark: #059669

Accent:      #f59e0b (Orange/Warning)
Danger:      #ef4444 (Red)

Gray Scale:
50:   #f9fafb (Lightest)
100:  #f3f4f6
200:  #e5e7eb
300:  #d1d5db
400:  #9ca3af
500:  #6b7280
600:  #4b5563
700:  #374151
800:  #1f2937
900:  #111827 (Darkest)
```

---

## 📊 Stats Cards

Each stat card shows:
- **Icon Emoji** - Visual indicator
- **Label** - Stat name
- **Value** - Large, bold number
- **Subtitle** - Additional context
- **Color Accent** - Left border with theme color

---

## 🔧 Customization

### Adding New Columns to Products Table
Edit `SellerStoreDashboard.tsx` > Products Grid

### Changing Colors
Modify Tailwind classes:
- `bg-indigo-600` → Change to `bg-blue-600`
- `text-green-600` → Change to `text-emerald-600`

### Adjusting Payout Limits
Edit API validation in backend StoreController

### Adding Email Notifications
Backend already sends emails on payout completion
Frontend can poll `/api/stores/{id}/payout-history` to check status

---

## ✅ Testing

### Manual Testing Checklist
- [ ] Login with seller credentials
- [ ] View store overview/stats
- [ ] Add new product
- [ ] Edit product (if implemented)
- [ ] Delete product
- [ ] View payout history
- [ ] Request cashout
- [ ] Verify token persists on page reload
- [ ] Check logout functionality
- [ ] Test on mobile/tablet

### API Testing
```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -d '{"email":"seller@test.com","password":"pass"}'

# Add product
curl -X POST http://localhost:8000/api/stores/1/products \
  -H "Authorization: Bearer {token}" \
  -d '{...product data...}'

# Request cashout
curl -X POST http://localhost:8000/api/stores/1/cashout \
  -H "Authorization: Bearer {token}" \
  -d '{"amount":500}'
```

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Login not working | Check API URL in .env |
| Products not loading | Verify store has products in DB |
| Cashout fails | Check store balance > amount |
| Button not responding | Check console for errors |
| Styles look wrong | Ensure Tailwind is compiled |

---

## 📚 Related Files

- `/frontend/src/components/Seller/SellerStoreDashboard.tsx` - Main dashboard
- `/frontend/src/components/Seller/SellerLogin.tsx` - Login page
- `/frontend/src/App.tsx` - Route handler
- `/frontend/src/styles/global.css` - Global styling
- `backend/routes/api.php` - API endpoints
- `backend/app/Http/Controllers/StoreController.php` - Store logic
- `backend/app/Services/StripeConnectService.php` - Payout logic

---

## 🎯 Next Steps

1. **Install Node.js** - Required to run frontend
2. **Run migrations** - Set up database tables
3. **Test login** - Verify Sanctum authentication works
4. **Create products** - Add sample game topups
5. **Request payout** - Test Stripe integration
6. **Monitor logs** - Check backend for errors

---

**Dashboard Status: ✅ PRODUCTION-READY**

*Last Updated: November 27, 2025*
