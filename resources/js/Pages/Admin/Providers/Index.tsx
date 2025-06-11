import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  Search,
  Filter,
  User,
  Building,
  DollarSign,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Package,
  FileText,
  ChevronRight,
  TrendingUp,
  Calendar,
  CreditCard,
  UserPlus
} from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

interface Provider {
  id: number;
  name: string;
  email: string;
  npi_number?: string;
  phone?: string;
  profile?: {
    verification_status: string;
    profile_completion_percentage: number;
  };
  current_organization?: {
    id: number;
    name: string;
  };
  facilities_count: number;
  active_products_count: number;
  total_orders_count: number;
  pending_orders_count: number;
  financial_summary: {
    total_outstanding: number;
    past_due_amount: number;
    days_past_due: number;
    last_payment_date?: string;
    last_payment_amount?: number;
    credit_limit?: number;
  };
  created_at: string;
  last_activity_at?: string;
}

interface Props {
  providers: {
    data: Provider[];
    links: any;
    meta: any;
  };
  filters: {
    search?: string;
    organization?: string;
    verification_status?: string;
    has_past_due?: boolean;
  };
  organizations: Array<{
    id: number;
    name: string;
  }>;
  summary: {
    total_providers: number;
    verified_providers: number;
    providers_with_past_due: number;
    total_outstanding: number;
    total_past_due: number;
  };
}

export default function ProvidersIndex({ providers, filters, organizations, summary }: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedOrganization, setSelectedOrganization] = useState(filters.organization || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.verification_status || '');
  const [showPastDueOnly, setShowPastDueOnly] = useState(filters.has_past_due || false);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('admin.providers.index'), {
      search: searchTerm,
      organization: selectedOrganization,
      verification_status: selectedStatus,
      has_past_due: showPastDueOnly
    });
  };

  const getVerificationBadge = (status: string) => {
    switch (status) {
      case 'verified':
        return <Badge className="bg-green-100 text-green-800"><CheckCircle2 className="w-3 h-3 mr-1" />Verified</Badge>;
      case 'pending':
        return <Badge className="bg-yellow-100 text-yellow-800"><Clock className="w-3 h-3 mr-1" />Pending</Badge>;
      case 'rejected':
        return <Badge className="bg-red-100 text-red-800"><AlertTriangle className="w-3 h-3 mr-1" />Rejected</Badge>;
      default:
        return <Badge className="bg-gray-100 text-gray-800">Unknown</Badge>;
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(amount);
  };

  return (
    <MainLayout>
      <Head title="Provider Management" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-6 flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Provider Management</h1>
              <p className="text-gray-600">Comprehensive provider profiles with financial tracking</p>
            </div>
            <Link
              href="/admin/providers/create"
              className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              <UserPlus className="w-5 h-5 mr-2" />
              Add Provider
            </Link>
          </div>

          {/* Summary Cards */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Total Providers</p>
                  <p className="text-2xl font-bold">{summary.total_providers}</p>
                </div>
                <User className="w-8 h-8 text-gray-400" />
              </div>
            </Card>
            
            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Verified</p>
                  <p className="text-2xl font-bold text-green-600">{summary.verified_providers}</p>
                </div>
                <CheckCircle2 className="w-8 h-8 text-green-400" />
              </div>
            </Card>

            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Past Due</p>
                  <p className="text-2xl font-bold text-red-600">{summary.providers_with_past_due}</p>
                </div>
                <AlertTriangle className="w-8 h-8 text-red-400" />
              </div>
            </Card>

            <Card className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">Outstanding</p>
                  <p className="text-2xl font-bold">{formatCurrency(summary.total_outstanding)}</p>
                </div>
                <DollarSign className="w-8 h-8 text-blue-400" />
              </div>
            </Card>

            <Card className="p-4 border-red-200 bg-red-50">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-red-600">Total Past Due</p>
                  <p className="text-2xl font-bold text-red-700">{formatCurrency(summary.total_past_due)}</p>
                </div>
                <CreditCard className="w-8 h-8 text-red-400" />
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
                  placeholder="Search providers by name, email, or NPI..."
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                />
              </div>

              <select
                value={selectedOrganization}
                onChange={(e) => setSelectedOrganization(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
              >
                <option value="">All Organizations</option>
                {organizations.map(org => (
                  <option key={org.id} value={org.id}>{org.name}</option>
                ))}
              </select>

              <select
                value={selectedStatus}
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
              >
                <option value="">All Statuses</option>
                <option value="verified">Verified</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
              </select>

              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={showPastDueOnly}
                  onChange={(e) => setShowPastDueOnly(e.target.checked)}
                  className="rounded border-gray-300 text-red-600 focus:ring-red-500"
                />
                <span className="text-sm text-gray-700">Past Due Only</span>
              </label>

              <button
                type="submit"
                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2"
              >
                <Filter className="w-4 h-4" />
                Apply Filters
              </button>
            </form>
          </Card>

          {/* Providers List */}
          <Card className="overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Provider
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Organization & Facilities
                    </th>
                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Activity
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Financial Status
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {providers.data.map((provider) => (
                    <tr key={provider.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <div className="h-10 w-10 flex-shrink-0">
                            <div className="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                              <User className="h-5 w-5 text-gray-500" />
                            </div>
                          </div>
                          <div className="ml-4">
                            <div className="text-sm font-medium text-gray-900">{provider.name}</div>
                            <div className="text-sm text-gray-500">{provider.email}</div>
                            {provider.npi_number && (
                              <div className="text-xs text-gray-400">NPI: {provider.npi_number}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{provider.current_organization?.name || 'No Organization'}</div>
                        <div className="text-sm text-gray-500 flex items-center gap-1">
                          <Building className="w-3 h-3" />
                          {provider.facilities_count} {provider.facilities_count === 1 ? 'Facility' : 'Facilities'}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-center">
                        <div className="flex flex-col items-center gap-2">
                          {provider.profile && getVerificationBadge(provider.profile.verification_status)}
                          {provider.profile && (
                            <div className="text-xs text-gray-500">
                              {provider.profile.profile_completion_percentage}% Complete
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-center">
                        <div className="space-y-1">
                          <div className="flex items-center justify-center gap-4 text-sm">
                            <span className="flex items-center gap-1">
                              <Package className="w-4 h-4 text-gray-400" />
                              {provider.active_products_count} Products
                            </span>
                            <span className="flex items-center gap-1">
                              <FileText className="w-4 h-4 text-gray-400" />
                              {provider.total_orders_count} Orders
                            </span>
                          </div>
                          {provider.pending_orders_count > 0 && (
                            <Badge className="bg-yellow-100 text-yellow-800 text-xs">
                              {provider.pending_orders_count} Pending
                            </Badge>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right">
                        <div className="space-y-1">
                          <div className="text-sm font-medium text-gray-900">
                            {formatCurrency(provider.financial_summary.total_outstanding)}
                          </div>
                          {provider.financial_summary.past_due_amount > 0 && (
                            <div className="flex items-center justify-end gap-1">
                              <AlertTriangle className="w-4 h-4 text-red-500" />
                              <span className="text-sm font-medium text-red-600">
                                {formatCurrency(provider.financial_summary.past_due_amount)}
                              </span>
                              <span className="text-xs text-red-500">
                                ({provider.financial_summary.days_past_due}d past due)
                              </span>
                            </div>
                          )}
                          {provider.financial_summary.last_payment_date && (
                            <div className="text-xs text-gray-500">
                              Last payment: {formatDistanceToNow(new Date(provider.financial_summary.last_payment_date), { addSuffix: true })}
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div className="flex items-center justify-end gap-3">
                          <Link
                            href={route('admin.providers.edit', provider.id)}
                            className="text-blue-600 hover:text-blue-900"
                          >
                            Edit
                          </Link>
                          <button
                            onClick={() => router.visit(route('admin.providers.show', provider.id))}
                            className="text-red-600 hover:text-red-900 flex items-center gap-1"
                          >
                            View Profile
                            <ChevronRight className="w-4 h-4" />
                          </button>
                          {provider.financial_summary.total_outstanding === 0 && (
                            <button
                              onClick={() => {
                                if (confirm(`Are you sure you want to deactivate ${provider.name}? This action cannot be undone.`)) {
                                  router.delete(route('admin.providers.destroy', provider.id));
                                }
                              }}
                              className="text-red-600 hover:text-red-900"
                            >
                              Delete
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {providers.meta?.links && (
              <div className="bg-white px-4 py-3 border-t border-gray-200">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Showing <span className="font-medium">{providers.meta.from || 0}</span> to{' '}
                      <span className="font-medium">{providers.meta.to || 0}</span> of{' '}
                      <span className="font-medium">{providers.meta.total || 0}</span> providers
                    </p>
                  </div>
                  <div className="flex gap-2">
                    {providers.links.map((link: any, index: number) => (
                      <button
                        key={index}
                        onClick={() => link.url && router.visit(link.url)}
                        disabled={!link.url}
                        className={`px-3 py-1 text-sm rounded ${
                          link.active
                            ? 'bg-red-600 text-white'
                            : link.url
                            ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ))}
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