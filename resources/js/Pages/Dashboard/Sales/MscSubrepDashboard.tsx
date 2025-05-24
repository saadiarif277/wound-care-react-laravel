import React from 'react';
import { Link } from '@inertiajs/react';
import { UserWithRole } from '@/types/roles';

interface MscSubrepDashboardProps {
  user?: UserWithRole;
}

// Limited access data for MSC Sub-Representatives
const subrepMetrics = {
  monthlyCommission: 7150.00,
  monthlyTarget: 8000.00,
  assignedCustomers: 7,
  activeActivities: 4,
  primaryRepName: 'Sarah Johnson'
};

const commissionSummary = [
  {
    id: 'SCS-001',
    period: 'January 2024',
    commission: 7150.00,
    status: 'pending_approval',
    primaryRepApproval: 'pending',
    dueDate: '2024-02-15'
  },
  {
    id: 'SCS-002',
    period: 'December 2023',
    commission: 6800.00,
    status: 'paid',
    primaryRepApproval: 'approved',
    dueDate: '2024-01-15'
  },
  {
    id: 'SCS-003',
    period: 'November 2023',
    commission: 6500.00,
    status: 'paid',
    primaryRepApproval: 'approved',
    dueDate: '2023-12-15'
  }
];

const assignedCustomers = [
  {
    id: 'AC-001',
    customer: 'Northside Clinic',
    lastContact: '2024-01-15',
    relationship: 'good',
    supportLevel: 'active',
    primaryContact: 'Dr. Lisa Anderson',
    nextFollowUp: '2024-01-20'
  },
  {
    id: 'AC-002',
    customer: 'Eastside Medical',
    lastContact: '2024-01-14',
    relationship: 'excellent',
    supportLevel: 'maintenance',
    primaryContact: 'Susan Miller, RN',
    nextFollowUp: '2024-01-22'
  },
  {
    id: 'AC-003',
    customer: 'Community Health',
    lastContact: '2024-01-13',
    relationship: 'fair',
    supportLevel: 'needs_attention',
    primaryContact: 'Dr. Robert Kim',
    nextFollowUp: '2024-01-18'
  },
  {
    id: 'AC-004',
    customer: 'Regional Wound Care',
    lastContact: '2024-01-12',
    relationship: 'good',
    supportLevel: 'active',
    primaryContact: 'Maria Rodriguez, NP',
    nextFollowUp: '2024-01-25'
  }
];

const recentActivities = [
  {
    id: 'RA-001',
    type: 'customer_visit',
    description: 'Product demonstration at Northside Clinic',
    customer: 'Northside Clinic',
    date: '2024-01-15',
    outcome: 'successful',
    nextAction: 'Follow up on pricing questions'
  },
  {
    id: 'RA-002',
    type: 'support_call',
    description: 'Training session for new staff at Eastside Medical',
    customer: 'Eastside Medical',
    date: '2024-01-14',
    outcome: 'completed',
    nextAction: 'Schedule quarterly review'
  },
  {
    id: 'RA-003',
    type: 'documentation',
    description: 'Assisted with prior authorization paperwork',
    customer: 'Community Health',
    date: '2024-01-13',
    outcome: 'in_progress',
    nextAction: 'Follow up on PA status'
  },
  {
    id: 'RA-004',
    type: 'coordination',
    description: 'Coordinated with Primary Rep on territory planning',
    customer: 'All Territory',
    date: '2024-01-12',
    outcome: 'completed',
    nextAction: 'Implement new customer approach'
  }
];

const coordinationTasks = [
  {
    id: 'CT-001',
    title: 'Weekly Territory Review',
    description: 'Coordinate with Primary Rep on customer status updates',
    priority: 'medium',
    dueDate: '2024-01-20',
    status: 'pending',
    assignedBy: 'Sarah Johnson'
  },
  {
    id: 'CT-002',
    title: 'Customer Training Support',
    description: 'Assist Community Health with staff training on new products',
    priority: 'high',
    dueDate: '2024-01-18',
    status: 'in_progress',
    assignedBy: 'Sarah Johnson'
  },
  {
    id: 'CT-003',
    title: 'Market Research Input',
    description: 'Provide feedback on competitor activity in assigned area',
    priority: 'low',
    dueDate: '2024-01-25',
    status: 'pending',
    assignedBy: 'Sarah Johnson'
  }
];

export default function MscSubrepDashboard({ user }: MscSubrepDashboardProps) {
  const commissionProgress = (subrepMetrics.monthlyCommission / subrepMetrics.monthlyTarget) * 100;

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'excellent':
      case 'paid':
      case 'completed':
      case 'successful':
        return 'text-green-600 bg-green-100';
      case 'good':
      case 'pending_approval':
      case 'in_progress':
        return 'text-yellow-600 bg-yellow-100';
      case 'fair':
      case 'needs_attention':
        return 'text-red-600 bg-red-100';
      case 'active':
      case 'maintenance':
        return 'text-blue-600 bg-blue-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high':
        return 'text-red-600 bg-red-100';
      case 'medium':
        return 'text-yellow-600 bg-yellow-100';
      case 'low':
        return 'text-blue-600 bg-blue-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Sub-Representative Dashboard</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          Support your assigned customers, coordinate with your Primary Rep ({subrepMetrics.primaryRepName}), and track your activities and limited commission access.
        </p>
      </div>

      {/* Key Sub-Rep Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Monthly Commission</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">${subrepMetrics.monthlyCommission.toLocaleString()}</p>
          <div className="mt-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-600">{commissionProgress.toFixed(1)}% of target</span>
              <span className="text-gray-600">${subrepMetrics.monthlyTarget.toLocaleString()}</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
              <div
                className="bg-green-600 h-2 rounded-full"
                style={{ width: `${Math.min(commissionProgress, 100)}%` }}
              ></div>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Assigned Customers</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{subrepMetrics.assignedCustomers}</p>
          <p className="text-xs text-gray-600 mt-2">Support accounts</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Activities</h3>
          <p className="text-3xl font-bold text-amber-600 mt-2">{subrepMetrics.activeActivities}</p>
          <p className="text-xs text-gray-600 mt-2">This week</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Primary Rep</h3>
          <p className="text-lg font-bold text-purple-600 mt-2">{subrepMetrics.primaryRepName}</p>
          <p className="text-xs text-gray-600 mt-2">Territory coordinator</p>
        </div>
      </div>

      {/* Coordination Tasks */}
      {coordinationTasks.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-purple-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2V9a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Coordination Tasks</h2>
                  <p className="text-sm text-gray-600 mt-1">Tasks assigned by your Primary Rep</p>
                </div>
              </div>
              <Link
                href="/subrep/coordination"
                className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
              >
                View All Tasks
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {coordinationTasks.map((task) => (
              <div key={task.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{task.title}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(task.priority)}`}>
                        {task.priority} priority
                      </span>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(task.status)}`}>
                        {task.status.replace('_', ' ')}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{task.description}</p>
                    <div className="mt-2 flex items-center space-x-4">
                      <div>
                        <p className="text-xs text-gray-500">Due Date</p>
                        <p className="text-sm font-medium">{task.dueDate}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Assigned By</p>
                        <p className="text-sm font-medium">{task.assignedBy}</p>
                      </div>
                    </div>
                  </div>
                  <button className="ml-4 px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition-colors">
                    Update Status
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Quick Actions for Sub-Rep */}
      <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Link
            href="/subrep/activities/new"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Log Activity</span>
          </Link>

          <Link
            href="/subrep/customers"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Customer Support</span>
          </Link>

          <Link
            href="/subrep/commission"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-purple-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
            </svg>
            <span className="text-sm font-medium text-gray-900">View Commission</span>
          </Link>

          <Link
            href="/subrep/coordinate"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Contact Primary Rep</span>
          </Link>
        </div>
      </div>

      {/* Limited Commission Access */}
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
                <h2 className="text-xl font-semibold text-gray-900">Limited Commission Access</h2>
                <p className="text-sm text-gray-600 mt-1">Your commission statements (Primary Rep approval required)</p>
              </div>
            </div>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {commissionSummary.map((commission) => (
            <div key={commission.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{commission.period}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(commission.status)}`}>
                      {commission.status.replace('_', ' ')}
                    </span>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(commission.primaryRepApproval)}`}>
                      Rep: {commission.primaryRepApproval}
                    </span>
                  </div>
                  <div className="mt-2 flex items-center space-x-4">
                    <div>
                      <p className="text-xs text-gray-500">Commission Amount</p>
                      <p className="text-lg font-bold text-green-600">${commission.commission.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Payment Due</p>
                      <p className="text-sm font-medium">{commission.dueDate}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Assigned Customer Activities */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-blue-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Assigned Customer Support</h2>
                <p className="text-sm text-gray-600 mt-1">Your customer support assignments and activities</p>
              </div>
            </div>
            <Link
              href="/subrep/customers/full-list"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              All Customers
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {assignedCustomers.map((customer) => (
            <div key={customer.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{customer.customer}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.relationship)}`}>
                      {customer.relationship} relationship
                    </span>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.supportLevel)}`}>
                      {customer.supportLevel.replace('_', ' ')}
                    </span>
                  </div>
                  <div className="mt-2 grid grid-cols-3 gap-4">
                    <div>
                      <p className="text-xs text-gray-500">Primary Contact</p>
                      <p className="text-sm font-medium">{customer.primaryContact}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Last Contact</p>
                      <p className="text-sm font-medium">{customer.lastContact}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Next Follow-up</p>
                      <p className="text-sm font-medium">{customer.nextFollowUp}</p>
                    </div>
                  </div>
                </div>
                <Link
                  href={`/subrep/customers/${customer.id}`}
                  className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                >
                  Support
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Recent Activities Log */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Recent Activities</h2>
                <p className="text-sm text-gray-600 mt-1">Your customer support and coordination activities</p>
              </div>
            </div>
            <Link
              href="/subrep/activities"
              className="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
            >
              Activity Log
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {recentActivities.map((activity) => (
            <div key={activity.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{activity.customer}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                      activity.type === 'customer_visit' ? 'bg-blue-100 text-blue-800' :
                      activity.type === 'support_call' ? 'bg-green-100 text-green-800' :
                      activity.type === 'documentation' ? 'bg-purple-100 text-purple-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {activity.type.replace('_', ' ')}
                    </span>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(activity.outcome)}`}>
                      {activity.outcome.replace('_', ' ')}
                    </span>
                  </div>
                  <p className="text-sm text-gray-600 mt-1">{activity.description}</p>
                  <div className="mt-2 flex items-center space-x-4">
                    <div>
                      <p className="text-xs text-gray-500">Date</p>
                      <p className="text-sm font-medium">{activity.date}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Next Action</p>
                      <p className="text-sm font-medium">{activity.nextAction}</p>
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
