import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { RoleRestrictions } from '@/types/roles';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import GlassCard from '@/Components/ui/GlassCard';
import MetricCard from '@/Components/ui/MetricCard';
import { FiUsers, FiFileText, FiClock, FiCheckCircle } from 'react-icons/fi';

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

  const getStatusColor = (status: string) => {
    const colors = theme === 'dark' ? {
      active: 'bg-green-500/20 text-green-300 border-green-500/30',
      inactive: 'bg-white/10 text-white/60 border-white/20',
      pending: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30'
    } : {
      active: 'bg-green-100 text-green-800',
      inactive: 'bg-gray-100 text-gray-800',
      pending: 'bg-yellow-100 text-yellow-800'
    };
    return colors[status] || colors.inactive;
  };

  const getPriorityColor = (priority: string) => {
    const colors = theme === 'dark' ? {
      high: 'bg-red-500/20 text-red-300 border-red-500/30',
      medium: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
      low: 'bg-green-500/20 text-green-300 border-green-500/30'
    } : {
      high: 'bg-red-100 text-red-800',
      medium: 'bg-yellow-100 text-yellow-800',
      low: 'bg-green-100 text-green-800'
    };
    return colors[priority] || colors.medium;
  };

  const getRequestStatusColor = (status: string) => {
    const colors = theme === 'dark' ? {
      draft: 'bg-white/10 text-white/60 border-white/20',
      submitted: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      pending_eligibility: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
      approved: 'bg-green-500/20 text-green-300 border-green-500/30',
      rejected: 'bg-red-500/20 text-red-300 border-red-500/30',
      pending_documentation: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
      in_review: 'bg-purple-500/20 text-purple-300 border-purple-500/30'
    } : {
      draft: 'bg-gray-100 text-gray-800',
      submitted: 'bg-blue-100 text-blue-800',
      pending_eligibility: 'bg-yellow-100 text-yellow-800',
      approved: 'bg-green-100 text-green-800',
      rejected: 'bg-red-100 text-red-800',
      pending_documentation: 'bg-orange-100 text-orange-800',
      in_review: 'bg-purple-100 text-purple-800'
    };
    return colors[status] || colors.draft;
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

      <div className="space-y-8">
        {/* Office Manager Welcome Header */}
        <div className="mb-8 text-center max-w-4xl mx-auto">
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-[#1925c3] to-[#c71719]">
            Welcome, {user.name}
          </h1>
          <p className={cn("mt-2 leading-normal", t.text.secondary)}>
            Coordinate facility operations, manage provider workflows, and oversee administrative tasks.
          </p>
        </div>

        {/* Key Metrics */}
        <div className="max-w-5xl mx-auto">
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <MetricCard
              title="Total Providers"
              value={facilityMetrics.total_providers}
              subtitle="Active in facility"
              icon={<FiUsers className="h-8 w-8" />}
              status="default"
              size="sm"
            />

            <MetricCard
              title="Active Requests"
              value={facilityMetrics.active_requests}
              subtitle="Currently processing"
              icon={<FiFileText className="h-8 w-8" />}
              status="info"
              size="sm"
            />

            <MetricCard
              title="Avg Processing Time"
              value={`${facilityMetrics.processing_time} days`}
              subtitle="Request to approval"
              icon={<FiClock className="h-8 w-8" />}
              status="success"
              size="sm"
            />

            <MetricCard
              title="Admin Tasks"
              value={facilityMetrics.admin_tasks}
              subtitle="Pending completion"
              icon={<FiCheckCircle className="h-8 w-8" />}
              status="warning"
              size="sm"
            />
          </div>
        </div>

        {/* Action Items Section */}
        {dashboardData.action_items && dashboardData.action_items.length > 0 && (
          <GlassCard className="max-w-5xl mx-auto" variant="warning">
            <div className="flex items-start gap-4">
              <div className="p-3 rounded-full">
                <FiClock className="h-6 w-6 animate-pulse text-amber-400" />
              </div>
              <div className="flex-1">
                <div className="mb-4">
                  <h2 className={cn("text-xl font-semibold", t.text.primary)}>Administrative Tasks</h2>
                  <p className={cn("text-sm mt-1", t.text.secondary)}>Items requiring your attention</p>
                </div>
                <div className="space-y-3">
                  {dashboardData.action_items.map((item) => (
                    <div key={item.id} className={cn("p-4 rounded-lg border flex items-start gap-4", t.glass.base, "border-amber-500/20")}>
                      <div className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getPriorityColor(item.priority)}`}>
                        {item.priority}
                      </div>
                      <div className="flex-1">
                        <h3 className={cn("text-sm font-semibold", t.text.primary)}>{item.patient_name}</h3>
                        <p className={cn("text-sm mt-1", t.text.secondary)}>{item.description}</p>
                        <p className={cn("text-xs mt-1", t.text.muted)}>Due: {item.due_date}</p>
                      </div>
                      <Link
                        href={route('product-requests.show', { id: item.request_id })}
                        className={cn(
                          "inline-flex items-center px-3 py-1 text-sm font-medium rounded-lg transition-all",
                          theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                        )}
                      >
                        View
                      </Link>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </GlassCard>
        )}

        {/* Provider Activity */}
        <GlassCard className="max-w-5xl mx-auto">
          <div className="flex items-start gap-4">
            <div className={cn(
              "p-3 rounded-full",
              theme === 'dark' ? 'bg-emerald-500/20' : 'bg-emerald-500/10'
            )}>
              <FiUsers className="h-6 w-6 text-emerald-500" />
            </div>
            <div className="flex-1">
              <div className="flex items-center justify-between mb-4">
                <div>
                  <h2 className={cn("text-xl font-semibold", t.text.primary)}>Provider Activity</h2>
                  <p className={cn("text-sm mt-1", t.text.secondary)}>Monitor and coordinate provider workflows</p>
                </div>
                <Link
                  href="/providers"
                  className={cn(
                    "px-4 py-2 rounded-lg font-medium transition-all",
                    theme === 'dark' ? t.button.primary : 'bg-blue-600 text-white hover:bg-blue-700'
                  )}
                >
                  Manage Providers
                </Link>
              </div>

              <div className="space-y-4">
                {providerActivity.map((provider) => (
                  <div key={provider.id} className={cn("p-4 rounded-lg border", t.glass.base, t.glass.border)}>
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <h3 className={cn("font-semibold", t.text.primary)}>{provider.providerName}</h3>
                          <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(provider.status)}`}>
                            {provider.status}
                          </span>
                        </div>
                        <p className={cn("text-sm", t.text.secondary)}>{provider.specialty}</p>
                        <div className="mt-3 grid grid-cols-3 gap-4">
                          <div>
                            <p className={cn("text-xs", t.text.muted)}>Active Patients</p>
                            <p className={cn("text-sm font-medium", t.text.primary)}>{provider.activePatients}</p>
                          </div>
                          <div>
                            <p className={cn("text-xs", t.text.muted)}>Requests This Week</p>
                            <p className={cn("text-sm font-medium", t.text.primary)}>{provider.requestsThisWeek}</p>
                          </div>
                          <div>
                            <p className={cn("text-xs", t.text.muted)}>Last Activity</p>
                            <p className={cn("text-sm font-medium", t.text.primary)}>{provider.lastActivity}</p>
                          </div>
                        </div>
                      </div>
                      <Link
                        href={`/providers/${provider.id}`}
                        className={cn(
                          "ml-4 px-3 py-2 rounded-lg font-medium transition-all",
                          theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                        )}
                      >
                        Manage
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </GlassCard>

        {/* Administrative Tasks */}
        <GlassCard className="max-w-5xl mx-auto">
          <div className="flex items-start gap-4">
            <div className={cn(
              "p-3 rounded-full",
              theme === 'dark' ? 'bg-blue-500/20' : 'bg-blue-500/10'
            )}>
              <FiFileText className="h-6 w-6 text-blue-500" />
            </div>
            <div className="flex-1">
              <div className="mb-4">
                <h2 className={cn("text-xl font-semibold", t.text.primary)}>Administrative Tasks</h2>
                <p className={cn("text-sm mt-1", t.text.secondary)}>Facility management and compliance tasks</p>
              </div>

              <div className="space-y-4">
                {adminTasks.map((task) => (
                  <div key={task.id} className={cn("p-4 rounded-lg border", t.glass.base, t.glass.border)}>
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <h3 className={cn("font-semibold", t.text.primary)}>{task.title}</h3>
                          <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getPriorityColor(task.priority)}`}>
                            {task.priority}
                          </span>
                        </div>
                        <p className={cn("text-sm", t.text.secondary)}>{task.description}</p>
                        <div className="mt-2 flex items-center gap-4">
                          <p className={cn("text-xs", t.text.muted)}>Due: {task.dueDate}</p>
                          <p className={cn("text-xs", t.text.muted)}>Assigned: {task.assignedTo}</p>
                        </div>
                      </div>
                      <button className={cn(
                        "px-3 py-2 rounded-lg font-medium transition-all",
                        theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                      )}>
                        Complete
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </GlassCard>

        {/* Recent Requests */}
        <GlassCard className="max-w-5xl mx-auto">
          <div className="mb-6">
            <h2 className={cn("text-xl font-semibold", t.text.primary)}>Recent Product Requests</h2>
            <p className={cn("text-sm mt-1", t.text.secondary)}>Latest requests from facility providers</p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className={cn(t.table.header)}>
                  <th className={cn("px-6 py-3 text-left", t.table.headerText)}>Request #</th>
                  <th className={cn("px-6 py-3 text-left", t.table.headerText)}>Patient</th>
                  <th className={cn("px-6 py-3 text-left", t.table.headerText)}>Wound Type</th>
                  <th className={cn("px-6 py-3 text-left", t.table.headerText)}>Status</th>
                  <th className={cn("px-6 py-3 text-left", t.table.headerText)}>Facility</th>
                  <th className={cn("px-6 py-3 text-left", t.table.headerText)}>Date</th>
                  <th className={cn("px-6 py-3 text-right", t.table.headerText)}>Actions</th>
                </tr>
              </thead>
              <tbody className={cn(t.table.body)}>
                {dashboardData.recent_requests.map((request, index) => (
                  <tr key={request.id} className={cn(
                    t.table.row,
                    index % 2 === 0 ? t.table.evenRow : '',
                    t.table.rowHover
                  )}>
                    <td className={cn("px-6 py-4 font-medium", t.text.primary)}>
                      {request.request_number}
                    </td>
                    <td className={cn("px-6 py-4", t.text.primary)}>
                      {request.patient_name}
                    </td>
                    <td className={cn("px-6 py-4 text-sm", t.text.secondary)}>
                      {request.wound_type}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getRequestStatusColor(request.status)}`}>
                        {request.status}
                      </span>
                    </td>
                    <td className={cn("px-6 py-4 text-sm", t.text.secondary)}>
                      {request.facility_name}
                    </td>
                    <td className={cn("px-6 py-4 text-sm", t.text.secondary)}>
                      {new Date(request.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <Link
                        href={route('product-requests.show', { id: request.id })}
                        className={cn(
                          "inline-flex items-center px-3 py-1 text-sm font-medium rounded-lg transition-all",
                          theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                        )}
                      >
                        View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </GlassCard>
      </div>
    </MainLayout>
  );
}
