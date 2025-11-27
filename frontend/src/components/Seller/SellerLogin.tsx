import { useState } from 'react';
import axios from 'axios';

export default function SellerLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const response = await axios.post(`${API_URL}/auth/login`, {
        email,
        password,
      });

      // Save token
      localStorage.setItem('sanctum_token', response.data.token);
      
      // Redirect to store dashboard
      window.location.href = '/seller/store';
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed. Please check your credentials.');
    }
    setLoading(false);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-500 via-blue-500 to-blue-600 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo Section */}
        <div className="text-center mb-8">
          <h1 className="text-5xl font-bold text-white mb-2">🏪 Dave TopUp</h1>
          <p className="text-indigo-100 text-lg">Seller Dashboard Login</p>
        </div>

        {/* Login Card */}
        <div className="bg-white rounded-2xl shadow-2xl p-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-6 text-center">Welcome Back!</h2>

          {error && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
              <p className="text-red-800 text-sm font-semibold">❌ {error}</p>
            </div>
          )}

          <form onSubmit={handleLogin} className="space-y-4">
            {/* Email Field */}
            <div>
              <label className="block text-gray-700 font-semibold mb-2">📧 Email Address</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="seller@davetopup.com"
                required
                className="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-600 focus:ring-2 focus:ring-indigo-200 transition"
              />
            </div>

            {/* Password Field */}
            <div>
              <label className="block text-gray-700 font-semibold mb-2">🔐 Password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                required
                className="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-600 focus:ring-2 focus:ring-indigo-200 transition"
              />
            </div>

            {/* Login Button */}
            <button
              type="submit"
              disabled={loading}
              className="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold py-3 rounded-lg transition transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed mt-6"
            >
              {loading ? '⏳ Logging in...' : '🔓 Login to Dashboard'}
            </button>
          </form>

          {/* Divider */}
          <div className="my-6 border-t border-gray-300"></div>

          {/* Demo Info */}
          <div className="bg-blue-50 rounded-lg p-4">
            <p className="text-sm text-gray-700">
              <strong>Demo Credentials:</strong>
              <br />
              Email: seller@davetopup.com
              <br />
              Password: password123
            </p>
          </div>

          {/* Footer Links */}
          <div className="mt-6 text-center space-y-2">
            <p className="text-gray-600 text-sm">
              Don't have an account?{' '}
              <a href="/register" className="text-indigo-600 font-semibold hover:underline">
                Register here
              </a>
            </p>
            <p className="text-gray-600 text-sm">
              <a href="/" className="text-indigo-600 font-semibold hover:underline">
                Back to Home
              </a>
            </p>
          </div>
        </div>

        {/* Features Section */}
        <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-white bg-opacity-20 backdrop-blur rounded-lg p-4 text-white text-center">
            <p className="text-2xl mb-2">📦</p>
            <p className="font-semibold">Manage Products</p>
            <p className="text-xs text-indigo-100">Add and manage your game topups</p>
          </div>
          <div className="bg-white bg-opacity-20 backdrop-blur rounded-lg p-4 text-white text-center">
            <p className="text-2xl mb-2">💰</p>
            <p className="font-semibold">Track Payouts</p>
            <p className="text-xs text-indigo-100">Request cashouts to your bank</p>
          </div>
          <div className="bg-white bg-opacity-20 backdrop-blur rounded-lg p-4 text-white text-center">
            <p className="text-2xl mb-2">📊</p>
            <p className="font-semibold">View Analytics</p>
            <p className="text-xs text-indigo-100">Track your earnings and stats</p>
          </div>
        </div>
      </div>
    </div>
  );
}
