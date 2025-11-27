const express = require('express');
const router = express.Router();
const User = require('../models/User');

// Bann yon kont
router.post('/ban', async (req, res) => {
  const { username, reason } = req.body;
  const user = await User.findOne({ username });
  if(!user) return res.json({ success: false, error: 'User not found' });

  user.banned = true;
  user.banReason = reason || 'Violation of rules';
  await user.save();

  res.json({ success: true, message: `${username} is banned.` });
});

// Debann yon kont
router.post('/unban', async (req, res) => {
  const { username } = req.body;
  const user = await User.findOne({ username });
  if(!user) return res.json({ success: false, error: 'User not found' });

  user.banned = false;
  user.banReason = '';
  await user.save();

  res.json({ success: true, message: `${username} is unbanned.` });
});

module.exports = router;