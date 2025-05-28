import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiFileText, FiCheckCircle, FiClock, FiAlertCircle, FiDownload, FiEye } from 'react-icons/fi';

interface DocuSealSubmissionsProps {
  auth: {
    user: any;
  };
}

export default function DocuSealSubmissions({ auth }: DocuSealSubmissionsProps) {
  // Mock data - in production, this would come from the backend
  const submissions = [
    {
      id: '1',
      orderNumber: 'ORD-2024-001',
      documentType: 'Insurance Verification',
      status: 'pending',
      createdAt: '2024-01-15T10:30:00Z',
      signerName: 'Dr. Jane Smith',
      signerEmail: 'jane.smith@hospital.com'
    },
    {
      id: '2',
      orderNumber: 'ORD-2024-002',
      documentType: 'Order Form',
      status: 'completed',
      createdAt: '2024-01-15T09:15:00Z',
      signerName: 'Dr. Michael Johnson',
      signerEmail: 'michael.johnson@clinic.com',
      completedAt: '2024-01-15T11:45:00Z'
    },
    {
      id: '3',
      orderNumber: 'ORD-2024-003',
      documentType: 'Onboarding Form',
      status: 'overdue',
      createdAt: '2024-01-14T14:20:00Z',
      signerName: 'Dr. Sarah Wilson',
      signerEmail: 'sarah.wilson@medical.com'
    },
    {
      id: '4',
      orderNumber: 'ORD-2024-004',
      documentType: 'Insurance Verification',
      status: 'completed',
      createdAt: '2024-01-14T16:00:00Z',
      signerName: 'Dr. Robert Davis',
      signerEmail: 'robert.davis@healthcare.com',
      completedAt: '2024-01-15T08:30:00Z'
    }
  ];

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <FiCheckCircle className="w-5 h-5 text-green-500" />;
      case 'pending':
        return <FiClock className="w-5 h-5 text-yellow-500" />;
      case 'overdue':
        return <FiAlertCircle className="w-5 h-5 text-red-500" />;
      default:
        return <FiFileText className="w-5 h-5 text-gray-500" />;
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
    <MainLayout title="Document Submissions">
      <Head title="Document Submissions" />

      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Document Submissions</h1>
        <p className="mt-1 text-sm text-gray-600">
          View and manage all document signing submissions
        </p>
      </div>

      {/* Submissions Table */}
      <div className="bg-white shadow overflow-hidden sm:rounded-lg">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Document
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Signer
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Created
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {submissions.map((submission) => (
                <tr key={submission.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      {getStatusIcon(submission.status)}
                      <div className="ml-3">
                        <div className="text-sm font-medium text-gray-900">
                          {submission.documentType}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{submission.orderNumber}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{submission.signerName}</div>
                    <div className="text-sm text-gray-500">{submission.signerEmail}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={getStatusBadge(submission.status)}>
                      {submission.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div>{new Date(submission.createdAt).toLocaleDateString()}</div>
                    <div>{new Date(submission.createdAt).toLocaleTimeString()}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div className="flex space-x-2">
                      <button className="text-indigo-600 hover:text-indigo-900 p-1">
                        <FiEye className="w-4 h-4" title="View Details" />
                      </button>
                      {submission.status === 'completed' && (
                        <button className="text-green-600 hover:text-green-900 p-1">
                          <FiDownload className="w-4 h-4" title="Download Document" />
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
    </MainLayout>
  );
}
