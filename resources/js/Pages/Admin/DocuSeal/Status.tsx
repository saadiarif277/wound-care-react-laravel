import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiCheckCircle, FiClock, FiAlertCircle, FiRefreshCw } from 'react-icons/fi';

interface DocusealStatusProps {
  auth: {
    user: any;
  };
}

export default function DocusealStatus({ auth }: DocusealStatusProps) {
  return (
    <MainLayout title="Document Signing Status">
      <Head title="Document Signing Status" />

      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Document Signing Status</h1>
        <p className="mt-1 text-sm text-gray-600">
          Track the progress of document signing across all orders
        </p>
      </div>

      {/* Status Overview */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
          <div className="flex items-center">
            <FiClock className="h-8 w-8 text-yellow-600" />
            <div className="ml-4">
              <h3 className="text-lg font-medium text-yellow-900">Pending Signatures</h3>
              <p className="text-2xl font-bold text-yellow-600">23</p>
            </div>
          </div>
        </div>

        <div className="bg-green-50 border border-green-200 rounded-lg p-6">
          <div className="flex items-center">
            <FiCheckCircle className="h-8 w-8 text-green-600" />
            <div className="ml-4">
              <h3 className="text-lg font-medium text-green-900">Completed Today</h3>
              <p className="text-2xl font-bold text-green-600">8</p>
            </div>
          </div>
        </div>

        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center">
            <FiAlertCircle className="h-8 w-8 text-red-600" />
            <div className="ml-4">
              <h3 className="text-lg font-medium text-red-900">Overdue</h3>
              <p className="text-2xl font-bold text-red-600">3</p>
            </div>
          </div>
        </div>
      </div>

      {/* Coming Soon Message */}
      <div className="bg-white shadow rounded-lg">
        <div className="px-4 py-5 sm:p-6 text-center">
          <FiRefreshCw className="mx-auto h-12 w-12 text-gray-400 mb-4" />
          <h3 className="text-lg leading-6 font-medium text-gray-900 mb-2">
            Real-time Status Tracking
          </h3>
          <p className="text-sm text-gray-500 mb-4">
            Detailed document signing status tracking is coming soon. This page will show:
          </p>
          <ul className="text-sm text-gray-600 text-left max-w-md mx-auto space-y-2">
            <li>• Real-time signature progress updates</li>
            <li>• Document completion notifications</li>
            <li>• Automated follow-up reminders</li>
            <li>• Integration with order management</li>
          </ul>
        </div>
      </div>
    </MainLayout>
  );
}
