import React, { useState, useEffect } from 'react';
import { X, Building2, Mail, Phone, MapPin, User, Save, Loader2 } from 'lucide-react';
import { api } from '@/lib/api';

interface OrganizationModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: (organization: any) => void;
    organization?: any; // For editing existing organization
    mode?: 'create' | 'edit';
}

interface FormData {
    name: string;
    tax_id: string;
    type: string;
    status: string;
    sales_rep_id: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    region: string;
    country: string;
    postal_code: string;
    fhir_id: string;
}

const OrganizationModal: React.FC<OrganizationModalProps> = ({
    isOpen,
    onClose,
    onSave,
    organization,
    mode = 'create'
}) => {
    const [formData, setFormData] = useState<FormData>({
        name: '',
        tax_id: '',
        type: '',
        status: 'active',
        sales_rep_id: '',
        email: '',
        phone: '',
        address: '',
        city: '',
        region: '',
        country: 'US',
        postal_code: '',
        fhir_id: '',
    });

    const [errors, setErrors] = useState<Partial<FormData>>({});
    const [isLoading, setIsLoading] = useState(false);
    const [salesReps, setSalesReps] = useState<any[]>([]);

    // Organization types
    const organizationTypes = [
        { value: '', label: 'Select Type' },
        { value: 'Hospital', label: 'Hospital' },
        { value: 'Clinic Group', label: 'Clinic Group' },
        { value: 'Private Practice', label: 'Private Practice' },
        { value: 'Surgery Center', label: 'Surgery Center' },
        { value: 'Wound Care Center', label: 'Wound Care Center' },
        { value: 'Other', label: 'Other' },
    ];

    // Load form data when editing
    useEffect(() => {
        if (mode === 'edit' && organization) {
            setFormData({
                name: organization.name || '',
                tax_id: organization.tax_id || '',
                type: organization.type || '',
                status: organization.status || 'active',
                sales_rep_id: organization.sales_rep_id || '',
                email: organization.email || '',
                phone: organization.phone || '',
                address: organization.address || '',
                city: organization.city || '',
                region: organization.region || '',
                country: organization.country || 'US',
                postal_code: organization.postal_code || '',
                fhir_id: organization.fhir_id || '',
            });
        } else {
            // Reset form for create mode
            setFormData({
                name: '',
                tax_id: '',
                type: '',
                status: 'active',
                sales_rep_id: '',
                email: '',
                phone: '',
                address: '',
                city: '',
                region: '',
                country: 'US',
                postal_code: '',
                fhir_id: '',
            });
        }
        setErrors({});
    }, [mode, organization, isOpen]);

    // Load sales reps
    useEffect(() => {
        if (isOpen) {
            loadSalesReps();
        }
    }, [isOpen]);

    const loadSalesReps = async () => {
        try {
            // This would need to be implemented in your API
            // const response = await api.salesReps.getAll();
            // setSalesReps(response.data || []);
            setSalesReps([]); // Placeholder
        } catch (error) {
            console.error('Error loading sales reps:', error);
        }
    };

    const handleInputChange = (field: keyof FormData, value: string) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));

        // Clear error when user starts typing
        if (errors[field]) {
            setErrors(prev => ({
                ...prev,
                [field]: ''
            }));
        }
    };

    const validateForm = (): boolean => {
        const newErrors: Partial<FormData> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'Organization name is required';
        }

        if (!formData.email.trim()) {
            newErrors.email = 'Email is required';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            newErrors.email = 'Please enter a valid email address';
        }

        if (formData.phone && !/^[\d\s\-\+\(\)\.]+$/.test(formData.phone)) {
            newErrors.phone = 'Please enter a valid phone number';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsLoading(true);

        try {
            let response;
            if (mode === 'edit' && organization) {
                response = await api.organizations.update(organization.id, formData);
            } else {
                response = await api.organizations.create(formData);
            }

            onSave(response.data);
            onClose();
        } catch (error: any) {
            // Handle validation errors from server
            if (error.message && error.message.includes('validation')) {
                // Parse server validation errors if available
                setErrors({ name: 'Server validation error occurred' });
            } else {
                setErrors({ name: error.message || 'An error occurred while saving' });
            }
        } finally {
            setIsLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-200">
                    <div className="flex items-center space-x-3">
                        <Building2 className="h-6 w-6 text-blue-600" />
                        <h2 className="text-xl font-semibold text-gray-900">
                            {mode === 'edit' ? 'Edit Organization' : 'Add New Organization'}
                        </h2>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <X className="h-6 w-6" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Basic Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900">Basic Information</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Organization Name */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Organization Name *
                                </label>
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                        errors.name ? 'border-red-300' : 'border-gray-300'
                                    }`}
                                    placeholder="Enter organization name"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            {/* Type */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Type
                                </label>
                                <select
                                    value={formData.type}
                                    onChange={(e) => handleInputChange('type', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    {organizationTypes.map(type => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Status */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Status
                                </label>
                                <select
                                    value={formData.status}
                                    onChange={(e) => handleInputChange('status', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="active">Active</option>
                                    <option value="pending">Pending</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            {/* Tax ID */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Tax ID
                                </label>
                                <input
                                    type="text"
                                    value={formData.tax_id}
                                    onChange={(e) => handleInputChange('tax_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter tax ID"
                                />
                            </div>

                            {/* FHIR ID */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    FHIR ID
                                </label>
                                <input
                                    type="text"
                                    value={formData.fhir_id}
                                    onChange={(e) => handleInputChange('fhir_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter FHIR ID (optional)"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Contact Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900">Contact Information</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Email */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Email *
                                </label>
                                <div className="relative">
                                    <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                    <input
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => handleInputChange('email', e.target.value)}
                                        className={`w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                            errors.email ? 'border-red-300' : 'border-gray-300'
                                        }`}
                                        placeholder="Enter email address"
                                    />
                                </div>
                                {errors.email && (
                                    <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                                )}
                            </div>

                            {/* Phone */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Phone
                                </label>
                                <div className="relative">
                                    <Phone className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                    <input
                                        type="tel"
                                        value={formData.phone}
                                        onChange={(e) => handleInputChange('phone', e.target.value)}
                                        className={`w-full pl-10 pr-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                            errors.phone ? 'border-red-300' : 'border-gray-300'
                                        }`}
                                        placeholder="Enter phone number"
                                    />
                                </div>
                                {errors.phone && (
                                    <p className="mt-1 text-sm text-red-600">{errors.phone}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Address Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900">Address Information</h3>

                        <div className="space-y-4">
                            {/* Street Address */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Street Address
                                </label>
                                <div className="relative">
                                    <MapPin className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <textarea
                                        value={formData.address}
                                        onChange={(e) => handleInputChange('address', e.target.value)}
                                        rows={2}
                                        className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Enter street address"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* City */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        City
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.city}
                                        onChange={(e) => handleInputChange('city', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Enter city"
                                    />
                                </div>

                                {/* Region/State */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        State/Region
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.region}
                                        onChange={(e) => handleInputChange('region', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Enter state/region"
                                    />
                                </div>

                                {/* Postal Code */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Postal Code
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.postal_code}
                                        onChange={(e) => handleInputChange('postal_code', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        placeholder="Enter postal code"
                                    />
                                </div>
                            </div>

                            {/* Country */}
                            <div className="md:w-1/3">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Country
                                </label>
                                <select
                                    value={formData.country}
                                    onChange={(e) => handleInputChange('country', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="MX">Mexico</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className="flex items-center space-x-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="h-4 w-4" />
                            )}
                            <span>{isLoading ? 'Saving...' : (mode === 'edit' ? 'Update' : 'Create')}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default OrganizationModal;
