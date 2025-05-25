import React from 'react';
import { Link } from '@inertiajs/react';
import { UserWithRole } from '@/types/roles';

interface MscSubrepDashboardProps {
  user?: UserWithRole;
}

// Sub-rep focused data
const subrepMetrics = {
  personalCommission: 8450.00,
  monthlyTarget: 12000.00,
  assignedCustomers: 8,
  activeOrders: 5,
  parentRepName: 'Michael Thompson'
};

const personalCommissionHistory = [
  {
    id: 'SC-001',
    period: 'January 2024',
    baseCommission: 7200.00,
    bonusCommission: 1250.00,
    totalCommission: 8450.00,
    status: 'pending_approval',
    dueDate: '2024-02-15'
  },
  {
    id: 'SC-002',
    period: 'December 2023',
    baseCommission: 6800.00,
    bonusCommission: 950.00,
    totalCommission: 7750.00,
    status: 'paid',
    dueDate: '2024-01-15'
  }
];

const assignedCustomers = [
  {
    id: 'AC-001',
    customer: 'Westside Clinic',
    monthlyRevenue: 15200.00,
    lastOrder: '2024-01-14',
    status: 'active',
    relationship: 'excellent',
    nextFollowUp: '2024-01-20'
  },
  {
    id: 'AC-002',
    customer: 'Community Health Center',
    monthlyRevenue: 12800.00,
    lastOrder: '2024-01-12',
    status: 'active',
    relationship: 'good',
    nextFollowUp: '2024-01-18'
  }
];

const trainingModules = [
  {
    id: 'TM-001',
    title: 'Advanced Wound Matrix Products',
    progress: 85,
    status: 'in_progress',
    dueDate: '2024-01-25',
    duration: '45 minutes'
  },
  {
    id: 'TM-002',
    title: 'Insurance Authorization Process',
    progress: 100,
    status: 'completed',
    completedDate: '2024-01-10',
    duration: '30 minutes'
  },
  {
    id: 'TM-003',
    title: 'Customer Relationship Management',
    progress: 60,
    status: 'in_progress',
    dueDate: '2024-01-30',
    duration: '60 minutes'
  }
];

const recentOrders = [
  {
    id: 'RO-001',
    customer: 'Westside Clinic',
    orderValue: 2850.00,
    status: 'approved',
    orderDate: '2024-01-15',
    commissionEarned: 285.00,
    productType: 'Advanced Dressing'
  },
  {
    id: 'RO-002',
    customer: 'Community Health Center',
    orderValue: 1920.00,
    status: 'pending_delivery',
    orderDate: '2024-01-14',
    commissionEarned: 192.00,
    productType: 'Compression System'
  }
];

export default function MscSubrepDashboard({ user }: MscSubrepDashboardProps) {
  const commissionProgress = (subrepMetrics.personalCommission / subrepMetrics.monthlyTarget) * 100;

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
      case 'completed':
      case 'approved':
      case 'excellent':
        return 'text-green-600 bg-green-100';
      case 'pending_approval':
      case 'pending_delivery':
      case 'in_progress':
      case 'good':
        return 'text-yellow-600 bg-yellow-100';
      case 'at_risk':
      case 'fair':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-4 sm:space-y-6">
        {/* Header - Mobile Optimized */}
        <div className="pt-4 sm:pt-6 pb-2 sm:pb-4">
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Sub-Representative Dashboard</h1>
          <p className="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600 leading-relaxed">
            Track your personal performance, manage assigned customers, and continue your professional development.
          </p>
          <p className="text-sm text-purple-600 font-medium mt-1">
            Parent Representative: {subrepMetrics.parentRepName}
        </p>
      </div>

        {/* Personal Performance Metrics - Mobile First Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Personal Commission</h3>
            <p className="text-2xl sm:text-3xl font-bold text-green-600 mt-1 sm:mt-2">${subrepMetrics.personalCommission.toLocaleString()}</p>
          <div className="mt-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-600">{commissionProgress.toFixed(1)}% of target</span>
              <span className="text-gray-600">${subrepMetrics.monthlyTarget.toLocaleString()}</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
              <div
                  className="bg-green-600 h-2 rounded-full transition-all duration-300"
                style={{ width: `${Math.min(commissionProgress, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Assigned Customers</h3>
            <p className="text-2xl sm:text-3xl font-bold text-blue-600 mt-1 sm:mt-2">{subrepMetrics.assignedCustomers}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">Active accounts</p>
        </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Orders</h3>
            <p className="text-2xl sm:text-3xl font-bold text-amber-600 mt-1 sm:mt-2">{subrepMetrics.activeOrders}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">In progress</p>
        </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Training Progress</h3>
            <p className="text-2xl sm:text-3xl font-bold text-purple-600 mt-1 sm:mt-2">75%</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">Completion rate</p>
        </div>
      </div>

        {/* Personal Commission Tracking - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-green-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Personal Commission History</h2>
                  <p className="text-sm text-gray-600 mt-1">Your commission earnings and payment status</p>
                </div>
              </div>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {personalCommissionHistory.map((commission) => (
              <div key={commission.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                  <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{commission.period}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(commission.status)}`}>
                        {commission.status.replace('_', ' ')}
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Base Commission</p>
                        <p className="text-sm font-medium">${commission.baseCommission.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Bonus Commission</p>
                        <p className="text-sm font-medium">${commission.bonusCommission.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Total Commission</p>
                        <p className="text-lg font-bold text-green-600">${commission.totalCommission.toLocaleString()}</p>
                      </div>
                    </div>
                    <p className="text-xs text-gray-500 mt-2">Due: {commission.dueDate}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Product Training Section - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-purple-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Product Training</h2>
                  <p className="text-sm text-gray-600 mt-1">Complete your training modules to enhance your product knowledge</p>
                </div>
              </div>
              <Link
                href="/training/modules"
                className="w-full sm:w-auto px-4 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors"
              >
                All Training
              </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
            {trainingModules.map((module) => (
              <div key={module.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{module.title}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(module.status)}`}>
                        {module.status.replace('_', ' ')}
                    </span>
                    </div>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Progress</p>
                        <div className="flex items-center space-x-2 mt-1">
                          <div className="flex-1 bg-gray-200 rounded-full h-2">
                            <div
                              className="bg-purple-600 h-2 rounded-full transition-all duration-300"
                              style={{ width: `${module.progress}%` }}
                            ></div>
                          </div>
                          <span className="text-sm font-medium">{module.progress}%</span>
                        </div>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Duration</p>
                        <p className="text-sm font-medium">{module.duration}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">
                          {module.status === 'completed' ? 'Completed' : 'Due Date'}
                        </p>
                        <p className="text-sm font-medium">
                          {module.status === 'completed' ? module.completedDate : module.dueDate}
                        </p>
                      </div>
                    </div>
                  </div>
                  <Link
                    href={`/training/modules/${module.id}`}
                    className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors"
                  >
                    {module.status === 'completed' ? 'Review' : 'Continue'}
                  </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

        {/* Assigned Customers - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-blue-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Assigned Customers</h2>
                  <p className="text-sm text-gray-600 mt-1">Your dedicated customer accounts and relationships</p>
              </div>
            </div>
            <Link
                href="/customers/assigned"
                className="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
            >
                View All
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {assignedCustomers.map((customer) => (
              <div key={customer.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                    <h3 className="text-sm font-semibold text-gray-900">{customer.customer}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.status)}`}>
                        {customer.status}
                      </span>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.relationship)}`}>
                      {customer.relationship} relationship
                    </span>
                    </div>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Monthly Revenue</p>
                        <p className="text-lg font-bold text-green-600">${customer.monthlyRevenue.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Last Order</p>
                        <p className="text-sm font-medium">{customer.lastOrder}</p>
                    </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                      <p className="text-xs text-gray-500">Next Follow-up</p>
                      <p className="text-sm font-medium">{customer.nextFollowUp}</p>
                    </div>
                  </div>
                </div>
                <Link
                    href={`/customers/${customer.id}`}
                    className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
                >
                    Manage Account
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

        {/* Recent Orders - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-amber-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
              </div>
              <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Recent Orders</h2>
                  <p className="text-sm text-gray-600 mt-1">Latest orders from your customer accounts</p>
              </div>
            </div>
            <Link
                href="/orders/my-orders"
                className="w-full sm:w-auto px-4 py-2 bg-amber-600 text-white text-center rounded-md hover:bg-amber-700 transition-colors"
            >
                View All Orders
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
            {recentOrders.map((order) => (
              <div key={order.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{order.customer}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(order.status)}`}>
                        {order.status.replace('_', ' ')}
                    </span>
                    </div>
                    <p className="text-sm text-gray-600">{order.productType}</p>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Order Value</p>
                        <p className="text-lg font-bold text-green-600">${order.orderValue.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Your Commission</p>
                        <p className="text-sm font-medium text-purple-600">${order.commissionEarned.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Order Date</p>
                        <p className="text-sm font-medium">{order.orderDate}</p>
                      </div>
                    </div>
                  </div>
                  <Link
                    href={`/orders/${order.id}`}
                    className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-amber-600 text-white text-center rounded-md hover:bg-amber-700 transition-colors"
                  >
                    View Order
                  </Link>
                </div>
              </div>
            ))}
            </div>
        </div>
      </div>
    </div>
  );
}
