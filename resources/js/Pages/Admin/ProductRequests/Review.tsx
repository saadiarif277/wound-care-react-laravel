import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  Eye,
  CheckCircle,
  XCircle,
  MessageSquare,
  Filter,
  Search,
  Clock,
  AlertTriangle,
  User,
  Building,
  Calendar,
  FileText,
  DollarSign,
  ChevronDown,
  ChevronUp,
  Download
} from 'lucide-react';

interface ProductRequest {
  id: number;
  request_number: string;
  patient_display: string;
  patient_fhir_id: string;
  order_status: string;
  wound_type: string;
  expected_service_date: string;
  submitted_at: string;
  total_order_value: number;
  facility: {
    id: number;
    name: string;
    city: string;
    state: string;
  };
  provider: {
    id: number;
    name: string;
    email: string;
    npi_number?: string;
  };
  payer_name: string;
  mac_validation_status: string;
  eligibility_status: string;
  pre_auth_required: boolean;
  clinical_summary: any;
  products_count: number;
  days_since_submission: number;
  priority_score: number;
}

interface Props {
  requests: {
    data: ProductRequest[];
    links: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    search?: string;
    status?: string;
    priority?: string;
    facility?: string;
    days_pending?: string;
  };
  statusCounts: {
    [key: string]: number;
  };
  facilities: Array<{ id: number; name: string }>;
  roleRestrictions: any;
}

const AdminProductRequestReview: React.FC<Props> = ({
  requests,
  filters,
  statusCounts,
  facilities,
  roleRestrictions
}) => {
  const [selectedRequests, setSelectedRequests] = useState<Set<number>>(new Set());
  const [expandedRequest, setExpandedRequest] = useState<number | null>(null);
  const [showBulkActions, setShowBulkActions] = useState(false);
  const [reviewModal, setReviewModal] = useState<{ requestId: number; action: string } | null>(null);
  const [reviewNotes, setReviewNotes] = useState('');

  // Status configuration
  const statusConfig = {
    submitted: { color: 'bg-blue-100 text-blue-800', icon: Clock, label: 'Submitted', priority: 1 },
    processing: { color: 'bg-yellow-100 text-yellow-800', icon: AlertTriangle, label: 'Under Review', priority: 2 },
    approved: { color: 'bg-green-100 text-green-800', icon: CheckCircle, label: 'Approved', priority: 3 },
    rejected: { color: 'bg-red-100 text-red-800', icon: XCircle, label: 'Rejected', priority: 4 },
  };

  const getPriorityBadge = (score: number) => {
    if (score >= 80) return { color: 'bg-red-100 text-red-800', label: 'High' };
    if (score >= 60) return { color: 'bg-yellow-100 text-yellow-800', label: 'Medium' };
    return { color: 'bg-green-100 text-green-800', label: 'Low' };
  };

  const toggleRequestSelection = (requestId: number) => {
    const newSelected = new Set(selectedRequests);
    if (newSelected.has(requestId)) {
      newSelected.delete(requestId);
    } else {
      newSelected.add(requestId);
    }
    setSelectedRequests(newSelected);
    setShowBulkActions(newSelected.size > 0);
  };

  const selectAllRequests = () => {
    if (selectedRequests.size === requests.data.length) {
      setSelectedRequests(new Set());
      setShowBulkActions(false);
    } else {
      setSelectedRequests(new Set(requests.data.map(r => r.id)));
      setShowBulkActions(true);
    }
  };

  const handleBulkAction = (action: string) => {
    if (selectedRequests.size === 0) return;

    router.post(`/admin/product-requests/bulk-action`, {
      request_ids: Array.from(selectedRequests),
      action: action,
      notes: reviewNotes
    }, {
      onSuccess: () => {
        setSelectedRequests(new Set());
        setShowBulkActions(false);
        setReviewNotes('');
      }
    });
  };

  const handleSingleAction = (requestId: number, action: string) => {
    router.post(`/admin/product-requests/${requestId}/review`, {
      action: action,
      notes: reviewNotes
    }, {
      onSuccess: () => {
        setReviewModal(null);
        setReviewNotes('');
      }
    });
  };

  const formatCurrency = (amount: number) => `$${amount.toFixed(2)}`;

  return (
    <MainLayout>
      <Head title="Product Request Review - Admin" />

      {/* Header with Stats */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Product Request Review</h1>
            <p className="mt-1 text-sm text-gray-600">
              Review and process submitted product requests from providers
            </p>
          </div>
          <div className="mt-4 sm:mt-0">
            <Link
              href="/admin/product-requests/analytics"
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              <Download className="h-4 w-4 mr-2" />
              Export Report
            </Link>
          </div>
        </div>

        {/* Status Cards */}
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {Object.entries(statusCounts).map(([status, count]) => {
            const config = statusConfig[status as keyof typeof statusConfig];
            if (!config) return null;
            const StatusIcon = config.icon;

            return (
              <div key={status} className="bg-white rounded-lg border border-gray-200 p-4">
                <div className="flex items-center">
                  <div className={`p-2 rounded-md ${config.color}`}>
                    <StatusIcon className="h-5 w-5" />
                  </div>
                  <div className="ml-3">
                    <p className="text-sm font-medium text-gray-900">{count}</p>
                    <p className="text-xs text-gray-500">{config.label}</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Search and Filters */}
      <div className="mb-6 bg-white rounded-lg border border-gray-200 p-4">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search requests..."
              className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              defaultValue={filters.search}
              onChange={(e) => {
                router.get(window.location.pathname,
                  { ...filters, search: e.target.value },
                  { preserveState: true, replace: true }
                );
              }}
            />
          </div>

          <select
            className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            defaultValue={filters.status}
            onChange={(e) => {
              router.get(window.location.pathname,
                { ...filters, status: e.target.value },
                { preserveState: true, replace: true }
              );
            }}
          >
            <option value="">All Statuses</option>
            <option value="submitted">Submitted</option>
            <option value="processing">Under Review</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>

          <select
            className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            defaultValue={filters.priority}
            onChange={(e) => {
              router.get(window.location.pathname,
                { ...filters, priority: e.target.value },
                { preserveState: true, replace: true }
              );
            }}
          >
            <option value="">All Priorities</option>
            <option value="high">High Priority</option>
            <option value="medium">Medium Priority</option>
            <option value="low">Low Priority</option>
          </select>

          <select
            className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            defaultValue={filters.facility}
            onChange={(e) => {
              router.get(window.location.pathname,
                { ...filters, facility: e.target.value },
                { preserveState: true, replace: true }
              );
            }}
          >
            <option value="">All Facilities</option>
            {facilities.map(facility => (
              <option key={facility.id} value={facility.id}>
                {facility.name}
              </option>
            ))}
          </select>

          <select
            className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            defaultValue={filters.days_pending}
            onChange={(e) => {
              router.get(window.location.pathname,
                { ...filters, days_pending: e.target.value },
                { preserveState: true, replace: true }
              );
            }}
          >
            <option value="">All Timeframes</option>
            <option value="1">Last 24 hours</option>
            <option value="3">Last 3 days</option>
            <option value="7">Last week</option>
            <option value="14">Last 2 weeks</option>
          </select>
        </div>
      </div>

      {/* Bulk Actions Bar */}
      {showBulkActions && (
        <div className="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <CheckCircle className="h-5 w-5 text-blue-500 mr-2" />
              <span className="text-sm font-medium text-blue-900">
                {selectedRequests.size} request{selectedRequests.size !== 1 ? 's' : ''} selected
              </span>
            </div>
            <div className="flex space-x-2">
              <button
                onClick={() => handleBulkAction('approve')}
                className="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700"
              >
                Approve Selected
              </button>
              <button
                onClick={() => handleBulkAction('request_info')}
                className="px-4 py-2 bg-yellow-600 text-white text-sm rounded-md hover:bg-yellow-700"
              >
                Request Info
              </button>
              <button
                onClick={() => handleBulkAction('reject')}
                className="px-4 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700"
              >
                Reject Selected
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Requests Table */}
      <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left">
                  <input
                    type="checkbox"
                    checked={selectedRequests.size === requests.data.length && requests.data.length > 0}
                    onChange={selectAllRequests}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Request Details
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Provider & Facility
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Clinical Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Priority & Timeline
                </th>
                {roleRestrictions.can_view_financials && (
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Order Value
                  </th>
                )}
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {requests.data.map((request) => {
                const statusInfo = statusConfig[request.order_status as keyof typeof statusConfig];
                const priorityInfo = getPriorityBadge(request.priority_score);
                const isExpanded = expandedRequest === request.id;

                return (
                  <React.Fragment key={request.id}>
                    <tr className={`hover:bg-gray-50 ${selectedRequests.has(request.id) ? 'bg-blue-50' : ''}`}>
                      <td className="px-6 py-4">
                        <input
                          type="checkbox"
                          checked={selectedRequests.has(request.id)}
                          onChange={() => toggleRequestSelection(request.id)}
                          className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                      </td>

                      <td className="px-6 py-4">
                        <div className="flex items-center justify-between">
                          <div>
                            <div className="text-sm font-medium text-gray-900">
                              {request.request_number}
                            </div>
                            <div className="text-sm text-gray-500">
                              Patient: {request.patient_display}
                            </div>
                            <div className="text-sm text-gray-500">
                              Wound: {request.wound_type}
                            </div>
                            <div className="mt-1">
                              <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusInfo?.color}`}>
                                {statusInfo?.label}
                              </span>
                            </div>
                          </div>
                          <button
                            onClick={() => setExpandedRequest(isExpanded ? null : request.id)}
                            className="text-gray-400 hover:text-gray-600"
                          >
                            {isExpanded ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                          </button>
                        </div>
                      </td>

                      <td className="px-6 py-4">
                        <div>
                          <div className="text-sm font-medium text-gray-900">
                            Dr. {request.provider.name}
                          </div>
                          <div className="text-sm text-gray-500">
                            {request.provider.npi_number && `NPI: ${request.provider.npi_number}`}
                          </div>
                          <div className="text-sm text-gray-500 mt-1">
                            {request.facility.name}
                          </div>
                          <div className="text-sm text-gray-400">
                            {request.facility.city}, {request.facility.state}
                          </div>
                        </div>
                      </td>

                      <td className="px-6 py-4">
                        <div className="space-y-1">
                          <div className="flex items-center text-sm">
                            <span className="text-gray-500">MAC:</span>
                            <span className={`ml-2 px-2 py-1 text-xs rounded-full ${
                              request.mac_validation_status === 'passed'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                            }`}>
                              {request.mac_validation_status}
                            </span>
                          </div>
                          <div className="flex items-center text-sm">
                            <span className="text-gray-500">Eligibility:</span>
                            <span className={`ml-2 px-2 py-1 text-xs rounded-full ${
                              request.eligibility_status === 'verified'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-yellow-100 text-yellow-800'
                            }`}>
                              {request.eligibility_status}
                            </span>
                          </div>
                          {request.pre_auth_required && (
                            <div className="text-xs text-orange-600">
                              Prior Auth Required
                            </div>
                          )}
                        </div>
                      </td>

                      <td className="px-6 py-4">
                        <div>
                          <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${priorityInfo.color}`}>
                            {priorityInfo.label} Priority
                          </span>
                          <div className="text-sm text-gray-500 mt-1">
                            Submitted {request.days_since_submission} days ago
                          </div>
                          <div className="text-sm text-gray-500">
                            Service Date: {request.expected_service_date}
                          </div>
                        </div>
                      </td>

                      {roleRestrictions.can_view_financials && (
                        <td className="px-6 py-4">
                          <div className="text-sm font-medium text-gray-900">
                            {formatCurrency(request.total_order_value)}
                          </div>
                          <div className="text-sm text-gray-500">
                            {request.products_count} product{request.products_count !== 1 ? 's' : ''}
                          </div>
                        </td>
                      )}

                      <td className="px-6 py-4">
                        <div className="flex space-x-2">
                          <Link
                            href={`/admin/product-requests/${request.id}`}
                            className="text-blue-600 hover:text-blue-900"
                          >
                            <Eye className="h-4 w-4" />
                          </Link>
                          {request.order_status === 'submitted' && (
                            <>
                              <button
                                onClick={() => setReviewModal({ requestId: request.id, action: 'approve' })}
                                className="text-green-600 hover:text-green-900"
                              >
                                <CheckCircle className="h-4 w-4" />
                              </button>
                              <button
                                onClick={() => setReviewModal({ requestId: request.id, action: 'request_info' })}
                                className="text-yellow-600 hover:text-yellow-900"
                              >
                                <MessageSquare className="h-4 w-4" />
                              </button>
                              <button
                                onClick={() => setReviewModal({ requestId: request.id, action: 'reject' })}
                                className="text-red-600 hover:text-red-900"
                              >
                                <XCircle className="h-4 w-4" />
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>

                    {/* Expanded Row */}
                    {isExpanded && (
                      <tr>
                        <td colSpan={roleRestrictions.can_view_financials ? 7 : 6} className="px-6 py-4 bg-gray-50">
                          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                              <h4 className="text-sm font-medium text-gray-900 mb-2">Clinical Summary</h4>
                              <div className="text-sm text-gray-600">
                                {request.clinical_summary ? (
                                  <div className="space-y-1">
                                    <p><strong>Diagnosis:</strong> {request.clinical_summary.diagnosis || 'Not specified'}</p>
                                    <p><strong>Wound Size:</strong> {request.clinical_summary.wound_dimensions || 'Not specified'}</p>
                                    <p><strong>Duration:</strong> {request.clinical_summary.wound_duration || 'Not specified'}</p>
                                  </div>
                                ) : (
                                  <p className="text-gray-400 italic">No clinical summary provided</p>
                                )}
                              </div>
                            </div>

                            <div>
                              <h4 className="text-sm font-medium text-gray-900 mb-2">Insurance Information</h4>
                              <div className="text-sm text-gray-600">
                                <p><strong>Payer:</strong> {request.payer_name}</p>
                                <p><strong>Service Date:</strong> {request.expected_service_date}</p>
                                <p><strong>Submitted:</strong> {request.submitted_at}</p>
                              </div>
                            </div>

                            <div>
                              <h4 className="text-sm font-medium text-gray-900 mb-2">Request Actions</h4>
                              <div className="space-y-2">
                                <Link
                                  href={`/admin/product-requests/${request.id}`}
                                  className="block w-full px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 text-center"
                                >
                                  View Full Details
                                </Link>
                                <Link
                                  href={`/admin/product-requests/${request.id}/clinical-review`}
                                  className="block w-full px-3 py-2 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700 text-center"
                                >
                                  Clinical Review
                                </Link>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {requests.links && (
          <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div className="flex items-center justify-between">
              <div className="flex-1 flex justify-between sm:hidden">
                {requests.links.map((link, index) => (
                  <Link
                    key={index}
                    href={link.url || '#'}
                    className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md ${
                      link.active
                        ? 'bg-blue-50 border-blue-500 text-blue-600'
                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
              <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p className="text-sm text-gray-700">
                    Showing {((requests.current_page - 1) * requests.per_page) + 1} to{' '}
                    {Math.min(requests.current_page * requests.per_page, requests.total)} of{' '}
                    {requests.total} results
                  </p>
                </div>
                <div>
                  <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                    {requests.links.map((link, index) => (
                      <Link
                        key={index}
                        href={link.url || '#'}
                        className={`relative inline-flex items-center px-3 py-2 border text-sm font-medium ${
                          link.active
                            ? 'bg-blue-50 border-blue-500 text-blue-600'
                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                        } ${
                          index === 0 ? 'rounded-l-md' : ''
                        } ${
                          index === requests.links.length - 1 ? 'rounded-r-md' : ''
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ))}
                  </nav>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Review Modal */}
      {reviewModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                {reviewModal.action === 'approve' && 'Approve Request'}
                {reviewModal.action === 'reject' && 'Reject Request'}
                {reviewModal.action === 'request_info' && 'Request Additional Information'}
              </h3>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  {reviewModal.action === 'approve' && 'Approval Notes (Optional)'}
                  {reviewModal.action === 'reject' && 'Rejection Reason (Required)'}
                  {reviewModal.action === 'request_info' && 'Information Needed (Required)'}
                </label>
                <textarea
                  value={reviewNotes}
                  onChange={(e) => setReviewNotes(e.target.value)}
                  rows={4}
                  className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                  placeholder={
                    reviewModal.action === 'approve'
                      ? 'Optional notes for approval...'
                      : reviewModal.action === 'reject'
                      ? 'Please specify the reason for rejection...'
                      : 'Please specify what additional information is needed...'
                  }
                />
              </div>
              <div className="flex justify-end space-x-3">
                <button
                  onClick={() => {
                    setReviewModal(null);
                    setReviewNotes('');
                  }}
                  className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                >
                  Cancel
                </button>
                <button
                  onClick={() => handleSingleAction(reviewModal.requestId, reviewModal.action)}
                  disabled={
                    (reviewModal.action === 'reject' || reviewModal.action === 'request_info') &&
                    !reviewNotes.trim()
                  }
                  className={`px-4 py-2 text-sm font-medium rounded-md ${
                    reviewModal.action === 'approve'
                      ? 'bg-green-600 hover:bg-green-700 text-white'
                      : reviewModal.action === 'reject'
                      ? 'bg-red-600 hover:bg-red-700 text-white'
                      : 'bg-yellow-600 hover:bg-yellow-700 text-white'
                  } disabled:opacity-50`}
                >
                  {reviewModal.action === 'approve' && 'Approve'}
                  {reviewModal.action === 'reject' && 'Reject'}
                  {reviewModal.action === 'request_info' && 'Request Info'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

export default AdminProductRequestReview;
