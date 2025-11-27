const sendSticker = (sticker) => {
  const msg = { from: username, to: null, groupId, content: sticker, type: 'sticker' };
  socket.emit('sendMessage', msg);
  setMessages(prev => [...prev, msg]);
};