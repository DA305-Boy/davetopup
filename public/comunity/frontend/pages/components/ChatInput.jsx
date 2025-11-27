import React, { useState } from 'react';
import { ReactMic } from 'react-mic';
import StickerPicker from './StickerPicker';

export default function ChatInput({ sendMessage }) {
  const [input, setInput] = useState('');
  const [record, setRecord] = useState(false);

  const handleSend = () => {
    if(input.trim()!=='') sendMessage(input, 'text');
    setInput('');
  };

  const startRecording = () => setRecord(true);
  const stopRecording = () => setRecord(false);


const onStop = (recordedBlob) => {
  const formData = new FormData();
  formData.append('voice', recordedBlob.blob, `voice-${Date.now()}.mp3`);
  axios.post('http://localhost:3000/chat/upload-voice', formData)
    .then(res => sendMessage(res.data.filename, 'voice'));
};

  return (
    <div className="flex items-center space-x-2">
      <input 
        className="flex-1 border rounded px-2 py-1" 
        value={input} 
        onChange={e=>setInput(e.target.value)} 
        placeholder="Type a message..." 
      />
      <button onClick={handleSend} className="bg-blue-500 text-white px-3 py-1 rounded">Send</button>
      <button onMouseDown={startRecording} onMouseUp={stopRecording} className="bg-green-500 text-white px-3 py-1 rounded">🎤</button>
      <StickerPicker sendMessage={sendMessage} />
      <ReactMic record={record} onStop={onStop} />
    </div>
  );
}