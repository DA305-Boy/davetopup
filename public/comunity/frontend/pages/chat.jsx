import React, { useState, useEffect } from 'react';
import io from 'socket.io-client';

const socket = io('http://localhost:3000');

export default function Chat() {
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const userId = 'dave';
  const recipient = 'mark';

  useEffect(() => {
    socket.emit('join', userId);
    socket.on('newMessage', (msg) => {
      setMessages(prev => [...prev, msg]);
    });
  }, []);

  const sendMessage = () => {
    const msg = { from: userId, to: recipient, content: input, type: 'text' };
    socket.emit('sendMessage', msg);
    setMessages(prev => [...prev, msg]);
    setInput('');
  };

  return (
    <div>
      <div>
        {messages.map((m, i) => (
          <div key={i}><b>{m.from}</b>: {m.content}</div>
        ))}
      </div>
      <input value={input} onChange={e => setInput(e.target.value)} />
      <button onClick={sendMessage}>Send</button>
    </div>
  );
}