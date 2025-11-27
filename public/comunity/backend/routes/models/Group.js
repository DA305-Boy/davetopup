const mongoose = require('mongoose');

const groupSchema = new mongoose.Schema({
  name: String,
  admin: String,
  members: [String],
  createdAt: { type: Date, default: Date.now }
});

module.exports = mongoose.model('Group', groupSchema);