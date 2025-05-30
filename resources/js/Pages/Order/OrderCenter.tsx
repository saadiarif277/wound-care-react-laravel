import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout'; // Assuming MainLayout is the correct layout
import {
  Eye,
  CheckCircle,
  XCircle,
  MessageSquare,
  Filter,
  Search,
  Clock,
  AlertTriangle,
  ChevronDown,
  ChevronUp,
  Download,
  ShoppingCart, // Changed from FiShoppingCart for lucide-react consistency
} from 'lucide-react';

// --- Interface for ProductRequest --- START ---
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
  clinical_summary: any; // Should be typed more specifically if possible
  products_count: number;
  days_since_submission: number;
  priority_score: number;
}
// --- Interface for ProductRequest --- END ---

// --- Interface for ReviewTabProps --- START ---
interface ReviewTabProps {
  requests: {
    data: ProductRequest[]; // Now uses ProductRequest
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
// --- Interface for ReviewTabProps --- END ---

// Placeholder for Order Management content
const OrderManagementTabContent: React.FC = () => {
  const [activeSubTab, setActiveSubTab] = useState<'processing' | 'documents' | 'create'>('processing');
  const [loading, setLoading] = useState(false);
  const [orders, setOrders] = useState<any[]>([]);
  const [docuSealSubmissions, setDocuSealSubmissions] = useState<any[]>([]);
  const [stats, setStats] = useState({
    totalOrders: 0,
    pendingApproval: 0,
    pendingDocuments: 0,
    completedToday: 0
  });

  // Create Order Form State
  const [step, setStep] = useState(1);
  const [orderForm, setOrderForm] = useState({
    orderNumber: `ORD-${new Date().getTime()}`,
    doctorFacilityName: '',
    patientHash: '',
    dateOfService: new Date(),
    creditTerms: 'net60',
    sku: '',
    nationalAsp: 0,
    pricePerSqCm: 0,
    expectedReimbursement: 0,
    graphType: '',
    productName: '',
    graphSize: '',
    units: 1,
    qCode: '',
    paymentStatus: 'pending'
  });

  const subTabs = [
    { id: 'processing', label: 'Order Processing', icon: ShoppingCart, count: stats.pendingApproval },
    { id: 'documents', label: 'Document Generation', icon: Download, count: stats.pendingDocuments },
    { id: 'create', label: 'Manual Order Creation', icon: Eye, count: null }
  ];

  const getStatusBadge = (status: string) => {
    const baseClasses = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium";
    switch (status?.toLowerCase()) {
      case 'approved':
      case 'completed':
      case 'fulfilled':
        return `${baseClasses} bg-green-100 text-green-800`;
      case 'pending':
      case 'processing':
        return `${baseClasses} bg-yellow-100 text-yellow-800`;
      case 'rejected':
      case 'cancelled':
      case 'expired':
      case 'overdue':
        return `${baseClasses} bg-red-100 text-red-800`;
      case 'shipped':
        return `${baseClasses} bg-blue-100 text-blue-800`;
      default:
        return `${baseClasses} bg-gray-100 text-gray-800`;
    }
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  // Render Order Processing Tab
  const renderOrderProcessing = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Order Processing & Approval</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2">
            <Download className="w-4 h-4" />
            Export
          </button>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order Details
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Organization / Facility
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {orders.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-8">
                    <ShoppingCart className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                    <p className="text-gray-600 text-lg">No orders found</p>
                    <p className="text-gray-500 text-sm">Orders will appear here when they are submitted for processing.</p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  // Render Document Generation Tab
  const renderDocumentGeneration = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Document Generation & Management</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Documents</option>
            <option value="InsuranceVerification">Insurance Verification</option>
            <option value="OrderForm">Order Form</option>
            <option value="OnboardingForm">Onboarding Form</option>
          </select>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2">
            <Download className="w-4 h-4" />
            Bulk Generate
          </button>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order / Document
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Signer
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Created
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {docuSealSubmissions.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-8">
                    <Download className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                    <p className="text-gray-600 text-lg">No document submissions found</p>
                    <p className="text-gray-500 text-sm">Document submissions will appear here when orders are processed.</p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  // Render Manual Order Creation Tab
  const renderManualOrderCreation = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Manual Order Creation</h3>
        <div className="text-sm text-gray-500">
          Step {step} of 3
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-lg overflow-hidden">
        {/* Step 1: Order Information */}
        {step === 1 && (
          <div className="p-6">
            <h2 className="text-xl font-semibold text-red-800 mb-6 flex items-center gap-2">
              <MessageSquare className="text-red-600" />
              Order Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Order Number
                </label>
                <input
                  type="text"
                  value={orderForm.orderNumber}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                  disabled
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Doctor/Facility Name *
                </label>
                <input
                  type="text"
                  value={orderForm.doctorFacilityName}
                  onChange={(e) => setOrderForm({...orderForm, doctorFacilityName: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Patient Hash *
                </label>
                <input
                  type="text"
                  value={orderForm.patientHash}
                  onChange={(e) => setOrderForm({...orderForm, patientHash: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Date of Service *
                </label>
                <input
                  type="date"
                  value={orderForm.dateOfService.toISOString().split('T')[0]}
                  onChange={(e) => setOrderForm({...orderForm, dateOfService: new Date(e.target.value)})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500"
                  required
                />
              </div>
            </div>
          </div>
        )}

        {/* Navigation */}
        <div className="px-6 py-4 bg-gray-50 flex justify-between items-center">
          <button
            type="button"
            onClick={() => setStep(Math.max(step - 1, 1))}
            disabled={step === 1}
            className={`flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md ${
              step === 1
                ? 'text-gray-400 cursor-not-allowed'
                : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
            }`}
          >
            ← Previous
          </button>

          {step < 3 ? (
            <button
              type="button"
              onClick={() => setStep(step + 1)}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700"
            >
              Next →
            </button>
          ) : (
            <button
              type="button"
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700"
            >
              Create Order
            </button>
          )}
        </div>
      </div>
    </div>
  );

  return (
    <div className="space-y-6">
      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <ShoppingCart className="h-8 w-8 text-blue-600" />
            </div>
            <div className="ml-5">
              <dt className="text-sm font-medium text-gray-500">Total Orders</dt>
              <dd className="text-2xl font-semibold text-gray-900">{stats.totalOrders}</dd>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <Clock className="h-8 w-8 text-yellow-600" />
            </div>
            <div className="ml-5">
              <dt className="text-sm font-medium text-gray-500">Pending Approval</dt>
              <dd className="text-2xl font-semibold text-gray-900">{stats.pendingApproval}</dd>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <Download className="h-8 w-8 text-purple-600" />
            </div>
            <div className="ml-5">
              <dt className="text-sm font-medium text-gray-500">Pending Documents</dt>
              <dd className="text-2xl font-semibold text-gray-900">{stats.pendingDocuments}</dd>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <CheckCircle className="h-8 w-8 text-green-600" />
            </div>
            <div className="ml-5">
              <dt className="text-sm font-medium text-gray-500">Completed Today</dt>
              <dd className="text-2xl font-semibold text-gray-900">{stats.completedToday}</dd>
            </div>
          </div>
        </div>
      </div>

      {/* Sub-tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {subTabs.map((tab) => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveSubTab(tab.id as any)}
                className={`py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
                  activeSubTab === tab.id
                    ? 'border-red-500 text-red-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <Icon className="w-4 h-4" />
                {tab.label}
                {tab.count !== null && tab.count > 0 && (
                  <span className="bg-red-100 text-red-600 py-0.5 px-2.5 rounded-full text-xs">
                    {tab.count}
                  </span>
                )}
              </button>
            );
          })}
        </nav>
      </div>

      {/* Sub-tab Content */}
      <div className="min-h-96">
        {activeSubTab === 'processing' && renderOrderProcessing()}
        {activeSubTab === 'documents' && renderDocumentGeneration()}
        {activeSubTab === 'create' && renderManualOrderCreation()}
      </div>
    </div>
  );
};

// Content from AdminProductRequestReview.tsx, adapted into a component
const RequestReviewsTabContent: React.FC<ReviewTabProps> = ({
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
    if (requests?.data && selectedRequests.size === requests.data.length) {
      setSelectedRequests(new Set());
      setShowBulkActions(false);
    } else if (requests?.data) {
      setSelectedRequests(new Set(requests.data.map(r => r.id)));
      setShowBulkActions(true);
    }
  };

  const handleBulkAction = (action: string) => {
    if (selectedRequests.size === 0) return;
    router.post(`/product-requests/review/bulk-action`, {
      request_ids: Array.from(selectedRequests),
      action: action,
      notes: reviewNotes
    }, {
      onSuccess: () => {
        setSelectedRequests(new Set());
        setShowBulkActions(false);
        setReviewNotes('');
        router.reload({ only: ['requests', 'statusCounts'] }); // Reload data for the tab
      }
    });
  };

  const handleSingleAction = (requestId: number, action: string) => {
    let endpoint = '';
    let data: any = {};

    switch (action) {
      case 'approve':
        endpoint = `/product-requests/review/${requestId}/approve`;
        data = {
          comments: reviewNotes,
          notify_provider: true
        };
        break;
      case 'reject':
        endpoint = `/product-requests/review/${requestId}/reject`;
        data = {
          reason: reviewNotes,
          category: 'other',
          notify_provider: true
        };
        break;
      case 'request_info':
        endpoint = `/product-requests/review/${requestId}/request-info`;
        data = {
          information_needed: reviewNotes,
          notify_provider: true
        };
        break;
    }

    router.post(endpoint, data, {
      onSuccess: () => {
        setReviewModal(null);
        setReviewNotes('');
        router.reload({ only: ['requests', 'statusCounts'] }); // Reload data for the tab
      }
    });
  };

  const formatCurrency = (amount: number | undefined) => amount !== undefined ? `$${amount.toFixed(2)}` : '$0.00';

  if (!requests || !requests.data) {
    return <div className="p-4">Loading request reviews or no requests found...</div>; 
  }

  return (
    <div className="p-0">
      <div className="mb-8">
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {Object.entries(statusCounts).map(([status, count]) => {
            const config = statusConfig[status as keyof typeof statusConfig];
            if (!config) return null;
            const StatusIcon = config.icon;
            return (
              <div key={status} className="bg-white rounded-lg border border-gray-200 p-4 shadow">
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

      <div className="mb-6 bg-white rounded-lg border border-gray-200 p-4 shadow">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search requests..."
              className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
              defaultValue={filters.search}
              onChange={(e) => {
                router.get(router.page.url, 
                  { ...filters, search: e.target.value },
                  { preserveState: true, replace: true, only: ['requests'] }
                );
              }}
            />
          </div>
          <select
            className="rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
            defaultValue={filters.status}
            onChange={(e) => {
              router.get(router.page.url,
                { ...filters, status: e.target.value },
                { preserveState: true, replace: true, only: ['requests'] }
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
            className="rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
            defaultValue={filters.priority}
            onChange={(e) => {
              router.get(router.page.url,
                { ...filters, priority: e.target.value },
                { preserveState: true, replace: true, only: ['requests'] }
              );
            }}
          >
            <option value="">All Priorities</option>
            <option value="high">High Priority</option>
            <option value="medium">Medium Priority</option>
            <option value="low">Low Priority</option>
          </select>
          <select
            className="rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
            defaultValue={filters.facility}
            onChange={(e) => {
              router.get(router.page.url,
                { ...filters, facility: e.target.value },
                { preserveState: true, replace: true, only: ['requests'] }
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
            className="rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
            defaultValue={filters.days_pending}
            onChange={(e) => {
              router.get(router.page.url,
                { ...filters, days_pending: e.target.value },
                { preserveState: true, replace: true, only: ['requests'] }
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

      {showBulkActions && (
        <div className="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4 shadow">
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
                className="px-3 py-1.5 bg-green-600 text-white text-xs rounded-md hover:bg-green-700"
              >
                Approve Selected
              </button>
              <button
                onClick={() => handleBulkAction('request_info')}
                className="px-3 py-1.5 bg-yellow-500 text-white text-xs rounded-md hover:bg-yellow-600"
              >
                Request Info
              </button>
              <button
                onClick={() => handleBulkAction('reject')}
                className="px-3 py-1.5 bg-red-600 text-white text-xs rounded-md hover:bg-red-700"
              >
                Reject Selected
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="bg-white rounded-lg border border-gray-200 overflow-hidden shadow">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    checked={requests.data.length > 0 && selectedRequests.size === requests.data.length}
                    onChange={selectAllRequests}
                    className="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                  />
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Details</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider & Facility</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clinical Status</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority & Timeline</th>
                {roleRestrictions?.can_view_financials && (
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Value</th>
                )}
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {requests.data.map((request) => {
                const statusInfo = statusConfig[request.order_status as keyof typeof statusConfig] || { color: 'bg-gray-100 text-gray-800', label: request.order_status || 'Unknown' };
                const priorityInfo = getPriorityBadge(request.priority_score);
                const isExpanded = expandedRequest === request.id;

                return (
                  <React.Fragment key={request.id}>
                    <tr className={`hover:bg-gray-50 ${selectedRequests.has(request.id) ? 'bg-red-50' : ''}`}>
                      <td className="px-4 py-3">
                        <input
                          type="checkbox"
                          checked={selectedRequests.has(request.id)}
                          onChange={() => toggleRequestSelection(request.id)}
                          className="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                        />
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-between">
                          <div>
                            <Link href={`/product-requests/review/${request.id}`} className="text-sm font-medium text-red-600 hover:text-red-700">
                              {request.request_number}
                            </Link>
                            <div className="text-xs text-gray-500">Patient: {request.patient_display}</div>
                            <div className="text-xs text-gray-500">Wound: {request.wound_type}</div>
                            <div className="mt-1">
                              <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ${statusInfo.color}`}>
                                {statusInfo.label}
                              </span>
                            </div>
                          </div>
                          <button
                            onClick={() => setExpandedRequest(isExpanded ? null : request.id)}
                            className="text-gray-400 hover:text-gray-600 p-1"
                          >
                            {isExpanded ? <ChevronUp className="h-5 w-5" /> : <ChevronDown className="h-5 w-5" />}
                          </button>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div>
                          <div className="text-sm font-medium text-gray-900">Dr. {request.provider.name}</div>
                          <div className="text-xs text-gray-500">
                            {request.provider.npi_number && `NPI: ${request.provider.npi_number}`}
                          </div>
                          <div className="text-xs text-gray-500 mt-1">{request.facility.name}</div>
                          <div className="text-xs text-gray-400">{request.facility.city}, {request.facility.state}</div>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="space-y-1 text-xs">
                          <div className="flex items-center">
                            <span className="text-gray-500">MAC:</span>
                            <span className={`ml-1.5 px-1.5 py-0.5 rounded-full ${
                              request.mac_validation_status === 'passed'
                                ? 'bg-green-100 text-green-800'
                                : request.mac_validation_status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'
                            }`}>
                              {request.mac_validation_status || 'N/A'}
                            </span>
                          </div>
                          <div className="flex items-center">
                            <span className="text-gray-500">Elig:</span>
                            <span className={`ml-1.5 px-1.5 py-0.5 rounded-full ${
                              request.eligibility_status === 'verified' || request.eligibility_status === 'eligible'
                                ? 'bg-green-100 text-green-800'
                                : request.eligibility_status === 'not_eligible' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'
                            }`}>
                              {request.eligibility_status || 'N/A'}
                            </span>
                          </div>
                          {request.pre_auth_required && (
                            <div className="text-xs text-orange-600 font-semibold">PA Required</div>
                          )}
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div>
                          <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ${priorityInfo.color}`}>
                            {priorityInfo.label} Priority
                          </span>
                          <div className="text-xs text-gray-500 mt-1">Submitted {request.days_since_submission} days ago</div>
                          <div className="text-xs text-gray-500">Service Date: {new Date(request.expected_service_date).toLocaleDateString()}</div>
                        </div>
                      </td>
                      {roleRestrictions?.can_view_financials && (
                        <td className="px-4 py-3">
                          <div className="text-sm font-medium text-gray-900">
                            {formatCurrency(request.total_order_value)}
                          </div>
                          <div className="text-xs text-gray-500">
                            {request.products_count} product{request.products_count !== 1 ? 's' : ''}
                          </div>
                        </td>
                      )}
                      <td className="px-4 py-3">
                        <div className="flex space-x-1.5">
                          <Link
                            href={`/product-requests/review/${request.id}`}
                            className="p-1 text-blue-600 hover:text-blue-900 hover:bg-blue-100 rounded-md"
                            title="View Details"
                          >
                            <Eye className="h-4 w-4" />
                          </Link>
                          {request.order_status === 'submitted' && (
                            <>
                              <button
                                onClick={() => setReviewModal({ requestId: request.id, action: 'approve' })}
                                className="p-1 text-green-600 hover:text-green-900 hover:bg-green-100 rounded-md"
                                title="Approve"
                              >
                                <CheckCircle className="h-4 w-4" />
                              </button>
                              <button
                                onClick={() => setReviewModal({ requestId: request.id, action: 'request_info' })}
                                className="p-1 text-yellow-500 hover:text-yellow-700 hover:bg-yellow-100 rounded-md"
                                title="Request Info"
                              >
                                <MessageSquare className="h-4 w-4" />
                              </button>
                              <button
                                onClick={() => setReviewModal({ requestId: request.id, action: 'reject' })}
                                className="p-1 text-red-600 hover:text-red-900 hover:bg-red-100 rounded-md"
                                title="Reject"
                              >
                                <XCircle className="h-4 w-4" />
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                    {isExpanded && (
                      <tr>
                        <td colSpan={roleRestrictions?.can_view_financials ? 7 : 6} className="p-4 bg-gray-50 border-b border-gray-200">
                          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div>
                              <h5 className="font-semibold text-gray-700 mb-1">Clinical Summary</h5>
                              {request.clinical_summary ? (
                                <div className="space-y-0.5 text-gray-600">
                                  <p><strong>Diagnosis:</strong> {request.clinical_summary.diagnosis || 'N/A'}</p>
                                  <p><strong>Wound Size:</strong> {request.clinical_summary.wound_dimensions || 'N/A'}</p>
                                  <p><strong>Duration:</strong> {request.clinical_summary.wound_duration || 'N/A'}</p>
                                  {/* TODO: Add more clinical summary details here from request.clinical_summary */}
                                </div>
                              ) : <p className="text-gray-400 italic">No clinical summary provided</p>}
                            </div>
                            <div>
                              <h5 className="font-semibold text-gray-700 mb-1">Insurance & Dates</h5>
                              <div className="space-y-0.5 text-gray-600">
                                <p><strong>Payer:</strong> {request.payer_name}</p>
                                <p><strong>Service Date:</strong> {new Date(request.expected_service_date).toLocaleDateString()}</p>
                                <p><strong>Submitted:</strong> {new Date(request.submitted_at).toLocaleString()}</p>
                              </div>
                            </div>
                            <div>
                              <h5 className="font-semibold text-gray-700 mb-1">Quick Actions</h5>
                              <div className="space-y-1.5 mt-2">
                                <Link
                                  href={`/product-requests/review/${request.id}`}
                                  className="block w-full px-3 py-1.5 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700 text-center shadow-sm"
                                >
                                  View Full Details / Documents
                                </Link>
                                {/* Add other quick actions like 'View FHIR Data', 'View Patient Record' if applicable */}
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                );
              })}
              {requests.data.length === 0 && (
                <tr>
                  <td colSpan={roleRestrictions?.can_view_financials ? 7 : 6} className="px-6 py-12 text-center text-gray-500">
                    No product requests match your current filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {requests.links && requests.data.length > 0 && (
          <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div className="flex items-center justify-between">
              <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p className="text-sm text-gray-700">
                    Showing <span className="font-medium">{((requests.current_page - 1) * requests.per_page) + 1}</span>
                    to <span className="font-medium">{Math.min(requests.current_page * requests.per_page, requests.total)}</span>
                    of <span className="font-medium">{requests.total}</span> results
                  </p>
                </div>
                <div>
                  <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    {requests.links.map((link, index) => (
                      <Link
                        key={index}
                        href={link.url || '#'}
                        preserveScroll
                        preserveState
                        className={`relative inline-flex items-center px-3 py-1.5 border text-xs font-medium 
                          ${link.active ? 'z-10 bg-red-50 border-red-500 text-red-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'}
                          ${index === 0 ? 'rounded-l-md' : ''}
                          ${index === requests.links.length - 1 ? 'rounded-r-md' : ''}
                          ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
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
        <div className="fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
          <div className="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-auto">
            <div className="p-5">
              <h3 className="text-lg font-semibold text-gray-900 mb-3">
                {reviewModal.action === 'approve' && 'Approve Request'}
                {reviewModal.action === 'reject' && 'Reject Request'}
                {reviewModal.action === 'request_info' && 'Request Additional Information'}
              </h3>
              <div className="mb-4">
                <label htmlFor="reviewNotesArea" className="block text-sm font-medium text-gray-700 mb-1.5">
                  {reviewModal.action === 'approve' && 'Approval Notes (Optional)'}
                  {reviewModal.action === 'reject' && 'Rejection Reason (Required)'}
                  {reviewModal.action === 'request_info' && 'Information Needed (Required)'}
                </label>
                <textarea
                  id="reviewNotesArea"
                  value={reviewNotes}
                  onChange={(e) => setReviewNotes(e.target.value)}
                  rows={4}
                  className="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                  placeholder={
                    reviewModal.action === 'approve'
                      ? 'Optional notes for approval...'
                      : reviewModal.action === 'reject'
                      ? 'Please specify the reason for rejection...'
                      : 'Please specify what additional information is needed...'
                  }
                />
              </div>
              <div className="flex justify-end space-x-2 pt-4 border-t border-gray-200">
                <button
                  type="button"
                  onClick={() => {
                    setReviewModal(null);
                    setReviewNotes('');
                  }}
                  className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={() => handleSingleAction(reviewModal.requestId, reviewModal.action)}
                  disabled={ (reviewModal.action === 'reject' || reviewModal.action === 'request_info') && !reviewNotes.trim() }
                  className={`px-4 py-2 text-sm font-medium text-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2
                    ${reviewModal.action === 'approve' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' 
                    : reviewModal.action === 'reject' ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' 
                    : 'bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400'}
                    disabled:opacity-60 disabled:cursor-not-allowed`}
                >
                  {reviewModal.action === 'approve' && 'Confirm Approve'}
                  {reviewModal.action === 'reject' && 'Confirm Reject'}
                  {reviewModal.action === 'request_info' && 'Send Request'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// The main OrderCenter component, which includes tabs for RequestReviews and OrderManagement

interface Tab {
  id: string;
  name: string;
  icon: React.ComponentType<any>;
  content: React.ComponentType<any>; // Content component can accept ReviewTabProps or other specific props
}

// Props for OrderCenter might be a combination of what OrderManagement and RequestReviews need.
// It should align with what the backend controller for the '/orders/center' (or similar) route provides.
interface OrderCenterProps extends ReviewTabProps { // Assuming OrderCenter will receive all props needed by ReviewTabProps
  // Add other props as necessary for Order Management tab if any, e.g.,
  // activeOrders?: any[];
}

const OrderCenter: React.FC<OrderCenterProps> = (props) => {
  const [activeTab, setActiveTab] = useState<string>('requestReviews');

  const tabs: Tab[] = [
    {
      id: 'requestReviews',
      name: 'Request Reviews',
      icon: Eye, // Changed from FiEye for consistency with lucide-react imports above
      content: RequestReviewsTabContent, // RequestReviewsTabContent expects ReviewTabProps
    },
    {
      id: 'orderManagement',
      name: 'Order Management',
      icon: ShoppingCart, // Using lucide-react ShoppingCart
      content: OrderManagementTabContent,
    },
  ];

  const ActiveTabContent = tabs.find(tab => tab.id === activeTab)?.content || (() => <div className="p-4">Select a tab</div>);

  return (
    <MainLayout>
      <Head title="Order Center" />
      <div className="p-4 sm:p-6 lg:p-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Order Center</h1>
          <p className="mt-1 text-sm text-gray-600">
            Manage product requests and active orders.
          </p>
        </div>

        <div className="mb-6 border-b border-gray-200">
          <nav className="-mb-px flex space-x-6" aria-label="Tabs">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`group inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm focus:outline-none
                  ${activeTab === tab.id
                    ? 'border-red-500 text-red-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}`}
                aria-current={activeTab === tab.id ? 'page' : undefined}
              >
                <tab.icon className={`-ml-0.5 mr-1.5 h-5 w-5 ${activeTab === tab.id ? 'text-red-500' : 'text-gray-400 group-hover:text-gray-500'}`} aria-hidden="true" />
                <span>{tab.name}</span>
              </button>
            ))}
          </nav>
        </div>
        <div>
          {/* Pass all props to the active tab. Ensure 'props' matches what ActiveTabContent expects. */}
          {/* If ActiveTabContent is RequestReviewsTabContent, it expects ReviewTabProps. */}
          {/* If ActiveTabContent is OrderManagementTabContent, it currently expects no props. */}
          <ActiveTabContent {...props} /> 
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderCenter; 