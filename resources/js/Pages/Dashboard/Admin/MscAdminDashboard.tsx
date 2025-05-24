import React from 'react';
import { Link } from '@inertiajs/react';
import { UserWithRole } from '@/types/roles';

interface MscAdminDashboardProps {
  user?: UserWithRole;
}

// Business-focused data for MSC Administrator
const businessMetrics = {
  totalOutstandingCommissions: 47850.00,
  monthlyRevenue: 285400.00,
  monthlyTarget: 320000.00,
  pendingApprovalAmount: 125600.00,
  collectionsEfficiency: 94.2,
  profitMargin: 18.5
};

const pendingApprovals = [
  {
    id: 'PA-2024-001',
    type: 'High-Value Order',
    customer: 'St. Mary\'s Wound Center',
    amount: 12500.00,
    description: 'Advanced wound matrix therapy - bulk order',
    priority: 'high',
    submittedDate: '2024-01-15',
    link: '/admin/approvals/PA-2024-001'
  },
  {
    id: 'PA-2024-002',
    type: 'Commission Adjustment',
    salesRep: 'Johnson, Mike',
    amount: 3200.00,
    description: 'Q4 territory bonus calculation',
    priority: 'medium',
    submittedDate: '2024-01-14',
    link: '/admin/approvals/PA-2024-002'
  },
  {
    id: 'PA-2024-003',
    type: 'Credit Limit Increase',
    customer: 'Regional Medical Group',
    amount: 50000.00,
    description: 'Credit limit increase request',
    priority: 'high',
    submittedDate: '2024-01-13',
    link: '/admin/approvals/PA-2024-003'
  }
];

const commissionQueue = [
  {
    id: 'CQ-2024-001',
    salesRep: 'Smith, Sarah',
    territory: 'Northeast',
    amount: 8450.00,
    period: 'December 2023',
    status: 'ready_for_payment',
    dueDate: '2024-01-20'
  },
  {
    id: 'CQ-2024-002',
    salesRep: 'Johnson, Mike',
    territory: 'Southeast',
    amount: 12200.00,
    period: 'December 2023',
    status: 'pending_approval',
    dueDate: '2024-01-20'
  },
  {
    id: 'CQ-2024-003',
    salesRep: 'Davis, Robert',
    territory: 'Midwest',
    amount: 6750.00,
    period: 'December 2023',
    status: 'ready_for_payment',
    dueDate: '2024-01-20'
  }
];

const customerFinancialHealth = [
  {
    id: 'CFH-001',
    customer: 'Metro Health System',
    creditLimit: 75000.00,
    currentBalance: 45200.00,
    utilizationPercentage: 60.3,
    paymentHistory: 'excellent',
    riskLevel: 'low',
    lastPayment: '2024-01-10'
  },
  {
    id: 'CFH-002',
    customer: 'City Medical Center',
    creditLimit: 50000.00,
    currentBalance: 48500.00,
    utilizationPercentage: 97.0,
    paymentHistory: 'good',
    riskLevel: 'medium',
    lastPayment: '2023-12-28'
  },
  {
    id: 'CFH-003',
    customer: 'Valley Wound Clinic',
    creditLimit: 25000.00,
    currentBalance: 23800.00,
    utilizationPercentage: 95.2,
    paymentHistory: 'fair',
    riskLevel: 'high',
    lastPayment: '2023-12-15'
  }
];

export default function MscAdminDashboard({ user }: MscAdminDashboardProps) {
  const revenueProgress = (businessMetrics.monthlyRevenue / businessMetrics.monthlyTarget) * 100;

  return (
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
          <p className="text-3xl font-bold text-red-600 mt-2">${businessMetrics.totalOutstandingCommissions.toLocaleString()}</p>
          <p className="text-xs text-gray-600 mt-2">Awaiting payment</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Monthly Revenue</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">${businessMetrics.monthlyRevenue.toLocaleString()}</p>
          <div className="mt-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-600">{revenueProgress.toFixed(1)}% of target</span>
              <span className="text-gray-600">${businessMetrics.monthlyTarget.toLocaleString()}</span>
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
          <p className="text-3xl font-bold text-amber-600 mt-2">${businessMetrics.pendingApprovalAmount.toLocaleString()}</p>
          <p className="text-xs text-gray-600 mt-2">{pendingApprovals.length} items waiting</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Profit Margin</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{businessMetrics.profitMargin}%</p>
          <p className="text-xs text-gray-600 mt-2">Collections: {businessMetrics.collectionsEfficiency}%</p>
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
          {pendingApprovals.map((approval) => (
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
                    {approval.customer || approval.salesRep} - ${approval.amount.toLocaleString()}
                  </p>
                  <p className="text-sm text-gray-600">{approval.description}</p>
                  <p className="text-xs text-gray-500 mt-2">Submitted: {approval.submittedDate}</p>
                </div>
                <Link
                  href={approval.link}
                  className="ml-4 inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                >
                  Review & Approve
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Commission Management Center */}
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
                <p className="text-sm text-gray-600 mt-1">Process sales representative commission payments</p>
              </div>
            </div>
            <Link
              href="/admin/commissions"
              className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
              Process Payments
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {commissionQueue.map((commission) => (
            <div key={commission.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{commission.salesRep}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                      commission.status === 'ready_for_payment' ? 'bg-green-100 text-green-800' :
                      commission.status === 'pending_approval' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {commission.status.replace('_', ' ')}
                    </span>
                  </div>
                  <p className="text-sm text-gray-600 mt-1">{commission.territory} Territory - {commission.period}</p>
                  <p className="text-lg font-semibold text-green-600">${commission.amount.toLocaleString()}</p>
                  <p className="text-xs text-gray-500 mt-1">Due: {commission.dueDate}</p>
                </div>
                <div className="flex space-x-2">
                  {commission.status === 'pending_approval' && (
                    <button className="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                      Approve
                    </button>
                  )}
                  {commission.status === 'ready_for_payment' && (
                    <button className="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition-colors">
                      Process Payment
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
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
                <p className="text-sm text-gray-600 mt-1">Monitor credit utilization and payment risk</p>
              </div>
            </div>
            <Link
              href="/admin/customers/financial"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Full Report
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {customerFinancialHealth.map((customer) => (
            <div key={customer.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{customer.customer}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                      customer.riskLevel === 'low' ? 'bg-green-100 text-green-800' :
                      customer.riskLevel === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {customer.riskLevel} risk
                    </span>
                  </div>
                  <div className="mt-2 flex items-center space-x-4">
                    <div>
                      <p className="text-xs text-gray-500">Credit Utilization</p>
                      <p className="text-sm font-medium">{customer.utilizationPercentage}%</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Current Balance</p>
                      <p className="text-sm font-medium">${customer.currentBalance.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Credit Limit</p>
                      <p className="text-sm font-medium">${customer.creditLimit.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Last Payment</p>
                      <p className="text-sm font-medium">{customer.lastPayment}</p>
                    </div>
                  </div>
                  <div className="mt-2">
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className={`h-2 rounded-full ${
                          customer.utilizationPercentage >= 90 ? 'bg-red-500' :
                          customer.utilizationPercentage >= 75 ? 'bg-yellow-500' :
                          'bg-green-500'
                        }`}
                        style={{ width: `${customer.utilizationPercentage}%` }}
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
