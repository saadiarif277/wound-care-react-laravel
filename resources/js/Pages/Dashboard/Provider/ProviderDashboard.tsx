import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { PricingDisplay, OrderTotalDisplay } from '@/Components/Pricing/PricingDisplay';

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
  clinical_opportunities?: any[];
  eligibility_status?: any[];
}

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
}

interface ProviderDashboardProps {
  user: User;
  dashboardData: DashboardData;
  roleRestrictions: RoleRestrictions;
}

// Static data for clinical opportunities (this would come from the clinical engine)
const clinicalOpportunities = [
  {
    id: 'CO-001',
    type: 'Compression Therapy',
    patient: 'Maria Garcia',
    description: 'Patient may benefit from compression therapy for venous ulcer management',
    priority: 'medium',
    estimatedValue: 450.00,
    hcpcsCode: 'A6545'
  },
  {
    id: 'CO-002',
    type: 'Negative Pressure Therapy',
    patient: 'John Doe',
    description: 'Consider NPWT for complex diabetic foot ulcer',
    priority: 'high',
    estimatedValue: 1250.00,
    hcpcsCode: 'E2402'
  }
];

// Static eligibility status data (this would come from payer integrations)
const eligibilityStatus = [
  {
    payer: 'Medicare',
    status: 'verified',
    lastUpdated: '2024-01-15',
    coverage: 'Active',
    deductible: 'Met'
  },
  {
    payer: 'Aetna',
    status: 'verified',
    lastUpdated: '2024-01-14',
    coverage: 'Active',
    deductible: 'Partial'
  }
];

export default function ProviderDashboard({ user, dashboardData, roleRestrictions }: ProviderDashboardProps) {
  const getStatusColor = (status: string) => {
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

  return (
    <MainLayout>
      <Head title="Provider Dashboard" />

      <div className="space-y-6">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Clinical Dashboard</h1>
          <p className="mt-2 text-gray-600 leading-normal">
            Streamline your wound care workflows with intelligent product recommendations, eligibility verification, and clinical opportunities.
          </p>
        </div>

        {/* Key Clinical Metrics */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Total Requests</h3>
            <p className="text-2xl sm:text-3xl font-bold text-blue-600 mt-1 sm:mt-2">{dashboardData.metrics.total_requests}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">All time</p>
          </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Pending Requests</h3>
            <p className="text-2xl sm:text-3xl font-bold text-amber-600 mt-1 sm:mt-2">{dashboardData.metrics.pending_requests}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">Require attention</p>
          </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Approved Requests</h3>
            <p className="text-2xl sm:text-3xl font-bold text-green-600 mt-1 sm:mt-2">{dashboardData.metrics.approved_requests}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">This month</p>
          </div>

          {/* Financial metrics - only show if role allows */}
          {roleRestrictions.can_view_financials && dashboardData.metrics.total_amount_owed !== undefined ? (
            <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
              <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Amount Owed</h3>
              <p className="text-2xl sm:text-3xl font-bold text-purple-600 mt-1 sm:mt-2">
                ${dashboardData.metrics.total_amount_owed.toFixed(2)}
              </p>
              <p className="text-xs text-gray-600 mt-1 sm:mt-2">Outstanding</p>
            </div>
          ) : (
            <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
              <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Clinical Opportunities</h3>
              <p className="text-2xl sm:text-3xl font-bold text-purple-600 mt-1 sm:mt-2">{clinicalOpportunities.length}</p>
              <p className="text-xs text-gray-600 mt-1 sm:mt-2">Identified</p>
            </div>
          )}
        </div>

        {/* Action Required Items */}
        {dashboardData.action_items.length > 0 && (
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-4 sm:p-6 border-b border-gray-200">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <svg className="h-5 w-5 sm:h-6 sm:w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Action Required</h2>
                    <p className="text-sm text-gray-600 mt-1">Items that need your immediate attention</p>
                  </div>
                </div>
                <Link
                  href="/product-requests"
                  className="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 transition-colors text-center"
                >
                  View All
                </Link>
              </div>
            </div>
            <div className="divide-y divide-gray-200">
              {dashboardData.action_items.map((item) => (
                <div key={item.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                    <div className="flex-1">
                      <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                        <h3 className="text-sm font-semibold text-gray-900">{item.patient_name}</h3>
                        <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(item.priority)}`}>
                          {item.priority} priority
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                      <p className="text-xs text-gray-500 mt-1">Due: {item.due_date}</p>
                    </div>
                    <Link
                      href={`/product-requests/${item.id}`}
                      className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
                    >
                      Take Action
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Recent Patient Requests */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Recent Patient Requests</h2>
                  <p className="text-sm text-gray-600 mt-1">Latest wound care product requests and their status</p>
                </div>
              </div>
              <Link
                href="/product-requests"
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-center"
              >
                View All Requests
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {dashboardData.recent_requests.length > 0 ? (
              dashboardData.recent_requests.map((request) => (
                <div key={request.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                    <div className="flex-1">
                      <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                        <h3 className="text-sm font-semibold text-gray-900">{request.patient_name}</h3>
                        <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(request.status)}`}>
                          {request.status.replace('_', ' ')}
                        </span>
                      </div>
                      <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">Wound Type</p>
                          <p className="text-sm font-medium">{request.wound_type.replace('_', ' ')}</p>
                        </div>
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">Request Date</p>
                          <p className="text-sm font-medium">{request.created_at}</p>
                        </div>
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">Facility</p>
                          <p className="text-sm font-medium">{request.facility_name}</p>
                        </div>
                      </div>

                      {/* Financial information - only show if role allows */}
                      {roleRestrictions.can_view_financials && request.total_amount && (
                        <div className="mt-2 p-3 bg-blue-50 rounded-lg">
                          <OrderTotalDisplay
                            roleRestrictions={roleRestrictions}
                            total={request.total_amount}
                            amountOwed={request.amount_owed}
                            className="text-sm"
                          />
                        </div>
                      )}
                    </div>
                    <Link
                      href={`/product-requests/${request.id}`}
                      className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
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

        {/* Clinical Opportunities Engine */}
        {clinicalOpportunities.length > 0 && (
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-4 sm:p-6 border-b border-gray-200">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <svg className="h-5 w-5 sm:h-6 sm:w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Clinical Opportunities</h2>
                    <p className="text-sm text-gray-600 mt-1">AI-powered recommendations for additional services</p>
                  </div>
                </div>
              </div>
            </div>
            <div className="divide-y divide-gray-200">
              {clinicalOpportunities.map((opportunity) => (
                <div key={opportunity.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                    <div className="flex-1">
                      <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                        <h3 className="text-sm font-semibold text-gray-900">{opportunity.type}</h3>
                        <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(opportunity.priority)}`}>
                          {opportunity.priority} priority
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">{opportunity.description}</p>
                      <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">Patient</p>
                          <p className="text-sm font-medium">{opportunity.patient}</p>
                        </div>
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">HCPCS Code</p>
                          <p className="text-sm font-medium">{opportunity.hcpcsCode}</p>
                        </div>
                        {roleRestrictions.can_view_financials && (
                          <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                            <p className="text-xs text-gray-500">Estimated Value</p>
                            <p className="text-sm font-medium text-green-600">${opportunity.estimatedValue.toFixed(2)}</p>
                          </div>
                        )}
                      </div>
                    </div>
                    <button className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors">
                      Add to Request
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Eligibility Status */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 sm:h-6 sm:w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Payer Eligibility Status</h2>
                <p className="text-sm text-gray-600 mt-1">Real-time insurance verification status</p>
              </div>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {eligibilityStatus.map((payer, index) => (
              <div key={index} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{payer.payer}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        payer.status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                      }`}>
                        {payer.status}
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Coverage</p>
                        <p className="text-sm font-medium">{payer.coverage}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Deductible</p>
                        <p className="text-sm font-medium">{payer.deductible}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Last Updated</p>
                        <p className="text-sm font-medium">{payer.lastUpdated}</p>
                      </div>
                    </div>
                  </div>
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

        {/* Debug Information - Remove in production */}
        {process.env.NODE_ENV === 'development' && (
          <div className="bg-gray-100 border border-gray-300 rounded-lg p-4">
            <h3 className="text-sm font-medium text-gray-800 mb-2">Debug Information (Development Only)</h3>
            <div className="text-xs text-gray-600 space-y-1">
              <p><strong>User Role:</strong> {user.role} ({user.role_display_name})</p>
              <p><strong>Can View Financials:</strong> {roleRestrictions.can_view_financials ? 'Yes' : 'No'}</p>
              <p><strong>Can See Discounts:</strong> {roleRestrictions.can_see_discounts ? 'Yes' : 'No'}</p>
              <p><strong>Can See MSC Pricing:</strong> {roleRestrictions.can_see_msc_pricing ? 'Yes' : 'No'}</p>
              <p><strong>Can See Order Totals:</strong> {roleRestrictions.can_see_order_totals ? 'Yes' : 'No'}</p>
              <p><strong>Pricing Access Level:</strong> {roleRestrictions.pricing_access_level}</p>
              <p><strong>Recent Requests Count:</strong> {dashboardData.recent_requests.length}</p>
              <p><strong>Action Items Count:</strong> {dashboardData.action_items.length}</p>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
}
