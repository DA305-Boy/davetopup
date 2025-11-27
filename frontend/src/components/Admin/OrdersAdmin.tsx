import React, { useEffect, useState } from 'react'
import axios from 'axios'

export default function OrdersAdmin() {
  const [orders, setOrders] = useState<any[]>([])

  const load = async () => {
    try {
      const res = await axios.get((window as any).REACT_APP_API_BASE + '/admin/orders')
      setOrders(res.data.orders.data || [])
    } catch (err) {
      console.error(err)
    }
  }

  useEffect(() => { load() }, [])

  const markDelivered = async (id: number) => {
    try {
      await axios.post((window as any).REACT_APP_API_BASE + `/admin/orders/${id}/mark-delivered`)
      load()
    } catch (err) { console.error(err) }
  }

  const refund = async (id: number) => {
    try {
      await axios.post((window as any).REACT_APP_API_BASE + `/admin/orders/${id}/refund`)
      load()
    } catch (err) { console.error(err) }
  }

  return (
    <div>
      <h3>Recent Orders</h3>
      <div>
        {orders.map((o) => (
          <div key={o.id} style={{ padding: 8, borderBottom: '1px solid #eee' }}>
            <div><strong>Order #{o.id}</strong> — {o.status} — {new Date(o.created_at).toLocaleString()}</div>
            <div>Amount: ${o.total}</div>
            <div style={{ marginTop: 8 }}>
              <button onClick={() => markDelivered(o.id)}>Mark Delivered</button>
              <button onClick={() => refund(o.id)} style={{ marginLeft: 8 }}>Refund</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
