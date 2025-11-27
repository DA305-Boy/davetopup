import React, { useState, useEffect } from 'react'
import axios from 'axios'

export default function PaymentLinkGenerator() {
  const [links, setLinks] = useState<any[]>([])
  const [title, setTitle] = useState('')
  const [amount, setAmount] = useState('')
  const [loading, setLoading] = useState(false)

  const token = localStorage.getItem('sanctum_token')

  const load = async () => {
    try {
      const res = await axios.get(
        (window as any).REACT_APP_API_BASE + '/payment-links',
        { headers: { Authorization: `Bearer ${token}` } }
      )
      setLinks(res.data.payment_links || [])
    } catch (err) { console.error(err) }
  }

  useEffect(() => { load() }, [])

  const createLink = async () => {
    if (!title || !amount) return
    setLoading(true)
    try {
      const res = await axios.post(
        (window as any).REACT_APP_API_BASE + '/payment-links',
        { title, amount, currency: 'USD', store_id: 1 },
        { headers: { Authorization: `Bearer ${token}` } }
      )
      setTitle('')
      setAmount('')
      load()
    } catch (err: any) {
      alert(err.response?.data?.message || 'Error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ maxWidth: 700, margin: '20px auto' }}>
      <h2>💳 Payment Link Generator</h2>

      <div style={{ background: '#f7f7f7', padding: 16, borderRadius: 8, marginBottom: 20 }}>
        <h3>Create New Payment Link</h3>
        <div style={{ display: 'grid', gap: 12 }}>
          <div>
            <label>Title</label>
            <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Payment for..." style={{ width: '100%', padding: 8 }} />
          </div>
          <div>
            <label>Amount ($USD)</label>
            <input type="number" value={amount} onChange={(e) => setAmount(e.target.value)} step="0.01" style={{ width: '100%', padding: 8 }} />
          </div>
          <button onClick={createLink} disabled={loading}>
            {loading ? 'Creating...' : 'Generate Link'}
          </button>
        </div>
      </div>

      <h3>Your Payment Links</h3>
      {links.length === 0 ? (
        <p style={{ color: '#999' }}>No links yet. Create one to get started!</p>
      ) : (
        links.map((l) => (
          <div key={l.id} style={{ padding: 12, border: '1px solid #ddd', borderRadius: 6, marginBottom: 8 }}>
            <strong>{l.title}</strong> — ${l.amount}
            <div style={{ fontSize: 12, color: '#666', marginTop: 4 }}>
              Share: <code style={{ background: '#f0f0f0', padding: 4 }}>
                {window.location.origin}/payment/{l.token}
              </code>
              <button onClick={() => navigator.clipboard.writeText(`${window.location.origin}/payment/${l.token}`)} style={{ marginLeft: 8 }}>
                Copy
              </button>
            </div>
          </div>
        ))
      )}
    </div>
  )
}
