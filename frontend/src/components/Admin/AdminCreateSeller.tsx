import React, { useState } from 'react';
import axios from 'axios';

export default function AdminCreateSeller() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [phone, setPhone] = useState('');
  const [storeName, setStoreName] = useState('');
  const [country, setCountry] = useState('US');
  const [currency, setCurrency] = useState('USD');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);

  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
  const token = localStorage.getItem('sanctum_token');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);
    setLoading(true);

    try {
      const headers = { Authorization: `Bearer ${token}` };
      const payload = {
        name,
        email,
        password,
        phone,
        store_name: storeName,
        country,
        currency,
      };

      const res = await axios.post(`${API_URL}/admin/stores`, payload, { headers });
      setSuccess(res.data);
      setName(''); setEmail(''); setPassword(''); setPhone(''); setStoreName('');
    } catch (err: any) {
      console.error(err);
      if (err.response?.data?.message) setError(err.response.data.message);
      else setError('Failed to create seller');
    }

    setLoading(false);
  };

  return (
    <div className="p-6 bg-white rounded-lg shadow-md max-w-3xl mx-auto">
      <h2 className="text-2xl font-semibold mb-4">Create Seller Account</h2>
      <p className="text-sm text-gray-600 mb-4">Create a real seller account (email & password). This is admin-only.</p>

      {error && <div className="mb-4 text-red-700">{error}</div>}
      {success && (
        <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded">
          <strong>Seller created successfully.</strong>
          <div className="mt-2">User ID: {success.user?.id}</div>
          <div>Store ID: {success.store?.id}</div>
          <div>Store Slug: {success.store?.slug}</div>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium">Full name</label>
          <input className="mt-1 w-full border rounded p-2" value={name} onChange={e => setName(e.target.value)} required />
        </div>

        <div>
          <label className="block text-sm font-medium">Email</label>
          <input type="email" className="mt-1 w-full border rounded p-2" value={email} onChange={e => setEmail(e.target.value)} required />
        </div>

        <div>
          <label className="block text-sm font-medium">Password</label>
          <input type="password" className="mt-1 w-full border rounded p-2" value={password} onChange={e => setPassword(e.target.value)} required minLength={8} />
        </div>

        <div>
          <label className="block text-sm font-medium">Phone (optional)</label>
          <input className="mt-1 w-full border rounded p-2" value={phone} onChange={e => setPhone(e.target.value)} />
        </div>

        <div>
          <label className="block text-sm font-medium">Store name</label>
          <input className="mt-1 w-full border rounded p-2" value={storeName} onChange={e => setStoreName(e.target.value)} required />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium">Country</label>
            <input className="mt-1 w-full border rounded p-2" value={country} onChange={e => setCountry(e.target.value)} />
          </div>
          <div>
            <label className="block text-sm font-medium">Currency</label>
            <input className="mt-1 w-full border rounded p-2" value={currency} onChange={e => setCurrency(e.target.value)} />
          </div>
        </div>

        <div className="flex items-center gap-4">
          <button type="submit" className="bg-indigo-600 text-white px-4 py-2 rounded" disabled={loading}>
            {loading ? 'Creating...' : 'Create Seller'}
          </button>
        </div>
      </form>
    </div>
  );
}
