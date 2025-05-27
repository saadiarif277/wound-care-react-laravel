import { Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import React from 'react';
import { UserRole, UserWithRole } from '@/types/roles';
import {
  getDashboardTitle,
  getDashboardDescription,
  getFeatureFlags,
  hasPermission
} from '@/lib/roleUtils';

// Import role-specific dashboards
import ProviderDashboard from './Provider/ProviderDashboard';
import OfficeManagerDashboard from './OfficeManager/OfficeManagerDashboard';
import MscAdminDashboard from './Admin/MscAdminDashboard';
import SuperAdminDashboard from './Admin/SuperAdminDashboard';
import MscRepDashboard from './Sales/MscRepDashboard';
import MscSubrepDashboard from './Sales/MscSubrepDashboard';

// Define the types
interface Verification {
  id: string;
  customerId: string;
  requestDate?: string;
  patientInfo: {
    firstName: string;
    lastName: string;
  };
  insuranceInfo: {
    payerId: string;
  };
}

interface VerificationResult {
  id: string;
  status: 'active' | 'inactive' | 'pending' | 'error';
}

interface ActionItem {
  id: string;
  type: 'document_required' | 'pa_approval' | 'mac_validation' | 'review_needed';
  title: string;
  description: string;
  priority: 'high' | 'medium' | 'low';
  dueDate?: string;
  link: string;
}

interface ClinicalOpportunity {
  id: string;
  patientName: string;
  opportunity: string;
  rationale: string;
  potentialValue: string;
  urgency: 'high' | 'medium' | 'low';
}

interface Request {
  id: string;
  type: 'product_request' | 'eligibility_check' | 'pa_request' | 'order';
  patientName: string;
  status: 'pending' | 'approved' | 'denied' | 'in_review' | 'completed';
  requestDate: string;
  description: string;
}

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
}

interface DashboardProps {
  userRole?: UserRole;
  user?: UserWithRole;
  roleRestrictions?: RoleRestrictions;
}

// Create dummy data
const dummyVerifications: Verification[] = [
  {
    id: 'ver-001',
    customerId: 'customer-001',
    requestDate: '2023-06-15',
    patientInfo: {
      firstName: 'John',
      lastName: 'Doe'
    },
    insuranceInfo: {
      payerId: 'AETNA-123'
    }
  },
  {
    id: 'ver-002',
    customerId: 'customer-002',
    requestDate: '2023-06-14',
    patientInfo: {
      firstName: 'Jane',
      lastName: 'Smith'
    },
    insuranceInfo: {
      payerId: 'BCBS-456'
    }
  },
  {
    id: 'ver-003',
    customerId: 'customer-003',
    requestDate: '2023-06-13',
    patientInfo: {
      firstName: 'Robert',
      lastName: 'Johnson'
    },
    insuranceInfo: {
      payerId: 'MEDICARE-789'
    }
  },
  {
    id: 'ver-004',
    customerId: 'customer-001',
    requestDate: '2023-06-12',
    patientInfo: {
      firstName: 'Sarah',
      lastName: 'Williams'
    },
    insuranceInfo: {
      payerId: 'UNITED-101'
    }
  },
  {
    id: 'ver-005',
    customerId: 'customer-002',
    requestDate: '2023-06-11',
    patientInfo: {
      firstName: 'Michael',
      lastName: 'Brown'
    },
    insuranceInfo: {
      payerId: 'CIGNA-202'
    }
  }
];

const dummyResults: Record<string, VerificationResult> = {
  'ver-001': { id: 'ver-001', status: 'active' },
  'ver-002': { id: 'ver-002', status: 'inactive' },
  'ver-003': { id: 'ver-003', status: 'pending' },
  'ver-004': { id: 'ver-004', status: 'error' },
  'ver-005': { id: 'ver-005', status: 'active' }
};

// New dummy data for enhanced dashboard
const dummyActionItems: ActionItem[] = [
  {
    id: 'action-001',
    type: 'document_required',
    title: 'Additional Documentation Required',
    description: 'Wound assessment photos needed for Request #WC-2024-001',
    priority: 'high',
    dueDate: '2024-01-20',
    link: '/orders/WC-2024-001'
  },
  {
    id: 'action-002',
    type: 'pa_approval',
    title: 'Prior Authorization Pending',
    description: 'PA request for Smith, Jane - Advanced wound dressing',
    priority: 'medium',
    dueDate: '2024-01-22',
    link: '/pa/PA-2024-015'
  },
  {
    id: 'action-003',
    type: 'mac_validation',
    title: 'MAC Validation Warning',
    description: 'Missing osteomyelitis documentation for DFU case',
    priority: 'high',
    link: '/mac-validation/MV-2024-008'
  }
];

const dummyClinicalOpportunities: ClinicalOpportunity[] = [
  {
    id: 'opp-001',
    patientName: 'Robert Johnson',
    opportunity: 'Consider Offloading DME L4631',
    rationale: 'Wagner Grade 3 DFU detected - offloading recommended for optimal healing',
    potentialValue: 'Improved healing outcomes',
    urgency: 'high'
  },
  {
    id: 'opp-002',
    patientName: 'Sarah Williams',
    opportunity: 'Advanced Wound Matrix Therapy',
    rationale: 'Chronic wound >12 weeks with poor healing response to standard care',
    potentialValue: '$2,400 potential revenue',
    urgency: 'medium'
  }
];

const dummyRecentRequests: Request[] = [
  {
    id: 'req-001',
    type: 'product_request',
    patientName: 'John Doe',
    status: 'pending',
    requestDate: '2024-01-15',
    description: 'Advanced alginate dressing for diabetic foot ulcer'
  },
  {
    id: 'req-002',
    type: 'pa_request',
    patientName: 'Jane Smith',
    status: 'approved',
    requestDate: '2024-01-14',
    description: 'Prior authorization for negative pressure wound therapy'
  },
  {
    id: 'req-003',
    type: 'eligibility_check',
    patientName: 'Robert Johnson',
    status: 'completed',
    requestDate: '2024-01-13',
    description: 'Medicare coverage verification'
  },
  {
    id: 'req-004',
    type: 'order',
    patientName: 'Sarah Williams',
    status: 'in_review',
    requestDate: '2024-01-12',
    description: 'Hydrocolloid dressing order - 30 units'
  }
];

function DashboardPage({ userRole = 'provider', user, roleRestrictions }: DashboardProps) {
  // Route to specific role-based dashboards
  const renderRoleSpecificDashboard = () => {
    switch (userRole) {
      case 'provider':
        return <ProviderDashboard user={user} dashboardData={{}} roleRestrictions={roleRestrictions} />;
      case 'office_manager':
        return <OfficeManagerDashboard user={user} dashboardData={{}} roleRestrictions={roleRestrictions} />;
      case 'msc_admin':
        return <MscAdminDashboard user={user} />;
      case 'superadmin':
        return <SuperAdminDashboard user={user} />;
      case 'msc_rep':
        return <MscRepDashboard user={user} />;
      case 'msc_subrep':
        return <MscSubrepDashboard user={user} />;
      default:
        // Fall back to the existing generic dashboard for unrecognized roles
        return renderGenericDashboard();
    }
  };

  const renderGenericDashboard = () => {
    // Use the dummy data directly
    const verifications = dummyVerifications;
    const results = dummyResults;
    const actionItems = dummyActionItems;
    const clinicalOpportunities = dummyClinicalOpportunities;
    const recentRequests = dummyRecentRequests;

    // Get role-based feature flags
    const featureFlags = getFeatureFlags(userRole);

  // Calculate status counts
  const getTotalsByStatus = () => {
    const totals = {
      active: 0,
      inactive: 0,
      pending: 0,
      error: 0
    };

    verifications.forEach(verification => {
      if (verification.id && results[verification.id]) {
        const status = results[verification.id].status;
        if (status in totals) {
          totals[status as keyof typeof totals]++;
        }
      }
    });

    return totals;
  };

  const statusTotals = getTotalsByStatus();
  const totalVerifications = verifications.length;

  // Get role-specific dashboard content using utility functions
  const dashboardContent = {
    title: getDashboardTitle(userRole),
    description: getDashboardDescription(userRole)
  };

  // Get priority action items (top 3)
  const priorityActionItems = actionItems
    .sort((a, b) => {
      const priorityOrder = { high: 3, medium: 2, low: 1 };
      return priorityOrder[b.priority] - priorityOrder[a.priority];
    })
    .slice(0, 3);

  return (
    <div className="space-y-6">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900">{dashboardContent.title}</h1>
        <p className="mt-2 text-gray-600 leading-normal">
          {dashboardContent.description}
        </p>
      </div>

      {/* Action Required Notifications */}
      {priorityActionItems.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-amber-50">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Action Required</h2>
                <p className="text-sm text-gray-600 mt-1">Items that need your immediate attention</p>
              </div>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {priorityActionItems.map((item) => (
              <div key={item.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{item.title}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        item.priority === 'high' ? 'bg-red-100 text-red-800' :
                        item.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {item.priority} priority
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                    {item.dueDate && (
                      <p className="text-xs text-gray-500 mt-2">Due: {item.dueDate}</p>
                    )}
                  </div>
                  <Link
                    href={item.link}
                    className="ml-4 inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white hover:shadow-md transition-all duration-200"
                    style={{ backgroundColor: '#1822cf' }}
                  >
                    Review
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Quick Action Buttons */}
      <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Link
            href="/orders/new"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span className="text-sm font-medium text-gray-900">New Request</span>
          </Link>

          <Link
            href="/eligibility/check"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Check Eligibility</span>
          </Link>

          <Link
            href="/product-requests/create"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">New Product Request</span>
          </Link>

          <Link
            href="/products"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Product Catalog</span>
          </Link>

          <Link
            href="/pa/submit"
            className="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 group"
          >
            <svg className="h-8 w-8 text-gray-400 group-hover:text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span className="text-sm font-medium text-gray-900">Submit PA</span>
          </Link>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid gap-6 md:grid-cols-4">
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Total Requests</h3>
          <p className="text-3xl font-bold mt-2 text-gray-900">{recentRequests.length}</p>
          <p className="text-xs text-gray-600 mt-2">All time</p>
        </div>
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Pending Actions</h3>
          <p className="text-3xl font-bold text-amber-600 mt-2">{actionItems.length}</p>
          <p className="text-xs text-gray-600 mt-2">Require attention</p>
        </div>
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Active Coverage</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">{statusTotals.active}</p>
          <p className="text-xs text-gray-600 mt-2">
            {statusTotals.active > 0 && totalVerifications > 0
              ? Math.round((statusTotals.active / totalVerifications) * 100)
              : 0}% verified
          </p>
        </div>
        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Opportunities</h3>
          <p className="text-3xl font-bold text-blue-600 mt-2">{clinicalOpportunities.length}</p>
          <p className="text-xs text-gray-600 mt-2">Clinical insights</p>
        </div>
      </div>

      {/* Clinical Opportunities Widget - Show for users with clinical access */}
      {roleRestrictions?.can_see_msc_pricing && clinicalOpportunities.length > 0 && (
        <div className="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
          <div className="p-6 border-b border-gray-200 bg-blue-50">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <svg className="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
              </div>
              <div className="ml-3">
                <h2 className="text-xl font-semibold text-gray-900">Clinical Opportunities</h2>
                <p className="text-sm text-gray-600 mt-1">AI-identified opportunities to enhance patient care</p>
              </div>
            </div>
          </div>
          <div className="divide-y divide-gray-200">
            {clinicalOpportunities.map((opportunity) => (
              <div key={opportunity.id} className="p-6 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center">
                      <h3 className="text-sm font-semibold text-gray-900">{opportunity.patientName}</h3>
                      <span className={`ml-2 px-2 py-1 text-xs font-medium rounded-full ${
                        opportunity.urgency === 'high' ? 'bg-red-100 text-red-800' :
                        opportunity.urgency === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {opportunity.urgency} priority
                      </span>
                    </div>
                    <p className="text-sm font-medium text-blue-600 mt-1">{opportunity.opportunity}</p>
                    <p className="text-sm text-gray-600 mt-1">{opportunity.rationale}</p>
                    <p className="text-xs text-green-600 mt-2 font-medium">{opportunity.potentialValue}</p>
                  </div>
                  <div className="ml-4 flex space-x-2">
                    <button className="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                      Dismiss
                    </button>
                    <button
                      className="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white hover:shadow-md transition-all duration-200"
                      style={{ backgroundColor: '#1822cf' }}
                    >
                      Accept
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Enhanced Recent Requests */}
      <div className="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
        <div className="p-6 border-b border-gray-200">
          <h2 className="text-xl font-semibold text-gray-900">Recent Activity</h2>
          <p className="text-sm text-gray-600 mt-1">Latest requests across all categories</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Type</th>
                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Patient</th>
                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Description</th>
                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Date</th>
                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Status</th>
                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white">
              {recentRequests.length > 0 ? (
                recentRequests.map((request) => (
                  <tr key={request.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                        request.type === 'product_request' ? 'bg-blue-100 text-blue-800' :
                        request.type === 'pa_request' ? 'bg-purple-100 text-purple-800' :
                        request.type === 'eligibility_check' ? 'bg-green-100 text-green-800' :
                        'bg-gray-100 text-gray-800'
                      }`}>
                        {request.type.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {request.patientName}
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-700 max-w-xs truncate">
                      {request.description}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                      {request.requestDate}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        request.status === 'approved' || request.status === 'completed' ? 'bg-green-100 text-green-800' :
                        request.status === 'denied' ? 'bg-red-100 text-red-800' :
                        request.status === 'pending' || request.status === 'in_review' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-gray-100 text-gray-800'
                      }`}>
                        {request.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <Link
                        href={`/requests/${request.id}`}
                        className="text-blue-600 hover:text-blue-800 font-semibold transition-colors"
                        style={{ color: '#1822cf' }}
                      >
                        View
                      </Link>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} className="px-6 py-4 text-center text-gray-500">
                    No recent requests found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="px-6 py-4 border-t bg-gray-50">
          <Link
            href="/requests"
            className="text-blue-600 hover:text-blue-800 font-semibold transition-colors"
            style={{ color: '#1822cf' }}
          >
            View all requests →
          </Link>
        </div>
      </div>

      {/* Role-specific Quick Links */}
      <div className="grid gap-6 md:grid-cols-3">
        {roleRestrictions?.can_view_financials && roleRestrictions?.pricing_access_level === 'full' && (
          <>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">System Management</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Manage users, organizations, and system configurations.
              </p>
              <Link
                href="/admin/users"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Manage System
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">MAC Rules Management</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Configure Medicare Administrative Contractor coverage rules.
              </p>
              <Link
                href="/admin/mac-rules"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Edit MAC Rules
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">Analytics & Reports</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                View comprehensive system analytics and generate reports.
              </p>
              <Link
                href="/admin/reports"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                View Reports
              </Link>
            </div>
          </>
        )}

        {roleRestrictions?.can_see_msc_pricing && (
          <>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">Product Catalog</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Browse MSC wound care products with intelligent recommendations.
              </p>
              <Link
                href="/products"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Browse Products
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">Patient Management</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Manage patient records and clinical documentation.
              </p>
              <Link
                href="/patients"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Manage Patients
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">MSC Assist</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Get AI-powered guidance for clinical decisions and documentation.
              </p>
              <button
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Launch MSC Assist
              </button>
            </div>
          </>
        )}

        {!roleRestrictions?.can_see_msc_pricing && roleRestrictions?.can_view_financials === false && (
          <>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">Facility Management</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Oversee facility operations and coordinate provider activities.
              </p>
              <Link
                href="/facility/management"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Manage Facility
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">Provider Coordination</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Coordinate provider activities and administrative workflows.
              </p>
              <Link
                href="/providers/coordination"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Coordinate Providers
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">Administrative Tools</h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                Access administrative workflow tools and facility reporting.
              </p>
              <Link
                href="/admin/tools"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                Admin Tools
              </Link>
            </div>
          </>
        )}

        {roleRestrictions?.can_view_financials && (
          <>
                        <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">
                {roleRestrictions?.pricing_access_level === 'full' ? 'Commission Tracking' : 'Limited Commission Access'}
              </h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                {roleRestrictions?.pricing_access_level === 'full'
                  ? 'View your commission statements and team performance.'
                  : 'Track your limited commission access and activities.'
                }
              </p>
              <Link
                href="/sales/commission"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                View Commission
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">
                {roleRestrictions?.pricing_access_level === 'full' ? 'Customer Management' : 'Customer Support'}
              </h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                {roleRestrictions?.pricing_access_level === 'full'
                  ? 'Manage customer relationships and territory oversight.'
                  : 'Assist with customer interactions and territory support.'
                }
              </p>
              <Link
                href="/sales/customers"
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                {roleRestrictions?.pricing_access_level === 'full' ? 'Manage Customers' : 'Customer Support'}
              </Link>
            </div>
            <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">
                {roleRestrictions?.pricing_access_level === 'full' ? 'Territory & Analytics' : 'Sub-Rep Activities'}
              </h3>
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">
                {roleRestrictions?.pricing_access_level === 'full'
                  ? 'Track sales performance and manage sub-representatives.'
                  : 'Report activities and coordinate with primary MSC Rep.'
                }
              </p>
              <Link
                href={roleRestrictions?.pricing_access_level === 'full' ? '/sales/analytics' : '/sales/subrep-activities'}
                className="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white transition-all duration-200 hover:shadow-md"
                style={{ backgroundColor: '#1822cf' }}
              >
                {roleRestrictions?.pricing_access_level === 'full' ? 'View Analytics' : 'My Activities'}
              </Link>
            </div>
          </>
        )}
      </div>

    </div>
    );
  };

  return (
    <div>
      {renderRoleSpecificDashboard()}
    </div>
  );
}

DashboardPage.layout = (page: React.ReactNode) => {
  // Access props through Inertia's page prop system
  return (
    <MainLayout title="Dashboard" children={page} />
  );
};

export default DashboardPage;
