import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  Eye,
  CheckCircle,
  XCircle,
  Clock,
  AlertTriangle,
  FileText,
  Send,
  MoreVertical,
  Filter,
  Search,
  ChevronDown,
  Package,
  Calendar,
  Building2,
  User,
  Hash,
} from 'lucide-react';

interface Order {
  id: string;
  order_number: string;
  patient_display_id: string;
  patient_fhir_id: string;
  order_status: 'pending_ivr' | 'ivr_sent' | 'ivr_confirmed' | 'approved' | 'sent_back' | 'denied' | 'submitted_to_manufacturer';
  provider: {
    id: number;
    name: string;
    email: string;
    npi_number?: string;
  };
  facility: {
    id: number;
    name: string;
    city: string;
    state: string;
  };
  manufacturer: {
    id: number;
    name: string;
    contact_email?: string;
  };
  expected_service_date: string;
  submitted_at: string;
  total_order_value: number;
  products_count: number;
  action_required: boolean;
  ivr_generation_status?: string;
  docuseal_generation_status?: string;
}

interface OrderCenterProps {
  orders: {
    data: Order[];
    links: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    search?: string;
    status?: string;
    action_required?: boolean;
    manufacturer?: string;
    date_range?: string;
  };
  statusCounts: {
    [key: string]: number;
  };
  manufacturers: Array<{ id: number; name: string }>;
}

const OrderCenter: React.FC<OrderCenterProps> = ({
  orders,
  filters,
  statusCounts,
  manufacturers,
}) => {
  const [activeTab, setActiveTab] = useState<'requiring_action' | 'all_orders'>('requiring_action');
  const [searchQuery, setSearchQuery] = useState(filters.search || '');

  const statusConfig = {
    pending_ivr: {
      color: 'bg-gray-100 text-gray-800',
      icon: Clock,
      label: 'Pending IVR',
      description: 'Awaiting IVR generation'
    },
    ivr_sent: {
      color: 'bg-blue-100 text-blue-800',
      icon: Send,
      label: 'IVR Sent',
      description: 'IVR sent to manufacturer'
    },
    ivr_confirmed: {
      color: 'bg-purple-100 text-purple-800',
      icon: FileText,
      label: 'IVR Confirmed',
      description: 'Manufacturer confirmed IVR'
    },
    approved: {
      color: 'bg-green-100 text-green-800',
      icon: CheckCircle,
      label: 'Approved',
      description: 'Ready to submit to manufacturer'
    },
    sent_back: {
      color: 'bg-orange-100 text-orange-800',
      icon: AlertTriangle,
      label: 'Sent Back',
      description: 'Returned to provider for changes'
    },
    denied: {
      color: 'bg-red-100 text-red-800',
      icon: XCircle,
      label: 'Denied',
      description: 'Order rejected'
    },
    submitted_to_manufacturer: {
      color: 'bg-green-900 text-white',
      icon: Package,
      label: 'Submitted',
      description: 'Sent to manufacturer'
    },
  };

  const getStatusBadge = (status: Order['order_status']) => {
    const config = statusConfig[status];
    if (!config) return null;

    const Icon = config.icon;
    return (
      <div className="flex items-center space-x-1">
        <Icon className="w-4 h-4" />
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.color}`}>
          {config.label}
        </span>
      </div>
    );
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const handleSearch = (value: string) => {
    setSearchQuery(value);
    router.get(router.page.url,
      { ...filters, search: value },
      { preserveState: true, replace: true, only: ['orders'] }
    );
  };

  const handleFilterChange = (key: string, value: string) => {
    router.get(router.page.url,
      { ...filters, [key]: value },
      { preserveState: true, replace: true, only: ['orders'] }
    );
  };

  const handleTabChange = (tab: 'requiring_action' | 'all_orders') => {
    setActiveTab(tab);
    handleFilterChange('action_required', tab === 'requiring_action' ? 'true' : '');
  };

  const filteredOrders = activeTab === 'requiring_action'
    ? orders.data.filter(order => order.action_required)
    : orders.data;

  return (
    <MainLayout>
      <Head title="Admin Order Center" />

      <div className="p-4 sm:p-6 lg:p-8">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Order Management Center</h1>
          <p className="mt-1 text-sm text-gray-600">
            Manage provider-submitted product requests, generate IVRs, and track order lifecycles
          </p>
        </div>

        {/* Status Overview Cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
          {Object.entries(statusCounts).map(([status, count]) => {
            const config = statusConfig[status as keyof typeof statusConfig];
            if (!config) return null;
            const Icon = config.icon;

            return (
              <div key={status} className="bg-white rounded-lg border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-2xl font-semibold text-gray-900">{count}</p>
                    <p className="text-xs text-gray-500 mt-1">{config.label}</p>
                  </div>
                  <div className={`p-2 rounded-md ${config.color}`}>
                    <Icon className="h-5 w-5" />
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Tabs and Filters */}
        <div className="bg-white rounded-t-lg border-x border-t border-gray-200">
          <div className="px-4 sm:px-6">
            <div className="flex items-center justify-between">
              {/* Tabs */}
              <div className="flex space-x-8 border-b border-gray-200 -mb-px">
                <button
                  onClick={() => handleTabChange('requiring_action')}
                  className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                    activeTab === 'requiring_action'
                      ? 'border-red-500 text-red-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  Orders Requiring Action
                  {statusCounts.pending_ivr > 0 && (
                    <span className="ml-2 bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs">
                      {statusCounts.pending_ivr}
                    </span>
                  )}
                </button>
                <button
                  onClick={() => handleTabChange('all_orders')}
                  className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                    activeTab === 'all_orders'
                      ? 'border-red-500 text-red-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  All Orders
                </button>
              </div>

              {/* Search and Filters */}
              <div className="flex items-center space-x-3">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Search by Order ID or Provider"
                    className="pl-10 pr-4 py-2 w-64 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    value={searchQuery}
                    onChange={(e) => handleSearch(e.target.value)}
                  />
                </div>

                <select
                  className="rounded-md border border-gray-300 py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
                  value={filters.status || ''}
                  onChange={(e) => handleFilterChange('status', e.target.value)}
                >
                  <option value="">All Statuses</option>
                  {Object.entries(statusConfig).map(([value, config]) => (
                    <option key={value} value={value}>{config.label}</option>
                  ))}
                </select>

                <button className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md">
                  <Filter className="h-5 w-5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Orders Table */}
        <div className="bg-white rounded-b-lg border-x border-b border-gray-200 overflow-hidden shadow">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div className="flex items-center space-x-1">
                      <Hash className="w-4 h-4" />
                      <span>Order ID</span>
                    </div>
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div className="flex items-center space-x-1">
                      <User className="w-4 h-4" />
                      <span>Provider Name</span>
                    </div>
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div className="flex items-center space-x-1">
                      <User className="w-4 h-4" />
                      <span>Patient ID</span>
                    </div>
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Order Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div className="flex items-center space-x-1">
                      <Calendar className="w-4 h-4" />
                      <span>Request Date</span>
                    </div>
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div className="flex items-center space-x-1">
                      <Building2 className="w-4 h-4" />
                      <span>Manufacturer</span>
                    </div>
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Action Required
                  </th>
                  <th className="relative px-6 py-3">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredOrders.map((order) => (
                  <tr
                    key={order.id}
                    className="hover:bg-gray-50 cursor-pointer"
                    onClick={() => {
                      console.log('Navigating to order:', order.id);
                      router.visit(`/admin/orders/${order.id}`);
                    }}
                  >
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">{order.order_number || 'N/A'}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div>
                        <div className="text-sm font-medium text-gray-900">{order.provider?.name || 'Unknown'}</div>
                        <div className="text-xs text-gray-500">{order.facility?.name || 'Unknown Facility'}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">{order.patient_display_id || 'N/A'}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {getStatusBadge(order.order_status)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">{formatDate(order.submitted_at)}</div>
                      <div className="text-xs text-gray-500">Service: {formatDate(order.expected_service_date)}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">{order.manufacturer?.name || 'Unknown'}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-center">
                      {order.action_required ? (
                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                          <AlertTriangle className="w-3 h-3 mr-1" />
                          Yes
                        </span>
                      ) : (
                        <span className="text-sm text-gray-400">No</span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          // Handle action menu
                        }}
                        className="text-gray-400 hover:text-gray-600"
                      >
                        <MoreVertical className="h-5 w-5" />
                      </button>
                    </td>
                  </tr>
                ))}

                {filteredOrders.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-6 py-12 text-center">
                      <div className="text-gray-500">
                        <Package className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="text-lg font-medium">No orders found</p>
                        <p className="text-sm mt-1">
                          {activeTab === 'requiring_action'
                            ? 'No orders require action at this time'
                            : 'Try adjusting your filters'}
                        </p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {orders.links && orders.data.length > 0 && (
            <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
              <div className="flex items-center justify-between">
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing <span className="font-medium">{((orders.current_page - 1) * orders.per_page) + 1}</span>
                      {' '}to <span className="font-medium">{Math.min(orders.current_page * orders.per_page, orders.total)}</span>
                      {' '}of <span className="font-medium">{orders.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      {orders.links.map((link, index) => (
                        <Link
                          key={index}
                          href={link.url || '#'}
                          preserveScroll
                          preserveState
                          className={`relative inline-flex items-center px-3 py-2 border text-sm font-medium
                            ${link.active ? 'z-10 bg-red-50 border-red-500 text-red-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'}
                            ${index === 0 ? 'rounded-l-md' : ''}
                            ${index === orders.links.length - 1 ? 'rounded-r-md' : ''}
                            ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ))}
                    </nav>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Floating Create Order Button */}
        <Link
          href="/admin/orders/create"
          className="fixed bottom-8 right-8 bg-red-600 text-white rounded-full p-4 shadow-lg hover:bg-red-700 transition-colors flex items-center space-x-2 group"
        >
          <Package className="h-6 w-6" />
          <span className="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300 ease-in-out">
            <span className="pl-2 pr-2">Create Order</span>
          </span>
        </Link>
      </div>
    </MainLayout>
  );
};

export default OrderCenter;
