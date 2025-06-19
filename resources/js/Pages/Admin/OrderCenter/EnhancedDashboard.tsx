import React, { useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import EpisodeCard from '@/Components/Episodes/EpisodeCard';
import {
  Package,
  TrendingUp,
  Users,
  DollarSign,
  Activity,
  Clock,
  ArrowUp,
  ArrowDown,
  Calendar,
  Filter,
  Download,
  RefreshCw,
  Bell,
  Search,
  ChevronRight,
  FileText,
  CheckCircle,
  AlertTriangle,
  Send,
  Eye,
  Truck,
  Brain,
  Sparkle,
  Mic,
  MicOff,
  Volume2,
  BarChart3,
  Layers,
  Shield,
  Target,
  Zap,
  Info,
  Settings,
  ChevronDown,
  Timer,
  Heart,
  Star,
  MessageSquare,
  Sparkles,
  Wind,
  PlayCircle,
  PauseCircle,
  SkipForward,
} from 'lucide-react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line, Bar, Doughnut } from 'react-chartjs-2';

// Register ChartJS components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

interface Episode {
  id: string;
  patient_id: string;
  patient_display_id: string;
  status: string;
  ivr_status: string;
  manufacturer: {
    id: number;
    name: string;
  };
  orders: Array<{
    id: string;
    order_number: string;
    status: string;
    provider: {
      name: string;
    };
  }>;
  total_order_value: number;
  action_required: boolean;
  created_at: string;
  updated_at: string;
}

interface DashboardStats {
  total_episodes: number;
  pending_review: number;
  ivr_expiring_soon: number;
  total_value: number;
  episodes_this_week: number;
  completion_rate: number;
}

interface AIInsight {
  id: string;
  type: 'warning' | 'info' | 'success' | 'critical';
  title: string;
  description: string;
  action?: {
    label: string;
    route: string;
  };
  confidence: number;
}

interface Props {
  episodes: Episode[];
  stats: DashboardStats;
  aiInsights: AIInsight[];
  recentActivity: Array<{
    id: string;
    type: string;
    description: string;
    timestamp: string;
    user?: string;
  }>;
  performanceData: {
    labels: string[];
    episodesCompleted: number[];
    averageProcessingTime: number[];
  };
}

export default function EnhancedDashboard({ 
  episodes = [], 
  stats = {
    total_episodes: 0,
    pending_review: 0,
    ivr_expiring_soon: 0,
    total_value: 0,
    episodes_this_week: 0,
    completion_rate: 0
  },
  aiInsights = [],
  recentActivity = [],
  performanceData = {
    labels: [],
    episodesCompleted: [],
    averageProcessingTime: []
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

  const [voiceEnabled, setVoiceEnabled] = useState(false);
  const [showPredictiveAlerts, setShowPredictiveAlerts] = useState(true);
  const [dashboardView, setDashboardView] = useState<'episodes' | 'analytics' | 'workflow'>('episodes');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedManufacturer, setSelectedManufacturer] = useState<string>('all');
  const [isListening, setIsListening] = useState(false);
  const recognitionRef = useRef<any>(null);

  // Initialize voice recognition
  useEffect(() => {
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SpeechRecognition = (window as any).webkitSpeechRecognition || (window as any).SpeechRecognition;
      recognitionRef.current = new SpeechRecognition();
      recognitionRef.current.continuous = false;
      recognitionRef.current.interimResults = false;
      recognitionRef.current.lang = 'en-US';

      recognitionRef.current.onresult = (event: any) => {
        const command = event.results[0][0].transcript.toLowerCase();
        handleVoiceCommand(command);
        setIsListening(false);
      };

      recognitionRef.current.onerror = () => {
        setIsListening(false);
      };
    }
  }, []);

  const handleVoiceCommand = (command: string) => {
    if (command.includes('show episodes') || command.includes('view episodes')) {
      setDashboardView('episodes');
      speak('Showing episodes view');
    } else if (command.includes('review ivr') || command.includes('pending review')) {
      router.visit(route('admin.episodes.pending-review'));
      speak('Opening pending IVR reviews');
    } else if (command.includes('analytics') || command.includes('show analytics')) {
      setDashboardView('analytics');
      speak('Showing analytics dashboard');
    } else if (command.includes('workflow') || command.includes('show workflow')) {
      setDashboardView('workflow');
      speak('Showing workflow view');
    } else if (command.includes('refresh') || command.includes('reload')) {
      router.reload();
      speak('Refreshing dashboard data');
    }
  };

  const speak = (text: string) => {
    if ('speechSynthesis' in window && voiceEnabled) {
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.rate = 1.0;
      utterance.pitch = 1.0;
      window.speechSynthesis.speak(utterance);
    }
  };

  const toggleVoiceRecognition = () => {
    if (!recognitionRef.current) return;

    if (isListening) {
      recognitionRef.current.stop();
      setIsListening(false);
    } else {
      recognitionRef.current.start();
      setIsListening(true);
      speak('Listening for commands');
    }
  };

  // Real-time updates simulation
  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ['recentActivity', 'stats'] });
    }, 30000);

    return () => clearInterval(interval);
  }, []);

  // Filter episodes
  const filteredEpisodes = episodes.filter(episode => {
    const matchesSearch = searchQuery === '' || 
      episode.patient_display_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
      episode.orders.some(order => order.order_number.toLowerCase().includes(searchQuery.toLowerCase()));
    
    const matchesManufacturer = selectedManufacturer === 'all' || 
      episode.manufacturer.name === selectedManufacturer;

    return matchesSearch && matchesManufacturer;
  });

  // Chart configurations
  const performanceChartData = {
    labels: performanceData.labels,
    datasets: [
      {
        label: 'Episodes Completed',
        data: performanceData.episodesCompleted,
        borderColor: theme === 'dark' ? 'rgb(59, 130, 246)' : 'rgb(37, 99, 235)',
        backgroundColor: theme === 'dark' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(37, 99, 235, 0.1)',
        tension: 0.4,
        fill: true,
      },
    ],
  };

  const statusDistributionData = {
    labels: ['Pending Review', 'IVR Sent', 'Verified', 'In Progress', 'Completed'],
    datasets: [
      {
        data: [
          episodes.filter(e => e.status === 'ready_for_review').length,
          episodes.filter(e => e.status === 'ivr_sent').length,
          episodes.filter(e => e.status === 'ivr_verified').length,
          episodes.filter(e => e.status === 'sent_to_manufacturer').length,
          episodes.filter(e => e.status === 'completed').length,
        ],
        backgroundColor: [
          '#F59E0B',
          '#3B82F6',
          '#10B981',
          '#8B5CF6',
          '#6B7280',
        ],
        borderWidth: 0,
      },
    ],
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
    },
    scales: {
      x: {
        grid: {
          display: false,
        },
        ticks: {
          color: theme === 'dark' ? '#9CA3AF' : '#6B7280',
        },
      },
      y: {
        grid: {
          color: theme === 'dark' ? 'rgba(75, 85, 99, 0.2)' : 'rgba(229, 231, 235, 0.5)',
        },
        ticks: {
          color: theme === 'dark' ? '#9CA3AF' : '#6B7280',
        },
      },
    },
  };

  return (
    <MainLayout>
      <Head title="Enhanced Order Center Dashboard | MSC Healthcare" />

      <div className={`min-h-screen ${theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50'} p-6`}>
        {/* Header with Voice Controls */}
        <div className={`${t.glass.card} ${t.glass.border} p-6 mb-6`}>
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div className="mb-4 lg:mb-0">
              <h1 className={`text-2xl font-bold ${t.text.primary}`}>Order Management Center</h1>
              <p className={`${t.text.secondary} mt-1`}>
                AI-powered episode tracking and workflow automation
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              {/* Voice Control */}
              <button
                onClick={() => setVoiceEnabled(!voiceEnabled)}
                className={`${t.button.secondary} px-4 py-2 flex items-center space-x-2`}
              >
                {voiceEnabled ? <Mic className="w-4 h-4" /> : <MicOff className="w-4 h-4" />}
                <span className="text-sm">Voice: {voiceEnabled ? 'On' : 'Off'}</span>
              </button>

              {voiceEnabled && (
                <button
                  onClick={toggleVoiceRecognition}
                  className={`${isListening ? t.button.danger : t.button.primary} px-4 py-2 flex items-center space-x-2`}
                >
                  {isListening ? <PauseCircle className="w-4 h-4" /> : <PlayCircle className="w-4 h-4" />}
                  <span className="text-sm">{isListening ? 'Listening...' : 'Start Voice'}</span>
                </button>
              )}

              {/* Dashboard View Switcher */}
              <div className={`${t.glass.card} ${t.glass.border} p-1 flex`}>
                <button
                  onClick={() => setDashboardView('episodes')}
                  className={`px-3 py-1.5 text-sm rounded transition-colors ${
                    dashboardView === 'episodes' 
                      ? `${t.button.primary}` 
                      : `${t.button.ghost}`
                  }`}
                >
                  Episodes
                </button>
                <button
                  onClick={() => setDashboardView('analytics')}
                  className={`px-3 py-1.5 text-sm rounded transition-colors ${
                    dashboardView === 'analytics' 
                      ? `${t.button.primary}` 
                      : `${t.button.ghost}`
                  }`}
                >
                  Analytics
                </button>
                <button
                  onClick={() => setDashboardView('workflow')}
                  className={`px-3 py-1.5 text-sm rounded transition-colors ${
                    dashboardView === 'workflow' 
                      ? `${t.button.primary}` 
                      : `${t.button.ghost}`
                  }`}
                >
                  Workflow
                </button>
              </div>

              <button
                onClick={() => router.reload()}
                className={`${t.button.ghost} p-2`}
              >
                <RefreshCw className="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        {/* AI Insights Banner */}
        {showPredictiveAlerts && aiInsights.length > 0 && (
          <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6 relative overflow-hidden`}>
            <div className="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10 animate-pulse"></div>
            <div className="relative">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center space-x-3">
                  <div className="p-2 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg">
                    <Brain className="w-5 h-5 text-white" />
                  </div>
                  <h3 className={`font-semibold ${t.text.primary}`}>AI Predictive Insights</h3>
                </div>
                <button
                  onClick={() => setShowPredictiveAlerts(false)}
                  className={`${t.button.ghost} p-1`}
                >
                  <ChevronDown className="w-4 h-4" />
                </button>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {aiInsights.slice(0, 3).map((insight) => (
                  <div
                    key={insight.id}
                    className={`${t.glass.card} ${t.glass.border} p-3 hover:shadow-lg transition-all cursor-pointer`}
                    onClick={() => insight.action && router.visit(insight.action.route)}
                  >
                    <div className="flex items-start space-x-2">
                      <div className={`p-1.5 rounded ${
                        insight.type === 'critical' ? 'bg-red-500/20 text-red-500' :
                        insight.type === 'warning' ? 'bg-yellow-500/20 text-yellow-500' :
                        insight.type === 'success' ? 'bg-green-500/20 text-green-500' :
                        'bg-blue-500/20 text-blue-500'
                      }`}>
                        {insight.type === 'critical' ? <AlertTriangle className="w-4 h-4" /> :
                         insight.type === 'warning' ? <AlertTriangle className="w-4 h-4" /> :
                         insight.type === 'success' ? <CheckCircle className="w-4 h-4" /> :
                         <Info className="w-4 h-4" />}
                      </div>
                      <div className="flex-1">
                        <h4 className={`text-sm font-medium ${t.text.primary}`}>{insight.title}</h4>
                        <p className={`text-xs ${t.text.secondary} mt-1`}>{insight.description}</p>
                        {insight.action && (
                          <span className="text-xs text-blue-500 hover:text-blue-400 mt-2 inline-flex items-center">
                            {insight.action.label} <ChevronRight className="w-3 h-3 ml-1" />
                          </span>
                        )}
                      </div>
                      <span className={`text-xs ${t.text.muted}`}>
                        {Math.round(insight.confidence * 100)}%
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Total Episodes</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.total_episodes}</p>
                <p className="text-xs text-green-500 mt-2 flex items-center">
                  <ArrowUp className="w-3 h-3 mr-1" />
                  {stats.episodes_this_week} this week
                </p>
              </div>
              <div className="p-3 bg-blue-500/20 rounded-lg">
                <Layers className="w-6 h-6 text-blue-500" />
              </div>
            </div>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Pending Review</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.pending_review}</p>
                <p className={`text-xs ${stats.pending_review > 0 ? 'text-yellow-500' : 'text-green-500'} mt-2`}>
                  {stats.pending_review > 0 ? 'Requires attention' : 'All reviewed'}
                </p>
              </div>
              <div className="p-3 bg-yellow-500/20 rounded-lg">
                <Clock className="w-6 h-6 text-yellow-500" />
              </div>
            </div>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>IVR Expiring Soon</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.ivr_expiring_soon}</p>
                <p className={`text-xs ${stats.ivr_expiring_soon > 0 ? 'text-orange-500' : 'text-green-500'} mt-2`}>
                  {stats.ivr_expiring_soon > 0 ? 'Within 30 days' : 'None expiring'}
                </p>
              </div>
              <div className="p-3 bg-orange-500/20 rounded-lg">
                <AlertTriangle className="w-6 h-6 text-orange-500" />
              </div>
            </div>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Total Value</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>
                  ${(stats.total_value || 0).toLocaleString()}
                </p>
                <p className="text-xs text-green-500 mt-2">
                  {stats.completion_rate}% completion rate
                </p>
              </div>
              <div className="p-3 bg-green-500/20 rounded-lg">
                <DollarSign className="w-6 h-6 text-green-500" />
              </div>
            </div>
          </div>
        </div>

        {/* Main Content Based on View */}
        {dashboardView === 'episodes' && (
          <>
            {/* Search and Filters */}
            <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6`}>
              <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="relative flex-1 max-w-md">
                  <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${t.text.muted}`} />
                  <input
                    type="text"
                    placeholder="Search episodes, orders, or patient IDs..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className={`w-full pl-10 pr-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
                  />
                </div>

                <div className="flex items-center gap-3">
                  <select
                    value={selectedManufacturer}
                    onChange={(e) => setSelectedManufacturer(e.target.value)}
                    className={`px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
                  >
                    <option value="all">All Manufacturers</option>
                    {Array.from(new Set(episodes.map(e => e.manufacturer.name))).map(name => (
                      <option key={name} value={name}>{name}</option>
                    ))}
                  </select>

                  <button className={`${t.button.secondary} px-4 py-2 flex items-center space-x-2`}>
                    <Filter className="w-4 h-4" />
                    <span>More Filters</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Episodes Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
              {filteredEpisodes.map((episode) => (
                <EpisodeCard
                  key={episode.id}
                  episode={episode}
                  onRefresh={() => router.reload()}
                  viewMode="compact"
                />
              ))}
            </div>

            {filteredEpisodes.length === 0 && (
              <div className={`${t.glass.card} ${t.glass.border} p-12 text-center`}>
                <Package className={`w-12 h-12 ${t.text.muted} mx-auto mb-4`} />
                <p className={`${t.text.secondary}`}>No episodes found matching your criteria</p>
              </div>
            )}
          </>
        )}

        {dashboardView === 'analytics' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Performance Chart */}
            <div className={`${t.glass.card} ${t.glass.border} p-6`}>
              <h3 className={`text-lg font-semibold ${t.text.primary} mb-4`}>Weekly Performance</h3>
              <div className="h-64">
                <Line data={performanceChartData} options={chartOptions} />
              </div>
            </div>

            {/* Status Distribution */}
            <div className={`${t.glass.card} ${t.glass.border} p-6`}>
              <h3 className={`text-lg font-semibold ${t.text.primary} mb-4`}>Episode Status Distribution</h3>
              <div className="h-64">
                <Doughnut data={statusDistributionData} options={{
                  ...chartOptions,
                  plugins: {
                    legend: {
                      display: true,
                      position: 'bottom' as const,
                      labels: {
                        color: theme === 'dark' ? '#9CA3AF' : '#6B7280',
                      },
                    },
                  },
                }} />
              </div>
            </div>

            {/* Recent Activity */}
            <div className={`${t.glass.card} ${t.glass.border} p-6 lg:col-span-2`}>
              <h3 className={`text-lg font-semibold ${t.text.primary} mb-4`}>Recent Activity</h3>
              <div className="space-y-3 max-h-96 overflow-y-auto">
                {recentActivity.map((activity) => (
                  <div key={activity.id} className={`flex items-start space-x-3 p-3 ${t.glass.hover} rounded-lg`}>
                    <div className="p-2 bg-blue-500/20 rounded-lg">
                      <Activity className="w-4 h-4 text-blue-500" />
                    </div>
                    <div className="flex-1">
                      <p className={`text-sm ${t.text.primary}`}>{activity.description}</p>
                      <p className={`text-xs ${t.text.muted} mt-1`}>
                        {activity.user} • {new Date(activity.timestamp).toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {dashboardView === 'workflow' && (
          <div className={`${t.glass.card} ${t.glass.border} p-8`}>
            <h3 className={`text-lg font-semibold ${t.text.primary} mb-6 text-center`}>Episode Workflow Overview</h3>
            
            {/* Workflow Visualization */}
            <div className="flex items-center justify-between max-w-4xl mx-auto">
              {[
                { label: 'Provider Submits', icon: FileText, count: episodes.filter(e => e.status === 'ready_for_review').length },
                { label: 'Admin Reviews', icon: Eye, count: stats.pending_review },
                { label: 'IVR Sent', icon: Send, count: episodes.filter(e => e.status === 'ivr_sent').length },
                { label: 'Manufacturer Process', icon: Package, count: episodes.filter(e => e.status === 'sent_to_manufacturer').length },
                { label: 'Shipped', icon: Truck, count: episodes.filter(e => e.status === 'tracking_added').length },
                { label: 'Completed', icon: CheckCircle, count: episodes.filter(e => e.status === 'completed').length },
              ].map((step, index) => (
                <React.Fragment key={step.label}>
                  <div className="flex flex-col items-center">
                    <div className={`p-4 rounded-full ${
                      step.count > 0 ? 'bg-blue-500/20' : 'bg-gray-500/20'
                    }`}>
                      <step.icon className={`w-6 h-6 ${
                        step.count > 0 ? 'text-blue-500' : 'text-gray-500'
                      }`} />
                    </div>
                    <p className={`text-sm font-medium ${t.text.primary} mt-2`}>{step.label}</p>
                    <p className={`text-xs ${t.text.secondary} mt-1`}>{step.count} episodes</p>
                  </div>
                  {index < 5 && (
                    <ChevronRight className={`w-6 h-6 ${t.text.muted}`} />
                  )}
                </React.Fragment>
              ))}
            </div>

            {/* Quick Actions */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
              <button
                onClick={() => router.visit(route('admin.episodes.pending-review'))}
                className={`${t.button.secondary} p-4 flex flex-col items-center space-y-2`}
              >
                <Eye className="w-6 h-6" />
                <span>Review Pending IVRs</span>
              </button>
              <button
                onClick={() => router.visit(route('admin.episodes.expiring'))}
                className={`${t.button.secondary} p-4 flex flex-col items-center space-y-2`}
              >
                <Timer className="w-6 h-6" />
                <span>Expiring IVRs</span>
              </button>
              <button
                onClick={() => router.visit(route('admin.episodes.tracking'))}
                className={`${t.button.secondary} p-4 flex flex-col items-center space-y-2`}
              >
                <Truck className="w-6 h-6" />
                <span>Update Tracking</span>
              </button>
            </div>
          </div>
        )}

        {/* Voice Command Helper */}
        {voiceEnabled && (
          <div className="fixed bottom-6 right-6 z-50">
            <div className={`${t.glass.card} ${t.glass.border} p-4 max-w-xs`}>
              <div className="flex items-center space-x-2 mb-2">
                <Volume2 className="w-4 h-4 text-blue-500" />
                <p className={`text-sm font-medium ${t.text.primary}`}>Voice Commands</p>
              </div>
              <ul className={`text-xs ${t.text.secondary} space-y-1`}>
                <li>• "Show episodes" - View episodes</li>
                <li>• "Review IVR" - Open pending reviews</li>
                <li>• "Show analytics" - View analytics</li>
                <li>• "Show workflow" - View workflow</li>
                <li>• "Refresh" - Reload data</li>
              </ul>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
}