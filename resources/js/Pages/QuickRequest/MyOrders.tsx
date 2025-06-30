import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { FiPackage, FiCalendar, FiEye, FiClock, FiCheckCircle, FiAlertCircle, FiFilter, FiSearch, FiDownload } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import MainLayout from '@/Layouts/MainLayout';
import { useFinancialPermissions } from '@/Components/ProtectedFinancialInfo';

interface Order {
  id: string;
  order_number: string;
  patient_name: string;
  product_name: string;
  product_code: string;
  status: 'draft' | 'submitted' | 'ivr_pending' | 'ivr_approved' | 'order_form_pending' | 'order_form_signed' | 'approved' | 'shipped' | 'delivered' | 'cancelled';
  created_at: string;
  updated_at: string;
  facility_name?: string;
  asp_price?: number;
  tracking_number?: string;
}

interface MyOrdersProps {
  auth: any;
  orders: Order[];
  filter?: string;
  search?: string;
}

export default function MyOrders({ auth, orders, filter, search }: MyOrdersProps) {
  const { theme } = useTheme();
  const t = themes[theme];
  const permissions = useFinancialPermissions(auth.user.role);
  
  const [searchTerm, setSearchTerm] = useState(search || '');
  const [statusFilter, setStatusFilter] = useState(filter || 'all');

  const getStatusBadge = (status: Order['status']) => {
    const statusConfig = {
      draft: { 
        color: theme === 'dark' ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-700',
        icon: FiClock,
        label: 'Draft'
      },
      submitted: { 
        color: theme === 'dark' ? 'bg-yellow-900/30 text-yellow-400' : 'bg-yellow-100 text-yellow-800',
        icon: FiClock,
        label: 'Submitted'
      },
      ivr_pending: { 
        color: theme === 'dark' ? 'bg-blue-900/30 text-blue-400' : 'bg-blue-100 text-blue-800',
        icon: FiClock,
        label: 'IVR Pending'
      },
      ivr_approved: { 
        color: theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-800',
        icon: FiCheckCircle,
        label: 'IVR Approved'
      },
      order_form_pending: { 
        color: theme === 'dark' ? 'bg-orange-900/30 text-orange-400' : 'bg-orange-100 text-orange-800',
        icon: FiClock,
        label: 'Order Form Pending'
      },
      order_form_signed: { 
        color: theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-800',
        icon: FiCheckCircle,
        label: 'Order Form Signed'
      },
      approved: { 
        color: theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-800',
        icon: FiCheckCircle,
        label: 'Approved'
      },
      shipped: { 
        color: theme === 'dark' ? 'bg-blue-900/30 text-blue-400' : 'bg-blue-100 text-blue-800',
        icon: FiPackage,
        label: 'Shipped'
      },
      delivered: { 
        color: theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-800',
        icon: FiCheckCircle,
        label: 'Delivered'
      },
      cancelled: { 
        color: theme === 'dark' ? 'bg-red-900/30 text-red-400' : 'bg-red-100 text-red-800',
        icon: FiAlertCircle,
        label: 'Cancelled'
      }
    };

    const config = statusConfig[status] || statusConfig.draft;
    const Icon = config.icon;

    return (
      <span className={cn("inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium", config.color)}>
        <Icon className="h-3 w-3 mr-1" />
        {config.label}
      </span>
    );
  };

  const filteredOrders = orders.filter(order => {
    // Status filter
    if (statusFilter !== 'all' && order.status !== statusFilter) {
      return false;
    }

    // Search filter
    if (searchTerm) {
      const searchLower = searchTerm.toLowerCase();
      return (
        order.order_number.toLowerCase().includes(searchLower) ||
        order.patient_name.toLowerCase().includes(searchLower) ||
        order.product_name.toLowerCase().includes(searchLower) ||
        order.product_code.toLowerCase().includes(searchLower)
      );
    }

    return true;
  });

  const handleExport = () => {
    // TODO: Implement CSV export
    console.log('Exporting orders...');
  };

  return (
    <MainLayout title="My Orders">
      <Head title="My Orders" />

      <div className="py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <h1 className={cn("text-2xl font-bold", t.text.primary)}>My Orders</h1>
            <p className={cn("mt-1", t.text.secondary)}>
              Track and manage your submitted orders
            </p>
          </div>

          {/* Filters and Search */}
          <div className={cn("mb-6 p-4 rounded-lg", t.glass.card)}>
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
              {/* Search */}
              <div className="flex-1 max-w-md">
                <div className="relative">
                  <FiSearch className={cn("absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5", t.text.tertiary)} />
                  <input
                    type="text"
                    placeholder="Search by order #, patient, or product..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className={cn(
                      "w-full pl-10 pr-4 py-2 rounded-lg border",
                      t.input.base,
                      t.input.focus
                    )}
                  />
                </div>
              </div>

              {/* Status Filter */}
              <div className="flex items-center gap-4">
                <FiFilter className={cn("h-5 w-5", t.text.secondary)} />
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className={cn(
                    "px-4 py-2 rounded-lg border",
                    t.input.base,
                    t.input.focus
                  )}
                >
                  <option value="all">All Status</option>
                  <option value="draft">Draft</option>
                  <option value="submitted">Submitted</option>
                  <option value="ivr_pending">IVR Pending</option>
                  <option value="ivr_approved">IVR Approved</option>
                  <option value="order_form_pending">Order Form Pending</option>
                  <option value="order_form_signed">Order Form Signed</option>
                  <option value="approved">Approved</option>
                  <option value="shipped">Shipped</option>
                  <option value="delivered">Delivered</option>
                  <option value="cancelled">Cancelled</option>
                </select>

                {/* Export Button */}
                <button
                  onClick={handleExport}
                  className={cn(
                    "inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                    theme === 'dark'
                      ? 'bg-gray-700 hover:bg-gray-600 text-gray-300'
                      : 'bg-gray-200 hover:bg-gray-300 text-gray-700'
                  )}
                >
                  <FiDownload className="mr-2 h-4 w-4" />
                  Export
                </button>
              </div>
            </div>
          </div>

          {/* Orders Table */}
          <div className={cn("overflow-hidden rounded-lg", t.glass.card)}>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead className={cn(theme === 'dark' ? 'bg-gray-800/50' : 'bg-gray-50')}>
                  <tr>
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Order #
                    </th>
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Date
                    </th>
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Patient
                    </th>
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Product
                    </th>
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Facility
                    </th>
                    {permissions.canViewPricing && (
                      <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                        Price
                      </th>
                    )}
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Status
                    </th>
                    <th className={cn("px-6 py-3 text-left text-xs font-medium uppercase tracking-wider", t.text.secondary)}>
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className={cn("divide-y", theme === 'dark' ? 'divide-gray-700' : 'divide-gray-200')}>
                  {filteredOrders.length === 0 ? (
                    <tr>
                      <td colSpan={permissions.canViewPricing ? 8 : 7} className="px-6 py-8 text-center">
                        <FiPackage className={cn("h-12 w-12 mx-auto mb-3", t.text.tertiary)} />
                        <p className={cn("text-sm", t.text.secondary)}>
                          No orders found matching your criteria
                        </p>
                      </td>
                    </tr>
                  ) : (
                    filteredOrders.map((order) => (
                      <tr key={order.id} className={cn(
                        "hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                      )}>
                        <td className={cn("px-6 py-4 whitespace-nowrap text-sm font-medium", t.text.primary)}>
                          {order.order_number}
                        </td>
                        <td className={cn("px-6 py-4 whitespace-nowrap text-sm", t.text.secondary)}>
                          <div className="flex items-center">
                            <FiCalendar className="h-4 w-4 mr-1" />
                            {new Date(order.created_at).toLocaleDateString()}
                          </div>
                        </td>
                        <td className={cn("px-6 py-4 whitespace-nowrap text-sm", t.text.primary)}>
                          {order.patient_name}
                        </td>
                        <td className={cn("px-6 py-4 whitespace-nowrap text-sm", t.text.primary)}>
                          <div>
                            <div>{order.product_name}</div>
                            <div className={cn("text-xs", t.text.secondary)}>{order.product_code}</div>
                          </div>
                        </td>
                        <td className={cn("px-6 py-4 whitespace-nowrap text-sm", t.text.secondary)}>
                          {order.facility_name || 'N/A'}
                        </td>
                        {permissions.canViewPricing && (
                          <td className={cn("px-6 py-4 whitespace-nowrap text-sm", t.text.primary)}>
                            ${order.asp_price?.toFixed(2) || '0.00'}
                          </td>
                        )}
                        <td className="px-6 py-4 whitespace-nowrap">
                          {getStatusBadge(order.status)}
                          {order.tracking_number && (
                            <div className={cn("text-xs mt-1", t.text.secondary)}>
                              Track: {order.tracking_number}
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                          <Link
                            href={`/quick-request/orders/${order.id}`}
                            className={cn(
                              "inline-flex items-center text-sm font-medium transition-colors",
                              theme === 'dark' ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'
                            )}
                          >
                            <FiEye className="mr-1 h-4 w-4" />
                            View
                          </Link>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Summary Stats */}
          <div className="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className={cn("p-4 rounded-lg", t.glass.card)}>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Total Orders</p>
              <p className={cn("text-2xl font-bold mt-1", t.text.primary)}>{orders.length}</p>
            </div>
            <div className={cn("p-4 rounded-lg", t.glass.card)}>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Pending</p>
              <p className={cn("text-2xl font-bold mt-1", t.text.primary)}>
                {orders.filter(o => ['submitted', 'ivr_pending', 'order_form_pending'].includes(o.status)).length}
              </p>
            </div>
            <div className={cn("p-4 rounded-lg", t.glass.card)}>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Approved</p>
              <p className={cn("text-2xl font-bold mt-1", t.text.primary)}>
                {orders.filter(o => o.status === 'approved').length}
              </p>
            </div>
            <div className={cn("p-4 rounded-lg", t.glass.card)}>
              <p className={cn("text-sm font-medium", t.text.secondary)}>Delivered</p>
              <p className={cn("text-2xl font-bold mt-1", t.text.primary)}>
                {orders.filter(o => o.status === 'delivered').length}
              </p>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}