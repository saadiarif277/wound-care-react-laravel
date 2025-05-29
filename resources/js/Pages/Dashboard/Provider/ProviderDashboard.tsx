import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { PricingDisplay, OrderTotalDisplay } from '@/Components/Pricing/PricingDisplay';
import { RoleRestrictions, UserWithRole } from '@/types/roles';
import { FiPlus, FiTrendingUp, FiClock, FiCheck, FiAlertTriangle, FiUser, FiFileText } from 'react-icons/fi';

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

export default function ProviderDashboard({ user, dashboardData, roleRestrictions }: ProviderDashboardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'draft': return 'bg-gray-100 text-gray-800';
      case 'submitted': return 'bg-blue-100 text-blue-800';
      case 'approved': return 'bg-green-100 text-green-800';
      case 'rejected': return 'bg-red-100 text-red-800';
      case 'processing': return 'bg-yellow-100 text-yellow-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high': return 'bg-red-100 text-red-800';
      case 'medium': return 'bg-yellow-100 text-yellow-800';
      case 'low': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getEligibilityStatusColor = (status: string) => {
    switch (status) {
      case 'verified': return 'bg-green-100 text-green-800';
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'expired': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  return (
    <MainLayout>
      <Head title="Provider Dashboard" />

      <div className="space-y-6">
        {/* Provider Welcome Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Welcome, {user.name}</h1>
          <p className="mt-2 text-gray-600 leading-normal">
            Manage your wound care product requests, track patient outcomes, and access clinical decision support tools.
          </p>
        </div>

        {/* Key Metrics */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Total Requests</h3>
            <p className="text-3xl font-bold text-blue-600 mt-2">{dashboardData.metrics.total_requests}</p>
            <p className="text-xs text-gray-600 mt-2">All time</p>
          </div>

          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Pending Requests</h3>
            <p className="text-3xl font-bold text-yellow-600 mt-2">{dashboardData.metrics.pending_requests}</p>
            <p className="text-xs text-gray-600 mt-2">Awaiting processing</p>
          </div>

          <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Approved Requests</h3>
            <p className="text-3xl font-bold text-green-600 mt-2">{dashboardData.metrics.approved_requests}</p>
            <p className="text-xs text-gray-600 mt-2">Ready for delivery</p>
          </div>

          {roleRestrictions.can_view_financials && dashboardData.metrics.total_amount_owed && (
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Total Value</h3>
              <p className="text-3xl font-bold text-purple-600 mt-2">
                {formatCurrency(dashboardData.metrics.total_amount_owed)}
              </p>
              <p className="text-xs text-gray-600 mt-2">Approved orders</p>
            </div>
          )}
        </div>

        {/* Action Items */}
        {dashboardData.action_items && dashboardData.action_items.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-6 border-b border-gray-200 bg-amber-50">
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <FiAlertTriangle className="h-6 w-6 text-amber-600" />
                  </div>
                  <div className="ml-3">
                    <h2 className="text-xl font-semibold text-gray-900">Action Required</h2>
                    <p className="text-sm text-gray-600 mt-1">Items that need your immediate attention</p>
                  </div>
                </div>
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
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiFileText className="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" />
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
            {dashboardData.recent_requests && dashboardData.recent_requests.length > 0 ? (
              dashboardData.recent_requests.map((request) => (
                <div key={request.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                    <div className="flex-1">
                      <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                        <h3 className="text-sm font-semibold text-gray-900">{request.patient_name}</h3>
                        <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(request.status)}`}>
                          {request.status.replace('_', ' ').toUpperCase()}
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">
                        {request.wound_type} - {request.facility_name}
                      </p>
                      <div className="flex flex-col sm:flex-row sm:items-center sm:space-x-4 mt-2 text-xs text-gray-500">
                        <span>Request #{request.request_number}</span>
                        <span>Created: {new Date(request.created_at).toLocaleDateString()}</span>
                        {roleRestrictions.can_view_financials && request.total_amount && (
                          <span>Value: {formatCurrency(request.total_amount)}</span>
                        )}
                      </div>
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
                  <FiPlus className="mr-2 h-4 w-4" />
                  Create New Request
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* Clinical Opportunities (if available from backend) */}
        {dashboardData.clinical_opportunities && dashboardData.clinical_opportunities.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiTrendingUp className="h-6 w-6 text-purple-600" />
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Clinical Opportunities</h2>
                  <p className="text-sm text-gray-600 mt-1">AI-identified opportunities for enhanced patient care</p>
                </div>
              </div>
            </div>
            <div className="divide-y divide-gray-200">
              {dashboardData.clinical_opportunities.map((opportunity) => (
                <div key={opportunity.id} className="p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center">
                        <h3 className="text-sm font-semibold text-gray-900">{opportunity.patient}</h3>
                        <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(opportunity.priority)}`}>
                          {opportunity.priority} priority
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">{opportunity.description}</p>
                      <div className="flex items-center mt-2 text-xs text-gray-500">
                        <span>{opportunity.type}</span>
                        {opportunity.hcpcs_code && <span className="ml-4">HCPCS: {opportunity.hcpcs_code}</span>}
                        {roleRestrictions.can_view_financials && opportunity.estimated_value && (
                          <span className="ml-4">Est. Value: {formatCurrency(opportunity.estimated_value)}</span>
                        )}
                      </div>
                    </div>
                    <button className="ml-4 px-3 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                      Review
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Insurance Eligibility Status (if available from backend) */}
        {dashboardData.eligibility_status && dashboardData.eligibility_status.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <FiCheck className="h-6 w-6 text-green-600" />
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Insurance Eligibility Status</h2>
                  <p className="text-sm text-gray-600 mt-1">Real-time payer connectivity and eligibility verification</p>
                </div>
              </div>
            </div>
            <div className="p-6">
              <div className="grid gap-4 md:grid-cols-2">
                {dashboardData.eligibility_status.map((status, index) => (
                  <div key={index} className="border border-gray-200 rounded-lg p-4">
                    <div className="flex items-center justify-between">
                      <h3 className="text-sm font-semibold text-gray-900">{status.payer}</h3>
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${getEligibilityStatusColor(status.status)}`}>
                        {status.status}
                      </span>
                    </div>
                    <div className="mt-2 text-xs text-gray-600">
                      <p>Coverage: {status.coverage}</p>
                      <p>Deductible: {status.deductible}</p>
                      <p>Last Updated: {new Date(status.last_updated).toLocaleDateString()}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Quick Actions */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
          <div className="grid gap-4 md:grid-cols-3">
            <Link
              href="/product-requests/create"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <FiPlus className="h-6 w-6 text-blue-600 mr-3" />
              <div>
                <h3 className="text-sm font-semibold text-gray-900">New Product Request</h3>
                <p className="text-xs text-gray-600">Submit wound care product request</p>
              </div>
            </Link>

            <Link
              href="/eligibility"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <FiCheck className="h-6 w-6 text-green-600 mr-3" />
              <div>
                <h3 className="text-sm font-semibold text-gray-900">Check Eligibility</h3>
                <p className="text-xs text-gray-600">Verify patient insurance coverage</p>
              </div>
            </Link>

            <Link
              href="/product-requests"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <FiUser className="h-6 w-6 text-purple-600 mr-3" />
              <div>
                <h3 className="text-sm font-semibold text-gray-900">Patient Management</h3>
                <p className="text-xs text-gray-600">View all patient requests</p>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
