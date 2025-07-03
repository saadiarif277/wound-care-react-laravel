import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiFileText, FiCheckCircle, FiClock, FiAlertCircle } from 'react-icons/fi';

interface DocusealDashboardProps {
  auth: {
    user: any;
  };
}

export default function DocusealDashboard({ auth }: DocusealDashboardProps) {
  // Mock data - in production, this would come from the backend
  const stats = {
    totalSubmissions: 156,
    pendingSignatures: 23,
    completedToday: 8,
    overdue: 3
  };

  const recentSubmissions = [
    {
      id: '1',
      orderNumber: 'ORD-2024-001',
      documentType: 'Insurance Verification',
      status: 'pending',
      createdAt: '2024-01-15T10:30:00Z',
      signerName: 'Dr. Jane Smith'
    },
    {
      id: '2',
      orderNumber: 'ORD-2024-002',
      documentType: 'Order Form',
      status: 'completed',
      createdAt: '2024-01-15T09:15:00Z',
      signerName: 'Dr. Michael Johnson'
    },
    {
      id: '3',
      orderNumber: 'ORD-2024-003',
      documentType: 'Onboarding Form',
      status: 'overdue',
      createdAt: '2024-01-14T14:20:00Z',
      signerName: 'Dr. Sarah Wilson'
    }
  ];

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <FiCheckCircle className="w-4 h-4 text-green-500" />;
      case 'pending':
        return <FiClock className="w-4 h-4 text-yellow-500" />;
      case 'overdue':
        return <FiAlertCircle className="w-4 h-4 text-red-500" />;
      default:
        return <FiFileText className="w-4 h-4 text-gray-500" />;
    }
  };

  const getStatusBadge = (status: string) => {
    const baseClasses = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium";
    switch (status) {
      case 'completed':
        return `${baseClasses} bg-green-100 text-green-800`;
      case 'pending':
        return `${baseClasses} bg-yellow-100 text-yellow-800`;
      case 'overdue':
        return `${baseClasses} bg-red-100 text-red-800`;
      default:
        return `${baseClasses} bg-gray-100 text-gray-800`;
    }
  };

  return (
    <MainLayout title="Docuseal Dashboard">
      <Head title="Docuseal Dashboard" />

      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Document Management</h1>
        <p className="mt-1 text-sm text-gray-600">
          Manage and track document signatures for orders
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiFileText className="h-6 w-6 text-gray-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Total Submissions
                  </dt>
                  <dd className="text-lg font-medium text-gray-900">
                    {stats.totalSubmissions}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiClock className="h-6 w-6 text-yellow-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Pending Signatures
                  </dt>
                  <dd className="text-lg font-medium text-gray-900">
                    {stats.pendingSignatures}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiCheckCircle className="h-6 w-6 text-green-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Completed Today
                  </dt>
                  <dd className="text-lg font-medium text-gray-900">
                    {stats.completedToday}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiAlertCircle className="h-6 w-6 text-red-400" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Overdue
                  </dt>
                  <dd className="text-lg font-medium text-gray-900">
                    {stats.overdue}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Submissions Table */}
      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-medium text-gray-900">
            Recent Document Submissions
          </h3>
          <p className="mt-1 max-w-2xl text-sm text-gray-500">
            Latest document signing requests and their status
          </p>
        </div>
        <ul className="divide-y divide-gray-200">
          {recentSubmissions.map((submission) => (
            <li key={submission.id}>
              <div className="px-4 py-4 sm:px-6">
                <div className="flex items-center justify-between">
                  <div className="flex items-center">
                    {getStatusIcon(submission.status)}
                    <div className="ml-3">
                      <p className="text-sm font-medium text-gray-900">
                        {submission.orderNumber}
                      </p>
                      <p className="text-sm text-gray-500">
                        {submission.documentType} • {submission.signerName}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center">
                    <span className={getStatusBadge(submission.status)}>
                      {submission.status}
                    </span>
                    <p className="ml-4 text-sm text-gray-500">
                      {new Date(submission.createdAt).toLocaleDateString()}
                    </p>
                  </div>
                </div>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </MainLayout>
  );
}
