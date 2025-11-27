import React, { useState } from 'react'
import axios from 'axios'

const paymentMethods = [
  { type: 'card', icon: '💳', label: 'Credit/Debit Card' },
  { type: 'bank', icon: '🏦', label: 'Bank Transfer' },
  { type: 'paypal', icon: '🅿️', label: 'PayPal' },
  { type: 'binance', icon: '₿', label: 'Binance Pay' }
]

export default function SellerOnboarding() {
  const [step, setStep] = useState(1)
  const [store, setStore] = useState(localStorage.getItem('current_store') || '')
  const [docType, setDocType] = useState('passport')
  const [docUrl, setDocUrl] = useState('')
  const [name, setName] = useState('')
  const [country, setCountry] = useState('')
  const [paymentType, setPaymentType] = useState('card')
  const [paymentData, setPaymentData] = useState({})

  const token = localStorage.getItem('sanctum_token')

  const submitVerification = async () => {
    try {
      const res = await axios.post(
        (window as any).REACT_APP_API_BASE + '/verifications',
        {
          store_id: store,
          document_type: docType,
          document_url: docUrl,
          verified_name: name,
          verified_country: country
        },
        { headers: { Authorization: `Bearer ${token}` } }
      )
      alert('Verification submitted! Pending admin review.')
      setStep(2)
    } catch (err: any) {
      alert(err.response?.data?.message || 'Error')
    }
  }

  const addPaymentMethod = async () => {
    try {
      await axios.post(
        (window as any).REACT_APP_API_BASE + '/payment-methods',
        { type: paymentType, metadata: paymentData },
        { headers: { Authorization: `Bearer ${token}` } }
      )
      alert('Payment method added!')
      setPaymentData({})
    } catch (err: any) {
      alert(err.response?.data?.message || 'Error')
    }
  }

  return (
    <div style={{ maxWidth: 600, margin: '20px auto' }}>
      <h2>Seller Onboarding</h2>

      {step === 1 && (
        <div>
          <h3>Identity Verification</h3>
          <div style={{ marginBottom: 12 }}>
            <label>Document Type</label>
            <select value={docType} onChange={(e) => setDocType(e.target.value)} style={{ width: '100%', padding: 8 }}>
              <option value="passport">Passport</option>
              <option value="national_id">National ID</option>
              <option value="drivers_license">Drivers License</option>
              <option value="ssn">SSN / Tax ID</option>
            </select>
          </div>
          <div style={{ marginBottom: 12 }}>
            <label>Full Name (as on document)</label>
            <input value={name} onChange={(e) => setName(e.target.value)} style={{ width: '100%', padding: 8 }} />
          </div>
          <div style={{ marginBottom: 12 }}>
            <label>Country</label>
            <input value={country} onChange={(e) => setCountry(e.target.value)} placeholder="US, HT, etc" style={{ width: '100%', padding: 8 }} />
          </div>
          <div style={{ marginBottom: 12 }}>
            <label>Document URL (or upload location)</label>
            <input value={docUrl} onChange={(e) => setDocUrl(e.target.value)} placeholder="https://..." style={{ width: '100%', padding: 8 }} />
          </div>
          <button onClick={submitVerification}>Submit Verification</button>
        </div>
      )}

      {step === 2 && (
        <div>
          <h3>Add Payment Methods</h3>
          <p style={{ color: '#666' }}>Choose how you want to receive payouts:</p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12, marginBottom: 20 }}>
            {paymentMethods.map((m) => (
              <div
                key={m.type}
                onClick={() => setPaymentType(m.type)}
                style={{
                  padding: 12,
                  border: paymentType === m.type ? '2px solid #007bff' : '1px solid #ccc',
                  borderRadius: 8,
                  cursor: 'pointer',
                  textAlign: 'center'
                }}
              >
                <div style={{ fontSize: 24 }}>{m.icon}</div>
                <div>{m.label}</div>
              </div>
            ))}
          </div>
          <div style={{ marginBottom: 12 }}>
            <label>Account details (vary by method)</label>
            <textarea
              value={JSON.stringify(paymentData)}
              onChange={(e) => setPaymentData(JSON.parse(e.target.value || '{}'))}
              style={{ width: '100%', height: 80, padding: 8 }}
            />
          </div>
          <button onClick={addPaymentMethod}>Add Payment Method</button>
        </div>
      )}
    </div>
  )
}
