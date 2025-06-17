import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { GlassTable, Table, Thead, Tbody, Tr, Th, Td } from '@/Components/ui/GlassTable';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import Heading from '@/Components/ui/Heading';
import GlassCard from '@/Components/ui/GlassCard';
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
  TrendingUp,
  TrendingDown,
  Activity,
  Download,
  FilterX,
  CalendarRange,
  Sparkles,
  ChevronRight,
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

  const handleSearch = (value: string) => {
    setSearchQuery(value);
    router.get(window.location.pathname,
      { ...filters, search: value },
      { preserveState: true, replace: true, only: ['orders'] }
    );
  };

  const handleFilterChange = (key: string, value: string) => {
    router.get(window.location.pathname,
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

  // Calculate stats for header
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

  // Get time-based greeting
  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  };

  return (
    <MainLayout>
      <Head title="Admin Order Center" />
      
      {/* Hero Header with Gradient Background */}
      <div className={`relative overflow-hidden ${theme === 'dark' ? 'bg-gradient-to-br from-[#1925c3]/20 via-transparent to-[#c71719]/20' : 'bg-gradient-to-br from-[#1925c3]/10 via-transparent to-[#c71719]/10'} -mt-6 -mx-4 sm:-mx-6 lg:-mx-8`}>
        <div className="absolute inset-0 opacity-5">
          <div className="absolute inset-0" style={{
            backgroundImage: `repeating-linear-gradient(
              45deg,
              transparent,
              transparent 10px,
              ${theme === 'dark' ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.03)'} 10px,
              ${theme === 'dark' ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.03)'} 20px
            )`
          }}></div>
        </div>
        <div className="relative p-4 sm:p-6 lg:p-8">
          <div className="mb-8">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className={`text-sm ${t.text.secondary} mb-1`}>{getGreeting()}, Admin</p>
                <Heading level={1} className="bg-gradient-to-r from-[#1925c3] to-[#c71719] bg-clip-text text-transparent animate-in fade-in slide-in-from-bottom-2 duration-500">
                  Order Management Center
                </Heading>
                <p className={`mt-2 text-base ${t.text.secondary}`}>
                  Track and manage all provider-submitted product requests in real-time
                </p>
              </div>
              <div className="hidden lg:flex items-center gap-3">
                <button className={`px-4 py-2.5 ${theme === 'dark' ? 'bg-white/10 hover:bg-white/15' : 'bg-gray-100 hover:bg-gray-200'} rounded-xl flex items-center gap-2 transition-all backdrop-blur-sm border ${theme === 'dark' ? 'border-white/10' : 'border-gray-200'} shadow-sm hover:shadow-md`}>
                  <Download className="w-4 h-4" />
                  <span className="text-sm font-medium">Export</span>
                </button>
              </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
              <GlassCard variant="default" className={`p-6 relative overflow-hidden group transform transition-all duration-300 hover:-translate-y-1 ${theme === 'dark' ? 'bg-white/[0.08]' : 'bg-white/90'} backdrop-blur-xl`}>
                <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-blue-500/20 to-transparent rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative">
                  <div className="flex items-center justify-between mb-3">
                    <div className={`p-2.5 rounded-xl ${theme === 'dark' ? 'bg-blue-500/20' : 'bg-blue-100'} shadow-sm`}>
                      <Activity className={`w-5 h-5 ${theme === 'dark' ? 'text-blue-400' : 'text-blue-600'}`} />
                    </div>
                    {stats.todaysOrders > 0 && (
                      <span className={`text-xs ${theme === 'dark' ? 'text-green-400' : 'text-green-600'} flex items-center gap-1 font-medium`}>
                        <TrendingUp className="w-3 h-3" />
                        +{Math.round((stats.todaysOrders / Math.max(stats.totalOrders, 1)) * 100)}%
                      </span>
                    )}
                  </div>
                  <p className={`text-3xl font-bold ${t.text.primary} transition-all duration-300 group-hover:scale-110 origin-left mb-1`}>{stats.todaysOrders}</p>
                  <p className={`text-sm ${t.text.secondary} font-medium`}>Orders Today</p>
                </div>
              </GlassCard>

              <GlassCard variant="default" className={`p-6 relative overflow-hidden group transform transition-all duration-300 hover:-translate-y-1 ${theme === 'dark' ? 'bg-white/[0.08]' : 'bg-white/90'} backdrop-blur-xl`}>
                <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-amber-500/20 to-transparent rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative">
                  <div className="flex items-center justify-between mb-3">
                    <div className={`p-2.5 rounded-xl ${theme === 'dark' ? 'bg-amber-500/20' : 'bg-amber-100'} shadow-sm`}>
                      <AlertTriangle className={`w-5 h-5 ${theme === 'dark' ? 'text-amber-400' : 'text-amber-600'}`} />
                    </div>
                    {stats.pendingActions > 5 && (
                      <span className={`text-xs ${theme === 'dark' ? 'text-red-400' : 'text-red-600'} font-bold animate-pulse`}>Urgent</span>
                    )}
                  </div>
                  <p className={`text-3xl font-bold ${t.text.primary} transition-all duration-300 group-hover:scale-110 origin-left mb-1`}>{stats.pendingActions}</p>
                  <p className={`text-sm ${t.text.secondary} font-medium`}>Pending Actions</p>
                </div>
              </GlassCard>

              <GlassCard variant="default" className={`p-6 relative overflow-hidden group transform transition-all duration-300 hover:-translate-y-1 ${theme === 'dark' ? 'bg-white/[0.08]' : 'bg-white/90'} backdrop-blur-xl`}>
                <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-emerald-500/20 to-transparent rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative">
                  <div className="flex items-center justify-between mb-3">
                    <div className={`p-2.5 rounded-xl ${theme === 'dark' ? 'bg-emerald-500/20' : 'bg-emerald-100'} shadow-sm`}>
                      <CheckCircle className={`w-5 h-5 ${theme === 'dark' ? 'text-emerald-400' : 'text-emerald-600'}`} />
                    </div>
                    {stats.approvalRate > 90 && (
                      <Sparkles className={`w-4 h-4 ${theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'} animate-pulse`} />
                    )}
                  </div>
                  <p className={`text-3xl font-bold ${t.text.primary} transition-all duration-300 group-hover:scale-110 origin-left mb-1`}>{stats.approvalRate}%</p>
                  <p className={`text-sm ${t.text.secondary} font-medium`}>Approval Rate</p>
                </div>
              </GlassCard>

              <GlassCard variant="default" className={`p-6 relative overflow-hidden group transform transition-all duration-300 hover:-translate-y-1 ${theme === 'dark' ? 'bg-white/[0.08]' : 'bg-white/90'} backdrop-blur-xl`}>
                <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gradient-to-br from-purple-500/20 to-transparent rounded-full blur-2xl group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative">
                  <div className="flex items-center justify-between mb-3">
                    <div className={`p-2.5 rounded-xl ${theme === 'dark' ? 'bg-purple-500/20' : 'bg-purple-100'} shadow-sm`}>
                      <Package className={`w-5 h-5 ${theme === 'dark' ? 'text-purple-400' : 'text-purple-600'}`} />
                    </div>
                  </div>
                  <p className={`text-3xl font-bold ${t.text.primary} transition-all duration-300 group-hover:scale-110 origin-left mb-1`}>{stats.totalOrders}</p>
                  <p className={`text-sm ${t.text.secondary} font-medium`}>Total Orders</p>
                </div>
              </GlassCard>
            </div>
          </div>

          {/* Status Pills - Horizontal Scrollable with Gradient Fade */}
          <div className="mb-6 relative">
            <div className="absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-current to-transparent pointer-events-none z-10" style={{ color: theme === 'dark' ? 'rgb(17, 24, 39)' : 'rgb(249, 250, 251)' }}></div>
            <div className="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-current to-transparent pointer-events-none z-10" style={{ color: theme === 'dark' ? 'rgb(17, 24, 39)' : 'rgb(249, 250, 251)' }}></div>
            <div className="-mx-4 px-4 overflow-x-auto scrollbar-hide">
              <div className="flex gap-3 pb-2 min-w-max">
                {Object.entries(statusCounts).map(([status, count]) => {
                  const config = statusConfig[status as keyof typeof statusConfig];
                  if (!config) return null;
                  const Icon = config.icon;
                  const isActive = filters.status === status;

                  return (
                    <button
                      key={status}
                      onClick={() => handleFilterChange('status', status)}
                      className={`
                        group relative flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200
                        ${isActive 
                          ? `${theme === 'dark' ? 'bg-gradient-to-r from-[#1925c3]/30 to-[#c71719]/30' : 'bg-gradient-to-r from-[#1925c3]/20 to-[#c71719]/20'} ring-2 ring-[#c71719] scale-105 shadow-lg backdrop-blur-xl` 
                          : `${t.glass.card} ${t.glass.hover} hover:scale-105 hover:shadow-md`
                        }
                      `}
                    >
                      {isActive && (
                        <div className="absolute inset-0 bg-gradient-to-r from-[#1925c3]/10 to-[#c71719]/10 rounded-xl animate-pulse"></div>
                      )}
                      <div className={`relative p-2 rounded-lg transition-colors ${isActive ? 'bg-gradient-to-br from-[#1925c3]/30 to-[#c71719]/30' : theme === 'dark' ? 'bg-white/10' : 'bg-gray-100'}`}>
                        <Icon className={`w-4 h-4 ${isActive ? 'text-[#c71719]' : t.text.primary}`} />
                      </div>
                      <div className="relative text-left">
                        <p className={`text-lg font-semibold ${t.text.primary}`}>{count}</p>
                        <p className={`text-xs ${t.text.secondary}`}>{config.label}</p>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="px-4 sm:px-6 lg:px-8 pb-8">
        {/* Enhanced Filter Bar */}
        <div className="mb-6 -mt-8 relative z-10">
          <GlassCard variant="default" className="p-4">
            <div className="flex flex-col lg:flex-row gap-4">
              {/* Tabs */}
              <div className="flex-shrink-0">
                <div className={`inline-flex ${theme === 'dark' ? 'bg-white/5' : 'bg-gray-100'} p-1 rounded-xl`}>
                  <button
                    onClick={() => handleTabChange('requiring_action')}
                    className={`px-4 py-2 rounded-lg font-medium text-sm transition-all ${
                      activeTab === 'requiring_action'
                        ? `${theme === 'dark' ? 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white shadow-lg' : 'bg-white text-gray-900 shadow'}`
                        : `${t.text.secondary} hover:${t.text.primary}`
                    }`}
                  >
                    <span className="flex items-center gap-2">
                      Requiring Action
                      {stats.pendingActions > 0 && (
                        <span className={`${activeTab === 'requiring_action' ? 'bg-white/20 text-white' : theme === 'dark' ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-600'} px-2 py-0.5 rounded-full text-xs font-bold`}>
                          {stats.pendingActions}
                        </span>
                      )}
                    </span>
                  </button>
                  <button
                    onClick={() => handleTabChange('all_orders')}
                    className={`px-4 py-2 rounded-lg font-medium text-sm transition-all ${
                      activeTab === 'all_orders'
                        ? `${theme === 'dark' ? 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white shadow-lg' : 'bg-white text-gray-900 shadow'}`
                        : `${t.text.secondary} hover:${t.text.primary}`
                    }`}
                  >
                    All Orders
                  </button>
                </div>
              </div>

              {/* Search and Filters */}
              <div className="flex-1 flex flex-col sm:flex-row gap-3">
                <div className="flex-1 relative">
                  <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 ${t.text.muted}`} />
                  <input
                    type="text"
                    placeholder="Search orders, providers, or patient IDs..."
                    className={`pl-10 pr-4 py-2.5 w-full rounded-xl text-sm ${theme === 'dark' ? 'bg-white/5 hover:bg-white/10' : 'bg-white hover:bg-gray-50'} backdrop-blur-sm border ${theme === 'dark' ? 'border-white/10 focus:border-[#c71719]/50' : 'border-gray-300 focus:border-[#1925c3]'} transition-all focus:ring-2 ${theme === 'dark' ? 'focus:ring-[#c71719]/20' : 'focus:ring-[#1925c3]/20'} outline-none`}
                    value={searchQuery}
                    onChange={(e) => handleSearch(e.target.value)}
                  />
                </div>

                <div className="flex gap-2">
                  <button className={`px-4 py-2.5 ${theme === 'dark' ? 'bg-white/5 hover:bg-white/10' : 'bg-white hover:bg-gray-50'} backdrop-blur-sm rounded-xl flex items-center gap-2 text-sm font-medium transition-all border ${theme === 'dark' ? 'border-white/10' : 'border-gray-300'} shadow-sm hover:shadow-md`}>
                    <CalendarRange className="w-4 h-4" />
                    <span className="hidden sm:inline">Date Range</span>
                  </button>

                  <select
                    className={`px-4 py-2.5 rounded-xl text-sm ${theme === 'dark' ? 'bg-white/5 hover:bg-white/10' : 'bg-white hover:bg-gray-50'} backdrop-blur-sm border ${theme === 'dark' ? 'border-white/10 focus:border-[#c71719]/50' : 'border-gray-300 focus:border-[#1925c3]'} transition-all min-w-[150px] cursor-pointer outline-none focus:ring-2 ${theme === 'dark' ? 'focus:ring-[#c71719]/20' : 'focus:ring-[#1925c3]/20'}`}
                    value={filters.manufacturer || ''}
                    onChange={(e) => handleFilterChange('manufacturer', e.target.value)}
                  >
                    <option value="">All Manufacturers</option>
                    {manufacturers.map((manufacturer) => (
                      <option key={manufacturer.id} value={manufacturer.id}>
                        {manufacturer.name}
                      </option>
                    ))}
                  </select>

                  <button className={`p-2.5 ${theme === 'dark' ? 'bg-white/5 hover:bg-white/10' : 'bg-white hover:bg-gray-50'} backdrop-blur-sm rounded-xl transition-all group border ${theme === 'dark' ? 'border-white/10' : 'border-gray-300'} shadow-sm hover:shadow-md`}>
                    <Filter className="h-4 w-4 group-hover:rotate-180 transition-transform duration-300" />
                  </button>

                  {(filters.search || filters.status || filters.manufacturer) && (
                    <button 
                      onClick={() => {
                        setSearchQuery('');
                        router.get(window.location.pathname, {}, { preserveState: false });
                      }}
                      className={`p-2.5 ${theme === 'dark' ? 'bg-red-500/20 text-red-300 hover:bg-red-500/30' : 'bg-red-100 text-red-600 hover:bg-red-200'} rounded-xl transition-all`}
                    >
                      <FilterX className="h-4 w-4" />
                    </button>
                  )}
                </div>
              </div>
            </div>

            {/* Active Filters */}
            {(filters.search || filters.status || filters.manufacturer) && (
              <div className="flex flex-wrap gap-2 mt-4">
                {filters.search && (
                  <span className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${theme === 'dark' ? 'bg-white/10 text-white/80' : 'bg-gray-100 text-gray-700'}`}>
                    Search: {filters.search}
                    <button onClick={() => handleFilterChange('search', '')} className="ml-1 hover:text-red-500">
                      <XCircle className="w-3 h-3" />
                    </button>
                  </span>
                )}
                {filters.status && (
                  <span className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${theme === 'dark' ? 'bg-white/10 text-white/80' : 'bg-gray-100 text-gray-700'}`}>
                    Status: {statusConfig[filters.status as keyof typeof statusConfig]?.label}
                    <button onClick={() => handleFilterChange('status', '')} className="ml-1 hover:text-red-500">
                      <XCircle className="w-3 h-3" />
                    </button>
                  </span>
                )}
                {filters.manufacturer && (
                  <span className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${theme === 'dark' ? 'bg-white/10 text-white/80' : 'bg-gray-100 text-gray-700'}`}>
                    Manufacturer: {manufacturers.find(m => m.id.toString() === filters.manufacturer)?.name}
                    <button onClick={() => handleFilterChange('manufacturer', '')} className="ml-1 hover:text-red-500">
                      <XCircle className="w-3 h-3" />
                    </button>
                  </span>
                )}
              </div>
            )}
          </GlassCard>
        </div>

        {/* Enhanced Orders Table */}
        <div className="relative">
          <GlassTable className={`rounded-2xl overflow-hidden ${theme === 'dark' ? 'shadow-2xl shadow-black/30' : 'shadow-xl shadow-gray-200/50'}`}>
            <Table>
              <Thead>
                <Tr>
                  <Th>
                    <div className="flex items-center gap-2">
                      <div className={`p-1.5 rounded-lg ${theme === 'dark' ? 'bg-white/10' : 'bg-gray-100'}`}>
                        <Hash className="w-3 h-3" />
                      </div>
                      <span>Order Details</span>
                    </div>
                  </Th>
                  <Th>
                    <div className="flex items-center gap-2">
                      <div className={`p-1.5 rounded-lg ${theme === 'dark' ? 'bg-white/10' : 'bg-gray-100'}`}>
                        <User className="w-3 h-3" />
                      </div>
                      <span>Provider & Patient</span>
                    </div>
                  </Th>
                  <Th>Status & Timeline</Th>
                  <Th>
                    <div className="flex items-center gap-2">
                      <div className={`p-1.5 rounded-lg ${theme === 'dark' ? 'bg-white/10' : 'bg-gray-100'}`}>
                        <Building2 className="w-3 h-3" />
                      </div>
                      <span>Manufacturer</span>
                    </div>
                  </Th>
                  <Th className="text-center">Actions</Th>
                </Tr>
              </Thead>
              <Tbody>
                {filteredOrders.map((order, index) => (
                  <Tr
                    key={order.id}
                    className={`cursor-pointer group transition-all hover:scale-[1.005] ${theme === 'dark' ? 'hover:bg-white/[0.05]' : 'hover:bg-gray-50'}`}
                    onClick={() => router.visit(`/admin/orders/${order.id}`)}
                  >
                    <Td className="font-medium">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className={`text-sm font-semibold ${t.text.primary}`}>
                            {order.order_number || 'N/A'}
                          </span>
                          {order.products_count > 0 && (
                            <span className={`text-xs px-2 py-0.5 rounded-full ${theme === 'dark' ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700'} font-medium`}>
                              {order.products_count} items
                            </span>
                          )}
                        </div>
                        <div className={`text-xs ${t.text.secondary}`}>
                          Total: {formatCurrency(order.total_order_value)}
                        </div>
                      </div>
                    </Td>
                    <Td>
                      <div className="space-y-2">
                        <div className="flex items-center gap-2">
                          <div className={`w-8 h-8 rounded-xl ${theme === 'dark' ? 'bg-gradient-to-br from-[#1925c3]/30 to-[#c71719]/30' : 'bg-gradient-to-br from-[#1925c3]/20 to-[#c71719]/20'} flex items-center justify-center shadow-sm`}>
                            <User className={`w-4 h-4 ${theme === 'dark' ? 'text-white/80' : 'text-gray-700'}`} />
                          </div>
                          <div>
                            <div className={`text-sm font-medium ${t.text.primary}`}>
                              {order.provider?.name || 'Unknown'}
                            </div>
                            <div className={`text-xs ${t.text.secondary}`}>
                              {order.facility?.name || 'Unknown Facility'}
                            </div>
                          </div>
                        </div>
                        <div className={`flex items-center gap-2 pl-10`}>
                          <span className={`text-xs ${t.text.muted}`}>Patient:</span>
                          <span className={`text-xs font-medium ${t.text.secondary}`}>
                            {order.patient_display_id || 'N/A'}
                          </span>
                        </div>
                      </div>
                    </Td>
                    <Td>
                      <div className="space-y-2">
                        <OrderStatusBadge status={order.order_status} size="md" showIcon />
                        <div className={`text-xs ${t.text.secondary} space-y-0.5`}>
                          <div className="flex items-center gap-1">
                            <Clock className="w-3 h-3" />
                            Submitted: {formatDate(order.submitted_at)}
                          </div>
                          <div className="flex items-center gap-1">
                            <Calendar className="w-3 h-3" />
                            Service: {formatDate(order.expected_service_date)}
                          </div>
                        </div>
                      </div>
                    </Td>
                    <Td>
                      <div className="flex items-center gap-2">
                        <div className={`w-10 h-10 rounded-xl ${theme === 'dark' ? 'bg-purple-500/20' : 'bg-purple-100'} flex items-center justify-center shadow-sm`}>
                          <Package className={`w-5 h-5 ${theme === 'dark' ? 'text-purple-300' : 'text-purple-600'}`} />
                        </div>
                        <div>
                          <div className={`text-sm font-medium ${t.text.primary}`}>
                            {order.manufacturer?.name || 'Unknown'}
                          </div>
                          {order.manufacturer?.contact_email && (
                            <div className={`text-xs ${t.text.muted}`}>
                              {order.manufacturer.contact_email}
                            </div>
                          )}
                        </div>
                      </div>
                    </Td>
                    <Td className="text-center">
                      <div className="flex items-center justify-center gap-2">
                        {order.action_required ? (
                          <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium ${theme === 'dark' ? 'bg-red-500/20 text-red-300 ring-1 ring-red-500/30' : 'bg-red-100 text-red-700 ring-1 ring-red-200'}`}>
                            <AlertTriangle className="w-3 h-3 mr-1.5" />
                            Action Required
                          </span>
                        ) : (
                          <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium ${theme === 'dark' ? 'bg-green-500/20 text-green-300' : 'bg-green-100 text-green-700'}`}>
                            <CheckCircle className="w-3 h-3 mr-1.5" />
                            On Track
                          </span>
                        )}
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/admin/orders/${order.id}`);
                          }}
                          className={`p-2 rounded-lg ${theme === 'dark' ? 'bg-white/10 hover:bg-white/20' : 'bg-gray-100 hover:bg-gray-200'} opacity-0 group-hover:opacity-100 transition-all`}
                        >
                          <ChevronRight className="h-4 w-4" />
                        </button>
                      </div>
                    </Td>
                  </Tr>
                ))}

              {filteredOrders.length === 0 && (
                <Tr>
                  <Td colSpan={5} className="text-center py-20">
                    <div className="max-w-md mx-auto">
                      <div className="relative mb-6">
                        <div className={`absolute inset-0 ${theme === 'dark' ? 'bg-gradient-to-r from-[#1925c3]/30 to-[#c71719]/30' : 'bg-gradient-to-r from-[#1925c3]/20 to-[#c71719]/20'} blur-3xl rounded-full animate-pulse`}></div>
                        <Package className={`relative h-20 w-20 mx-auto ${theme === 'dark' ? 'text-white/30' : 'text-gray-400'}`} />
                      </div>
                      <p className={`text-2xl font-bold ${t.text.primary} mb-3`}>No orders found</p>
                      <p className={`text-base ${t.text.secondary} mb-6`}>
                        {activeTab === 'requiring_action'
                          ? 'Great! No orders require your attention right now'
                          : 'Try adjusting your filters or search criteria'}
                      </p>
                      {filters.search || filters.status || filters.manufacturer ? (
                        <button
                          onClick={() => {
                            setSearchQuery('');
                            router.get(window.location.pathname, {}, { preserveState: false });
                          }}
                          className={`inline-flex items-center px-5 py-2.5 ${theme === 'dark' ? 'bg-white/10 hover:bg-white/15' : 'bg-gray-100 hover:bg-gray-200'} rounded-xl text-sm font-semibold transition-all shadow-sm hover:shadow-md`}
                        >
                          <FilterX className="w-4 h-4 mr-2" />
                          Clear all filters
                        </button>
                      ) : null}
                    </div>
                  </Td>
                </Tr>
              )}
            </Tbody>
          </Table>
        </GlassTable>

        {/* Enhanced Pagination */}
        {orders.links && orders.data.length > 0 && (
          <div className="mt-6">
            <GlassCard variant="default" className="p-4">
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="text-center sm:text-left">
                  <p className={`text-sm ${t.text.secondary}`}>
                    Showing <span className={`font-semibold ${t.text.primary} text-base`}>{((orders.current_page - 1) * orders.per_page) + 1}</span>
                    {' - '}
                    <span className={`font-semibold ${t.text.primary} text-base`}>{Math.min(orders.current_page * orders.per_page, orders.total)}</span>
                    {' of '}
                    <span className={`font-semibold ${t.text.primary} text-base`}>{orders.total}</span>
                    {' orders'}
                  </p>
                </div>
                
                <nav className="flex items-center gap-1" aria-label="Pagination">
                  {orders.links.map((link, index) => {
                    const isFirst = index === 0;
                    const isLast = index === orders.links.length - 1;
                    const isActive = link.active;
                    const isDisabled = !link.url;
                    
                    return (
                      <Link
                        key={index}
                        href={link.url || '#'}
                        preserveScroll
                        preserveState
                        className={`
                          relative inline-flex items-center justify-center min-w-[40px] h-10 px-3
                          text-sm font-medium transition-all duration-200
                          ${isFirst || isLast ? 'px-4' : ''}
                          ${isActive
                            ? 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white shadow-lg scale-105 z-10'
                            : isDisabled
                            ? `${theme === 'dark' ? 'bg-white/5 text-white/30' : 'bg-gray-100 text-gray-300'} cursor-not-allowed`
                            : `${t.glass.card} ${t.text.secondary} ${t.glass.hover} hover:scale-105`
                          }
                          ${isFirst ? 'rounded-l-xl' : ''}
                          ${isLast ? 'rounded-r-xl' : ''}
                          ${!isFirst && !isLast ? 'rounded-lg' : ''}
                        `}
                      >
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                      </Link>
                    );
                  })}
                </nav>
              </div>
            </GlassCard>
          </div>
        )}

        {/* Enhanced Floating Action Button */}
        <div className="fixed bottom-8 right-8 group">
          <div className={`absolute -inset-4 bg-gradient-to-r from-[#1925c3] to-[#c71719] rounded-full blur-lg opacity-30 group-hover:opacity-60 transition-opacity duration-300`}></div>
          <Link
            href="/admin/orders/create"
            className={`relative flex items-center space-x-2 bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white font-semibold rounded-full p-4 shadow-2xl transform transition-all duration-300 hover:scale-110 hover:shadow-[0_20px_60px_rgba(199,23,25,0.3)]`}
          >
            <Package className="h-6 w-6" />
            <span className="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300 ease-in-out whitespace-nowrap">
              <span className="pl-2 pr-2">Create Order</span>
            </span>
          </Link>
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderCenter;
