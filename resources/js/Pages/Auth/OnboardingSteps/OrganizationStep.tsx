import React from 'react';
import { Building2, Mail, Phone, MapPin, CreditCard, User } from 'lucide-react';

interface OrganizationStepProps {
    data: any;
    setData: (field: string, value: any) => void;
    errors: any;
    states: Array<{ code: string; name: string }>;
}

export default function OrganizationStep({ data, setData, errors, states }: OrganizationStepProps) {
    const organizationTypes = [
        { value: 'healthcare', label: 'Healthcare Provider' },
        { value: 'clinic', label: 'Medical Clinic' },
        { value: 'hospital', label: 'Hospital' },
        { value: 'wound_care', label: 'Wound Care Center' },
        { value: 'home_health', label: 'Home Health Agency' },
        { value: 'other', label: 'Other' }
    ];

    return (
        <div className="space-y-8">
            <div>
                <h2 className="text-2xl font-bold text-gray-900 flex items-center mb-2">
                    <Building2 className="h-6 w-6 mr-3 text-blue-600" />
                    Organization Information
                </h2>
                <p className="text-gray-600">Tell us about your organization</p>
            </div>

            {/* Basic Information */}
            <div className="space-y-6">
                <h3 className="text-lg font-semibold text-gray-800 border-b pb-2">Basic Information</h3>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Organization Name <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.organization_name}
                            onChange={(e) => setData('organization_name', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.organization_name ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="Enter your organization name"
                        />
                        {errors.organization_name && (
                            <p className="mt-1 text-sm text-red-600">{errors.organization_name}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Organization Type <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.organization_type}
                            onChange={(e) => setData('organization_type', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.organization_type ? 'border-red-500' : 'border-gray-300'
                            }`}
                        >
                            <option value="">Select organization type</option>
                            {organizationTypes.map(type => (
                                <option key={type.value} value={type.value}>{type.label}</option>
                            ))}
                        </select>
                        {errors.organization_type && (
                            <p className="mt-1 text-sm text-red-600">{errors.organization_type}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Tax ID / EIN <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.organization_tax_id}
                            onChange={(e) => setData('organization_tax_id', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.organization_tax_id ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="12-3456789"
                        />
                        {errors.organization_tax_id && (
                            <p className="mt-1 text-sm text-red-600">{errors.organization_tax_id}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            <Mail className="inline h-4 w-4 mr-1" />
                            Contact Email <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            value={data.contact_email}
                            onChange={(e) => setData('contact_email', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.contact_email ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="contact@organization.com"
                        />
                        {errors.contact_email && (
                            <p className="mt-1 text-sm text-red-600">{errors.contact_email}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            <Phone className="inline h-4 w-4 mr-1" />
                            Contact Phone <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="tel"
                            value={data.contact_phone}
                            onChange={(e) => setData('contact_phone', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.contact_phone ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="(555) 123-4567"
                        />
                        {errors.contact_phone && (
                            <p className="mt-1 text-sm text-red-600">{errors.contact_phone}</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Physical Address */}
            <div className="space-y-6">
                <h3 className="text-lg font-semibold text-gray-800 border-b pb-2 flex items-center">
                    <MapPin className="h-5 w-5 mr-2 text-gray-600" />
                    Physical Address
                </h3>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Street Address <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.address ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="123 Main Street, Suite 100"
                        />
                        {errors.address && (
                            <p className="mt-1 text-sm text-red-600">{errors.address}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            City <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.city}
                            onChange={(e) => setData('city', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.city ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="City"
                        />
                        {errors.city && (
                            <p className="mt-1 text-sm text-red-600">{errors.city}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            State <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.state}
                            onChange={(e) => setData('state', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.state ? 'border-red-500' : 'border-gray-300'
                            }`}
                        >
                            <option value="">Select state</option>
                            {states.map(state => (
                                <option key={state.code} value={state.code}>{state.name}</option>
                            ))}
                        </select>
                        {errors.state && (
                            <p className="mt-1 text-sm text-red-600">{errors.state}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            ZIP Code <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.zip_code}
                            onChange={(e) => setData('zip_code', e.target.value)}
                            className={`w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                                errors.zip_code ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="12345"
                        />
                        {errors.zip_code && (
                            <p className="mt-1 text-sm text-red-600">{errors.zip_code}</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Billing Information */}
            <div className="space-y-6">
                <h3 className="text-lg font-semibold text-gray-800 border-b pb-2 flex items-center">
                    <CreditCard className="h-5 w-5 mr-2 text-gray-600" />
                    Billing Information
                </h3>
                
                <div className="mb-4">
                    <label className="flex items-center">
                        <input
                            type="checkbox"
                            checked={data.billing_address === data.address}
                            onChange={(e) => {
                                if (e.target.checked) {
                                    setData('billing_address', data.address);
                                    setData('billing_city', data.city);
                                    setData('billing_state', data.state);
                                    setData('billing_zip', data.zip_code);
                                } else {
                                    setData('billing_address', '');
                                    setData('billing_city', '');
                                    setData('billing_state', '');
                                    setData('billing_zip', '');
                                }
                            }}
                            className="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                        />
                        <span className="ml-2 text-sm text-gray-700">Same as physical address</span>
                    </label>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Billing Address
                        </label>
                        <input
                            type="text"
                            value={data.billing_address}
                            onChange={(e) => setData('billing_address', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Billing street address"
                            disabled={data.billing_address === data.address}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Billing City
                        </label>
                        <input
                            type="text"
                            value={data.billing_city}
                            onChange={(e) => setData('billing_city', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Billing city"
                            disabled={data.billing_address === data.address}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Billing State
                        </label>
                        <select
                            value={data.billing_state}
                            onChange={(e) => setData('billing_state', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            disabled={data.billing_address === data.address}
                        >
                            <option value="">Select state</option>
                            {states.map(state => (
                                <option key={state.code} value={state.code}>{state.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Billing ZIP
                        </label>
                        <input
                            type="text"
                            value={data.billing_zip}
                            onChange={(e) => setData('billing_zip', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="12345"
                            disabled={data.billing_address === data.address}
                        />
                    </div>
                </div>
            </div>

            {/* Accounts Payable Contact */}
            <div className="space-y-6">
                <h3 className="text-lg font-semibold text-gray-800 border-b pb-2 flex items-center">
                    <User className="h-5 w-5 mr-2 text-gray-600" />
                    Accounts Payable Contact
                </h3>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Contact Name
                        </label>
                        <input
                            type="text"
                            value={data.ap_contact_name}
                            onChange={(e) => setData('ap_contact_name', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="John Doe"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Contact Phone
                        </label>
                        <input
                            type="tel"
                            value={data.ap_contact_phone}
                            onChange={(e) => setData('ap_contact_phone', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="(555) 123-4567"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Contact Email
                        </label>
                        <input
                            type="email"
                            value={data.ap_contact_email}
                            onChange={(e) => setData('ap_contact_email', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="ap@organization.com"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
} 