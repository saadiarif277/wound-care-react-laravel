import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { PricingDisplay, OrderTotalDisplay } from '@/Components/Pricing/PricingDisplay';
import { RoleRestrictions, UserWithRole } from '@/types/roles';
import { FiPlus, FiTrendingUp, FiClock, FiCheck, FiAlertTriangle, FiUser, FiFileText, FiArrowUpRight, FiActivity } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { colors } from '@/theme/colors.config';
import Heading from '@/Components/ui/Heading';
import MetricCard from '@/Components/ui/MetricCard';
import GlassCard from '@/Components/ui/GlassCard';
import ActionButton from '@/Components/ui/ActionButton';
import clsx from 'clsx';

interface DashboardData {
  recent_requests: Array<{
    id: string;
    request_number: string;
    patient_name: string;
    wound_type: string;
    status: string;
    created_at: string;
    facility_name: string;
    total_amount?: number;
    amount_owed?: number;
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
    total_amount_owed?: number;
    total_savings?: number;
  };
  clinical_opportunities?: Array<{
    id: string;
    type: string;
    patient: string;
    description: string;
    priority: string;
    estimated_value?: number;
    hcpcs_code?: string;
  }>;
  eligibility_status?: Array<{
    payer: string;
    status: string;
    last_updated: string;
    coverage: string;
    deductible: string;
  }>;
}

interface ProviderDashboardProps {
  user: UserWithRole;
  dashboardData: DashboardData;
  roleRestrictions: RoleRestrictions;
}

export default function ProviderDashboard(props: ProviderDashboardProps) {
  return (
    <MainLayout>
      <DashboardContent {...props} />
    </MainLayout>
  );
}

function DashboardContent({ user, dashboardData, roleRestrictions }: ProviderDashboardProps) {
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
      draft: 'bg-white/[0.08] text-white/55 border-white/[0.15]',
      submitted: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      approved: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
      rejected: 'bg-red-500/20 text-red-300 border-red-500/30',
      processing: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
    } : {
      draft: 'bg-gray-100 text-gray-600 border-gray-300',
      submitted: 'bg-blue-50 text-blue-700 border-blue-200',
      approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      rejected: 'bg-red-50 text-red-700 border-red-200',
      processing: 'bg-amber-50 text-amber-700 border-amber-200',
    };
    return colors[status] || colors.draft;
  };

  const getPriorityColor = (priority: string) => {
    const colors = theme === 'dark' ? {
      high: 'bg-red-500/20 text-red-300 border-red-500/30',
      medium: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
      low: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
    } : {
      high: 'bg-red-50 text-red-700 border-red-200',
      medium: 'bg-amber-50 text-amber-700 border-amber-200',
      low: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };
    return colors[priority] || colors.medium;
  };

  const getEligibilityStatusColor = (status: string) => {
    const colors = theme === 'dark' ? {
      verified: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
      pending: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
      expired: 'bg-red-500/20 text-red-300 border-red-500/30',
    } : {
      verified: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      pending: 'bg-amber-50 text-amber-700 border-amber-200',
      expired: 'bg-red-50 text-red-700 border-red-200',
    };
    return colors[status] || colors.pending;
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  return (
    <>
      <Head title="Provider Dashboard" />

      <div className="relative space-y-6">
        {/* Create Request Button - Top Right */}
        <div className="absolute top-0 right-0">
          <Link
            href={route('quick-requests.create-new')}
            className={cn(
              "inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all",
              theme === 'dark' 
                ? "bg-[#1925c3] hover:bg-[#1925c3]/90 text-white" 
                : "bg-[#1925c3] hover:bg-[#1520a6] text-white"
            )}
          >
            <FiPlus className="h-4 w-4" />
            Create New Product Request
          </Link>
        </div>

        {/* Provider Welcome Header */}
        <div className="mb-8 text-center max-w-4xl mx-auto">
          <Heading level={1} className="bg-clip-text text-transparent bg-gradient-to-r from-[#1925c3] to-[#c71719]">
            Welcome, {user.name}
          </Heading>
          <p className={cn("mt-2 leading-normal", t.text.secondary)}>
            Manage your wound care product requests, track patient outcomes, and access clinical decision support tools.
          </p>
        </div>

        {/* Key Metrics */}
        <div className="max-w-5xl mx-auto">
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <MetricCard
              title="Total Requests"
              value={dashboardData.metrics.total_requests}
              subtitle="All time"
              icon={<FiActivity className="h-8 w-8" />}
              status="default"
              size="sm"
            />

            <MetricCard
              title="Pending Requests"
              value={dashboardData.metrics.pending_requests}
              subtitle="Awaiting processing"
              icon={<FiClock className="h-8 w-8" />}
              status="info"
              size="sm"
            />

            <MetricCard
              title="Approved Requests"
              value={dashboardData.metrics.approved_requests}
              subtitle="Ready for delivery"
              icon={<FiCheck className="h-8 w-8" />}
              status="success"
              trend={15}
              size="sm"
            />

            {roleRestrictions.can_view_financials && dashboardData.metrics.total_amount_owed && (
              <MetricCard
                title="Total Value"
                value={formatCurrency(dashboardData.metrics.total_amount_owed)}
                subtitle="Approved orders"
                icon={<FiTrendingUp className="h-8 w-8" />}
                status="danger"
                trend={-5}
                size="sm"
              />
            )}
          </div>
        </div>

        {/* Action Items Section */}
        {dashboardData.action_items.length > 0 && (
          <GlassCard className="max-w-5xl mx-auto" variant="warning">
            <div className="flex items-start gap-4">
              <div className="p-3 rounded-full">
                <FiAlertTriangle className="h-6 w-6 animate-pulse text-amber-400" />
              </div>
              <div className="flex-1">
                <div className="mb-4">
                  <h2 className={cn("text-xl font-semibold", t.text.primary)}>Action Required</h2>
                  <p className={cn("text-sm mt-1", t.text.secondary)}>Items that need your immediate attention</p>
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
                        href={route('product-requests.show', { productRequest: item.request_id })}
                        className={cn(
                          "inline-flex items-center px-3 py-1 text-sm font-medium rounded-lg transition-all",
                          theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                        )}
                      >
                        View <FiArrowUpRight className="ml-1 h-3 w-3" />
                      </Link>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </GlassCard>
        )}

        {/* Recent Requests */}
        <GlassCard className="max-w-5xl mx-auto">
          <div className="mb-6">
            <h2 className={cn("text-xl font-semibold", t.text.primary)}>Recent Product Requests</h2>
            <p className={cn("text-sm mt-1", t.text.secondary)}>Your latest wound care supply orders</p>
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
                  {roleRestrictions.can_view_financials && (
                    <th className={cn("px-6 py-3 text-right", t.table.headerText)}>Amount</th>
                  )}
                  <th className={cn("px-6 py-3 text-right", t.table.headerText)}>Actions</th>
                </tr>
              </thead>
              <tbody>
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
                      <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                        {request.status}
                      </span>
                    </td>
                    <td className={cn("px-6 py-4 text-sm", t.text.secondary)}>
                      {request.facility_name}
                    </td>
                    <td className={cn("px-6 py-4 text-sm", t.text.secondary)}>
                      {new Date(request.created_at).toLocaleDateString()}
                    </td>
                    {roleRestrictions.can_view_financials && (
                      <td className={cn("px-6 py-4 text-right font-medium", t.text.primary)}>
                        {request.total_amount ? formatCurrency(request.total_amount) : '-'}
                      </td>
                    )}
                    <td className="px-6 py-4 text-right">
                      <Link
                        href={route('product-requests.show', { productRequest: request.id })}
                        className={cn(
                          "inline-flex items-center px-3 py-1 text-sm font-medium rounded-lg transition-all",
                          theme === 'dark' ? t.table.actionButton : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
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

        {/* Quick Actions */}
        <div className="max-w-5xl mx-auto grid gap-6 md:grid-cols-3">
          <Link
            href={route('product-requests.create')}
            className={cn(
              "group relative overflow-hidden rounded-2xl p-6 transition-all duration-300 hover:scale-105",
              theme === 'dark' ? t.glass.card : 'bg-white border border-gray-200 shadow-sm hover:shadow-md'
            )}
          >
            <div className="flex items-center gap-4">
              <div className={cn(
                "p-3 rounded-full",
                theme === 'dark' ? 'bg-[#1925c3]/20' : 'bg-[#1925c3]/10'
              )}>
                <FiPlus className="h-6 w-6 text-[#1925c3]" />
              </div>
              <div>
                <h3 className={cn("font-semibold", t.text.primary)}>New Request</h3>
                <p className={cn("text-sm mt-1", t.text.secondary)}>Create a product request</p>
              </div>
            </div>
          </Link>

          <Link
            href={route('providers.index')}
            className={cn(
              "group relative overflow-hidden rounded-2xl p-6 transition-all duration-300 hover:scale-105",
              theme === 'dark' ? t.glass.card : 'bg-white border border-gray-200 shadow-sm hover:shadow-md'
            )}
          >
            <div className="flex items-center gap-4">
              <div className={cn(
                "p-3 rounded-full",
                theme === 'dark' ? 'bg-emerald-500/20' : 'bg-emerald-500/10'
              )}>
                <FiUser className="h-6 w-6 text-emerald-500" />
              </div>
              <div>
                <h3 className={cn("font-semibold", t.text.primary)}>My Profile</h3>
                <p className={cn("text-sm mt-1", t.text.secondary)}>Update your information</p>
              </div>
            </div>
          </Link>

          <Link
            href={route('product-requests.index')}
            className={cn(
              "group relative overflow-hidden rounded-2xl p-6 transition-all duration-300 hover:scale-105",
              theme === 'dark' ? t.glass.card : 'bg-white border border-gray-200 shadow-sm hover:shadow-md'
            )}
          >
            <div className="flex items-center gap-4">
              <div className={cn(
                "p-3 rounded-full",
                theme === 'dark' ? 'bg-amber-500/20' : 'bg-amber-500/10'
              )}>
                <FiFileText className="h-6 w-6 text-amber-500" />
              </div>
              <div>
                <h3 className={cn("font-semibold", t.text.primary)}>All Requests</h3>
                <p className={cn("text-sm mt-1", t.text.secondary)}>View all product requests</p>
              </div>
            </div>
          </Link>
        </div>
      </div>
    </>
  );
}