import { useState, useEffect } from 'react';
import axios from 'axios';

interface Product {
  id: number;
  name: string;
  description: string;
  price: number;
  game: string;
  category: string;
  stock: number;
  image_url: string;
  created_at: string;
}

interface Store {
  id: number;
  store_name: string;
  owner_id: number;
  stripe_account_id: string;
  balance: number;
  status: string;
  created_at: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  avatar_url?: string;
  phone?: string;
}

export default function SellerStoreDashboard() {
  const [activeTab, setActiveTab] = useState<'overview' | 'products' | 'payouts'>('overview');
  const [store, setStore] = useState<Store | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(false);
  const [showAddProduct, setShowAddProduct] = useState(false);
  const [cashoutAmount, setCashoutAmount] = useState('');
  const [payoutHistory, setPayoutHistory] = useState<any[]>([]);

  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
  const token = localStorage.getItem('sanctum_token');

  useEffect(() => {
    loadStoreData();
  }, []);

  const loadStoreData = async () => {
    setLoading(true);
    try {
      const headers = { Authorization: `Bearer ${token}` };
      
      // Get authenticated user
      const userRes = await axios.get(`${API_URL}/auth/me`, { headers });
      setUser(userRes.data.user || userRes.data);

      // Get user's store (assuming first store belongs to user)
      const storesRes = await axios.get(`${API_URL}/stores?owner=true`, { headers });
      const userStore = storesRes.data.data?.[0] || storesRes.data[0];
      setStore(userStore);

      if (userStore?.id) {
        // Get products
        const productsRes = await axios.get(`${API_URL}/stores/${userStore.id}/products`, { headers });
        setProducts(productsRes.data.data || productsRes.data);

        // Get payout history
        const payoutsRes = await axios.get(`${API_URL}/stores/${userStore.id}/payout-history`, { headers });
        setPayoutHistory(payoutsRes.data.data || payoutsRes.data);
      }
    } catch (error) {
      console.error('Error loading store data:', error);
    }
    setLoading(false);
  };

  const handleAddProduct = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    
    try {
      const headers = { Authorization: `Bearer ${token}` };
      await axios.post(
        `${API_URL}/stores/${store?.id}/products`,
        Object.fromEntries(formData),
        { headers }
      );
      setShowAddProduct(false);
      loadStoreData();
    } catch (error) {
      console.error('Error adding product:', error);
    }
  };

  const handleCashout = async () => {
    if (!cashoutAmount || parseFloat(cashoutAmount) <= 0) {
      alert('Please enter a valid amount');
      return;
    }

    try {
      const headers = { Authorization: `Bearer ${token}` };
      const response = await axios.post(
        `${API_URL}/stores/${store?.id}/cashout`,
        { amount: parseFloat(cashoutAmount) },
        { headers }
      );

      alert('Payout request sent! Status: ' + response.data.status);
      setCashoutAmount('');
      loadStoreData();
    } catch (error: any) {
      alert('Error: ' + (error.response?.data?.error || 'Failed to process cashout'));
    }
  };

  const handleDeleteProduct = async (productId: number) => {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
      const headers = { Authorization: `Bearer ${token}` };
      await axios.delete(
        `${API_URL}/stores/${store?.id}/products/${productId}`,
        { headers }
      );
      loadStoreData();
    } catch (error) {
      console.error('Error deleting product:', error);
    }
  };

  if (!store || !user) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
          <p className="text-gray-600 mt-4">Loading your store...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
      {/* Header */}
      <div className="bg-gradient-to-r from-indigo-600 to-blue-600 text-white p-6 shadow-lg">
        <div className="max-w-7xl mx-auto flex justify-between items-center">
          <div>
            <h1 className="text-4xl font-bold">{store.store_name} 🏪</h1>
            <p className="text-indigo-100 mt-1">Seller Dashboard</p>
          </div>
          <div className="text-right">
            <div className="flex items-center gap-4">
              {user.avatar_url && (
                <img
                  src={user.avatar_url}
                  alt={user.name}
                  className="w-12 h-12 rounded-full border-2 border-white"
                />
              )}
              <div>
                <p className="font-semibold text-lg">{user.name}</p>
                <p className="text-indigo-100 text-sm">{user.email}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto p-6">
        {/* Tabs */}
        <div className="flex gap-2 mb-6 bg-white rounded-lg shadow p-2">
          <button
            onClick={() => setActiveTab('overview')}
            className={`px-6 py-2 rounded-lg font-semibold transition ${
              activeTab === 'overview'
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            📊 Overview
          </button>
          <button
            onClick={() => setActiveTab('products')}
            className={`px-6 py-2 rounded-lg font-semibold transition ${
              activeTab === 'products'
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            📦 Products
          </button>
          <button
            onClick={() => setActiveTab('payouts')}
            className={`px-6 py-2 rounded-lg font-semibold transition ${
              activeTab === 'payouts'
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            💰 Payouts
          </button>
        </div>

        {/* Overview Tab */}
        {activeTab === 'overview' && (
          <div className="space-y-6">
            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-600">
                <p className="text-gray-600 text-sm font-semibold uppercase">Store Balance</p>
                <p className="text-3xl font-bold text-indigo-600 mt-2">${store.balance.toFixed(2)}</p>
                <p className="text-gray-500 text-xs mt-1">Available for cashout</p>
              </div>
              
              <div className="bg-white rounded-lg shadow p-6 border-l-4 border-green-600">
                <p className="text-gray-600 text-sm font-semibold uppercase">Products</p>
                <p className="text-3xl font-bold text-green-600 mt-2">{products.length}</p>
                <p className="text-gray-500 text-xs mt-1">Active listings</p>
              </div>

              <div className="bg-white rounded-lg shadow p-6 border-l-4 border-orange-600">
                <p className="text-gray-600 text-sm font-semibold uppercase">Status</p>
                <p className="text-xl font-bold mt-2">
                  <span className={`px-3 py-1 rounded-full text-white text-sm ${
                    store.status === 'active' ? 'bg-green-500' : 'bg-yellow-500'
                  }`}>
                    {store.status === 'active' ? '✓ Active' : '⏳ Pending'}
                  </span>
                </p>
                <p className="text-gray-500 text-xs mt-1">Store verification</p>
              </div>

              <div className="bg-white rounded-lg shadow p-6 border-l-4 border-blue-600">
                <p className="text-gray-600 text-sm font-semibold uppercase">Joined</p>
                <p className="text-lg font-bold text-blue-600 mt-2">
                  {new Date(store.created_at).toLocaleDateString()}
                </p>
                <p className="text-gray-500 text-xs mt-1">Since this date</p>
              </div>
            </div>

            {/* Quick Actions */}
            <div className="bg-white rounded-lg shadow p-6">
              <h2 className="text-2xl font-bold mb-4">💡 Quick Actions</h2>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button
                  onClick={() => setActiveTab('products')}
                  className="bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white p-4 rounded-lg font-semibold transition transform hover:scale-105"
                >
                  ➕ Add New Product
                </button>
                <button
                  onClick={() => setActiveTab('payouts')}
                  className="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white p-4 rounded-lg font-semibold transition transform hover:scale-105"
                >
                  💸 Request Cashout
                </button>
                <button
                  onClick={loadStoreData}
                  className="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white p-4 rounded-lg font-semibold transition transform hover:scale-105"
                >
                  🔄 Refresh Data
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Products Tab */}
        {activeTab === 'products' && (
          <div className="space-y-6">
            <div className="flex justify-between items-center">
              <h2 className="text-2xl font-bold">📦 My Products</h2>
              <button
                onClick={() => setShowAddProduct(!showAddProduct)}
                className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition"
              >
                {showAddProduct ? '✖ Cancel' : '➕ Add Product'}
              </button>
            </div>

            {/* Add Product Form */}
            {showAddProduct && (
              <form onSubmit={handleAddProduct} className="bg-white rounded-lg shadow p-6 space-y-4">
                <h3 className="text-lg font-bold mb-4">Add New Product</h3>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <input
                    type="text"
                    name="name"
                    placeholder="Product Name"
                    required
                    className="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  />
                  <input
                    type="text"
                    name="game"
                    placeholder="Game Name"
                    required
                    className="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <input
                    type="number"
                    name="price"
                    placeholder="Price ($)"
                    step="0.01"
                    required
                    className="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  />
                  <input
                    type="number"
                    name="stock"
                    placeholder="Stock Quantity"
                    required
                    className="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  />
                </div>

                <select
                  name="category"
                  required
                  className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                  <option value="">Select Category</option>
                  <option value="diamonds">Diamonds</option>
                  <option value="credits">Credits</option>
                  <option value="coins">Coins</option>
                  <option value="premium">Premium</option>
                  <option value="other">Other</option>
                </select>

                <textarea
                  name="description"
                  placeholder="Product Description"
                  rows={3}
                  className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />

                <input
                  type="url"
                  name="image_url"
                  placeholder="Image URL"
                  className="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />

                <button
                  type="submit"
                  className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-semibold transition"
                >
                  Save Product
                </button>
              </form>
            )}

            {/* Products List */}
            {products.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {products.map((product) => (
                  <div key={product.id} className="bg-white rounded-lg shadow hover:shadow-lg transition overflow-hidden">
                    {product.image_url && (
                      <img src={product.image_url} alt={product.name} className="w-full h-40 object-cover bg-gray-200" />
                    )}
                    <div className="p-4">
                      <h3 className="font-bold text-lg">{product.name}</h3>
                      <p className="text-gray-600 text-sm">{product.game}</p>
                      <p className="text-gray-700 text-sm mt-2">{product.description}</p>
                      
                      <div className="flex justify-between items-center mt-4 pt-4 border-t">
                        <div>
                          <p className="text-2xl font-bold text-indigo-600">${product.price.toFixed(2)}</p>
                          <p className="text-xs text-gray-500">Stock: {product.stock}</p>
                        </div>
                        <button
                          onClick={() => handleDeleteProduct(product.id)}
                          className="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition"
                        >
                          🗑️ Delete
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="bg-white rounded-lg shadow p-12 text-center">
                <p className="text-gray-500 text-lg">No products yet. Add your first product! 📦</p>
              </div>
            )}
          </div>
        )}

        {/* Payouts Tab */}
        {activeTab === 'payouts' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow p-6">
                <p className="text-green-100 text-sm">Available Balance</p>
                <p className="text-4xl font-bold mt-2">${store.balance.toFixed(2)}</p>
              </div>
              <div className="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg shadow p-6">
                <p className="text-blue-100 text-sm">Total Payouts</p>
                <p className="text-4xl font-bold mt-2">
                  ${payoutHistory.reduce((sum, p) => sum + (p.amount || 0), 0).toFixed(2)}
                </p>
              </div>
              <div className="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg shadow p-6">
                <p className="text-indigo-100 text-sm">Pending Payouts</p>
                <p className="text-4xl font-bold mt-2">
                  {payoutHistory.filter(p => p.status === 'processing').length}
                </p>
              </div>
            </div>

            {/* Cashout Form */}
            <div className="bg-white rounded-lg shadow p-6">
              <h2 className="text-2xl font-bold mb-4">💸 Request Cashout</h2>
              <div className="space-y-4">
                <div>
                  <label className="block text-gray-700 font-semibold mb-2">Amount ($)</label>
                  <div className="flex gap-2">
                    <input
                      type="number"
                      value={cashoutAmount}
                      onChange={(e) => setCashoutAmount(e.target.value)}
                      placeholder="Enter amount"
                      step="0.01"
                      max={store.balance}
                      className="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    />
                    <button
                      onClick={handleCashout}
                      className="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition"
                    >
                      💰 Cashout
                    </button>
                  </div>
                  <p className="text-xs text-gray-500 mt-1">Available: ${store.balance.toFixed(2)}</p>
                </div>
              </div>
            </div>

            {/* Payout History */}
            <div className="bg-white rounded-lg shadow p-6">
              <h2 className="text-2xl font-bold mb-4">📋 Payout History</h2>
              {payoutHistory.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50 border-b">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-700">Amount</th>
                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-700">Status</th>
                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-700">Date</th>
                        <th className="px-6 py-3 text-left text-xs font-semibold text-gray-700">Transfer ID</th>
                      </tr>
                    </thead>
                    <tbody>
                      {payoutHistory.map((payout) => (
                        <tr key={payout.id} className="border-b hover:bg-gray-50">
                          <td className="px-6 py-4 font-semibold text-green-600">
                            ${payout.amount?.toFixed(2) || '0.00'} {payout.currency || 'USD'}
                          </td>
                          <td className="px-6 py-4">
                            <span className={`px-3 py-1 rounded-full text-xs font-semibold ${
                              payout.status === 'completed' ? 'bg-green-100 text-green-800' :
                              payout.status === 'processing' ? 'bg-blue-100 text-blue-800' :
                              payout.status === 'failed' ? 'bg-red-100 text-red-800' :
                              'bg-gray-100 text-gray-800'
                            }`}>
                              {payout.status}
                            </span>
                          </td>
                          <td className="px-6 py-4 text-gray-600">
                            {new Date(payout.created_at).toLocaleDateString()}
                          </td>
                          <td className="px-6 py-4 text-gray-600 text-sm font-mono">
                            {payout.stripe_transfer_id?.substring(0, 20)}...
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-gray-500 text-center py-8">No payouts yet</p>
              )}
            </div>
          </div>
        )}

        {loading && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div className="bg-white rounded-lg p-8">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
              <p className="text-gray-600 mt-4">Loading...</p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
