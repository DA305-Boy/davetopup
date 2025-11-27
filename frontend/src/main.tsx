import React from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './styles.css'

// Vite env -> Compatibility shims for components originally written for CRA
;(window as any).REACT_APP_API_BASE = (import.meta as any).env.VITE_API_BASE || ''
;(window as any).REACT_APP_STRIPE_PUBLIC_KEY = (import.meta as any).env.VITE_STRIPE_KEY || ''

const root = createRoot(document.getElementById('root')!)
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
