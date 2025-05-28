import React from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { FiMail, FiClock, FiCheckCircle, FiXCircle, FiRefreshCw, FiTrash2 } from 'react-icons/fi';
import { formatDistanceToNow, format } from 'date-fns';

interface ProviderInvitation {
    id: string;
    email: string;
    first_name: string;
    last_name: string;
    status: 'pending' | 'sent' | 'accepted' | 'expired' | 'cancelled';
    expires_at: string;
    created_at: string;
    sent_at?: string;
    accepted_at?: string;
    organization: {
        id: string;
        name: string;
    };
    invited_by: {
        id: string;
        first_name: string;
        last_name: string;
    };
}

interface PaginatedInvitations {
    data: ProviderInvitation[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface ProviderInvitationsIndexProps {
    invitations: PaginatedInvitations;
}

export default function ProviderInvitationsIndex({ invitations }: ProviderInvitationsIndexProps) {
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'sent':
                return 'bg-blue-100 text-blue-800';
            case 'accepted':
                return 'bg-green-100 text-green-800';
            case 'expired':
                return 'bg-red-100 text-red-800';
            case 'cancelled':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-yellow-100 text-yellow-800';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'sent':
                return <FiMail className="w-4 h-4" />;
            case 'accepted':
                return <FiCheckCircle className="w-4 h-4" />;
            case 'expired':
                return <FiClock className="w-4 h-4" />;
            case 'cancelled':
                return <FiXCircle className="w-4 h-4" />;
            default:
                return <FiClock className="w-4 h-4" />;
        }
    };

    const handleResendInvitation = (invitationId: string) => {
        router.post(`/admin/provider-invitations/${invitationId}/resend`);
    };

    const handleCancelInvitation = (invitationId: string) => {
        if (confirm('Are you sure you want to cancel this invitation?')) {
            router.delete(`/admin/provider-invitations/${invitationId}`);
        }
    };

    return (
        <div className="space-y-6">
            <Head title="Provider Invitations" />

            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Provider Invitations</h1>
                    <p className="text-gray-600 mt-1">
                        Manage provider invitation workflow and track status
                    </p>
                </div>
                <Button onClick={() => router.visit('/admin/customer-management')}>
                    Invite New Providers
                </Button>
            </div>

            {/* Summary Statistics */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center">
                            <FiMail className="h-8 w-8 text-blue-600" />
                            <div className="ml-3">
                                <p className="text-sm font-medium text-gray-500">Total Sent</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {invitations.data.filter(inv => inv.status === 'sent').length}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center">
                            <FiCheckCircle className="h-8 w-8 text-green-600" />
                            <div className="ml-3">
                                <p className="text-sm font-medium text-gray-500">Accepted</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {invitations.data.filter(inv => inv.status === 'accepted').length}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center">
                            <FiClock className="h-8 w-8 text-yellow-600" />
                            <div className="ml-3">
                                <p className="text-sm font-medium text-gray-500">Pending</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {invitations.data.filter(inv => inv.status === 'sent' || inv.status === 'pending').length}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center">
                            <FiXCircle className="h-8 w-8 text-red-600" />
                            <div className="ml-3">
                                <p className="text-sm font-medium text-gray-500">Expired</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {invitations.data.filter(inv => inv.status === 'expired').length}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Invitations Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Recent Invitations</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Provider
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Organization
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Sent
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Expires
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Invited By
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
                                            <div>
                                                <div className="text-sm font-medium text-gray-900">
                                                    {invitation.first_name} {invitation.last_name}
                                                </div>
                                                <div className="text-sm text-gray-500">{invitation.email}</div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-900">{invitation.organization.name}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(invitation.status)}`}>
                                                {getStatusIcon(invitation.status)}
                                                <span className="ml-1 capitalize">{invitation.status}</span>
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {invitation.sent_at ? formatDistanceToNow(new Date(invitation.sent_at), { addSuffix: true }) : '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {format(new Date(invitation.expires_at), 'MMM dd, yyyy')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {invitation.invited_by.first_name} {invitation.invited_by.last_name}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div className="flex space-x-2">
                                                {(invitation.status === 'sent' || invitation.status === 'pending') && (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => handleResendInvitation(invitation.id)}
                                                    >
                                                        <FiRefreshCw className="w-4 h-4 mr-1" />
                                                        Resend
                                                    </Button>
                                                )}
                                                {invitation.status !== 'accepted' && invitation.status !== 'cancelled' && (
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleCancelInvitation(invitation.id)}
                                                    >
                                                        <FiTrash2 className="w-4 h-4 mr-1" />
                                                        Cancel
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {invitations.data.length === 0 && (
                        <div className="text-center py-12">
                            <FiMail className="mx-auto h-12 w-12 text-gray-400" />
                            <h3 className="mt-2 text-sm font-medium text-gray-900">No invitations</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Get started by inviting providers to join organizations.
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
} 