import React, { useState, useEffect, useRef } from 'react';
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
  Mic,
  MicOff,
  Star,
  Heart,
  MessageSquare,
  Activity,
  Timer,
  BarChart3,
  Sparkle,
  Brain,
  QrCode,
  Camera,
  Upload,
  Smartphone,
  Fingerprint,
  Shield,
  Zap,
  Info,
  ArrowUp,
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
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [searchQuery, setSearchQuery] = useState('');
  const [selectedFilter, setSelectedFilter] = useState<string>('all');
  const [showMobileMenu, setShowMobileMenu] = useState(false);
  const [voiceEnabled, setVoiceEnabled] = useState(false);
  const [touchStartX, setTouchStartX] = useState(0);
  const [isScanning, setIsScanning] = useState(false);
  const [selectedDate, setSelectedDate] = useState(new Date());
  const scannerRef = useRef<HTMLVideoElement>(null);

  // Mobile swipe gesture handling
  const handleTouchStart = (e: React.TouchEvent) => {
    setTouchStartX(e.touches[0].clientX);
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    const touchEndX = e.changedTouches[0].clientX;
    const swipeDistance = touchEndX - touchStartX;

    if (Math.abs(swipeDistance) > 100) {
      if (swipeDistance > 0) {
        // Swipe right - show menu
        setShowMobileMenu(true);
      } else {
        // Swipe left - hide menu
        setShowMobileMenu(false);
      }
    }
  };

  // Voice command setup
  const startVoiceCommand = () => {
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SpeechRecognition = (window as any).webkitSpeechRecognition || (window as any).SpeechRecognition;
      const recognition = new SpeechRecognition();

      recognition.onresult = (event: any) => {
        const command = event.results[0][0].transcript.toLowerCase();
        handleVoiceCommand(command);
      };

      recognition.start();
    }
  };

  const handleVoiceCommand = (command: string) => {
    if (command.includes('new order')) {
      router.visit(route('orders.create'));
    } else if (command.includes('show pending')) {
      setSelectedFilter('pending');
    } else if (command.includes('refresh')) {
      router.reload();
    }
  };

  // QR Code Scanner
  const startQRScanner = async () => {
    setIsScanning(true);
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' }
      });
      if (scannerRef.current) {
        scannerRef.current.srcObject = stream;
      }
    } catch (error) {
      console.error('Camera access denied:', error);
      setIsScanning(false);
    }
  };

  const stopQRScanner = () => {
    if (scannerRef.current && scannerRef.current.srcObject) {
      const stream = scannerRef.current.srcObject as MediaStream;
      stream.getTracks().forEach(track => track.stop());
    }
    setIsScanning(false);
  };

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

  // Haptic feedback for mobile
  const triggerHaptic = (type: 'light' | 'medium' | 'heavy' = 'light') => {
    if ('vibrate' in navigator) {
      const patterns = {
        light: [10],
        medium: [20],
        heavy: [30, 10, 30]
      };
      navigator.vibrate(patterns[type]);
    }
  };



  const getPriorityColor = (priority: string) => {
    const colors = theme === 'dark' ? {
      critical: 'text-red-400 bg-red-500/20 border border-red-500/30',
      high: 'text-orange-400 bg-orange-500/20 border border-orange-500/30',
      medium: 'text-yellow-400 bg-yellow-500/20 border border-yellow-500/30',
      low: 'text-green-400 bg-green-500/20 border border-green-500/30'
    } : {
      critical: 'text-red-700 bg-red-50 border border-red-200',
      high: 'text-orange-700 bg-orange-50 border border-orange-200',
      medium: 'text-yellow-700 bg-yellow-50 border border-yellow-200',
      low: 'text-green-700 bg-green-50 border border-green-200'
    };
    return colors[priority] || colors.low;
  };

  return (
    <MainLayout>
      <Head title="Provider Dashboard | MSC Healthcare" />

      <div
        className={`min-h-screen ${theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50'} pb-20 lg:pb-6`}
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
      >
        {/* Mobile-First Header */}
        <div className={`${t.glass.card} ${t.glass.border} p-4 lg:p-6 sticky top-0 z-10`}>
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <button
                onClick={() => setShowMobileMenu(!showMobileMenu)}
                className={`${t.button.ghost} p-2 lg:hidden`}
              >
                <Package className="w-5 h-5" />
              </button>
              <div>
                <h1 className={`text-lg lg:text-2xl font-bold ${t.text.primary}`}>My Orders</h1>
                <p className={`text-xs lg:text-sm ${t.text.secondary} hidden sm:block`}>
                  Quickly create and manage your orders
                </p>
              </div>
            </div>

            <div className="flex items-center space-x-2">
              {/* Refresh button and notifications removed as requested */}
            </div>
          </div>
        </div>

        {/* Mobile Quick Actions */}
        <div className="lg:hidden">
          <div className="flex overflow-x-auto space-x-3 p-4 -mt-2">
            <button
              onClick={() => {
                triggerHaptic('medium');
                router.visit(route('orders.create'));
              }}
              className={`${t.button.primary} px-4 py-2 flex items-center space-x-2 whitespace-nowrap`}
            >
              <Plus className="w-4 h-4" />
              <span>New Order</span>
            </button>
            <button
              onClick={() => {
                triggerHaptic('light');
                startQRScanner();
              }}
              className={`${t.button.secondary} px-4 py-2 flex items-center space-x-2 whitespace-nowrap`}
            >
              <QrCode className="w-4 h-4" />
              <span>Scan QR</span>
            </button>
            <button
              onClick={() => {
                triggerHaptic('light');
                setVoiceEnabled(!voiceEnabled);
                if (!voiceEnabled) startVoiceCommand();
              }}
              className={`${t.button.secondary} px-4 py-2 flex items-center space-x-2 whitespace-nowrap`}
            >
              {voiceEnabled ? <Mic className="w-4 h-4" /> : <MicOff className="w-4 h-4" />}
              <span>Voice</span>
            </button>

          </div>
        </div>

        {/* AI Insights - Mobile Optimized - REMOVED */}
        <div className="p-4 lg:p-6">
          {/* AI Assistant banner removed as requested */}

          {/* Mobile Stats Grid */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <Package className="w-5 h-5 text-blue-500" />
              </div>
              <p className={`text-xl font-bold ${t.text.primary}`}>{stats.total_orders}</p>
              <p className={`text-xs ${t.text.secondary}`}>Total Orders</p>
            </div>

            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <Clock className="w-5 h-5 text-yellow-500" />
              </div>
              <p className={`text-xl font-bold ${t.text.primary}`}>{stats.pending_ivr}</p>
              <p className={`text-xs ${t.text.secondary}`}>Pending IVR</p>
            </div>

            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <Activity className="w-5 h-5 text-purple-500" />
              </div>
              <p className={`text-xl font-bold ${t.text.primary}`}>{stats.in_progress}</p>
              <p className={`text-xs ${t.text.secondary}`}>Processing</p>
            </div>

            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <CheckCircle className="w-5 h-5 text-green-500" />
              </div>
              <p className={`text-xl font-bold ${t.text.primary}`}>{stats.completed}</p>
              <p className={`text-xs ${t.text.secondary}`}>Completed</p>
            </div>
          </div>

          {/* Search and Filter - Mobile Optimized */}
          <div className={`${t.glass.card} ${t.glass.border} p-3 mb-4`}>
            <div className="flex flex-col sm:flex-row gap-3">
              <div className="relative flex-1">
                <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${t.text.muted}`} />
                <input
                  type="text"
                  placeholder="Search orders..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className={`w-full pl-10 pr-4 py-2 ${t.input.base} ${t.input.focus} rounded-lg text-sm`}
                />
              </div>
              <select
                value={selectedFilter}
                onChange={(e) => setSelectedFilter(e.target.value)}
                className={`px-4 py-2 ${t.input.select} rounded-lg text-sm`}
              >
                <option value="all">All Orders</option>
                <option value="pending">Pending IVR</option>
                <option value="action_required">Action Required</option>
                <option value="completed">Completed</option>
              </select>
            </div>
          </div>

          {/* Orders List - Mobile Optimized Cards */}
          <div className="space-y-3">
            {filteredOrders.map((order) => (
              <div
                key={order.id}
                onClick={() => {
                  triggerHaptic('light');
                  router.visit(route('orders.show', order.id));
                }}
                className={`${t.glass.card} ${t.glass.border} p-4 cursor-pointer hover:shadow-lg transition-all`}
              >
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <div className="flex items-center space-x-2">
                      <h3 className={`text-sm font-semibold ${t.text.primary}`}>
                        #{order.order_number}
                      </h3>
                      {order.action_required && (
                        <span className="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                      )}
                    </div>
                    <p className={`text-xs ${t.text.secondary} mt-1`}>
                      Patient: {order.patient_display_id}
                    </p>
                  </div>
                  <span className={`text-xs px-2 py-1 rounded-full ${getPriorityColor(order.priority)}`}>
                    {order.priority}
                  </span>
                </div>

                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className={`text-xs ${t.text.secondary}`}>Manufacturer</span>
                    <span className={`text-xs ${t.text.primary}`}>{order.manufacturer.name}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className={`text-xs ${t.text.secondary}`}>Service Date</span>
                    <span className={`text-xs ${t.text.primary}`}>
                      {new Date(order.expected_service_date).toLocaleDateString()}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className={`text-xs ${t.text.secondary}`}>Status</span>
                    <span className={`text-xs font-medium ${
                      order.status === 'completed' ? (theme === 'dark' ? 'text-green-400' : 'text-green-600') :
                      order.status === 'pending_ivr' ? (theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600') :
                      (theme === 'dark' ? 'text-blue-400' : 'text-blue-600')
                    }`}>
                      {order.status.replace(/_/g, ' ')}
                    </span>
                  </div>
                </div>

                {order.products.length > 0 && (
                  <div className={`mt-3 pt-3 border-t ${theme === 'dark' ? 'border-gray-700' : 'border-gray-200'}`}>
                    <p className={`text-xs ${t.text.secondary}`}>
                      {order.products.length} product{order.products.length !== 1 ? 's' : ''}
                    </p>
                  </div>
                )}

                <div className="flex items-center justify-between mt-3">
                  <div className="flex items-center space-x-3">
                    {order.ivr_status === 'completed' && (
                      <FileText className="w-4 h-4 text-green-500" />
                    )}
                    {order.tracking_number && (
                      <Package className="w-4 h-4 text-blue-500" />
                    )}
                  </div>
                  <ChevronRight className={`w-4 h-4 ${t.text.muted}`} />
                </div>
              </div>
            ))}
          </div>

          {filteredOrders.length === 0 && (
            <div className={`${t.glass.card} ${t.glass.border} p-8 text-center`}>
              <Package className={`w-12 h-12 ${t.text.muted} mx-auto mb-4`} />
              <p className={`${t.text.secondary}`}>No orders found</p>
            </div>
          )}
        </div>

        {/* Mobile Bottom Navigation */}
        <div className={`lg:hidden fixed bottom-0 left-0 right-0 ${t.glass.card} border-t ${t.glass.border}`}>
          <div className="flex items-center justify-around py-2">
            <button
              onClick={() => router.visit(route('dashboard'))}
              className={`${t.button.ghost} p-3 flex flex-col items-center`}
            >
              <BarChart3 className="w-5 h-5" />
              <span className="text-xs mt-1">Dashboard</span>
            </button>
            <button
              onClick={() => router.visit(route('orders.index'))}
              className={`${t.button.ghost} p-3 flex flex-col items-center text-blue-500`}
            >
              <Package className="w-5 h-5" />
              <span className="text-xs mt-1">Orders</span>
            </button>
            <button
              onClick={() => {
                triggerHaptic('medium');
                router.visit(route('orders.create'));
              }}
              className={`${t.button.primary} p-3 rounded-full -mt-4`}
            >
              <Plus className="w-6 h-6" />
            </button>
            <button
              onClick={() => router.visit(route('patients.index'))}
              className={`${t.button.ghost} p-3 flex flex-col items-center`}
            >
              <User className="w-5 h-5" />
              <span className="text-xs mt-1">Patients</span>
            </button>
            <button
              onClick={() => router.visit(route('profile'))}
              className={`${t.button.ghost} p-3 flex flex-col items-center`}
            >
              <Shield className="w-5 h-5" />
              <span className="text-xs mt-1">Profile</span>
            </button>
          </div>
        </div>

        {/* QR Scanner Modal */}
        {isScanning && (
          <div className="fixed inset-0 bg-black z-50 flex flex-col">
            <div className="flex items-center justify-between p-4 text-white">
              <h2 className="text-lg font-semibold">Scan Order QR Code</h2>
              <button
                onClick={() => {
                  stopQRScanner();
                  triggerHaptic('light');
                }}
                className="p-2"
              >
                <ChevronLeft className="w-6 h-6" />
              </button>
            </div>
            <div className="flex-1 relative">
              <video
                ref={scannerRef}
                autoPlay
                playsInline
                className="w-full h-full object-cover"
              />
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="w-64 h-64 border-2 border-white rounded-lg">
                  <div className="w-full h-full border-2 border-white rounded-lg animate-pulse"></div>
                </div>
              </div>
            </div>
            <div className="p-4 text-center text-white">
              <p className="text-sm">Position QR code within the frame</p>
            </div>
          </div>
        )}


      </div>
    </MainLayout>
  );
}
