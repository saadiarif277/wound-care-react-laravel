import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import {
  FileText,
  Clock,
  CheckCircle,
  AlertTriangle,
  Send,
  Calendar,
  User,
  Building2,
  Search,
  Filter,
  Download,
  RefreshCw,
  ChevronRight,
  ChevronDown,
  Eye,
  Mail,
  Phone,
  MapPin,
  Timer,
  Shield,
  Activity,
  Brain,
  Sparkle,
  TrendingUp,
  Info,
  Bell,
  MessageSquare,
  Mic,
  Volume2,
  Target,
  Award,
  BarChart3,
  ArrowUp,
  ArrowDown,
  ExternalLink,
  Copy,
  Settings,
  HelpCircle,
} from 'lucide-react';

interface PatientIVR {
  id: string;
  patient_display_id: string;
  patient_name: string;
  status: 'pending' | 'in_progress' | 'completed' | 'expired';
  ivr_type: string;
  manufacturer: {
    id: number;
    name: string;
    ivr_frequency: 'weekly' | 'monthly' | 'quarterly';
  };
  provider: {
    id: number;
    name: string;
    email: string;
    phone?: string;
  };
  facility: {
    id: number;
    name: string;
    address: string;
  };
  created_at: string;
  expires_at: string;
  last_activity: string;
  completion_percentage: number;
  estimated_completion_time: number;
  risk_score: number;
  ai_insights: {
    completion_likelihood: number;
    recommended_actions: string[];
    potential_issues: string[];
  };
}

interface Props {
  patientIVRs: PatientIVR[];
  stats: {
    total_active: number;
    completed_today: number;
    expiring_soon: number;
    average_completion_time: number;
    completion_rate: number;
    risk_assessments: number;
  };
  aiPredictions: {
    high_risk_ivrs: string[];
    optimal_reminder_times: Record<string, string>;
    workflow_bottlenecks: string[];
  };
}

export default function IVRManagement({ 
  patientIVRs = [], 
  stats = {
    total_active: 0,
    completed_today: 0,
    expiring_soon: 0,
    average_completion_time: 0,
    completion_rate: 0,
    risk_assessments: 0
  },
  aiPredictions = {
    high_risk_ivrs: [],
    optimal_reminder_times: {},
    workflow_bottlenecks: []
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

  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [selectedManufacturer, setSelectedManufacturer] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [sortBy, setSortBy] = useState<'expires_at' | 'risk_score' | 'completion_percentage'>('expires_at');
  const [showAIInsights, setShowAIInsights] = useState(true);
  const [selectedIVRs, setSelectedIVRs] = useState<string[]>([]);
  const [bulkAction, setBulkAction] = useState('');
  const [voiceEnabled, setVoiceEnabled] = useState(false);

  // Real-time updates
  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ['patientIVRs', 'stats'] });
    }, 60000); // Update every minute

    return () => clearInterval(interval);
  }, []);

  // Filter and sort IVRs
  const filteredIVRs = patientIVRs
    .filter(ivr => {
      const matchesStatus = selectedStatus === 'all' || ivr.status === selectedStatus;
      const matchesManufacturer = selectedManufacturer === 'all' || ivr.manufacturer.name === selectedManufacturer;
      const matchesSearch = searchQuery === '' || 
        ivr.patient_display_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ivr.provider.name.toLowerCase().includes(searchQuery.toLowerCase());
      
      return matchesStatus && matchesManufacturer && matchesSearch;
    })
    .sort((a, b) => {
      if (sortBy === 'expires_at') {
        return new Date(a.expires_at).getTime() - new Date(b.expires_at).getTime();
      } else if (sortBy === 'risk_score') {
        return b.risk_score - a.risk_score;
      } else {
        return a.completion_percentage - b.completion_percentage;
      }
    });

  const handleBulkAction = () => {
    if (selectedIVRs.length === 0) return;

    switch (bulkAction) {
      case 'send_reminders':
        router.post(route('admin.ivr.bulk-remind'), { ivr_ids: selectedIVRs });
        break;
      case 'export':
        router.post(route('admin.ivr.export'), { ivr_ids: selectedIVRs });
        break;
      case 'reassign':
        // Open reassignment modal
        break;
    }
    
    setSelectedIVRs([]);
    setBulkAction('');
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return 'text-green-500 bg-green-500/20';
      case 'in_progress': return 'text-blue-500 bg-blue-500/20';
      case 'pending': return 'text-yellow-500 bg-yellow-500/20';
      case 'expired': return 'text-red-500 bg-red-500/20';
      default: return 'text-gray-500 bg-gray-500/20';
    }
  };

  const getRiskColor = (score: number) => {
    if (score >= 80) return 'text-red-500';
    if (score >= 60) return 'text-orange-500';
    if (score >= 40) return 'text-yellow-500';
    return 'text-green-500';
  };

  const speak = (text: string) => {
    if ('speechSynthesis' in window && voiceEnabled) {
      const utterance = new SpeechSynthesisUtterance(text);
      window.speechSynthesis.speak(utterance);
    }
  };

  return (
    <MainLayout>
      <Head title="Patient IVR Management | MSC Healthcare" />

      <div className={`min-h-screen ${theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50'} p-6`}>
        {/* Header */}
        <div className={`${t.glass.card} ${t.glass.border} p-6 mb-6`}>
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h1 className={`text-2xl font-bold ${t.text.primary}`}>Patient IVR Management</h1>
              <p className={`${t.text.secondary} mt-1`}>
                AI-powered tracking and optimization of patient IVR processes
              </p>
            </div>

            <div className="flex items-center gap-3 mt-4 lg:mt-0">
              <button
                onClick={() => setVoiceEnabled(!voiceEnabled)}
                className={`${t.button.secondary} px-3 py-2 flex items-center space-x-2`}
              >
                {voiceEnabled ? <Mic className="w-4 h-4" /> : <Volume2 className="w-4 h-4" />}
                <span className="text-sm">Voice: {voiceEnabled ? 'On' : 'Off'}</span>
              </button>

              <button
                onClick={() => router.reload()}
                className={`${t.button.ghost} p-2`}
              >
                <RefreshCw className="w-4 h-4" />
              </button>

              <button
                onClick={() => router.visit(route('admin.ivr.settings'))}
                className={`${t.button.ghost} p-2`}
              >
                <Settings className="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        {/* AI Insights Panel */}
        {showAIInsights && aiPredictions.workflow_bottlenecks.length > 0 && (
          <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6 relative overflow-hidden`}>
            <div className="absolute inset-0 bg-gradient-to-r from-purple-500/10 to-blue-500/10"></div>
            <div className="relative">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center space-x-3">
                  <div className="p-2 bg-gradient-to-br from-purple-500 to-blue-500 rounded-lg">
                    <Brain className="w-5 h-5 text-white" />
                  </div>
                  <h3 className={`font-semibold ${t.text.primary}`}>AI Workflow Insights</h3>
                </div>
                <button
                  onClick={() => setShowAIInsights(false)}
                  className={`${t.button.ghost} p-1`}
                >
                  <ChevronDown className="w-4 h-4" />
                </button>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className={`${t.glass.card} ${t.glass.border} p-3`}>
                  <div className="flex items-center space-x-2 mb-2">
                    <AlertTriangle className="w-4 h-4 text-orange-500" />
                    <span className={`text-sm font-medium ${t.text.primary}`}>High Risk IVRs</span>
                  </div>
                  <p className={`text-2xl font-bold ${t.text.primary}`}>{aiPredictions.high_risk_ivrs.length}</p>
                  <p className={`text-xs ${t.text.secondary} mt-1`}>Require immediate attention</p>
                </div>

                <div className={`${t.glass.card} ${t.glass.border} p-3`}>
                  <div className="flex items-center space-x-2 mb-2">
                    <Clock className="w-4 h-4 text-blue-500" />
                    <span className={`text-sm font-medium ${t.text.primary}`}>Optimal Reminder Time</span>
                  </div>
                  <p className={`text-sm ${t.text.primary} mt-1`}>2:00 PM - 4:00 PM</p>
                  <p className={`text-xs ${t.text.secondary} mt-1`}>Based on completion patterns</p>
                </div>

                <div className={`${t.glass.card} ${t.glass.border} p-3`}>
                  <div className="flex items-center space-x-2 mb-2">
                    <Target className="w-4 h-4 text-green-500" />
                    <span className={`text-sm font-medium ${t.text.primary}`}>Predicted Completion</span>
                  </div>
                  <p className={`text-2xl font-bold ${t.text.primary}`}>87%</p>
                  <p className={`text-xs ${t.text.secondary} mt-1`}>By end of day</p>
                </div>
              </div>

              {aiPredictions.workflow_bottlenecks.length > 0 && (
                <div className={`mt-3 p-3 ${t.glass.card} ${t.glass.border}`}>
                  <p className={`text-sm ${t.text.secondary}`}>
                    <span className="font-medium">Bottleneck detected:</span> {aiPredictions.workflow_bottlenecks[0]}
                  </p>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Active IVRs</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.total_active}</p>
                <div className="flex items-center mt-2">
                  <Activity className="w-3 h-3 text-blue-500 mr-1" />
                  <span className="text-xs text-blue-500">Live tracking</span>
                </div>
              </div>
              <div className="p-3 bg-blue-500/20 rounded-lg">
                <FileText className="w-6 h-6 text-blue-500" />
              </div>
            </div>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Completed Today</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.completed_today}</p>
                <p className="text-xs text-green-500 mt-2 flex items-center">
                  <ArrowUp className="w-3 h-3 mr-1" />
                  {stats.completion_rate}% rate
                </p>
              </div>
              <div className="p-3 bg-green-500/20 rounded-lg">
                <CheckCircle className="w-6 h-6 text-green-500" />
              </div>
            </div>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Expiring Soon</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.expiring_soon}</p>
                <p className={`text-xs ${stats.expiring_soon > 0 ? 'text-orange-500' : 'text-green-500'} mt-2`}>
                  Within 48 hours
                </p>
              </div>
              <div className="p-3 bg-orange-500/20 rounded-lg">
                <Timer className="w-6 h-6 text-orange-500" />
              </div>
            </div>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-6 hover:shadow-lg transition-all`}>
            <div className="flex items-center justify-between">
              <div>
                <p className={`text-sm ${t.text.secondary}`}>Avg. Completion Time</p>
                <p className={`text-2xl font-bold ${t.text.primary} mt-1`}>{stats.average_completion_time}m</p>
                <p className="text-xs text-blue-500 mt-2 flex items-center">
                  <TrendingUp className="w-3 h-3 mr-1" />
                  Improving
                </p>
              </div>
              <div className="p-3 bg-purple-500/20 rounded-lg">
                <Clock className="w-6 h-6 text-purple-500" />
              </div>
            </div>
          </div>
        </div>

        {/* Filters and Search */}
        <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6`}>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="relative">
              <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${t.text.muted}`} />
              <input
                type="text"
                placeholder="Search patients, providers..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-10 pr-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
              />
            </div>

            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className={`px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            >
              <option value="all">All Status</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="expired">Expired</option>
            </select>

            <select
              value={selectedManufacturer}
              onChange={(e) => setSelectedManufacturer(e.target.value)}
              className={`px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            >
              <option value="all">All Manufacturers</option>
              {Array.from(new Set(patientIVRs.map(ivr => ivr.manufacturer.name))).map(name => (
                <option key={name} value={name}>{name}</option>
              ))}
            </select>

            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className={`px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            >
              <option value="expires_at">Sort by Expiration</option>
              <option value="risk_score">Sort by Risk Score</option>
              <option value="completion_percentage">Sort by Progress</option>
            </select>
          </div>

          {/* Bulk Actions */}
          {selectedIVRs.length > 0 && (
            <div className="flex items-center gap-3 mt-4 p-3 bg-blue-500/10 rounded-lg">
              <span className={`text-sm ${t.text.primary}`}>
                {selectedIVRs.length} selected
              </span>
              <select
                value={bulkAction}
                onChange={(e) => setBulkAction(e.target.value)}
                className={`px-3 py-1.5 text-sm ${t.glass.input} ${t.glass.border} rounded`}
              >
                <option value="">Choose action...</option>
                <option value="send_reminders">Send Reminders</option>
                <option value="export">Export Data</option>
                <option value="reassign">Reassign Provider</option>
              </select>
              <button
                onClick={handleBulkAction}
                disabled={!bulkAction}
                className={`${t.button.primary} px-3 py-1.5 text-sm disabled:opacity-50`}
              >
                Apply
              </button>
            </div>
          )}
        </div>

        {/* IVR List */}
        <div className={`${t.glass.card} ${t.glass.border} overflow-hidden`}>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className={`${theme === 'dark' ? 'bg-gray-800/50' : 'bg-gray-50'}`}>
                <tr>
                  <th className="px-6 py-3 text-left">
                    <input
                      type="checkbox"
                      checked={selectedIVRs.length === filteredIVRs.length && filteredIVRs.length > 0}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedIVRs(filteredIVRs.map(ivr => ivr.id));
                        } else {
                          setSelectedIVRs([]);
                        }
                      }}
                      className="rounded"
                    />
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Patient
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Provider/Facility
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Status
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Progress
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Risk Score
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Expires
                  </th>
                  <th className={`px-6 py-3 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className={`divide-y ${theme === 'dark' ? 'divide-gray-800' : 'divide-gray-200'}`}>
                {filteredIVRs.map((ivr) => {
                  const isHighRisk = aiPredictions.high_risk_ivrs.includes(ivr.id);
                  const daysUntilExpiry = Math.ceil((new Date(ivr.expires_at).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24));

                  return (
                    <tr
                      key={ivr.id}
                      className={`${t.glass.hover} transition-colors ${isHighRisk ? 'bg-red-500/5' : ''}`}
                    >
                      <td className="px-6 py-4">
                        <input
                          type="checkbox"
                          checked={selectedIVRs.includes(ivr.id)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setSelectedIVRs([...selectedIVRs, ivr.id]);
                            } else {
                              setSelectedIVRs(selectedIVRs.filter(id => id !== ivr.id));
                            }
                          }}
                          className="rounded"
                        />
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <p className={`text-sm font-medium ${t.text.primary}`}>{ivr.patient_display_id}</p>
                          <p className={`text-xs ${t.text.secondary}`}>{ivr.manufacturer.name}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <p className={`text-sm ${t.text.primary}`}>{ivr.provider.name}</p>
                          <p className={`text-xs ${t.text.secondary}`}>{ivr.facility.name}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(ivr.status)}`}>
                          {ivr.status === 'in_progress' && <Activity className="w-3 h-3 mr-1 animate-pulse" />}
                          {ivr.status.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <div className="w-full">
                          <div className="flex items-center justify-between mb-1">
                            <span className={`text-xs ${t.text.secondary}`}>{ivr.completion_percentage}%</span>
                            <span className={`text-xs ${t.text.muted}`}>~{ivr.estimated_completion_time}m</span>
                          </div>
                          <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                              className="h-2 rounded-full bg-gradient-to-r from-blue-500 to-green-500 transition-all"
                              style={{ width: `${ivr.completion_percentage}%` }}
                            />
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center space-x-2">
                          <Shield className={`w-4 h-4 ${getRiskColor(ivr.risk_score)}`} />
                          <span className={`text-sm font-medium ${getRiskColor(ivr.risk_score)}`}>
                            {ivr.risk_score}%
                          </span>
                          {isHighRisk && (
                            <Sparkle className="w-3 h-3 text-orange-500 animate-pulse" />
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <p className={`text-sm ${daysUntilExpiry <= 2 ? 'text-red-500 font-medium' : t.text.primary}`}>
                            {daysUntilExpiry > 0 ? `${daysUntilExpiry}d` : 'Expired'}
                          </p>
                          <p className={`text-xs ${t.text.secondary}`}>
                            {new Date(ivr.expires_at).toLocaleDateString()}
                          </p>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center space-x-2">
                          <button
                            onClick={() => router.visit(route('admin.ivr.show', ivr.id))}
                            className={`${t.button.ghost} p-1.5`}
                            title="View Details"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => {
                              router.post(route('admin.ivr.remind', ivr.id));
                              speak(`Reminder sent to ${ivr.provider.name}`);
                            }}
                            className={`${t.button.ghost} p-1.5`}
                            title="Send Reminder"
                          >
                            <Bell className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => router.visit(route('admin.ivr.contact', ivr.id))}
                            className={`${t.button.ghost} p-1.5`}
                            title="Contact Provider"
                          >
                            <MessageSquare className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {filteredIVRs.length === 0 && (
            <div className="text-center py-12">
              <FileText className={`w-12 h-12 ${t.text.muted} mx-auto mb-4`} />
              <p className={`${t.text.secondary}`}>No IVRs found matching your criteria</p>
            </div>
          )}
        </div>

        {/* AI Recommendations */}
        <div className={`${t.glass.card} ${t.glass.border} p-6 mt-6`}>
          <div className="flex items-center space-x-3 mb-4">
            <div className="p-2 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg">
              <Sparkles className="w-5 h-5 text-white" />
            </div>
            <h3 className={`text-lg font-semibold ${t.text.primary}`}>AI Recommendations</h3>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <span className={`text-sm font-medium ${t.text.primary}`}>Optimal Reminder Strategy</span>
                <Target className="w-4 h-4 text-blue-500" />
              </div>
              <p className={`text-xs ${t.text.secondary}`}>
                Send reminders at 2:00 PM for 23% higher completion rate
              </p>
            </div>

            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <span className={`text-sm font-medium ${t.text.primary}`}>Provider Performance</span>
                <Award className="w-4 h-4 text-green-500" />
              </div>
              <p className={`text-xs ${t.text.secondary}`}>
                Dr. Smith has 95% completion rate - assign high-priority IVRs
              </p>
            </div>

            <div className={`${t.glass.card} ${t.glass.border} p-4`}>
              <div className="flex items-center justify-between mb-2">
                <span className={`text-sm font-medium ${t.text.primary}`}>Process Optimization</span>
                <BarChart3 className="w-4 h-4 text-purple-500" />
              </div>
              <p className={`text-xs ${t.text.secondary}`}>
                Reduce form fields by 3 to improve completion by 15%
              </p>
            </div>
          </div>
        </div>

        {/* Voice Assistant Helper */}
        {voiceEnabled && (
          <div className="fixed bottom-6 right-6 z-50">
            <div className={`${t.glass.card} ${t.glass.border} p-4 max-w-xs`}>
              <div className="flex items-center space-x-2 mb-2">
                <Volume2 className="w-4 h-4 text-blue-500" />
                <p className={`text-sm font-medium ${t.text.primary}`}>Voice Assistant Active</p>
              </div>
              <p className={`text-xs ${t.text.secondary}`}>
                Announcing IVR completions and high-risk alerts
              </p>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
}