import React from 'react';

export default function MessageItem({ message }) {
  if(message.type==='voice') {
    return <audio controls src={`http://localhost:3000/uploads/${message.content}`} className="my-1" />;
  } else if(message.type==='sticker') {
    return <img src={`/stickers/${message.content}`} alt="sticker" className="my-1 w-16 h-16" />;
  }
  return <div className="my-1"><b>{message.from}:</b> {message.content}</div>;
}