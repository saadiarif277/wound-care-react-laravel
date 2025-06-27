import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { cn, themes } from '@/theme/glass-theme';
import MetricCard from '@/Components/ui/MetricCard';
import { api } from '@/lib/api';
import {
  FiDollarSign,
  FiUsers,
  FiTrendingUp,
  FiPercent,
  FiCalendar,
  FiRefreshCw,
  FiUser,
  FiTarget
} from 'react-icons/fi';

interface SubRepDashboardProps {
  user: any;
  auth: { user: any };
}

export default function SubRepDashboard({ user, auth }: SubRepDashboardProps) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [loading, setLoading] = useState(true);
  const [commissionSummary, setCommissionSummary] = useState<any>(null);
  const [parentRepInfo, setParentRepInfo] = useState<any>(null);
  const [recentCommissions, setRecentCommissions] = useState<any[]>([]);

  const [dateRange, setDateRange] = useState({
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const [summaryResponse, detailsResponse] = await Promise.all([
        api.salesReps.getCommissionSummary({
          date_from: dateRange.start,
          date_to: dateRange.end
        }),
        api.salesReps.getCommissionDetails({
          date_from: dateRange.start,
          date_to: dateRange.end,
          per_page: 5
        })
      ]);

      setCommissionSummary(summaryResponse);
      setRecentCommissions(detailsResponse.data || []);

      // Mock parent rep info - this would come from API
      setParentRepInfo({
        name: 'Sarah Johnson',
        territory: 'Northeast Region',
        splitPercentage: 50
      });
    } catch (error) {
      console.error('Error fetching sub-rep dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [dateRange]);

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
      default:
        return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300`;
    }
  };

  if (loading) {
    return (
      <MainLayout>
        <Head title="Sub-Rep Dashboard" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <FiRefreshCw className="animate-spin h-8 w-8 mx-auto mb-4 text-blue-600" />
            <p className={cn("text-lg", t.text.secondary)}>Loading your dashboard...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title="Sub-Rep Dashboard" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-4 lg:space-y-0">
          <div>
            <h1 className={cn("text-3xl font-bold", t.text.primary)}>
              Sub-Rep Dashboard
            </h1>
            <p className={cn("text-sm", t.text.secondary)}>
              Welcome back, {auth.user.first_name}! Track your commission splits and performance.
            </p>
          </div>

          <div className="flex items-center space-x-3">
            <div className="flex items-center space-x-2">
              <FiCalendar className={cn("w-4 h-4", t.text.secondary)} />
              <input
                type="date"
                value={dateRange.start}
                onChange={(e) => setDateRange(prev => ({ ...prev, start: e.target.value }))}
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
                value={dateRange.end}
                onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value }))}
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
              className="flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all bg-blue-600 hover:bg-blue-700 text-white"
            >
              <FiRefreshCw className="w-4 h-4" />
              <span>Refresh</span>
            </button>
          </div>
        </div>

        {/* Parent Rep Information */}
        {parentRepInfo && (
          <div className={cn(
            "rounded-2xl p-6 border-2 backdrop-blur-xl",
            theme === 'dark'
              ? 'bg-blue-500/10 border-blue-500/20'
              : 'bg-blue-50 border-blue-200'
          )}>
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <div className="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center">
                  <FiUser className="w-6 h-6 text-white" />
                </div>
              </div>
              <div className="flex-1">
                <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                  Parent Representative
                </h3>
                <p className={cn("text-sm mt-1", t.text.secondary)}>
                  {parentRepInfo.name} • {parentRepInfo.territory}
                </p>
                <div className="flex items-center space-x-4 mt-3">
                  <div className="flex items-center space-x-2">
                    <FiPercent className="w-4 h-4 text-blue-500" />
                    <span className={cn("text-sm font-medium", t.text.primary)}>
                      Your Split: {parentRepInfo.splitPercentage}%
                    </span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <FiPercent className="w-4 h-4 text-gray-500" />
                    <span className={cn("text-sm", t.text.secondary)}>
                      Parent Split: {100 - parentRepInfo.splitPercentage}%
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Sub-Rep Specific Metrics */}
        {commissionSummary && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MetricCard
              title="My Commission Share"
              value={formatCurrency(commissionSummary.totals?.paid || 0)}
              icon={<FiDollarSign />}
              status="success"
              subtitle="From split arrangements"
            />

            <MetricCard
              title="Parent Rep Earnings"
              value={formatCurrency((commissionSummary.totals?.paid || 0) * (100 - (parentRepInfo?.splitPercentage || 50)) / 100)}
              icon={<FiUsers />}
              status="info"
              subtitle="Their portion of splits"
            />

            <MetricCard
              title="Total Generated"
              value={formatCurrency((commissionSummary.totals?.paid || 0) * 2)} // Assuming 50/50 split
              icon={<FiTrendingUp />}
              status="default"
              subtitle="Combined commission value"
            />

            <MetricCard
              title="Commission Rate"
              value={`${parentRepInfo?.splitPercentage || 50}%`}
              icon={<FiPercent />}
              status="warning"
              subtitle="Your split percentage"
            />
          </div>
        )}

        {/* Recent Commission Activity */}
        <div className={cn(
          "rounded-2xl backdrop-blur-xl border-2",
          theme === 'dark'
            ? 'bg-white/[0.08] border-white/10'
            : 'bg-white/90 border-gray-200/50'
        )}>
          <div className="p-6 border-b border-gray-200/20">
            <h3 className={cn("text-xl font-semibold", t.text.primary)}>
              Recent Commission Activity
            </h3>
            <p className={cn("text-sm mt-1", t.text.secondary)}>
              Your share of commission splits from orders
            </p>
          </div>

          <div className="p-6">
            <div className="space-y-4">
              {recentCommissions.length > 0 ? (
                recentCommissions.map((commission: any) => (
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
                            {commission.orderId || commission.id}
                          </h4>
                          <span className={getStatusBadge(commission.status)}>
                            {commission.status}
                          </span>
                          {commission.split && (
                            <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                              Split: {commission.split.subRepPercentage}%
                            </span>
                          )}
                        </div>
                        <p className={cn("text-sm mt-1", t.text.secondary)}>
                          {commission.providerName || 'Unknown Provider'} • {commission.product?.name || 'Product'}
                        </p>
                        <div className="flex items-center space-x-4 mt-2">
                          <p className={cn("text-xs", t.text.tertiary)}>
                            Service: {commission.dateOfService ? formatDate(commission.dateOfService) : 'No date'}
                          </p>
                          {commission.split && (
                            <p className={cn("text-xs", t.text.tertiary)}>
                              Total Commission: {formatCurrency((commission.split.repAmount || 0) + (commission.split.subRepAmount || 0))}
                            </p>
                          )}
                        </div>
                      </div>
                      <div className="text-right">
                        <p className={cn("font-semibold text-lg", t.text.primary)}>
                          {formatCurrency(commission.commissionAmount || 0)}
                        </p>
                        <p className={cn("text-xs", t.text.secondary)}>
                          Your Share
                        </p>
                        {commission.split && (
                          <p className={cn("text-xs", t.text.tertiary)}>
                            Parent: {formatCurrency(commission.split.repAmount || 0)}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="text-center py-8">
                  <FiTarget className="w-12 h-12 mx-auto mb-4 text-gray-400" />
                  <p className={cn("text-lg", t.text.secondary)}>No commission activity yet</p>
                  <p className={cn("text-sm", t.text.tertiary)}>
                    Commission splits will appear here as orders are processed
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Performance Summary */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className={cn(
            "p-6 rounded-xl border",
            theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white border-gray-200'
          )}>
            <h4 className={cn("font-medium mb-4", t.text.primary)}>
              Split Performance
            </h4>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className={cn("text-sm", t.text.secondary)}>Your Earnings</span>
                <span className={cn("font-medium", t.text.primary)}>
                  {formatCurrency(commissionSummary?.totals?.paid || 0)}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className={cn("text-sm", t.text.secondary)}>Parent Rep Earnings</span>
                <span className={cn("font-medium", t.text.primary)}>
                  {formatCurrency((commissionSummary?.totals?.paid || 0) * (100 - (parentRepInfo?.splitPercentage || 50)) / 100)}
                </span>
              </div>
              <div className="border-t pt-3">
                <div className="flex justify-between items-center">
                  <span className={cn("text-sm font-medium", t.text.primary)}>Total Generated</span>
                  <span className={cn("font-bold text-lg", t.text.primary)}>
                    {formatCurrency((commissionSummary?.totals?.paid || 0) * 2)}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div className={cn(
            "p-6 rounded-xl border",
            theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white border-gray-200'
          )}>
            <h4 className={cn("font-medium mb-4", t.text.primary)}>
              Commission Status
            </h4>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className={cn("text-sm", t.text.secondary)}>Paid Commissions</span>
                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                  {commissionSummary?.byStatus?.paid?.count || 0}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className={cn("text-sm", t.text.secondary)}>Pending Payments</span>
                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                  {commissionSummary?.byStatus?.pending?.count || 0}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className={cn("text-sm", t.text.secondary)}>Processing</span>
                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                  {commissionSummary?.byStatus?.processing?.count || 0}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
