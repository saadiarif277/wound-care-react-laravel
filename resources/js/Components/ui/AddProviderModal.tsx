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
    organization_id?: string;
    facility_assignments?: string;
}

const AddProviderModal: React.FC<AddProviderModalProps> = ({
    isOpen,
    onClose,
    onSave,
    organizationId,
    facilities = [],
    organizations = []
}) => {
    let theme: 'dark' | 'light' = 'dark';
    let t = themes.dark;

    try {
        const themeContext = useTheme();
        theme = themeContext.theme;
        t = themes[theme];
    } catch (e) {
        // Fallback to dark theme
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
        { value: 'DPM', label: 'Doctor of Podiatric Medicine (DPM)' },
        { value: 'Other', label: 'Other' }
    ];

    const specialtyOptions = [
        'Wound Care',
        'General Surgery',
        'Podiatry',
        'Internal Medicine',
        'Family Medicine',
        'Vascular Surgery',
        'Plastic Surgery',
        'Dermatology',
        'Emergency Medicine',
        'Other'
    ];

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
        { value: 'WY', label: 'Wyoming' }
    ];

    // Load facilities when organization changes
    useEffect(() => {
        if (formData.organization_id && facilities.length > 0) {
            // Auto-select all facilities for the organization
            const orgFacilities = facilities.filter(f => 
                f.organization_id === formData.organization_id
            );
            setFormData(prev => ({
                ...prev,
                facility_assignments: orgFacilities.map(f => f.id),
                is_primary_facility: orgFacilities.reduce((acc, f, index) => ({
                    ...acc,
                    [f.id]: index === 0 // First facility is primary by default
                }), {})
            }));
        }
    }, [formData.organization_id, facilities]);

    const handleInputChange = (field: keyof FormData, value: any) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
        // Clear error for this field
        if (errors[field as keyof FormErrors]) {
            setErrors(prev => ({
                ...prev,
                [field]: undefined
            }));
        }
    };

    const handleFacilityToggle = (facilityId: string) => {
        setFormData(prev => {
            const isSelected = prev.facility_assignments.includes(facilityId);
            const newAssignments = isSelected
                ? prev.facility_assignments.filter(id => id !== facilityId)
                : [...prev.facility_assignments, facilityId];
            
            // If removing, also remove from primary facilities
            const newPrimary = { ...prev.is_primary_facility };
            if (isSelected) {
                delete newPrimary[facilityId];
            }
            
            return {
                ...prev,
                facility_assignments: newAssignments,
                is_primary_facility: newPrimary
            };
        });
    };

    const handlePrimaryToggle = (facilityId: string) => {
        setFormData(prev => ({
            ...prev,
            is_primary_facility: {
                ...prev.is_primary_facility,
                [facilityId]: !prev.is_primary_facility[facilityId]
            }
        }));
    };

    const validateForm = () => {
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
            // Format the data for submission
            const providerData = {
                ...formData,
                facility_assignments: formData.facility_assignments.map(facilityId => ({
                    facility_id: facilityId,
                    is_primary: formData.is_primary_facility[facilityId] || false
                }))
            };

            await onSave(providerData);
            onClose();
        } catch (error) {
            console.error('Error saving provider:', error);
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
                                <TextInput
                                    value={formData.first_name}
                                    onChange={(e) => handleInputChange('first_name', e.target.value)}
                                    placeholder="John"
                                    error={errors.first_name}
                                    icon={<User className="h-4 w-4" />}
                                />
                            </div>

                            {/* Last Name */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Last Name *
                                </label>
                                <TextInput
                                    value={formData.last_name}
                                    onChange={(e) => handleInputChange('last_name', e.target.value)}
                                    placeholder="Doe"
                                    error={errors.last_name}
                                    icon={<User className="h-4 w-4" />}
                                />
                            </div>

                            {/* Email */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Email *
                                </label>
                                <TextInput
                                    type="email"
                                    value={formData.email}
                                    onChange={(e) => handleInputChange('email', e.target.value)}
                                    placeholder="john.doe@example.com"
                                    error={errors.email}
                                    icon={<Mail className="h-4 w-4" />}
                                />
                            </div>

                            {/* Phone */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Phone
                                </label>
                                <TextInput
                                    type="tel"
                                    value={formData.phone}
                                    onChange={(e) => handleInputChange('phone', e.target.value)}
                                    placeholder="(555) 123-4567"
                                    error={errors.phone}
                                    icon={<Phone className="h-4 w-4" />}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Professional Information */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Professional Information</h3>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Title */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Title
                                </label>
                                <select
                                    value={formData.title}
                                    onChange={(e) => handleInputChange('title', e.target.value)}
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.title ? t.input.error : ''
                                    )}
                                >
                                    {providerTitles.map(title => (
                                        <option key={title.value} value={title.value}>
                                            {title.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* NPI */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    NPI Number
                                </label>
                                <TextInput
                                    value={formData.npi}
                                    onChange={(e) => handleInputChange('npi', e.target.value)}
                                    placeholder="1234567890"
                                    error={errors.npi}
                                />
                            </div>

                            {/* License Number */}
                            <div>
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    License Number
                                </label>
                                <TextInput
                                    value={formData.license_number}
                                    onChange={(e) => handleInputChange('license_number', e.target.value)}
                                    placeholder="12345"
                                    error={errors.license_number}
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
                                    className={cn(
                                        t.input.base,
                                        t.input.focus,
                                        errors.license_state ? t.input.error : ''
                                    )}
                                >
                                    {states.map(state => (
                                        <option key={state.value} value={state.value}>
                                            {state.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Specialties */}
                            <div className="md:col-span-2">
                                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                    Specialties
                                </label>
                                <select
                                    value={formData.specialties}
                                    onChange={(e) => handleInputChange('specialties', e.target.value)}
                                    className={cn(t.input.base, t.input.focus)}
                                >
                                    <option value="">Select Specialty</option>
                                    {specialtyOptions.map(specialty => (
                                        <option key={specialty} value={specialty}>
                                            {specialty}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Facility Assignments */}
                    {formData.organization_id && facilities.length > 0 && (
                        <div className="space-y-4">
                            <h3 className={cn("text-lg font-medium", t.text.primary)}>Facility Assignments *</h3>
                            
                            <div className="space-y-2">
                                {facilities
                                    .filter(f => f.organization_id === formData.organization_id)
                                    .map(facility => (
                                        <div key={facility.id} className={cn(
                                            "flex items-center justify-between p-3 rounded-lg",
                                            theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
                                        )}>
                                            <div className="flex items-center space-x-3">
                                                <input
                                                    type="checkbox"
                                                    id={`facility-${facility.id}`}
                                                    checked={formData.facility_assignments.includes(facility.id)}
                                                    onChange={() => handleFacilityToggle(facility.id)}
                                                    className={cn(
                                                        "h-4 w-4 rounded",
                                                        theme === 'dark' ? 'bg-gray-700 border-gray-600' : ''
                                                    )}
                                                />
                                                <label
                                                    htmlFor={`facility-${facility.id}`}
                                                    className={cn("cursor-pointer", t.text.primary)}
                                                >
                                                    <div className="flex items-center space-x-2">
                                                        <Building className="h-4 w-4" />
                                                        <span>{facility.name}</span>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            {formData.facility_assignments.includes(facility.id) && (
                                                <div className="flex items-center space-x-2">
                                                    <input
                                                        type="checkbox"
                                                        id={`primary-${facility.id}`}
                                                        checked={formData.is_primary_facility[facility.id] || false}
                                                        onChange={() => handlePrimaryToggle(facility.id)}
                                                        className={cn(
                                                            "h-4 w-4 rounded",
                                                            theme === 'dark' ? 'bg-gray-700 border-gray-600' : ''
                                                        )}
                                                    />
                                                    <label
                                                        htmlFor={`primary-${facility.id}`}
                                                        className={cn("text-sm cursor-pointer", t.text.secondary)}
                                                    >
                                                        Primary
                                                    </label>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                            </div>
                            
                            {errors.facility_assignments && (
                                <p className={cn("text-sm", t.status.error.split(' ')[0])}>{errors.facility_assignments}</p>
                            )}
                        </div>
                    )}

                    {/* Invitation Options */}
                    <div className="space-y-4">
                        <h3 className={cn("text-lg font-medium", t.text.primary)}>Invitation Options</h3>
                        
                        <div className="space-y-4">
                            <label className={cn("flex items-center space-x-3 cursor-pointer", t.text.primary)}>
                                <input
                                    type="checkbox"
                                    checked={formData.send_invitation}
                                    onChange={(e) => handleInputChange('send_invitation', e.target.checked)}
                                    className={cn(
                                        "h-4 w-4 rounded",
                                        theme === 'dark' ? 'bg-gray-700 border-gray-600' : ''
                                    )}
                                />
                                <span>Send invitation email to provider</span>
                            </label>
                            
                            {formData.send_invitation && (
                                <div>
                                    <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                                        Custom Message (Optional)
                                    </label>
                                    <textarea
                                        value={formData.invitation_message}
                                        onChange={(e) => handleInputChange('invitation_message', e.target.value)}
                                        placeholder="Add a personalized message to the invitation email..."
                                        rows={3}
                                        className={cn(t.input.base, t.input.focus)}
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className={cn(
                        "flex items-center justify-end space-x-3 pt-4 border-t",
                        theme === 'dark' ? 'border-white/10' : 'border-gray-200'
                    )}>
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={isLoading}
                            className={cn(
                                "px-4 py-2 rounded-lg font-medium transition-colors",
                                theme === 'dark'
                                    ? 'text-white/60 hover:text-white hover:bg-white/10'
                                    : 'text-gray-600 hover:text-gray-800 hover:bg-gray-100'
                            )}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className={cn(
                                "px-4 py-2 rounded-lg font-medium flex items-center space-x-2",
                                t.button.primary,
                                isLoading && "opacity-50 cursor-not-allowed"
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
        </Modal>
    );
};

export default AddProviderModal;