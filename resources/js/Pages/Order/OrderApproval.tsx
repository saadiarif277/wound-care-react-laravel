import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiCheck, FiAlertTriangle, FiX, FiFilter, FiSearch,
  FiChevronDown, FiChevronUp, FiCalendar, FiMapPin,
  FiUser, FiDollarSign, FiPackage, FiClock
} from 'react-icons/fi';

// Types
interface Order {
  id: string;
  submissionDate: Date;
  providerName: string;
  facilityName: string;
  payerName: string;
  expectedServiceDate: Date;
  products: string;
  totalOrderValue: number;
  macValidation: 'passed' | 'warning' | 'failed';
  eligibilityStatus: 'eligible' | 'pending' | 'not_eligible';
  preAuthStatus: 'approved' | 'pending' | 'denied';
}

// Dummy data
const dummyOrders: Order[] = [
  {
    id: 'ORD-001',
    submissionDate: new Date('2024-03-15T10:30:00'),
    providerName: 'Dr. John Smith',
    facilityName: 'Main Hospital',
    payerName: 'Medicare',
    expectedServiceDate: new Date('2024-03-20'),
    products: 'Wound Dressing Advanced (2x2), Antimicrobial Foam (4x4)',
    totalOrderValue: 1250.75,
    macValidation: 'passed',
    eligibilityStatus: 'eligible',
    preAuthStatus: 'approved'
  },
  {
    id: 'ORD-002',
    submissionDate: new Date('2024-03-15T09:15:00'),
    providerName: 'Dr. Sarah Johnson',
    facilityName: 'Northside Clinic',
    payerName: 'Blue Cross',
    expectedServiceDate: new Date('2024-03-18'),
    products: 'Wound Dressing Advanced (4x4)',
    totalOrderValue: 850.50,
    macValidation: 'warning',
    eligibilityStatus: 'pending',
    preAuthStatus: 'pending'
  },
  {
    id: 'ORD-003',
    submissionDate: new Date('2024-03-14T16:45:00'),
    providerName: 'Dr. Michael Brown',
    facilityName: 'Downtown Medical Center',
    payerName: 'Aetna',
    expectedServiceDate: new Date('2024-03-17'),
    products: 'Antimicrobial Foam (6x6), Wound Dressing Advanced (2x2)',
    totalOrderValue: 1500.25,
    macValidation: 'failed',
    eligibilityStatus: 'not_eligible',
    preAuthStatus: 'denied'
  }
];

const OrderApproval = () => {
  const [selectedStatus, setSelectedStatus] = useState<string>('pending');
  const [dateRange, setDateRange] = useState<{ start: Date | null; end: Date | null }>({
    start: null,
    end: null
  });
  const [macJurisdiction, setMacJurisdiction] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [showAdvancedFilters, setShowAdvancedFilters] = useState<boolean>(false);
  const [selectedOrders, setSelectedOrders] = useState<string[]>([]);

  // Status badge component
  const StatusBadge = ({ status, type }: { status: string; type: 'mac' | 'eligibility' | 'preAuth' }) => {
    const getStatusColor = () => {
      switch (status) {
        case 'passed':
        case 'eligible':
        case 'approved':
          return 'bg-green-100 text-green-800';
        case 'warning':
        case 'pending':
          return 'bg-yellow-100 text-yellow-800';
        case 'failed':
        case 'not_eligible':
        case 'denied':
          return 'bg-red-100 text-red-800';
        default:
          return 'bg-gray-100 text-gray-800';
      }
    };

    const getStatusIcon = () => {
      switch (status) {
        case 'passed':
        case 'eligible':
        case 'approved':
          return <FiCheck className="w-4 h-4" />;
        case 'warning':
        case 'pending':
          return <FiAlertTriangle className="w-4 h-4" />;
        case 'failed':
        case 'not_eligible':
        case 'denied':
          return <FiX className="w-4 h-4" />;
        default:
          return null;
      }
    };

    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor()}`}>
        {getStatusIcon()}
        <span className="ml-1">{status.charAt(0).toUpperCase() + status.slice(1)}</span>
      </span>
    );
  };

  // Filter controls component
  const FilterControls = () => (
    <div className="bg-white p-4 rounded-lg shadow mb-6">
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option value="pending">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="info_requested">Info Requested</option>
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
          <div className="flex gap-2">
            <input
              type="date"
              value={dateRange.start?.toISOString().split('T')[0] || ''}
              onChange={(e) => setDateRange(prev => ({ ...prev, start: e.target.value ? new Date(e.target.value) : null }))}
              className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <input
              type="date"
              value={dateRange.end?.toISOString().split('T')[0] || ''}
              onChange={(e) => setDateRange(prev => ({ ...prev, end: e.target.value ? new Date(e.target.value) : null }))}
              className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">MAC Jurisdiction</label>
          <select
            value={macJurisdiction}
            onChange={(e) => setMacJurisdiction(e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option value="all">All Jurisdictions</option>
            <option value="jurisdiction_a">Jurisdiction A</option>
            <option value="jurisdiction_b">Jurisdiction B</option>
            <option value="jurisdiction_c">Jurisdiction C</option>
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Search</label>
          <div className="relative">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search provider or facility..."
              className="w-full rounded-md border-gray-300 shadow-sm pl-10 focus:border-indigo-500 focus:ring-indigo-500"
            />
            <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
          </div>
        </div>
      </div>
      <div className="mt-4">
        <button
          onClick={() => setShowAdvancedFilters(!showAdvancedFilters)}
          className="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
        >
          {showAdvancedFilters ? <FiChevronUp /> : <FiChevronDown />}
          Advanced Filters
        </button>
        {showAdvancedFilters && (
          <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            {/* Add advanced filter fields here */}
          </div>
        )}
      </div>
    </div>
  );

  return (
    <MainLayout>
      <Head title="Order Approval Queue" />

      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Order Approval Queue</h1>
          <p className="text-gray-500">
            Review and process pending wound care product orders
          </p>
        </div>
        {selectedOrders.length > 0 && (
          <div className="flex gap-2">
            <button className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
              Approve Selected
            </button>
            <button className="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">
              Request Info
            </button>
            <button className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
              Reject Selected
            </button>
          </div>
        )}
      </div>

      {/* Filter Controls */}
      <FilterControls />

      {/* Orders Table */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  <input
                    type="checkbox"
                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelectedOrders(dummyOrders.map(order => order.id));
                      } else {
                        setSelectedOrders([]);
                      }
                    }}
                  />
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Request ID
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Submission Date
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Provider/Facility
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Payer
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Service Date
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Products
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total Value
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  MAC Validation
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Eligibility
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Pre-Auth
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Action
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {dummyOrders.map((order) => (
                <tr key={order.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <input
                      type="checkbox"
                      checked={selectedOrders.includes(order.id)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedOrders([...selectedOrders, order.id]);
                        } else {
                          setSelectedOrders(selectedOrders.filter(id => id !== order.id));
                        }
                      }}
                      className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <Link href={`/admin/orders/${order.id}`} className="text-indigo-600 hover:text-indigo-900">
                      {order.id}
                    </Link>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {order.submissionDate.toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-900">{order.providerName}</div>
                    <div className="text-sm text-gray-500">{order.facilityName}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {order.payerName}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {order.expectedServiceDate.toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-900 max-w-xs">
                      {order.products.split(',').map((product, index) => {
                        const words = product.trim().split(' ');
                        const shortDesc = words.slice(0, 3).join(' ');
                        return (
                          <div key={index} className="mb-1">
                            {shortDesc}
                            {words.length > 3 ? '...' : ''}
                          </div>
                        );
                      })}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    ${order.totalOrderValue.toFixed(2)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={order.macValidation} type="mac" />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={order.eligibilityStatus} type="eligibility" />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={order.preAuthStatus} type="preAuth" />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <select className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                      <option value="">Select Action</option>
                      <option value="approve">Approve</option>
                      <option value="request_info">Request Info</option>
                      <option value="reject">Reject</option>
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      <div className="mt-4 flex items-center justify-between">
        <div className="text-sm text-gray-700">
          Showing <span className="font-medium">1</span> to <span className="font-medium">3</span> of{' '}
          <span className="font-medium">3</span> results
        </div>
        <div className="flex gap-2">
          <button className="px-3 py-1 border rounded-md text-sm disabled:opacity-50" disabled>
            Previous
          </button>
          <button className="px-3 py-1 border rounded-md text-sm disabled:opacity-50" disabled>
            Next
          </button>
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderApproval;
