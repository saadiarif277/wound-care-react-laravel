import React, { useState, useEffect } from 'react';
import { X, Building, Mail, Phone, MapPin, Save, Loader2, Clock } from 'lucide-react';

interface AddFacilityModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: (facility: any) => void;
    organizationId?: string;
    organizations?: Array<{ id: string; name: string }>;
}

interface FormData {
    organization_id: string;
    name: string;
    facility_type: string;
    group_npi: string;
    status: string;
    address: string;
    city: string;
    state: string;
    zip_code: string;
    phone: string;
    email: string;
    business_hours: string;
    active: boolean;
}

const AddFacilityModal: React.FC<AddFacilityModalProps> = ({
    isOpen,
    onClose,
    onSave,
    organizationId,
    organizations = []
}) => {
    const [formData, setFormData] = useState<FormData>({
        organization_id: organizationId || '',
        name: '',
        facility_type: '',
        group_npi: '',
        status: 'active',
        address: '',
        city: '',
        state: '',
        zip_code: '',
        phone: '',
        email: '',
        business_hours: '',
        active: true,
    });

    const [errors, setErrors] = useState<Partial<FormData>>({});
    const [isLoading, setIsLoading] = useState(false);

    // Facility types
    const facilityTypes = [
        { value: '', label: 'Select Type' },
        { value: 'Hospital', label: 'Hospital' },
        { value: 'Clinic', label: 'Clinic' },
        { value: 'Private Practice', label: 'Private Practice' },
        { value: 'Surgery Center', label: 'Surgery Center' },
        { value: 'Wound Care Center', label: 'Wound Care Center' },
        { value: 'Emergency Department', label: 'Emergency Department' },
        { value: 'Urgent Care', label: 'Urgent Care' },
        { value: 'Specialty Clinic', label: 'Specialty Clinic' },
        { value: 'Other', label: 'Other' },
    ];

    // US States
    const states = [
        { value: '', label: 'Select State' },
        { value: 'AL', label: 'Alabama' },
        { value: 'AK', label: 'Alaska' },
        { value: 'AZ', label: 'Arizona' },
        { value: 'AR', label: 'Arkansas' },
        { value: 'CA', label: 'California' },
        { value: 'CO', label: 'Colorado' },
        { value: 'CT', label: 'Connecticut' },
        { value: 'DE', label: 'Delaware' },
        { value: 'FL', label: 'Florida' },
        { value: 'GA', label: 'Georgia' },
        { value: 'HI', label: 'Hawaii' },
        { value: 'ID', label: 'Idaho' },
        { value: 'IL', label: 'Illinois' },
        { value: 'IN', label: 'Indiana' },
        { value: 'IA', label: 'Iowa' },
        { value: 'KS', label: 'Kansas' },
        { value: 'KY', label: 'Kentucky' },
        { value: 'LA', label: 'Louisiana' },
        { value: 'ME', label: 'Maine' },
        { value: 'MD', label: 'Maryland' },
        { value: 'MA', label: 'Massachusetts' },
        { value: 'MI', label: 'Michigan' },
        { value: 'MN', label: 'Minnesota' },
        { value: 'MS', label: 'Mississippi' },
        { value: 'MO', label: 'Missouri' },
        { value: 'MT', label: 'Montana' },
        { value: 'NE', label: 'Nebraska' },
        { value: 'NV', label: 'Nevada' },
        { value: 'NH', label: 'New Hampshire' },
        { value: 'NJ', label: 'New Jersey' },
        { value: 'NM', label: 'New Mexico' },
        { value: 'NY', label: 'New York' },
        { value: 'NC', label: 'North Carolina' },
        { value: 'ND', label: 'North Dakota' },
        { value: 'OH', label: 'Ohio' },
        { value: 'OK', label: 'Oklahoma' },
        { value: 'OR', label: 'Oregon' },
        { value: 'PA', label: 'Pennsylvania' },
        { value: 'RI', label: 'Rhode Island' },
        { value: 'SC', label: 'South Carolina' },
        { value: 'SD', label: 'South Dakota' },
        { value: 'TN', label: 'Tennessee' },
        { value: 'TX', label: 'Texas' },
        { value: 'UT', label: 'Utah' },
        { value: 'VT', label: 'Vermont' },
        { value: 'VA', label: 'Virginia' },
        { value: 'WA', label: 'Washington' },
        { value: 'WV', label: 'West Virginia' },
        { value: 'WI', label: 'Wisconsin' },
        { value: 'WY', label: 'Wyoming' },
    ];

    // Reset form when modal opens
    useEffect(() => {
        if (isOpen) {
            setFormData({
                organization_id: organizationId || '',
                name: '',
                facility_type: '',
                group_npi: '',
                status: 'active',
                address: '',
                city: '',
                state: '',
                zip_code: '',
                phone: '',
                email: '',
                business_hours: '',
                active: true,
            });
            setErrors({});
        }
    }, [isOpen, organizationId]);

    const handleInputChange = (field: keyof FormData, value: string | boolean) => {
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
            newErrors.name = 'Facility name is required';
        }

        if (!formData.organization_id) {
            newErrors.organization_id = 'Organization is required';
        }

        if (!formData.facility_type) {
            newErrors.facility_type = 'Facility type is required';
        }

        if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            newErrors.email = 'Please enter a valid email address';
        }

        if (formData.phone && !/^[\d\s\-\+\(\)\.]+$/.test(formData.phone)) {
            newErrors.phone = 'Please enter a valid phone number';
        }

        if (formData.group_npi && !/^\d{10}$/.test(formData.group_npi.replace(/\D/g, ''))) {
            newErrors.group_npi = 'NPI must be 10 digits';
        }

        if (formData.zip_code && !/^\d{5}(-\d{4})?$/.test(formData.zip_code)) {
            newErrors.zip_code = 'Please enter a valid ZIP code (12345 or 12345-6789)';
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
            // Here you would typically make an API call to create the facility
            // For now, we'll simulate success and pass the data to the parent
            await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate API call

            onSave({
                ...formData,
                id: Date.now().toString(), // Temporary ID for demo
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            });
            onClose();
        } catch (error: any) {
            setErrors({ name: error.message || 'An error occurred while saving' });
        } finally {
            setIsLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b border-gray-200">
                    <div className="flex items-center space-x-3">
                        <Building className="h-6 w-6 text-blue-600" />
                        <h2 className="text-xl font-semibold text-gray-900">Add New Facility</h2>
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
                            {/* Organization */}
                            {!organizationId && (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Organization *
                                    </label>
                                    <select
                                        value={formData.organization_id}
                                        onChange={(e) => handleInputChange('organization_id', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                            errors.organization_id ? 'border-red-300' : 'border-gray-300'
                                        }`}
                                    >
                                        <option value="">Select Organization</option>
                                        {organizations.map(org => (
                                            <option key={org.id} value={org.id}>
                                                {org.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.organization_id && (
                                        <p className="mt-1 text-sm text-red-600">{errors.organization_id}</p>
                                    )}
                                </div>
                            )}

                            {/* Facility Name */}
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Facility Name *
                                </label>
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                        errors.name ? 'border-red-300' : 'border-gray-300'
                                    }`}
                                    placeholder="Enter facility name"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            {/* Facility Type */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Facility Type *
                                </label>
                                <select
                                    value={formData.facility_type}
                                    onChange={(e) => handleInputChange('facility_type', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                        errors.facility_type ? 'border-red-300' : 'border-gray-300'
                                    }`}
                                >
                                    {facilityTypes.map(type => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.facility_type && (
                                    <p className="mt-1 text-sm text-red-600">{errors.facility_type}</p>
                                )}
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

                            {/* Group NPI */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Group NPI
                                </label>
                                <input
                                    type="text"
                                    value={formData.group_npi}
                                    onChange={(e) => handleInputChange('group_npi', e.target.value)}
                                    className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                        errors.group_npi ? 'border-red-300' : 'border-gray-300'
                                    }`}
                                    placeholder="Enter 10-digit NPI"
                                    maxLength={10}
                                />
                                {errors.group_npi && (
                                    <p className="mt-1 text-sm text-red-600">{errors.group_npi}</p>
                                )}
                            </div>

                            {/* Active Toggle */}
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="active"
                                    checked={formData.active}
                                    onChange={(e) => handleInputChange('active', e.target.checked)}
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <label htmlFor="active" className="ml-2 block text-sm text-gray-900">
                                    Active Facility
                                </label>
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
                                    Email
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

                                {/* State */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        State
                                    </label>
                                    <select
                                        value={formData.state}
                                        onChange={(e) => handleInputChange('state', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        {states.map(state => (
                                            <option key={state.value} value={state.value}>
                                                {state.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* ZIP Code */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        ZIP Code
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.zip_code}
                                        onChange={(e) => handleInputChange('zip_code', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                                            errors.zip_code ? 'border-red-300' : 'border-gray-300'
                                        }`}
                                        placeholder="12345 or 12345-6789"
                                    />
                                    {errors.zip_code && (
                                        <p className="mt-1 text-sm text-red-600">{errors.zip_code}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Business Hours */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium text-gray-900">Operations</h3>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Business Hours
                            </label>
                            <div className="relative">
                                <Clock className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                <textarea
                                    value={formData.business_hours}
                                    onChange={(e) => handleInputChange('business_hours', e.target.value)}
                                    rows={3}
                                    className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="e.g., Mon-Fri: 8:00 AM - 5:00 PM, Sat: 9:00 AM - 2:00 PM"
                                />
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
                            <span>{isLoading ? 'Creating...' : 'Create Facility'}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default AddFacilityModal;
