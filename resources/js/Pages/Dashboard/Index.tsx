import { Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import React from 'react';

// Define the types
interface Verification {
  id: string;
  customerId: string;
  requestDate?: string;
  patientInfo: {
    firstName: string;
    lastName: string;
  };
  insuranceInfo: {
    payerId: string;
  };
}

interface VerificationResult {
  id: string;
  status: 'active' | 'inactive' | 'pending' | 'error';
}

// Create dummy data directly in the component
const dummyVerifications: Verification[] = [
  {
    id: 'ver-001',
    customerId: 'customer-001',
    requestDate: '2023-06-15',
    patientInfo: {
      firstName: 'John',
      lastName: 'Doe'
    },
    insuranceInfo: {
      payerId: 'AETNA-123'
    }
  },
  {
    id: 'ver-002',
    customerId: 'customer-002',
    requestDate: '2023-06-14',
    patientInfo: {
      firstName: 'Jane',
      lastName: 'Smith'
    },
    insuranceInfo: {
      payerId: 'BCBS-456'
    }
  },
  {
    id: 'ver-003',
    customerId: 'customer-003',
    requestDate: '2023-06-13',
    patientInfo: {
      firstName: 'Robert',
      lastName: 'Johnson'
    },
    insuranceInfo: {
      payerId: 'MEDICARE-789'
    }
  },
  {
    id: 'ver-004',
    customerId: 'customer-001',
    requestDate: '2023-06-12',
    patientInfo: {
      firstName: 'Sarah',
      lastName: 'Williams'
    },
    insuranceInfo: {
      payerId: 'UNITED-101'
    }
  },
  {
    id: 'ver-005',
    customerId: 'customer-002',
    requestDate: '2023-06-11',
    patientInfo: {
      firstName: 'Michael',
      lastName: 'Brown'
    },
    insuranceInfo: {
      payerId: 'CIGNA-202'
    }
  }
];

const dummyResults: Record<string, VerificationResult> = {
  'ver-001': { id: 'ver-001', status: 'active' },
  'ver-002': { id: 'ver-002', status: 'inactive' },
  'ver-003': { id: 'ver-003', status: 'pending' },
  'ver-004': { id: 'ver-004', status: 'error' },
  'ver-005': { id: 'ver-005', status: 'active' }
};

function DashboardPage() {
  // Use the dummy data directly
  const verifications = dummyVerifications;
  const results = dummyResults;

  // Calculate status counts
  const getTotalsByStatus = () => {
    const totals = {
      active: 0,
      inactive: 0,
      pending: 0,
      error: 0
    };

    verifications.forEach(verification => {
      if (verification.id && results[verification.id]) {
        const status = results[verification.id].status;
        if (status in totals) {
          totals[status as keyof typeof totals]++;
        }
      }
    });

    return totals;
  };

  const statusTotals = getTotalsByStatus();
  const totalVerifications = verifications.length;

  // Recent verifications
  const recentVerifications = [...verifications]
    .sort((a, b) => {
      const dateA = a.requestDate ? new Date(a.requestDate).getTime() : 0;
      const dateB = b.requestDate ? new Date(b.requestDate).getTime() : 0;
      return dateB - dateA;
    })
    .slice(0, 5);

  return (
    <div className="space-y-6">
      <h1 className="mb-8 text-3xl font-bold">Admin Dashboard</h1>
      <p className="mb-12 leading-normal">
        Welcome to your admin dashboard. Here you can monitor all eligibility verification activity.
      </p>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-sm font-medium text-gray-500">Total Verifications</h3>
          <p className="text-2xl font-bold mt-1">{totalVerifications}</p>
          <p className="text-xs text-gray-500 mt-1">All time</p>
        </div>
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-sm font-medium text-gray-500">Active Coverage</h3>
          <p className="text-2xl font-bold text-green-600 mt-1">{statusTotals.active}</p>
          <p className="text-xs text-gray-500 mt-1">
            {statusTotals.active > 0 && totalVerifications > 0
              ? Math.round((statusTotals.active / totalVerifications) * 100)
              : 0}% of total
          </p>
        </div>
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-sm font-medium text-gray-500">Inactive Coverage</h3>
          <p className="text-2xl font-bold text-red-600 mt-1">{statusTotals.inactive}</p>
          <p className="text-xs text-gray-500 mt-1">
            {statusTotals.inactive > 0 && totalVerifications > 0
              ? Math.round((statusTotals.inactive / totalVerifications) * 100)
              : 0}% of total
          </p>
        </div>
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="text-sm font-medium text-gray-500">Verification Errors</h3>
          <p className="text-2xl font-bold text-amber-600 mt-1">{statusTotals.error}</p>
          <p className="text-xs text-gray-500 mt-1">
            {statusTotals.error > 0 && totalVerifications > 0
              ? Math.round((statusTotals.error / totalVerifications) * 100)
              : 0}% of total
          </p>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="p-6 border-b">
          <h2 className="text-lg font-semibold">Recent Verifications</h2>
          <p className="text-sm text-gray-500">Latest eligibility verification requests</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {recentVerifications.length > 0 ? (
                recentVerifications.map((verification) => (
                  <tr key={verification.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      {verification.patientInfo.firstName} {verification.patientInfo.lastName}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {verification.customerId === 'customer-001' ? 'Wound Care Clinic' :
                      verification.customerId === 'customer-002' ? 'City Medical Center' :
                      verification.customerId === 'customer-003' ? 'Rural Health Center' :
                      'Unknown'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {verification.requestDate || 'N/A'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {verification.id && results[verification.id] ? (
                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                          results[verification.id].status === 'active' ? 'bg-green-100 text-green-800' :
                          results[verification.id].status === 'inactive' ? 'bg-red-100 text-red-800' :
                          results[verification.id].status === 'error' ? 'bg-red-100 text-red-800' :
                          'bg-yellow-100 text-yellow-800'
                        }`}>
                          {results[verification.id].status}
                        </span>
                      ) : (
                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                          Processing
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <Link href={`/admin/verifications/${verification.id}`} className="text-indigo-600 hover:text-indigo-900">
                        Details
                      </Link>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} className="px-6 py-4 text-center text-gray-500">
                    No verifications have been submitted yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="px-6 py-4 border-t bg-gray-50">
          <Link href="/admin/verifications" className="text-indigo-600 hover:text-indigo-900 font-medium">
            View all verifications →
          </Link>
        </div>
      </div>

      {/* Quick Links */}
      <div className="grid gap-4 md:grid-cols-3">
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="font-medium mb-2">Verification Management</h3>
          <p className="text-sm text-gray-600 mb-4">
            View all verification requests, process pending verifications, and manage verification history.
          </p>
          <Link href="/admin/verifications" className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
            View Verifications
          </Link>
        </div>
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="font-medium mb-2">Customer Management</h3>
          <p className="text-sm text-gray-600 mb-4">
            View and manage customer accounts, verification history, and communication logs.
          </p>
          <Link href="/admin/customers" className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
            Manage Customers
          </Link>
        </div>
        <div className="bg-white rounded-lg shadow p-6">
          <h3 className="font-medium mb-2">MAC Coverage Rules</h3>
          <p className="text-sm text-gray-600 mb-4">
            Configure and update MAC jurisdiction-specific coverage rules for wound care products.
          </p>
          <button className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
            Edit MAC Rules
          </button>
        </div>
      </div>
    </div>
  );
}

DashboardPage.layout = (page: React.ReactNode) => (
  <MainLayout title="Dashboard" children={page} />
);

export default DashboardPage;
