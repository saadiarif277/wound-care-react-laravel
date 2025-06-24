import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import {
    CheckCircle2,
    FileText,
    Shield,
    Settings,
    Upload,
    Building2,
    Users,
    CreditCard,
    Eye
} from 'lucide-react';
import { toast } from 'sonner';

interface OnboardingStep {
    id: number;
    title: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    required: boolean;
    completed: boolean;
}

interface OnboardingFormData {
    [key: string]: File | null | boolean | string | number | Array<{
        email: string;
        role: string;
        first_name: string;
        last_name: string;
    }> | undefined;
    // Documents
    business_license: File | null;
    insurance_certificate: File | null;
    tax_exemption: File | null;
    w9_form: File | null;

    // Compliance
    hipaa_acknowledgment: boolean;
    business_associate_agreement: boolean;
    compliance_training_completed: boolean;

    // Payment
    payment_method: string;
    billing_contact_email: string;
    net_terms: number;

    // Integration
    ehr_system: string;
    integration_required: boolean;
    technical_contact_email: string;

    // Team setup
    additional_users: Array<{
        email: string;
        role: string;
        first_name: string;
        last_name: string;
    }>;

    step?: number;
}

interface OrganizationSetupWizardProps {
    organization: {
        id: string;
        name: string;
        type: string;
        status: string;
    };
    onboardingData: {
        current_step: number;
        completed_steps: number[];
        required_documents: string[];
        progress_percentage: number;
    };
}

export default function OrganizationSetupWizard({ organization, onboardingData }: OrganizationSetupWizardProps) {
    const [currentStep, setCurrentStep] = useState(onboardingData.current_step || 1);
    const [completedSteps, setCompletedSteps] = useState<number[]>(onboardingData.completed_steps || []);

    const steps: OnboardingStep[] = [
        {
            id: 1,
            title: 'Document Upload',
            description: 'Upload required business documents',
            icon: FileText,
            required: true,
            completed: completedSteps.includes(1)
        },
        {
            id: 2,
            title: 'Compliance & Security',
            description: 'Complete HIPAA and compliance requirements',
            icon: Shield,
            required: true,
            completed: completedSteps.includes(2)
        },
        {
            id: 3,
            title: 'Payment Setup',
            description: 'Configure billing and payment preferences',
            icon: CreditCard,
            required: true,
            completed: completedSteps.includes(3)
        },
        {
            id: 4,
            title: 'System Integration',
            description: 'Set up EHR and system integrations',
            icon: Settings,
            required: false,
            completed: completedSteps.includes(4)
        },
        {
            id: 5,
            title: 'Team Management',
            description: 'Invite team members and assign roles',
            icon: Users,
            required: false,
            completed: completedSteps.includes(5)
        },
        {
            id: 6,
            title: 'Review & Launch',
            description: 'Review setup and activate your account',
            icon: Eye,
            required: true,
            completed: completedSteps.includes(6)
        }
    ];

    const { data, setData, post } = useForm<OnboardingFormData>({
        business_license: null,
        insurance_certificate: null,
        tax_exemption: null,
        w9_form: null,
        hipaa_acknowledgment: false,
        business_associate_agreement: false,
        compliance_training_completed: false,
        payment_method: '',
        billing_contact_email: '',
        net_terms: 30,
        ehr_system: '',
        integration_required: false,
        technical_contact_email: '',
        additional_users: []
    });

    const progress = (completedSteps.length / steps.length) * 100;

    const handleStepComplete = (stepId: number) => {
        if (!completedSteps.includes(stepId)) {
            setCompletedSteps([...completedSteps, stepId]);
        }

        // Auto-advance to next step if not at the end
        const nextStep = stepId + 1;
        if (nextStep <= steps.length) {
            setCurrentStep(nextStep);
        }

        // Save progress to backend by merging step into form data
        post(route('onboarding.save-progress'), {
            data: { ...data, step: stepId },
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Progress saved successfully');
            },
            onError: () => {
                toast.error('Failed to save progress');
            }
        });
    };

    const renderDocumentUploadStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <FileText className="mx-auto h-12 w-12 text-blue-600 mb-4" />
                <h2 className="text-2xl font-bold text-gray-900">Document Upload</h2>
                <p className="text-gray-600">Please upload the required business documents</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {[
                    { key: 'business_license', label: 'Business License', required: true },
                    { key: 'insurance_certificate', label: 'Insurance Certificate', required: true },
                    { key: 'tax_exemption', label: 'Tax Exemption Certificate', required: false },
                    { key: 'w9_form', label: 'W-9 Form', required: true }
                ].map((doc) => (
                    <div key={doc.key} className="border border-gray-300 rounded-lg p-4">
                        <div className="flex items-center justify-between mb-2">
                            <h3 className="text-sm font-medium text-gray-900">{doc.label}</h3>
                            {doc.required && (
                                <span className="text-xs px-2 py-1 bg-red-100 text-red-800 rounded-full">Required</span>
                            )}
                        </div>

                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <Upload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
                            <input
                                type="file"
                                accept=".pdf,.doc,.docx,.jpg,.png"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setData(doc.key as keyof OnboardingFormData, file as any);
                                }}
                                className="hidden"
                                id={`upload-${doc.key}`}
                            />
                            <label
                                htmlFor={`upload-${doc.key}`}
                                className="cursor-pointer text-sm text-blue-600 hover:text-blue-500"
                            >
                                Click to upload or drag and drop
                            </label>
                            <p className="text-xs text-gray-500 mt-1">PDF, DOC, JPG, PNG up to 10MB</p>
                        </div>

                        {data[doc.key as keyof OnboardingFormData] && (
                            <div className="mt-2 flex items-center text-sm text-green-600">
                                <CheckCircle2 className="h-4 w-4 mr-1" />
                                File uploaded successfully
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="flex justify-end">
                <Button
                    onClick={() => handleStepComplete(1)}
                    disabled={!data.business_license || !data.insurance_certificate || !data.w9_form}
                >
                    Continue to Compliance
                </Button>
            </div>
        </div>
    );

    const renderComplianceStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <Shield className="mx-auto h-12 w-12 text-green-600 mb-4" />
                <h2 className="text-2xl font-bold text-gray-900">Compliance & Security</h2>
                <p className="text-gray-600">Review and acknowledge compliance requirements</p>
            </div>

            <div className="space-y-4">
                <div className="bg-blue-50 p-6 rounded-lg">
                    <div className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            id="hipaa"
                            checked={data.hipaa_acknowledgment}
                            onChange={(e) => setData('hipaa_acknowledgment', e.target.checked)}
                            className="mt-1"
                        />
                        <div>
                            <label htmlFor="hipaa" className="text-sm font-medium text-gray-900">
                                HIPAA Acknowledgment
                            </label>
                            <p className="text-sm text-gray-600 mt-1">
                                I acknowledge that our organization will handle PHI in accordance with HIPAA regulations
                                and will implement appropriate safeguards.
                            </p>
                            <button
                                type="button"
                                className="text-blue-600 text-sm hover:underline text-left"
                                onClick={() => window.open('/policies/hipaa', '_blank')}
                            >
                                View HIPAA Policy →
                            </button>
                        </div>
                    </div>
                </div>

                <div className="bg-purple-50 p-6 rounded-lg">
                    <div className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            id="baa"
                            checked={data.business_associate_agreement}
                            onChange={(e) => setData('business_associate_agreement', e.target.checked)}
                            className="mt-1"
                        />
                        <div>
                            <label htmlFor="baa" className="text-sm font-medium text-gray-900">
                                Business Associate Agreement
                            </label>
                            <p className="text-sm text-gray-600 mt-1">
                                I agree to the terms of the Business Associate Agreement and understand our
                                responsibilities as a covered entity.
                            </p>
                            <button
                                type="button"
                                className="text-purple-600 text-sm hover:underline text-left"
                                onClick={() => window.open('/documents/baa', '_blank')}
                            >
                                Download BAA →
                            </button>
                        </div>
                    </div>
                </div>

                <div className="bg-orange-50 p-6 rounded-lg">
                    <div className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            id="training"
                            checked={data.compliance_training_completed}
                            onChange={(e) => setData('compliance_training_completed', e.target.checked)}
                            className="mt-1"
                        />
                        <div>
                            <label htmlFor="training" className="text-sm font-medium text-gray-900">
                                Compliance Training
                            </label>
                            <p className="text-sm text-gray-600 mt-1">
                                I confirm that key staff members have completed required compliance training modules.
                            </p>
                            <button
                                type="button"
                                className="text-orange-600 text-sm hover:underline text-left"
                                onClick={() => window.open('/training/compliance', '_blank')}
                            >
                                Start Training →
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex justify-between">
                <Button type="button" variant="secondary" onClick={() => setCurrentStep(1)}>
                    Back
                </Button>
                <Button
                    onClick={() => handleStepComplete(2)}
                    disabled={!data.hipaa_acknowledgment || !data.business_associate_agreement || !data.compliance_training_completed}
                >
                    Continue to Payment Setup
                </Button>
            </div>
        </div>
    );

    const renderPaymentStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <CreditCard className="mx-auto h-12 w-12 text-green-600 mb-4" />
                <h2 className="text-2xl font-bold text-gray-900">Payment Setup</h2>
                <p className="text-gray-600">Configure your billing and payment preferences</p>
            </div>

            <div className="space-y-4">
                <div>
                    <label htmlFor="payment-method" className="block text-sm font-medium text-gray-700 mb-2">
                        Payment Method
                    </label>
                    <select
                        id="payment-method"
                        value={data.payment_method}
                        onChange={(e) => setData('payment_method', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Payment method"
                        title="Payment method"
                    >
                        <option value="">Select payment method</option>
                        <option value="net_terms">Net Terms (Invoice)</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="ach">ACH Transfer</option>
                    </select>
                </div>

                <div>
                    <label htmlFor="billing-email" className="block text-sm font-medium text-gray-700 mb-2">
                        Billing Contact Email
                    </label>
                    <input
                        id="billing-email"
                        type="email"
                        value={data.billing_contact_email}
                        onChange={(e) => setData('billing_contact_email', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="billing@organization.com"
                        aria-label="Billing contact email"
                    />
                </div>

                {data.payment_method === 'net_terms' && (
                    <div>
                        <label htmlFor="net-terms" className="block text-sm font-medium text-gray-700 mb-2">
                            Net Terms
                        </label>
                        <select
                            id="net-terms"
                            value={data.net_terms}
                            onChange={(e) => setData('net_terms', parseInt(e.target.value))}
                            aria-label="Net terms"
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value={15}>Net 15</option>
                            <option value={30}>Net 30</option>
                            <option value={45}>Net 45</option>
                            <option value={60}>Net 60</option>
                        </select>
                    </div>
                )}
            </div>

            <div className="flex justify-between">
                <Button variant="secondary" onClick={() => setCurrentStep(2)}>
                    Continue to Integration
                </Button>
            </div>
        </div>
    );

    const renderIntegrationStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <Settings className="mx-auto h-12 w-12 text-purple-600 mb-4" />
                <h2 className="text-2xl font-bold text-gray-900">System Integration</h2>
                <p className="text-gray-600">Set up EHR and system integrations (Optional)</p>
            </div>

            <div className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        EHR System
                    </label>
                    <select
                        value={data.ehr_system}
                        onChange={(e) => setData('ehr_system', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        title="EHR system"
                    >
                        <option value="">Select EHR system</option>
                        <option value="epic">Epic</option>
                        <option value="cerner">Cerner</option>
                        <option value="allscripts">Allscripts</option>
                        <option value="eclinicalworks">eClinicalWorks</option>
                        <option value="athenahealth">athenahealth</option>
                        <option value="other">Other</option>
                        <option value="none">No EHR Integration Needed</option>
                    </select>
                </div>

                <div className="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                    <input
                        type="checkbox"
                        id="integration_required"
                        checked={data.integration_required}
                        onChange={(e) => setData('integration_required', e.target.checked)}
                        className="mt-1"
                    />
                    <div>
                        <label htmlFor="integration_required" className="text-sm font-medium text-gray-900">
                            Request Integration Setup
                        </label>
                        <p className="text-sm text-gray-600 mt-1">
                            Our technical team will contact you to set up API integrations with your EHR system.
                        </p>
                    </div>
                </div>

                {data.integration_required && (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Technical Contact Email
                        </label>
                        <input
                            type="email"
                            value={data.technical_contact_email}
                            onChange={(e) => setData('technical_contact_email', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="it@organization.com"
                        />
                    </div>
                )}
            </div>

            <div className="flex justify-between">
                <Button type="button" variant="secondary" onClick={() => setCurrentStep(3)}>
                    Back
                </Button>
                <Button onClick={() => handleStepComplete(4)}>
                    Continue to Team Setup
                </Button>
            </div>
        </div>
    );

    const renderTeamStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <Users className="mx-auto h-12 w-12 text-blue-600 mb-4" />
                <h2 className="text-2xl font-bold text-gray-900">Team Management</h2>
                <p className="text-gray-600">Invite team members to your organization (Optional)</p>
            </div>

            <div className="bg-gray-50 p-4 rounded-lg mb-4">
                <p className="text-sm text-gray-600">
                    You can invite team members now or add them later from the team management page.
                </p>
            </div>

            <div className="space-y-4">
                {data.additional_users.map((user, index) => (
                    <div key={index} className="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-lg">
                        <input
                            type="text"
                            placeholder="First Name"
                            value={user.first_name}
                            onChange={(e) => {
                                const users = [...data.additional_users];
                                users[index]!.first_name = e.target.value;
                                setData('additional_users', users);
                            }}
                            className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <input
                            type="text"
                            placeholder="Last Name"
                            value={user.last_name}
                            onChange={(e) => {
                                const users = [...data.additional_users];
                                if (users[index]) {
                                    users[index].last_name = e.target.value;
                                    setData('additional_users', users);
                                }
                            }}
                            className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <input
                            type="email"
                            placeholder="Email"
                            value={user.email}
                            onChange={(e) => {
                                const users = [...data.additional_users];
                                if (users[index]) {
                                    users[index].email = e.target.value;
                                    setData('additional_users', users);
                                }
                            }}
                            className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <select
                            value={user.role}
                            onChange={(e) => {
                                const users = [...data.additional_users];
                                if (users[index]) {
                                    users[index].role = e.target.value;
                                }
                                setData('additional_users', users);
                            }}
                            className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Team member role"
                            title="Team member role"
                        >
                            <option value="">Select role</option>
                            <option value="provider">Provider</option>
                            <option value="admin">Administrator</option>
                            <option value="billing">Billing</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                ))}
            </div>

            <Button
                type="button"
                variant="secondary"
                onClick={() => {
                    setData('additional_users', [
                        ...data.additional_users,
                        { email: '', role: '', first_name: '', last_name: '' }
                    ]);
                }}
            >
                Add Team Member
            </Button>

            <div className="flex justify-between">
                <Button type="button" variant="secondary" onClick={() => setCurrentStep(4)}>
                    Back
                </Button>
                <Button onClick={() => handleStepComplete(5)}>
                    Continue to Review
                </Button>
            </div>
        </div>
    );

    const renderReviewStep = () => (
        <div className="space-y-6">
            <div className="text-center mb-8">
                <Eye className="mx-auto h-12 w-12 text-green-600 mb-4" />
                <h2 className="text-2xl font-bold text-gray-900">Review & Launch</h2>
                <p className="text-gray-600">Review your setup and activate your account</p>
            </div>

            <div className="bg-green-50 p-6 rounded-lg">
                <div className="flex items-center gap-2 mb-4">
                    <CheckCircle2 className="h-6 w-6 text-green-600" />
                    <h3 className="text-lg font-medium text-green-900">Setup Complete!</h3>
                </div>
                <p className="text-green-700 mb-4">
                    Congratulations! You've completed the onboarding process for <strong>{organization.name}</strong>.
                </p>
                <ul className="text-sm text-green-700 space-y-1">
                    <li>✓ Required documents uploaded</li>
                    <li>✓ Compliance requirements completed</li>
                    <li>✓ Payment method configured</li>
                    {data.integration_required && <li>✓ Integration setup requested</li>}
                    {data.additional_users.length > 0 && <li>✓ Team members invited</li>}
                </ul>
            </div>

            <div className="bg-blue-50 p-4 rounded-lg">
                <h4 className="text-sm font-medium text-blue-800 mb-2">What happens next?</h4>
                <ul className="text-sm text-blue-700 space-y-1">
                    <li>• Your documents will be reviewed within 1-2 business days</li>
                    <li>• You'll receive email confirmation when approved</li>
                    <li>• Your team members will receive invitation emails</li>
                    <li>• You can start using the MSC Wound Care portal immediately</li>
                </ul>
            </div>

            <div className="flex justify-between">
                <Button type="button" variant="secondary" onClick={() => setCurrentStep(5)}>
                    Back
                </Button>
                <Button
                    onClick={() => handleStepComplete(6)}
                    className="bg-green-600 hover:bg-green-700"
                >
                    Activate Account
                </Button>
            </div>
        </div>
    );

    const renderStepContent = () => {
        switch (currentStep) {
            case 1: return renderDocumentUploadStep();
            case 2: return renderComplianceStep();
            case 3: return renderPaymentStep();
            case 4: return renderIntegrationStep();
            case 5: return renderTeamStep();
            case 6: return renderReviewStep();
            default: return null;
        }
    };

    return (
        <MainLayout>
            <Head title="Organization Setup" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <Card
                        title={
                            <div className="flex items-center gap-2">
                                <Building2 className="h-6 w-6" />
                                {organization.name} Setup
                            </div>
                        }
                    >
                        <div className="mb-6">
                            <div className="flex items-center justify-between mb-4">
                                <p className="text-sm text-gray-600">
                                    Complete your organization setup to start using the MSC Portal
                                </p>
                                <span className="text-sm px-3 py-1 bg-blue-100 text-blue-800 rounded-full">
                                    {Math.round(progress)}% Complete
                                </span>
                                <div
                                    className={`bg-blue-600 h-2 rounded-full transition-all duration-300 progress-bar`}
                                    data-progress={progress}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>
                            {/* Step Navigation */}
                            <div className="mb-8">
                                <nav className="flex justify-between">
                                    {steps.map((step) => {
                                        const Icon = step.icon;
                                        const isCompleted = completedSteps.includes(step.id);
                                        const isCurrent = currentStep === step.id;
                                        const isAccessible = step.id <= Math.max(...completedSteps, currentStep);

                                        return (
                                            <div
                                                key={step.id}
                                                className="flex flex-col items-center cursor-pointer"
                                                onClick={() => isAccessible && setCurrentStep(step.id)}
                                            >
                                                <div className={`
                                                    flex items-center justify-center w-10 h-10 rounded-full border-2
                                                    ${isCompleted ? 'bg-green-100 border-green-500 text-green-600' :
                                                      isCurrent ? 'bg-blue-100 border-blue-500 text-blue-600' :
                                                      isAccessible ? 'bg-gray-100 border-gray-300 text-gray-400 hover:bg-gray-200' :
                                                      'bg-gray-50 border-gray-200 text-gray-300'}
                                                `}>
                                                    {isCompleted ? (
                                                        <CheckCircle2 className="h-5 w-5" />
                                                    ) : (
                                                        <Icon className="h-4 w-4" />
                                                    )}
                                                </div>
                                                <div className="mt-2 text-center">
                                                    <p className={`text-xs font-medium ${isCurrent ? 'text-blue-600' : 'text-gray-500'}`}>
                                                        {step.title}
                                                    </p>
                                                    {step.required && !isCompleted && (
                                                        <span className="text-xs mt-1 px-2 py-0.5 bg-red-100 text-red-800 rounded-full">Required</span>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </nav>
                            </div>

                            {/* Step Content */}
                            <div className="min-h-[400px]">
                                {renderStepContent()}
                            </div>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
