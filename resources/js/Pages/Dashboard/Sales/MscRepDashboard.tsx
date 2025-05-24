import React from 'react';
import { Link } from '@inertiajs/react';
import { UserWithRole } from '@/types/roles';

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
  const commissionProgress = (salesMetrics.monthlyCommission / salesMetrics.monthlyTarget) * 100;
  const quarterlyProgress = (salesMetrics.monthlyCommission / salesMetrics.quarterlyTarget) * 100;

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
      case 'paid':
      case 'excellent':
        return 'text-green-600 bg-green-100';
      case 'pending_approval':
      case 'good':
        return 'text-yellow-600 bg-yellow-100';
      case 'at_risk':
      case 'fair':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getStageColor = (stage: string) => {
    switch (stage) {
      case 'closing':
        return 'text-green-600 bg-green-100';
      case 'negotiation':
        return 'text-blue-600 bg-blue-100';
      case 'proposal':
        return 'text-purple-600 bg-purple-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Sales Territory Dashboard</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          Manage your territory, track commission performance, oversee sub-representatives, and drive customer growth.
        </p>
      </div>

      {/* Key Sales Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Monthly Commission</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">${salesMetrics.monthlyCommission.toLocaleString()}</p>
          <div className="mt-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-600">{commissionProgress.toFixed(1)}% of target</span>
              <span className="text-gray-600">${salesMetrics.monthlyTarget.toLocaleString()}</span>
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
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Territory Customers</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{salesMetrics.territoryCustomers}</p>
          <p className="text-xs text-gray-600 mt-2">Active accounts</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Deals</h3>
          <p className="text-3xl font-bold text-amber-600 mt-2">{salesMetrics.activeDeals}</p>
          <p className="text-xs text-gray-600 mt-2">In pipeline</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Sub-Reps</h3>
          <p className="text-3xl font-bold text-purple-600 mt-2">{salesMetrics.teamSubReps}</p>
          <p className="text-xs text-gray-600 mt-2">Team members</p>
        </div>
      </div>

      {/* Commission Tracking */}
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
                <h2 className="text-xl font-semibold text-gray-900">Commission Tracking</h2>
                <p className="text-sm text-gray-600 mt-1">Monthly commission statements and payment status</p>
              </div>
            </div>
            <Link
              href="/sales/commission/detailed"
              className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
              View Detailed Report
            </Link>
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
                  </div>
                  <div className="mt-2 grid grid-cols-3 gap-4">
                    <div>
                      <p className="text-xs text-gray-500">Base Commission</p>
                      <p className="text-sm font-medium">${commission.baseCommission.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Bonus Commission</p>
                      <p className="text-sm font-medium">${commission.bonusCommission.toLocaleString()}</p>
                    </div>
                    <div>
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

      {/* Active Opportunities Pipeline */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-amber-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Active Opportunities Pipeline</h2>
                <p className="text-sm text-gray-600 mt-1">Current sales opportunities and deal progression</p>
              </div>
            </div>
            <Link
              href="/sales/opportunities"
              className="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 transition-colors"
            >
              Manage Pipeline
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {activeOpportunities.map((opportunity) => (
            <div key={opportunity.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{opportunity.customer}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStageColor(opportunity.stage)}`}>
                      {opportunity.stage}
                    </span>
                  </div>
                  <p className="text-sm text-purple-700 font-medium mt-1">{opportunity.opportunity}</p>
                  <div className="mt-2 grid grid-cols-3 gap-4">
                    <div>
                      <p className="text-xs text-gray-500">Estimated Value</p>
                      <p className="text-lg font-bold text-green-600">${opportunity.estimatedValue.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Probability</p>
                      <p className="text-sm font-medium">{opportunity.probability}%</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Expected Close</p>
                      <p className="text-sm font-medium">{opportunity.closeDate}</p>
                    </div>
                  </div>
                </div>
                <Link
                  href={`/sales/opportunities/${opportunity.id}`}
                  className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                >
                  Manage Deal
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Territory Performance */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-blue-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Territory Customer Performance</h2>
                <p className="text-sm text-gray-600 mt-1">Customer relationships and revenue tracking</p>
              </div>
            </div>
            <Link
              href="/sales/customers"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              Customer Management
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {territoryPerformance.map((customer) => (
            <div key={customer.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h3 className="text-sm font-semibold text-gray-900">{customer.customer}</h3>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.status)}`}>
                      {customer.status.replace('_', ' ')}
                    </span>
                    <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(customer.relationship)}`}>
                      {customer.relationship} relationship
                    </span>
                  </div>
                  <div className="mt-2 grid grid-cols-4 gap-4">
                    <div>
                      <p className="text-xs text-gray-500">Monthly Revenue</p>
                      <p className="text-lg font-bold text-green-600">${customer.monthlyRevenue.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Last Order</p>
                      <p className="text-sm font-medium">{customer.lastOrder}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Growth Potential</p>
                      <p className="text-sm font-medium">{customer.potentialGrowth}</p>
                    </div>
                    <div>
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

      {/* Sub-Representative Team Management */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200 bg-purple-50">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Sub-Representative Team</h2>
                <p className="text-sm text-gray-600 mt-1">Manage and coordinate your sub-representative team</p>
              </div>
            </div>
            <Link
              href="/sales/team/subreps"
              className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
            >
              Team Management
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {subRepTeam.map((subRep) => (
            <div key={subRep.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                  <div className="flex-shrink-0">
                    <div className="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                      <span className="text-white text-sm font-medium">
                        {subRep.name.split(' ').map(n => n[0]).join('')}
                      </span>
                    </div>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{subRep.name}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(subRep.performance)}`}>
                        {subRep.performance}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600">{subRep.territory}</p>
                    <div className="mt-1 grid grid-cols-3 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Monthly Commission</p>
                        <p className="text-sm font-medium">${subRep.monthlyCommission.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Customers</p>
                        <p className="text-sm font-medium">{subRep.customerCount}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Last Activity</p>
                        <p className="text-sm font-medium">{subRep.lastActivity}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <Link
                  href={`/sales/team/subreps/${subRep.id}`}
                  className="ml-4 px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition-colors"
                >
                  View Performance
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
