import React, { useState, useEffect } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { Head, usePage } from '@inertiajs/inertia-react';
import { 
  FiBrain, 
  FiTrendingUp, 
  FiTrendingDown, 
  FiTarget, 
  FiActivity, 
  FiUsers, 
  FiRefreshCw, 
  FiDownload,
  FiAlertTriangle,
  FiCheckCircle,
  FiXCircle,
  FiSettings,
  FiInfo,
  FiDatabase,
  FiClock,
  FiBarChart3,
  FiPieChart
} from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import AdminLayout from '@/layouts/AdminLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, BarChart, Bar, PieChart, Pie, Cell } from 'recharts';

interface MLAnalyticsData {
  system_status: {
    ml_available: boolean;
    models_loaded: number;
    training_data_size: number;
    last_training: string;
    training_status: string;
    error?: string;
  };
  performance_metrics: {
    total_predictions: number;
    successful_predictions: number;
    average_confidence: number;
    accuracy_rate: number;
    improvement_rate: number;
  };
  manufacturer_breakdown: Array<{
    manufacturer: string;
    predictions: number;
    accuracy: number;
    avg_confidence: number;
  }>;
  confidence_distribution: Array<{
    range: string;
    count: number;
    percentage: number;
  }>;
  recent_activity: Array<{
    timestamp: string;
    event: string;
    manufacturer: string;
    success: boolean;
    confidence: number;
  }>;
  time_series_data: Array<{
    date: string;
    predictions: number;
    accuracy: number;
    confidence: number;
  }>;
  field_mapping_stats: {
    most_mapped_fields: Array<{
      field: string;
      count: number;
      success_rate: number;
    }>;
    problematic_fields: Array<{
      field: string;
      failures: number;
      success_rate: number;
    }>;
  };
  training_insights: {
    data_sources: Array<{
      source: string;
      count: number;
      quality_score: number;
    }>;
    model_performance: Array<{
      model: string;
      accuracy: number;
      precision: number;
      recall: number;
    }>;
  };
}

interface Props {
  analyticsData: MLAnalyticsData;
  refreshInterval?: number;
}

const MLAnalyticsDashboard: React.FC<Props> = ({ 
  analyticsData, 
  refreshInterval = 30000 
}) => {
  const { theme } = useTheme();
  const t = themes[theme];
  
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date());
  const [selectedTimeRange, setSelectedTimeRange] = useState<'24h' | '7d' | '30d' | '90d'>('7d');
  const [autoRefresh, setAutoRefresh] = useState(true);

  // Auto-refresh functionality
  useEffect(() => {
    if (!autoRefresh) return;

    const interval = setInterval(() => {
      handleRefresh();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval]);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    try {
      await Inertia.reload({ only: ['analyticsData'] });
      setLastRefresh(new Date());
    } catch (error) {
      console.error('Failed to refresh analytics:', error);
    } finally {
      setIsRefreshing(false);
    }
  };

  const handleTriggerTraining = async () => {
    try {
      await Inertia.post('/admin/ml-analytics/trigger-training');
    } catch (error) {
      console.error('Failed to trigger training:', error);
    }
  };

  const handleExportData = () => {
    const dataStr = JSON.stringify(analyticsData, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    const exportFileDefaultName = `ml-analytics-${new Date().toISOString().split('T')[0]}.json`;
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
      case 'completed':
        return 'text-green-600';
      case 'training':
        return 'text-blue-600';
      case 'error':
        return 'text-red-600';
      default:
        return 'text-gray-600';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'active':
      case 'completed':
        return FiCheckCircle;
      case 'training':
        return FiActivity;
      case 'error':
        return FiXCircle;
      default:
        return FiInfo;
    }
  };

  const CHART_COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'];

  if (!analyticsData.system_status.ml_available) {
    return (
      <AdminLayout>
        <Head title="ML Analytics Dashboard" />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <Card className={cn(t.glass.card, "p-8 text-center")}>
            <FiAlertTriangle className="w-16 h-16 mx-auto mb-4 text-amber-500" />
            <h2 className={cn("text-2xl font-bold mb-2", t.text.primary)}>
              ML System Not Available
            </h2>
            <p className={cn("mb-4", t.text.secondary)}>
              The ML field mapping system is currently unavailable. 
              {analyticsData.system_status.error && (
                <span className="block mt-2 text-sm text-red-600">
                  Error: {analyticsData.system_status.error}
                </span>
              )}
            </p>
            <div className="space-x-4">
              <button
                onClick={handleRefresh}
                disabled={isRefreshing}
                className={cn(
                  "inline-flex items-center px-4 py-2 rounded-md",
                  t.button.primary.base,
                  t.button.primary.hover
                )}
              >
                <FiRefreshCw className={cn("w-4 h-4 mr-2", isRefreshing && "animate-spin")} />
                {isRefreshing ? 'Checking...' : 'Check Again'}
              </button>
            </div>
          </Card>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout>
      <Head title="ML Analytics Dashboard" />
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className={cn("text-3xl font-bold", t.text.primary)}>
                ML Analytics Dashboard
              </h1>
              <p className={cn("mt-2", t.text.secondary)}>
                Monitor and analyze ML field mapping performance
              </p>
            </div>
            
            <div className="flex items-center space-x-4">
              <div className="flex items-center space-x-2">
                <span className={cn("text-sm", t.text.muted)}>
                  Last updated: {lastRefresh.toLocaleTimeString()}
                </span>
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    checked={autoRefresh}
                    onChange={(e) => setAutoRefresh(e.target.checked)}
                    className="mr-2"
                  />
                  <span className={cn("text-sm", t.text.muted)}>Auto-refresh</span>
                </label>
              </div>
              
              <button
                onClick={handleExportData}
                className={cn(
                  "inline-flex items-center px-3 py-2 rounded-md",
                  t.button.ghost.base,
                  t.button.ghost.hover
                )}
              >
                <FiDownload className="w-4 h-4 mr-2" />
                Export
              </button>
              
              <button
                onClick={handleRefresh}
                disabled={isRefreshing}
                className={cn(
                  "inline-flex items-center px-3 py-2 rounded-md",
                  t.button.primary.base,
                  t.button.primary.hover
                )}
              >
                <FiRefreshCw className={cn("w-4 h-4 mr-2", isRefreshing && "animate-spin")} />
                Refresh
              </button>
            </div>
          </div>
        </div>

        {/* System Status */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <Card className={cn(t.glass.card)}>
            <CardContent className="p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiDatabase className={cn("w-8 h-8", t.text.primary)} />
                </div>
                <div className="ml-4">
                  <p className={cn("text-sm font-medium", t.text.secondary)}>
                    Models Loaded
                  </p>
                  <p className={cn("text-2xl font-bold", t.text.primary)}>
                    {analyticsData.system_status.models_loaded}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className={cn(t.glass.card)}>
            <CardContent className="p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiActivity className={cn("w-8 h-8", t.text.primary)} />
                </div>
                <div className="ml-4">
                  <p className={cn("text-sm font-medium", t.text.secondary)}>
                    Training Data Size
                  </p>
                  <p className={cn("text-2xl font-bold", t.text.primary)}>
                    {analyticsData.system_status.training_data_size.toLocaleString()}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className={cn(t.glass.card)}>
            <CardContent className="p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiTarget className={cn("w-8 h-8", t.text.primary)} />
                </div>
                <div className="ml-4">
                  <p className={cn("text-sm font-medium", t.text.secondary)}>
                    Accuracy Rate
                  </p>
                  <p className={cn("text-2xl font-bold", t.text.primary)}>
                    {Math.round(analyticsData.performance_metrics.accuracy_rate * 100)}%
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className={cn(t.glass.card)}>
            <CardContent className="p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiClock className={cn("w-8 h-8", t.text.primary)} />
                </div>
                <div className="ml-4">
                  <p className={cn("text-sm font-medium", t.text.secondary)}>
                    Training Status
                  </p>
                  <div className="flex items-center mt-1">
                    <span className={cn("text-sm font-medium", getStatusColor(analyticsData.system_status.training_status))}>
                      {analyticsData.system_status.training_status}
                    </span>
                    <button
                      onClick={handleTriggerTraining}
                      className={cn(
                        "ml-2 p-1 rounded-md",
                        t.button.ghost.base,
                        t.button.ghost.hover
                      )}
                      title="Trigger Training"
                    >
                      <FiSettings className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Performance Metrics */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <Card className={cn(t.glass.card)}>
            <CardHeader>
              <CardTitle className="flex items-center">
                <FiBarChart3 className="w-5 h-5 mr-2" />
                Performance Trend
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={analyticsData.time_series_data}>
                    <CartesianGrid strokeDasharray="3 3" stroke={theme === 'dark' ? '#374151' : '#E5E7EB'} />
                    <XAxis dataKey="date" stroke={theme === 'dark' ? '#9CA3AF' : '#6B7280'} />
                    <YAxis stroke={theme === 'dark' ? '#9CA3AF' : '#6B7280'} />
                    <Tooltip 
                      contentStyle={{
                        backgroundColor: theme === 'dark' ? '#1F2937' : '#FFFFFF',
                        border: '1px solid ' + (theme === 'dark' ? '#374151' : '#E5E7EB'),
                        borderRadius: '8px'
                      }}
                    />
                    <Legend />
                    <Line 
                      type="monotone" 
                      dataKey="accuracy" 
                      stroke={CHART_COLORS[0]} 
                      strokeWidth={2}
                      name="Accuracy"
                    />
                    <Line 
                      type="monotone" 
                      dataKey="confidence" 
                      stroke={CHART_COLORS[1]} 
                      strokeWidth={2}
                      name="Confidence"
                    />
                  </LineChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>

          <Card className={cn(t.glass.card)}>
            <CardHeader>
              <CardTitle className="flex items-center">
                <FiPieChart className="w-5 h-5 mr-2" />
                Confidence Distribution
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={analyticsData.confidence_distribution}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ range, percentage }) => `${range}: ${percentage}%`}
                      outerRadius={80}
                      fill="#8884d8"
                      dataKey="count"
                    >
                      {analyticsData.confidence_distribution.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Manufacturer Breakdown */}
        <Card className={cn(t.glass.card, "mb-8")}>
          <CardHeader>
            <CardTitle className="flex items-center">
              <FiUsers className="w-5 h-5 mr-2" />
              Manufacturer Performance
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={analyticsData.manufacturer_breakdown}>
                  <CartesianGrid strokeDasharray="3 3" stroke={theme === 'dark' ? '#374151' : '#E5E7EB'} />
                  <XAxis dataKey="manufacturer" stroke={theme === 'dark' ? '#9CA3AF' : '#6B7280'} />
                  <YAxis stroke={theme === 'dark' ? '#9CA3AF' : '#6B7280'} />
                  <Tooltip 
                    contentStyle={{
                      backgroundColor: theme === 'dark' ? '#1F2937' : '#FFFFFF',
                      border: '1px solid ' + (theme === 'dark' ? '#374151' : '#E5E7EB'),
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Bar dataKey="predictions" fill={CHART_COLORS[0]} name="Predictions" />
                  <Bar dataKey="accuracy" fill={CHART_COLORS[1]} name="Accuracy %" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Field Mapping Stats */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <Card className={cn(t.glass.card)}>
            <CardHeader>
              <CardTitle className="flex items-center">
                <FiTrendingUp className="w-5 h-5 mr-2 text-green-600" />
                Most Successful Fields
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {analyticsData.field_mapping_stats.most_mapped_fields.map((field, index) => (
                  <div key={index} className="flex items-center justify-between">
                    <div className="flex-1">
                      <span className={cn("text-sm font-medium", t.text.primary)}>
                        {field.field}
                      </span>
                      <div className="flex items-center mt-1">
                        <div className={cn("w-full bg-gray-200 rounded-full h-2", 
                          theme === 'dark' ? 'bg-gray-700' : 'bg-gray-200'
                        )}>
                          <div 
                            className="bg-green-600 h-2 rounded-full" 
                            style={{ width: `${field.success_rate * 100}%` }}
                          />
                        </div>
                        <span className={cn("text-xs ml-2", t.text.muted)}>
                          {Math.round(field.success_rate * 100)}%
                        </span>
                      </div>
                    </div>
                    <div className="ml-4 text-right">
                      <span className={cn("text-sm font-medium", t.text.secondary)}>
                        {field.count}
                      </span>
                      <span className={cn("text-xs", t.text.muted)}>
                        {' '}predictions
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card className={cn(t.glass.card)}>
            <CardHeader>
              <CardTitle className="flex items-center">
                <FiTrendingDown className="w-5 h-5 mr-2 text-red-600" />
                Problematic Fields
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {analyticsData.field_mapping_stats.problematic_fields.map((field, index) => (
                  <div key={index} className="flex items-center justify-between">
                    <div className="flex-1">
                      <span className={cn("text-sm font-medium", t.text.primary)}>
                        {field.field}
                      </span>
                      <div className="flex items-center mt-1">
                        <div className={cn("w-full bg-gray-200 rounded-full h-2", 
                          theme === 'dark' ? 'bg-gray-700' : 'bg-gray-200'
                        )}>
                          <div 
                            className="bg-red-600 h-2 rounded-full" 
                            style={{ width: `${(1 - field.success_rate) * 100}%` }}
                          />
                        </div>
                        <span className={cn("text-xs ml-2", t.text.muted)}>
                          {Math.round((1 - field.success_rate) * 100)}% failure
                        </span>
                      </div>
                    </div>
                    <div className="ml-4 text-right">
                      <span className={cn("text-sm font-medium", t.text.secondary)}>
                        {field.failures}
                      </span>
                      <span className={cn("text-xs", t.text.muted)}>
                        {' '}failures
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Recent Activity */}
        <Card className={cn(t.glass.card)}>
          <CardHeader>
            <CardTitle className="flex items-center">
              <FiActivity className="w-5 h-5 mr-2" />
              Recent Activity
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {analyticsData.recent_activity.map((activity, index) => (
                <div key={index} className="flex items-center justify-between py-2">
                  <div className="flex items-center">
                    <div className={cn(
                      "w-2 h-2 rounded-full mr-3",
                      activity.success ? 'bg-green-500' : 'bg-red-500'
                    )} />
                    <div>
                      <span className={cn("text-sm", t.text.primary)}>
                        {activity.event}
                      </span>
                      <span className={cn("text-xs ml-2", t.text.muted)}>
                        {activity.manufacturer}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center space-x-4">
                    <span className={cn("text-xs", t.text.muted)}>
                      {Math.round(activity.confidence * 100)}% confidence
                    </span>
                    <span className={cn("text-xs", t.text.muted)}>
                      {new Date(activity.timestamp).toLocaleTimeString()}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
};

export default MLAnalyticsDashboard; 