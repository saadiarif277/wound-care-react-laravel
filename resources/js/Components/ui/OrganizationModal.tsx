import React, { useState, useEffect } from 'react';
import { X, Building2, Mail, Phone, MapPin, User, Save, Loader2 } from 'lucide-react';
import { api } from '@/lib/api';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

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
        <div className={cn("fixed inset-0 z-50 flex items-center justify-center p-4", t.modal.backdrop)}>
            <div className={cn("max-w-2xl w-full max-h-[90vh] overflow-y-auto", t.modal.container)}>
                {/* Header */}
                <div className={cn("flex items-center justify-between", t.modal.header)}>
                    <div className="flex items-center space-x-3">
                        <Building2 className={cn("h-6 w-6", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')} />
                        <h2 className={cn("text-xl font-semibold", t.text.primary)}>
                            {mode === 'edit' ? 'Edit Organization' : 'Add New Organization'}
                        </h2>
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
                            {/* Organization Name */}
                            <div className="md:col-span-2">
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Organization Name *
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
                                    placeholder="Enter organization name"
                                />
                                {errors.name && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.name}</p>
                                )}
                            </div>

                            {/* Type */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Type
                                </label>
                                <select
                                    value={formData.type}
                                    onChange={(e) => handleInputChange('type', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
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

                            {/* Tax ID */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Tax ID
                                </label>
                                <input
                                    type="text"
                                    value={formData.tax_id}
                                    onChange={(e) => handleInputChange('tax_id', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                    placeholder="Enter tax ID"
                                />
                            </div>

                            {/* FHIR ID */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    FHIR ID
                                </label>
                                <input
                                    type="text"
                                    value={formData.fhir_id}
                                    onChange={(e) => handleInputChange('fhir_id', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                    placeholder="Enter FHIR ID (optional)"
                                />
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
                                    Email *
                                </label>
                                <div className="relative">
                                    <Mail className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4", t.text.muted)} />
                                    <input
                                        type="email"
                                        value={formData.email}
                                        onChange={(e) => handleInputChange('email', e.target.value)}
                                        className={cn(
                                            "pl-10",
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
                                            "pl-10",
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
                                        className={cn("pl-10", t.input.base, t.input.focus)}
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

                                {/* Region/State */}
                                <div>
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        State/Region
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.region}
                                        onChange={(e) => handleInputChange('region', e.target.value)}
                                        className={cn(t.input.base, t.input.focus)}
                                        placeholder="Enter state/region"
                                    />
                                </div>

                                {/* Postal Code */}
                                <div>
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        Postal Code
                                    </label>
                                    <input
                                        type="text"
                                        value={formData.postal_code}
                                        onChange={(e) => handleInputChange('postal_code', e.target.value)}
                                        className={cn(t.input.base, t.input.focus)}
                                        placeholder="Enter postal code"
                                    />
                                </div>
                            </div>

                            {/* Country */}
                            <div className="md:w-1/3">
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Country
                                </label>
                                <select
                                    value={formData.country}
                                    onChange={(e) => handleInputChange('country', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                >
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="MX">Mexico</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                {/* Action Buttons */}
                <div className={cn("flex items-center justify-end space-x-3 pt-6", t.modal.footer)}>
                    <button
                        type="button"
                        onClick={onClose}
                        className={cn("px-4 py-2 text-sm font-medium rounded-lg", t.button.secondary)}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        onClick={handleSubmit}
                        disabled={isLoading}
                        className={cn(
                            "flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-lg",
                            t.button.primary,
                            isLoading ? "opacity-50 cursor-not-allowed" : ""
                        )}
                    >
                        {isLoading ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Save className="h-4 w-4" />
                        )}
                        <span>{isLoading ? 'Saving...' : (mode === 'edit' ? 'Update' : 'Create')}</span>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default OrganizationModal;
