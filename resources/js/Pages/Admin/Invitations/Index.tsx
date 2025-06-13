import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  FiMail,
  FiPlus,
  FiRefreshCw,
  FiTrash2,
  FiEye,
  FiCalendar,
  FiUser,
  FiBriefcase,
  FiCheck,
  FiX,
  FiAlertCircle
} from 'react-icons/fi';

interface Invitation {
  id: string;
  email: string;
  role: string;
  status: 'pending' | 'sent' | 'accepted' | 'expired' | 'cancelled';
  organization?: {
    id: string;
    name: string;
  };
  invited_by?: {
    first_name: string;
    last_name: string;
  };
  created_at: string;
  expires_at: string;
}

interface InvitationsProps {
  auth: {
    user: any;
  };
  invitations: {
    data: Invitation[];
    links: any;
    meta: any;
  };
  flash?: {
    success?: string;
    error?: string;
  };
}

interface InviteFormData {
  email: string;
  role: string;
  organization_id: string;
  message?: string;
}

export default function InvitationsIndex({ auth, invitations, flash }: InvitationsProps) {
  const [showInviteForm, setShowInviteForm] = useState(false);
  const [showSuccessMessage, setShowSuccessMessage] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm<InviteFormData>({
    email: '',
    role: '',
    organization_id: '',
    message: ''
  });

  const roles = [
    { value: 'provider', label: 'Provider' },
    { value: 'office-manager', label: 'Office Manager' },
    { value: 'msc-rep', label: 'MSC Rep' },
    { value: 'msc-subrep', label: 'MSC Sub-Rep' },
    { value: 'msc-admin', label: 'MSC Admin' }
  ];

  const getStatusBadge = (status: 'pending' | 'sent' | 'accepted' | 'expired' | 'cancelled') => {
    const variants = {
      pending: 'bg-yellow-100 text-yellow-800',
      sent: 'bg-blue-100 text-blue-800',
      accepted: 'bg-green-100 text-green-800',
      expired: 'bg-red-100 text-red-800',
      cancelled: 'bg-gray-100 text-gray-800'
    };

    return `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${variants[status] || variants.pending}`;
  };

  const handleSendInvitation = () => {
    post('/admin/invitations', {
      onSuccess: () => {
        reset();
        setShowInviteForm(false);
        setShowSuccessMessage(true);
        setTimeout(() => setShowSuccessMessage(false), 5000);
      },
      onError: (errors) => {
        console.error('Invitation error:', errors);
      }
    });
  };

  const handleResendInvitation = (invitationId: string) => {
    router.post(`/admin/invitations/${invitationId}/resend`, {}, {
      onSuccess: () => {
        setShowSuccessMessage(true);
        setTimeout(() => setShowSuccessMessage(false), 5000);
      }
    });
  };

  const handleCancelInvitation = (invitationId: string) => {
    if (confirm('Are you sure you want to cancel this invitation?')) {
      router.delete(`/admin/invitations/${invitationId}`, {
        onSuccess: () => {
          setShowSuccessMessage(true);
          setTimeout(() => setShowSuccessMessage(false), 5000);
        }
      });
    }
  };

  return (
    <MainLayout title="User Invitations">
      <Head title="User Invitations" />

      {/* Success/Error Messages */}
      {(showSuccessMessage || flash?.success) && (
        <div className="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-center">
            <FiCheck className="w-5 h-5 text-green-600 mr-3" />
            <p className="text-green-800 font-medium">
              {flash?.success || 'Operation completed successfully!'}
            </p>
            <button
              onClick={() => setShowSuccessMessage(false)}
              className="ml-auto text-green-600 hover:text-green-800"
            >
              <FiX className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {flash?.error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-center">
            <FiAlertCircle className="w-5 h-5 text-red-600 mr-3" />
            <p className="text-red-800 font-medium">{flash.error}</p>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">User Invitations</h1>
          <p className="mt-1 text-sm text-gray-600">
            Send and manage invitations for new users across all roles
          </p>
        </div>
        <Button onClick={() => setShowInviteForm(true)}>
          <FiPlus className="w-4 h-4 mr-2" />
          Send Invitation
        </Button>
      </div>

      {/* Invite Form Modal */}
      {showInviteForm && (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Send New Invitation</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Email Address *
                </label>
                <input
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 transition-colors ${
                    errors.email
                      ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                      : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                  }`}
                  placeholder="user@example.com"
                  required
                />
                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Role *
                </label>
                <select
                  value={data.role}
                  onChange={(e) => setData('role', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 transition-colors ${
                    errors.role
                      ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                      : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                  }`}
                  required
                >
                  <option value="">Select role</option>
                  {roles.map((role) => (
                    <option key={role.value} value={role.value}>
                      {role.label}
                    </option>
                  ))}
                </select>
                {errors.role && <p className="mt-1 text-sm text-red-600">{errors.role}</p>}
              </div>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Custom Message (Optional)
              </label>
              <textarea
                value={data.message}
                onChange={(e) => setData('message', e.target.value)}
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                placeholder="Add a personal message to the invitation..."
              />
            </div>

            <div className="flex gap-3 justify-end">
              <Button
                variant="secondary"
                onClick={() => setShowInviteForm(false)}
                disabled={processing}
              >
                Cancel
              </Button>
              <Button
                variant="primary"
                onClick={handleSendInvitation}
                disabled={processing || !data.email || !data.role}
                isLoading={processing}
              >
                <FiMail className="w-4 h-4 mr-2" />
                Send Invitation
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Invitations Table */}
      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    User
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Role
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Organization
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Sent Date
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {invitations.data.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center">
                      <div className="flex flex-col items-center">
                        <FiMail className="w-12 h-12 text-gray-400 mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No invitations yet</h3>
                        <p className="text-gray-500 mb-4">Get started by sending your first invitation.</p>
                        <Button
                          variant="primary"
                          onClick={() => setShowInviteForm(true)}
                        >
                          <FiPlus className="w-4 h-4 mr-2" />
                          Send First Invitation
                        </Button>
                      </div>
                    </td>
                  </tr>
                ) : (
                  invitations.data.map((invitation) => (
                  <tr key={invitation.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <FiUser className="w-5 h-5 text-gray-400 mr-3" />
                        <div>
                          <div className="text-sm font-medium text-gray-900">
                            {invitation.email}
                          </div>
                          {invitation.invited_by && (
                            <div className="text-sm text-gray-500">
                              Invited by {invitation.invited_by.first_name} {invitation.invited_by.last_name}
                            </div>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <Badge variant="outline">
                        {roles.find(r => r.value === invitation.role)?.label || invitation.role}
                      </Badge>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {invitation.organization ? (
                        <div className="flex items-center">
                          <FiBriefcase className="w-4 h-4 text-gray-400 mr-2" />
                          <span className="text-sm text-gray-900">
                            {invitation.organization.name}
                          </span>
                        </div>
                      ) : (
                        <span className="text-sm text-gray-500">—</span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={getStatusBadge(invitation.status)}>
                        {invitation.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div className="flex items-center">
                        <FiCalendar className="w-4 h-4 mr-2" />
                        {new Date(invitation.created_at).toLocaleDateString()}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <div className="flex space-x-1">
                        {(invitation.status === 'pending' || invitation.status === 'sent') && (
                          <button
                            onClick={() => handleResendInvitation(invitation.id)}
                            className="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-md transition-colors"
                            title="Resend Invitation"
                          >
                            <FiRefreshCw className="w-4 h-4" />
                          </button>
                        )}
                        <button
                          className="inline-flex items-center justify-center w-8 h-8 text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-md transition-colors"
                          title="View Details"
                          onClick={() => {
                            // TODO: Implement view details modal
                            alert('View details functionality coming soon!');
                          }}
                        >
                          <FiEye className="w-4 h-4" />
                        </button>
                        {invitation.status !== 'accepted' && (
                          <button
                            onClick={() => handleCancelInvitation(invitation.id)}
                            className="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-md transition-colors"
                            title="Cancel Invitation"
                          >
                            <FiTrash2 className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </MainLayout>
  );
}
