import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { UserWithRole } from '@/types/roles';

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
  };
}

export default function MscAdminDashboard({ user, dashboardData }: MscAdminDashboardProps) {
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

  const revenueProgress = businessMetrics.monthly_target > 0
    ? (businessMetrics.monthly_revenue / businessMetrics.monthly_target) * 100
    : 0;

  return (
    <MainLayout>
      <Head title="MSC Admin Dashboard" />

      <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Business Operations Dashboard</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          Monitor financial performance, manage commissions, and oversee business operations across all territories and customers.
        </p>
      </div>

      {/* Key Business Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Outstanding Commissions</h3>
          <p className="text-3xl font-bold text-red-600 mt-2">${businessMetrics.total_outstanding_commissions.toLocaleString()}</p>
          <p className="text-xs text-gray-600 mt-2">Awaiting payment</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Monthly Revenue</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">${businessMetrics.monthly_revenue.toLocaleString()}</p>
          <div className="mt-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-600">{revenueProgress.toFixed(1)}% of target</span>
              <span className="text-gray-600">${businessMetrics.monthly_target.toLocaleString()}</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
              <div
                className="bg-green-600 h-2 rounded-full"
                style={{ width: `${Math.min(revenueProgress, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Pending Approvals</h3>
          <p className="text-3xl font-bold text-amber-600 mt-2">${businessMetrics.pending_approval_amount.toLocaleString()}</p>
          <p className="text-xs text-gray-600 mt-2">{pendingApprovals.length} items waiting</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Profit Margin</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{businessMetrics.profit_margin}%</p>
          <p className="text-xs text-gray-600 mt-2">Collections: {businessMetrics.collections_efficiency}%</p>
        </div>
      </div>

      {/* Pending Approvals Section */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-amber-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Pending Business Approvals</h2>
                <p className="text-sm text-gray-600 mt-1">High-value orders, commission adjustments, and credit decisions</p>
              </div>
            </div>
            <Link
              href="/admin/approvals"
              className="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 transition-colors"
            >
              View All
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {pendingApprovals.length > 0 ? (
            pendingApprovals.map((approval) => (
              <div key={approval.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{approval.type}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        approval.priority === 'high' ? 'bg-red-100 text-red-800' :
                        approval.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {approval.priority} priority
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">
                      {approval.customer || approval.sales_rep} - ${approval.amount.toLocaleString()}
                    </p>
                    <p className="text-sm text-gray-600">{approval.description}</p>
                    <p className="text-xs text-gray-500 mt-2">Submitted: {approval.submitted_date}</p>
                  </div>
                  <Link
                    href={approval.link}
                    className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                  >
                    Review
                  </Link>
                </div>
              </div>
            ))
          ) : (
            <div className="p-6 text-center text-gray-500">
              No pending approvals at this time
            </div>
          )}
        </div>
      </div>

      {/* Commission Queue */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-green-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Commission Payment Queue</h2>
                <p className="text-sm text-gray-600 mt-1">Ready for payment processing</p>
              </div>
            </div>
            <Link
              href="/commission/payouts"
              className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
              Process Payments
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {commissionQueue.length > 0 ? (
            commissionQueue.map((commission) => (
              <div key={commission.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{commission.sales_rep}</h3>
                      <span className="ml-2 px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                        {commission.territory}
                      </span>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        commission.status === 'ready_for_payment' ? 'bg-green-100 text-green-800' :
                        commission.status === 'pending_approval' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-gray-100 text-gray-800'
                      }`}>
                        {commission.status.replace('_', ' ')}
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-3 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Amount</p>
                        <p className="text-sm font-medium">${commission.amount.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Period</p>
                        <p className="text-sm font-medium">{commission.period}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Due Date</p>
                        <p className="text-sm font-medium">{commission.due_date}</p>
                      </div>
                    </div>
                  </div>
                  <button className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                    Process
                  </button>
                </div>
              </div>
            ))
          ) : (
            <div className="p-6 text-center text-gray-500">
              No commissions ready for payment
            </div>
          )}
        </div>
      </div>

      {/* Customer Financial Health */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Customer Financial Health</h2>
                <p className="text-sm text-gray-600 mt-1">Credit utilization and payment history</p>
              </div>
            </div>
            <Link
              href="/customers/financial"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              View Details
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {customerFinancialHealth.length > 0 ? (
            customerFinancialHealth.map((customer) => (
              <div key={customer.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{customer.customer}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        customer.risk_level === 'low' ? 'bg-green-100 text-green-800' :
                        customer.risk_level === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {customer.risk_level} risk
                      </span>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        customer.payment_history === 'excellent' ? 'bg-green-100 text-green-800' :
                        customer.payment_history === 'good' ? 'bg-blue-100 text-blue-800' :
                        customer.payment_history === 'fair' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {customer.payment_history} history
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-4 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Credit Limit</p>
                        <p className="text-sm font-medium">${customer.credit_limit.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Current Balance</p>
                        <p className="text-sm font-medium">${customer.current_balance.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Utilization</p>
                        <p className="text-sm font-medium">{customer.utilization_percentage.toFixed(1)}%</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Last Payment</p>
                        <p className="text-sm font-medium">{customer.last_payment}</p>
                      </div>
                    </div>
                    <div className="mt-3">
                      <div className="w-full bg-gray-200 rounded-full h-2">
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
            <div className="p-6 text-center text-gray-500">
              No customer financial data available
            </div>
          )}
        </div>
      </div>

      {/* Quick Actions */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <h2 className="text-xl font-semibold text-gray-900">Quick Actions</h2>
          <p className="text-sm text-gray-600 mt-1">Common administrative tasks</p>
        </div>
        <div className="p-6">
          <div className="grid gap-4 md:grid-cols-3">
            <Link
              href="/admin/users"
              className="flex flex-col items-center p-4 border-2 border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
            >
              <svg className="h-8 w-8 text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
              </svg>
              <span className="text-sm font-medium text-gray-900">Manage Users</span>
            </Link>

            <Link
              href="/commission/management"
              className="flex flex-col items-center p-4 border-2 border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all duration-200 group"
            >
              <svg className="h-8 w-8 text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
              </svg>
              <span className="text-sm font-medium text-gray-900">Commission Management</span>
            </Link>

            <Link
              href="/reports"
              className="flex flex-col items-center p-4 border-2 border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all duration-200 group"
            >
              <svg className="h-8 w-8 text-purple-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span className="text-sm font-medium text-gray-900">Generate Reports</span>
            </Link>
          </div>
        </div>
      </div>
      </div>
    </MainLayout>
  );
}
