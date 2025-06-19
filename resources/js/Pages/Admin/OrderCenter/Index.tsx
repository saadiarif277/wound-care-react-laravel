import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import Button from '@/Components/ui/button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import OrderStatusBadge, { OrderStatus } from '@/Components/Order/OrderStatusBadge';
import IVRExpirationWarning from '@/Components/Admin/IVRExpirationWarning';
import EpisodeCard from '@/Components/Episodes/EpisodeCard';
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
  Users,
  Calendar,
  Building2,
  Heart,
  Eye,
  Download,
  RefreshCw,
  BarChart3,
  AlertCircle,
  Zap,
  Timer,
  DollarSign,
  User,
  MapPin,
  Mail,
  Phone,
  Pill,
  Stethoscope,
  Grid3X3,
  List,
  Layers,
  Target,
  Percent,
  TrendingDown,
  Settings,
  Star,
  Sparkles,
  Shield,
  HelpCircle,
  ChevronDown,
  X,
  Calendar as CalendarIcon,
  Truck,
  FileCheck,
  Clock3,
  ArrowUpRight,
  ArrowDownRight,
  Database,
  Home,
} from 'lucide-react';
import { SelectItem, SelectContent, SelectTrigger, SelectValue } from '@/Components/GhostAiUi/ui/select';

// Enhanced status configuration for episodes with 2025 healthcare design principles
const episodeStatusConfig = {
  ready_for_review: {
    icon: Clock,
    label: 'Ready for Review',
    color: 'blue',
    bgColor: 'bg-blue-50',
    textColor: 'text-blue-700',
    borderColor: 'border-blue-200',
    priority: 'high',
    description: 'Episode awaiting initial review and IVR generation',
    actionText: 'Review Required',
    gradient: 'from-blue-500 to-blue-600'
  },
  ivr_sent: {
    icon: FileText,
    label: 'IVR Sent',
    color: 'yellow',
    bgColor: 'bg-yellow-50',
    textColor: 'text-yellow-700',
    borderColor: 'border-yellow-200',
    priority: 'medium',
    description: 'IVR documentation sent to manufacturer',
    actionText: 'Awaiting Verification',
    gradient: 'from-yellow-500 to-yellow-600'
  },
  ivr_verified: {
    icon: CheckCircle2,
    label: 'IVR Verified',
    color: 'green',
    bgColor: 'bg-green-50',
    textColor: 'text-green-700',
    borderColor: 'border-green-200',
    priority: 'low',
    description: 'IVR verified and ready for submission',
    actionText: 'Ready to Send',
    gradient: 'from-green-500 to-green-600'
  },
  sent_to_manufacturer: {
    icon: Package,
    label: 'Sent to Manufacturer',
    color: 'purple',
    bgColor: 'bg-purple-50',
    textColor: 'text-purple-700',
    borderColor: 'border-purple-200',
    priority: 'medium',
    description: 'Episode submitted to manufacturer for processing',
    actionText: 'In Processing',
    gradient: 'from-purple-500 to-purple-600'
  },
  tracking_added: {
    icon: Truck,
    label: 'Tracking Added',
    color: 'indigo',
    bgColor: 'bg-indigo-50',
    textColor: 'text-indigo-700',
    borderColor: 'border-indigo-200',
    priority: 'low',
    description: 'Tracking information added and shipment in progress',
    actionText: 'In Transit',
    gradient: 'from-indigo-500 to-indigo-600'
  },
  completed: {
    icon: CheckCircle2,
    label: 'Completed',
    color: 'green',
    bgColor: 'bg-green-50',
    textColor: 'text-green-700',
    borderColor: 'border-green-200',
    priority: 'low',
    description: 'Episode fully processed and completed',
    actionText: 'Completed',
    gradient: 'from-green-500 to-green-600'
  },
};

// Enhanced IVR status configuration with healthcare-appropriate styling
const ivrStatusConfig = {
  pending: {
    icon: Clock,
    label: 'Pending IVR',
    color: 'gray',
    bgColor: 'bg-gray-50',
    textColor: 'text-gray-700',
    borderColor: 'border-gray-200',
    description: 'IVR not yet generated or sent'
  },
  verified: {
    icon: CheckCircle2,
    label: 'Verified',
    color: 'green',
    bgColor: 'bg-green-50',
    textColor: 'text-green-700',
    borderColor: 'border-green-200',
    description: 'IVR verified and valid'
  },
  expired: {
    icon: AlertTriangle,
    label: 'Expired',
    color: 'red',
    bgColor: 'bg-red-50',
    textColor: 'text-red-700',
    borderColor: 'border-red-200',
    description: 'IVR has expired and requires renewal'
  },
};

interface Episode {
  id: string;
  patient_id: string;
  patient_name?: string;
  patient_display_id: string;
  manufacturer: {
    id: number;
    name: string;
    contact_email?: string;
  };
  status: keyof typeof episodeStatusConfig;
  ivr_status: keyof typeof ivrStatusConfig;
  verification_date?: string;
  expiration_date?: string;
  orders_count: number;
  total_order_value: number;
  latest_order_date: string;
  action_required: boolean;
  created_at: string;
  orders: Array<{
    id: string;
    order_number: string;
    order_status: string;
    expected_service_date: string;
    submitted_at: string;
  }>;
}

interface OrderCenterProps {
  episodes: {
    data: Episode[];
    links: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    search?: string;
    status?: string;
    ivr_status?: string;
    action_required?: boolean;
    manufacturer?: string;
    date_range?: string;
  };
  statusCounts: {
    [key: string]: number;
  };
  ivrStatusCounts: {
    [key: string]: number;
  };
  manufacturers: Array<{ id: number; name: string }>;
  expiringIVRs?: Array<{
    id: number;
    patient_fhir_id: string;
    patient_name: string;
    manufacturer_name: string;
    expiration_date: string;
    days_until_expiration: number;
  }>;
}

export default function OrderCenter({
  episodes,
  filters,
  statusCounts,
  ivrStatusCounts,
  manufacturers,
  expiringIVRs = [],
}: OrderCenterProps) {
  const [search, setSearch] = useState(filters.search || '');
  const [viewMode, setViewMode] = useState<'cards' | 'table'>('cards');
  const [showFilters, setShowFilters] = useState(false);
  const [lastRefresh, setLastRefresh] = useState(new Date());
  const [selectedEpisodes, setSelectedEpisodes] = useState<string[]>([]);

  // Comprehensive statistics calculation with 2025 healthcare metrics
  const statistics = useMemo(() => {
    const totalEpisodes = Object.values(statusCounts).reduce((sum, count) => sum + count, 0);
    const actionRequiredCount = statusCounts.ready_for_review || 0;
    const completedCount = statusCounts.completed || 0;
    const completionRate = totalEpisodes > 0 ? (completedCount / totalEpisodes) * 100 : 0;

    const today = new Date().toDateString();
    const todayEpisodes = episodes.data.filter(episode =>
      new Date(episode.latest_order_date).toDateString() === today
    ).length;

    const expiringCount = expiringIVRs.filter(ivr => ivr.days_until_expiration <= 7).length;

    const totalRevenue = episodes.data.reduce((sum, episode) => sum + episode.total_order_value, 0);
    const averageOrderValue = totalEpisodes > 0 ? totalRevenue / totalEpisodes : 0;

    return {
      totalEpisodes,
      actionRequiredCount,
      completionRate,
      todayEpisodes,
      expiringCount,
      totalRevenue,
      averageOrderValue,
    };
  }, [statusCounts, episodes.data, expiringIVRs]);

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('admin.orders.index'), {
      ...filters,
      search: search || undefined,
      page: undefined,
    });
  };

  const handleQuickFilter = (filterType: string, value: string) => {
    const newFilters = { ...filters };

    if (filterType === 'clear') {
      router.get(route('admin.orders.index'));
      return;
    }

    if (filterType === 'action_required') {
      newFilters.action_required = value === 'true' ? true : undefined;
    } else if (filterType === 'status') {
      newFilters.status = newFilters.status === value ? undefined : value;
    } else if (filterType === 'ivr_status') {
      newFilters.ivr_status = newFilters.ivr_status === value ? undefined : value;
    } else {
      (newFilters as any)[filterType] = (newFilters as any)[filterType] === value ? undefined : value;
    }

    router.get(route('admin.orders.index'), {
      ...newFilters,
      page: undefined,
    });
  };

  const refreshData = () => {
    setLastRefresh(new Date());
    router.reload({ only: ['episodes', 'statusCounts', 'ivrStatusCounts'] });
  };

  const getEpisodeStatusBadge = (status: keyof typeof episodeStatusConfig) => {
    const config = episodeStatusConfig[status] || episodeStatusConfig.ready_for_review;
    const IconComponent = config.icon;

    return (
      <div className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border ${config.bgColor} ${config.textColor} ${config.borderColor}`}>
        <IconComponent className="w-3 h-3 mr-1" />
        {config.label}
      </div>
    );
  };

  const getIvrStatusBadge = (ivrStatus: keyof typeof ivrStatusConfig) => {
    const config = ivrStatusConfig[ivrStatus] || ivrStatusConfig.pending;
    const IconComponent = config.icon;

    return (
      <div className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border ${config.bgColor} ${config.textColor} ${config.borderColor}`}>
        <IconComponent className="w-3 h-3 mr-1" />
        {config.label}
      </div>
    );
  };

  const getPriorityLevel = (episode: Episode) => {
    if (episode.action_required) return 'critical';

    const config = episodeStatusConfig[episode.status];
    return config?.priority || 'low';
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'critical': return 'border-l-red-500 bg-red-50';
      case 'high': return 'border-l-orange-500 bg-orange-50';
      case 'medium': return 'border-l-yellow-500 bg-yellow-50';
      default: return 'border-l-blue-500 bg-blue-50';
    }
  };

  return (
    <MainLayout>
      <Head title="Episode-Based Order Center | MSC Healthcare Distribution Platform" />

      <div className="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-indigo-50/20 p-4 lg:p-6">
        {/* Enhanced Header with 2025 Healthcare Design */}
        <div className="mb-8">
          <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-6 relative overflow-hidden">
            {/* Subtle background pattern */}
            <div className="absolute inset-0 opacity-5">
              <div className="absolute inset-0 bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600"></div>
            </div>

            <div className="relative flex flex-col lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center space-x-4 mb-4 lg:mb-0">
                <div className="flex items-center">
                  <div className="p-3 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl mr-4 shadow-lg">
                    <Layers className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h1 className="text-3xl font-bold bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 bg-clip-text text-transparent">
                      Episode Order Center
                    </h1>
                    <p className="text-sm text-gray-600 mt-1">
                      Comprehensive episode-based order management with real-time insights
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex flex-col sm:flex-row gap-3">
                <button
                  onClick={refreshData}
                  className="flex items-center px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white/80 hover:bg-white hover:shadow-md transition-all duration-300 backdrop-blur-sm"
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Refresh
                </button>

                <button className="flex items-center px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white/80 hover:bg-white hover:shadow-md transition-all duration-300 backdrop-blur-sm">
                  <Download className="w-4 h-4 mr-2" />
                  Export Data
                </button>

                <Link
                  href={route('admin.orders.create')}
                  className="flex items-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-medium hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl"
                >
                  <Plus className="w-4 h-4 mr-2" />
                  New Episode
                </Link>
              </div>
            </div>

            {/* Enhanced metrics bar */}
            <div className="mt-6 flex items-center justify-between text-xs text-gray-500 bg-gray-50/50 rounded-xl p-3">
              <div className="flex items-center space-x-4">
                <div className="flex items-center">
                  <Clock3 className="w-3 h-3 mr-1" />
                  Last updated: {formatDateTime(lastRefresh.toISOString())}
                </div>
                <div className="flex items-center">
                  <Database className="w-3 h-3 mr-1" />
                  {episodes.total} total episodes
                </div>
                <div className="flex items-center">
                  <Activity className="w-3 h-3 mr-1" />
                  System healthy
                </div>
              </div>
              <div className="flex items-center">
                <Sparkles className="w-3 h-3 mr-1 text-blue-500" />
                <span className="text-blue-600 font-medium">Live updates enabled</span>
              </div>
            </div>
          </div>
        </div>

        {/* Enhanced Statistics Dashboard - 2025 Healthcare Metrics */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4 mb-8">
          {/* Total Episodes - Enhanced with micro-animations */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-blue-50/50 to-indigo-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Episodes</p>
                <p className="text-2xl font-bold text-gray-900 group-hover:text-blue-900 transition-colors">
                  {statistics.totalEpisodes}
                </p>
                <div className="mt-2 flex items-center text-xs">
                  <TrendingUp className="w-3 h-3 text-green-500 mr-1" />
                  <span className="text-green-600 font-medium">Active episodes</span>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <Layers className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>

          {/* Action Required - Priority highlighting */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-orange-50/50 to-red-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Action Required</p>
                <p className="text-2xl font-bold text-orange-600 group-hover:text-orange-700 transition-colors">
                  {statistics.actionRequiredCount}
                </p>
                <div className="mt-2 flex items-center text-xs">
                  <AlertCircle className="w-3 h-3 text-orange-500 mr-1 animate-pulse" />
                  <span className="text-orange-600 font-medium">Needs attention</span>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <AlertTriangle className="w-6 h-6 text-white" />
              </div>
            </div>
            {statistics.actionRequiredCount > 0 && (
              <div className="absolute top-2 right-2">
                <div className="w-2 h-2 bg-red-500 rounded-full animate-ping"></div>
              </div>
            )}
          </div>

          {/* Completion Rate - Progress visualization */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-green-50/50 to-emerald-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-600">Completion Rate</p>
                <p className="text-2xl font-bold text-green-600 group-hover:text-green-700 transition-colors">
                  {statistics.completionRate.toFixed(1)}%
                </p>
                  <div
                    className={`bg-gradient-to-r from-green-500 to-emerald-500 h-1.5 rounded-full transition-all duration-1000 completion-bar`}
                    data-width={statistics.completionRate}
                  ></div>
                  </div>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <Target className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>

          {/* Today's Episodes - Real-time updates */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-blue-50/50 to-cyan-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Today's Episodes</p>
                <p className="text-2xl font-bold text-blue-600 group-hover:text-blue-700 transition-colors">
                  {statistics.todayEpisodes}
                </p>
                <div className="mt-2 flex items-center text-xs">
                  <Clock className="w-3 h-3 text-blue-500 mr-1" />
                  <span className="text-blue-600 font-medium">New today</span>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <CalendarIcon className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>

          {/* Expiring IVRs - Urgent attention indicator */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-red-50/50 to-pink-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Expiring IVRs</p>
                <p className="text-2xl font-bold text-red-600 group-hover:text-red-700 transition-colors">
                  {statistics.expiringCount}
                </p>
                <div className="mt-2 flex items-center text-xs">
                  <Timer className="w-3 h-3 text-red-500 mr-1" />
                  <span className="text-red-600 font-medium">≤7 days</span>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-red-500 to-pink-500 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <AlertTriangle className="w-6 h-6 text-white" />
              </div>
            </div>
            {statistics.expiringCount > 0 && (
              <div className="absolute top-2 right-2">
                <div className="w-2 h-2 bg-red-500 rounded-full animate-bounce"></div>
              </div>
            )}
          </div>

          {/* Total Revenue - Financial overview */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-emerald-50/50 to-teal-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Revenue</p>
                <p className="text-2xl font-bold text-emerald-600 group-hover:text-emerald-700 transition-colors">
                  {formatCurrency(statistics.totalRevenue)}
                </p>
                <div className="mt-2 flex items-center text-xs">
                  <TrendingUp className="w-3 h-3 text-emerald-500 mr-1" />
                  <span className="text-emerald-600 font-medium">Episode value</span>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <DollarSign className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>

          {/* Average Order Value - Performance metric */}
          <div className="group bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 cursor-default overflow-hidden relative">
            <div className="absolute inset-0 bg-gradient-to-br from-purple-50/50 to-indigo-50/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <div className="relative flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Avg Order Value</p>
                <p className="text-2xl font-bold text-purple-600 group-hover:text-purple-700 transition-colors">
                  {formatCurrency(statistics.averageOrderValue)}
                </p>
                <div className="mt-2 flex items-center text-xs">
                  <BarChart3 className="w-3 h-3 text-purple-500 mr-1" />
                  <span className="text-purple-600 font-medium">Per episode</span>
                </div>
              </div>
              <div className="p-3 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-xl shadow-md group-hover:shadow-lg transition-shadow">
                <BarChart3 className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>
        

        {/* Enhanced Search and Filters - 2025 UX Design */}
        <div className="bg-white/80 backdrop-blur-sm rounded-xl shadow-sm border border-gray-200/50 p-6 mb-6">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">

            {/* Enhanced Search Bar with voice search capability */}
            <div className="flex-1 max-w-md">
              <form onSubmit={handleSearch} className="relative group">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Search className="h-5 w-5 text-gray-400 group-focus-within:text-blue-500 transition-colors" />
                </div>
                <input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search episodes, patients, or manufacturers..."
                  className="block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-xl text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white/80 backdrop-blur-sm"
                />
                <button
                  type="button"
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-blue-500 transition-colors"
                  title="Voice search"
                >
                  <Sparkles className="h-4 w-4" />
                </button>
              </form>
            </div>

            {/* Enhanced Quick Filters */}
            <div className="flex items-center space-x-3">
              {/* Quick filter buttons with better visual feedback */}
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => handleQuickFilter('action_required', 'true')}
                  className={`px-3 py-2 text-xs font-medium rounded-lg transition-all duration-200 ${
                                         filters.action_required === true
                      ? 'bg-orange-100 text-orange-700 border border-orange-300 shadow-sm'
                      : 'bg-gray-100 text-gray-600 hover:bg-orange-50 hover:text-orange-600'
                  }`}
                >
                  <AlertCircle className="w-3 h-3 mr-1 inline" />
                  Action Required
                </button>

                <button
                  onClick={() => handleQuickFilter('ivr_status', 'expired')}
                  className={`px-3 py-2 text-xs font-medium rounded-lg transition-all duration-200 ${
                    filters.ivr_status === 'expired'
                      ? 'bg-red-100 text-red-700 border border-red-300 shadow-sm'
                      : 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600'
                  }`}
                >
                  <Timer className="w-3 h-3 mr-1 inline" />
                  Expired IVRs
                </button>
              </div>

              {/* View Mode Toggle with better design */}
              <div className="flex items-center bg-gray-100 rounded-xl p-1 shadow-inner">
                <button
                  onClick={() => setViewMode('cards')}
                  className={`p-2.5 rounded-lg transition-all duration-200 ${
                    viewMode === 'cards'
                      ? 'bg-white text-blue-600 shadow-sm scale-105'
                      : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                  title="Card view"
                >
                  <Grid3X3 className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setViewMode('table')}
                  className={`p-2.5 rounded-lg transition-all duration-200 ${
                    viewMode === 'table'
                      ? 'bg-white text-blue-600 shadow-sm scale-105'
                      : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                  }`}
                  title="Table view"
                >
                  <List className="w-4 h-4" />
                </button>
              </div>

              {/* Settings menu */}
              <button
                className="p-2.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-200"
                title="Settings"
              >
                <Settings className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Active filters display */}
          {(search || filters.status || filters.ivr_status || filters.manufacturer) && (
            <div className="mt-4 flex flex-wrap items-center gap-2">
              <span className="text-sm text-gray-500">Active filters:</span>
              {search && (
                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                  Search: "{search}"
                  <button
                    onClick={() => setSearch('')}
                    className="ml-2 hover:text-blue-900"
                    title="Clear search"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {filters.status && (
                <span className="inline-flex items-center px-3 py-1 rounded-full text-xs bg-purple-100 text-purple-700">
                  Status: {episodeStatusConfig[filters.status as keyof typeof episodeStatusConfig]?.label}
                  <button
                    onClick={() => handleQuickFilter('status', '')}
                    className="ml-2 hover:text-purple-900"
                    title="Clear status filter"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </span>
              )}
              {/* Additional filter badges as needed */}
            </div>
          )}
        </div>

        {/* Episodes List - New Streamlined Card View */}
        {viewMode === 'cards' ? (
          <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            {episodes.data.map((episode) => (
              <EpisodeCard
                key={episode.id}
                episode={episode}
                onRefresh={refreshData}
                viewMode="compact"
              />
            ))}
          </div>
        ) : (
          /* Enhanced Table View */
          <Card>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Patient & Episode
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Manufacturer
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Orders & Value
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Timeline
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {episodes.data.map((episode) => (
                      <tr
                        key={episode.id}
                        className="hover:bg-gray-50 cursor-pointer"
                        onClick={() => router.visit(`/admin/episodes/${episode.id}`)}
                      >
                        <td className="px-6 py-4">
                          <div className="flex items-center">
                            <div>
                              <div className="flex items-center space-x-2">
                                <div className="text-sm font-medium text-gray-900">
                                  {episode.patient_name || episode.patient_display_id}
                                </div>
                                {episode.action_required && (
                                  <Badge variant="destructive" className="text-xs">
                                    Action Required
                                  </Badge>
                                )}
                              </div>
                              <div className="text-sm text-gray-500">
                                ID: {episode.patient_display_id}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            {getEpisodeStatusBadge(episode.status)}
                            {getIvrStatusBadge(episode.ivr_status)}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">{episode.manufacturer.name}</div>
                          {episode.manufacturer.contact_email && (
                            <div className="text-sm text-gray-500 flex items-center">
                              <Mail className="w-3 h-3 mr-1" />
                              {episode.manufacturer.contact_email}
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">
                            {episode.orders_count} order{episode.orders_count !== 1 ? 's' : ''}
                          </div>
                          <div className="text-sm font-medium text-green-600">
                            {formatCurrency(episode.total_order_value)}
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="text-sm text-gray-900">
                            Latest: {formatDate(episode.latest_order_date)}
                          </div>
                          {episode.verification_date && (
                            <div className="text-sm text-gray-500">
                              Verified: {formatDate(episode.verification_date)}
                            </div>
                          )}
                        </td>
                        <td className="px-6 py-4">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                              e.stopPropagation();
                              router.visit(`/admin/episodes/${episode.id}`);
                            }}
                          >
                            <Eye className="w-4 h-4 mr-1" />
                            View
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Pagination */}
        {episodes.links && episodes.links.length > 3 && (
          <div className="mt-6 flex justify-center">
            <div className="flex space-x-1">
              {episodes.links.map((link, index) => (
                <Button
                  key={index}
                  variant={link.active ? "primary" : "secondary"}
                  size="sm"
                  onClick={() => link.url && router.visit(link.url)}
                  disabled={!link.url}
                >
                  <span dangerouslySetInnerHTML={{ __html: link.label }} />
                </Button>
              ))}
            </div>
          </div>
        )}

        {/* Empty State */}
        {episodes.data.length === 0 && (
          <Card className="text-center py-12">
            <CardContent>
              <Package className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">No episodes found</h3>
              <p className="text-gray-500 mb-4">
                {Object.values(filters).some(v => v)
                  ? 'Try adjusting your filters to see more episodes.'
                  : 'Episodes will appear here as providers submit orders.'
                }
              </p>
              {Object.values(filters).some(v => v) && (
                <Button
                  variant="secondary"
                  onClick={() => router.visit(window.location.pathname)}
                >
                  Clear Filters
                </Button>
              )}
            </CardContent>
          </Card>
        )}
      </MainLayout>
  );
}
