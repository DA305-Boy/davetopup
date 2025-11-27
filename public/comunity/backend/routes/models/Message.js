const mongoose = require('mongoose');

const messageSchema = new mongoose.Schema({
  from: String,
  to: String,
  groupId: { type: String, default: null },
  type: { type: String, enum: ['text', 'voice', 'sticker'], default: 'text' },
  content: String,
  timestamp: { type: Date, default: Date.now }
});

module.exports = mongoose.model('Message', messageSchema);