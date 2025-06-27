import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { UserWithRole } from '@/types/roles';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import GlassCard from '@/Components/ui/GlassCard';
import MetricCard from '@/Components/ui/MetricCard';
import Heading from '@/Components/ui/Heading';
import { FiDollarSign, FiTrendingUp, FiClock, FiPercent, FiFileText, FiCalendar } from 'react-icons/fi';
import { Line } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

interface MscAdminDashboardProps {
  user?: UserWithRole;
  dashboardData?: {
    business_metrics?: {
      total_outstanding_commissions: number;
      monthly_revenue: number;
      monthly_target: number;
      pending_approval_amount: number;
      collections_efficiency: number;
      profit_margin: number;
    };
    pending_approvals?: Array<{
      id: string;
      type: string;
      customer?: string;
      sales_rep?: string;
      amount: number;
      description: string;
      priority: string;
      submitted_date: string;
      link: string;
    }>;
    commission_queue?: Array<{
      id: string;
      sales_rep: string;
      territory: string;
      amount: number;
      period: string;
      status: string;
      due_date: string;
    }>;
    customer_financial_health?: Array<{
      id: string;
      customer: string;
      credit_limit: number;
      current_balance: number;
      utilization_percentage: number;
      payment_history: string;
      risk_level: string;
      last_payment: string;
    }>;
    recent_orders?: Array<{
      id: string;
      order_number: string;
      date: string;
      status: string;
      provider: string;
      amount: number;
      facility: string;
    }>;
    order_trends?: Array<{ date: string; orders: number }>;
  };
}

export default function MscAdminDashboard({ user, dashboardData }: MscAdminDashboardProps) {
  // Try to use theme if available, fallback to dark theme
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  const businessMetrics = dashboardData?.business_metrics || {
    total_outstanding_commissions: 0,
    monthly_revenue: 0,
    monthly_target: 320000,
    pending_approval_amount: 0,
    collections_efficiency: 0,
    profit_margin: 0,
  };

  const pendingApprovals = dashboardData?.pending_approvals || [];
  const commissionQueue = dashboardData?.commission_queue || [];
  const customerFinancialHealth = dashboardData?.customer_financial_health || [];
  const recentOrders = dashboardData?.recent_orders || [];
  const orderTrends = dashboardData?.order_trends || [];

  const revenueProgress = businessMetrics.monthly_target > 0
    ? (businessMetrics.monthly_revenue / businessMetrics.monthly_target) * 100
    : 0;

  return (
    <MainLayout>
      <Head title="MSC Admin Dashboard" />

      <div className="p-6 lg:p-8 space-y-8">
      {/* Header */}
      <div className="mb-10">
        <h1 className={cn("text-4xl font-bold", t.text.primary)}>Business Operations Dashboard</h1>
        <p className={cn("mt-3 text-lg leading-relaxed", t.text.secondary)}>
          Monitor financial performance, manage commissions, and oversee business operations across all territories and customers.
        </p>
      </div>

      {/* Key Business Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Outstanding Commissions"
          value={`$${businessMetrics.total_outstanding_commissions.toLocaleString()}`}
          subtitle="Awaiting payment"
          icon={<FiDollarSign className="h-8 w-8" />}
          status="danger"
        />

        <MetricCard
          title="Monthly Revenue"
          value={`$${businessMetrics.monthly_revenue.toLocaleString()}`}
          subtitle={`${revenueProgress.toFixed(1)}% of $${businessMetrics.monthly_target.toLocaleString()}`}
          icon={<FiTrendingUp className="h-8 w-8" />}
          status="success"
          trend={revenueProgress > 70 ? 15 : -5}
        />

        <MetricCard
          title="Pending Approvals"
          value={`$${businessMetrics.pending_approval_amount.toLocaleString()}`}
          subtitle={`${pendingApprovals.length} items waiting`}
          icon={<FiClock className="h-8 w-8" />}
          status="warning"
        />

        <MetricCard
          title="Profit Margin"
          value={`${businessMetrics.profit_margin}%`}
          subtitle={`Collections: ${businessMetrics.collections_efficiency}%`}
          icon={<FiPercent className="h-8 w-8" />}
          status="info"
        />
      </div>

      {/* Orders Over Time Chart */}
      <GlassCard className="p-0 overflow-hidden">
        <div className="p-8">
          <div className="flex items-center mb-6">
            <FiCalendar className="h-7 w-7 text-green-500" />
            <h2 className={cn("ml-4 text-2xl font-bold", t.text.primary)}>Orders Over Time</h2>
          </div>
          {orderTrends.length > 0 ? (
            <Line
              data={{
                labels: orderTrends.map((d) => d.date),
                datasets: [
                  {
                    label: 'Orders',
                    data: orderTrends.map((d) => d.orders),
                    fill: false,
                    borderColor: '#3b82f6',
                    backgroundColor: '#3b82f6',
                    tension: 0.3,
                  },
                ],
              }}
              options={{
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                  x: { grid: { display: false } },
                  y: { beginAtZero: true, grid: { color: theme === 'dark' ? '#22223b' : '#e5e7eb' } },
                },
              }}
            />
          ) : (
            <div className={cn("py-12 text-center text-gray-500 dark:text-gray-300")}>
              No order trend data available.
            </div>
          )}
        </div>
      </GlassCard>

      {/* Recent Orders Table */}
      <GlassCard className="p-0 overflow-hidden">
        <div className="p-8">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center">
              <FiFileText className="h-7 w-7 text-blue-500" />
              <h2 className={cn("ml-4 text-2xl font-bold", t.text.primary)}>Recent Orders</h2>
            </div>
            <Link
              href="/admin/orders"
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all duration-300",
                theme === 'dark'
                  ? 'bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 border border-blue-500/30'
                  : 'bg-blue-600 hover:bg-blue-700 text-white'
              )}
            >
              View All
            </Link>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order #</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Provider</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Facility</th>
                </tr>
              </thead>
              <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {recentOrders.length > 0 ? (
                  recentOrders.map((order) => (
                    <tr key={order.id} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                      <td className="px-6 py-4 whitespace-nowrap font-mono">{order.order_number}</td>
                      <td className="px-6 py-4 whitespace-nowrap">{order.date}</td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="inline-block px-2 py-1 rounded text-xs font-semibold bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                          {order.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">{order.provider}</td>
                      <td className="px-6 py-4 whitespace-nowrap">${order.amount?.toLocaleString()}</td>
                      <td className="px-6 py-4 whitespace-nowrap">{order.facility}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="px-6 py-8 text-center text-gray-500 dark:text-gray-300">
                      No recent orders found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </GlassCard>

      {/* Pending Approvals Section */}
      <GlassCard variant="warning" className="p-0 overflow-hidden">
        <div className="p-8">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-7 w-7 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-4">
                <h2 className={cn("text-2xl font-bold", t.text.primary)}>Pending Business Approvals</h2>
                <p className={cn("text-sm mt-1", t.text.secondary)}>High-value orders, commission adjustments, and credit decisions</p>
              </div>
            </div>
            <Link
              href="/admin/approvals"
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all duration-300",
                theme === 'dark'
                  ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 border border-amber-500/30'
                  : 'bg-amber-600 hover:bg-amber-700 text-white'
              )}
            >
              View All
            </Link>
          </div>
        </div>
        <div className={cn("divide-y", theme === 'dark' ? 'divide-white/10' : 'divide-gray-200')}>
          {pendingApprovals.length > 0 ? (
            pendingApprovals.map((approval) => (
              <div key={approval.id} className={cn("px-8 py-6 transition-colors", theme === 'dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50')}>
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className={cn("text-base font-semibold", t.text.primary)}>{approval.type}</h3>
                      <span className={cn(
                        "ml-2 px-2 py-1 text-xs font-medium rounded-full",
                        approval.priority === 'high' ? (theme === 'dark' ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-800') :
                        approval.priority === 'medium' ? (theme === 'dark' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-yellow-100 text-yellow-800') :
                        (theme === 'dark' ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-800')
                      )}>
                        {approval.priority} priority
                      </span>
                    </div>
                    <p className={cn("text-sm mt-1", t.text.secondary)}>
                      {approval.customer || approval.sales_rep} - ${approval.amount.toLocaleString()}
                    </p>
                    <p className={cn("text-sm", t.text.secondary)}>{approval.description}</p>
                    <p className={cn("text-xs mt-2", t.text.muted)}>Submitted: {approval.submitted_date}</p>
                  </div>
                  <Link
                    href={approval.link}
                    className={cn(
                      "ml-4 px-3 py-2 text-sm rounded-md transition-colors",
                      t.button.primary
                    )}
                  >
                    Review
                  </Link>
                </div>
              </div>
            ))
          ) : (
            <div className={cn("px-8 py-12 text-center", t.text.muted)}>
              No pending approvals at this time
            </div>
          )}
        </div>
      </GlassCard>

      {/* Commission Queue */}
      <GlassCard variant="success" className="p-0 overflow-hidden">
        <div className="p-8">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-7 w-7 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
              </div>
              <div className="ml-4">
                <h2 className={cn("text-2xl font-bold", t.text.primary)}>Commission Payment Queue</h2>
                <p className={cn("text-sm mt-1", t.text.secondary)}>Ready for payment processing</p>
              </div>
            </div>
            <Link
              href="/commission/payouts"
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all duration-300",
                theme === 'dark'
                  ? 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/30'
                  : 'bg-emerald-600 hover:bg-emerald-700 text-white'
              )}
            >
              Process Payments
            </Link>
          </div>
        </div>
        <div className={cn("divide-y", theme === 'dark' ? 'divide-white/10' : 'divide-gray-200')}>
          {commissionQueue.length > 0 ? (
            commissionQueue.map((commission) => (
              <div key={commission.id} className={cn("px-8 py-6 transition-colors", theme === 'dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50')}>
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className={cn("text-sm font-semibold", t.text.primary)}>{commission.sales_rep}</h3>
                      <span className={cn(
                        "ml-2 px-2 py-1 text-xs font-medium rounded-full",
                        theme === 'dark' ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-800'
                      )}>
                        {commission.territory}
                      </span>
                      <span className={cn(
                        "ml-2 px-2 py-1 text-xs font-medium rounded-full",
                        commission.status === 'ready_for_payment' ? (theme === 'dark' ? 'bg-green-500/20 text-green-300' : 'bg-green-100 text-green-800') :
                        commission.status === 'pending_approval' ? (theme === 'dark' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-yellow-100 text-yellow-800') :
                        (theme === 'dark' ? 'bg-white/10 text-white/60' : 'bg-gray-100 text-gray-800')
                      )}>
                        {commission.status.replace('_', ' ')}
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-3 gap-4">
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Amount</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>${commission.amount.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Period</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>{commission.period}</p>
                      </div>
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Due Date</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>{commission.due_date}</p>
                      </div>
                    </div>
                  </div>
                  <button className={cn(
                    "ml-4 px-3 py-2 text-sm rounded-md transition-colors",
                    t.button.primary
                  )}>
                    Process
                  </button>
                </div>
              </div>
            ))
          ) : (
            <div className={cn("px-8 py-12 text-center", t.text.muted)}>
              No commissions ready for payment
            </div>
          )}
        </div>
      </GlassCard>

      {/* Customer Financial Health */}
      <GlassCard className="p-0 overflow-hidden">
        <div className="p-8">
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-7 w-7 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <div className="ml-4">
                <h2 className={cn("text-2xl font-bold", t.text.primary)}>Customer Financial Health</h2>
                <p className={cn("text-sm mt-1", t.text.secondary)}>Credit utilization and payment history</p>
              </div>
            </div>
            <Link
              href="/customers/financial"
              className={cn(
                "px-6 py-3 rounded-xl font-medium transition-all duration-300",
                theme === 'dark'
                  ? 'bg-blue-500/20 text-blue-300 hover:bg-blue-500/30 border border-blue-500/30'
                  : 'bg-blue-600 hover:bg-blue-700 text-white'
              )}
            >
              View Details
            </Link>
          </div>
        </div>
        <div className={cn("divide-y", theme === 'dark' ? 'divide-white/10' : 'divide-gray-200')}>
          {customerFinancialHealth.length > 0 ? (
            customerFinancialHealth.map((customer) => (
              <div key={customer.id} className={cn("px-8 py-6 transition-colors", theme === 'dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50')}>
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className={cn("text-sm font-semibold", t.text.primary)}>{customer.customer}</h3>
                      <span className={cn(
                        "ml-2 px-2 py-1 text-xs font-medium rounded-full",
                        customer.risk_level === 'low' ? (theme === 'dark' ? 'bg-green-500/20 text-green-300' : 'bg-green-100 text-green-800') :
                        customer.risk_level === 'medium' ? (theme === 'dark' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-yellow-100 text-yellow-800') :
                        (theme === 'dark' ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-800')
                      )}>
                        {customer.risk_level} risk
                      </span>
                      <span className={cn(
                        "ml-2 px-2 py-1 text-xs font-medium rounded-full",
                        customer.payment_history === 'excellent' ? (theme === 'dark' ? 'bg-green-500/20 text-green-300' : 'bg-green-100 text-green-800') :
                        customer.payment_history === 'good' ? (theme === 'dark' ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-800') :
                        customer.payment_history === 'fair' ? (theme === 'dark' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-yellow-100 text-yellow-800') :
                        (theme === 'dark' ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-800')
                      )}>
                        {customer.payment_history} history
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-4 gap-4">
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Credit Limit</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>${customer.credit_limit.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Current Balance</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>${customer.current_balance.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Utilization</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>{customer.utilization_percentage.toFixed(1)}%</p>
                      </div>
                      <div>
                        <p className={cn("text-xs", t.text.muted)}>Last Payment</p>
                        <p className={cn("text-sm font-medium", t.text.primary)}>{customer.last_payment}</p>
                      </div>
                    </div>
                    <div className="mt-3">
                      <div className={cn("w-full rounded-full h-2", theme === 'dark' ? 'bg-white/10' : 'bg-gray-200')}>
                        <div
                          className={`h-2 rounded-full ${
                            customer.utilization_percentage > 90 ? 'bg-red-600' :
                            customer.utilization_percentage > 75 ? 'bg-yellow-500' :
                            'bg-green-600'
                          }`}
                          style={{ width: `${Math.min(customer.utilization_percentage, 100)}%` }}
                        ></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))
          ) : (
            <div className={cn("px-8 py-12 text-center", t.text.muted)}>
              No customer financial data available
            </div>
          )}
        </div>
      </GlassCard>

      {/* Quick Actions */}
      <GlassCard>
        <div className="mb-6">
          <h2 className={cn("text-2xl font-bold", t.text.primary)}>Quick Actions</h2>
          <p className={cn("text-sm mt-1", t.text.secondary)}>Common administrative tasks</p>
        </div>
        <div className="grid gap-6 md:grid-cols-3">
          <Link
            href="/admin/users"
            className={cn(
              "flex flex-col items-center p-8 border-2 rounded-2xl transition-all duration-300 group",
              "hover:scale-105 hover:-translate-y-1",
              theme === 'dark'
                ? 'border-white/10 hover:border-blue-500/40 hover:bg-blue-500/10 hover:shadow-[0_0_30px_rgba(59,130,246,0.3)]'
                : 'border-gray-200 hover:border-blue-500 hover:bg-blue-50 hover:shadow-lg'
            )}
          >
              <div className={cn(
                "p-4 rounded-xl mb-4 transition-all duration-300",
                "bg-gradient-to-br from-blue-500/20 to-blue-600/10",
                "group-hover:from-blue-500/30 group-hover:to-blue-600/20"
              )}>
                <svg className="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
              </div>
              <span className={cn("text-base font-semibold", t.text.primary)}>Manage Users</span>
            </Link>

            <Link
              href="/commission/management"
              className={cn(
                "flex flex-col items-center p-8 border-2 rounded-2xl transition-all duration-300 group",
                "hover:scale-105 hover:-translate-y-1",
                theme === 'dark'
                  ? 'border-white/10 hover:border-emerald-500/40 hover:bg-emerald-500/10 hover:shadow-[0_0_30px_rgba(16,185,129,0.3)]'
                  : 'border-gray-200 hover:border-emerald-500 hover:bg-emerald-50 hover:shadow-lg'
              )}
            >
              <div className={cn(
                "p-4 rounded-xl mb-4 transition-all duration-300",
                "bg-gradient-to-br from-emerald-500/20 to-emerald-600/10",
                "group-hover:from-emerald-500/30 group-hover:to-emerald-600/20"
              )}>
                <svg className="h-10 w-10 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
              </div>
              <span className={cn("text-base font-semibold", t.text.primary)}>Commission Management</span>
            </Link>

            <Link
              href="/reports"
              className={cn(
                "flex flex-col items-center p-8 border-2 rounded-2xl transition-all duration-300 group",
                "hover:scale-105 hover:-translate-y-1",
                theme === 'dark'
                  ? 'border-white/10 hover:border-purple-500/40 hover:bg-purple-500/10 hover:shadow-[0_0_30px_rgba(168,85,247,0.3)]'
                  : 'border-gray-200 hover:border-purple-500 hover:bg-purple-50 hover:shadow-lg'
              )}
            >
              <div className={cn(
                "p-4 rounded-xl mb-4 transition-all duration-300",
                "bg-gradient-to-br from-purple-500/20 to-purple-600/10",
                "group-hover:from-purple-500/30 group-hover:to-purple-600/20"
              )}>
                <svg className="h-10 w-10 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <span className={cn("text-base font-semibold", t.text.primary)}>Generate Reports</span>
            </Link>
          </div>
      </GlassCard>
      </div>
    </MainLayout>
  );
}
