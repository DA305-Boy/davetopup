import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function Profile({ userId }) {
  const [user, setUser] = useState({username:'', avatar:'', status:''});

  useEffect(() => {
    axios.get(`http://localhost:3000/auth/user/${userId}`)
      .then(res => setUser(res.data.user));
  }, [userId]);

  const handleUpdate = () => {
    axios.put(`http://localhost:3000/auth/user/${userId}`, user)
      .then(res => alert('Profile updated!'));
  };

  return (
    <div className="max-w-md mx-auto p-4 bg-white shadow rounded">
      <h2 className="text-xl font-bold mb-2">Profile</h2>
      <input className="border rounded px-2 py-1 w-full mb-2" 
             value={user.username} 
             onChange={e => setUser({...user, username:e.target.value})} />
      <input className="border rounded px-2 py-1 w-full mb-2" 
             value={user.status} 
             onChange={e => setUser({...user, status:e.target.value})} />
      <button onClick={handleUpdate} className="bg-blue-500 text-white px-4 py-2 rounded">Update</button>
    </div>
  );
}