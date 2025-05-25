import React, { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import FilterBar from '@/Components/FilterBar/FilterBar';
import Pagination from '@/Components/Pagination/Pagination';
import { ChevronDownIcon, ChevronUpIcon, FunnelIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { CheckCircleIcon, ClockIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/react/24/solid';

interface ProductRequest {
  id: number;
  request_number: string;
  patient_display: string;
  patient_fhir_id: string;
  order_status: string;
  step: number;
  step_description: string;
  facility_name: string;
  created_at: string;
  total_products: number;
  total_amount: number | string | null;
  mac_validation_status?: string;
  eligibility_status?: string;
  pre_auth_required?: boolean;
  submitted_at?: string;
  approved_at?: string;
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
    facility?: string;
    date_from?: string;
    date_to?: string;
  };
  facilities: Array<{ id: number; name: string }>;
  statusOptions: Array<{ value: string; label: string; count: number }>;
}

const ProductRequestIndex: React.FC<Props> = ({ requests, filters, facilities, statusOptions }) => {
  const { auth } = usePage<any>().props;
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const [selectedRequests, setSelectedRequests] = useState<Set<number>>(new Set());
  const [showFilters, setShowFilters] = useState(false);

  // Status configuration with colors and icons
  const statusConfig = {
    draft: {
      color: 'bg-gray-100 text-gray-800',
      icon: ClockIcon,
      label: 'Draft'
    },
    submitted: {
      color: 'bg-blue-100 text-blue-800',
      icon: ClockIcon,
      label: 'Submitted'
    },
    processing: {
      color: 'bg-yellow-100 text-yellow-800',
      icon: ClockIcon,
      label: 'Processing'
    },
    approved: {
      color: 'bg-green-100 text-green-800',
      icon: CheckCircleIcon,
      label: 'Approved'
    },
    rejected: {
      color: 'bg-red-100 text-red-800',
      icon: XCircleIcon,
      label: 'Rejected'
    },
    shipped: {
      color: 'bg-purple-100 text-purple-800',
      icon: CheckCircleIcon,
      label: 'Shipped'
    },
    delivered: {
      color: 'bg-green-100 text-green-800',
      icon: CheckCircleIcon,
      label: 'Delivered'
    },
    cancelled: {
      color: 'bg-red-100 text-red-800',
      icon: XCircleIcon,
      label: 'Cancelled'
    },
  };

  const getStatusConfig = (status: string) => {
    return statusConfig[status as keyof typeof statusConfig] || statusConfig.draft;
  };

  const getStepProgress = (step: number): number => {
    return Math.round((step / 6) * 100);
  };

  const formatCurrency = (amount: number | string | null): string => {
    const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount || '0');
    return isNaN(numericAmount) ? '$0.00' : `$${numericAmount.toFixed(2)}`;
  };

  const toggleRowExpansion = (id: number) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(id)) {
      newExpanded.delete(id);
    } else {
      newExpanded.add(id);
    }
    setExpandedRows(newExpanded);
  };

  const toggleRequestSelection = (id: number) => {
    const newSelected = new Set(selectedRequests);
    if (newSelected.has(id)) {
      newSelected.delete(id);
    } else {
      newSelected.add(id);
    }
    setSelectedRequests(newSelected);
  };

  const toggleAllRequests = () => {
    if (selectedRequests.size === requests.data.length) {
      setSelectedRequests(new Set());
    } else {
      setSelectedRequests(new Set(requests.data.map(r => r.id)));
    }
  };

  const handleFilter = (key: string, value: string) => {
    router.get(route('product-requests.index'), {
      ...filters,
      [key]: value,
      page: 1
    }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const clearFilters = () => {
    router.get(route('product-requests.index'), {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const hasActiveFilters = Object.values(filters).some(value => value);

  return (
    <MainLayout title="My Requests">
      <div className="min-h-screen bg-gray-50">
        {/* Header Section */}
        <div className="bg-white shadow-sm border-b border-gray-200">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="py-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">My Product Requests</h1>
                  <p className="mt-1 text-sm text-gray-600">
                    Manage your wound care product requests through the MSC-MVP workflow
                  </p>
                </div>
                <div className="mt-4 sm:mt-0 flex space-x-3">
                  <button
                    onClick={() => setShowFilters(!showFilters)}
                    className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                  >
                    <FunnelIcon className="h-4 w-4 mr-2" />
                    Filters
                    {hasActiveFilters && (
                      <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {Object.values(filters).filter(v => v).length}
                      </span>
                    )}
                  </button>
                  <Link
                    href="/product-requests/create"
                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                  >
                    + New Request
                  </Link>
                </div>
              </div>

              {/* Quick Stats */}
              <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                {statusOptions.map((option) => (
                  <button
                    key={option.value}
                    onClick={() => handleFilter('status', option.value)}
                    className={`relative rounded-lg border p-4 text-left hover:shadow-md transition-shadow ${
                      filters.status === option.value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'
                    }`}
                  >
                    <p className="text-sm font-medium text-gray-600">{option.label}</p>
                    <p className="mt-1 text-2xl font-semibold text-gray-900">{option.count}</p>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Filters Section */}
        {showFilters && (
          <div className="bg-white border-b border-gray-200">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                  <label className="block text-sm font-medium text-gray-700">Search</label>
                  <input
                    type="text"
                    value={filters.search || ''}
                    onChange={(e) => handleFilter('search', e.target.value)}
                    placeholder="Request #, Patient ID..."
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Status</label>
                  <select
                    value={filters.status || ''}
                    onChange={(e) => handleFilter('status', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  >
                    <option value="">All Statuses</option>
                    {Object.entries(statusConfig).map(([value, config]) => (
                      <option key={value} value={value}>{config.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Facility</label>
                  <select
                    value={filters.facility || ''}
                    onChange={(e) => handleFilter('facility', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  >
                    <option value="">All Facilities</option>
                    {facilities.map((facility) => (
                      <option key={facility.id} value={facility.id}>{facility.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Date From</label>
                  <input
                    type="date"
                    value={filters.date_from || ''}
                    onChange={(e) => handleFilter('date_from', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Date To</label>
                  <input
                    type="date"
                    value={filters.date_to || ''}
                    onChange={(e) => handleFilter('date_to', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                  />
                </div>
              </div>
              {hasActiveFilters && (
                <div className="mt-4">
                  <button
                    onClick={clearFilters}
                    className="text-sm text-blue-600 hover:text-blue-800"
                  >
                    Clear all filters
                  </button>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Main Content */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="bg-white shadow-sm rounded-lg overflow-hidden">
            {/* Table */}
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="w-12 px-6 py-3">
                      <input
                        type="checkbox"
                        checked={selectedRequests.size === requests.data.length && requests.data.length > 0}
                        onChange={toggleAllRequests}
                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Request Details
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Patient
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status & Progress
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Validation
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Products & Total
                    </th>
                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {requests.data.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center">
                        <div className="text-gray-500">
                          <p className="text-lg font-medium">No product requests found</p>
                          <p className="mt-1">Get started by creating your first product request.</p>
                          <Link
                            href="/product-requests/create"
                            className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                          >
                            + New Request
                          </Link>
                        </div>
                      </td>
                    </tr>
                  ) : (
                    requests.data.map((request) => {
                      const statusInfo = getStatusConfig(request.order_status);
                      const StatusIcon = statusInfo.icon;
                      const isExpanded = expandedRows.has(request.id);

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
                              <div>
                                <div className="text-sm font-medium text-gray-900">
                                  {request.request_number}
                                </div>
                                <div className="text-sm text-gray-500">
                                  {request.facility_name}
                                </div>
                                <div className="text-xs text-gray-400 mt-1">
                                  Created: {request.created_at}
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div>
                                <div className="text-sm font-medium text-gray-900">
                                  {request.patient_display}
                                </div>
                                <div className="text-xs text-gray-500">
                                  Sequential ID
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="space-y-2">
                                <div className="flex items-center">
                                  <StatusIcon className="h-5 w-5 mr-2" />
                                  <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusInfo.color}`}>
                                    {statusInfo.label}
                                  </span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                  <div
                                    className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                    style={{ width: `${getStepProgress(request.step)}%` }}
                                  />
                                </div>
                                <div className="text-xs text-gray-600">
                                  Step {request.step}/6: {request.step_description}
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div className="space-y-1">
                                {request.mac_validation_status && (
                                  <div className="flex items-center text-xs">
                                    {request.mac_validation_status === 'passed' ? (
                                      <CheckCircleIcon className="h-4 w-4 text-green-500 mr-1" />
                                    ) : (
                                      <ExclamationTriangleIcon className="h-4 w-4 text-yellow-500 mr-1" />
                                    )}
                                    <span>MAC: {request.mac_validation_status}</span>
                                  </div>
                                )}
                                {request.eligibility_status && (
                                  <div className="flex items-center text-xs">
                                    {request.eligibility_status === 'eligible' ? (
                                      <CheckCircleIcon className="h-4 w-4 text-green-500 mr-1" />
                                    ) : (
                                      <XCircleIcon className="h-4 w-4 text-red-500 mr-1" />
                                    )}
                                    <span>Eligibility: {request.eligibility_status}</span>
                                  </div>
                                )}
                                {request.pre_auth_required && (
                                  <div className="flex items-center text-xs">
                                    <ExclamationTriangleIcon className="h-4 w-4 text-yellow-500 mr-1" />
                                    <span>PA Required</span>
                                  </div>
                                )}
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <div>
                                <div className="text-sm font-medium text-gray-900">
                                  {request.total_products} product{request.total_products !== 1 ? 's' : ''}
                                </div>
                                <div className="text-sm text-gray-900 font-semibold">
                                  {formatCurrency(request.total_amount)}
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4 text-right">
                              <div className="flex items-center space-x-2">
                                <Link
                                  href={`/product-requests/${request.id}`}
                                  className="text-blue-600 hover:text-blue-900 text-sm font-medium"
                                >
                                  View
                                </Link>
                                <button
                                  onClick={() => toggleRowExpansion(request.id)}
                                  className="text-gray-400 hover:text-gray-600"
                                >
                                  {isExpanded ? (
                                    <ChevronUpIcon className="h-5 w-5" />
                                  ) : (
                                    <ChevronDownIcon className="h-5 w-5" />
                                  )}
                                </button>
                              </div>
                            </td>
                          </tr>
                          {isExpanded && (
                            <tr>
                              <td colSpan={7} className="px-6 py-4 bg-gray-50">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                  <div>
                                    <h4 className="text-sm font-medium text-gray-900 mb-2">Timeline</h4>
                                    <div className="space-y-1 text-xs text-gray-600">
                                      <div>Created: {request.created_at}</div>
                                      {request.submitted_at && <div>Submitted: {request.submitted_at}</div>}
                                      {request.approved_at && <div>Approved: {request.approved_at}</div>}
                                    </div>
                                  </div>
                                  <div>
                                    <h4 className="text-sm font-medium text-gray-900 mb-2">Clinical Details</h4>
                                    <div className="space-y-1 text-xs text-gray-600">
                                      <div>Wound Type: {request.wound_type || 'Not specified'}</div>
                                      <div>Service Date: {request.expected_service_date || 'Not set'}</div>
                                    </div>
                                  </div>
                                  <div>
                                    <h4 className="text-sm font-medium text-gray-900 mb-2">Quick Actions</h4>
                                    <div className="space-x-2">
                                      <Link
                                        href={`/product-requests/${request.id}/edit`}
                                        className="text-sm text-blue-600 hover:text-blue-800"
                                      >
                                        Edit
                                      </Link>
                                      <Link
                                        href={`/product-requests/${request.id}/duplicate`}
                                        className="text-sm text-blue-600 hover:text-blue-800"
                                      >
                                        Duplicate
                                      </Link>
                                      {request.order_status === 'draft' && (
                                        <button
                                          onClick={() => router.delete(`/product-requests/${request.id}`)}
                                          className="text-sm text-red-600 hover:text-red-800"
                                        >
                                          Delete
                                        </button>
                                      )}
                                    </div>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          )}
                        </React.Fragment>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {requests.data.length > 0 && (
              <div className="px-6 py-4 border-t border-gray-200">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-gray-700">
                    Showing {(requests.current_page - 1) * requests.per_page + 1} to{' '}
                    {Math.min(requests.current_page * requests.per_page, requests.total)} of{' '}
                    {requests.total} results
                  </div>
                  <Pagination links={requests.links} />
                </div>
              </div>
            )}
          </div>

          {/* Bulk Actions */}
          {selectedRequests.size > 0 && (
            <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg">
              <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div className="flex items-center justify-between">
                  <div className="text-sm text-gray-700">
                    {selectedRequests.size} request{selectedRequests.size !== 1 ? 's' : ''} selected
                  </div>
                  <div className="space-x-3">
                    <button
                      onClick={() => setSelectedRequests(new Set())}
                      className="text-sm text-gray-600 hover:text-gray-800"
                    >
                      Cancel
                    </button>
                    <button className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                      Export
                    </button>
                    <button className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                      Print
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
};

export default ProductRequestIndex;

