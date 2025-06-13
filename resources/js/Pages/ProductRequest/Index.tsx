import React, { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import FilterBar from '@/Components/FilterBar/FilterBar';
import Pagination from '@/Components/Pagination/Pagination';
import { ChevronDownIcon, ChevronUpIcon, FunnelIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { CheckCircleIcon, ClockIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/react/24/solid';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import GlassCard from '@/Components/ui/GlassCard';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import ProductRequestStatusBadge from '@/Components/ProductRequest/ProductRequestStatusBadge';
import { GlassTable, Table, Thead, Tbody, Tr, Th, Td } from '@/Components/ui/GlassTable';
import Heading from '@/Components/ui/Heading';
import { Filter, Search, Plus, ChevronDown, ChevronUp, CheckCircle, AlertTriangle, XCircle } from 'lucide-react';
import { CleanStatusDashboard } from '@/Components/ProductRequest/CleanStatusDashboard';

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
  wound_type?: string;
  expected_service_date?: string;
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
  statusOptions: Array<{ value: string; label: string; count: number; trend?: number }>;
  totalRequests: number;
}

const ProductRequestIndex: React.FC<Props> = ({ requests, filters, facilities, statusOptions, totalRequests }) => {
  const { auth } = usePage<any>().props;
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const [selectedRequests, setSelectedRequests] = useState<Set<number>>(new Set());
  const [showFilters, setShowFilters] = useState(false);

      // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t: typeof themes.dark | typeof themes.light = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Remove local status config as we'll use the ProductRequestStatusBadge component

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
      <div className="p-4 sm:p-6 lg:p-8">
        {/* Header Section */}
        <div className="mb-6">
          <Heading level={1} className="bg-gradient-to-r from-[#1925c3] to-[#c71719] bg-clip-text text-transparent">
            My Product Requests
          </Heading>
          <p className={cn("mt-1 text-sm", t.text.secondary)}>
            Manage your wound care product requests through the MSC-MVP workflow
          </p>
        </div>

        <div className="flex justify-end mb-6 space-x-3">
          <Button
            variant="secondary"
            onClick={() => setShowFilters(!showFilters)}
            className="inline-flex items-center"
          >
            <Filter className="h-4 w-4 mr-2" />
            Filters
            {hasActiveFilters && (
              <span className={cn(
                "ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium",
                theme === 'dark' ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700'
              )}>
                {Object.values(filters).filter(v => v).length}
              </span>
            )}
          </Button>
          <Link href="/product-requests/create">
            <Button variant="primary" className="inline-flex items-center">
              <Plus className="h-4 w-4 mr-2" />
              New Request
            </Button>
          </Link>
        </div>

        {/* Enhanced Status Dashboard */}
        <CleanStatusDashboard
          statusOptions={statusOptions}
          onStatusFilter={(status) => handleFilter('status', status)}
          activeFilter={filters.status}
          totalRequests={totalRequests}
        />

        {/* Filters Section */}
        {showFilters && (
          <GlassCard className="mb-6">
            <div className="p-6">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div className="relative">
                  <Search className={cn("absolute left-3 top-9 h-4 w-4", t.text.muted)} />
                  <Input
                    label="Search"
                    value={filters.search || ''}
                    onChange={(e) => handleFilter('search', e.target.value)}
                    placeholder="Request #, Patient ID..."
                    className="pl-10"
                  />
                </div>
                <Select
                  label="Status"
                  value={filters.status || ''}
                  onChange={(e) => handleFilter('status', e.target.value)}
                >
                  <option value="">All Statuses</option>
                  <option value="draft">Draft</option>
                  <option value="submitted">Submitted</option>
                  <option value="processing">Processing</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                  <option value="shipped">Shipped</option>
                  <option value="delivered">Delivered</option>
                  <option value="cancelled">Cancelled</option>
                </Select>
                <Select
                  label="Facility"
                  value={filters.facility || ''}
                  onChange={(e) => handleFilter('facility', e.target.value)}
                >
                  <option value="">All Facilities</option>
                  {facilities.map((facility) => (
                    <option key={facility.id} value={facility.id}>{facility.name}</option>
                  ))}
                </Select>
                <Input
                  label="Date From"
                  type="date"
                  value={filters.date_from || ''}
                  onChange={(e) => handleFilter('date_from', e.target.value)}
                />
                <Input
                  label="Date To"
                  type="date"
                  value={filters.date_to || ''}
                  onChange={(e) => handleFilter('date_to', e.target.value)}
                />
              </div>
              {hasActiveFilters && (
                <div className="mt-4">
                  <button
                    onClick={clearFilters}
                    className={cn(
                      "text-sm",
                      theme === 'dark' ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'
                    )}
                  >
                    Clear all filters
                  </button>
                </div>
              )}
            </div>
          </GlassCard>
        )}

        {/* Main Content */}
        <GlassTable>
          <Table>
            <Thead>
              <Tr>
                <Th className="w-12">
                  <input
                    type="checkbox"
                    checked={selectedRequests.size === requests.data.length && requests.data.length > 0}
                    onChange={toggleAllRequests}
                    className={cn(
                      "h-4 w-4 rounded",
                      theme === 'dark'
                        ? 'text-blue-500 focus:ring-blue-500/50 border-white/20 bg-white/10'
                        : 'text-blue-600 focus:ring-blue-500 border-gray-300'
                    )}
                    aria-label="Select all requests"
                  />
                </Th>
                <Th>Request Details</Th>
                <Th>Patient</Th>
                <Th>Status</Th>
                <Th>Validation</Th>
                <Th>Products & Total</Th>
                <Th>Actions</Th>
              </Tr>
            </Thead>
            <Tbody>
              {requests.data.length === 0 ? (
                <Tr>
                  <Td colSpan={7} className="text-center py-12">
                    <div className={t.text.secondary}>
                      <p className="text-lg font-medium">No product requests found</p>
                      <p className="mt-1">Get started by creating your first product request.</p>
                      <Link href="/product-requests/create" className="inline-block mt-4">
                        <Button variant="primary">
                          <Plus className="h-4 w-4 mr-2" />
                          New Request
                        </Button>
                      </Link>
                    </div>
                  </Td>
                </Tr>
              ) : (
                requests.data.map((request, index) => {
                  const isExpanded = expandedRows.has(request.id);
                  const isSelected = selectedRequests.has(request.id);

                  return (
                    <React.Fragment key={request.id}>
                      <Tr
                        isEven={index % 2 === 0}
                        className={cn(
                          isSelected && theme === 'dark' && 'bg-blue-500/10',
                          isSelected && theme === 'light' && 'bg-blue-50'
                        )}
                      >
                        <Td>
                          <input
                            type="checkbox"
                            checked={isSelected}
                            onChange={() => toggleRequestSelection(request.id)}
                            className={cn(
                              "h-4 w-4 rounded",
                              theme === 'dark'
                                ? 'text-blue-500 focus:ring-blue-500/50 border-white/20 bg-white/10'
                                : 'text-blue-600 focus:ring-blue-500 border-gray-300'
                            )}
                            aria-label={`Select request ${request.request_number}`}
                          />
                        </Td>
                        <Td>
                          <div>
                            <div className={cn("text-sm font-medium", t.text.primary)}>
                              {request.request_number}
                            </div>
                            <div className={cn("text-sm", t.text.secondary)}>
                              {request.facility_name}
                            </div>
                            <div className={cn("text-xs mt-1", t.text.muted)}>
                              Created: {request.created_at}
                            </div>
                          </div>
                        </Td>
                        <Td>
                          <div>
                            <div className={cn("text-sm font-medium", t.text.primary)}>
                              {request.patient_display}
                            </div>
                            <div className={cn("text-xs", t.text.tertiary)}>
                              Sequential ID
                            </div>
                          </div>
                        </Td>
                        <Td>
                          <ProductRequestStatusBadge
                            status={request.order_status as any}
                            size="md"
                            showIcon
                          />
                        </Td>
                        <Td>
                          <div className="space-y-1">
                            {request.mac_validation_status && (
                              <div className={cn("flex items-center text-xs", t.text.secondary)}>
                                {request.mac_validation_status === 'passed' ? (
                                  <CheckCircle className={cn("h-4 w-4 mr-1", theme === 'dark' ? 'text-green-400' : 'text-green-600')} />
                                ) : (
                                  <AlertTriangle className={cn("h-4 w-4 mr-1", theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600')} />
                                )}
                                <span>MAC: {request.mac_validation_status}</span>
                              </div>
                            )}
                            {request.eligibility_status && (
                              <div className={cn("flex items-center text-xs", t.text.secondary)}>
                                {request.eligibility_status === 'eligible' ? (
                                  <CheckCircle className={cn("h-4 w-4 mr-1", theme === 'dark' ? 'text-green-400' : 'text-green-600')} />
                                ) : (
                                  <XCircle className={cn("h-4 w-4 mr-1", theme === 'dark' ? 'text-red-400' : 'text-red-600')} />
                                )}
                                <span>Eligibility: {request.eligibility_status}</span>
                              </div>
                            )}
                            {request.pre_auth_required && (
                              <div className={cn("flex items-center text-xs", t.text.secondary)}>
                                <AlertTriangle className={cn("h-4 w-4 mr-1", theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600')} />
                                <span>PA Required</span>
                              </div>
                            )}
                          </div>
                        </Td>
                        <Td>
                          <div>
                            <div className={cn("text-sm font-medium", t.text.primary)}>
                              {request.total_products} product{request.total_products !== 1 ? 's' : ''}
                            </div>
                            <div className={cn("text-sm font-semibold", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                              {formatCurrency(request.total_amount)}
                            </div>
                          </div>
                        </Td>
                        <Td className="text-right">
                          <div className="flex items-center justify-end space-x-2">
                            <Link
                              href={`/product-requests/${request.id}`}
                              className={cn(
                                "text-sm font-medium",
                                theme === 'dark' ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'
                              )}
                            >
                              View
                            </Link>
                            <button
                              onClick={() => toggleRowExpansion(request.id)}
                              className={cn(
                                "transition-colors",
                                t.text.secondary,
                                theme === 'dark' ? 'hover:text-white' : 'hover:text-gray-900'
                              )}
                              title={isExpanded ? "Collapse row" : "Expand row"}
                              aria-label={isExpanded ? "Collapse row" : "Expand row"}
                            >
                              {isExpanded ? (
                                <ChevronUp className="h-5 w-5" />
                              ) : (
                                <ChevronDown className="h-5 w-5" />
                              )}
                            </button>
                          </div>
                        </Td>
                      </Tr>
                      {isExpanded && (
                        <Tr>
                          <Td colSpan={7} className={cn(theme === 'dark' ? 'bg-white/5' : 'bg-gray-50')}>
                            <div className="p-4">
                              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                  <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>Timeline</h4>
                                  <div className={cn("space-y-1 text-xs", t.text.secondary)}>
                                    <div>Created: {request.created_at}</div>
                                    {request.submitted_at && <div>Submitted: {request.submitted_at}</div>}
                                    {request.approved_at && <div>Approved: {request.approved_at}</div>}
                                  </div>
                                </div>
                                <div>
                                  <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>Clinical Details</h4>
                                  <div className={cn("space-y-1 text-xs", t.text.secondary)}>
                                    <div>Wound Type: {request.wound_type || 'Not specified'}</div>
                                    <div>Service Date: {request.expected_service_date || 'Not set'}</div>
                                  </div>
                                </div>
                                <div>
                                  <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>Quick Actions</h4>
                                  <div className="space-x-2">
                                    {/* Removed Edit and Duplicate buttons as per requirements */}
                                  </div>
                                </div>
                              </div>
                            </div>
                          </Td>
                        </Tr>
                      )}
                    </React.Fragment>
                  );
                })
              )}
            </Tbody>
          </Table>
        </GlassTable>

        {/* Pagination */}
        {requests.data.length > 0 && (
          <GlassCard className="px-4 py-3 mt-4">
            <div className="flex items-center justify-between">
              <div className={cn("text-sm", t.text.secondary)}>
                Showing <span className={cn("font-medium", t.text.primary)}>{(requests.current_page - 1) * requests.per_page + 1}</span>
                {' '}to <span className={cn("font-medium", t.text.primary)}>{Math.min(requests.current_page * requests.per_page, requests.total)}</span>
                {' '}of <span className={cn("font-medium", t.text.primary)}>{requests.total}</span> results
              </div>
              <Pagination links={requests.links} />
            </div>
          </GlassCard>
        )}

        {/* Bulk Actions */}
        {selectedRequests.size > 0 && (
          <div className={cn(
            "fixed bottom-0 left-0 right-0 shadow-lg z-50",
            theme === 'dark' ? 'bg-gray-900/95 border-t border-white/10' : 'bg-white border-t border-gray-200'
          )}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
              <div className="flex items-center justify-between">
                <div className={cn("text-sm", t.text.secondary)}>
                  <span className={cn("font-medium", t.text.primary)}>{selectedRequests.size}</span> request{selectedRequests.size !== 1 ? 's' : ''} selected
                </div>
                <div className="flex items-center space-x-3">
                  <button
                    onClick={() => setSelectedRequests(new Set())}
                    className={cn(
                      "text-sm transition-colors",
                      t.text.secondary,
                      theme === 'dark' ? 'hover:text-white' : 'hover:text-gray-900'
                    )}
                  >
                    Cancel
                  </button>
                  <Button variant="secondary" size="sm">
                    Export
                  </Button>
                  <Button variant="secondary" size="sm">
                    Print
                  </Button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
};

export default ProductRequestIndex;

