import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';

import {
  Package,
  Clock,
  CheckCircle,
  AlertTriangle,
  FileText,
  Plus,
  Search,
  Filter,
  Calendar,
  TrendingUp,
  User,
  Building2,
  ChevronRight,
  ChevronLeft,
  Eye,
  Download,
  RefreshCw,
  Bell,
  Activity,
  Timer,
  BarChart3,
} from 'lucide-react';

interface Order {
  id: string;
  order_number: string;
  patient_display_id: string;
  status: string;
  created_at: string;
  expected_service_date: string;
  products: Array<{
    id: number;
    name: string;
    quantity: number;
  }>;
  manufacturer: {
    name: string;
  };
  ivr_status: string;
  tracking_number?: string;
  action_required: boolean;
  priority: 'low' | 'medium' | 'high' | 'critical';
}

interface DashboardStats {
  total_orders: number;
  pending_ivr: number;
  in_progress: number;
  completed: number;
  success_rate: number;
  average_completion_time: number;
}

interface Props {
  orders: Order[];
  stats: DashboardStats;
  recentActivity: Array<{
    id: string;
    type: string;
    description: string;
    timestamp: string;
  }>;
  upcomingDeadlines: Array<{
    id: string;
    description: string;
    due_date: string;
    priority: string;
  }>;
  aiInsights: {
    message: string;
    urgentCount: number;
    upcomingCount: number;
    hasActions: boolean;
  };
}

export default function ProviderOrdersDashboard({
  orders = [],
  stats = {
    total_orders: 0,
    pending_ivr: 0,
    in_progress: 0,
    completed: 0,
    success_rate: 0,
    average_completion_time: 0
  },
  recentActivity = [],
  upcomingDeadlines = [],
  aiInsights = {
    message: "Welcome to your dashboard",
    urgentCount: 0,
    upcomingCount: 0,
    hasActions: false
  }
}: Props) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedFilter, setSelectedFilter] = useState('all');

  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  // Filter orders
  const filteredOrders = orders.filter(order => {
    const matchesSearch = searchQuery === '' ||
      order.order_number.toLowerCase().includes(searchQuery.toLowerCase()) ||
      order.patient_display_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
      order.manufacturer.name.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesFilter = selectedFilter === 'all' ||
      (selectedFilter === 'pending' && order.status === 'pending_ivr') ||
      (selectedFilter === 'action_required' && order.action_required) ||
      (selectedFilter === 'completed' && order.status === 'completed');

    return matchesSearch && matchesFilter;
  });

  // Real-time updates
  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ['orders', 'stats', 'recentActivity'] });
    }, 60000);

    return () => clearInterval(interval);
  }, []);

  return (
    <MainLayout>
      <Head title="Orders Dashboard" />
      
      <div className="container mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className={`text-2xl font-bold ${t.text.primary}`}>Orders Dashboard</h1>
          <p className={`text-sm ${t.text.secondary}`}>Manage and track your patient orders</p>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div className={`${t.glass.card} ${t.glass.border} p-6`}>
            <div className="flex items-center justify-between mb-4">
              <Package className="w-8 h-8 text-blue-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.total_orders}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Total Orders</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6`}>
            <div className="flex items-center justify-between mb-4">
              <Clock className="w-8 h-8 text-yellow-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.pending_ivr}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Pending IVR</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6`}>
            <div className="flex items-center justify-between mb-4">
              <Activity className="w-8 h-8 text-purple-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.in_progress}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>In Progress</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6`}>
            <div className="flex items-center justify-between mb-4">
              <CheckCircle className="w-8 h-8 text-green-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.completed}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Completed</p>
          </div>
        </div>

        {/* Search and Filter */}
        <div className={`${t.glass.card} ${t.glass.border} p-6 mb-8`}>
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${t.text.muted}`} />
              <input
                type="text"
                placeholder="Search orders..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-10 pr-4 py-2 ${t.input.base} ${t.input.focus} rounded-lg`}
              />
            </div>
            <select
              value={selectedFilter}
              onChange={(e) => setSelectedFilter(e.target.value)}
              className={`px-4 py-2 ${t.input.select} rounded-lg`}
            >
              <option value="all">All Orders</option>
              <option value="pending">Pending IVR</option>
              <option value="action_required">Action Required</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>

        {/* Orders List */}
        <div className="space-y-4">
          {filteredOrders.map((order) => (
            <div key={order.id} className={`${t.glass.card} ${t.glass.border} p-6`}>
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className={`text-lg font-semibold ${t.text.primary}`}>
                      {order.order_number}
                    </h3>
                    <span className={`px-2 py-1 rounded-full text-xs ${
                      order.status === 'completed' ? 'bg-green-100 text-green-800' :
                      order.status === 'pending_ivr' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-blue-100 text-blue-800'
                    }`}>
                      {order.status.replace('_', ' ')}
                    </span>
                  </div>
                  <p className={`text-sm ${t.text.secondary} mb-2`}>
                    Patient: {order.patient_display_id}
                  </p>
                  <p className={`text-sm ${t.text.secondary} mb-2`}>
                    Manufacturer: {order.manufacturer.name}
                  </p>
                                     <div className="flex flex-wrap gap-2">
                     {order.products.map((product) => (
                       <span key={product.id} className={`px-2 py-1 rounded text-xs ${t.glass.card} border ${t.glass.border}`}>
                         {product.name} ({product.quantity})
                       </span>
                     ))}
                   </div>
                </div>
                <div className="flex items-center gap-2">
                  <Link
                    href={route('orders.show', order.id)}
                    className={`${t.button.primary} px-4 py-2 rounded-lg flex items-center gap-2`}
                  >
                    <Eye className="w-4 h-4" />
                    View
                  </Link>
                </div>
              </div>
            </div>
          ))}
        </div>

        {filteredOrders.length === 0 && (
          <div className={`${t.glass.card} ${t.glass.border} p-12 text-center`}>
            <Package className={`w-16 h-16 mx-auto mb-4 ${t.text.muted}`} />
            <p className={`text-lg ${t.text.secondary}`}>No orders found</p>
            <p className={`text-sm ${t.text.muted}`}>Try adjusting your search or filter criteria</p>
          </div>
        )}

        {/* Action Button */}
        <div className="fixed bottom-6 right-6">
          <Link
            href={route('quick-requests.create-new')}
            className={`${t.button.primary} p-4 rounded-full shadow-lg flex items-center gap-2`}
          >
            <Plus className="w-6 h-6" />
            <span className="hidden sm:inline">New Request</span>
          </Link>
        </div>
      </div>
    </MainLayout>
  );
}
