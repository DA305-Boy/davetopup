import React, { useState } from 'react';

export default function Settings() {
  const [theme, setTheme] = useState('light');
  const [notifications, setNotifications] = useState(true);

  return (
    <div className="max-w-md mx-auto p-4 bg-white shadow rounded">
      <h2 className="text-xl font-bold mb-2">Settings</h2>
      <div className="mb-2">
        <label className="mr-2">Theme:</label>
        <select value={theme} onChange={e=>setTheme(e.target.value)} className="border px-2 py-1 rounded">
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select>
      </div>
      <div className="mb-2">
        <label className="mr-2">Notifications:</label>
        <input type="checkbox" checked={notifications} onChange={e=>setNotifications(e.target.checked)} />
      </div>
    </div>
  );
}