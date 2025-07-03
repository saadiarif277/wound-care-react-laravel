import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { cn, themes } from '@/theme/glass-theme';
import MetricCard from '@/Components/ui/MetricCard';
import { api } from '@/lib/api';
import {
  FiDollarSign,
  FiClock,
  FiTrendingUp,
  FiUsers,
  FiAlertTriangle,
  FiCalendar,
  FiDownload,
  FiRefreshCw,
  FiEye,
  FiChevronRight
} from 'react-icons/fi';
import {
  CommissionSummary,
  CommissionDetail,
  DelayedPaymentsResponse,
  CommissionAnalytics,
  DashboardFilters
} from '@/types/commission';

interface SalesRepDashboardProps {
  user: any;
  auth: { user: any };
}

export default function SalesRepDashboard({ user, auth }: SalesRepDashboardProps) {
  const { theme } = useTheme();
  const t = themes[theme];

  // State management
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'overview' | 'details' | 'delayed' | 'analytics'>('overview');
  const [commissionSummary, setCommissionSummary] = useState<CommissionSummary | null>(null);
  const [commissionDetails, setCommissionDetails] = useState<CommissionDetail[]>([]);
  const [delayedPayments, setDelayedPayments] = useState<DelayedPaymentsResponse | null>(null);
  const [analytics, setAnalytics] = useState<CommissionAnalytics | null>(null);
  const [filters, setFilters] = useState<DashboardFilters>({
    dateRange: {
      start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      end: new Date().toISOString().split('T')[0]
    },
    statusFilter: []
  });

  // Fetch data
  const fetchData = async () => {
    setLoading(true);
    try {
      const [summaryResponse, detailsResponse, delayedResponse, analyticsResponse] = await Promise.all([
        api.salesReps.getCommissionSummary({
          date_from: filters.dateRange.start,
          date_to: filters.dateRange.end
        }),
        api.salesReps.getCommissionDetails({
          date_from: filters.dateRange.start,
          date_to: filters.dateRange.end,
          per_page: 10
        }),
        api.salesReps.getDelayedPayments({ threshold_days: 60 }),
        api.salesReps.getCommissionAnalytics({
          period: 'monthly',
          date_from: filters.dateRange.start,
          date_to: filters.dateRange.end
        })
      ]);

      setCommissionSummary(summaryResponse);
      setCommissionDetails(detailsResponse.data);
      setDelayedPayments(delayedResponse);
      setAnalytics(analyticsResponse.data);
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [filters.dateRange]);

  const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);

  const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

  const getStatusBadge = (status: string) => {
    const baseClasses = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium";
    switch (status.toLowerCase()) {
      case 'paid':
        return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300`;
      case 'pending':
        return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300`;
      case 'approved':
        return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300`;
      case 'disputed':
        return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300`;
      default:
        return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300`;
    }
  };

  if (loading) {
    return (
      <MainLayout>
        <Head title="Sales Rep Dashboard" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <FiRefreshCw className="animate-spin h-8 w-8 mx-auto mb-4 text-blue-600" />
            <p className={cn("text-lg", t.text.secondary)}>Loading your commission dashboard...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title="Sales Rep Dashboard" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-4 lg:space-y-0">
          <div>
            <h1 className={cn("text-3xl font-bold", t.text.primary)}>
              Commission Dashboard
            </h1>
            <p className={cn("text-sm", t.text.secondary)}>
              Welcome back, {auth.user.first_name}! Track your commissions and performance.
            </p>
          </div>

          <div className="flex items-center space-x-3">
            <div className="flex items-center space-x-2">
              <FiCalendar className={cn("w-4 h-4", t.text.secondary)} />
              <input
                type="date"
                value={filters.dateRange.start}
                onChange={(e) => setFilters(prev => ({
                  ...prev,
                  dateRange: { ...prev.dateRange, start: e.target.value }
                }))}
                className={cn(
                  "px-3 py-2 text-sm rounded-lg border",
                  theme === 'dark'
                    ? 'bg-white/10 border-white/20 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                )}
              />
              <span className={cn("text-sm", t.text.secondary)}>to</span>
              <input
                type="date"
                value={filters.dateRange.end}
                onChange={(e) => setFilters(prev => ({
                  ...prev,
                  dateRange: { ...prev.dateRange, end: e.target.value }
                }))}
                className={cn(
                  "px-3 py-2 text-sm rounded-lg border",
                  theme === 'dark'
                    ? 'bg-white/10 border-white/20 text-white'
                    : 'bg-white border-gray-300 text-gray-900'
                )}
              />
            </div>

            <button
              onClick={fetchData}
              className={cn(
                "flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all",
                "bg-blue-600 hover:bg-blue-700 text-white"
              )}
            >
              <FiRefreshCw className="w-4 h-4" />
              <span>Refresh</span>
            </button>
          </div>
        </div>

        {/* Metrics Overview */}
        {commissionSummary && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MetricCard
              title="Total Paid"
              value={formatCurrency(commissionSummary.totals.paid)}
              icon={<FiDollarSign />}
              status="success"
              subtitle={`${commissionSummary.byStatus.paid.count} payments`}
            />

            <MetricCard
              title="Pending Payments"
              value={formatCurrency(commissionSummary.totals.pending)}
              icon={<FiClock />}
              status="warning"
              subtitle={`${commissionSummary.byStatus.pending.count} pending`}
            />

            <MetricCard
              title="Processing"
              value={formatCurrency(commissionSummary.totals.processing)}
              icon={<FiTrendingUp />}
              status="info"
              subtitle={`${commissionSummary.byStatus.processing.count} in progress`}
            />

            <MetricCard
              title="Avg Payout Time"
              value={`${commissionSummary.averagePayoutDays} days`}
              icon={<FiCalendar />}
              status="default"
              subtitle={commissionSummary.nextPayoutDate ? `Next: ${formatDate(commissionSummary.nextPayoutDate)}` : 'No upcoming payouts'}
            />
          </div>
        )}

        {/* Delayed Payments Alert */}
        {delayedPayments && delayedPayments.summary.totalDelayed > 0 && (
          <div className={cn(
            "rounded-2xl p-6 border-2",
            "bg-red-500/10 border-red-500/20 backdrop-blur-xl"
          )}>
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <FiAlertTriangle className="w-6 h-6 text-red-500" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-semibold text-red-900 dark:text-red-100">
                  Delayed Payments Alert
                </h3>
                <p className="text-sm text-red-700 dark:text-red-200 mt-1">
                  You have {delayedPayments.summary.totalDelayed} payments delayed by more than 60 days,
                  totaling {formatCurrency(delayedPayments.summary.totalAmount)}.
                </p>
                <button
                  onClick={() => setActiveTab('delayed')}
                  className="inline-flex items-center mt-3 text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                >
                  View delayed payments
                  <FiChevronRight className="w-4 h-4 ml-1" />
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Tab Navigation */}
        <div className={cn(
          "rounded-2xl backdrop-blur-xl border-2",
          theme === 'dark'
            ? 'bg-white/[0.08] border-white/10'
            : 'bg-white/90 border-gray-200/50'
        )}>
          <div className="border-b border-gray-200/20">
            <nav className="-mb-px flex space-x-8 px-6">
              {[
                { id: 'overview', label: 'Overview', icon: FiTrendingUp },
                { id: 'details', label: 'Commission Details', icon: FiDollarSign },
                { id: 'delayed', label: 'Delayed Payments', icon: FiAlertTriangle },
                { id: 'analytics', label: 'Analytics', icon: FiUsers }
              ].map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id as any)}
                    className={cn(
                      "py-4 px-1 border-b-2 font-medium text-sm flex items-center space-x-2 transition-colors",
                      activeTab === tab.id
                        ? "border-blue-500 text-blue-600 dark:text-blue-400"
                        : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300"
                    )}
                  >
                    <Icon className="w-4 h-4" />
                    <span>{tab.label}</span>
                  </button>
                );
              })}
            </nav>
          </div>

          {/* Tab Content */}
          <div className="p-6">
            {activeTab === 'overview' && (
              <div className="space-y-6">
                <h3 className={cn("text-xl font-semibold", t.text.primary)}>
                  Recent Commission Activity
                </h3>

                <div className="space-y-4">
                  {commissionDetails.slice(0, 5).map((commission) => (
                    <div
                      key={commission.id}
                      className={cn(
                        "p-4 rounded-xl border transition-all hover:shadow-lg",
                        theme === 'dark'
                          ? 'bg-white/5 border-white/10 hover:bg-white/10'
                          : 'bg-white border-gray-200 hover:shadow-md'
                      )}
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-3">
                            <h4 className={cn("font-medium", t.text.primary)}>
                              {commission.orderId}
                            </h4>
                            <span className={getStatusBadge(commission.status)}>
                              {commission.status}
                            </span>
                          </div>
                          <p className={cn("text-sm mt-1", t.text.secondary)}>
                            {commission.providerName} • {commission.product.name}
                          </p>
                          <p className={cn("text-xs mt-1", t.text.tertiary)}>
                            {formatDate(commission.dateOfService)}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className={cn("font-semibold", t.text.primary)}>
                            {formatCurrency(commission.commissionAmount)}
                          </p>
                          {commission.split && (
                            <p className={cn("text-xs", t.text.secondary)}>
                              Split: {commission.split.subRepPercentage}%
                            </p>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                <button
                  onClick={() => setActiveTab('details')}
                  className={cn(
                    "w-full p-3 rounded-lg border-2 border-dashed font-medium transition-all",
                    "border-gray-300 text-gray-600 hover:border-blue-500 hover:text-blue-600",
                    "dark:border-gray-600 dark:text-gray-400 dark:hover:border-blue-400 dark:hover:text-blue-400"
                  )}
                >
                  View all commission details
                </button>
              </div>
            )}

            {activeTab === 'details' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between">
                  <h3 className={cn("text-xl font-semibold", t.text.primary)}>
                    Commission Details
                  </h3>
                  <div className="flex items-center space-x-2">
                    <button className={cn(
                      "flex items-center space-x-2 px-3 py-2 rounded-lg text-sm font-medium",
                      "bg-blue-600 hover:bg-blue-700 text-white"
                    )}>
                      <FiDownload className="w-4 h-4" />
                      <span>Export</span>
                    </button>
                  </div>
                </div>

                <div className="space-y-4">
                  {commissionDetails.map((commission) => (
                    <div
                      key={commission.id}
                      className={cn(
                        "p-6 rounded-xl border",
                        theme === 'dark'
                          ? 'bg-white/5 border-white/10'
                          : 'bg-white border-gray-200'
                      )}
                    >
                      <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div>
                          <p className={cn("text-xs font-medium uppercase tracking-wider", t.text.tertiary)}>
                            Order & Provider
                          </p>
                          <p className={cn("font-medium mt-1", t.text.primary)}>
                            {commission.orderId}
                          </p>
                          <p className={cn("text-sm", t.text.secondary)}>
                            {commission.providerName}
                          </p>
                          <p className={cn("text-xs", t.text.tertiary)}>
                            {commission.facilityName}
                          </p>
                        </div>

                        <div>
                          <p className={cn("text-xs font-medium uppercase tracking-wider", t.text.tertiary)}>
                            Product & Service
                          </p>
                          <p className={cn("font-medium mt-1", t.text.primary)}>
                            {commission.product.name}
                          </p>
                          <p className={cn("text-sm", t.text.secondary)}>
                            {commission.product.manufacturer}
                          </p>
                          <p className={cn("text-xs", t.text.tertiary)}>
                            Service: {formatDate(commission.dateOfService)}
                          </p>
                        </div>

                        <div>
                          <p className={cn("text-xs font-medium uppercase tracking-wider", t.text.tertiary)}>
                            Commission
                          </p>
                          <p className={cn("font-semibold text-lg mt-1", t.text.primary)}>
                            {formatCurrency(commission.commissionAmount)}
                          </p>
                          {commission.split && (
                            <p className={cn("text-sm", t.text.secondary)}>
                              Split: {commission.split.subRepPercentage}% of {formatCurrency(commission.split.repAmount + commission.split.subRepAmount)}
                            </p>
                          )}
                        </div>

                        <div className="flex items-center justify-between">
                          <div>
                            <span className={getStatusBadge(commission.status)}>
                              {commission.status}
                            </span>
                            {commission.paymentDate && (
                              <p className={cn("text-xs mt-1", t.text.tertiary)}>
                                Paid: {formatDate(commission.paymentDate)}
                              </p>
                            )}
                          </div>
                          <button className={cn(
                            "p-2 rounded-lg transition-colors",
                            "text-gray-400 hover:text-gray-600 hover:bg-gray-100",
                            "dark:hover:text-gray-300 dark:hover:bg-white/10"
                          )}>
                            <FiEye className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {activeTab === 'delayed' && delayedPayments && (
              <div className="space-y-6">
                <div className="flex items-center justify-between">
                  <h3 className={cn("text-xl font-semibold", t.text.primary)}>
                    Delayed Payments ({delayedPayments.summary.totalDelayed})
                  </h3>
                  <div className={cn("px-4 py-2 rounded-lg", "bg-red-100 dark:bg-red-900/30")}>
                    <p className={cn("text-sm font-medium", "text-red-800 dark:text-red-200")}>
                      Total: {formatCurrency(delayedPayments.summary.totalAmount)}
                    </p>
                  </div>
                </div>

                <div className="space-y-4">
                  {delayedPayments.data.map((payment, index) => (
                    <div
                      key={index}
                      className={cn(
                        "p-6 rounded-xl border-2",
                        "border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20"
                      )}
                    >
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className={cn("font-medium", t.text.primary)}>
                            {payment.orderId}
                          </h4>
                          <p className={cn("text-sm", t.text.secondary)}>
                            {payment.provider} • {payment.facility}
                          </p>
                          <p className={cn("text-xs mt-1", "text-red-600 dark:text-red-400")}>
                            {payment.daysDelayed} days overdue • {payment.reason}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className={cn("font-semibold text-lg", t.text.primary)}>
                            {formatCurrency(payment.amount)}
                          </p>
                          <p className={cn("text-xs", t.text.tertiary)}>
                            Due: {formatDate(payment.originalDueDate)}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {activeTab === 'analytics' && analytics && (
              <div className="space-y-6">
                <h3 className={cn("text-xl font-semibold", t.text.primary)}>
                  Performance Analytics
                </h3>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <div className={cn(
                    "p-6 rounded-xl border",
                    theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white border-gray-200'
                  )}>
                    <h4 className={cn("font-medium mb-4", t.text.primary)}>
                      Monthly Targets
                    </h4>
                    <div className="space-y-4">
                      <div className="flex justify-between items-center">
                        <span className={cn("text-sm", t.text.secondary)}>Target</span>
                        <span className={cn("font-medium", t.text.primary)}>
                          {formatCurrency(analytics.monthlyTargets.currentMonthTarget)}
                        </span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className={cn("text-sm", t.text.secondary)}>Actual</span>
                        <span className={cn("font-medium", t.text.primary)}>
                          {formatCurrency(analytics.monthlyTargets.currentMonthActual)}
                        </span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                        <div
                          className="bg-blue-600 h-2 rounded-full transition-all duration-500"
                          style={{ width: `${Math.min(analytics.monthlyTargets.achievementPercentage, 100)}%` }}
                        />
                      </div>
                      <div className="text-center">
                        <span className={cn("text-lg font-bold", t.text.primary)}>
                          {analytics.monthlyTargets.achievementPercentage.toFixed(1)}%
                        </span>
                        <span className={cn("text-sm ml-1", t.text.secondary)}>
                          achievement
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className={cn(
                    "p-6 rounded-xl border",
                    theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white border-gray-200'
                  )}>
                    <h4 className={cn("font-medium mb-4", t.text.primary)}>
                      Top Providers
                    </h4>
                    <div className="space-y-3">
                      {analytics.topProviders.slice(0, 5).map((provider, index) => (
                        <div key={index} className="flex justify-between items-center">
                          <div>
                            <p className={cn("text-sm font-medium", t.text.primary)}>
                              Provider #{provider.providerId}
                            </p>
                            <p className={cn("text-xs", t.text.tertiary)}>
                              {provider.orderCount} orders
                            </p>
                          </div>
                          <span className={cn("font-medium", t.text.primary)}>
                            {formatCurrency(provider.totalCommission)}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
