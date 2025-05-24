import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiSearch,
  FiFilter,
  FiEye,
  FiCheck,
  FiX,
  FiClock,
  FiUser,
  FiMail,
  FiCalendar,
  FiFileText,
  FiUsers,
  FiChevronDown
} from 'react-icons/fi';

interface AccessRequest {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  requested_role: string;
  status: 'pending' | 'approved' | 'denied';
  created_at: string;
  request_notes?: string;
  reviewed_at?: string;
  reviewed_by?: {
    first_name: string;
    last_name: string;
  };
}

interface Props {
  accessRequests: {
    data: AccessRequest[];
    meta: any;
    links: any;
  };
  filters: {
    status?: string;
    role?: string;
    search?: string;
  };
  roles: Record<string, string>;
}

export default function AccessRequestsIndex({ accessRequests, filters, roles }: Props) {
  const [showFilters, setShowFilters] = useState(false);
  const [selectedRequests, setSelectedRequests] = useState<number[]>([]);

  const { data, setData, get, processing } = useForm({
    search: filters.search || '',
    status: filters.status || 'pending',
    role: filters.role || '',
  });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    get(route('access-requests.index'), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleFilterChange = (key: string, value: string) => {
    setData(key as any, value);
    get(route('access-requests.index', { ...data, [key]: value }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const getStatusBadge = (status: string) => {
    const statusConfig = {
      pending: {
        bg: 'bg-yellow-100',
        text: 'text-yellow-800',
        icon: FiClock,
        label: 'Pending'
      },
      approved: {
        bg: 'bg-green-100',
        text: 'text-green-800',
        icon: FiCheck,
        label: 'Approved'
      },
      denied: {
        bg: 'bg-red-100',
        text: 'text-red-800',
        icon: FiX,
        label: 'Denied'
      },
    };

    const config = statusConfig[status as keyof typeof statusConfig];
    const Icon = config.icon;

    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
        <Icon className="mr-1 h-3 w-3" />
        {config.label}
      </span>
    );
  };

  const getRoleBadge = (role: string) => {
    const roleColors = {
      provider: 'bg-blue-100 text-blue-800',
      office_manager: 'bg-purple-100 text-purple-800',
      msc_rep: 'bg-green-100 text-green-800',
      msc_subrep: 'bg-indigo-100 text-indigo-800',
      msc_admin: 'bg-gray-100 text-gray-800',
    };

    return (
      <span className={`inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ${roleColors[role as keyof typeof roleColors] || 'bg-gray-100 text-gray-800'}`}>
        {roles[role] || role}
      </span>
    );
  };

  const toggleRequestSelection = (requestId: number) => {
    setSelectedRequests(prev =>
      prev.includes(requestId)
        ? prev.filter(id => id !== requestId)
        : [...prev, requestId]
    );
  };

  const toggleSelectAll = () => {
    if (selectedRequests.length === accessRequests.data.length) {
      setSelectedRequests([]);
    } else {
      setSelectedRequests(accessRequests.data.map(req => req.id));
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const pendingCount = accessRequests.data.filter(req => req.status === 'pending').length;

  return (
    <MainLayout>
      <Head title="Access Requests" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="md:flex md:items-center md:justify-between mb-6">
            <div className="min-w-0 flex-1">
              <h1 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate">
                Access Requests
              </h1>
              <div className="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                <div className="mt-2 flex items-center text-sm text-gray-500">
                  <FiUsers className="mr-1.5 h-4 w-4" />
                  {accessRequests.data.length} total requests
                </div>
                {pendingCount > 0 && (
                  <div className="mt-2 flex items-center text-sm text-yellow-600">
                    <FiClock className="mr-1.5 h-4 w-4" />
                    {pendingCount} pending review
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Filters */}
          <div className="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
            <div className="p-4">
              <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {/* Search */}
                <form onSubmit={handleSearch} className="flex-1 max-w-lg">
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <FiSearch className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="text"
                      value={data.search}
                      onChange={(e) => setData('search', e.target.value)}
                      placeholder="Search by name, email, or facility..."
                      className="block w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </form>

                {/* Status and Role Filters */}
                <div className="flex gap-3">
                  <select
                    value={data.status}
                    onChange={(e) => handleFilterChange('status', e.target.value)}
                    className="block w-full py-2 px-3 border border-gray-300 bg-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="denied">Denied</option>
                  </select>

                  <select
                    value={data.role}
                    onChange={(e) => handleFilterChange('role', e.target.value)}
                    className="block w-full py-2 px-3 border border-gray-300 bg-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">All Roles</option>
                    {Object.entries(roles).map(([key, label]) => (
                      <option key={key} value={key}>{label}</option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          </div>

          {/* Results */}
          <div className="bg-white shadow-sm rounded-lg border border-gray-200">
            {accessRequests.data.length === 0 ? (
              <div className="text-center py-12">
                <FiFileText className="mx-auto h-12 w-12 text-gray-400" />
                <h3 className="mt-2 text-sm font-medium text-gray-900">No access requests</h3>
                <p className="mt-1 text-sm text-gray-500">
                  No requests match the current filters.
                </p>
              </div>
            ) : (
              <>
                {/* Bulk Actions Bar */}
                {selectedRequests.length > 0 && (
                  <div className="bg-blue-50 border-b border-blue-200 px-6 py-3">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center">
                        <span className="text-sm text-blue-700">
                          {selectedRequests.length} selected
                        </span>
                      </div>
                      <div className="flex space-x-2">
                        <button
                          type="button"
                          className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200"
                        >
                          <FiCheck className="mr-1 h-3 w-3" />
                          Bulk Approve
                        </button>
                        <button
                          type="button"
                          className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200"
                        >
                          <FiX className="mr-1 h-3 w-3" />
                          Bulk Deny
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {/* Table */}
                <div className="overflow-hidden">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left">
                          <input
                            type="checkbox"
                            checked={selectedRequests.length === accessRequests.data.length}
                            onChange={toggleSelectAll}
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          />
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Requester
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Role
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Status
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Submitted
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Actions
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {accessRequests.data.map((request) => (
                        <tr key={request.id} className="hover:bg-gray-50">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <input
                              type="checkbox"
                              checked={selectedRequests.includes(request.id)}
                              onChange={() => toggleRequestSelection(request.id)}
                              className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className="flex-shrink-0 h-10 w-10">
                                <div className="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                  <FiUser className="h-5 w-5 text-gray-500" />
                                </div>
                              </div>
                              <div className="ml-4">
                                <div className="text-sm font-medium text-gray-900">
                                  {request.first_name} {request.last_name}
                                </div>
                                <div className="text-sm text-gray-500 flex items-center">
                                  <FiMail className="mr-1 h-3 w-3" />
                                  {request.email}
                                </div>
                              </div>
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            {getRoleBadge(request.requested_role)}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            {getStatusBadge(request.status)}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div className="flex items-center">
                              <FiCalendar className="mr-1 h-3 w-3" />
                              {formatDate(request.created_at)}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div className="flex space-x-2">
                              <button
                                onClick={() => router.get(route('access-requests.show', request.id))}
                                className="text-blue-600 hover:text-blue-900 flex items-center"
                              >
                                <FiEye className="h-4 w-4" />
                              </button>
                              {request.status === 'pending' && (
                                <>
                                  <button className="text-green-600 hover:text-green-900 flex items-center">
                                    <FiCheck className="h-4 w-4" />
                                  </button>
                                  <button className="text-red-600 hover:text-red-900 flex items-center">
                                    <FiX className="h-4 w-4" />
                                  </button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Pagination */}
                {accessRequests.links && (
                  <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div className="flex items-center justify-between">
                      <div className="flex-1 flex justify-between sm:hidden">
                        {accessRequests.links.prev && (
                          <a
                            href={accessRequests.links.prev}
                            className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                          >
                            Previous
                          </a>
                        )}
                        {accessRequests.links.next && (
                          <a
                            href={accessRequests.links.next}
                            className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                          >
                            Next
                          </a>
                        )}
                      </div>
                      <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                          <p className="text-sm text-gray-700">
                            Showing{' '}
                            <span className="font-medium">{accessRequests.meta?.from || 1}</span>{' '}
                            to{' '}
                            <span className="font-medium">{accessRequests.meta?.to || accessRequests.data.length}</span>{' '}
                            of{' '}
                            <span className="font-medium">{accessRequests.meta?.total || accessRequests.data.length}</span>{' '}
                            results
                          </p>
                        </div>
                        <div>
                          <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            {/* Pagination links would go here */}
                          </nav>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
