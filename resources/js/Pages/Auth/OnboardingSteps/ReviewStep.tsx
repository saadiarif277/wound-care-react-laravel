import { CheckCircle, Building2, Hospital, User, FileText } from 'lucide-react';

interface ReviewStepProps {
    data: any;
    setData: (key: string, value: any) => void;
}

export default function ReviewStep({ data, setData }: ReviewStepProps) {
    return (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <CheckCircle className="h-8 w-8 text-green-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Review Your Information</h1>
                <p className="text-gray-600">Please review your information before completing setup</p>
            </div>

            {/* Organization Summary */}
            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <Building2 className="h-5 w-5" />
                    Organization Information
                </h3>
                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Organization Name</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.organization_name || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Tax ID</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.organization_tax_id || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Contact Email</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.contact_email || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Contact Phone</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.contact_phone || 'Not provided'}</dd>
                    </div>
                </dl>
            </div>

            {/* Facility Summary */}
            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <Hospital className="h-5 w-5" />
                    Facility Information
                </h3>
                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Facility Name</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.facility_name || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Facility Type</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.facility_type || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Group NPI</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.group_npi || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Facility Address</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                            {data.facility_address ? `${data.facility_address}, ${data.facility_city}, ${data.facility_state} ${data.facility_zip}` : 'Not provided'}
                        </dd>
                    </div>
                </dl>
            </div>

            {/* Provider Summary */}
            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <User className="h-5 w-5" />
                    Provider Information
                </h3>
                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Name</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                            {data.first_name && data.last_name ? `${data.first_name} ${data.last_name}` : 'Not provided'}
                            {data.credentials && `, ${data.credentials}`}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Email</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.email || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Individual NPI</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.individual_npi || 'Not provided'}</dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500">Specialty</dt>
                        <dd className="mt-1 text-sm text-gray-900">{data.specialty || 'Not provided'}</dd>
                    </div>
                </dl>
            </div>

            {/* BAA Status */}
            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <FileText className="h-5 w-5" />
                    Business Associate Agreement
                </h3>
                <p className="text-sm text-gray-900">
                    {data.baa_signed ? (
                        <span className="flex items-center gap-2 text-green-600">
                            <CheckCircle className="h-4 w-4" />
                            Signed on {data.baa_signed_at ? new Date(data.baa_signed_at).toLocaleDateString() : 'Unknown date'}
                        </span>
                    ) : (
                        <span className="text-red-600">Not yet signed</span>
                    )}
                </p>
            </div>

            {/* Terms Acceptance */}
            <div className="border-t pt-6">
                <label className="flex items-start">
                    <input
                        type="checkbox"
                        checked={data.accept_terms || false}
                        onChange={(e) => setData('accept_terms', e.target.checked)}
                        className="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <span className="ml-3 text-sm text-gray-700">
                        I have reviewed all the information above and confirm it is accurate. I accept the terms of service and privacy policy.
                    </span>
                </label>
            </div>
        </div>
    );
} 