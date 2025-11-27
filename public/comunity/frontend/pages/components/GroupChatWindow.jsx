import React, { useState, useEffect } from 'react';
import io from 'socket.io-client';
import MessageItem from './MessageItem';
import ChatInput from './ChatInput';
import axios from 'axios';

const socket = io('http://localhost:3000');

export default function GroupChatWindow({ groupId, username }) {
  const [messages, setMessages] = useState([]);
  const [groupName, setGroupName] = useState('');
  const [members, setMembers] = useState([]);

  useEffect(() => {
    socket.emit('join', groupId);

    // Fetch group info
    axios.get(`http://localhost:3000/groups/${groupId}`)
      .then(res => {
        setGroupName(res.data.group.name);
        setMembers(res.data.group.members);
      });

    // Listen for messages
    socket.on('newMessage', (msg) => {
      if(msg.groupId === groupId) setMessages(prev => [...prev, msg]);
    });

    return () => socket.off('newMessage');
  }, [groupId]);

  const sendMessage = (content, type='text') => {
    const msg = { from: username, groupId, content, type };
    socket.emit('sendMessage', msg);
    setMessages(prev => [...prev, msg]);
  };

  return (
    <div className="flex flex-col h-full border rounded-lg p-2 bg-white shadow">
      <div className="mb-2">
        <h2 className="text-lg font-bold">{groupName}</h2>
        <p className="text-sm text-gray-500">Members: {members.join(', ')}</p>
      </div>
      <div className="flex-1 overflow-y-auto mb-2">
        {messages.map((m,i) => <MessageItem key={i} message={m} />)}
      </div>
      <ChatInput sendMessage={sendMessage} />
    </div>
  );
}