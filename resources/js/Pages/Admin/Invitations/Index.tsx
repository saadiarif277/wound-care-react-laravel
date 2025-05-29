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
  FiBriefcase
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
}

interface InviteFormData {
  email: string;
  role: string;
  organization_id: string;
  message?: string;
}

export default function InvitationsIndex({ auth, invitations }: InvitationsProps) {
  const [showInviteForm, setShowInviteForm] = useState(false);

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
      }
    });
  };

  const handleResendInvitation = (invitationId: string) => {
    router.post(`/admin/invitations/${invitationId}/resend`);
  };

  const handleCancelInvitation = (invitationId: string) => {
    if (confirm('Are you sure you want to cancel this invitation?')) {
      router.delete(`/admin/invitations/${invitationId}`);
    }
  };

  return (
    <MainLayout title="User Invitations">
      <Head title="User Invitations" />

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
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="user@example.com"
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
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Add a personal message to the invitation..."
              />
            </div>

            <div className="flex gap-3 justify-end">
              <Button
                variant="secondary"
                onClick={() => setShowInviteForm(false)}
              >
                Cancel
              </Button>
              <Button
                onClick={handleSendInvitation}
                disabled={processing}
              >
                {processing ? 'Sending...' : 'Send Invitation'}
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
                {invitations.data.map((invitation) => (
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
                      <div className="flex space-x-2">
                        {(invitation.status === 'pending' || invitation.status === 'sent') && (
                          <button
                            onClick={() => handleResendInvitation(invitation.id)}
                            className="text-blue-600 hover:text-blue-900 p-1"
                            title="Resend Invitation"
                          >
                            <FiRefreshCw className="w-4 h-4" />
                          </button>
                        )}
                        <button className="text-gray-600 hover:text-gray-900 p-1" title="View Details">
                          <FiEye className="w-4 h-4" />
                        </button>
                        {invitation.status !== 'accepted' && (
                          <button
                            onClick={() => handleCancelInvitation(invitation.id)}
                            className="text-red-600 hover:text-red-900 p-1"
                            title="Cancel Invitation"
                          >
                            <FiTrash2 className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </MainLayout>
  );
}
