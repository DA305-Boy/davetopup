import React, { useState } from 'react';
import axios from 'axios';

export default function Help({ userId }) {
  const [message, setMessage] = useState('');

  const handleSubmit = () => {
    axios.post('http://localhost:3000/help', { userId, message })
      .then(res => alert('Message sent to support!'));
    setMessage('');
  };

  return (
    <div className="max-w-md mx-auto p-4 bg-white shadow rounded">
      <h2 className="text-xl font-bold mb-2">Help / Support</h2>
      <textarea className="w-full border rounded px-2 py-1 mb-2" 
                rows="4" 
                value={message} 
                onChange={e=>setMessage(e.target.value)} 
                placeholder="Type your message..."></textarea>
      <button onClick={handleSubmit} className="bg-green-500 text-white px-4 py-2 rounded">Send</button>
    </div>
  );
}