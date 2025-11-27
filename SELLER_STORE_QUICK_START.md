# 🚀 Seller Store Dashboard - Quick Start Guide

## 📋 What's New

✅ **Seller Store Dashboard** - Complete product and payout management  
✅ **Seller Login** - Email/password authentication  
✅ **Product Management** - Add, view, delete products  
✅ **Payout Tracking** - Request cashouts and view history  
✅ **Modern UI** - Beautiful Tailwind CSS styling with emojis  

---

## 🎯 How to Access

### URL Path
```
https://www.davetopup.com/seller/store
```

### Auto-Redirect
If logged in as seller, automatically loads dashboard

### Login Required
Requires valid seller Sanctum token in localStorage

---

## 🔓 Login Credentials (Demo)

```
📧 Email:    seller@davetopup.com
🔐 Password: password123
```

---

## 📱 Dashboard Tabs

### 1️⃣ Overview Tab (📊)
**Stats Overview**
- Store Balance: Available funds for cashout
- Products: Number of active listings
- Status: Store verification status (✓ Active or ⏳ Pending)
- Joined: Store creation date

**Quick Actions**
- ➕ Add New Product
- 💸 Request Cashout
- 🔄 Refresh Data

### 2️⃣ Products Tab (📦)
**Product Management**
- ➕ Add Product Form
- 📋 Product Grid (cards with image, price, stock)
- 🗑️ Delete button on each product

**Add Product Fields:**
```
✓ Product Name      (e.g., "100 Free Fire Diamonds")
✓ Game Name         (e.g., "Free Fire")
✓ Price             (e.g., "$9.99")
✓ Category          (Dropdown: Diamonds, Credits, Coins, Premium, Other)
✓ Stock Quantity    (e.g., "1000")
✓ Description       (Optional details)
✓ Image URL         (Optional product image)
```

### 3️⃣ Payouts Tab (💰)
**Payout Stats**
- Available Balance: Ready for cashout
- Total Payouts: Sum of all completed payouts
- Pending Payouts: Count of processing transfers

**Cashout Form**
- Amount input field
- Max: Current store balance
- 💰 Cashout button

**Payout History Table**
- Amount & Currency
- Status badge (Pending/Processing/Completed/Failed)
- Transaction date
- Stripe Transfer ID

---

## 🎨 UI Features

### Visual Elements
- 🏪 Emoji icons for clarity
- 💳 Color-coded status badges
- 📊 Stats cards with icons and colors
- 🎨 Gradient backgrounds (indigo-to-blue)
- ⚡ Smooth hover animations
- 📱 Responsive grid layouts

### Color Coding
- 🔵 **Indigo/Blue** - Primary actions, overview
- 🟢 **Green** - Success, earnings
- 🟠 **Orange** - Status/pending
- 🔴 **Red** - Delete/danger

---

## 🔗 API Integration

### Required Backend Endpoints

```
Authentication:
POST   /api/auth/login         → Get Sanctum token
GET    /api/auth/me            → Get seller info

Store:
GET    /api/stores?owner=true  → Get seller's store
GET    /api/stores/{id}/products

Products:
POST   /api/stores/{id}/products
DELETE /api/stores/{id}/products/{productId}

Payouts:
POST   /api/stores/{id}/cashout
GET    /api/stores/{id}/payout-history
```

### Authentication Header
```
Authorization: Bearer {sanctum_token}
```

Token stored in localStorage and used for all authenticated requests.

---

## 📦 Files Added/Modified

### New Files
```
frontend/src/components/Seller/SellerStoreDashboard.tsx  (350+ lines)
frontend/src/components/Seller/SellerLogin.tsx           (150+ lines)
frontend/src/styles/global.css                          (CSS framework)
```

### Modified Files
```
frontend/src/App.tsx                  (Added /seller/store route)
```

### Documentation
```
SELLER_STORE_DASHBOARD.md            (Complete feature guide)
SELLER_STORE_QUICK_START.md          (This file)
```

---

## 🚀 Getting Started

### Step 1: Install Dependencies
```bash
cd frontend
npm install
```

### Step 2: Start Dev Server
```bash
npm run dev
```

### Step 3: Access Dashboard
```
http://localhost:5173/seller/store
```

### Step 4: Login
```
Email: seller@davetopup.com
Password: password123
```

---

## 💡 Common Tasks

### Add a Product
1. Go to **Products** tab
2. Click **➕ Add Product**
3. Fill form:
   - Name: "500 Diamonds"
   - Game: "Free Fire"
   - Price: 4.99
   - Category: Diamonds
   - Stock: 1000
4. Click **Save Product**

### Request Cashout
1. Go to **Payouts** tab
2. Enter amount (e.g., 500)
3. Click **💰 Cashout**
4. Status changes to "processing"
5. Email sent when complete

### Check Payout Status
1. Go to **Payouts** tab
2. Scroll to **Payout History**
3. View status column:
   - 🟡 Pending (just received)
   - 🔵 Processing (Stripe transfer in progress)
   - ✅ Completed (transferred to bank)
   - ❌ Failed (will auto-retry)

---

## 🎨 Styling Notes

### Tailwind CSS Framework
Dashboard uses Tailwind utilities:
- Responsive grids (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`)
- Flex layouts (`flex`, `flex-col`, `gap-4`)
- Gradients (`bg-gradient-to-r`)
- Shadows (`shadow`, `shadow-lg`)
- Rounded corners (`rounded-lg`)
- Spacing (`p-6`, `mb-4`, `mt-8`)

### Global CSS
Located in `frontend/src/styles/global.css`:
- CSS variables for colors
- Reusable utility classes
- Media queries for responsive design
- Print styles included

### Button Styles
All buttons use consistent styling:
```tsx
className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition"
```

---

## 🔧 Customization Guide

### Change Primary Color
Replace all `indigo-` classes with desired color:
```
indigo-600  → blue-600
indigo-700  → blue-700
indigo-100  → blue-100
```

### Add New Product Category
Edit dropdown in SellerStoreDashboard.tsx:
```tsx
<option value="new-category">New Category</option>
```

### Modify Stats Cards
Edit grid in Overview tab:
```tsx
<div className="grid grid-cols-1 md:grid-cols-4 gap-4">
  {/* Add more stat cards here */}
</div>
```

### Change Payout Status Colors
Modify badge styling:
```tsx
payout.status === 'completed' ? 'bg-green-100 text-green-800' : '...'
```

---

## ✅ Quality Checklist

- ✅ Responsive design (mobile/tablet/desktop)
- ✅ Accessible forms with labels
- ✅ Error handling for failed requests
- ✅ Loading states during API calls
- ✅ Professional UI with gradients
- ✅ Emoji icons for clarity
- ✅ Color-coded status indicators
- ✅ Token persistence in localStorage
- ✅ Smooth transitions and animations
- ✅ Table with sortable data
- ✅ Form validation on client-side
- ✅ Modal/overlay loading indicator

---

## 🐛 Troubleshooting

### "Login failed" Error
- Check email is correct
- Verify password
- Ensure backend is running
- Check VITE_API_URL in .env

### Products not showing
- Verify store has products in database
- Check network tab for 404 errors
- Ensure seller owns the store

### Cashout button disabled
- Verify balance > 0
- Enter valid amount
- Check Sanctum token is valid

### Styles not loading
- Run `npm run dev` from frontend directory
- Clear browser cache
- Restart dev server

### Page shows 403 Forbidden
- Token might be expired
- Log out and log back in
- Check localStorage for token

---

## 📞 Support

For issues, check:
1. Backend routes in `/api.php` are registered
2. Models have correct relationships
3. Sanctum middleware is enabled
4. CORS is configured for frontend URL
5. Environment variables are set

---

## 🎯 Next Steps

1. **Test Product Add** - Create sample products
2. **Test Cashout** - Request a payout
3. **Check Stripe** - Verify transfer in dashboard
4. **Monitor Logs** - Review backend logs for errors
5. **Deploy** - Ready for production

---

**Status: ✅ READY FOR TESTING**

Access now at: `/seller/store` 🏪

*Updated: November 27, 2025*
