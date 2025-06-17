import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import OrderStatusBadge from '@/Components/Order/OrderStatusBadge';
import {
  Search,
  Package,
  Clock,
  CheckCircle2,
  AlertTriangle,
  FileText,
  TrendingUp,
  Plus,
  ChevronRight,
  Filter,
  Activity,
} from 'lucide-react';

// Status configuration for the order center
const statusConfig = {
  pending_ivr: { icon: Clock, label: 'Pending IVR', color: 'yellow' },
  ivr_sent: { icon: FileText, label: 'IVR Sent', color: 'blue' },
  ivr_confirmed: { icon: FileText, label: 'IVR Confirmed', color: 'blue' },
  approved: { icon: CheckCircle2, label: 'Approved', color: 'green' },
  sent_back: { icon: AlertTriangle, label: 'Sent Back', color: 'orange' },
  denied: { icon: AlertTriangle, label: 'Denied', color: 'red' },
  submitted_to_manufacturer: { icon: Package, label: 'Submitted', color: 'purple' },
};

interface Order {
  id: string;
  order_number: string;
  patient_display_id: string;
  patient_fhir_id: string;
  order_status: keyof typeof statusConfig;
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

export default function OrderCenter({
  orders,
  filters,
  statusCounts,
  manufacturers,
}: OrderCenterProps) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
  const [selectedManufacturer, setSelectedManufacturer] = useState(filters.manufacturer || '');
  const [showActionRequired, setShowActionRequired] = useState(filters.action_required || false);

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(window.location.pathname, {
      search: searchTerm,
      status: selectedStatus,
      manufacturer: selectedManufacturer,
      action_required: showActionRequired
    });
  };

  // Calculate stats
  const stats = useMemo(() => {
    const totalOrders = Object.values(statusCounts).reduce((a, b) => a + b, 0);
    const pendingActions = (statusCounts.pending_ivr || 0) + (statusCounts.sent_back || 0);
    const approvalRate = totalOrders > 0
      ? Math.round(((statusCounts.approved || 0) / totalOrders) * 100)
      : 0;
    const todaysOrders = orders.data.filter(order => {
      const orderDate = new Date(order.submitted_at);
      const today = new Date();
      return orderDate.toDateString() === today.toDateString();
    }).length;

    return { totalOrders, pendingActions, approvalRate, todaysOrders };
  }, [statusCounts, orders.data]);

  const getStatusBadge = (status: keyof typeof statusConfig) => {
    const config = statusConfig[status];
    if (!config) return <Badge>{status}</Badge>;

    const colorClasses = {
      green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
      purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    };

    const Icon = config.icon;
    return (
      <Badge className={colorClasses[config.color as keyof typeof colorClasses]}>
        <Icon className="w-3 h-3 mr-1" />
        {config.label}
      </Badge>
    );
  };

  return (
    <MainLayout>
      <Head title="Order Management Center" />

      <div className="w-full min-h-screen bg-gray-50 dark:bg-gray-900 py-4 px-2 sm:px-4 md:px-8">
        {/* Header */}
        <div className="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Order Management Center</h1>
            <p className="text-gray-600 dark:text-gray-300">Track and manage all provider-submitted product requests</p>
          </div>
          <Link
            href="/admin/orders/create"
            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <Plus className="w-5 h-5 mr-2" />
            Create Order
          </Link>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <Card className="p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-300">Orders Today</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white">{stats.todaysOrders}</p>
                {stats.todaysOrders > 0 && (
                  <p className="text-xs text-green-600 dark:text-green-300 flex items-center gap-1 mt-1">
                    <TrendingUp className="w-3 h-3" />
                    {Math.round((stats.todaysOrders / Math.max(stats.totalOrders, 1)) * 100)}% of total
                  </p>
                )}
              </div>
              <Activity className="w-8 h-8 text-blue-400 dark:text-blue-300" />
            </div>
          </Card>

          <Card className="p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-300">Pending Actions</p>
                <p className="text-2xl font-bold text-orange-600 dark:text-orange-300">{stats.pendingActions}</p>
                {stats.pendingActions > 5 && (
                  <p className="text-xs text-orange-600 dark:text-orange-300 mt-1">Requires attention</p>
                )}
              </div>
              <AlertTriangle className="w-8 h-8 text-orange-400 dark:text-orange-300" />
            </div>
          </Card>

          <Card className="p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-300">Approval Rate</p>
                <p className="text-2xl font-bold text-green-600 dark:text-green-300">{stats.approvalRate}%</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Overall performance</p>
              </div>
              <CheckCircle2 className="w-8 h-8 text-green-400 dark:text-green-300" />
            </div>
          </Card>

          <Card className="p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-300">Total Orders</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white">{stats.totalOrders}</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">All time</p>
              </div>
              <Package className="w-8 h-8 text-purple-400 dark:text-purple-300" />
            </div>
          </Card>
        </div>

        {/* Status Summary Bar */}
        <Card className="p-4 mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <div className="flex flex-wrap gap-4">
            {Object.entries(statusCounts).map(([status, count]) => {
              const config = statusConfig[status as keyof typeof statusConfig];
              if (!config) return null;
              const Icon = config.icon;
              const isActive = filters.status === status;

              return (
                <button
                  key={status}
                  onClick={() => router.get(window.location.pathname, {
                    ...filters,
                    status: isActive ? '' : status
                  })}
                  className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all
                    ${isActive ? 'bg-red-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
                >
                  <Icon className="w-4 h-4" />
                  <span>{config.label}</span>
                  <span className={`ml-1 px-2 py-0.5 rounded-full text-xs font-semibold
                    ${isActive ? 'bg-white/20' : 'bg-gray-200 dark:bg-gray-600'}`}>{count}</span>
                </button>
              );
            })}
          </div>
        </Card>

        {/* Search and Filters */}
        <Card className="p-4 mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <form onSubmit={handleSearch} className="flex flex-wrap gap-4">
            <div className="flex-1 min-w-[200px] relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 w-5 h-5" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Search orders, providers, or patient IDs..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:text-white dark:placeholder-gray-400"
              />
            </div>

            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:text-white"
            >
              <option value="">All Statuses</option>
              {Object.entries(statusConfig).map(([value, config]) => (
                <option key={value} value={value}>{config.label}</option>
              ))}
            </select>

            <select
              value={selectedManufacturer}
              onChange={(e) => setSelectedManufacturer(e.target.value)}
              className="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:text-white"
            >
              <option value="">All Manufacturers</option>
              {manufacturers.map((manufacturer) => (
                <option key={manufacturer.id} value={manufacturer.id}>
                  {manufacturer.name}
                </option>
              ))}
            </select>

            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={showActionRequired}
                onChange={(e) => setShowActionRequired(e.target.checked)}
                className="rounded border-gray-300 dark:border-gray-700 text-red-600 focus:ring-red-500"
              />
              <span className="text-sm text-gray-700 dark:text-gray-200">Action Required Only</span>
            </label>

            <button
              type="submit"
              className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2"
            >
              <Filter className="w-4 h-4" />
              Apply Filters
            </button>
          </form>
        </Card>

        {/* Orders Table */}
        <Card className="overflow-hidden bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Order Details
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Provider & Patient
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Dates
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Manufacturer
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {orders.data.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-8 text-center">
                      <Package className="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-4" />
                      <p className="text-gray-500 dark:text-gray-300">No orders found</p>
                      <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">
                        {filters.search || filters.status || filters.manufacturer || filters.action_required
                          ? 'Try adjusting your filters'
                          : 'Create your first order to get started'}
                      </p>
                    </td>
                  </tr>
                ) : (
                  orders.data.map((order) => (
                    <tr
                      key={order.id}
                      className="hover:bg-gray-50 dark:hover:bg-gray-900 cursor-pointer"
                      onClick={() => router.visit(`/admin/orders/${order.id}`)}
                    >
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div>
                          <div className="text-sm font-medium text-gray-900 dark:text-white">
                            {order.order_number || 'N/A'}
                          </div>
                          <div className="text-sm text-gray-500 dark:text-gray-300">
                            {order.products_count} items • {formatCurrency(order.total_order_value)}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div>
                          <div className="text-sm font-medium text-gray-900 dark:text-white">
                            {order.provider?.name || 'Unknown'}
                          </div>
                          <div className="text-sm text-gray-500 dark:text-gray-300">
                            {order.facility?.name || 'Unknown Facility'}
                          </div>
                          <div className="text-xs text-gray-400 dark:text-gray-400">
                            Patient: {order.patient_display_id || 'N/A'}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="space-y-2">
                          {getStatusBadge(order.order_status)}
                          {order.action_required && (
                            <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 text-xs">
                              <AlertTriangle className="w-3 h-3 mr-1" />
                              Action Required
                            </Badge>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                        <div>Submitted: {formatDate(order.submitted_at)}</div>
                        <div>Service: {formatDate(order.expected_service_date)}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900 dark:text-white">
                          {order.manufacturer?.name || 'Unknown'}
                        </div>
                        {order.manufacturer?.contact_email && (
                          <div className="text-xs text-gray-500 dark:text-gray-300">
                            {order.manufacturer.contact_email}
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/admin/orders/${order.id}`);
                          }}
                          className="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-200 flex items-center gap-1 ml-auto"
                        >
                          View Details
                          <ChevronRight className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {orders.links && orders.data.length > 0 && (
            <div className="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
              <div className="flex-1 flex justify-between sm:hidden">
                {orders.links[0]?.url && (
                  <Link
                    href={orders.links[0].url}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-700 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800"
                  >
                    Previous
                  </Link>
                )}
                {orders.links[orders.links.length - 1]?.url && (
                  <Link
                    href={orders.links[orders.links.length - 1].url}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-700 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800"
                  >
                    Next
                  </Link>
                )}
              </div>
              <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p className="text-sm text-gray-700 dark:text-gray-200">
                    Showing <span className="font-medium">{((orders.current_page - 1) * orders.per_page) + 1}</span> to{' '}
                    <span className="font-medium">{Math.min(orders.current_page * orders.per_page, orders.total)}</span> of{' '}
                    <span className="font-medium">{orders.total}</span> results
                  </p>
                </div>
                <div>
                  <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    {orders.links.map((link, index) => {
                      const isActive = link.active;
                      const isDisabled = !link.url;

                      return (
                        <Link
                          key={index}
                          href={link.url || '#'}
                          preserveScroll
                          preserveState
                          className={`
                            relative inline-flex items-center px-4 py-2 text-sm font-medium
                            ${index === 0 ? 'rounded-l-md' : ''}
                            ${index === orders.links.length - 1 ? 'rounded-r-md' : ''}
                            ${isActive
                              ? 'z-10 bg-red-50 dark:bg-gray-900 border-red-500 text-red-600 dark:text-red-400'
                              : isDisabled
                              ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 cursor-not-allowed'
                              : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                            }
                            ${index !== 0 ? '-ml-px' : ''}
                            border border-gray-300 dark:border-gray-700
                          `}
                          onClick={isDisabled ? (e) => e.preventDefault() : undefined}
                        >
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        </Link>
                      );
                    })}
                  </nav>
                </div>
              </div>
            </div>
          )}
        </Card>
      </div>
    </MainLayout>
  );
}
