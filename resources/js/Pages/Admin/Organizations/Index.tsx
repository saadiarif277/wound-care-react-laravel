import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiBriefcase, FiMapPin, FiUser, FiTrendingUp, FiPieChart,
  FiPlus, FiEdit, FiEye, FiTrash2, FiSearch, FiFilter,
  FiCheckCircle, FiClock, FiAlertTriangle, FiUsers,
  FiActivity, FiBarChart, FiTarget
} from 'react-icons/fi';
import { api, handleApiResponse } from '@/lib/api';

interface Organization {
  id: string;
  name: string;
  type: string;
  status: string;
  contact_email: string;
  phone: string;
  address: string;
  facilities_count: number;
  providers_count: number;
  created_at: string;
}

interface Facility {
  id: string;
  name: string;
  organization_name: string;
  type: string;
  status: string;
  address: string;
  contact_name: string;
  contact_email: string;
  providers_count: number;
  orders_count: number;
  created_at: string;
}

interface Provider {
  id: string;
  name: string;
  specialty: string;
  npi: string;
  facility_name: string;
  organization_name: string;
  email: string;
  phone: string;
  status: string;
  orders_count: number;
  last_order_date?: string;
  created_at: string;
}

interface OnboardingRecord {
  id: string;
  provider_name: string;
  facility_name: string;
  organization_name: string;
  status: 'pending' | 'in_progress' | 'completed' | 'rejected';
  stage: string;
  completion_percentage: number;
  assigned_to: string;
  created_at: string;
  target_completion_date: string;
}

interface CustomerAnalytic {
  organization_id: string;
  organization_name: string;
  total_orders: number;
  total_revenue: number;
  avg_order_value: number;
  last_order_date: string;
  growth_rate: number;
  churn_risk: 'low' | 'medium' | 'high';
  lifetime_value: number;
  satisfaction_score: number;
}

type TabType = 'organizations' | 'facilities' | 'providers' | 'onboarding' | 'analytics';

export default function OrganizationsIndex() {
  const [activeTab, setActiveTab] = useState<TabType>('organizations');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');

  // Data states
  const [organizations, setOrganizations] = useState<Organization[]>([]);
  const [facilities, setFacilities] = useState<Facility[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [onboardingRecords, setOnboardingRecords] = useState<OnboardingRecord[]>([]);
  const [analytics, setAnalytics] = useState<CustomerAnalytic[]>([]);

  // Stats
  const [stats, setStats] = useState({
    totalOrganizations: 0,
    activeFacilities: 0,
    activeProviders: 0,
    pendingOnboarding: 0,
    avgSatisfactionScore: 0,
    totalRevenue: 0
  });

  // Fetch data based on active tab
  const fetchData = async () => {
    setLoading(true);
    setError(null);

    try {
      switch (activeTab) {
        case 'organizations':
          const orgsResponse = await api.organizations.getAll({ search: searchTerm });
          setOrganizations(orgsResponse.data);
          break;

        case 'facilities':
          const facilitiesResponse = await api.facilities.getAll({ search: searchTerm });
          setFacilities(facilitiesResponse.data);
          break;

        case 'providers':
          const providersResponse = await api.providers.getAll({ search: searchTerm });
          setProviders(providersResponse.data);
          break;

        case 'onboarding':
          const onboardingResponse = await api.onboarding.getRecords({ search: searchTerm });
          setOnboardingRecords(onboardingResponse.data);
          break;

        case 'analytics':
          const analyticsResponse = await api.analytics.getCustomerAnalytics({ search: searchTerm });
          setAnalytics(analyticsResponse.data);
          break;
      }

      // Fetch stats for the dashboard
      await fetchStats();

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch data');
      console.error('Error fetching data:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const statsResponse = await api.organizations.getStats();
      setStats(statsResponse);
    } catch (err) {
      console.error('Error fetching stats:', err);
    }
  };

  useEffect(() => {
    fetchData();
  }, [activeTab, searchTerm]);

  const tabs = [
    { id: 'organizations', label: 'Organizations', icon: FiBriefcase, count: stats.totalOrganizations },
    { id: 'facilities', label: 'Facilities', icon: FiMapPin, count: stats.activeFacilities },
    { id: 'providers', label: 'Providers', icon: FiUser, count: stats.activeProviders },
    { id: 'onboarding', label: 'Onboarding Pipeline', icon: FiActivity, count: stats.pendingOnboarding },
    { id: 'analytics', label: 'Customer Analytics', icon: FiBarChart, count: null }
  ];

  const getStatusBadge = (status: string) => {
    const baseClasses = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium";

    switch (status.toLowerCase()) {
      case 'active':
      case 'completed':
        return `${baseClasses} bg-green-100 text-green-800`;
      case 'pending':
      case 'in_progress':
        return `${baseClasses} bg-yellow-100 text-yellow-800`;
      case 'inactive':
      case 'rejected':
        return `${baseClasses} bg-red-100 text-red-800`;
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

  // Render functions for each tab
  const renderOrganizations = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Organizations</h3>
        <Link
          href="/admin/organizations/create"
          className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2"
        >
          <FiPlus className="w-4 h-4" />
          Add Organization
        </Link>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {organizations.map((org) => (
            <li key={org.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{org.name}</h4>
                    <span className={getStatusBadge(org.status)}>
                      {org.status}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">{org.type}</p>
                  <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span>{org.facilities_count} facilities</span>
                    <span>{org.providers_count} providers</span>
                    <span>Created: {formatDate(org.created_at)}</span>
                  </div>
                </div>
                <div className="flex space-x-2">
                  <Link
                    href={`/admin/organizations/${org.id}`}
                    className="text-blue-600 hover:text-blue-900"
                  >
                    <FiEye className="w-4 h-4" />
                  </Link>
                  <Link
                    href={`/admin/organizations/${org.id}/edit`}
                    className="text-yellow-600 hover:text-yellow-900"
                  >
                    <FiEdit className="w-4 h-4" />
                  </Link>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );

  const renderFacilities = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Facilities</h3>
        <Link
          href="/admin/facilities/create"
          className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2"
        >
          <FiPlus className="w-4 h-4" />
          Add Facility
        </Link>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {facilities.map((facility) => (
            <li key={facility.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{facility.name}</h4>
                    <span className={getStatusBadge(facility.status)}>
                      {facility.status}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">{facility.organization_name}</p>
                  <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span>{facility.providers_count} providers</span>
                    <span>{facility.orders_count} orders</span>
                    <span>Type: {facility.type}</span>
                  </div>
                </div>
                <div className="flex space-x-2">
                  <Link
                    href={`/admin/facilities/${facility.id}`}
                    className="text-blue-600 hover:text-blue-900"
                  >
                    <FiEye className="w-4 h-4" />
                  </Link>
                  <Link
                    href={`/admin/facilities/${facility.id}/edit`}
                    className="text-yellow-600 hover:text-yellow-900"
                  >
                    <FiEdit className="w-4 h-4" />
                  </Link>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );

  const renderProviders = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Providers</h3>
        <Link
          href="/admin/providers/create"
          className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2"
        >
          <FiPlus className="w-4 h-4" />
          Add Provider
        </Link>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {providers.map((provider) => (
            <li key={provider.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{provider.name}</h4>
                    <span className={getStatusBadge(provider.status)}>
                      {provider.status}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">{provider.specialty} • NPI: {provider.npi}</p>
                  <p className="text-sm text-gray-500">{provider.facility_name}</p>
                  <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span>{provider.orders_count} orders</span>
                    {provider.last_order_date && (
                      <span>Last order: {formatDate(provider.last_order_date)}</span>
                    )}
                  </div>
                </div>
                <div className="flex space-x-2">
                  <Link
                    href={`/admin/providers/${provider.id}`}
                    className="text-blue-600 hover:text-blue-900"
                  >
                    <FiEye className="w-4 h-4" />
                  </Link>
                  <Link
                    href={`/admin/providers/${provider.id}/edit`}
                    className="text-yellow-600 hover:text-yellow-900"
                  >
                    <FiEdit className="w-4 h-4" />
                  </Link>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );

  const renderOnboarding = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Onboarding Pipeline</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Stages</option>
            <option value="documentation">Documentation</option>
            <option value="training">Training</option>
            <option value="verification">Verification</option>
            <option value="approval">Approval</option>
          </select>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {onboardingRecords.map((record) => (
            <li key={record.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{record.provider_name}</h4>
                    <span className={getStatusBadge(record.status)}>
                      {record.status.replace('_', ' ')}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">{record.facility_name} • {record.organization_name}</p>
                  <div className="flex items-center space-x-4 mt-2">
                    <span className="text-xs text-gray-500">Stage: {record.stage}</span>
                    <div className="flex items-center">
                      <div className="w-32 bg-gray-200 rounded-full h-2">
                        <div
                          className="bg-blue-600 h-2 rounded-full"
                          style={{ width: `${record.completion_percentage}%` }}
                        ></div>
                      </div>
                      <span className="ml-2 text-xs text-gray-500">{record.completion_percentage}%</span>
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-500">Assigned to: {record.assigned_to}</p>
                  <p className="text-xs text-gray-500">Due: {formatDate(record.target_completion_date)}</p>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );

  const renderAnalytics = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Customer Analytics</h3>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiTrendingUp className="h-8 w-8 text-green-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Total Revenue</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {formatCurrency(stats.totalRevenue)}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiTarget className="h-8 w-8 text-blue-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Avg Satisfaction</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {stats.avgSatisfactionScore}/10
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiUsers className="h-8 w-8 text-purple-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Active Customers</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {analytics.length}
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      {/* Analytics Table */}
      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Organization
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total Orders
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Revenue
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Avg Order Value
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Growth Rate
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Churn Risk
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Satisfaction
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {analytics.map((analytic) => (
                <tr key={analytic.organization_id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">
                      {analytic.organization_name}
                    </div>
                    <div className="text-sm text-gray-500">
                      Last order: {formatDate(analytic.last_order_date)}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {analytic.total_orders}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatCurrency(analytic.total_revenue)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatCurrency(analytic.avg_order_value)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                      analytic.growth_rate >= 0
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }`}>
                      {analytic.growth_rate >= 0 ? '+' : ''}{analytic.growth_rate.toFixed(1)}%
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={getStatusBadge(analytic.churn_risk)}>
                      {analytic.churn_risk}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {analytic.satisfaction_score}/10
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  if (loading) {
    return (
      <MainLayout>
        <Head title="Organizations & Analytics" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p className="text-gray-600">Loading data...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title="Organizations & Analytics" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Organizations & Analytics</h1>
            <p className="text-gray-500">
              Manage organizations, facilities, providers, onboarding, and customer analytics
            </p>
          </div>

          {/* Search */}
          <div className="relative">
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search..."
              className="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
          </div>
        </div>

        {/* Tabs */}
        <div className="border-b border-gray-200">
          <nav className="-mb-px flex space-x-8">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as TabType)}
                  className={`py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                  {tab.count !== null && (
                    <span className="bg-gray-100 text-gray-600 py-0.5 px-2.5 rounded-full text-xs">
                      {tab.count}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Error State */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-md p-4">
            <div className="text-red-700">
              <p className="font-medium">Error loading data</p>
              <p className="text-sm">{error}</p>
            </div>
          </div>
        )}

        {/* Tab Content */}
        <div className="min-h-96">
          {activeTab === 'organizations' && renderOrganizations()}
          {activeTab === 'facilities' && renderFacilities()}
          {activeTab === 'providers' && renderProviders()}
          {activeTab === 'onboarding' && renderOnboarding()}
          {activeTab === 'analytics' && renderAnalytics()}
        </div>
      </div>
    </MainLayout>
  );
}
