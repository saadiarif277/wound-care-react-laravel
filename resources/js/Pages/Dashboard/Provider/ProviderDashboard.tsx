import React from 'react';
import { Link } from '@inertiajs/react';
import { UserWithRole } from '@/types/roles';

interface ProviderDashboardProps {
  user?: UserWithRole;
}

// Clinical-focused data for Healthcare Providers
const clinicalMetrics = {
  activePatients: 34,
  pendingRequests: 8,
  eligibilitySuccessRate: 92.5,
  clinicalOpportunities: 6
};

const clinicalOpportunities = [
  {
    id: 'CO-001',
    patientName: 'Robert Johnson',
    opportunity: 'Consider Offloading DME L4631',
    rationale: 'Wagner Grade 3 DFU detected - offloading recommended for optimal healing',
    potentialOutcome: 'Improved healing outcomes',
    urgency: 'high',
    recommendedAction: 'Schedule DME evaluation'
  },
  {
    id: 'CO-002',
    patientName: 'Sarah Williams',
    opportunity: 'Advanced Wound Matrix Therapy',
    rationale: 'Chronic wound >12 weeks with poor healing response to standard care',
    potentialOutcome: 'Enhanced healing potential',
    urgency: 'medium',
    recommendedAction: 'Consider biological matrix'
  },
  {
    id: 'CO-003',
    patientName: 'Michael Chen',
    opportunity: 'Compression Therapy Optimization',
    rationale: 'Venous ulcer with suboptimal compression management',
    potentialOutcome: 'Reduced healing time',
    urgency: 'medium',
    recommendedAction: 'Evaluate compression levels'
  }
];

const recentPatientRequests = [
  {
    id: 'PR-2024-001',
    patientName: 'John Doe',
    patientInitials: 'JD',
    woundType: 'Diabetic Foot Ulcer',
    requestType: 'Advanced Dressing',
    status: 'pending_eligibility',
    requestDate: '2024-01-15',
    priority: 'high',
    nextAction: 'Eligibility verification in progress'
  },
  {
    id: 'PR-2024-002',
    patientName: 'Jane Smith',
    patientInitials: 'JS',
    woundType: 'Pressure Ulcer',
    requestType: 'Negative Pressure Therapy',
    status: 'approved',
    requestDate: '2024-01-14',
    priority: 'medium',
    nextAction: 'Ready for delivery coordination'
  },
  {
    id: 'PR-2024-003',
    patientName: 'Maria Garcia',
    patientInitials: 'MG',
    woundType: 'Venous Ulcer',
    requestType: 'Compression System',
    status: 'pending_documentation',
    requestDate: '2024-01-13',
    priority: 'medium',
    nextAction: 'Upload wound assessment photos'
  },
  {
    id: 'PR-2024-004',
    patientName: 'David Brown',
    patientInitials: 'DB',
    woundType: 'Surgical Site',
    requestType: 'Antimicrobial Dressing',
    status: 'in_review',
    requestDate: '2024-01-12',
    priority: 'low',
    nextAction: 'Clinical review pending'
  }
];

const actionRequiredItems = [
  {
    id: 'AR-001',
    type: 'documentation_required',
    patientName: 'John Doe',
    description: 'Wound assessment photos needed for advanced dressing approval',
    priority: 'high',
    dueDate: '2024-01-20',
    requestId: 'PR-2024-001'
  },
  {
    id: 'AR-002',
    type: 'pa_approval',
    patientName: 'Lisa Martinez',
    description: 'Prior authorization required for negative pressure therapy',
    priority: 'medium',
    dueDate: '2024-01-22',
    requestId: 'PR-2024-005'
  },
  {
    id: 'AR-003',
    type: 'eligibility_issue',
    patientName: 'William Taylor',
    description: 'Insurance eligibility requires verification',
    priority: 'medium',
    dueDate: '2024-01-21',
    requestId: 'PR-2024-006'
  }
];

const eligibilityStatus = [
  {
    payerName: 'Medicare',
    totalChecks: 145,
    successfulChecks: 138,
    successRate: 95.2,
    lastUpdated: '2024-01-15 14:30'
  },
  {
    payerName: 'Blue Cross Blue Shield',
    totalChecks: 89,
    successfulChecks: 81,
    successRate: 91.0,
    lastUpdated: '2024-01-15 14:15'
  },
  {
    payerName: 'Aetna',
    totalChecks: 56,
    successfulChecks: 50,
    successRate: 89.3,
    lastUpdated: '2024-01-15 13:45'
  },
  {
    payerName: 'United Healthcare',
    totalChecks: 72,
    successfulChecks: 65,
    successRate: 90.3,
    lastUpdated: '2024-01-15 14:00'
  }
];

export default function ProviderDashboard({ user }: ProviderDashboardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
        return 'text-green-600 bg-green-100';
      case 'pending_eligibility':
      case 'pending_documentation':
        return 'text-yellow-600 bg-yellow-100';
      case 'in_review':
        return 'text-blue-600 bg-blue-100';
      case 'denied':
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

  const getUrgencyColor = (urgency: string) => {
    switch (urgency) {
      case 'high':
        return 'text-red-600 bg-red-100';
      case 'medium':
        return 'text-yellow-600 bg-yellow-100';
      case 'low':
        return 'text-green-600 bg-green-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Provider Clinical Dashboard</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          Manage patient care, track requests, and access intelligent wound care recommendations to optimize patient outcomes.
        </p>
      </div>

      {/* Key Clinical Metrics */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Patients</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{clinicalMetrics.activePatients}</p>
          <p className="text-xs text-gray-600 mt-2">Under care</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Pending Requests</h3>
          <p className="text-3xl font-bold text-amber-600 mt-2">{clinicalMetrics.pendingRequests}</p>
          <p className="text-xs text-gray-600 mt-2">Require attention</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Eligibility Success</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">{clinicalMetrics.eligibilitySuccessRate}%</p>
          <p className="text-xs text-gray-600 mt-2">Verification rate</p>
        </div>

        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Clinical Opportunities</h3>
          <p className="text-3xl font-bold text-purple-600 mt-2">{clinicalMetrics.clinicalOpportunities}</p>
          <p className="text-xs text-gray-600 mt-2">AI insights available</p>
        </div>
      </div>

      {/* Action Required Items */}
      {actionRequiredItems.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-amber-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Action Required</h2>
                  <p className="text-sm text-gray-600 mt-1">Patient requests needing your immediate attention</p>
                </div>
              </div>
              <Link
                href="/product-requests"
                className="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 transition-colors"
              >
                View All Requests
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {actionRequiredItems.map((item) => (
              <div key={item.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{item.patientName}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(item.priority)}`}>
                        {item.priority} priority
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                    <p className="text-xs text-gray-500 mt-2">Due: {item.dueDate}</p>
                  </div>
                  <Link
                    href={`/product-requests/${item.requestId}`}
                    className="ml-4 inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                  >
                    Take Action
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Quick Clinical Actions */}
      <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Quick Clinical Actions</h2>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Link
            href="/product-requests/create"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span className="text-sm font-medium text-gray-900">New Patient Request</span>
          </Link>

          <Link
            href="/eligibility/check"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Check Eligibility</span>
          </Link>

          <Link
            href="/products"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-purple-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Browse Products</span>
          </Link>

          <Link
            href="/orders/track"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2V9a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Track Orders</span>
          </Link>
        </div>
      </div>

      {/* Clinical Opportunities Widget */}
      {clinicalOpportunities.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-purple-50">
            <div className="flex items-center justify-between">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-xl font-semibold text-gray-900">Clinical Opportunities</h2>
                  <p className="text-sm text-gray-600 mt-1">AI-powered recommendations to enhance patient care</p>
                </div>
              </div>
              <Link
                href="/clinical-insights"
                className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors"
              >
                View All Insights
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {clinicalOpportunities.map((opportunity) => (
              <div key={opportunity.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{opportunity.patientName}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getUrgencyColor(opportunity.urgency)}`}>
                        {opportunity.urgency} urgency
                      </span>
                    </div>
                    <p className="text-sm font-medium text-purple-700 mt-1">{opportunity.opportunity}</p>
                    <p className="text-sm text-gray-600 mt-1">{opportunity.rationale}</p>
                    <div className="mt-2 flex items-center space-x-4">
                      <div>
                        <p className="text-xs text-gray-500">Expected Outcome</p>
                        <p className="text-sm font-medium">{opportunity.potentialOutcome}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Recommended Action</p>
                        <p className="text-sm font-medium">{opportunity.recommendedAction}</p>
                      </div>
                    </div>
                  </div>
                  <button className="ml-4 px-3 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition-colors">
                    Act on Insight
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Recent Patient Requests */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Recent Patient Requests</h2>
                <p className="text-sm text-gray-600 mt-1">Track and manage your patient product requests</p>
              </div>
            </div>
            <Link
              href="/product-requests"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              View All
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {recentPatientRequests.map((request) => (
            <div key={request.id} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4">
                  <div className="flex-shrink-0">
                    <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                      <span className="text-white text-sm font-medium">{request.patientInitials}</span>
                    </div>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{request.patientName}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(request.status)}`}>
                        {request.status.replace('_', ' ')}
                      </span>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(request.priority)}`}>
                        {request.priority}
                      </span>
                    </div>
                    <div className="mt-1">
                      <p className="text-sm text-gray-600">{request.woundType} - {request.requestType}</p>
                      <p className="text-xs text-gray-500 mt-1">
                        Requested: {request.requestDate} | Next: {request.nextAction}
                      </p>
                    </div>
                  </div>
                </div>
                <Link
                  href={`/product-requests/${request.id}`}
                  className="ml-4 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                >
                  View Details
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Eligibility Status Overview */}
      <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Eligibility Status Overview</h2>
                <p className="text-sm text-gray-600 mt-1">Insurance verification success rates by payer</p>
              </div>
            </div>
            <Link
              href="/eligibility"
              className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
              Check Eligibility
            </Link>
          </div>
        </div>
        <div className="divide-y divide-gray-200">
          {eligibilityStatus.map((payer, index) => (
            <div key={index} className="p-6 hover:bg-gray-50 transition-colors">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-900">{payer.payerName}</h3>
                    <span className="text-sm font-bold text-green-600">{payer.successRate}%</span>
                  </div>
                  <div className="mt-2">
                    <div className="flex items-center justify-between text-xs text-gray-500">
                      <span>Success Rate</span>
                      <span>{payer.successfulChecks} of {payer.totalChecks} successful</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                      <div
                        className="bg-green-600 h-2 rounded-full"
                        style={{ width: `${payer.successRate}%` }}
                      ></div>
                    </div>
                  </div>
                  <p className="text-xs text-gray-500 mt-2">Last updated: {payer.lastUpdated}</p>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
