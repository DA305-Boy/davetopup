import React, { useState, useEffect } from 'react';
import io from 'socket.io-client';
import MessageItem from './MessageItem';
import ChatInput from './ChatInput';

const socket = io('http://localhost:3000');

export default function ChatWindow({ userId, recipientId }) {
  const [messages, setMessages] = useState([]);

  useEffect(() => {
    socket.emit('join', userId);
    socket.on('newMessage', (msg) => {
      if (msg.to === userId || msg.from === userId) setMessages(prev => [...prev, msg]);
    });
  }, []);

  const sendMessage = (content, type='text') => {
    const msg = { from: userId, to: recipientId, content, type };
    socket.emit('sendMessage', msg);
    setMessages(prev => [...prev, msg]);
  };

  return (
    <div className="flex flex-col h-full border rounded-lg p-2 bg-white shadow">
      <div className="flex-1 overflow-y-auto mb-2">
        {messages.map((msg,i) => <MessageItem key={i} message={msg} />)}
      </div>
      <ChatInput sendMessage={sendMessage} />
    </div>
  );
}