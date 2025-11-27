chat-app/
│
├─ frontend/   (React + TailwindCSS)
│   ├─ pages/
│   │   ├─ login.jsx
│   │   ├─ signup.jsx
│   │   ├─ profile.jsx
│   │   ├─ chat.jsx
│   │   ├─ group-chat.jsx
│   │   ├─ settings.jsx
│   │   └─ help.jsx
│   ├─ components/
│   │   ├─ MessageItem.jsx
│   │   ├─ ChatInput.jsx
│   │   ├─ VoiceRecorder.jsx
│   │   └─ StickerPicker.jsx
│   └─ context/ (UserContext, SocketContext)
│
├─ backend/   (Node.js + Express + Socket.IO)
│   ├─ server.js
│   ├─ routes/
│   │   ├─ auth.js
│   │   ├─ chat.js
│   │   ├─ groups.js
│   │   └─ settings.js
│   ├─ models/
│   │   ├─ User.js
│   │   ├─ Message.js
│   │   └─ Group.js
│   └─ utils/
│       ├─ encrypt.js   (AES-256)
│       └─ mediaHandler.js
│
└─ database/   (MongoDB)
    └─ Collections: users, messages, groups