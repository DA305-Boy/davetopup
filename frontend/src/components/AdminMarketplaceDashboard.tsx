import { useState, useEffect } from 'react';
import axios from 'axios';

interface DashboardStats {
  total_orders: number;
  total_revenue: number;
  total_payouts: number;
  pending_verifications: number;
  active_stores: number;
}

interface Order {
  id: number;
  store_id: number;
  total_amount: number;
  status: string;
  created_at: string;
  store: { store_name: string };
}

interface Seller {
  id: number;
  name: string;
  store_name: string;
  email: string;
  total_orders: number;
  total_revenue: number;
  verification_status: string;
  created_at: string;
}

interface Payout {
  id: number;
  store_id: number;
  amount: number;
  currency: string;
  status: string;
  created_at: string;
  store: { store_name: string; user: { name: string } };
}

export default function AdminMarketplaceDashboard() {
  const [activeTab, setActiveTab] = useState('overview');
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [orders, setOrders] = useState<Order[]>([]);
  const [sellers, setSellers] = useState<Seller[]>([]);
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [verifications, setVerifications] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState({ status: '', page: 1 });

  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

  useEffect(() => {
    loadDashboardData();
  }, [activeTab, filters]);

  const loadDashboardData = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('sanctum_token');
      const headers = { Authorization: `Bearer ${token}` };

      if (activeTab === 'overview') {
        const res = await axios.get(`${API_URL}/admin/overview`, { headers });
        setStats(res.data);
      } else if (activeTab === 'orders') {
        const res = await axios.get(`${API_URL}/admin/orders`, {
          headers,
          params: { status: filters.status || undefined, page: filters.page }
        });
        setOrders(res.data.data || []);
      } else if (activeTab === 'sellers') {
        const res = await axios.get(`${API_URL}/admin/sellers`, {
          headers,
          params: { verification_status: filters.status || undefined, page: filters.page }
        });
        setSellers(res.data.data || []);
      } else if (activeTab === 'payouts') {
        const res = await axios.get(`${API_URL}/admin/payouts`, {
          headers,
          params: { status: filters.status || undefined, page: filters.page }
        });
        setPayouts(res.data.data || []);
      } else if (activeTab === 'verifications') {
        const res = await axios.get(`${API_URL}/admin/verifications`, {
          headers,
          params: { status: filters.status || undefined, page: filters.page }
        });
        setVerifications(res.data.data || []);
      }
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    }
    setLoading(false);
  };

  const handleApproveVerification = async (verificationId: number) => {
    const token = localStorage.getItem('sanctum_token');
    try {
      await axios.post(
        `${API_URL}/admin/verifications/${verificationId}/approve`,
        {},
        { headers: { Authorization: `Bearer ${token}` } }
      );
      loadDashboardData();
    } catch (error) {
      console.error('Error approving verification:', error);
    }
  };

  const handleRejectVerification = async (verificationId: number) => {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;

    const token = localStorage.getItem('sanctum_token');
    try {
      await axios.post(
        `${API_URL}/admin/verifications/${verificationId}/reject`,
        { reason },
        { headers: { Authorization: `Bearer ${token}` } }
      );
      loadDashboardData();
    } catch (error) {
      console.error('Error rejecting verification:', error);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-4 md:p-8">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Marketplace Admin Dashboard</h1>
          <p className="text-gray-600 mt-2">Manage orders, sellers, payouts, and verifications</p>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-gray-200 mb-8 overflow-x-auto">
          {['overview', 'orders', 'sellers', 'payouts', 'verifications'].map((tab) => (
            <button
              key={tab}
              onClick={() => { setActiveTab(tab); setFilters({ ...filters, page: 1 }); }}
              className={`px-4 py-3 font-medium text-sm capitalize ${
                activeTab === tab
                  ? 'text-blue-600 border-b-2 border-blue-600'
                  : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              {tab}
            </button>
          ))}
        </div>

        {/* Overview Tab */}
        {activeTab === 'overview' && stats && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div className="bg-white rounded-lg shadow p-6">
              <p className="text-gray-500 text-sm">Total Orders</p>
              <p className="text-3xl font-bold text-gray-900">{stats.total_orders}</p>
            </div>
            <div className="bg-white rounded-lg shadow p-6">
              <p className="text-gray-500 text-sm">Total Revenue</p>
              <p className="text-3xl font-bold text-green-600">${stats.total_revenue.toFixed(2)}</p>
            </div>
            <div className="bg-white rounded-lg shadow p-6">
              <p className="text-gray-500 text-sm">Total Payouts</p>
              <p className="text-3xl font-bold text-blue-600">${stats.total_payouts.toFixed(2)}</p>
            </div>
            <div className="bg-white rounded-lg shadow p-6">
              <p className="text-gray-500 text-sm">Pending Verifications</p>
              <p className="text-3xl font-bold text-orange-600">{stats.pending_verifications}</p>
            </div>
            <div className="bg-white rounded-lg shadow p-6">
              <p className="text-gray-500 text-sm">Active Stores</p>
              <p className="text-3xl font-bold text-purple-600">{stats.active_stores}</p>
            </div>
          </div>
        )}

        {/* Orders Tab */}
        {activeTab === 'orders' && (
          <div className="bg-white rounded-lg shadow overflow-hidden">
            <div className="p-4 border-b border-gray-200">
              <select
                value={filters.status}
                onChange={(e) => setFilters({ ...filters, status: e.target.value, page: 1 })}
                className="px-3 py-2 border border-gray-300 rounded"
              >
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
              </select>
            </div>
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr key={order.id} className="border-b border-gray-200 hover:bg-gray-50">
                    <td className="px-6 py-4 text-sm font-medium text-gray-900">#{order.id}</td>
                    <td className="px-6 py-4 text-sm text-gray-600">{order.store?.store_name}</td>
                    <td className="px-6 py-4 text-sm font-medium text-gray-900">${order.total_amount.toFixed(2)}</td>
                    <td className="px-6 py-4 text-sm">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${
                        order.status === 'completed' ? 'bg-green-100 text-green-800' :
                        order.status === 'failed' ? 'bg-red-100 text-red-800' :
                        'bg-yellow-100 text-yellow-800'
                      }`}>
                        {order.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-600">{new Date(order.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Sellers Tab */}
        {activeTab === 'sellers' && (
          <div className="bg-white rounded-lg shadow overflow-hidden">
            <div className="p-4 border-b border-gray-200">
              <select
                value={filters.status}
                onChange={(e) => setFilters({ ...filters, status: e.target.value, page: 1 })}
                className="px-3 py-2 border border-gray-300 rounded"
              >
                <option value="">All Statuses</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Seller</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody>
                {sellers.map((seller) => (
                  <tr key={seller.id} className="border-b border-gray-200 hover:bg-gray-50">
                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{seller.name}</td>
                    <td className="px-6 py-4 text-sm text-gray-600">{seller.store_name}</td>
                    <td className="px-6 py-4 text-sm text-gray-900">{seller.total_orders}</td>
                    <td className="px-6 py-4 text-sm font-medium text-green-600">${seller.total_revenue.toFixed(2)}</td>
                    <td className="px-6 py-4 text-sm">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${
                        seller.verification_status === 'approved' ? 'bg-green-100 text-green-800' :
                        seller.verification_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {seller.verification_status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Payouts Tab */}
        {activeTab === 'payouts' && (
          <div className="bg-white rounded-lg shadow overflow-hidden">
            <div className="p-4 border-b border-gray-200">
              <select
                value={filters.status}
                onChange={(e) => setFilters({ ...filters, status: e.target.value, page: 1 })}
                className="px-3 py-2 border border-gray-300 rounded"
              >
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
              </select>
            </div>
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
              </thead>
              <tbody>
                {payouts.map((payout) => (
                  <tr key={payout.id} className="border-b border-gray-200 hover:bg-gray-50">
                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{payout.store?.store_name}</td>
                    <td className="px-6 py-4 text-sm font-bold">${payout.amount.toFixed(2)} {payout.currency}</td>
                    <td className="px-6 py-4 text-sm">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${
                        payout.status === 'completed' ? 'bg-green-100 text-green-800' :
                        payout.status === 'failed' ? 'bg-red-100 text-red-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {payout.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-600">{new Date(payout.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Verifications Tab */}
        {activeTab === 'verifications' && (
          <div className="bg-white rounded-lg shadow overflow-hidden">
            <div className="p-4 border-b border-gray-200">
              <select
                value={filters.status}
                onChange={(e) => setFilters({ ...filters, status: e.target.value, page: 1 })}
                className="px-3 py-2 border border-gray-300 rounded"
              >
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Doc Type</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody>
                {verifications.map((v) => (
                  <tr key={v.id} className="border-b border-gray-200 hover:bg-gray-50">
                    <td className="px-6 py-4 text-sm font-medium text-gray-900">{v.verified_name}</td>
                    <td className="px-6 py-4 text-sm text-gray-600">{v.store?.store_name}</td>
                    <td className="px-6 py-4 text-sm text-gray-600">{v.document_type}</td>
                    <td className="px-6 py-4 text-sm">
                      <span className={`px-2 py-1 rounded text-xs font-semibold ${
                        v.verification_status === 'approved' ? 'bg-green-100 text-green-800' :
                        v.verification_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {v.verification_status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm">
                      {v.verification_status === 'pending' && (
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleApproveVerification(v.id)}
                            className="px-3 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600"
                          >
                            Approve
                          </button>
                          <button
                            onClick={() => handleRejectVerification(v.id)}
                            className="px-3 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600"
                          >
                            Reject
                          </button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {loading && (
          <div className="flex justify-center items-center p-8">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          </div>
        )}
      </div>
    </div>
  );
}
