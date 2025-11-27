import React from 'react'
import OrdersAdmin from './OrdersAdmin'

export default function AdminDashboard() {
  return (
    <div style={{ padding: 20 }}>
      <h2>Admin Dashboard</h2>
      <p>Quick links: </p>
      <ul>
        <li><a href="#orders">Orders</a></li>
        <li><a href="#stores">Stores</a></li>
        <li><a href="#rewards">Rewards</a></li>
      </ul>

      <div id="orders">
        <OrdersAdmin />
      </div>
    </div>
  )
}
