import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { UserWithRole } from '@/types/roles';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import GlassCard from '@/Components/ui/GlassCard';
import MetricCard from '@/Components/ui/MetricCard';
import { FiDollarSign, FiUsers, FiTrendingUp, FiUserCheck } from 'react-icons/fi';

interface MscRepDashboardProps {
  user?: UserWithRole;
}

// Sales-focused data for MSC Representatives
const salesMetrics = {
  monthlyCommission: 18450.00,
  quarterlyTarget: 85000.00,
  monthlyTarget: 28333.00,
  territoryCustomers: 23,
  activeDeals: 8,
  teamSubReps: 3
};

const commissionSummary = [
  {
    id: 'CS-001',
    period: 'January 2024',
    baseCommission: 15200.00,
    bonusCommission: 3250.00,
    totalCommission: 18450.00,
    status: 'pending_approval',
    dueDate: '2024-02-15'
  },
  {
    id: 'CS-002',
    period: 'December 2023',
    baseCommission: 14800.00,
    bonusCommission: 2150.00,
    totalCommission: 16950.00,
    status: 'paid',
    dueDate: '2024-01-15'
  },
  {
    id: 'CS-003',
    period: 'November 2023',
    baseCommission: 13600.00,
    bonusCommission: 1800.00,
    totalCommission: 15400.00,
    status: 'paid',
    dueDate: '2023-12-15'
  }
];

const territoryPerformance = [
  {
    id: 'TP-001',
    customer: 'Metro Health System',
    monthlyRevenue: 45200.00,
    lastOrder: '2024-01-14',
    status: 'active',
    relationship: 'excellent',
    potentialGrowth: 'high'
  },
  {
    id: 'TP-002',
    customer: 'City Medical Center',
    monthlyRevenue: 32800.00,
    lastOrder: '2024-01-12',
    status: 'active',
    relationship: 'good',
    potentialGrowth: 'medium'
  },
  {
    id: 'TP-003',
    customer: 'Valley Wound Clinic',
    monthlyRevenue: 18500.00,
    lastOrder: '2024-01-10',
    status: 'active',
    relationship: 'good',
    potentialGrowth: 'high'
  },
  {
    id: 'TP-004',
    customer: 'Regional Medical Group',
    monthlyRevenue: 28900.00,
    lastOrder: '2024-01-08',
    status: 'at_risk',
    relationship: 'fair',
    potentialGrowth: 'low'
  }
];

const subRepTeam = [
  {
    id: 'SR-001',
    name: 'Jennifer Martinez',
    territory: 'North Metro',
    monthlyCommission: 8200.00,
    customerCount: 8,
    performance: 'excellent',
    lastActivity: '2024-01-15 16:30'
  },
  {
    id: 'SR-002',
    name: 'David Thompson',
    territory: 'South Metro',
    monthlyCommission: 6800.00,
    customerCount: 6,
    performance: 'good',
    lastActivity: '2024-01-15 14:20'
  },
  {
    id: 'SR-003',
    name: 'Lisa Chen',
    territory: 'East Metro',
    monthlyCommission: 7150.00,
    customerCount: 7,
    performance: 'good',
    lastActivity: '2024-01-15 11:45'
  }
];

const activeOpportunities = [
  {
    id: 'AO-001',
    customer: 'Metro Health System',
    opportunity: 'Advanced Wound Matrix Expansion',
    estimatedValue: 85000.00,
    probability: 85,
    closeDate: '2024-02-15',
    stage: 'negotiation'
  },
  {
    id: 'AO-002',
    customer: 'City Medical Center',
    opportunity: 'Negative Pressure Therapy Program',
    estimatedValue: 62000.00,
    probability: 70,
    closeDate: '2024-02-28',
    stage: 'proposal'
  },
  {
    id: 'AO-003',
    customer: 'Valley Wound Clinic',
    opportunity: 'Compression Therapy Upgrade',
    estimatedValue: 35000.00,
    probability: 90,
    closeDate: '2024-01-30',
    stage: 'closing'
  }
];

export default function MscRepDashboard({ user }: MscRepDashboardProps) {
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

  const commissionProgress = (salesMetrics.monthlyCommission / salesMetrics.monthlyTarget) * 100;
  const quarterlyProgress = (salesMetrics.monthlyCommission / salesMetrics.quarterlyTarget) * 100;

  const getStatusColor = (status: string) => {
    const colors = theme === 'dark' ? {
      active: 'bg-green-500/20 text-green-300 border-green-500/30',
      paid: 'bg-green-500/20 text-green-300 border-green-500/30',
      excellent: 'bg-green-500/20 text-green-300 border-green-500/30',
      pending_approval: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
      good: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
      at_risk: 'bg-red-500/20 text-red-300 border-red-500/30',
      fair: 'bg-red-500/20 text-red-300 border-red-500/30'
    } : {
      active: 'bg-green-100 text-green-600',
      paid: 'bg-green-100 text-green-600',
      excellent: 'bg-green-100 text-green-600',
      pending_approval: 'bg-yellow-100 text-yellow-600',
      good: 'bg-yellow-100 text-yellow-600',
      at_risk: 'bg-red-100 text-red-600',
      fair: 'bg-red-100 text-red-600'
    };
    return colors[status] || (theme === 'dark' ? 'bg-white/10 text-white/60' : 'bg-gray-100 text-gray-600');
  };

  const getStageColor = (stage: string) => {
    const colors = theme === 'dark' ? {
      closing: 'bg-green-500/20 text-green-300 border-green-500/30',
      negotiation: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      proposal: 'bg-purple-500/20 text-purple-300 border-purple-500/30'
    } : {
      closing: 'bg-green-100 text-green-600',
      negotiation: 'bg-blue-100 text-blue-600',
      proposal: 'bg-purple-100 text-purple-600'
    };
    return colors[stage] || (theme === 'dark' ? 'bg-white/10 text-white/60' : 'bg-gray-100 text-gray-600');
  };

  return (
    <MainLayout>
      <Head title="MSC Rep Dashboard" />

      <div className="space-y-6">
      <div className="max-w-7xl mx-auto space-y-4 sm:space-y-6">
        {/* Header - Mobile Optimized */}
        <div className="pt-4 sm:pt-6 pb-2 sm:pb-4">
          <h1 className={cn("text-2xl sm:text-3xl font-bold", t.text.primary)}>Sales Territory Dashboard</h1>
          <p className={cn("mt-1 sm:mt-2 text-sm sm:text-base leading-relaxed", t.text.secondary)}>
            Manage your territory, track commission performance, oversee sub-representatives, and drive customer growth.
          </p>
        </div>

        {/* Key Sales Metrics - Mobile First Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
          <MetricCard
            title="Monthly Commission"
            value={`$${salesMetrics.monthlyCommission.toLocaleString()}`}
            subtitle={`${commissionProgress.toFixed(1)}% of $${salesMetrics.monthlyTarget.toLocaleString()}`}
            icon={<FiDollarSign className="h-8 w-8" />}
            status="success"
            trend={commissionProgress > 80 ? 15 : -5}
            size="sm"
          />

          <MetricCard
            title="Territory Customers"
            value={salesMetrics.territoryCustomers}
            subtitle="Active accounts"
            icon={<FiUsers className="h-8 w-8" />}
            status="info"
            size="sm"
          />

          <MetricCard
            title="Active Deals"
            value={salesMetrics.activeDeals}
            subtitle="In pipeline"
            icon={<FiTrendingUp className="h-8 w-8" />}
            status="warning"
            size="sm"
          />

          <MetricCard
            title="Sub-Reps"
            value={salesMetrics.teamSubReps}
            subtitle="Team members"
            icon={<FiUserCheck className="h-8 w-8" />}
            status="default"
            size="sm"
          />
        </div>

        {/* Commission Tracking - Mobile Optimized */}
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
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Commission Tracking</h2>
                  <p className="text-sm text-gray-600 mt-1">Monthly commission statements and payment status</p>
                </div>
              </div>
              <Link
                href="/sales/commission/detailed"
                className="w-full sm:w-auto px-4 py-2 bg-green-600 text-white text-center rounded-md hover:bg-green-700 transition-colors"
              >
                View Detailed Report
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {commissionSummary.map((commission) => (
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

        {/* Active Opportunities Pipeline - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-amber-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Active Opportunities Pipeline</h2>
                  <p className="text-sm text-gray-600 mt-1">Current sales opportunities and deal progression</p>
                </div>
              </div>
              <Link
                href="/sales/opportunities"
                className="w-full sm:w-auto px-4 py-2 bg-amber-600 text-white text-center rounded-md hover:bg-amber-700 transition-colors"
              >
                Manage Pipeline
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {activeOpportunities.map((opportunity) => (
              <div key={opportunity.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between space-y-3 lg:space-y-0">
                  <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{opportunity.customer}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStageColor(opportunity.stage)}`}>
                        {opportunity.stage}
                      </span>
                    </div>
                    <p className="text-sm text-purple-700 font-medium mt-1">{opportunity.opportunity}</p>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Estimated Value</p>
                        <p className="text-lg font-bold text-green-600">${opportunity.estimatedValue.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Probability</p>
                        <p className="text-sm font-medium">{opportunity.probability}%</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Expected Close</p>
                        <p className="text-sm font-medium">{opportunity.closeDate}</p>
                      </div>
                    </div>
                  </div>
                  <Link
                    href={`/sales/opportunities/${opportunity.id}`}
                    className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-blue-600 text-white text-sm text-center rounded-md hover:bg-blue-700 transition-colors"
                  >
                    Manage Deal
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Territory Performance - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-blue-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Territory Customer Performance</h2>
                  <p className="text-sm text-gray-600 mt-1">Customer relationships and revenue tracking</p>
                </div>
              </div>
              <Link
                href="/sales/customers"
                className="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
              >
                Customer Management
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {territoryPerformance.map((customer) => (
              <div key={customer.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                  <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{customer.customer}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.status)}`}>
                        {customer.status.replace('_', ' ')}
                      </span>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.relationship)}`}>
                        {customer.relationship} relationship
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-4 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Monthly Revenue</p>
                        <p className="text-lg font-bold text-green-600">${customer.monthlyRevenue.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Last Order</p>
                        <p className="text-sm font-medium">{customer.lastOrder}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Growth Potential</p>
                        <p className="text-sm font-medium">{customer.potentialGrowth}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Relationship</p>
                        <p className="text-sm font-medium">{customer.relationship}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Sub-Representative Team Management - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-purple-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Sub-Representative Team</h2>
                  <p className="text-sm text-gray-600 mt-1">Manage and coordinate your sub-representative team</p>
                </div>
              </div>
              <Link
                href="/sales/team/subreps"
                className="w-full sm:w-auto px-4 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors"
              >
                Team Management
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {subRepTeam.map((subRep) => (
              <div key={subRep.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                  <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{subRep.name}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(subRep.performance)}`}>
                        {subRep.performance}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600">{subRep.territory}</p>
                    <div className="mt-1 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Monthly Commission</p>
                        <p className="text-sm font-medium">${subRep.monthlyCommission.toLocaleString()}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Customers</p>
                        <p className="text-sm font-medium">{subRep.customerCount}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Last Activity</p>
                        <p className="text-sm font-medium">{subRep.lastActivity}</p>
                      </div>
                    </div>
                  </div>
                  <Link
                    href={`/sales/team/subreps/${subRep.id}`}
                    className="w-full sm:w-auto sm:ml-4 px-3 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors"
                  >
                    View Performance
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
      </div>
    </MainLayout>
  );
}
