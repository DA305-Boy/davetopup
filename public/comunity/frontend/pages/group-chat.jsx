import React, { useState, useEffect } from 'react';
import io from 'socket.io-client';
import axios from 'axios';

const socket = io('http://localhost:3000');

export default function GroupChat({ groupId, username }) {
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [members, setMembers] = useState([]);

  useEffect(() => {
    // Join group room
    socket.emit('join', groupId);

    // Fetch group members
    axios.get(`http://localhost:3000/groups/${groupId}`)
      .then(res => setMembers(res.data.group.members));

    // Listen for messages
    socket.on('newMessage', (msg) => {
      if (msg.groupId === groupId) setMessages(prev => [...prev, msg]);
    });
  }, []);

  const sendMessage = () => {
    const msg = { from: username, to: null, groupId, content: input, type: 'text' };
    socket.emit('sendMessage', msg);
    setMessages(prev => [...prev, msg]);
    setInput('');
  };

  return (
    <div>
      <h2>Group Members: {members.join(', ')}</h2>
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