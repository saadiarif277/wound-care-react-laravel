import React, { useState, useEffect } from 'react';
import { X, UserPlus, Mail, Phone, User, Save, Loader2, Building } from 'lucide-react';
import { Modal } from '@/Components/Modal';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import Button from '@/Components/Button';

interface AddProviderModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: (provider: any) => void;
    organizationId?: string;
    facilities?: Array<{ id: string; name: string }>;
    organizations?: Array<{ id: string; name: string }>;
}

interface FormData {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    title: string;
    npi: string;
    license_number: string;
    license_state: string;
    specialties: string;
    organization_id: string;
    facility_assignments: string[];
    is_primary_facility: { [key: string]: boolean };
    send_invitation: boolean;
    invitation_message: string;
}

interface FormErrors {
    first_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
    title?: string;
    npi?: string;
    license_number?: string;
    license_state?: string;
    specialties?: string;
    organization_id?: string;
    facility_assignments?: string;
    is_primary_facility?: string;
    send_invitation?: string;
    invitation_message?: string;
}

const AddProviderModal: React.FC<AddProviderModalProps> = ({
    isOpen,
    onClose,
    onSave,
    organizationId,
    facilities = [],
    organizations = []
}) => {
    // Theme setup
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
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        title: '',
        npi: '',
        license_number: '',
        license_state: '',
        specialties: '',
        organization_id: organizationId || '',
        facility_assignments: [],
        is_primary_facility: {},
        send_invitation: true,
        invitation_message: '',
    });

    const [errors, setErrors] = useState<FormErrors>({});
    const [isLoading, setIsLoading] = useState(false);

    // Provider titles/specialties
    const providerTitles = [
        { value: '', label: 'Select Title' },
        { value: 'MD', label: 'Medical Doctor (MD)' },
        { value: 'DO', label: 'Doctor of Osteopathic Medicine (DO)' },
        { value: 'NP', label: 'Nurse Practitioner (NP)' },
        { value: 'PA', label: 'Physician Assistant (PA)' },
        { value: 'RN', label: 'Registered Nurse (RN)' },
        { value: 'LPN', label: 'Licensed Practical Nurse (LPN)' },
        { value: 'DPM', label: 'Doctor of Podiatric Medicine (DPM)' },
        { value: 'Other', label: 'Other' },
    ];

    // US States for license
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
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                title: '',
                npi: '',
                license_number: '',
                license_state: '',
                specialties: '',
                organization_id: organizationId || '',
                facility_assignments: [],
                is_primary_facility: {},
                send_invitation: true,
                invitation_message: '',
            });
            setErrors({});
        }
    }, [isOpen, organizationId]);

    const handleInputChange = (field: keyof FormData, value: string | boolean | string[]) => {
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

    const handleFacilityAssignment = (facilityId: string, isAssigned: boolean) => {
        if (isAssigned) {
            setFormData(prev => ({
                ...prev,
                facility_assignments: [...prev.facility_assignments, facilityId]
            }));
        } else {
            setFormData(prev => ({
                ...prev,
                facility_assignments: prev.facility_assignments.filter(id => id !== facilityId),
                is_primary_facility: {
                    ...prev.is_primary_facility,
                    [facilityId]: false
                }
            }));
        }
    };

    const handlePrimaryFacility = (facilityId: string, isPrimary: boolean) => {
        setFormData(prev => ({
            ...prev,
            is_primary_facility: {
                ...prev.is_primary_facility,
                [facilityId]: isPrimary
            }
        }));
    };

    const validateForm = (): boolean => {
        const newErrors: FormErrors = {};

        if (!formData.first_name.trim()) {
            newErrors.first_name = 'First name is required';
        }

        if (!formData.last_name.trim()) {
            newErrors.last_name = 'Last name is required';
        }

        if (!formData.email.trim()) {
            newErrors.email = 'Email is required';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            newErrors.email = 'Please enter a valid email address';
        }

        if (!formData.organization_id) {
            newErrors.organization_id = 'Organization is required';
        }

        if (formData.phone && !/^[\d\s\-\+\(\)\.]+$/.test(formData.phone)) {
            newErrors.phone = 'Please enter a valid phone number';
        }

        if (formData.npi && !/^\d{10}$/.test(formData.npi.replace(/\D/g, ''))) {
            newErrors.npi = 'NPI must be 10 digits';
        }

        if (formData.facility_assignments.length === 0) {
            newErrors.facility_assignments = 'At least one facility assignment is required';
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
            // Here you would typically make an API call to create/invite the provider
            // For now, we'll simulate success and pass the data to the parent
            await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate API call

            onSave({
                ...formData,
                id: Date.now().toString(), // Temporary ID for demo
                full_name: `${formData.first_name} ${formData.last_name}`,
                role: 'provider',
                status: formData.send_invitation ? 'invitation_sent' : 'active',
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            });
            onClose();
        } catch (error: any) {
            setErrors({ email: error.message || 'An error occurred while saving' });
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="2xl">
            <div className={cn(theme === 'dark' ? t.modal.container : '')}>
                {/* Header */}
                <div className={cn(t.modal.header, "flex items-center justify-between")}>
                    <div className="flex items-center space-x-3">
                        <UserPlus className={cn("h-6 w-6", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')} />
                        <h2 className={cn("text-xl font-semibold", t.text.primary)}>Add New Provider</h2>
                    </div>
                    <button
                        onClick={onClose}
                        className={cn(
                            "p-2 rounded-lg transition-colors",
                            theme === 'dark' ? 'text-white/60 hover:text-white hover:bg-white/10' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
                        )}
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className={cn(t.modal.body, "space-y-6")}>
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

                            {/* First Name */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    First Name *
                                </label>
                                <div className="relative">
                                    <User className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4", t.text.muted)} />
                                    <input
                                        type="text"
                                        value={formData.first_name}
                                        onChange={(e) => handleInputChange('first_name', e.target.value)}
                                        className={cn(
                                            "pl-10",
                                            t.input.base,
                                            t.input.focus,
                                            errors.first_name ? t.input.error : ''
                                        )}
                                        placeholder="Enter first name"
                                    />
                                </div>
                                {errors.first_name && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.first_name}</p>
                                )}
                            </div>

                            {/* Last Name */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Last Name *
                                </label>
                                <input
                                    type="text"
                                    value={formData.last_name}
                                    onChange={(e) => handleInputChange('last_name', e.target.value)}
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.last_name ? t.input.error : ''
                                    )}
                                    placeholder="Enter last name"
                                />
                                {errors.last_name && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.last_name}</p>
                                )}
                            </div>

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

                            {/* Title */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Title/Credential
                                </label>
                                <select
                                    value={formData.title}
                                    onChange={(e) => handleInputChange('title', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                >
                                    {providerTitles.map(title => (
                                        <option key={title.value} value={title.value}>
                                            {title.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Specialties */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Specialties
                                </label>
                                <input
                                    type="text"
                                    value={formData.specialties}
                                    onChange={(e) => handleInputChange('specialties', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                    placeholder="e.g., Wound Care, Internal Medicine"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Professional Information */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Professional Information</h3>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {/* NPI */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    NPI
                                </label>
                                <input
                                    type="text"
                                    value={formData.npi}
                                    onChange={(e) => handleInputChange('npi', e.target.value)}
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.npi ? t.input.error : ''
                                    )}
                                    placeholder="Enter 10-digit NPI"
                                    maxLength={10}
                                />
                                {errors.npi && (
                                    <p className={cn("mt-1 text-sm", t.status.error.split(' ')[0])}>{errors.npi}</p>
                                )}
                            </div>

                            {/* License Number */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    License Number
                                </label>
                                <input
                                    type="text"
                                    value={formData.license_number}
                                    onChange={(e) => handleInputChange('license_number', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                    placeholder="Enter license number"
                                />
                            </div>

                            {/* License State */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    License State
                                </label>
                                <select
                                    value={formData.license_state}
                                    onChange={(e) => handleInputChange('license_state', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                >
                                    {states.map(state => (
                                        <option key={state.value} value={state.value}>
                                            {state.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Facility Assignments */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Facility Assignments</h3>

                        {facilities.length > 0 ? (
                            <div className="space-y-3">
                                <p className={cn("text-sm", t.text.secondary)}>Select the facilities this provider will be assigned to:</p>
                                {facilities.map(facility => (
                                    <div key={facility.id} className={cn("flex items-center justify-between p-3 rounded-lg", t.glass.base)}>
                                        <div className="flex items-center space-x-3">
                                            <input
                                                type="checkbox"
                                                id={`facility-${facility.id}`}
                                                checked={formData.facility_assignments.includes(facility.id)}
                                                onChange={(e) => handleFacilityAssignment(facility.id, e.target.checked)}
                                                className={cn("h-4 w-4 rounded",
                                                    theme === 'dark'
                                                        ? 'text-blue-400 focus:ring-blue-400/30 border-white/20'
                                                        : 'text-blue-600 focus:ring-blue-500 border-gray-300'
                                                )}
                                            />
                                            <label htmlFor={`facility-${facility.id}`} className="flex items-center space-x-2">
                                                <Building className={cn("h-4 w-4", t.text.muted)} />
                                                <span className={cn("text-sm font-medium", t.text.primary)}>{facility.name}</span>
                                            </label>
                                        </div>
                                        {formData.facility_assignments.includes(facility.id) && (
                                            <div className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    id={`primary-${facility.id}`}
                                                    checked={formData.is_primary_facility[facility.id] || false}
                                                    onChange={(e) => handlePrimaryFacility(facility.id, e.target.checked)}
                                                    className={cn("h-3 w-3 rounded",
                                                        theme === 'dark'
                                                            ? 'text-blue-400 focus:ring-blue-400/30 border-white/20'
                                                            : 'text-blue-600 focus:ring-blue-500 border-gray-300'
                                                    )}
                                                />
                                                <label htmlFor={`primary-${facility.id}`} className={cn("text-xs", t.text.secondary)}>
                                                    Primary
                                                </label>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {errors.facility_assignments && (
                                    <p className={cn("text-sm", t.status.error.split(' ')[0])}>{errors.facility_assignments}</p>
                                )}
                            </div>
                        ) : (
                            <div className={cn("text-center py-4", t.text.muted)}>
                                No facilities available. Please add facilities to this organization first.
                            </div>
                        )}
                    </div>

                    {/* Invitation Settings */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Invitation Settings</h3>

                        <div className="space-y-3">
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="send_invitation"
                                    checked={formData.send_invitation}
                                    onChange={(e) => handleInputChange('send_invitation', e.target.checked)}
                                    className={cn("h-4 w-4 rounded",
                                        theme === 'dark'
                                            ? 'text-blue-400 focus:ring-blue-400/30 border-white/20'
                                            : 'text-blue-600 focus:ring-blue-500 border-gray-300'
                                    )}
                                />
                                <label htmlFor="send_invitation" className={cn("ml-2 block text-sm", t.text.primary)}>
                                    Send invitation email to provider
                                </label>
                            </div>

                            {formData.send_invitation && (
                                <div>
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        Invitation Message (Optional)
                                    </label>
                                    <textarea
                                        value={formData.invitation_message}
                                        onChange={(e) => handleInputChange('invitation_message', e.target.value)}
                                        rows={3}
                                        className={cn(t.input.base, t.input.focus)}
                                        placeholder="Add a personal message to the invitation email..."
                                    />
                                </div>
                            )}
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
                        <span>{isLoading ? (formData.send_invitation ? 'Sending Invitation...' : 'Creating...') : (formData.send_invitation ? 'Send Invitation' : 'Create Provider')}</span>
                    </button>
                </div>
                </form>
            </div>
        </div>
    );
};

export default AddProviderModal;
