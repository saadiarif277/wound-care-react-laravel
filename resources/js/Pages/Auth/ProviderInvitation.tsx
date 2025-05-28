import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { UserPlus, Building2, Mail, CheckCircle2, AlertCircle, Key, Shield, FileText } from 'lucide-react';
import { parseISO, isBefore, differenceInDays, formatDistanceToNow, format } from 'date-fns';

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

interface AcceptanceFormData {
    first_name: string;
    last_name: string;
    password: string;
    password_confirmation: string;
    phone: string;
    title: string;
    npi_number: string;
    license_number: string;
    license_state: string;
    specialty: string;
    accept_terms: boolean;
}

interface ProviderInvitationProps {
    invitation: InvitationData;
    token: string;
}

export default function ProviderInvitation({ invitation, token }: ProviderInvitationProps) {
    const [step, setStep] = useState<'review' | 'setup' | 'credentials' | 'complete'>('review');
    const [setupValidationErrors, setSetupValidationErrors] = useState<Record<string, string>>({});
    const [credentialsValidationErrors, setCredentialsValidationErrors] = useState<Record<string, string>>({});

    const { data, setData, post, processing, errors } = useForm<AcceptanceFormData>({
        first_name: '',
        last_name: '',
        password: '',
        password_confirmation: '',
        phone: '',
        title: '',
        npi_number: '',
        license_number: '',
        license_state: '',
        specialty: '',
        accept_terms: false
    });

    const isExpired = isBefore(parseISO(invitation.expires_at), new Date());
    const daysUntilExpiry = Math.max(0, differenceInDays(parseISO(invitation.expires_at), new Date()));

    const handleAcceptInvitation = () => {
        if (isExpired) return;
        setStep('setup');
    };

    const validateSetupForm = (): boolean => {
        const validationErrors: Record<string, string> = {};
        let isValid = true;

        // Validate required fields
        if (!data.first_name.trim()) {
            validationErrors.first_name = 'First name is required';
            isValid = false;
        }

        if (!data.last_name.trim()) {
            validationErrors.last_name = 'Last name is required';
            isValid = false;
        }

        if (!data.password) {
            validationErrors.password = 'Password is required';
            isValid = false;
        } else if (data.password.length < 8) {
            validationErrors.password = 'Password must be at least 8 characters long';
            isValid = false;
        }

        if (!data.password_confirmation) {
            validationErrors.password_confirmation = 'Password confirmation is required';
            isValid = false;
        } else if (data.password !== data.password_confirmation) {
            validationErrors.password_confirmation = 'Passwords do not match';
            isValid = false;
        }

        // Optional field validation with format checks
        if (data.phone && !/^[\+]?[\d\s\-\(\)]+$/.test(data.phone)) {
            validationErrors.phone = 'Please enter a valid phone number';
            isValid = false;
        }

        setSetupValidationErrors(validationErrors);
        return isValid;
    };

    const clearFieldValidationError = (fieldName: string) => {
        if (setupValidationErrors[fieldName]) {
            setSetupValidationErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[fieldName];
                return newErrors;
            });
        }
    };

    const handleFieldChange = (fieldName: keyof AcceptanceFormData, value: string | boolean) => {
        setData(fieldName, value);
        clearFieldValidationError(fieldName);
        clearCredentialsFieldError(fieldName);
    };

    const handleAccountSetup = () => {
        // Clear any previous validation errors
        setSetupValidationErrors({});

        // Validate the form before proceeding
        if (validateSetupForm()) {
            setStep('credentials');
        }
    };

    const validateCredentialsForm = (): boolean => {
        const validationErrors: Record<string, string> = {};
        let isValid = true;

        // Terms acceptance is required
        if (!data.accept_terms) {
            validationErrors.accept_terms = 'You must accept the Terms of Service and Privacy Policy to continue';
            isValid = false;
        }

        // Optional NPI validation if provided
        if (data.npi_number && !/^\d{10}$/.test(data.npi_number.replace(/\s/g, ''))) {
            validationErrors.npi_number = 'NPI number must be exactly 10 digits';
            isValid = false;
        }

        setCredentialsValidationErrors(validationErrors);
        return isValid;
    };

    const clearCredentialsFieldError = (fieldName: string) => {
        if (credentialsValidationErrors[fieldName]) {
            setCredentialsValidationErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[fieldName];
                return newErrors;
            });
        }
    };

    const handleCompleteRegistration = () => {
        // Clear any previous validation errors
        setCredentialsValidationErrors({});

        // Validate the credentials form before proceeding
        if (validateCredentialsForm()) {
            post(`/auth/provider-invitation/${token}/accept`, {
                onSuccess: () => {
                    setStep('complete');
                }
            });
        }
    };

    const renderReviewStep = () => (
        <div className="space-y-6">
            <div className="text-center">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                    <UserPlus className="h-8 w-8 text-blue-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">You're Invited!</h1>
                <p className="text-gray-600">
                    You've been invited to join <strong>{invitation.organization_name}</strong> as a {invitation.invited_role}
                </p>
            </div>

            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <Building2 className="h-5 w-5" />
                    Organization Details
                </h3>
                <dl className="space-y-3">
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Organization:</dt>
                        <dd className="text-sm text-gray-900">{invitation.organization_name}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Type:</dt>
                        <dd className="text-sm text-gray-900">{invitation.organization_type}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Your Role:</dt>
                        <dd className="text-sm text-gray-900">
                            <Badge variant="default">
                                {invitation.invited_role}
                            </Badge>
                        </dd>
                    </div>
                    <div className="flex justify-between">
                        <dt className="text-sm font-medium text-gray-500">Invited by:</dt>
                        <dd className="text-sm text-gray-900">{invitation.metadata.invited_by_name}</dd>
                    </div>
                </dl>
            </div>

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

            {isExpired ? (
                <div className="bg-red-50 p-4 rounded-lg">
                    <div className="flex items-center gap-2">
                        <AlertCircle className="h-5 w-5 text-red-600" />
                        <h4 className="text-sm font-medium text-red-800">Invitation Expired</h4>
                    </div>
                    <p className="text-sm text-red-700 mt-1">
                        This invitation has expired. Please contact the organization to request a new invitation.
                    </p>
                </div>
            ) : (
                <div className="flex gap-3 justify-end">
                    <Button variant="secondary">
                        Decline
                    </Button>
                    <Button onClick={handleAcceptInvitation}>
                        Accept Invitation
                    </Button>
                </div>
            )}
        </div>
    );

    const renderSetupStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <Key className="h-8 w-8 text-green-600" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Account Setup</h1>
                <p className="text-gray-600">Create your account to get started</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        First Name *
                    </label>
                    <input
                        type="text"
                        value={data.first_name}
                        onChange={(e) => handleFieldChange('first_name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            setupValidationErrors.first_name || errors.first_name
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                    />
                    {(setupValidationErrors.first_name || errors.first_name) && (
                        <p className="mt-1 text-sm text-red-600">
                            {setupValidationErrors.first_name || errors.first_name}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Last Name *
                    </label>
                    <input
                        type="text"
                        value={data.last_name}
                        onChange={(e) => handleFieldChange('last_name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            setupValidationErrors.last_name || errors.last_name
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                    />
                    {(setupValidationErrors.last_name || errors.last_name) && (
                        <p className="mt-1 text-sm text-red-600">
                            {setupValidationErrors.last_name || errors.last_name}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number
                    </label>
                    <input
                        type="tel"
                        value={data.phone}
                        onChange={(e) => handleFieldChange('phone', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            setupValidationErrors.phone || errors.phone
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                    />
                    {(setupValidationErrors.phone || errors.phone) && (
                        <p className="mt-1 text-sm text-red-600">
                            {setupValidationErrors.phone || errors.phone}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Title/Position
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(e) => handleFieldChange('title', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            setupValidationErrors.title || errors.title
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                        placeholder="e.g., Physician, Nurse Practitioner"
                    />
                    {(setupValidationErrors.title || errors.title) && (
                        <p className="mt-1 text-sm text-red-600">
                            {setupValidationErrors.title || errors.title}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Password *
                    </label>
                    <input
                        type="password"
                        value={data.password}
                        onChange={(e) => handleFieldChange('password', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            setupValidationErrors.password || errors.password
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                    />
                    {(setupValidationErrors.password || errors.password) && (
                        <p className="mt-1 text-sm text-red-600">
                            {setupValidationErrors.password || errors.password}
                        </p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Confirm Password *
                    </label>
                    <input
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => handleFieldChange('password_confirmation', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            setupValidationErrors.password_confirmation || errors.password_confirmation
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                    />
                    {(setupValidationErrors.password_confirmation || errors.password_confirmation) && (
                        <p className="mt-1 text-sm text-red-600">
                            {setupValidationErrors.password_confirmation || errors.password_confirmation}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={() => setStep('review')}>
                    Back
                </Button>
                <Button onClick={handleAccountSetup}>
                    Continue
                </Button>
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
                        value={data.npi_number}
                        onChange={(e) => handleFieldChange('npi_number', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                            credentialsValidationErrors.npi_number || errors.npi_number
                                ? 'border-red-300 focus:ring-red-500'
                                : 'border-gray-300'
                        }`}
                        placeholder="10-digit NPI number"
                    />
                    {(credentialsValidationErrors.npi_number || errors.npi_number) && (
                        <p className="mt-1 text-sm text-red-600">
                            {credentialsValidationErrors.npi_number || errors.npi_number}
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
                        {errors.license_number && <p className="mt-1 text-sm text-red-600">{errors.license_number}</p>}
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
                        {errors.license_state && <p className="mt-1 text-sm text-red-600">{errors.license_state}</p>}
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
                        <option value="wound_care">Wound Care</option>
                        <option value="family_medicine">Family Medicine</option>
                        <option value="internal_medicine">Internal Medicine</option>
                        <option value="emergency_medicine">Emergency Medicine</option>
                        <option value="surgery">Surgery</option>
                        <option value="dermatology">Dermatology</option>
                        <option value="nursing">Nursing</option>
                    </select>
                    {errors.specialty && <p className="mt-1 text-sm text-red-600">{errors.specialty}</p>}
                </div>

                <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                    <input
                        type="checkbox"
                        id="accept_terms"
                        checked={data.accept_terms}
                        onChange={(e) => handleFieldChange('accept_terms', e.target.checked)}
                        className={`mt-1 ${
                            credentialsValidationErrors.accept_terms || errors.accept_terms
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
                {(credentialsValidationErrors.accept_terms || errors.accept_terms) && (
                    <p className="mt-1 text-sm text-red-600">
                        {credentialsValidationErrors.accept_terms || errors.accept_terms}
                    </p>
                )}
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={() => setStep('setup')}>
                    Back
                </Button>
                <Button onClick={handleCompleteRegistration} disabled={processing}>
                    Complete Registration
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
                Your account has been created and is pending credential verification.
                You'll receive an email confirmation once your credentials are approved.
            </p>
            <div className="bg-blue-50 p-4 rounded-lg">
                <h3 className="text-sm font-medium text-blue-800 mb-2">What's Next?</h3>
                <ul className="text-sm text-blue-700 space-y-1">
                    <li>• We'll verify your credentials within 1-2 business days</li>
                    <li>• You'll receive email notification when your account is activated</li>
                    <li>• Complete your organization onboarding process</li>
                    <li>• Start using the MSC Wound Care portal</li>
                </ul>
            </div>
            <Button>
                Return to Login
            </Button>
        </div>
    );

    return (
        <Head title="Provider Invitation" />
    );
}
