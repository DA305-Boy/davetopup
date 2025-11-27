import React, { useState } from 'react'

export default function Login() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    // This is a placeholder: integrate with Laravel Sanctum or Firebase later.
      try {
        // For OAuth approach we redirect the browser to the provider
        // The backend routes are: /auth/redirect/google or /auth/redirect/facebook
        // For SPA session tokens we expect the backend to redirect back to the frontend with a token.
        window.location.href = '/auth/redirect/google'
    } catch (err) {
      setError('Login failed')
    }
  }

  return (
    <div style={{ maxWidth: 420, margin: '0 auto' }}>
      <h2>Sign in</h2>
      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: 8 }}>
          <label>Email</label>
          <input style={{ width: '100%' }} value={email} onChange={(e) => setEmail(e.target.value)} />
        </div>
        <div style={{ marginBottom: 8 }}>
          <label>Password</label>
          <input type="password" style={{ width: '100%' }} value={password} onChange={(e) => setPassword(e.target.value)} />
        </div>
        {error && <div style={{ color: 'red' }}>{error}</div>}
          <button type="submit">Sign In with Google</button>
        </form>

        <div style={{ marginTop: 12 }}>
          <strong>Or sign in with:</strong>
          <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
            <a href="/auth/redirect/google"><button>Google</button></a>
            <a href="/auth/redirect/facebook"><button>Facebook</button></a>
          </div>
        </div>
      </form>
    </div>
  )
}
