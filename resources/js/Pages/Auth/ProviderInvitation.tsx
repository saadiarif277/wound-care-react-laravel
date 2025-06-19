import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { UserPlus, Building2, Mail, CheckCircle2, AlertCircle, Key, Shield, FileText, MapPin, CreditCard, Home } from 'lucide-react';
import { parseISO, isBefore, differenceInDays, format } from 'date-fns';

interface InvitationData {
    id: string;
    organization_name: string;
    organization_type: string;
    invited_email: string;
    invited_role: string;
    expires_at: string;
    status: string;
    metadata: {
        organization_id: string;
        invited_by: string;
        invited_by_name: string;
    };
}

interface FacilityData {
    id: number;
    name: string;
    full_address: string;
}

interface ComprehensiveOnboardingData {
    // Personal Information
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    phone: string;
    title: string;

    // Professional Credentials
    individual_npi: string;
    specialty: string;
    license_number: string;
    license_state: string;
    ptan: string;

    // Organization Information
    organization_name: string;
    organization_tax_id: string;
    organization_type: string;

    // Facility Information
    facility_name: string;
    facility_type: string;
    group_npi: string;
    facility_tax_id: string;
    facility_ptan: string;

    // Ship-To Address (Facility Address)
    facility_address: string;
    facility_city: string;
    facility_state: string;
    facility_zip: string;
    facility_phone: string;
    facility_email: string;

    // Bill-To Address (Organization Address - can be different)
    billing_address: string;
    billing_city: string;
    billing_state: string;
    billing_zip: string;

    // Accounts Payable Contact
    ap_contact_name: string;
    ap_contact_phone: string;
    ap_contact_email: string;

    // Business Operations
    business_hours: string;
    default_place_of_service: string;

    // Terms
    accept_terms: boolean;

    // Practice Type
    practice_type: 'solo_practitioner' | 'group_practice' | 'existing_organization';

    // For joining existing facility
    facility_id: number | null;
}

interface ProviderInvitationProps {
    invitation: InvitationData;
    token: string;
    facilities: FacilityData[];
    states: Array<{ code: string; name: string }>;
}

type OnboardingStep = 'review' | 'practice-type' | 'personal' | 'organization' | 'facility' | 'facility-selection' | 'credentials' | 'billing' | 'complete';

export default function ProviderInvitation({ invitation, token, facilities, states }: ProviderInvitationProps) {
    const [step, setStep] = useState<OnboardingStep>('review');
    const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

    const { data, setData, post, processing, errors } = useForm<ComprehensiveOnboardingData>({
        // Personal Information
        first_name: '',
        last_name: '',
        email: invitation.invited_email,
        password: '',
        password_confirmation: '',
        phone: '',
        title: '',

        // Professional Credentials
        individual_npi: '',
        specialty: '',
        license_number: '',
        license_state: '',
        ptan: '',

        // Organization Information
        organization_name: '',
        organization_tax_id: '',
        organization_type: 'healthcare_provider',

        // Facility Information
        facility_name: '',
        facility_type: '',
        group_npi: '',
        facility_tax_id: '',
        facility_ptan: '',

        // Ship-To Address
        facility_address: '',
        facility_city: '',
        facility_state: '',
        facility_zip: '',
        facility_phone: '',
        facility_email: '',

        // Bill-To Address
        billing_address: '',
        billing_city: '',
        billing_state: '',
        billing_zip: '',

        // AP Contact
        ap_contact_name: '',
        ap_contact_phone: '',
        ap_contact_email: '',

        // Business Operations
        business_hours: '',
        default_place_of_service: '11',

        // Terms
        accept_terms: false,

        // Practice Type
        practice_type: 'solo_practitioner',

        // Facility Selection
        facility_id: null,
    });

    useEffect(() => {
        if (data.practice_type === 'existing_organization') {
            setData('organization_name', invitation.organization_name);
            if (facilities.length === 1) {
                setData('facility_id', facilities[0].id);
            }
        }
    }, [data.practice_type]);

    const isExpired = isBefore(parseISO(invitation.expires_at), new Date());
    const daysUntilExpiry = Math.max(0, differenceInDays(parseISO(invitation.expires_at), new Date()));

    const facilityTypes = [
        { value: 'Private Practice', label: 'Private Practice' },
        { value: 'Clinic', label: 'Clinic' },
        { value: 'Hospital', label: 'Hospital' },
        { value: 'Surgery Center', label: 'Surgery Center' },
        { value: 'Wound Care Center', label: 'Wound Care Center' },
        { value: 'Emergency Department', label: 'Emergency Department' },
        { value: 'Urgent Care', label: 'Urgent Care' },
        { value: 'Specialty Clinic', label: 'Specialty Clinic' },
        { value: 'Other', label: 'Other' },
    ];

    const specialties = [
        { value: 'wound_care', label: 'Wound Care' },
        { value: 'family_medicine', label: 'Family Medicine' },
        { value: 'internal_medicine', label: 'Internal Medicine' },
        { value: 'emergency_medicine', label: 'Emergency Medicine' },
        { value: 'surgery', label: 'Surgery' },
        { value: 'dermatology', label: 'Dermatology' },
        { value: 'podiatry', label: 'Podiatry' },
        { value: 'nursing', label: 'Nursing' },
        { value: 'other', label: 'Other' },
    ];

    const placeOfServiceCodes = [
        { value: '11', label: '11 - Office' },
        { value: '12', label: '12 - Home' },
        { value: '31', label: '31 - Skilled Nursing Facility' },
        { value: '32', label: '32 - Nursing Facility' },
    ];

    const handleFieldChange = (field: keyof ComprehensiveOnboardingData, value: string | boolean) => {
        setData(field, value);
        // Clear validation error when user starts typing
        if (validationErrors[field]) {
            setValidationErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const validateCurrentStep = (): boolean => {
        const newErrors: Record<string, string> = {};
        let isValid = true;

        switch (step) {
            case 'personal':
        if (!data.first_name.trim()) {
                    newErrors.first_name = 'First name is required';
            isValid = false;
        }
        if (!data.last_name.trim()) {
                    newErrors.last_name = 'Last name is required';
            isValid = false;
        }
                if (!data.password || data.password.length < 8) {
                    newErrors.password = 'Password must be at least 8 characters';
            isValid = false;
                }
                if (data.password !== data.password_confirmation) {
                    newErrors.password_confirmation = 'Passwords do not match';
            isValid = false;
        }
                if (data.phone && !/^[\+]?[\d\s\-\(\)]+$/.test(data.phone)) {
                    newErrors.phone = 'Please enter a valid phone number';
                    isValid = false;
                }
                break;

            case 'organization':
                if (!data.organization_name.trim()) {
                    newErrors.organization_name = 'Organization name is required';
            isValid = false;
                }
                if (data.organization_tax_id && !/^\d{2}-\d{7}$/.test(data.organization_tax_id.replace(/\D/g, '').replace(/(\d{2})(\d{7})/, '$1-$2'))) {
                    newErrors.organization_tax_id = 'Tax ID should be in format XX-XXXXXXX';
            isValid = false;
        }
                break;

            case 'facility':
                if (!data.facility_name.trim()) {
                    newErrors.facility_name = 'Facility name is required';
            isValid = false;
        }
                if (!data.facility_type) {
                    newErrors.facility_type = 'Facility type is required';
                    isValid = false;
                }
                if (!data.facility_address.trim()) {
                    newErrors.facility_address = 'Facility address is required';
                    isValid = false;
                }
                if (!data.facility_city.trim()) {
                    newErrors.facility_city = 'City is required';
            isValid = false;
        }
                if (!data.facility_state) {
                    newErrors.facility_state = 'State is required';
                    isValid = false;
                }
                if (!data.facility_zip || !/^\d{5}(-\d{4})?$/.test(data.facility_zip)) {
                    newErrors.facility_zip = 'ZIP code must be 5 digits or ZIP+4 format';
                    isValid = false;
                }
                if (data.facility_phone && !/^[\+]?[\d\s\-\(\)]+$/.test(data.facility_phone)) {
                    newErrors.facility_phone = 'Please enter a valid phone number';
                    isValid = false;
                }
                if (data.facility_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.facility_email)) {
                    newErrors.facility_email = 'Please enter a valid email address';
                    isValid = false;
                }
                if (data.group_npi && !/^\d{10}$/.test(data.group_npi.replace(/\s/g, ''))) {
                    newErrors.group_npi = 'Group NPI must be exactly 10 digits';
                    isValid = false;
                }
                break;

            case 'credentials':
                if (data.individual_npi && !/^\d{10}$/.test(data.individual_npi.replace(/\s/g, ''))) {
                    newErrors.individual_npi = 'Individual NPI must be exactly 10 digits';
            isValid = false;
                }
                if (!data.accept_terms) {
                    newErrors.accept_terms = 'You must accept the terms to continue';
                    isValid = false;
                }
                break;

            case 'facility-selection':
                if (!data.facility_id) {
                    newErrors.facility_id = 'You must select a facility to continue';
                    isValid = false;
                }
                break;
        }

        setValidationErrors(newErrors);
        return isValid;
    };

    const handleNext = () => {
        if (validateCurrentStep()) {
            const currentFlow = stepFlows[data.practice_type];
            const currentIndex = currentFlow.indexOf(step);
            if (currentIndex < currentFlow.length - 1) {
                setStep(currentFlow[currentIndex + 1]);
            }
        }
    };

    const handleBack = () => {
        const currentFlow = stepFlows[data.practice_type];
        const currentIndex = currentFlow.indexOf(step);
        if (currentIndex > 0) {
            setStep(currentFlow[currentIndex - 1]);
        }
    };

    const handleCompleteRegistration = () => {
        if (validateCurrentStep()) {
            post(`/auth/provider-invitation/${token}/accept`, {
                onSuccess: () => {
                    setStep('complete');
                }
            });
        }
    };

    const copyFacilityToBilling = () => {
        setData(prev => ({
            ...prev,
            billing_address: data.facility_address,
            billing_city: data.facility_city,
            billing_state: data.facility_state,
            billing_zip: data.facility_zip,
        }));
    };

    // Auto-populate facility name based on organization and practice type
    useEffect(() => {
        if (data.practice_type === 'solo_practitioner' && data.organization_name && data.first_name && data.last_name) {
            const practitionerName = `${data.first_name} ${data.last_name}`;
            if (!data.facility_name) {
                setData('facility_name', `${practitionerName} Clinic`);
            }
        }
    }, [data.practice_type, data.organization_name, data.first_name, data.last_name]);

    const stepFlows: Record<typeof data.practice_type, OnboardingStep[]> = {
        solo_practitioner: ['review', 'practice-type', 'personal', 'organization', 'facility', 'credentials', 'billing', 'complete'],
        group_practice: ['review', 'practice-type', 'personal', 'organization', 'facility', 'credentials', 'billing', 'complete'],
        existing_organization: facilities.length > 1
            ? ['review', 'practice-type', 'personal', 'facility-selection', 'credentials', 'complete']
            : ['review', 'practice-type', 'personal', 'credentials', 'complete'],
    };

    const renderReviewStep = () => (
        <div className="space-y-6">
            <div className="text-center">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                    <UserPlus className="h-8 w-8 text-blue-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Welcome to MSC Wound Portal!</h1>
                <p className="text-gray-600">
                    You've been invited to join <strong>{invitation.organization_name}</strong> as a {invitation.invited_role}
                </p>
            </div>

            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <Building2 className="h-5 w-5" />
                    Invitation Details
                </h3>
                <dl className="space-y-3">
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Organization:</dt>
                        <dd className="text-sm text-gray-900">{invitation.organization_name}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Your Role:</dt>
                        <dd className="text-sm text-gray-900">
                            <Badge variant="default">{invitation.invited_role}</Badge>
                        </dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Invited by:</dt>
                        <dd className="text-sm text-gray-900">{invitation.metadata.invited_by_name}</dd>
                    </div>
                </dl>
            </div>

            <div className="bg-blue-50 p-4 rounded-lg">
                <h4 className="text-sm font-medium text-blue-800 mb-2">What to Expect</h4>
                <ul className="text-sm text-blue-700 space-y-1">
                    <li>• Complete comprehensive practice onboarding</li>
                    <li>• Set up your organization and facility information</li>
                    <li>• Provide professional credentials for verification</li>
                    <li>• Enable automatic manufacturer form completion</li>
                    <li>• Start accessing wound care products and services</li>
                </ul>
            </div>

            {isExpired ? (
                <div className="bg-red-50 p-4 rounded-lg">
                    <div className="flex items-center gap-2">
                        <AlertCircle className="h-5 w-5 text-red-600" />
                        <h4 className="text-sm font-medium text-red-800">Invitation Expired</h4>
                    </div>
                    <p className="text-sm text-red-700 mt-1">
                        This invitation expired on {format(parseISO(invitation.expires_at), 'PPP')}.
                        Please contact the organization to request a new invitation.
                    </p>
                </div>
            ) : (
                <>
                    {daysUntilExpiry <= 7 && (
                        <div className="bg-yellow-50 p-4 rounded-lg flex items-start gap-3">
                            <AlertCircle className="h-5 w-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h4 className="text-sm font-medium text-yellow-800">Invitation Expires Soon</h4>
                                <p className="text-sm text-yellow-700">
                                    This invitation expires in {daysUntilExpiry} day{daysUntilExpiry !== 1 ? 's' : ''}
                                    ({format(parseISO(invitation.expires_at), 'PPP')})
                                </p>
                            </div>
                        </div>
                    )}
                <div className="flex gap-3 justify-end">
                        <Button variant="secondary">Decline</Button>
                        <Button onClick={handleNext}>Get Started</Button>
                </div>
                </>
            )}
        </div>
    );

    const renderPracticeTypeStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <Building2 className="h-8 w-8 text-green-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Practice Setup</h1>
                <p className="text-gray-600">Tell us about your practice structure</p>
            </div>

            <div className="space-y-4">
                <label className="block">
                    <input
                        type="radio"
                        name="practice_type"
                        value="solo_practitioner"
                        checked={data.practice_type === 'solo_practitioner'}
                        onChange={(e) => handleFieldChange('practice_type', e.target.value as any)}
                        className="sr-only"
                    />
                    <div className={`p-6 border-2 rounded-lg cursor-pointer transition-colors ${
                        data.practice_type === 'solo_practitioner'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200 hover:border-gray-300'
                    }`}>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Solo Practitioner</h3>
                        <p className="text-sm text-gray-600">
                            You're the primary provider and need to set up both organization and facility information.
                            Perfect for individual practices where you are both the provider and practice owner.
                        </p>
                    </div>
                </label>

                <label className="block">
                    <input
                        type="radio"
                        name="practice_type"
                        value="group_practice"
                        checked={data.practice_type === 'group_practice'}
                        onChange={(e) => handleFieldChange('practice_type', e.target.value as any)}
                        className="sr-only"
                    />
                    <div className={`p-6 border-2 rounded-lg cursor-pointer transition-colors ${
                        data.practice_type === 'group_practice'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200 hover:border-gray-300'
                    }`}>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Group Practice</h3>
                        <p className="text-sm text-gray-600">
                            You're joining a new group practice that needs to be set up in our system.
                            We'll collect organization, facility, and your individual provider information.
                        </p>
                    </div>
                </label>

                <label className="block">
                    <input
                        type="radio"
                        name="practice_type"
                        value="existing_organization"
                        checked={data.practice_type === 'existing_organization'}
                        onChange={(e) => handleFieldChange('practice_type', e.target.value as any)}
                        className="sr-only"
                    />
                    <div className={`p-6 border-2 rounded-lg cursor-pointer transition-colors ${
                        data.practice_type === 'existing_organization'
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200 hover:border-gray-300'
                    }`}>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Joining Existing Organization</h3>
                        <p className="text-sm text-gray-600">
                            You're joining an organization that's already set up in our system.
                            We'll focus on collecting your individual provider credentials and facility assignment.
                        </p>
                    </div>
                </label>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleNext}>Continue</Button>
            </div>
        </div>
    );

    const renderFacilitySelectionStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                    <Home className="h-8 w-8 text-blue-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Select Your Facility</h1>
                <p className="text-gray-600">
                    You're joining <strong>{invitation.organization_name}</strong>. Please select your primary practice location from the list below.
                </p>
            </div>

            <div className="space-y-4">
                {facilities.map(facility => (
                    <label key={facility.id} className="block">
                        <input
                            type="radio"
                            name="facility_id"
                            value={facility.id}
                            checked={data.facility_id === facility.id}
                            onChange={(e) => handleFieldChange('facility_id', parseInt(e.target.value))}
                            className="sr-only"
                        />
                        <div className={`p-6 border-2 rounded-lg cursor-pointer transition-colors ${
                            data.facility_id === facility.id
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-200 hover:border-gray-300'
                        }`}>
                            <h3 className="text-lg font-medium text-gray-900 mb-1">{facility.name}</h3>
                            <p className="text-sm text-gray-600 flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-gray-400" />
                                {facility.full_address}
                            </p>
                        </div>
                    </label>
                ))}
            </div>

            {(validationErrors.facility_id || errors.facility_id) && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.facility_id || errors.facility_id}</p>
            )}

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleNext}>Continue</Button>
            </div>
        </div>
    );

    const renderPersonalStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
                    <Key className="h-8 w-8 text-purple-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Personal Information</h1>
                <p className="text-gray-600">Create your account and provide basic information</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                    <input
                        type="text"
                        value={data.first_name}
                        onChange={(e) => handleFieldChange('first_name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.first_name || errors.first_name ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.first_name || errors.first_name) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.first_name || errors.first_name}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                    <input
                        type="text"
                        value={data.last_name}
                        onChange={(e) => handleFieldChange('last_name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.last_name || errors.last_name ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.last_name || errors.last_name) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.last_name || errors.last_name}</p>
                    )}
                </div>

                <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input
                        type="email"
                        value={data.email}
                        disabled
                        className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input
                        type="tel"
                        value={data.phone}
                        onChange={(e) => handleFieldChange('phone', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.phone || errors.phone ? 'border-red-300' : 'border-gray-300'
                        }`}
                        placeholder="(555) 123-4567"
                    />
                    {(validationErrors.phone || errors.phone) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.phone || errors.phone}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Title/Position</label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(e) => handleFieldChange('title', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g., Physician, Nurse Practitioner"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                    <input
                        type="password"
                        value={data.password}
                        onChange={(e) => handleFieldChange('password', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.password || errors.password ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.password || errors.password) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.password || errors.password}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                    <input
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => handleFieldChange('password_confirmation', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.password_confirmation || errors.password_confirmation ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.password_confirmation || errors.password_confirmation) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.password_confirmation || errors.password_confirmation}</p>
                    )}
                </div>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleNext}>Continue</Button>
            </div>
        </div>
    );

    const renderOrganizationStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
                    <Key className="h-8 w-8 text-purple-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Organization Information</h1>
                <p className="text-gray-600">Provide your organization details</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Organization Name *</label>
                    <input
                        type="text"
                        value={data.organization_name}
                        onChange={(e) => handleFieldChange('organization_name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.organization_name || errors.organization_name ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.organization_name || errors.organization_name) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.organization_name || errors.organization_name}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Tax ID</label>
                    <input
                        type="text"
                        value={data.organization_tax_id}
                        onChange={(e) => handleFieldChange('organization_tax_id', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.organization_tax_id || errors.organization_tax_id ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.organization_tax_id || errors.organization_tax_id) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.organization_tax_id || errors.organization_tax_id}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Practice Type</label>
                    <select
                        value={data.practice_type}
                        onChange={(e) => handleFieldChange('practice_type', e.target.value as any)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Select practice type</option>
                        <option value="solo_practitioner">Solo Practitioner</option>
                        <option value="group_practice">Group Practice</option>
                        <option value="existing_organization">Joining Existing Organization</option>
                    </select>
                    {validationErrors.practice_type && <p className="mt-1 text-sm text-red-600">{validationErrors.practice_type}</p>}
                </div>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleNext}>Continue</Button>
            </div>
        </div>
    );

    const renderFacilityStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
                    <Key className="h-8 w-8 text-purple-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Facility Information</h1>
                <p className="text-gray-600">Provide your facility details</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Facility Name *</label>
                    <input
                        type="text"
                        value={data.facility_name}
                        onChange={(e) => handleFieldChange('facility_name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.facility_name || errors.facility_name ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.facility_name || errors.facility_name) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.facility_name || errors.facility_name}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Facility Type</label>
                    <select
                        value={data.facility_type}
                        onChange={(e) => handleFieldChange('facility_type', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Select facility type</option>
                        {facilityTypes.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    {validationErrors.facility_type && <p className="mt-1 text-sm text-red-600">{validationErrors.facility_type}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Facility Address *</label>
                    <input
                        type="text"
                        value={data.facility_address}
                        onChange={(e) => handleFieldChange('facility_address', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.facility_address || errors.facility_address ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.facility_address || errors.facility_address) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.facility_address || errors.facility_address}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">City *</label>
                    <input
                        type="text"
                        value={data.facility_city}
                        onChange={(e) => handleFieldChange('facility_city', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.facility_city || errors.facility_city ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.facility_city || errors.facility_city) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.facility_city || errors.facility_city}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">State *</label>
                    <select
                        value={data.facility_state}
                        onChange={(e) => handleFieldChange('facility_state', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Select state</option>
                        {states.map((state) => (
                            <option key={state.code} value={state.code}>
                                {state.name}
                            </option>
                        ))}
                    </select>
                    {validationErrors.facility_state && <p className="mt-1 text-sm text-red-600">{validationErrors.facility_state}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">ZIP Code *</label>
                    <input
                        type="text"
                        value={data.facility_zip}
                        onChange={(e) => handleFieldChange('facility_zip', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.facility_zip || errors.facility_zip ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.facility_zip || errors.facility_zip) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.facility_zip || errors.facility_zip}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input
                        type="tel"
                        value={data.facility_phone}
                        onChange={(e) => handleFieldChange('facility_phone', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.facility_phone || errors.facility_phone ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.facility_phone || errors.facility_phone) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.facility_phone || errors.facility_phone}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input
                        type="email"
                        value={data.facility_email}
                        onChange={(e) => handleFieldChange('facility_email', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.facility_email || errors.facility_email ? 'border-red-300' : 'border-gray-300'
                        }`}
                    />
                    {(validationErrors.facility_email || errors.facility_email) && (
                        <p className="mt-1 text-sm text-red-600">{validationErrors.facility_email || errors.facility_email}</p>
                    )}
                </div>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleNext}>Continue</Button>
            </div>
        </div>
    );

    const renderCredentialsStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
                    <Shield className="h-8 w-8 text-purple-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Professional Credentials</h1>
                <p className="text-gray-600">Provide your professional credentials for verification</p>
            </div>

            <div className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        NPI Number
                    </label>
                    <input
                        type="text"
                        value={data.individual_npi}
                        onChange={(e) => handleFieldChange('individual_npi', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            validationErrors.individual_npi || errors.individual_npi
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                        placeholder="10-digit NPI number"
                    />
                    {(validationErrors.individual_npi || errors.individual_npi) && (
                        <p className="mt-1 text-sm text-red-600">
                            {validationErrors.individual_npi || errors.individual_npi}
                        </p>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            License Number
                        </label>
                        <input
                            type="text"
                            value={data.license_number}
                            onChange={(e) => handleFieldChange('license_number', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        {validationErrors.license_number && <p className="mt-1 text-sm text-red-600">{validationErrors.license_number}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            License State
                        </label>
                        <select
                            value={data.license_state}
                            onChange={(e) => handleFieldChange('license_state', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select state</option>
                            <option value="AL">Alabama</option>
                            <option value="CA">California</option>
                            <option value="FL">Florida</option>
                            <option value="TX">Texas</option>
                            {/* Add more states */}
                        </select>
                        {validationErrors.license_state && <p className="mt-1 text-sm text-red-600">{validationErrors.license_state}</p>}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Specialty
                    </label>
                    <select
                        value={data.specialty}
                        onChange={(e) => handleFieldChange('specialty', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Select specialty</option>
                        {specialties.map((spec) => (
                            <option key={spec.value} value={spec.value}>
                                {spec.label}
                            </option>
                        ))}
                    </select>
                    {validationErrors.specialty && <p className="mt-1 text-sm text-red-600">{validationErrors.specialty}</p>}
                </div>

                <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                    <input
                        type="checkbox"
                        id="accept_terms"
                        checked={data.accept_terms}
                        onChange={(e) => handleFieldChange('accept_terms', e.target.checked)}
                        className={`mt-1 ${
                            validationErrors.accept_terms || errors.accept_terms
                                ? 'border-red-300 focus:ring-red-500'
                                : ''
                        }`}
                    />
                    <label htmlFor="accept_terms" className="text-sm text-gray-700">
                        I agree to the <a href="#" className="text-blue-600 hover:underline">Terms of Service</a> and{' '}
                        <a href="#" className="text-blue-600 hover:underline">Privacy Policy</a>. I understand that my credentials
                        will be verified before account activation.
                    </label>
                </div>
                {(validationErrors.accept_terms || errors.accept_terms) && (
                    <p className="mt-1 text-sm text-red-600">
                        {validationErrors.accept_terms || errors.accept_terms}
                    </p>
                )}
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleNext}>Continue</Button>
            </div>
        </div>
    );

    const renderBillingStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <CreditCard className="h-8 w-8 text-green-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Billing Information</h1>
                <p className="text-gray-600">Where invoices should be sent (can be different from shipping address)</p>
            </div>

            <div className="bg-blue-50 p-4 rounded-lg mb-6">
                <button
                    type="button"
                    onClick={copyFacilityToBilling}
                    className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                    📋 Copy facility address to billing address
                </button>
            </div>

            <div className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Bill-To Address
                    </label>
                    <textarea
                        value={data.billing_address}
                        onChange={(e) => handleFieldChange('billing_address', e.target.value)}
                        rows={2}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Billing address (can be different from facility address)"
                    />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <input
                            type="text"
                            value={data.billing_city}
                            onChange={(e) => handleFieldChange('billing_city', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">State</label>
                        <select
                            value={data.billing_state}
                            onChange={(e) => handleFieldChange('billing_state', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select state</option>
                            {states.map(state => (
                                <option key={state.code} value={state.code}>{state.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">ZIP Code</label>
                        <input
                            type="text"
                            value={data.billing_zip}
                            onChange={(e) => handleFieldChange('billing_zip', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="12345 or 12345-6789"
                        />
                    </div>
                </div>

                <div className="border-t pt-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Accounts Payable Contact</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                AP Contact Name
                            </label>
                            <input
                                type="text"
                                value={data.ap_contact_name}
                                onChange={(e) => handleFieldChange('ap_contact_name', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Person who handles payments"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                AP Contact Phone
                            </label>
                            <input
                                type="tel"
                                value={data.ap_contact_phone}
                                onChange={(e) => handleFieldChange('ap_contact_phone', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="(555) 123-4567"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                AP Contact Email
                            </label>
                            <input
                                type="email"
                                value={data.ap_contact_email}
                                onChange={(e) => handleFieldChange('ap_contact_email', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="billing@example.com"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={handleBack}>Back</Button>
                <Button onClick={handleCompleteRegistration} disabled={processing}>
                    {processing ? 'Creating Account...' : 'Complete Registration'}
                </Button>
            </div>
        </div>
    );

    const renderCompleteStep = () => (
        <div className="text-center space-y-6">
            <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <CheckCircle2 className="h-8 w-8 text-green-600" />
            </div>
            <h1 className="text-2xl font-bold text-gray-900">Account Created Successfully!</h1>
            <p className="text-gray-600">
                Your comprehensive practice profile has been created and is pending verification.
                You'll receive email confirmation once your credentials are approved.
            </p>
            <div className="bg-blue-50 p-4 rounded-lg">
                <h3 className="text-sm font-medium text-blue-800 mb-2">What's Next?</h3>
                <ul className="text-sm text-blue-700 space-y-1">
                    <li>• We'll verify your credentials within 1-2 business days</li>
                    <li>• Your organization and facility will be set up in our system</li>
                    <li>• You'll receive email notification when your account is activated</li>
                    <li>• Manufacturer onboarding forms will be auto-populated with your information</li>
                    <li>• Start accessing wound care products and services</li>
                </ul>
            </div>
            <Button onClick={() => window.location.href = '/login'}>
                Continue to Login
            </Button>
        </div>
    );

    return (
        <>
        <Head title="Provider Invitation" />
            <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div className="sm:mx-auto sm:w-full sm:max-w-2xl">
                    <Card>
                        <CardContent className="p-8">
                            {step === 'review' && renderReviewStep()}
                            {step === 'practice-type' && renderPracticeTypeStep()}
                            {step === 'facility-selection' && renderFacilitySelectionStep()}
                            {step === 'personal' && renderPersonalStep()}
                            {step === 'organization' && renderOrganizationStep()}
                            {step === 'facility' && renderFacilityStep()}
                            {step === 'credentials' && renderCredentialsStep()}
                            {step === 'billing' && renderBillingStep()}
                            {step === 'complete' && renderCompleteStep()}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
