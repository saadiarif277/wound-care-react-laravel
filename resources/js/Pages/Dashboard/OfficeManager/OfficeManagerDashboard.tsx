import React from 'react';
import { Link } from '@inertiajs/react';
import { UserWithRole } from '@/types/roles';

interface OfficeManagerDashboardProps {
  user?: UserWithRole;
}

// Facility operations data for Office Manager
const facilityMetrics = {
  totalProviders: 12,
  activeRequests: 28,
  processingTime: 2.3,
  adminTasks: 5
};

const providerActivity = [
  {
    id: 'PA-001',
    providerName: 'Dr. Sarah Johnson',
    specialty: 'Wound Care Specialist',
    activePatients: 15,
    requestsThisWeek: 8,
    status: 'active',
    lastActivity: '2024-01-15 14:30'
  },
  {
    id: 'PA-002',
    providerName: 'Dr. Michael Chen',
    specialty: 'Podiatrist',
    activePatients: 22,
    requestsThisWeek: 12,
    status: 'active',
    lastActivity: '2024-01-15 13:45'
  },
  {
    id: 'PA-003',
    providerName: 'Dr. Emily Rodriguez',
    specialty: 'Vascular Surgeon',
    activePatients: 18,
    requestsThisWeek: 6,
    status: 'active',
    lastActivity: '2024-01-15 12:20'
  },
  {
    id: 'PA-004',
    providerName: 'Dr. Robert Williams',
    specialty: 'General Surgery',
    activePatients: 10,
    requestsThisWeek: 4,
    status: 'inactive',
    lastActivity: '2024-01-14 16:15'
  }
];

const adminTasks = [
  {
    id: 'AT-001',
    type: 'documentation_review',
    title: 'Review Provider Credentials',
    description: 'Annual credential review for Dr. Johnson due',
    priority: 'medium',
    dueDate: '2024-01-25',
    assignedTo: 'Compliance Team'
  },
  {
    id: 'AT-002',
    type: 'facility_management',
    title: 'Setup New Provider Office Space',
    description: 'Prepare office and equipment for new wound care specialist',
    priority: 'high',
    dueDate: '2024-01-22',
    assignedTo: 'Facilities Team'
  },
  {
    id: 'AT-003',
    type: 'facility_maintenance',
    title: 'Schedule Equipment Maintenance',
    description: 'Quarterly maintenance for wound care equipment',
    priority: 'low',
    dueDate: '2024-01-30',
    assignedTo: 'Facilities'
  }
];

const facilityPerformance = [
  {
    metric: 'Request Processing Time',
    current: '2.3 days',
    target: '2.0 days',
    trend: 'stable',
    status: 'warning'
  },
  {
    metric: 'Provider Utilization',
    current: '87%',
    target: '85%',
    trend: 'up',
    status: 'good'
  },
  {
    metric: 'Documentation Completion',
    current: '94%',
    target: '95%',
    trend: 'up',
    status: 'warning'
  },
  {
    metric: 'Patient Satisfaction',
    current: '4.6/5',
    target: '4.5/5',
    trend: 'up',
    status: 'good'
  }
];

const communicationCenter = [
  {
    id: 'CC-001',
    type: 'provider_notification',
    title: 'New MAC Guidelines Available',
    message: 'Updated guidelines for diabetic foot ulcer documentation',
    priority: 'medium',
    timestamp: '2024-01-15 10:30',
    recipients: 'All Providers'
  },
  {
    id: 'CC-002',
    type: 'system_update',
    title: 'System Maintenance Scheduled',
    message: 'Planned maintenance window: Jan 20, 2-4 AM',
    priority: 'high',
    timestamp: '2024-01-15 09:15',
    recipients: 'All Users'
  },
  {
    id: 'CC-003',
    type: 'training_announcement',
    title: 'Monthly Training Session',
    message: 'Advanced wound assessment training - Jan 25, 2 PM',
    priority: 'low',
    timestamp: '2024-01-15 08:00',
    recipients: 'Clinical Staff'
  }
];

export default function OfficeManagerDashboard({ user }: OfficeManagerDashboardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
      case 'good':
        return 'text-green-600 bg-green-100';
      case 'warning':
        return 'text-yellow-600 bg-yellow-100';
      case 'inactive':
      case 'poor':
        return 'text-red-600 bg-red-100';
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

  const getTrendIcon = (trend: string) => {
    switch (trend) {
      case 'up':
        return (
          <svg className="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 17l9.2-9.2M17 17V7m0 10H7" />
          </svg>
        );
      case 'down':
        return (
          <svg className="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 7l-9.2 9.2M7 7v10h10" />
          </svg>
        );
      default:
        return (
          <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" />
          </svg>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Facility Operations Dashboard</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          Oversee facility operations, coordinate provider activities, and manage administrative workflows.
        </p>
      </div>

      {/* Key Facility Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Providers</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{facilityMetrics.totalProviders}</p>
          <p className="text-xs text-gray-600 mt-2">In facility</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Requests</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">{facilityMetrics.activeRequests}</p>
          <p className="text-xs text-gray-600 mt-2">In process</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Avg Processing Time</h3>
          <p className="text-3xl font-bold text-amber-600 mt-2">{facilityMetrics.processingTime} days</p>
          <p className="text-xs text-gray-600 mt-2">Request to approval</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Admin Tasks</h3>
          <p className="text-3xl font-bold text-purple-600 mt-2">{facilityMetrics.adminTasks}</p>
          <p className="text-xs text-gray-600 mt-2">Pending action</p>
        </div>
      </div>

      {/* Administrative Tasks */}
      {adminTasks.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-purple-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2V9a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Administrative Tasks</h2>
                  <p className="text-sm text-gray-600 mt-1">Facility management and operational tasks</p>
                </div>
              </div>
              <Link
                href="/admin/tasks"
                className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
              >
                View All Tasks
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {adminTasks.map((task) => (
              <div key={task.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{task.title}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(task.priority)}`}>
                        {task.priority} priority
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{task.description}</p>
                    <div className="mt-2 flex items-center space-x-4">
                      <div>
                        <p className="text-xs text-gray-500">Due Date</p>
                        <p className="text-sm font-medium">{task.dueDate}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Assigned To</p>
                        <p className="text-sm font-medium">{task.assignedTo}</p>
                      </div>
                    </div>
                  </div>
                  <button className="ml-4 px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition-colors">
                    Manage
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Quick Administrative Actions */}
      <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Quick Administrative Actions</h2>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <Link
            href="/providers/manage"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Manage Providers</span>
          </Link>

          <Link
            href="/facility/reports"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">View Reports</span>
          </Link>

          <Link
            href="/facility/settings"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-purple-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Facility Settings</span>
          </Link>


        </div>
      </div>

      {/* Provider Activity Overview */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-blue-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Provider Activity Overview</h2>
                <p className="text-sm text-gray-600 mt-1">Monitor provider workload and activity levels</p>
              </div>
            </div>
            <Link
              href="/providers/detailed-view"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Detailed View
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {providerActivity.map((provider) => (
            <div key={provider.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                  <div className="flex-shrink-0">
                    <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                      <span className="text-white text-sm font-medium">
                        {provider.providerName.split(' ').map(n => n[0]).join('')}
                      </span>
                    </div>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{provider.providerName}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(provider.status)}`}>
                        {provider.status}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600">{provider.specialty}</p>
                    <div className="mt-1 grid grid-cols-3 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Active Patients</p>
                        <p className="text-sm font-medium">{provider.activePatients}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Requests This Week</p>
                        <p className="text-sm font-medium">{provider.requestsThisWeek}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Last Activity</p>
                        <p className="text-sm font-medium">{provider.lastActivity}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <Link
                  href={`/providers/${provider.id}`}
                  className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                >
                  View Details
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Facility Performance Metrics */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Facility Performance Metrics</h2>
                <p className="text-sm text-gray-600 mt-1">Track operational efficiency and performance indicators</p>
              </div>
            </div>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {facilityPerformance.map((metric, index) => (
            <div key={index} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-900">{metric.metric}</h3>
                    <div className="flex items-center space-x-2">
                      {getTrendIcon(metric.trend)}
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(metric.status)}`}>
                        {metric.status}
                      </span>
                    </div>
                  </div>
                  <div className="mt-2 flex items-center justify-between">
                    <div>
                      <p className="text-xs text-gray-500">Current</p>
                      <p className="text-lg font-bold text-gray-900">{metric.current}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Target</p>
                      <p className="text-sm font-medium text-gray-600">{metric.target}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Communication Center */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-indigo-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Communication Center</h2>
                <p className="text-sm text-gray-600 mt-1">Provider notifications and facility announcements</p>
              </div>
            </div>
            <Link
              href="/communications"
              className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors"
            >
              View All
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {communicationCenter.map((communication) => (
            <div key={communication.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{communication.title}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(communication.priority)}`}>
                      {communication.priority} priority
                    </span>
                  </div>
                  <p className="text-sm text-gray-600 mt-1">{communication.message}</p>
                  <div className="mt-2 flex items-center space-x-4">
                    <div>
                      <p className="text-xs text-gray-500">Timestamp</p>
                      <p className="text-sm font-medium">{communication.timestamp}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Recipients</p>
                      <p className="text-sm font-medium">{communication.recipients}</p>
                    </div>
                  </div>
                </div>
                <button className="ml-4 px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors">
                  Manage
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
