import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  Plus,
  Search,
  Filter,
  Calendar,
  FileText,
  Clock,
  CheckCircle,
  AlertTriangle,
  Eye,
  Edit,
  MoreHorizontal
} from 'lucide-react';

interface Request {
  id: number;
  request_number: string;
  patient_name: string;
  wound_type: string;
  status: 'draft' | 'submitted' | 'in_review' | 'approved' | 'rejected';
  created_at: string;
  expected_service_date: string;
  facility_name: string;
  total_amount?: number;
}

interface Props {
  requests: {
    data: Request[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    search?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
  };
}

const RequestsIndex: React.FC<Props> = ({ requests, filters }) => {
  const [searchTerm, setSearchTerm] = useState(filters?.search || '');
  const [statusFilter, setStatusFilter] = useState(filters?.status || '');
  const [showFilters, setShowFilters] = useState(false);

  // Provide default values if requests is undefined
  const requestsData = requests || {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  };

  const filtersData = filters || {};

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/requests', {
      ...filtersData,
      search: searchTerm,
      page: 1
    }, {
      preserveState: true
    });
  };

  const handleStatusFilter = (status: string) => {
    setStatusFilter(status);
    router.get('/requests', {
      ...filtersData,
      status: status === 'all' ? '' : status,
      page: 1
    }, {
      preserveState: true
    });
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'draft':
        return 'bg-gray-100 text-gray-800';
      case 'submitted':
        return 'bg-blue-100 text-blue-800';
      case 'in_review':
        return 'bg-yellow-100 text-yellow-800';
      case 'approved':
        return 'bg-green-100 text-green-800';
      case 'rejected':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'draft':
        return <FileText className="h-4 w-4" />;
      case 'submitted':
        return <Clock className="h-4 w-4" />;
      case 'in_review':
        return <AlertTriangle className="h-4 w-4" />;
      case 'approved':
        return <CheckCircle className="h-4 w-4" />;
      case 'rejected':
        return <AlertTriangle className="h-4 w-4" />;
      default:
        return <FileText className="h-4 w-4" />;
    }
  };

  return (
    <MainLayout title="My Requests">
      <Head title="My Requests" />

      <div className="min-h-screen bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto py-6">
          {/* Header */}
          <div className="mb-8">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h1 className="text-2xl font-semibold text-gray-900">My Requests</h1>
                <p className="mt-2 text-sm text-gray-600">
                  Manage and track your product requests
                </p>
              </div>
              <div className="mt-4 sm:mt-0">
                <Link
                  href="/product-requests/create"
                  className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <Plus className="h-4 w-4 mr-2" />
                  New Request
                </Link>
              </div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div className="p-4">
              <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-4">
                <div className="flex-1">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                      type="text"
                      placeholder="Search by patient name, request number..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </div>
                <div className="flex gap-2">
                  <button
                    type="submit"
                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    Search
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowFilters(!showFilters)}
                    className="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <Filter className="h-4 w-4" />
                  </button>
                </div>
              </form>

              {/* Status Filter Tabs */}
              <div className="mt-4 border-b border-gray-200">
                <nav className="-mb-px flex space-x-8">
                  {[
                    { key: 'all', label: 'All', count: requestsData.total },
                    { key: 'draft', label: 'Draft', count: 0 },
                    { key: 'submitted', label: 'Submitted', count: 0 },
                    { key: 'in_review', label: 'In Review', count: 0 },
                    { key: 'approved', label: 'Approved', count: 0 },
                    { key: 'rejected', label: 'Rejected', count: 0 }
                  ].map((tab) => (
                    <button
                      key={tab.key}
                      onClick={() => handleStatusFilter(tab.key)}
                      className={`py-2 px-1 border-b-2 font-medium text-sm ${
                        (statusFilter === tab.key || (statusFilter === '' && tab.key === 'all'))
                          ? 'border-blue-500 text-blue-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      }`}
                    >
                      {tab.label}
                      {tab.count > 0 && (
                        <span className="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs">
                          {tab.count}
                        </span>
                      )}
                    </button>
                  ))}
                </nav>
              </div>
            </div>
          </div>

          {/* Requests Table */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            {requestsData.data.length > 0 ? (
              <>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Request
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Patient
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Wound Type
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Status
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Service Date
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Facility
                        </th>
                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Actions
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {requestsData.data.map((request) => (
                        <tr key={request.id} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div>
                              <div className="text-sm font-medium text-gray-900">
                                {request.request_number}
                              </div>
                              <div className="text-sm text-gray-500">
                                {new Date(request.created_at).toLocaleDateString()}
                              </div>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">
                              {request.patient_name}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-gray-900">
                              {request.wound_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(request.status)}`}>
                              {getStatusIcon(request.status)}
                              <span className="ml-1">
                                {request.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                              </span>
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {new Date(request.expected_service_date).toLocaleDateString()}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {request.facility_name}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div className="flex items-center justify-end space-x-2">
                              <Link
                                href={`/requests/${request.id}`}
                                className="text-blue-600 hover:text-blue-900"
                              >
                                <Eye className="h-4 w-4" />
                              </Link>
                              {request.status === 'draft' && (
                                <Link
                                  href={`/product-requests/${request.id}/edit`}
                                  className="text-gray-600 hover:text-gray-900"
                                >
                                  <Edit className="h-4 w-4" />
                                </Link>
                              )}
                              <button className="text-gray-400 hover:text-gray-600">
                                <MoreHorizontal className="h-4 w-4" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Pagination */}
                {requestsData.last_page > 1 && (
                  <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div className="flex-1 flex justify-between sm:hidden">
                      <button className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                      </button>
                      <button className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                      </button>
                    </div>
                    <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                      <div>
                        <p className="text-sm text-gray-700">
                          Showing{' '}
                          <span className="font-medium">
                            {(requestsData.current_page - 1) * requestsData.per_page + 1}
                          </span>{' '}
                          to{' '}
                          <span className="font-medium">
                            {Math.min(requestsData.current_page * requestsData.per_page, requestsData.total)}
                          </span>{' '}
                          of{' '}
                          <span className="font-medium">{requestsData.total}</span> results
                        </p>
                      </div>
                      <div>
                        <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                          {/* Pagination buttons would go here */}
                        </nav>
                      </div>
                    </div>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center py-12">
                <FileText className="mx-auto h-12 w-12 text-gray-400" />
                <h3 className="mt-2 text-sm font-medium text-gray-900">No requests found</h3>
                <p className="mt-1 text-sm text-gray-500">
                  Get started by creating a new product request.
                </p>
                <div className="mt-6">
                  <Link
                    href="/product-requests/create"
                    className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                  >
                    <Plus className="h-4 w-4 mr-2" />
                    New Request
                  </Link>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default RequestsIndex;
