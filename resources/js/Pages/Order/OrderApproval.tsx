import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiCheck, FiAlertTriangle, FiX, FiFilter, FiSearch,
  FiChevronDown, FiChevronUp, FiCalendar, FiMapPin,
  FiUser, FiDollarSign, FiPackage, FiClock
} from 'react-icons/fi';
import { api, handleApiResponse } from '@/lib/api';

// Types
interface Order {
  id: string;
  order_number: string;
  submission_date: string;
  provider_name: string;
  facility_name: string;
  payer_name: string;
  expected_service_date: string;
  products: string;
  total_order_value: number;
  mac_validation: 'passed' | 'warning' | 'failed';
  eligibility_status: 'eligible' | 'pending' | 'not_eligible';
  pre_auth_status: 'approved' | 'pending' | 'denied';
  status: string;
}

interface Filters {
  search: string;
  status: string;
  validation: string;
  eligibility: string;
}

const OrderApproval: React.FC = () => {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<Filters>({
    search: '',
    status: '',
    validation: '',
    eligibility: ''
  });
  const [selectedStatus, setSelectedStatus] = useState<string>('pending');
  const [dateRange, setDateRange] = useState<{ start: Date | null; end: Date | null }>({
    start: null,
    end: null
  });
  const [macJurisdiction, setMacJurisdiction] = useState<string>('all');
  const [showAdvancedFilters, setShowAdvancedFilters] = useState<boolean>(false);
  const [selectedOrders, setSelectedOrders] = useState<string[]>([]);

  // Fetch orders from API
  const fetchOrders = async () => {
    setLoading(true);
    setError(null);

    try {
      const params: any = {};

      if (filters.search) params.search = filters.search;
      if (filters.status) params.status = filters.status;
      if (filters.validation) params.mac_validation = filters.validation;
      if (filters.eligibility) params.eligibility_status = filters.eligibility;

      const response = await api.orders.getAll(params);

      // Transform the data to match our interface
      const transformedOrders: Order[] = response.data.map((order: any) => ({
        id: order.id,
        order_number: order.order_number,
        submission_date: order.created_at,
        provider_name: order.provider?.name || 'Unknown Provider',
        facility_name: order.facility?.name || 'Unknown Facility',
        payer_name: order.payer_name || 'Unknown Payer',
        expected_service_date: order.expected_service_date,
        products: order.items?.map((item: any) => item.product_name).join(', ') || 'No products',
        total_order_value: order.total_amount || 0,
        mac_validation: order.mac_validation_status || 'pending',
        eligibility_status: order.eligibility_status || 'pending',
        pre_auth_status: order.pre_auth_status || 'pending',
        status: order.status
      }));

      setOrders(transformedOrders);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch orders');
      console.error('Error fetching orders:', err);
    } finally {
      setLoading(false);
    }
  };

  // Load data on component mount and when filters change
  useEffect(() => {
    fetchOrders();
  }, [filters]);

  const handleFilterChange = (filterName: keyof Filters, value: string) => {
    setFilters(prev => ({ ...prev, [filterName]: value }));
  };

  const handleApprove = async (orderId: string) => {
    try {
      await api.orders.approve(orderId, { notes: 'Approved from order approval interface' });
      // Refresh the orders list
      await fetchOrders();
    } catch (err) {
      console.error('Error approving order:', err);
      alert('Failed to approve order. Please try again.');
    }
  };

  const handleReject = async (orderId: string) => {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason) return;

    try {
      await api.orders.reject(orderId, { reason });
      // Refresh the orders list
      await fetchOrders();
    } catch (err) {
      console.error('Error rejecting order:', err);
      alert('Failed to reject order. Please try again.');
    }
  };

  // Status badge component
  const StatusBadge = ({ status, type }: { status: string; type: 'mac' | 'eligibility' | 'preAuth' }) => {
    const getStatusColor = () => {
      switch (status) {
        case 'passed':
        case 'eligible':
        case 'approved':
          return 'bg-green-100 text-green-800';
        case 'warning':
        case 'pending':
          return 'bg-yellow-100 text-yellow-800';
        case 'failed':
        case 'not_eligible':
        case 'denied':
          return 'bg-red-100 text-red-800';
        default:
          return 'bg-gray-100 text-gray-800';
      }
    };

    const getStatusIcon = () => {
      switch (status) {
        case 'passed':
        case 'eligible':
        case 'approved':
          return <FiCheck className="w-4 h-4" />;
        case 'warning':
        case 'pending':
          return <FiAlertTriangle className="w-4 h-4" />;
        case 'failed':
        case 'not_eligible':
        case 'denied':
          return <FiX className="w-4 h-4" />;
        default:
          return null;
      }
    };

    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor()}`}>
        {getStatusIcon()}
        <span className="ml-1">{status.charAt(0).toUpperCase() + status.slice(1)}</span>
      </span>
    );
  };

  // Filter controls component
  const FilterControls = () => (
    <div className="bg-white p-4 rounded-lg shadow mb-6">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option value="pending">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="info_requested">Info Requested</option>
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
          <div className="flex gap-2">
            <input
              type="date"
              value={dateRange.start?.toISOString().split('T')[0] || ''}
              onChange={(e) => setDateRange(prev => ({ ...prev, start: e.target.value ? new Date(e.target.value) : null }))}
              className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <input
              type="date"
              value={dateRange.end?.toISOString().split('T')[0] || ''}
              onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value ? new Date(e.target.value) : null }))}
              className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">MAC Jurisdiction</label>
          <select
            value={macJurisdiction}
            onChange={(e) => setMacJurisdiction(e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option value="all">All Jurisdictions</option>
            <option value="jurisdiction_a">Jurisdiction A</option>
            <option value="jurisdiction_b">Jurisdiction B</option>
            <option value="jurisdiction_c">Jurisdiction C</option>
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Search</label>
          <div className="relative">
            <input
              type="text"
              value={filters.search}
              onChange={(e) => handleFilterChange('search', e.target.value)}
              placeholder="Search provider or facility..."
              className="w-full rounded-md border-gray-300 shadow-sm pl-10 focus:border-indigo-500 focus:ring-indigo-500"
            />
            <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
          </div>
        </div>
      </div>
      <div className="mt-4">
        <button
          onClick={() => setShowAdvancedFilters(!showAdvancedFilters)}
          className="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
        >
          {showAdvancedFilters ? <FiChevronUp /> : <FiChevronDown />}
          Advanced Filters
        </button>
        {showAdvancedFilters && (
          <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            {/* Add advanced filter fields here */}
          </div>
        )}
      </div>
    </div>
  );

  return (
    <MainLayout>
      <Head title="Order Approval Queue" />

      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Order Approval Queue</h1>
          <p className="text-gray-500">
            Review and process pending wound care product orders
          </p>
        </div>
        {selectedOrders.length > 0 && (
          <div className="flex gap-2">
            <button className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
              Approve Selected
            </button>
            <button className="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">
              Request Info
            </button>
            <button className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
              Reject Selected
            </button>
          </div>
        )}
      </div>

      {/* Filter Controls */}
      <FilterControls />

      {/* Orders Table */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelectedOrders(orders.map(order => order.id));
                      } else {
                        setSelectedOrders([]);
                      }
                    }}
                  />
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Request ID
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Submission Date
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Provider/Facility
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Payer
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Service Date
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Products
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total Value
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  MAC Validation
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Eligibility
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Pre-Auth
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Action
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {orders.map((order) => (
                <tr key={order.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <input
                      type="checkbox"
                      checked={selectedOrders.includes(order.id)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedOrders([...selectedOrders, order.id]);
                        } else {
                          setSelectedOrders(selectedOrders.filter(id => id !== order.id));
                        }
                      }}
                      className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <Link href={`/admin/orders/${order.id}`} className="text-indigo-600 hover:text-indigo-900">
                      {order.order_number}
                    </Link>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {new Date(order.submission_date).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-900">{order.provider_name}</div>
                    <div className="text-sm text-gray-500">{order.facility_name}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {order.payer_name}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {new Date(order.expected_service_date).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-900 max-w-xs">
                      {order.products.split(',').map((product, index) => {
                        const words = product.trim().split(' ');
                        const shortDesc = words.slice(0, 3).join(' ');
                        return (
                          <div key={index} className="mb-1">
                            {shortDesc}
                            {words.length > 3 ? '...' : ''}
                          </div>
                        );
                      })}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    ${order.total_order_value.toFixed(2)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={order.mac_validation} type="mac" />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={order.eligibility_status} type="eligibility" />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={order.pre_auth_status} type="preAuth" />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex space-x-2">
                      <button
                        onClick={() => handleApprove(order.id)}
                        className="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200"
                      >
                        <FiCheck className="mr-1 h-3 w-3" />
                        Approve
                      </button>
                      <button
                        onClick={() => handleReject(order.id)}
                        className="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200"
                      >
                        <FiX className="mr-1 h-3 w-3" />
                        Reject
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Loading State */}
        {loading && (
          <div className="text-center py-8">
            <div className="inline-flex items-center">
              <FiClock className="animate-spin h-5 w-5 mr-2 text-gray-400" />
              <span className="text-gray-600">Loading orders...</span>
            </div>
          </div>
        )}

        {/* Error State */}
        {error && !loading && (
          <div className="text-center py-8">
            <div className="text-red-600 mb-4">
              <FiAlertTriangle className="h-8 w-8 mx-auto mb-2" />
              <p className="text-lg font-medium">Error Loading Orders</p>
              <p className="text-sm">{error}</p>
            </div>
            <button
              onClick={fetchOrders}
              className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
            >
              Try Again
            </button>
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && orders.length === 0 && (
          <div className="text-center py-8">
            <FiPackage className="h-12 w-12 mx-auto text-gray-400 mb-4" />
            <p className="text-gray-600 text-lg">No orders found</p>
            <p className="text-gray-500 text-sm">Try adjusting your filters or check back later.</p>
          </div>
        )}
      </div>
    </MainLayout>
  );
};

export default OrderApproval;
