import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { GlassTable, Table, Thead, Tbody, Tr, Th, Td } from '@/Components/ui/GlassTable';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import Heading from '@/Components/ui/Heading';
import GlassCard from '@/Components/ui/GlassCard';
import Input from '@/Components/Input';
import OrderStatusBadge from '@/Components/Order/OrderStatusBadge';
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

// Status configuration for the order center
const statusConfig = {
  pending_ivr: { icon: Clock, label: 'Pending IVR' },
  ivr_sent: { icon: Send, label: 'IVR Sent' },
  ivr_confirmed: { icon: FileText, label: 'IVR Confirmed' },
  approved: { icon: CheckCircle, label: 'Approved' },
  sent_back: { icon: AlertTriangle, label: 'Sent Back' },
  denied: { icon: XCircle, label: 'Denied' },
  submitted_to_manufacturer: { icon: Package, label: 'Submitted' },
};

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

  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }


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
          <Heading level={1} className="bg-gradient-to-r from-[#1925c3] to-[#c71719] bg-clip-text text-transparent">
            Order Management Center
          </Heading>
          <p className={`mt-1 text-sm ${t.text.secondary}`}>
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
              <GlassCard key={status} variant="frost" className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className={`text-2xl font-semibold ${t.text.primary}`}>{count}</p>
                    <p className={`text-xs mt-1 ${t.text.tertiary}`}>{config.label}</p>
                  </div>
                  <div className="p-2 rounded-md">
                    <OrderStatusBadge status={status as Order['order_status']} size="sm" showIcon />
                  </div>
                </div>
              </GlassCard>
            );
          })}
        </div>

        {/* Tabs and Filters */}
        <GlassCard variant="base" className="rounded-t-2xl rounded-b-none border-b-0">
          <div className="px-4 sm:px-6">
            <div className="flex items-center justify-between">
              {/* Tabs */}
              <div className={`flex space-x-8 border-b ${theme === 'dark' ? 'border-white/10' : 'border-gray-200'} -mb-px`}>
                <button
                  onClick={() => handleTabChange('requiring_action')}
                  className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                    activeTab === 'requiring_action'
                      ? `border-[#c71719] ${theme === 'dark' ? 'text-red-400' : 'text-red-600'}`
                      : `border-transparent ${t.text.secondary} ${theme === 'dark' ? 'hover:text-white/90 hover:border-white/30' : 'hover:text-gray-700 hover:border-gray-300'}`
                  }`}
                >
                  Orders Requiring Action
                  {statusCounts.pending_ivr > 0 && (
                    <span className={`ml-2 ${theme === 'dark' ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-600'} py-0.5 px-2 rounded-full text-xs`}>
                      {statusCounts.pending_ivr}
                    </span>
                  )}
                </button>
                <button
                  onClick={() => handleTabChange('all_orders')}
                  className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                    activeTab === 'all_orders'
                      ? `border-[#c71719] ${theme === 'dark' ? 'text-red-400' : 'text-red-600'}`
                      : `border-transparent ${t.text.secondary} ${theme === 'dark' ? 'hover:text-white/90 hover:border-white/30' : 'hover:text-gray-700 hover:border-gray-300'}`
                  }`}
                >
                  All Orders
                </button>
              </div>

              {/* Search and Filters */}
              <div className="flex items-center space-x-3">
                <div className="relative">
                  <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 ${t.text.muted}`} />
                  <input
                    type="text"
                    placeholder="Search by Order ID or Provider"
                    className={`pl-10 pr-4 py-2 w-64 rounded-xl text-sm ${t.input.base} ${t.input.focus}`}
                    value={searchQuery}
                    onChange={(e) => handleSearch(e.target.value)}
                  />
                </div>

                <select
                  className={`rounded-xl py-2 px-3 text-sm ${t.input.base} ${t.input.focus}`}
                  value={filters.status || ''}
                  onChange={(e) => handleFilterChange('status', e.target.value)}
                >
                  <option value="">All Statuses</option>
                  {Object.entries(statusConfig).map(([value, config]) => (
                    <option key={value} value={value}>{config.label}</option>
                  ))}
                </select>

                <button className={`p-2 ${t.text.secondary} ${t.glass.hover} rounded-xl transition-colors`}>
                  <Filter className="h-5 w-5" />
                </button>
              </div>
            </div>
          </div>
        </GlassCard>

        {/* Orders Table */}
        <GlassTable>
          <Table>
            <Thead>
              <Tr>
                <Th>
                  <div className="flex items-center space-x-1">
                    <Hash className="w-4 h-4" />
                    <span>Order ID</span>
                  </div>
                </Th>
                <Th>
                  <div className="flex items-center space-x-1">
                    <User className="w-4 h-4" />
                    <span>Provider Name</span>
                  </div>
                </Th>
                <Th>
                  <div className="flex items-center space-x-1">
                    <User className="w-4 h-4" />
                    <span>Patient ID</span>
                  </div>
                </Th>
                <Th>Order Status</Th>
                <Th>
                  <div className="flex items-center space-x-1">
                    <Calendar className="w-4 h-4" />
                    <span>Request Date</span>
                  </div>
                </Th>
                <Th>
                  <div className="flex items-center space-x-1">
                    <Building2 className="w-4 h-4" />
                    <span>Manufacturer</span>
                  </div>
                </Th>
                <Th>Action Required</Th>
                <Th className="relative">
                  <span className="sr-only">Actions</span>
                </Th>
              </Tr>
            </Thead>
            <Tbody>
              {filteredOrders.map((order, index) => (
                <Tr
                  key={order.id}
                  isEven={index % 2 === 0}
                  className="cursor-pointer"
                  onClick={() => {
                    console.log('Navigating to order:', order.id);
                    router.visit(`/admin/orders/${order.id}`);
                  }}
                >
                  <Td>
                    <div className="text-sm font-medium">{order.order_number || 'N/A'}</div>
                  </Td>
                  <Td>
                    <div>
                      <div className="text-sm font-medium">{order.provider?.name || 'Unknown'}</div>
                      <div className="text-xs opacity-70">{order.facility?.name || 'Unknown Facility'}</div>
                    </div>
                  </Td>
                  <Td>
                    <div className="text-sm">{order.patient_display_id || 'N/A'}</div>
                  </Td>
                  <Td>
                    <OrderStatusBadge status={order.order_status} size="md" showIcon />
                  </Td>
                  <Td>
                    <div className="text-sm">{formatDate(order.submitted_at)}</div>
                    <div className="text-xs opacity-70">Service: {formatDate(order.expected_service_date)}</div>
                  </Td>
                  <Td>
                    <div className="text-sm">{order.manufacturer?.name || 'Unknown'}</div>
                  </Td>
                  <Td className="text-center">
                    {order.action_required ? (
                      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${t.status.error}`}>
                        <AlertTriangle className="w-3 h-3 mr-1" />
                        Yes
                      </span>
                    ) : (
                      <span className={`text-sm ${t.text.muted}`}>No</span>
                    )}
                  </Td>
                  <Td className="text-right">
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        // Handle action menu
                      }}
                      className={`${t.text.secondary} hover:${t.text.primary} transition-colors`}
                    >
                      <MoreVertical className="h-5 w-5" />
                    </button>
                  </Td>
                </Tr>
              ))}

              {filteredOrders.length === 0 && (
                <Tr>
                  <Td colSpan={8} className="text-center py-12">
                    <div className={t.text.secondary}>
                      <Package className={`h-12 w-12 mx-auto mb-4 ${t.text.muted}`} />
                      <p className="text-lg font-medium">No orders found</p>
                      <p className="text-sm mt-1">
                        {activeTab === 'requiring_action'
                          ? 'No orders require action at this time'
                          : 'Try adjusting your filters'}
                      </p>
                    </div>
                  </Td>
                </Tr>
              )}
            </Tbody>
          </Table>
        </GlassTable>

        {/* Pagination */}
        {orders.links && orders.data.length > 0 && (
          <GlassCard variant="base" className="px-4 py-3 mt-4">
            <div className="flex items-center justify-between">
              <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p className={`text-sm ${t.text.secondary}`}>
                    Showing <span className={`font-medium ${t.text.primary}`}>{((orders.current_page - 1) * orders.per_page) + 1}</span>
                    {' '}to <span className={`font-medium ${t.text.primary}`}>{Math.min(orders.current_page * orders.per_page, orders.total)}</span>
                    {' '}of <span className={`font-medium ${t.text.primary}`}>{orders.total}</span> results
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
                        className={`relative inline-flex items-center px-3 py-2 border text-sm font-medium transition-all
                          ${link.active
                            ? 'z-10 bg-gradient-to-r from-[#1925c3] to-[#c71719] border-transparent text-white'
                            : `${t.glass.base} ${t.text.secondary} ${t.glass.hover}`}
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
          </GlassCard>
        )}

        {/* Floating Create Order Button */}
        <Link
          href="/admin/orders/create"
          className={`fixed bottom-8 right-8 ${t.button.primary} rounded-full p-4 flex items-center space-x-2 group`}
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
