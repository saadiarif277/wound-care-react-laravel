import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiArrowLeft,
  FiUser,
  FiMail,
  FiPhone,
  FiCalendar,
  FiCheck,
  FiX,
  FiClock,
  FiFileText,
  FiMapPin,
  FiHome,
  FiAlertCircle,
  FiCheckCircle,
  FiSend
} from 'react-icons/fi';

interface AccessRequest {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  requested_role: string;
  status: 'pending' | 'approved' | 'denied';
  created_at: string;
  request_notes?: string;
  admin_notes?: string;
  reviewed_at?: string;
  reviewed_by?: {
    first_name: string;
    last_name: string;
  };
}

interface Props {
  accessRequest: AccessRequest;
  roleSpecificFields: Record<string, string>;
}

export default function AccessRequestShow({ accessRequest, roleSpecificFields }: Props) {
  const [showApprovalModal, setShowApprovalModal] = useState(false);
  const [showDenialModal, setShowDenialModal] = useState(false);

  const approveForm = useForm({
    admin_notes: ''
  });

  const denyForm = useForm({
    admin_notes: ''
  });

  const handleApprove = (e: React.FormEvent) => {
    e.preventDefault();
    approveForm.post(route('access-requests.approve', accessRequest.id), {
      onSuccess: () => {
        setShowApprovalModal(false);
      }
    });
  };

  const handleDeny = (e: React.FormEvent) => {
    e.preventDefault();
    denyForm.post(route('access-requests.deny', accessRequest.id), {
      onSuccess: () => {
        setShowDenialModal(false);
      }
    });
  };

  const getStatusBadge = (status: string) => {
    const statusConfig = {
      pending: {
        bg: 'bg-yellow-100',
        text: 'text-yellow-800',
        icon: FiClock,
        label: 'Pending Review'
      },
      approved: {
        bg: 'bg-green-100',
        text: 'text-green-800',
        icon: FiCheckCircle,
        label: 'Approved'
      },
      denied: {
        bg: 'bg-red-100',
        text: 'text-red-800',
        icon: FiAlertCircle,
        label: 'Denied'
      },
    };

    const config = statusConfig[status as keyof typeof statusConfig];
    const Icon = config.icon;

    return (
      <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${config.bg} ${config.text}`}>
        <Icon className="mr-2 h-4 w-4" />
        {config.label}
      </span>
    );
  };

  const getRoleDisplayName = (role: string) => {
    const roleNames = {
      provider: 'Healthcare Provider',
      office_manager: 'Office Manager',
      msc_rep: 'MSC Sales Representative',
      msc_subrep: 'MSC Sub-Representative',
      msc_admin: 'MSC Administrator',
    };
    return roleNames[role as keyof typeof roleNames] || role;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <MainLayout>
      <Head title={`Access Request - ${accessRequest.first_name} ${accessRequest.last_name}`} />

      <div className="py-6">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-6">
            <div className="flex items-center mb-4">
              <button
                onClick={() => window.history.back()}
                className="mr-4 p-2 text-gray-400 hover:text-gray-600"
              >
                <FiArrowLeft className="h-5 w-5" />
              </button>
              <div className="flex-1">
                <h1 className="text-2xl font-bold text-gray-900">
                  Access Request Details
                </h1>
                <p className="text-sm text-gray-600">
                  Review and manage access request
                </p>
              </div>
              <div className="ml-4">
                {getStatusBadge(accessRequest.status)}
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Main Content */}
            <div className="lg:col-span-2 space-y-6">
              {/* Personal Information */}
              <div className="bg-white shadow-sm rounded-lg border border-gray-200">
                <div className="px-6 py-4 border-b border-gray-200">
                  <h3 className="text-lg font-medium text-gray-900 flex items-center">
                    <FiUser className="mr-2 h-5 w-5 text-blue-600" />
                    Personal Information
                  </h3>
                </div>
                <div className="px-6 py-4 space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">
                        Full Name
                      </label>
                      <p className="text-sm text-gray-900">
                        {accessRequest.first_name} {accessRequest.last_name}
                      </p>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1">
                        Requested Role
                      </label>
                      <p className="text-sm text-gray-900">
                        {getRoleDisplayName(accessRequest.requested_role)}
                      </p>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-500 mb-1 flex items-center">
                        <FiMail className="mr-1 h-3 w-3" />
                        Email Address
                      </label>
                      <p className="text-sm text-gray-900">{accessRequest.email}</p>
                    </div>
                    {accessRequest.phone && (
                      <div>
                        <label className="block text-sm font-medium text-gray-500 mb-1 flex items-center">
                          <FiPhone className="mr-1 h-3 w-3" />
                          Phone Number
                        </label>
                        <p className="text-sm text-gray-900">{accessRequest.phone}</p>
                      </div>
                    )}
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-500 mb-1 flex items-center">
                      <FiCalendar className="mr-1 h-3 w-3" />
                      Request Submitted
                    </label>
                    <p className="text-sm text-gray-900">{formatDate(accessRequest.created_at)}</p>
                  </div>
                </div>
              </div>

              {/* Role-Specific Information */}
              {Object.keys(roleSpecificFields).length > 0 && (
                <div className="bg-white shadow-sm rounded-lg border border-gray-200">
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                      <FiHome className="mr-2 h-5 w-5 text-blue-600" />
                      {getRoleDisplayName(accessRequest.requested_role)} Details
                    </h3>
                  </div>
                  <div className="px-6 py-4 space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {Object.entries(roleSpecificFields).map(([label, value]) => (
                        value && (
                          <div key={label}>
                            <label className="block text-sm font-medium text-gray-500 mb-1">
                              {label}
                            </label>
                            <p className="text-sm text-gray-900">
                              {value || 'Not provided'}
                            </p>
                          </div>
                        )
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {/* Request Notes */}
              {accessRequest.request_notes && (
                <div className="bg-white shadow-sm rounded-lg border border-gray-200">
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                      <FiFileText className="mr-2 h-5 w-5 text-blue-600" />
                      Additional Notes
                    </h3>
                  </div>
                  <div className="px-6 py-4">
                    <p className="text-sm text-gray-900 whitespace-pre-wrap">
                      {accessRequest.request_notes}
                    </p>
                  </div>
                </div>
              )}

              {/* Admin Notes (if reviewed) */}
              {accessRequest.admin_notes && (
                <div className="bg-gray-50 shadow-sm rounded-lg border border-gray-200">
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                      <FiFileText className="mr-2 h-5 w-5 text-gray-600" />
                      Admin Review Notes
                    </h3>
                  </div>
                  <div className="px-6 py-4">
                    <p className="text-sm text-gray-900 whitespace-pre-wrap">
                      {accessRequest.admin_notes}
                    </p>
                    {accessRequest.reviewed_by && accessRequest.reviewed_at && (
                      <div className="mt-3 pt-3 border-t border-gray-200">
                        <p className="text-xs text-gray-500">
                          Reviewed by {accessRequest.reviewed_by.first_name} {accessRequest.reviewed_by.last_name} on{' '}
                          {formatDate(accessRequest.reviewed_at)}
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Sidebar - Actions */}
            <div className="space-y-6">
              {/* Request Status */}
              <div className="bg-white shadow-sm rounded-lg border border-gray-200">
                <div className="px-6 py-4">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">
                    Request Status
                  </h3>
                  <div className="mb-4">
                    {getStatusBadge(accessRequest.status)}
                  </div>

                  {accessRequest.status === 'pending' && (
                    <div className="space-y-3">
                      <button
                        onClick={() => setShowApprovalModal(true)}
                        className="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 flex items-center justify-center"
                      >
                        <FiCheck className="mr-2 h-4 w-4" />
                        Approve Request
                      </button>
                      <button
                        onClick={() => setShowDenialModal(true)}
                        className="w-full bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 flex items-center justify-center"
                      >
                        <FiX className="mr-2 h-4 w-4" />
                        Deny Request
                      </button>
                    </div>
                  )}

                  {accessRequest.status !== 'pending' && accessRequest.reviewed_at && (
                    <div className="text-sm text-gray-600">
                      <p className="flex items-center mb-1">
                        <FiCalendar className="mr-1 h-3 w-3" />
                        Reviewed on {formatDate(accessRequest.reviewed_at)}
                      </p>
                      {accessRequest.reviewed_by && (
                        <p className="flex items-center">
                          <FiUser className="mr-1 h-3 w-3" />
                          By {accessRequest.reviewed_by.first_name} {accessRequest.reviewed_by.last_name}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* Quick Actions */}
              <div className="bg-white shadow-sm rounded-lg border border-gray-200">
                <div className="px-6 py-4">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">
                    Quick Actions
                  </h3>
                  <div className="space-y-2">
                    <button className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">
                      Send Email to Applicant
                    </button>
                    <button className="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">
                      View Similar Requests
                    </button>
                    {accessRequest.status === 'approved' && (
                      <button className="w-full text-left px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg">
                        Create User Account
                      </button>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Approval Modal */}
      {showApprovalModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <div className="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full mb-4">
                <FiCheck className="h-6 w-6 text-green-600" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 text-center mb-2">
                Approve Access Request
              </h3>
              <p className="text-sm text-gray-500 text-center mb-4">
                Are you sure you want to approve this access request?
              </p>

              <form onSubmit={handleApprove}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Admin Notes (Optional)
                  </label>
                  <textarea
                    value={approveForm.data.admin_notes}
                    onChange={(e) => approveForm.setData('admin_notes', e.target.value)}
                    rows={3}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="Add any notes about this approval..."
                  />
                </div>

                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setShowApprovalModal(false)}
                    className="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-400"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={approveForm.processing}
                    className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center justify-center"
                  >
                    {approveForm.processing ? (
                      <>
                        <svg className="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Approving...
                      </>
                    ) : (
                      <>
                        <FiSend className="mr-2 h-4 w-4" />
                        Approve
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Denial Modal */}
      {showDenialModal && (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
          <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div className="mt-3">
              <div className="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <FiX className="h-6 w-6 text-red-600" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 text-center mb-2">
                Deny Access Request
              </h3>
              <p className="text-sm text-gray-500 text-center mb-4">
                Please provide a reason for denying this request.
              </p>

              <form onSubmit={handleDeny}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Reason for Denial *
                  </label>
                  <textarea
                    value={denyForm.data.admin_notes}
                    onChange={(e) => denyForm.setData('admin_notes', e.target.value)}
                    rows={4}
                    required
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                    placeholder="Explain why this request is being denied..."
                  />
                  {denyForm.errors.admin_notes && (
                    <p className="mt-1 text-sm text-red-600">{denyForm.errors.admin_notes}</p>
                  )}
                </div>

                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setShowDenialModal(false)}
                    className="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-400"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={denyForm.processing}
                    className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50 flex items-center justify-center"
                  >
                    {denyForm.processing ? (
                      <>
                        <svg className="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Denying...
                      </>
                    ) : (
                      <>
                        <FiSend className="mr-2 h-4 w-4" />
                        Deny Request
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </MainLayout>
  );
}
