import React, { useEffect, useState } from 'react'
import axios from 'axios'

export default function Chat() {
  const [messages, setMessages] = useState<any[]>([])
  const [body, setBody] = useState('')
  const [name, setName] = useState('')

  const load = async () => {
    try {
      const res = await axios.get((window as any).REACT_APP_API_BASE + '/chat/messages')
      setMessages(res.data.messages || [])
    } catch (err) {
      console.error(err)
    }
  }

  useEffect(() => { load() }, [])

  const send = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await axios.post((window as any).REACT_APP_API_BASE + '/chat/messages', { body, author_name: name })
      setBody('')
      load()
    } catch (err) {
      console.error(err)
    }
  }

  const whatsappThankYou = (text: string) => {
    const encoded = encodeURIComponent(text)
    // opens WhatsApp (mobile or web) with a prefilled message
    window.open(`https://wa.me/?text=${encoded}`, '_blank')
  }

  return (
    <div style={{ maxWidth: 720, margin: '0 auto' }}>
      <h3>Community Chat</h3>
      <div style={{ marginBottom: 12 }}>
        <form onSubmit={send}>
          <div style={{ marginBottom: 8 }}>
            <input placeholder="Your name (optional)" value={name} onChange={(e) => setName(e.target.value)} style={{ width: '100%' }} />
          </div>
          <div style={{ marginBottom: 8 }}>
            <textarea placeholder="Write a message" value={body} onChange={(e) => setBody(e.target.value)} rows={3} style={{ width: '100%' }} />
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button type="submit">Send</button>
            <button type="button" onClick={() => whatsappThankYou('Thanks for using Dave TopUp!')}>WhatsApp Thank You</button>
          </div>
        </form>
      </div>

      <div>
        {messages.map((m) => (
          <div key={m.id} style={{ padding: 8, borderBottom: '1px solid #eee' }}>
            <div style={{ fontSize: 12, color: '#666' }}>{m.author_name || 'Anonymous'} • {new Date(m.created_at).toLocaleString()}</div>
            <div style={{ marginTop: 6 }}>{m.body}</div>
          </div>
        ))}
      </div>
    </div>
  )
}
