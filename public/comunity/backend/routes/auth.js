const express = require('express');
const router = express.Router();
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');
const User = require('../models/User');

const SECRET_KEY = 'YOUR_SECRET_KEY';

// Signup
router.post('/signup', async (req, res) => {
  const { username, phoneNumber, password } = req.body;

  // Check if username or phone already exist in banned users
  const bannedUser = await User.findOne({ 
    $or: [{ username }, { phoneNumber }],
    banned: true
  });
  if(bannedUser) return res.json({ success: false, error: 'This username or phone is banned.' });

  const hash = await bcrypt.hash(password, 10);
  try {
    const user = await User.create({ username, phoneNumber, passwordHash: hash });
    res.json({ success: true, user });
  } catch (err) {
    res.json({ success: false, error: err.message });
  }
});

// Login
router.post('/login', async (req, res) => {
  const { username, password } = req.body;
  const user = await User.findOne({ username });
  if(!user) return res.json({ success: false, error: 'User not found' });
  
  if(user.banned) return res.json({ success: false, error: `This account is banned: ${user.banReason}` });

  const match = await bcrypt.compare(password, user.passwordHash);
  if(!match) return res.json({ success: false, error: 'Incorrect password' });

  const token = jwt.sign({ id: user._id, username }, SECRET_KEY, { expiresIn: '7d' });
  res.json({ success: true, token, user });
});

module.exports = router;