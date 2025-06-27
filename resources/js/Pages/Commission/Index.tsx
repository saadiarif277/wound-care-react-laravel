/** @jsxImportSource react */
import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiDollarSign, FiUsers, FiTrendingUp, FiCalendar,
  FiCheckCircle, FiClock, FiAlertTriangle, FiPlus, FiEdit, FiEye,
  FiDownload, FiSearch, FiFilter, FiBarChart,
  FiUserCheck, FiCreditCard, FiTarget, FiActivity
} from 'react-icons/fi';
import { api, handleApiResponse } from '@/lib/api';

interface CommissionRecord {
  id: string;
  order_id: string;
  order_number: string;
  rep_name: string;
  rep_type: 'primary' | 'sub';
  commission_amount: number;
  commission_rate: number;
  order_total: number;
  status: 'pending' | 'approved' | 'paid' | 'disputed';
  order_date: string;
  created_at: string;
  approval_date?: string;
  payout_date?: string;
}

interface Payout {
  id: string;
  rep_id: string;
  rep_name: string;
  period_start: string;
  period_end: string;
  total_amount: number;
  commission_count: number;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  processed_date?: string;
  payment_method: string;
  reference_number?: string;
}

interface SalesRep {
  id: string;
  name: string;
  email: string;
  phone: string;
  territory: string;
  status: 'active' | 'inactive' | 'pending';
  commission_rate: number;
  total_sales: number;
  total_commissions: number;
  last_order_date?: string;
  created_at: string;
  manager_name?: string;
}

interface SubRepApproval {
  id: string;
  sub_rep_name: string;
  primary_rep_name: string;
  customer_name: string;
  requested_commission_rate: number;
  territory: string;
  status: 'pending' | 'approved' | 'rejected';
  submitted_date: string;
  reviewed_date?: string;
  reviewer_name?: string;
  notes?: string;
}

interface SalesAnalytic {
  total_commissions: number;
  pending_commissions: number;
  paid_commissions: number;
  active_reps: number;
  pending_approvals: number;
  monthly_growth: number;
  avg_commission_per_order: number;
  top_performers: Array<{
    rep_name: string;
    total_commissions: number;
    order_count: number;
  }>;
}

type TabType = 'overview' | 'commissions' | 'payouts' | 'reps' | 'approvals';

export default function SalesManagementIndex() {
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');

  // Data states
  const [commissionRecords, setCommissionRecords] = useState<CommissionRecord[]>([]);
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [salesReps, setSalesReps] = useState<SalesRep[]>([]);
  const [subRepApprovals, setSubRepApprovals] = useState<SubRepApproval[]>([]);
  const [analytics, setAnalytics] = useState<SalesAnalytic>({
    total_commissions: 0,
    pending_commissions: 0,
    paid_commissions: 0,
    active_reps: 0,
    pending_approvals: 0,
    monthly_growth: 0,
    avg_commission_per_order: 0,
    top_performers: []
  });

  // Fetch data based on active tab
  const fetchData = async () => {
    setLoading(true);
    setError(null);

    try {
      switch (activeTab) {
        case 'overview':
          await fetchAnalytics();
          break;

        case 'commissions':
          const commissionsResponse = await api.commission.getRecords({ search: searchTerm });
          setCommissionRecords(commissionsResponse.data);
          break;

        case 'payouts':
          const payoutsResponse = await api.commission.getPayouts({});
          setPayouts(payoutsResponse.data);
          break;

        case 'reps':
          const repsResponse = await api.salesReps.getAll({ search: searchTerm });
          setSalesReps(repsResponse.data);
          break;

        case 'approvals':
          const approvalsResponse = await api.salesReps.getSubRepApprovals({ search: searchTerm });
          setSubRepApprovals(approvalsResponse.data);
          break;
      }

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch data');
      console.error('Error fetching sales data:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchAnalytics = async () => {
    try {
      const analyticsResponse = await api.salesReps.getAnalytics();
      setAnalytics(analyticsResponse);
    } catch (err) {
      console.error('Error fetching analytics:', err);
    }
  };

  useEffect(() => {
    fetchData();
  }, [activeTab, searchTerm]);

  const tabs = [
    { id: 'overview', label: 'Overview', icon: FiBarChart, count: null },
    { id: 'commissions', label: 'Commission Tracking', icon: FiDollarSign, count: analytics.pending_commissions },
    { id: 'payouts', label: 'Payout Management', icon: FiCreditCard, count: null },
    { id: 'reps', label: 'Sales Rep Management', icon: FiUsers, count: analytics.active_reps },
    { id: 'approvals', label: 'Sub-Rep Approvals', icon: FiUserCheck, count: analytics.pending_approvals }
  ];

  const getStatusBadge = (status: string) => {
    const baseClasses = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium max-w-full truncate";

    switch (status.toLowerCase()) {
      case 'approved':
      case 'completed':
      case 'paid':
      case 'active':
        return `${baseClasses} bg-green-100 text-green-800`;
      case 'pending':
      case 'processing':
        return `${baseClasses} bg-yellow-100 text-yellow-800`;
      case 'rejected':
      case 'failed':
      case 'disputed':
      case 'inactive':
        return `${baseClasses} bg-red-100 text-red-800`;
      default:
        return `${baseClasses} bg-gray-100 text-gray-800`;
    }
  };

  // Get readable status label
  const getStatusLabel = (status: string) => {
    switch (status.toLowerCase()) {
      case 'approved':
        return 'Approved';
      case 'completed':
        return 'Completed';
      case 'paid':
        return 'Paid';
      case 'active':
        return 'Active';
      case 'pending':
        return 'Pending';
      case 'processing':
        return 'Processing';
      case 'rejected':
        return 'Rejected';
      case 'failed':
        return 'Failed';
      case 'disputed':
        return 'Disputed';
      case 'inactive':
        return 'Inactive';
      default:
        return status.charAt(0).toUpperCase() + status.slice(1);
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

  const formatPercentage = (rate: number) => {
    return `${(rate * 100).toFixed(1)}%`;
  };

  // Render functions for each tab
  const renderOverview = () => (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Sales & Commission Overview</h3>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiDollarSign className="h-8 w-8 text-green-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Total Commissions</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {formatCurrency(analytics.total_commissions)}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiClock className="h-8 w-8 text-yellow-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Pending Commissions</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {formatCurrency(analytics.pending_commissions)}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiUsers className="h-8 w-8 text-blue-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Active Sales Reps</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {analytics.active_reps}
                </dd>
              </dl>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <FiTrendingUp className="h-8 w-8 text-purple-600" />
            </div>
            <div className="ml-5">
              <dl>
                <dt className="text-sm font-medium text-gray-500">Monthly Growth</dt>
                <dd className="text-2xl font-semibold text-gray-900">
                  {analytics.monthly_growth >= 0 ? '+' : ''}{analytics.monthly_growth.toFixed(1)}%
                </dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      {/* Top Performers */}
      <div className="bg-white rounded-lg shadow">
        <div className="px-6 py-4 border-b border-gray-200">
          <h4 className="text-lg font-medium text-gray-900">Top Performers This Month</h4>
        </div>
        <div className="overflow-hidden">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Sales Rep
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total Commissions
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Orders
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {analytics.top_performers.map((performer, index) => (
                <tr key={index} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {performer.rep_name}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatCurrency(performer.total_commissions)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {performer.order_count}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  const renderCommissions = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Commission Records</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="disputed">Disputed</option>
          </select>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2">
            <FiDownload className="w-4 h-4" />
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
                  Order / Rep
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Commission
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Rate
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order Total
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {commissionRecords.map((record) => (
                <tr key={record.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">{record.order_number}</div>
                    <div className="text-sm text-gray-500">{record.rep_name} ({record.rep_type})</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatCurrency(record.commission_amount)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatPercentage(record.commission_rate)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatCurrency(record.order_total)}
                  </td>
                  <td className="px-6 py-4">
                    <div className="max-w-[120px]">
                      <span className={getStatusBadge(record.status)}>
                        {getStatusLabel(record.status)}
                      </span>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatDate(record.order_date)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div className="flex space-x-2">
                      <Link href={`/admin/commissions/${record.id}`} className="text-blue-600 hover:text-blue-900">
                        <FiEye className="w-4 h-4" />
                      </Link>
                      {record.status === 'pending' && (
                        <button title="Approve commission" className="text-green-600 hover:text-green-900">
                          <FiCheckCircle className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  const renderPayouts = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Payout Management</h3>
        <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2">
          <FiPlus className="w-4 h-4" />
          Process Payouts
        </button>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {payouts.map((payout) => (
            <li key={payout.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{payout.rep_name}</h4>
                    <span className={getStatusBadge(payout.status)}>
                      {getStatusLabel(payout.status)}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">
                    Period: {formatDate(payout.period_start)} - {formatDate(payout.period_end)}
                  </p>
                  <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span>{formatCurrency(payout.total_amount)}</span>
                    <span>{payout.commission_count} commissions</span>
                    <span>{payout.payment_method}</span>
                    {payout.reference_number && <span>Ref: {payout.reference_number}</span>}
                  </div>
                </div>
                <div className="flex space-x-2">
                  <Link href={`/admin/payouts/${payout.id}`} className="text-blue-600 hover:text-blue-900">
                    <FiEye className="w-4 h-4" />
                  </Link>
                  <button title="Edit" className="text-yellow-600 hover:text-yellow-900">
                    <FiEdit className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );

  const renderSalesReps = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Sales Representatives</h3>
        <Link
          href="/admin/sales-reps/create"
          className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2"
        >
          <FiPlus className="w-4 h-4" />
          Add Sales Rep
        </Link>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {salesReps.map((rep) => (
            <li key={rep.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{rep.name}</h4>
                    <span className={getStatusBadge(rep.status)}>
                      {getStatusLabel(rep.status)}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">{rep.email} • {rep.territory}</p>
                  <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span>Rate: {formatPercentage(rep.commission_rate)}</span>
                    <span>Sales: {formatCurrency(rep.total_sales)}</span>
                    <span>Commissions: {formatCurrency(rep.total_commissions)}</span>
                    {rep.last_order_date && (
                      <span>Last order: {formatDate(rep.last_order_date)}</span>
                    )}
                  </div>
                </div>
                <div className="flex space-x-2">
                  <Link href={`/admin/sales-reps/${rep.id}`} className="text-blue-600 hover:text-blue-900">
                    <FiEye className="w-4 h-4" />
                  </Link>
                  <Link href={`/admin/sales-reps/${rep.id}/edit`} className="text-yellow-600 hover:text-yellow-900">
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

  const renderSubRepApprovals = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Sub-Rep Approval Requests</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <ul className="divide-y divide-gray-200">
          {subRepApprovals.map((approval) => (
            <li key={approval.id} className="px-6 py-4 hover:bg-gray-50">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center">
                    <h4 className="text-sm font-medium text-gray-900">{approval.sub_rep_name}</h4>
                    <span className={getStatusBadge(approval.status)}>
                      {getStatusLabel(approval.status)}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mt-1">
                    Primary Rep: {approval.primary_rep_name} • Customer: {approval.customer_name}
                  </p>
                  <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                    <span>Requested Rate: {formatPercentage(approval.requested_commission_rate)}</span>
                    <span>Territory: {approval.territory}</span>
                    <span>Submitted: {formatDate(approval.submitted_date)}</span>
                    {approval.reviewer_name && (
                      <span>Reviewed by: {approval.reviewer_name}</span>
                    )}
                  </div>
                  {approval.notes && (
                    <p className="text-xs text-gray-500 mt-1">Notes: {approval.notes}</p>
                  )}
                </div>
                <div className="flex space-x-2">
                  {approval.status === 'pending' && (
                    <>
                      <button title="Approve" className="text-green-600 hover:text-green-900 p-1">
                        <FiCheckCircle className="w-4 h-4" />
                      </button>
                      <button title="Reject commission" className="text-red-600 hover:text-red-900 p-1">
                        <FiAlertTriangle className="w-4 h-4" title="Reject" />
                      </button>
                    </>
                  )}
                  <Link href={`/admin/sub-rep-approvals/${approval.id}`} className="text-blue-600 hover:text-blue-900 p-1">
                    <FiEye className="w-4 h-4" title="View Details" />
                  </Link>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );

  if (loading) {
    return (
      <MainLayout>
        <Head title="Sales Management" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p className="text-gray-600">Loading sales data...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title="Sales Management" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Sales Management</h1>
            <p className="text-gray-500">
              Manage commissions, payouts, sales reps, and approvals
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
          {activeTab === 'overview' && renderOverview()}
          {activeTab === 'commissions' && renderCommissions()}
          {activeTab === 'payouts' && renderPayouts()}
          {activeTab === 'reps' && renderSalesReps()}
          {activeTab === 'approvals' && renderSubRepApprovals()}
        </div>
      </div>
    </MainLayout>
  );
}
