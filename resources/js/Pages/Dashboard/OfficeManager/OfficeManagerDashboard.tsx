import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { RoleRestrictions } from '@/types/roles';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  role_display_name: string;
}

interface DashboardData {
  recent_requests: Array<{
    id: string;
    request_number: string;
    patient_name: string;
    wound_type: string;
    status: string;
    created_at: string;
    facility_name: string;
    // Note: Office managers should NOT see financial data
  }>;
  action_items: Array<{
    id: string;
    type: string;
    patient_name: string;
    description: string;
    priority: string;
    due_date: string;
    request_id: string;
  }>;
  metrics: {
    total_requests: number;
    pending_requests: number;
    approved_requests: number;
    // Note: Office managers should NOT see financial metrics
  };
  facility_metrics?: {
    total_providers: number;
    active_requests: number;
    processing_time: number;
    admin_tasks: number;
  };
  provider_activity?: any[];
}

interface OfficeManagerDashboardProps {
  user: User;
  dashboardData: DashboardData;
  roleRestrictions: RoleRestrictions;
}

// Static provider activity data (this would come from facility relationships)
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

export default function OfficeManagerDashboard({ user, dashboardData, roleRestrictions }: OfficeManagerDashboardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return 'bg-green-100 text-green-800';
      case 'inactive':
        return 'bg-gray-100 text-gray-800';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high':
        return 'bg-red-100 text-red-800';
      case 'medium':
        return 'bg-yellow-100 text-yellow-800';
      case 'low':
        return 'bg-green-100 text-green-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getRequestStatusColor = (status: string) => {
    switch (status) {
      case 'draft':
        return 'bg-gray-100 text-gray-800';
      case 'submitted':
        return 'bg-blue-100 text-blue-800';
      case 'pending_eligibility':
        return 'bg-yellow-100 text-yellow-800';
      case 'approved':
        return 'bg-green-100 text-green-800';
      case 'rejected':
        return 'bg-red-100 text-red-800';
      case 'pending_documentation':
        return 'bg-orange-100 text-orange-800';
      case 'in_review':
        return 'bg-purple-100 text-purple-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  // Use facility metrics from dashboard data or fallback to defaults
  const facilityMetrics = dashboardData.facility_metrics || {
    total_providers: 12,
    active_requests: 28,
    processing_time: 2.3,
    admin_tasks: 5
  };

  return (
    <MainLayout>
      <Head title="Office Manager Dashboard" />

      <div className="space-y-6">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Facility Operations Dashboard</h1>
          <p className="mt-2 text-gray-600 leading-normal">
            Oversee facility operations, coordinate provider activities, and manage administrative workflows.
          </p>
        </div>

        {/* Key Facility Metrics - NO FINANCIAL DATA */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Providers</h3>
            <p className="text-3xl font-bold text-blue-600 mt-2">{facilityMetrics.total_providers}</p>
            <p className="text-xs text-gray-600 mt-2">In facility</p>
          </div>

          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Requests</h3>
            <p className="text-3xl font-bold text-green-600 mt-2">{facilityMetrics.active_requests}</p>
            <p className="text-xs text-gray-600 mt-2">In process</p>
          </div>

          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Avg Processing Time</h3>
            <p className="text-3xl font-bold text-amber-600 mt-2">{facilityMetrics.processing_time} days</p>
            <p className="text-xs text-gray-600 mt-2">Request to approval</p>
          </div>

          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Admin Tasks</h3>
            <p className="text-3xl font-bold text-purple-600 mt-2">{facilityMetrics.admin_tasks}</p>
            <p className="text-xs text-gray-600 mt-2">Pending action</p>
          </div>
        </div>

        {/* Action Required Items */}
        {dashboardData.action_items.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <svg className="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h2 className="text-xl font-semibold text-gray-900">Action Required</h2>
                    <p className="text-sm text-gray-600 mt-1">Items requiring immediate attention</p>
                  </div>
                </div>
                <Link
                  href="/product-requests"
                  className="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 transition-colors"
                >
                  View All
                </Link>
              </div>
            </div>
            <div className="divide-y divide-gray-200">
              {dashboardData.action_items.map((item) => (
                <div key={item.id} className="p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center">
                        <h3 className="text-sm font-semibold text-gray-900">{item.patient_name}</h3>
                        <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(item.priority)}`}>
                          {item.priority} priority
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                      <p className="text-xs text-gray-500 mt-1">Due: {item.due_date}</p>
                    </div>
                    <Link
                      href={`/product-requests/${item.id}`}
                      className="ml-4 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                      Take Action
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Recent Facility Requests - NO FINANCIAL DATA */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Recent Facility Requests</h2>
                  <p className="text-sm text-gray-600 mt-1">Latest product requests from facility providers</p>
                </div>
              </div>
              <Link
                href="/product-requests"
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                View All Requests
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {dashboardData.recent_requests.length > 0 ? (
              dashboardData.recent_requests.map((request) => (
                <div key={request.id} className="p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center">
                        <h3 className="text-sm font-semibold text-gray-900">{request.patient_name}</h3>
                        <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getRequestStatusColor(request.status)}`}>
                          {request.status.replace('_', ' ')}
                        </span>
                      </div>
                      <div className="mt-2 grid grid-cols-3 gap-4">
                        <div>
                          <p className="text-xs text-gray-500">Wound Type</p>
                          <p className="text-sm font-medium">{request.wound_type.replace('_', ' ')}</p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500">Request Date</p>
                          <p className="text-sm font-medium">{request.created_at}</p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500">Facility</p>
                          <p className="text-sm font-medium">{request.facility_name}</p>
                        </div>
                      </div>
                      {/* IMPORTANT: NO FINANCIAL DATA SHOWN FOR OFFICE MANAGERS */}
                    </div>
                    <Link
                      href={`/product-requests/${request.id}`}
                      className="ml-4 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                      View Request
                    </Link>
                  </div>
                </div>
              ))
            ) : (
              <div className="p-6 text-center text-gray-500">
                <p>No recent requests found.</p>
                <Link
                  href="/product-requests/create"
                  className="mt-2 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                >
                  Create New Request
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* Provider Activity Management */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Provider Activity</h2>
                  <p className="text-sm text-gray-600 mt-1">Monitor and coordinate provider workflows</p>
                </div>
              </div>
              <Link
                href="/providers"
                className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
              >
                Manage Providers
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
                    className="ml-4 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                  >
                    Manage
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Administrative Tasks */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
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
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{task.title}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(task.priority)}`}>
                        {task.priority} priority
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{task.description}</p>
                    <div className="mt-2 grid grid-cols-2 gap-4">
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
                  <button className="ml-4 px-3 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                    Manage Task
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Financial Restriction Notice */}
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-yellow-800">Financial Information Restricted</h3>
              <div className="mt-2 text-sm text-yellow-700">
                <p>
                  As an Office Manager, financial information including pricing, discounts, and amounts owed are not displayed.
                  You have full access to clinical workflows, provider coordination, and facility management features.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
