import React from 'react'
import Checkout from './components/Checkout/Checkout'
import AdminDashboard from './components/Admin/AdminDashboard'
import AdminMarketplaceDashboard from './components/AdminMarketplaceDashboard'
import SellerStoreDashboard from './components/Seller/SellerStoreDashboard'
import AdminCreateSeller from './components/Admin/AdminCreateSeller'

const sampleOrder = {
  id: 'order_123',
  items: [
    {
      id: 'item_1',
      name: 'Free Fire Diamonds',
      game: 'Free Fire',
      price: 9.99,
      quantity: 1,
      playerUid: '123456'
    }
  ],
  subtotal: 9.99,
  tax: 0.5,
  total: 10.49
}

export default function App() {
  // Route based on URL or component selection
  const route = window.location.pathname;
  
  if (route.includes('/admin/marketplace')) {
    return <AdminMarketplaceDashboard />;
  }

  if (route.includes('/admin/create-store') || route.includes('/admin/create-seller')) {
    return <AdminCreateSeller />;
  }

  if (route.includes('/seller/store')) {
    return <SellerStoreDashboard />;
  }

  return (
    <div style={{ padding: 20 }}>
      <h1>Dave TopUp Demo</h1>
      <div style={{ display: 'flex', gap: 24 }}>
        <div style={{ flex: 2 }}>
          <Checkout order={sampleOrder} />
        </div>
        <div style={{ flex: 1 }}>
          <AdminDashboard />
        </div>
      </div>
    </div>
  )
}
