const mongoose = require('mongoose');

const userSchema = new mongoose.Schema({
  username: { type: String, unique: true },
  phoneNumber: { type: String, unique: true }, // optional phone
  passwordHash: String,
  avatar: String,
  status: { type: String, default: 'online' },
  banned: { type: Boolean, default: false }, // bann flag
  banReason: { type: String, default: '' }, // reason for ban
  createdAt: { type: Date, default: Date.now }
});

module.exports = mongoose.model('User', userSchema);