const express = require('express');
const router = express.Router();
const Group = require('../models/Group');

// Create a new group
router.post('/create', async (req, res) => {
  const { name, admin, members } = req.body;
  try {
    const group = await Group.create({ name, admin, members: [admin, ...members] });
    res.json({ success: true, group });
  } catch (err) {
    res.json({ success: false, error: err.message });
  }
});

// Add member to group (admin only)
router.post('/add-member', async (req, res) => {
  const { groupId, member, admin } = req.body;
  const group = await Group.findById(groupId);
  if (group.admin !== admin) return res.json({ success: false, error: 'Only admin can add members' });

  if (!group.members.includes(member)) {
    group.members.push(member);
    await group.save();
  }
  res.json({ success: true, group });
});

// Remove member from group (admin only)
router.post('/remove-member', async (req, res) => {
  const { groupId, member, admin } = req.body;
  const group = await Group.findById(groupId);
  if (group.admin !== admin) return res.json({ success: false, error: 'Only admin can remove members' });

  group.members = group.members.filter(m => m !== member);
  await group.save();
  res.json({ success: true, group });
});

module.exports = router;