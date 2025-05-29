import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiFileText, FiCheckCircle, FiClock, FiAlertCircle, FiDownload, FiEye } from 'react-icons/fi';
import { api, handleApiResponse } from '@/lib/api';

interface DocuSealSubmission {
  id: string;
  order_number: string;
  document_type: string;
  status: 'pending' | 'completed' | 'expired' | 'cancelled' | 'overdue';
  created_at: string;
  completed_at?: string;
  signer_name: string;
  signer_email: string;
  signing_url?: string;
  download_url?: string;
}

interface DocuSealSubmissionsProps {
  auth: {
    user: any;
  };
}

export default function DocuSealSubmissions({ auth }: DocuSealSubmissionsProps) {
  const [submissions, setSubmissions] = useState<DocuSealSubmission[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [stats, setStats] = useState({
    totalSubmissions: 0,
    pendingSignatures: 0,
    completedToday: 0,
    overdue: 0
  });

  // Fetch submissions from API
  const fetchSubmissions = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await api.docuseal.getSubmissions();

      // Transform the data to match our interface
      const transformedSubmissions: DocuSealSubmission[] = response.data.map((submission: any) => ({
        id: submission.id,
        order_number: submission.order?.order_number || 'Unknown Order',
        document_type: submission.document_type,
        status: submission.status,
        created_at: submission.created_at,
        completed_at: submission.completed_at,
        signer_name: submission.order?.provider?.name || 'Unknown Signer',
        signer_email: submission.order?.provider?.email || 'unknown@email.com',
        signing_url: submission.signing_url,
        download_url: submission.download_url
      }));

      setSubmissions(transformedSubmissions);

      // Calculate stats
      const total = transformedSubmissions.length;
      const pending = transformedSubmissions.filter(s => s.status === 'pending').length;
      const today = new Date().toDateString();
      const completedToday = transformedSubmissions.filter(s =>
        s.status === 'completed' &&
        s.completed_at &&
        new Date(s.completed_at).toDateString() === today
      ).length;
      const overdue = transformedSubmissions.filter(s => s.status === 'overdue').length;

      setStats({
        totalSubmissions: total,
        pendingSignatures: pending,
        completedToday,
        overdue
      });

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch submissions');
      console.error('Error fetching DocuSeal submissions:', err);
    } finally {
      setLoading(false);
    }
  };

  // Load data on component mount
  useEffect(() => {
    fetchSubmissions();
  }, []);

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
      case 'expired':
        return `${baseClasses} bg-gray-100 text-gray-800`;
      case 'cancelled':
        return `${baseClasses} bg-red-100 text-red-800`;
      default:
        return `${baseClasses} bg-gray-100 text-gray-800`;
    }
  };

  const handleDownload = async (submissionId: string) => {
    try {
      await api.docuseal.downloadDocument(submissionId);
    } catch (err) {
      console.error('Error downloading document:', err);
      alert('Failed to download document. Please try again.');
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  if (loading) {
    return (
      <MainLayout>
        <Head title="Document Management" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p className="text-gray-600">Loading document submissions...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  if (error) {
    return (
      <MainLayout>
        <Head title="Document Management" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <div className="text-red-500 text-lg mb-4">Error loading submissions</div>
            <div className="text-gray-600 mb-4">{error}</div>
            <button
              onClick={fetchSubmissions}
              className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
            >
              Try Again
            </button>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title="Document Management" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Document Management</h1>
            <p className="text-gray-500">
              Manage and track document signatures for orders
            </p>
          </div>
          <button
            onClick={fetchSubmissions}
            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 flex items-center gap-2"
          >
            <FiFileText className="w-4 h-4" />
            Refresh
          </button>
        </div>

        {/* Stats Grid */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiFileText className="h-8 w-8 text-blue-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Total Submissions
                  </dt>
                  <dd className="text-2xl font-semibold text-gray-900">
                    {stats.totalSubmissions}
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
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Pending Signatures
                  </dt>
                  <dd className="text-2xl font-semibold text-gray-900">
                    {stats.pendingSignatures}
                  </dd>
                </dl>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiCheckCircle className="h-8 w-8 text-green-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Completed Today
                  </dt>
                  <dd className="text-2xl font-semibold text-gray-900">
                    {stats.completedToday}
                  </dd>
                </dl>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiAlertCircle className="h-8 w-8 text-red-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Overdue
                  </dt>
                  <dd className="text-2xl font-semibold text-gray-900">
                    {stats.overdue}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        {/* Recent Document Submissions */}
        <div className="bg-white rounded-lg shadow">
          <div className="px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-medium text-gray-900">Recent Document Submissions</h3>
            <p className="text-sm text-gray-500 mt-1">Latest document signing requests and their status</p>
          </div>
          <div className="overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Order / Document
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
                      Completed
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
                              {submission.order_number}
                            </div>
                            <div className="text-sm text-gray-500">
                              {submission.document_type}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900">{submission.signer_name}</div>
                        <div className="text-sm text-gray-500">{submission.signer_email}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={getStatusBadge(submission.status)}>
                          {submission.status}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {formatDate(submission.created_at)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {submission.completed_at ? formatDate(submission.completed_at) : '-'}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div className="flex space-x-2">
                          {submission.status === 'completed' && submission.download_url && (
                            <button
                              onClick={() => handleDownload(submission.id)}
                              className="text-blue-600 hover:text-blue-900 flex items-center"
                            >
                              <FiDownload className="w-4 h-4 mr-1" />
                              Download
                            </button>
                          )}
                          {submission.signing_url && submission.status === 'pending' && (
                            <a
                              href={submission.signing_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-green-600 hover:text-green-900 flex items-center"
                            >
                              <FiEye className="w-4 h-4 mr-1" />
                              Sign
                            </a>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Empty State */}
            {submissions.length === 0 && (
              <div className="text-center py-8">
                <FiFileText className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                <p className="text-gray-600 text-lg">No document submissions found</p>
                <p className="text-gray-500 text-sm">Document submissions will appear here when orders are processed.</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
