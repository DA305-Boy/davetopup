const multer = require('multer');
const path = require('path');

const storage = multer.diskStorage({
  destination: './uploads/voice',
  filename: (req, file, cb) => cb(null, Date.now() + path.extname(file.originalname))
});

const upload = multer({ storage });

router.post('/upload-voice', upload.single('voice'), async (req, res) => {
  res.json({ success: true, filename: req.file.filename });
});