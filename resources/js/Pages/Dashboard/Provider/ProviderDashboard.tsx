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
    <div className="min-h-screen bg-gray-50 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-4 sm:space-y-6">
        {/* Header - Mobile Optimized */}
        <div className="pt-4 sm:pt-6 pb-2 sm:pb-4">
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Provider Dashboard</h1>
          <p className="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600 leading-relaxed">
            Streamline your wound care product requests, track patient outcomes, and access clinical intelligence.
          </p>
        </div>

        {/* Clinical Performance Metrics - Mobile First Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Patients</h3>
            <p className="text-2xl sm:text-3xl font-bold text-blue-600 mt-1 sm:mt-2">{clinicalMetrics.activePatients}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">Under care</p>
          </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Pending Requests</h3>
            <p className="text-2xl sm:text-3xl font-bold text-amber-600 mt-1 sm:mt-2">{clinicalMetrics.pendingRequests}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">Need attention</p>
          </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Eligibility Success</h3>
            <p className="text-2xl sm:text-3xl font-bold text-green-600 mt-1 sm:mt-2">{clinicalMetrics.eligibilitySuccessRate}%</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">This month</p>
          </div>

          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg p-4 sm:p-6 border border-gray-100">
            <h3 className="text-xs sm:text-sm font-semibold text-gray-900 uppercase tracking-wide">Clinical Opportunities</h3>
            <p className="text-2xl sm:text-3xl font-bold text-purple-600 mt-1 sm:mt-2">{clinicalMetrics.clinicalOpportunities}</p>
            <p className="text-xs text-gray-600 mt-1 sm:mt-2">Identified</p>
          </div>
        </div>

        {/* Action Required Items - Mobile Optimized */}
        {actionRequiredItems.length > 0 && (
          <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
            <div className="p-4 sm:p-6 border-b border-gray-200 bg-red-50">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Action Required</h2>
                  <p className="text-sm text-gray-600 mt-1">Items that need your immediate attention</p>
                </div>
              </div>
            </div>
            <div className="divide-y divide-gray-200">
              {actionRequiredItems.map((item) => (
                <div key={item.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                  <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                    <div className="flex-1">
                      <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                        <h3 className="text-sm font-semibold text-gray-900">{item.patientName}</h3>
                        <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(item.priority)}`}>
                          {item.priority} priority
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                      <p className="text-xs text-gray-500 mt-1">Due: {item.dueDate}</p>
                    </div>
                    <Link
                      href={`/product-requests/${item.requestId}`}
                      className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-red-600 text-white text-center rounded-md hover:bg-red-700 transition-colors"
                    >
                      Take Action
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Recent Patient Requests - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-blue-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Recent Patient Requests</h2>
                  <p className="text-sm text-gray-600 mt-1">Latest product requests and their current status</p>
                </div>
              </div>
              <Link
                href="/product-requests/create"
                className="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
              >
                New Request
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {recentPatientRequests.map((request) => (
              <div key={request.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-3 lg:space-y-0">
                  <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <div className="flex items-center space-x-3">
                        <div className="w-8 h-8 sm:w-10 sm:h-10 bg-blue-500 rounded-full flex items-center justify-center">
                          <span className="text-white text-xs sm:text-sm font-medium">{request.patientInitials}</span>
                        </div>
                        <h3 className="text-sm font-semibold text-gray-900">{request.patientName}</h3>
                      </div>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(request.status)}`}>
                        {request.status.replace('_', ' ')}
                      </span>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(request.priority)}`}>
                        {request.priority} priority
                      </span>
                    </div>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Wound Type</p>
                        <p className="text-sm font-medium">{request.woundType}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Request Type</p>
                        <p className="text-sm font-medium">{request.requestType}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Request Date</p>
                        <p className="text-sm font-medium">{request.requestDate}</p>
                      </div>
                    </div>
                    <p className="text-xs text-gray-500 mt-2">Next: {request.nextAction}</p>
                  </div>
                  <Link
                    href={`/product-requests/${request.id}`}
                    className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-blue-600 text-white text-center rounded-md hover:bg-blue-700 transition-colors"
                  >
                    View Request
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Clinical Opportunities Engine - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-purple-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Clinical Opportunities Engine</h2>
                  <p className="text-sm text-gray-600 mt-1">AI-driven recommendations for improved patient outcomes</p>
                </div>
              </div>
              <Link
                href="/clinical-opportunities"
                className="w-full sm:w-auto px-4 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors"
              >
                View All
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {clinicalOpportunities.map((opportunity) => (
              <div key={opportunity.id} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between space-y-3 lg:space-y-0">
                  <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0">
                      <h3 className="text-sm font-semibold text-gray-900">{opportunity.patientName}</h3>
                      <span className={`inline-flex sm:ml-2 px-2 py-1 text-xs font-medium rounded-full ${getUrgencyColor(opportunity.urgency)}`}>
                        {opportunity.urgency} urgency
                      </span>
                    </div>
                    <p className="text-sm text-purple-700 font-medium mt-1">{opportunity.opportunity}</p>
                    <div className="mt-2 space-y-2">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Clinical Rationale</p>
                        <p className="text-sm text-gray-700">{opportunity.rationale}</p>
                      </div>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">Potential Outcome</p>
                          <p className="text-sm font-medium text-green-600">{opportunity.potentialOutcome}</p>
                        </div>
                        <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                          <p className="text-xs text-gray-500">Recommended Action</p>
                          <p className="text-sm font-medium">{opportunity.recommendedAction}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <Link
                    href={`/clinical-opportunities/${opportunity.id}`}
                    className="w-full lg:w-auto lg:ml-4 px-3 py-2 bg-purple-600 text-white text-center rounded-md hover:bg-purple-700 transition-colors"
                  >
                    Act on Opportunity
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Eligibility Status Overview - Mobile Optimized */}
        <div className="bg-white rounded-lg sm:rounded-xl shadow-sm sm:shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-4 sm:p-6 border-b border-gray-200 bg-green-50">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <svg className="h-5 w-5 sm:h-6 sm:w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <div className="ml-3">
                  <h2 className="text-lg sm:text-xl font-semibold text-gray-900">Eligibility Status Overview</h2>
                  <p className="text-sm text-gray-600 mt-1">Real-time insurance eligibility verification performance</p>
                </div>
              </div>
              <Link
                href="/eligibility/detailed"
                className="w-full sm:w-auto px-4 py-2 bg-green-600 text-white text-center rounded-md hover:bg-green-700 transition-colors"
              >
                Check Eligibility
              </Link>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {eligibilityStatus.map((payer, index) => (
              <div key={index} className="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                  <div className="flex-1">
                    <h3 className="text-sm font-semibold text-gray-900">{payer.payerName}</h3>
                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-4 gap-3 sm:gap-4">
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Success Rate</p>
                        <div className="flex items-center space-x-2 mt-1">
                          <div className="flex-1 bg-gray-200 rounded-full h-2">
                            <div
                              className="bg-green-600 h-2 rounded-full transition-all duration-300"
                              style={{ width: `${payer.successRate}%` }}
                            ></div>
                          </div>
                          <span className="text-sm font-bold text-green-600">{payer.successRate}%</span>
                        </div>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Total Checks</p>
                        <p className="text-sm font-medium">{payer.totalChecks}</p>
                      </div>
                      <div className="bg-gray-50 rounded-lg p-3 sm:bg-transparent sm:p-0">
                        <p className="text-xs text-gray-500">Successful</p>
                        <p className="text-sm font-medium text-green-600">{payer.successfulChecks}</p>
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
      </div>
    </div>
  );
}
