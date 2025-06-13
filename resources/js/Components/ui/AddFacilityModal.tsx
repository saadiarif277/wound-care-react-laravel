import React, { useState, useEffect } from 'react';
import { X, Building, Mail, Phone, MapPin, Save, Loader2, Clock } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

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
    // Theme setup with fallback
    let theme: 'dark' | 'light' = 'dark';
    let t = themes.dark;

    try {
        const themeContext = useTheme();
        theme = themeContext.theme;
        t = themes[theme];
    } catch (e) {
        // If not in ThemeProvider, use dark theme
    }

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
        <div className={cn("fixed inset-0 z-50 flex items-center justify-center p-4", t.modal.backdrop)}>
            <div className={cn("max-w-3xl w-full max-h-[90vh] overflow-y-auto", t.modal.container)}>
                {/* Header */}
                <div className={cn("flex items-center justify-between", t.modal.header)}>
                    <div className="flex items-center space-x-3">
                        <Building className={cn("h-6 w-6", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')} />
                        <h2 className={cn("text-xl font-semibold", t.text.primary)}>Add New Facility</h2>
                    </div>
                    <button
                        onClick={onClose}
                        className={cn(
                            "transition-colors rounded-lg p-1",
                            theme === 'dark'
                                ? 'text-white/60 hover:text-white/90 hover:bg-white/10'
                                : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
                        )}
                    >
                        <X className="h-6 w-6" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className={cn("space-y-6", t.modal.body)}>
                    {/* Basic Information */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Basic Information</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Organization */}
                            {!organizationId && (
                                <div className="md:col-span-2">
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        Organization *
                                    </label>
                                    <select
                                        value={formData.organization_id}
                                        onChange={(e) => handleInputChange('organization_id', e.target.value)}
                                        className={cn(
                                            t.input.base,
                                            t.input.focus,
                                            errors.organization_id ? t.input.error : ''
                                        )}
                                    >
                                        <option value="">Select Organization</option>
                                        {organizations.map(org => (
                                            <option key={org.id} value={org.id}>
                                                {org.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.organization_id && (
                                        <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.organization_id}</p>
                                    )}
                                </div>
                            )}

                            {/* Facility Name */}
                            <div className="md:col-span-2">
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Facility Name *
                                </label>
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.name ? t.input.error : ''
                                    )}
                                    placeholder="Enter facility name"
                                />
                                {errors.name && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.name}</p>
                                )}
                            </div>

                            {/* Facility Type */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Facility Type *
                                </label>
                                <select
                                    value={formData.facility_type}
                                    onChange={(e) => handleInputChange('facility_type', e.target.value)}
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.facility_type ? t.input.error : ''
                                    )}
                                >
                                    {facilityTypes.map(type => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.facility_type && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.facility_type}</p>
                                )}
                            </div>

                            {/* Status */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Status
                                </label>
                                <select
                                    value={formData.status}
                                    onChange={(e) => handleInputChange('status', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                >
                                    <option value="active">Active</option>
                                    <option value="pending">Pending</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            {/* Group NPI */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Group NPI
                                </label>
                                <input
                                    type="text"
                                    value={formData.group_npi}
                                    onChange={(e) => handleInputChange('group_npi', e.target.value)}
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.group_npi ? t.input.error : ''
                                    )}
                                    placeholder="Enter 10-digit NPI"
                                    maxLength={10}
                                />
                                {errors.group_npi && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.group_npi}</p>
                                )}
                            </div>

                            {/* Active Toggle */}
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="active"
                                    checked={formData.active}
                                    onChange={(e) => handleInputChange('active', e.target.checked)}
                                    className={cn("h-4 w-4 rounded",
                                        theme === 'dark'
                                            ? 'text-blue-400 focus:ring-blue-400/30 border-white/20'
                                            : 'text-blue-600 focus:ring-blue-500 border-gray-300'
                                    )}
                                />
                                <label htmlFor="active" className={cn("ml-2 block text-sm", t.text.primary)}>
                                    Active Facility
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Contact Information */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Contact Information</h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Email */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Email
                                </label>
                                <div className="relative">
                                    <Mail className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4", t.text.muted)} />
                                    <input
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => handleInputChange('email', e.target.value)}
                                        className={cn(
                                            "pl-10 pr-3",
                                            t.input.base,
                                            t.input.focus,
                                            errors.email ? t.input.error : ''
                                        )}
                                        placeholder="Enter email address"
                                    />
                                </div>
                                {errors.email && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.email}</p>
                                )}
                            </div>

                            {/* Phone */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Phone
                                </label>
                                <div className="relative">
                                    <Phone className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4", t.text.muted)} />
                                    <input
                                        type="tel"
                                        value={formData.phone}
                                        onChange={(e) => handleInputChange('phone', e.target.value)}
                                        className={cn(
                                            "pl-10 pr-3",
                                            t.input.base,
                                            t.input.focus,
                                            errors.phone ? t.input.error : ''
                                        )}
                                        placeholder="Enter phone number"
                                    />
                                </div>
                                {errors.phone && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.phone}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Address Information */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Address Information</h3>

                        <div className="space-y-4">
                            {/* Street Address */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Street Address
                                </label>
                                <div className="relative">
                                    <MapPin className={cn("absolute left-3 top-3 h-4 w-4", t.text.muted)} />
                                    <textarea
                                        value={formData.address}
                                        onChange={(e) => handleInputChange('address', e.target.value)}
                                        rows={2}
                                        className={cn("pl-10 pr-3", t.input.base, t.input.focus)}
                                        placeholder="Enter street address"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {/* City */}
                                <div>
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        City
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.city}
                                        onChange={(e) => handleInputChange('city', e.target.value)}
                                        className={cn(t.input.base, t.input.focus)}
                                        placeholder="Enter city"
                                    />
                                </div>

                                {/* State */}
                                <div>
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        State
                                    </label>
                                    <select
                                        value={formData.state}
                                        onChange={(e) => handleInputChange('state', e.target.value)}
                                        className={cn(t.input.base, t.input.focus)}
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
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        ZIP Code
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.zip_code}
                                        onChange={(e) => handleInputChange('zip_code', e.target.value)}
                                        className={cn(
                                            t.input.base,
                                            t.input.focus,
                                            errors.zip_code ? t.input.error : ''
                                        )}
                                        placeholder="12345 or 12345-6789"
                                    />
                                    {errors.zip_code && (
                                        <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.zip_code}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Business Hours */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Operations</h3>

                        <div>
                            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                Business Hours
                            </label>
                            <div className="relative">
                                <Clock className={cn("absolute left-3 top-3 h-4 w-4", t.text.muted)} />
                                <textarea
                                    value={formData.business_hours}
                                    onChange={(e) => handleInputChange('business_hours', e.target.value)}
                                    rows={3}
                                    className={cn("pl-10 pr-3", t.input.base, t.input.focus)}
                                    placeholder="e.g., Mon-Fri: 8:00 AM - 5:00 PM, Sat: 9:00 AM - 2:00 PM"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className={cn("flex items-center justify-end space-x-3 pt-6", t.modal.footer)}>
                        <button
                            type="button"
                            onClick={onClose}
                            className={cn(t.button.secondary.base, t.button.secondary.hover)}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className={cn(
                                "flex items-center space-x-2",
                                t.button.primary.base,
                                t.button.primary.hover,
                                isLoading && "opacity-50 cursor-not-allowed"
                            )}
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
