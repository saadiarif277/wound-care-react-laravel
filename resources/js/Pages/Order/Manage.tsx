import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  Search,
  Filter,
  Calendar,
  FileText,
  Clock,
  CheckCircle,
  AlertTriangle,
  XCircle,
  Eye,
  MoreHorizontal,
  Users,
  Building,
  DollarSign,
  Package
} from 'lucide-react';

interface Order {
  id: number;
  order_number: string;
  patient_fhir_id: string;
  status: string;
  order_date: string;
  total_amount: number;
  organization_name: string;
  facility_name: string;
  sales_rep_name: string;
  items_count: number;
}

interface Props {
  orders: {
    data: Order[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
  filters: {
    search?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
    sales_rep_id?: string;
  };
  statuses: Record<string, string>;
}

const OrderManage: React.FC<Props> = ({ orders, filters, statuses }) => {
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [statusFilter, setStatusFilter] = useState(filters?.status || '');
  const [showFilters, setShowFilters] = useState(false);

  const ordersData = orders || {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0
  };

  const filtersData = filters || {};

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/orders/manage', {
      ...filtersData,
      search: searchTerm,
      page: 1
    }, {
      preserveState: true
    });
  };

  const handleStatusFilter = (status: string) => {
    setStatusFilter(status);
    router.get('/orders/manage', {
      ...filtersData,
      status: status === 'all' ? '' : status,
      page: 1
    }, {
      preserveState: true
    });
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending_admin_approval':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'pending_documents':
        return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'approved':
        return 'bg-green-100 text-green-800 border-green-200';
      case 'processing':
        return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'shipped':
        return 'bg-indigo-100 text-indigo-800 border-indigo-200';
      case 'delivered':
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
      case 'rejected':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'cancelled':
        return 'bg-gray-100 text-gray-800 border-gray-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'pending_admin_approval':
        return <Clock className="h-4 w-4" />;
      case 'pending_documents':
        return <FileText className="h-4 w-4" />;
      case 'approved':
        return <CheckCircle className="h-4 w-4" />;
      case 'processing':
        return <Package className="h-4 w-4" />;
      case 'shipped':
        return <Package className="h-4 w-4" />;
      case 'delivered':
        return <CheckCircle className="h-4 w-4" />;
      case 'rejected':
        return <XCircle className="h-4 w-4" />;
      case 'cancelled':
        return <XCircle className="h-4 w-4" />;
      default:
        return <FileText className="h-4 w-4" />;
    }
  };

  // Get pending approval count for default tab
  const pendingCount = ordersData.data.filter(order => order.status === 'pending_admin_approval').length;

  return (
    <MainLayout title="Order Management">
      <Head title="Order Management" />

      <div className="min-h-screen bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto py-6">
          {/* Header */}
          <div className="mb-8">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h1 className="text-2xl font-semibold text-gray-900">Order Management</h1>
                <p className="mt-2 text-sm text-gray-600">
                  Review, validate, and process submitted product requests
                </p>
              </div>
              <div className="mt-4 sm:mt-0 flex space-x-3">
                <div className="bg-white rounded-lg border border-gray-200 px-4 py-2">
                  <div className="flex items-center space-x-2">
                    <AlertTriangle className="h-5 w-5 text-yellow-500" />
                    <span className="text-sm font-medium text-gray-900">
                      {pendingCount} Pending Review
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div className="p-4">
              <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-4">
                <div className="flex-1">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                      type="text"
                      placeholder="Search by order number, facility, organization..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </div>
                <div className="flex gap-2">
                  <button
                    type="submit"
                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    Search
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowFilters(!showFilters)}
                    className="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <Filter className="h-4 w-4" />
                  </button>
                </div>
              </form>

              {/* Status Filter Tabs */}
              <div className="mt-4 border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                  <button
                    onClick={() => handleStatusFilter('all')}
                    className={`py-2 px-1 border-b-2 font-medium text-sm ${
                      (statusFilter === '' || statusFilter === 'all')
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    }`}
                  >
                    All Orders
                    <span className="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs">
                      {ordersData.total}
                    </span>
                  </button>
                  {Object.entries(statuses).map(([key, label]) => (
                    <button
                      key={key}
                      onClick={() => handleStatusFilter(key)}
                      className={`py-2 px-1 border-b-2 font-medium text-sm ${
                        statusFilter === key
                          ? 'border-blue-500 text-blue-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      }`}
                    >
                      {label}
                      {key === 'pending_admin_approval' && (
                        <span className="ml-2 bg-yellow-100 text-yellow-900 py-0.5 px-2.5 rounded-full text-xs">
                          {pendingCount}
                        </span>
                      )}
                    </button>
                  ))}
                </nav>
              </div>
            </div>
          </div>

          {/* Orders Table */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200">
            <div className="overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Order Details
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Facility & Organization
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Sales Rep
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Total Amount
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {ordersData.data.length > 0 ? (
                    ordersData.data.map((order) => (
                      <tr key={order.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex flex-col">
                            <div className="text-sm font-medium text-gray-900">
                              {order.order_number}
                            </div>
                            <div className="text-sm text-gray-500">
                              {new Date(order.order_date).toLocaleDateString()}
                            </div>
                            <div className="text-xs text-gray-400 flex items-center mt-1">
                              <Package className="h-3 w-3 mr-1" />
                              {order.items_count} item{order.items_count !== 1 ? 's' : ''}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex flex-col">
                            <div className="text-sm text-gray-900 flex items-center">
                              <Building className="h-4 w-4 mr-1 text-gray-400" />
                              {order.facility_name}
                            </div>
                            <div className="text-sm text-gray-500 flex items-center">
                              <Users className="h-4 w-4 mr-1 text-gray-400" />
                              {order.organization_name}
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-gray-900">{order.sales_rep_name}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${getStatusColor(order.status)}`}>
                            {getStatusIcon(order.status)}
                            <span className="ml-1">{statuses[order.status]}</span>
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-gray-900 flex items-center">
                            <DollarSign className="h-4 w-4 mr-1 text-gray-400" />
                            {order.total_amount ? `$${order.total_amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}` : 'TBD'}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                          <div className="flex items-center space-x-2">
                            <Link
                              href={`/orders/${order.id}`}
                              className="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                              title="View Order Details"
                            >
                              <Eye className="h-4 w-4" />
                            </Link>
                            <button
                              className="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-50"
                              title="More Actions"
                            >
                              <MoreHorizontal className="h-4 w-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="px-6 py-12 text-center">
                        <div className="flex flex-col items-center">
                          <FileText className="h-12 w-12 text-gray-400 mb-4" />
                          <h3 className="text-sm font-medium text-gray-900 mb-2">No orders found</h3>
                          <p className="text-sm text-gray-500">
                            {statusFilter && statusFilter !== 'all'
                              ? `No orders with status "${statuses[statusFilter]}" match your criteria.`
                              : 'No orders match your search criteria.'
                            }
                          </p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {ordersData.last_page > 1 && (
              <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div className="flex-1 flex justify-between sm:hidden">
                  <button
                    onClick={() => router.get('/orders/manage', { ...filtersData, page: ordersData.current_page - 1 })}
                    disabled={ordersData.current_page === 1}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => router.get('/orders/manage', { ...filtersData, page: ordersData.current_page + 1 })}
                    disabled={ordersData.current_page === ordersData.last_page}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Next
                  </button>
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing <span className="font-medium">{ordersData.from}</span> to{' '}
                      <span className="font-medium">{ordersData.to}</span> of{' '}
                      <span className="font-medium">{ordersData.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      {/* Pagination buttons would go here */}
                    </nav>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderManage;
