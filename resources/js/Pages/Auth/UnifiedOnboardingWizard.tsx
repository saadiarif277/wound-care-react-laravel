import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Progress } from '@/Components/ui/progress';
import { toast } from 'sonner';
import {
    Building2,
    Hospital,
    User,
    FileText,
    CheckCircle,
    ChevronRight,
    ChevronLeft} from 'lucide-react';

// Import step components
import OrganizationStep from './OnboardingSteps/OrganizationStep';
import FacilityStep from './OnboardingSteps/FacilityStep';
import ProviderStep from './OnboardingSteps/ProviderStep';
import BAAStep from './OnboardingSteps/BAAStep';
import ReviewStep from './OnboardingSteps/ReviewStep';

interface UnifiedOnboardingWizardProps {
    invitation: {
        id: string;
        email: string;
        organization_name?: string;
        token: string;
    };
    states: Array<{ code: string; name: string }>;
}

interface OnboardingData {
    // Organization Info
    organization_name: string;
    organization_type: string;
    organization_tax_id: string;
    contact_email: string;
    contact_phone: string;
    address: string;
    city: string;
    state: string;
    zip_code: string;
    billing_address: string;
    billing_city: string;
    billing_state: string;
    billing_zip: string;
    ap_contact_name: string;
    ap_contact_phone: string;
    ap_contact_email: string;

    // Facility Info
    facility_name: string;
    facility_type: string;
    group_npi: string;
    facility_tax_id: string;
    facility_ptan: string;
    facility_medicaid_number: string;
    facility_address: string;
    facility_city: string;
    facility_state: string;
    facility_zip: string;
    facility_phone: string;
    facility_fax: string;
    facility_email: string;
    facility_contact_name: string;
    facility_contact_phone: string;
    facility_contact_email: string;
    facility_contact_fax: string;
    business_hours: string;
    default_place_of_service: string;

    // Provider Info
    first_name: string;
    last_name: string;
    credentials: string;
    email: string;
    password: string;
    password_confirmation: string;
    phone: string;
    fax: string;
    specialty: string;
    individual_npi: string;
    tax_id: string;
    ptan: string;
    medicaid_number: string;
    license_number: string;
    license_state: string;
    license_expiry: string;

    // BAA
    baa_signed: boolean;
    baa_signed_at?: string;

    // Terms
    accept_terms: boolean;
}

const STEPS = [
    { id: 1, name: 'Organization', icon: Building2, description: 'Set up your organization details' },
    { id: 2, name: 'Facility', icon: Hospital, description: 'Add your primary facility' },
    { id: 3, name: 'Provider', icon: User, description: 'Your professional information' },
    { id: 4, name: 'BAA Agreement', icon: FileText, description: 'Sign Business Associate Agreement' },
    { id: 5, name: 'Review', icon: CheckCircle, description: 'Review and complete setup' }
];

export default function UnifiedOnboardingWizard({ invitation, states }: UnifiedOnboardingWizardProps) {
    const [currentStep, setCurrentStep] = useState(1);
    const [completedSteps, setCompletedSteps] = useState<number[]>([]);
    const [isBaaCompleted, setIsBaaCompleted] = useState(false);

    const { data, setData, post, processing, errors, clearErrors } = useForm<OnboardingData>({
        // Organization defaults
        organization_name: invitation.organization_name || '',
        organization_type: 'healthcare',
        organization_tax_id: '',
        contact_email: invitation.email,
        contact_phone: '',
        address: '',
        city: '',
        state: '',
        zip_code: '',
        billing_address: '',
        billing_city: '',
        billing_state: '',
        billing_zip: '',
        ap_contact_name: '',
        ap_contact_phone: '',
        ap_contact_email: '',

        // Facility defaults
        facility_name: '',
        facility_type: '',
        group_npi: '',
        facility_tax_id: '',
        facility_ptan: '',
        facility_medicaid_number: '',
        facility_address: '',
        facility_city: '',
        facility_state: '',
        facility_zip: '',
        facility_phone: '',
        facility_fax: '',
        facility_email: '',
        facility_contact_name: '',
        facility_contact_phone: '',
        facility_contact_email: '',
        facility_contact_fax: '',
        business_hours: '',
        default_place_of_service: '11',

        // Provider defaults
        first_name: '',
        last_name: '',
        credentials: '',
        email: invitation.email,
        password: '',
        password_confirmation: '',
        phone: '',
        fax: '',
        specialty: '',
        individual_npi: '',
        tax_id: '',
        ptan: '',
        medicaid_number: '',
        license_number: '',
        license_state: '',
        license_expiry: '',

        // BAA
        baa_signed: false,
        baa_signed_at: undefined,

        // Terms
        accept_terms: false
    });

    const progress = (currentStep / STEPS.length) * 100;

    const handleNext = () => {
        if (validateCurrentStep()) {
            setCompletedSteps([...completedSteps, currentStep]);
            setCurrentStep(currentStep + 1);
            clearErrors();
        }
    };

    const handlePrevious = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
            clearErrors();
        }
    };

    const handleStepClick = (stepId: number) => {
        if (stepId <= currentStep || completedSteps.includes(stepId - 1)) {
            setCurrentStep(stepId);
            clearErrors();
        }
    };

    const validateCurrentStep = (): boolean => {
        // Add validation logic for each step
        switch (currentStep) {
            case 1: // Organization
                if (!data.organization_name || !data.organization_type) {
                    toast.error('Please fill in all required organization fields');
                    return false;
                }
                break;
            case 2: // Facility
                if (!data.facility_name || !data.facility_type) {
                    toast.error('Please fill in all required facility fields');
                    return false;
                }
                break;
            case 3: // Provider
                if (!data.first_name || !data.last_name || !data.password) {
                    toast.error('Please fill in all required provider fields');
                    return false;
                }
                if (data.password !== data.password_confirmation) {
                    toast.error('Passwords do not match');
                    return false;
                }
                break;
            case 4: // BAA
                if (!isBaaCompleted) {
                    toast.error('Please sign the Business Associate Agreement');
                    return false;
                }
                break;
        }
        return true;
    };

    const handleSubmit = () => {
        if (!data.accept_terms) {
            toast.error('Please accept the terms and conditions');
            return;
        }

        post(`/auth/unified-onboarding/${invitation.token}/complete`, {
            onSuccess: () => {
                toast.success('Account created successfully!');
                router.visit('/login');
            },
            onError: () => {
                toast.error('Failed to complete onboarding. Please check your information.');
            }
        });
    };

    const renderStepContent = () => {
        switch (currentStep) {
            case 1:
                return <OrganizationStep data={data} setData={setData} errors={errors} states={states} />;
            case 2:
                return <FacilityStep data={data} setData={setData} errors={errors} states={states} />;
            case 3:
                return <ProviderStep data={data} setData={setData} errors={errors} states={states} />;
            case 4:
                return <BAAStep 
                    data={data} 
                    setData={setData} 
                    onComplete={() => {
                        setIsBaaCompleted(true);
                        setData('baa_signed', true);
                        setData('baa_signed_at', new Date().toISOString());
                    }} 
                />;
            case 5:
                return <ReviewStep data={data} setData={setData} />;
            default:
                return null;
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title="Complete Your Onboarding" />

            <div className="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">Welcome to Your Healthcare Platform</h1>
                    <p className="mt-2 text-lg text-gray-600">
                        Let's get your organization set up in just a few steps
                    </p>
                </div>

                {/* Progress Bar */}
                <div className="mb-8">
                    <Progress value={progress} className="h-2" />
                </div>

                {/* Step Indicators */}
                <div className="flex justify-between mb-8">
                    {STEPS.map((step) => {
                        const Icon = step.icon;
                        const isActive = currentStep === step.id;
                        const isCompleted = completedSteps.includes(step.id);
                        const isClickable = step.id <= currentStep || completedSteps.includes(step.id - 1);

                        return (
                            <button
                                key={step.id}
                                onClick={() => handleStepClick(step.id)}
                                disabled={!isClickable}
                                className={`flex flex-col items-center p-4 rounded-lg transition-all ${
                                    isActive 
                                        ? 'bg-blue-50 border-2 border-blue-500' 
                                        : isCompleted 
                                        ? 'bg-green-50 border-2 border-green-500'
                                        : isClickable
                                        ? 'bg-white border-2 border-gray-300 hover:border-gray-400 cursor-pointer'
                                        : 'bg-gray-100 border-2 border-gray-200 cursor-not-allowed'
                                }`}
                            >
                                <div className={`p-3 rounded-full mb-2 ${
                                    isActive 
                                        ? 'bg-blue-500 text-white' 
                                        : isCompleted 
                                        ? 'bg-green-500 text-white'
                                        : 'bg-gray-300 text-gray-600'
                                }`}>
                                    {isCompleted ? <CheckCircle className="h-6 w-6" /> : <Icon className="h-6 w-6" />}
                                </div>
                                <span className={`text-sm font-medium ${
                                    isActive || isCompleted ? 'text-gray-900' : 'text-gray-500'
                                }`}>
                                    {step.name}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Step Content */}
                <div className="bg-white rounded-lg shadow-sm p-8 mb-8">
                    {renderStepContent()}
                </div>

                {/* Navigation Buttons */}
                <div className="flex justify-between">
                    <Button
                        variant="secondary"
                        onClick={handlePrevious}
                        disabled={currentStep === 1}
                        className="flex items-center"
                    >
                        <ChevronLeft className="h-4 w-4 mr-2" />
                        Previous
                    </Button>

                    {currentStep < STEPS.length ? (
                        <Button
                            onClick={handleNext}
                            className="flex items-center"
                        >
                            Next
                            <ChevronRight className="h-4 w-4 ml-2" />
                        </Button>
                    ) : (
                        <Button
                            onClick={handleSubmit}
                            disabled={processing || !data.accept_terms}
                            className="flex items-center"
                        >
                            {processing ? 'Creating Account...' : 'Complete Setup'}
                            <CheckCircle className="h-4 w-4 ml-2" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
} 