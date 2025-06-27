import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Organization } from '@/types';

interface Props {
    organization: Organization;
}

export default function OrganizationDetail({ organization }: Props) {
    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const getStatusBadge = (status: string) => {
        const statusClasses = {
            active: 'bg-green-100 text-green-800',
            pending: 'bg-yellow-100 text-yellow-800',
            inactive: 'bg-red-100 text-red-800',
        };

        return (
            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClasses[status] || 'bg-gray-100 text-gray-800'}`}>
                {status?.charAt(0).toUpperCase() + status?.slice(1) || 'Unknown'}
            </span>
        );
    };

    return (
        <>
            <Head title={organization.name} />

            <div className="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div className="p-6">
                        <div className="flex justify-between items-start mb-6">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900">
                                    {organization.name}
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Organization Details
                                </p>
                            </div>
                            <div className="flex space-x-3">
                                <Link
                                    href={route('admin.organizations.edit', organization.id)}
                                    className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => window.history.back()}
                                    className="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    Back
                                </button>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            {/* Basic Information */}
                            <div className="space-y-6">
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900 mb-4">
                                        Basic Information
                                    </h2>
                                    <dl className="space-y-4">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Organization Name
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.name}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Type
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.type || 'Not specified'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Status
                                            </dt>
                                            <dd className="mt-1">
                                                {getStatusBadge(organization.status)}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Tax ID
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.tax_id || 'Not provided'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                FHIR ID
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-mono">
                                                {organization.fhir_id || 'Not assigned'}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                {/* Sales Representative */}
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900 mb-4">
                                        Sales Representative
                                    </h2>
                                    <dl className="space-y-4">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Assigned Sales Rep
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.sales_rep ?
                                                    `${organization.sales_rep.first_name} ${organization.sales_rep.last_name}` :
                                                    'Not assigned'
                                                }
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            {/* Contact Information */}
                            <div className="space-y-6">
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900 mb-4">
                                        Contact Information
                                    </h2>
                                    <dl className="space-y-4">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Email
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.email ? (
                                                    <a
                                                        href={`mailto:${organization.email}`}
                                                        className="text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        {organization.email}
                                                    </a>
                                                ) : (
                                                    'Not provided'
                                                )}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Phone
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.phone ? (
                                                    <a
                                                        href={`tel:${organization.phone}`}
                                                        className="text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        {organization.phone}
                                                    </a>
                                                ) : (
                                                    'Not provided'
                                                )}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Address
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900 whitespace-pre-line">
                                                {organization.address ? (
                                                    <div>
                                                        <div>{organization.address}</div>
                                                        <div>
                                                            {[organization.city, organization.region, organization.postal_code]
                                                                .filter(Boolean)
                                                                .join(', ')}
                                                        </div>
                                                        {organization.country && (
                                                            <div>{organization.country}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    'Not provided'
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                {/* System Information */}
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900 mb-4">
                                        System Information
                                    </h2>
                                    <dl className="space-y-4">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Created
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.created_at ? formatDate(organization.created_at) : 'Unknown'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Last Updated
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {organization.updated_at ? formatDate(organization.updated_at) : 'Unknown'}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Organization ID
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900 font-mono">
                                                {organization.id}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        {/* Related Information */}
                        <div className="mt-8 pt-6 border-t border-gray-200">
                            <h2 className="text-lg font-medium text-gray-900 mb-4">
                                Related Information
                            </h2>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <dt className="text-sm font-medium text-gray-500">
                                        Facilities
                                    </dt>
                                    <dd className="mt-1 text-2xl font-semibold text-gray-900">
                                        {organization.facilities?.length || 0}
                                    </dd>
                                </div>

                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <dt className="text-sm font-medium text-gray-500">
                                        Users
                                    </dt>
                                    <dd className="mt-1 text-2xl font-semibold text-gray-900">
                                        {organization.users_count || 0}
                                    </dd>
                                </div>

                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <dt className="text-sm font-medium text-gray-500">
                                        Provider Invitations
                                    </dt>
                                    <dd className="mt-1 text-2xl font-semibold text-gray-900">
                                        {organization.provider_invitations?.length || 0}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
