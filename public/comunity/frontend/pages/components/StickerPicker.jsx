import React from 'react';

const stickers = ['sticker1.png', 'sticker2.png', 'sticker3.png'];

export default function StickerPicker({ sendMessage }) {
  return (
    <div className="flex space-x-1">
      {stickers.map((s,i) => (
        <img key={i} src={`/stickers/${s}`} alt="sticker" className="w-10 h-10 cursor-pointer" 
          onClick={()=>sendMessage(s,'sticker')} />
      ))}
    </div>
  );
}