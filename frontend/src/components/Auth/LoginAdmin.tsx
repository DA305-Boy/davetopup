import React, { useState } from 'react'
import axios from 'axios'

export default function LoginAdmin() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const res = await axios.post((window as any).REACT_APP_API_BASE + '/auth/login', {
        email,
        password
      })

      // Store token in localStorage
      localStorage.setItem('sanctum_token', res.data.token)
      localStorage.setItem('sanctum_user', JSON.stringify(res.data.user))

      // Optionally redirect or emit auth event
      window.location.href = '/admin'
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ maxWidth: 420, margin: '40px auto' }}>
      <h2>Admin/Seller Login</h2>
      <form onSubmit={handleLogin}>
        <div style={{ marginBottom: 12 }}>
          <label>Email</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="admin@example.com"
            style={{ width: '100%', padding: 8 }}
            required
          />
        </div>
        <div style={{ marginBottom: 12 }}>
          <label>Password</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
            style={{ width: '100%', padding: 8 }}
            required
          />
        </div>
        {error && <div style={{ color: '#c00', marginBottom: 12 }}>{error}</div>}
        <button type="submit" disabled={loading} style={{ width: '100%', padding: 10 }}>
          {loading ? 'Logging in...' : 'Sign In'}
        </button>
      </form>
    </div>
  )
}
