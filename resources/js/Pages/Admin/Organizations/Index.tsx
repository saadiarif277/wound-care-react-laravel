import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  Search,
  Building,
  Users,
  MapPin,
  Plus,
  Edit,
  Eye,
  Trash2,
  CheckCircle2,
  Clock,
  AlertTriangle,
  TrendingUp
} from 'lucide-react';

interface Organization {
  id: string;
  name: string;
  type: string;
  status: string;
  contact_email: string;
  phone?: string;
  address?: string;
  facilities_count: number;
  providers_count: number;
  users_count: number;
  created_at: string;
  updated_at: string;
}

interface Props {
  organizations: {
    data: Organization[];
    links: any;
    meta: any;
  };
  filters: {
    search?: string;
    status?: string;
    type?: string;
  };
  summary: {
    total_organizations: number;
    active_organizations: number;
    pending_organizations: number;
    total_facilities: number;
    total_providers: number;
  };
}

export default function OrganizationsIndex({ organizations, filters, summary }: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
  const [selectedType, setSelectedType] = useState(filters.type || '');

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('admin.organizations.index'), {
      search: searchTerm,
      status: selectedStatus,
      type: selectedType
    });
  };

  const handleDelete = (organization: Organization) => {
    if (window.confirm(`Are you sure you want to delete ${organization.name}?`)) {
      router.delete(route('admin.organizations.destroy', organization.id), {
        onSuccess: () => {
          // Page will refresh automatically
        }
      });
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return <Badge className="bg-green-100 text-green-800"><CheckCircle2 className="w-3 h-3 mr-1" />Active</Badge>;
      case 'pending':
        return <Badge className="bg-yellow-100 text-yellow-800"><Clock className="w-3 h-3 mr-1" />Pending</Badge>;
      case 'inactive':
        return <Badge className="bg-red-100 text-red-800"><AlertTriangle className="w-3 h-3 mr-1" />Inactive</Badge>;
      default:
        return <Badge className="bg-gray-100 text-gray-800">{status}</Badge>;
    }
  };

  const getTypeBadge = (type: string) => {
    const typeLabels: Record<string, string> = {
      healthcare: 'Healthcare',
      clinic: 'Clinic',
      hospital: 'Hospital',
      other: 'Other'
    };
    return typeLabels[type] || type;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  return (
    <MainLayout>
      <Head title="Organizations" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-6 flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Organizations</h1>
              <p className="text-gray-600">Manage organizations and their facilities</p>
            </div>
            <Link
              href="/admin/organizations/create"
              className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              <Plus className="w-5 h-5 mr-2" />
              Add Organization
            </Link>
          </div>

          {/* Summary Cards */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Organizations</p>
                  <p className="text-2xl font-bold">{summary.total_organizations}</p>
                </div>
                <Building className="w-8 h-8 text-gray-400" />
              </div>
            </Card>
            
            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Active</p>
                  <p className="text-2xl font-bold text-green-600">{summary.active_organizations}</p>
                </div>
                <CheckCircle2 className="w-8 h-8 text-green-400" />
              </div>
            </Card>

            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Pending</p>
                  <p className="text-2xl font-bold text-yellow-600">{summary.pending_organizations}</p>
                </div>
                <Clock className="w-8 h-8 text-yellow-400" />
              </div>
            </Card>

            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Facilities</p>
                  <p className="text-2xl font-bold">{summary.total_facilities}</p>
                </div>
                <MapPin className="w-8 h-8 text-blue-400" />
              </div>
            </Card>

            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Providers</p>
                  <p className="text-2xl font-bold">{summary.total_providers}</p>
                </div>
                <Users className="w-8 h-8 text-purple-400" />
              </div>
            </Card>
          </div>

          {/* Search and Filters */}
          <Card className="p-4 mb-6">
            <form onSubmit={handleSearch} className="flex flex-wrap gap-4">
              <div className="flex-1 min-w-[300px] relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search organizations by name, email, or phone..."
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                />
              </div>
              
              <select
                value={selectedStatus}
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
              >
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="inactive">Inactive</option>
              </select>

              <select
                value={selectedType}
                onChange={(e) => setSelectedType(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
              >
                <option value="">All Types</option>
                <option value="healthcare">Healthcare</option>
                <option value="clinic">Clinic</option>
                <option value="hospital">Hospital</option>
                <option value="other">Other</option>
              </select>

              <button
                type="submit"
                className="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
              >
                Search
              </button>
            </form>
          </Card>

          {/* Organizations Table */}
          <Card className="overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Organization
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Type
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Facilities
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Providers
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Contact
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {organizations.data.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="px-6 py-8 text-center">
                        <Building className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <p className="text-gray-500">No organizations found</p>
                      </td>
                    </tr>
                  ) : (
                    organizations.data.map((organization) => (
                      <tr key={organization.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div>
                            <div className="text-sm font-medium text-gray-900">{organization.name}</div>
                            {organization.address && (
                              <div className="text-sm text-gray-500">{organization.address}</div>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className="text-sm text-gray-900">{getTypeBadge(organization.type)}</span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          {getStatusBadge(organization.status)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center text-sm text-gray-900">
                            <MapPin className="w-4 h-4 mr-1 text-gray-400" />
                            {organization.facilities_count}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex items-center text-sm text-gray-900">
                            <Users className="w-4 h-4 mr-1 text-gray-400" />
                            {organization.providers_count}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm">
                            <div className="text-gray-900">{organization.contact_email}</div>
                            {organization.phone && (
                              <div className="text-gray-500">{organization.phone}</div>
                            )}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {formatDate(organization.created_at)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                          <div className="flex items-center justify-end space-x-2">
                            <Link
                              href={`/admin/organizations/${organization.id}`}
                              className="text-gray-600 hover:text-gray-900 p-1"
                              title="View details"
                            >
                              <Eye className="w-5 h-5" />
                            </Link>
                            <Link
                              href={`/admin/organizations/${organization.id}/edit`}
                              className="text-blue-600 hover:text-blue-900 p-1"
                              title="Edit"
                            >
                              <Edit className="w-5 h-5" />
                            </Link>
                            <button
                              onClick={() => handleDelete(organization)}
                              className="text-red-600 hover:text-red-900 p-1"
                              title="Delete"
                            >
                              <Trash2 className="w-5 h-5" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {organizations.meta && organizations.meta.last_page > 1 && (
              <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div className="flex-1 flex justify-between sm:hidden">
                  {organizations.links.prev && (
                    <Link
                      href={organizations.links.prev}
                      className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                      Previous
                    </Link>
                  )}
                  {organizations.links.next && (
                    <Link
                      href={organizations.links.next}
                      className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                      Next
                    </Link>
                  )}
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing <span className="font-medium">{organizations.meta.from}</span> to{' '}
                      <span className="font-medium">{organizations.meta.to}</span> of{' '}
                      <span className="font-medium">{organizations.meta.total}</span> results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      {organizations.links.prev && (
                        <Link
                          href={organizations.links.prev}
                          className="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                        >
                          Previous
                        </Link>
                      )}
                      {organizations.links.next && (
                        <Link
                          href={organizations.links.next}
                          className="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                        >
                          Next
                        </Link>
                      )}
                    </nav>
                  </div>
                </div>
              </div>
            )}
          </Card>
        </div>
      </div>
    </MainLayout>
  );
}