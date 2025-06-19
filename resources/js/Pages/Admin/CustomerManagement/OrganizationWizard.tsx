import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import { CheckCircle2, Building2, Users, FileText, Settings } from 'lucide-react';

interface OrganizationFormData {
    name: string;
    tax_id: string;
    type: string;
    status: string;
    sales_rep_id: string;
    billing_address: {
        street: string;
        city: string;
        state: string;
        zip: string;
    };
    primary_contact: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        title: string;
    };
}

interface StepComponentProps {
    data: OrganizationFormData;
    setData: (key: keyof OrganizationFormData, value: any) => void;
    errors: Record<string, string>;
}

const steps = [
    { id: 1, title: 'Basic Information', icon: Building2, description: 'Organization details and contact info' },
    { id: 2, title: 'Address & Contact', icon: Users, description: 'Location and primary contact details' },
    { id: 3, title: 'Configuration', icon: Settings, description: 'Account settings and preferences' },
    { id: 4, title: 'Review & Submit', icon: FileText, description: 'Review information and create organization' }
];

const BasicInformationStep: React.FC<StepComponentProps> = ({ data, setData, errors }) => (
    <div className="space-y-6">
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
                Organization Name *
            </label>
            <input
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter organization name"
            />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Tax ID / EIN
                </label>
                <input
                    type="text"
                    value={data.tax_id}
                    onChange={(e) => setData('tax_id', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="XX-XXXXXXX"
                />
                {errors.tax_id && <p className="mt-1 text-sm text-red-600">{errors.tax_id}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Organization Type *
                </label>
                <select
                    value={data.type}
                    onChange={(e) => setData('type', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Select type</option>
                    <option value="hospital">Hospital</option>
                    <option value="clinic_group">Clinic Group</option>
                    <option value="wound_center">Wound Center</option>
                    <option value="physician_practice">Physician Practice</option>
                    <option value="home_health">Home Health</option>
                </select>
                {errors.type && <p className="mt-1 text-sm text-red-600">{errors.type}</p>}
            </div>
        </div>
    </div>
);

const AddressContactStep: React.FC<StepComponentProps> = ({ data, setData, errors }) => (
    <div className="space-y-6">
        <div>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Billing Address</h3>
            <div className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Street Address *
                    </label>
                    <input
                        type="text"
                        value={data.billing_address.street}
                        onChange={(e) => setData('billing_address', { ...data.billing_address, street: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="123 Main Street"
                    />
                    {errors['billing_address.street'] && <p className="mt-1 text-sm text-red-600">{errors['billing_address.street']}</p>}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">City *</label>
                        <input
                            type="text"
                            value={data.billing_address.city}
                            onChange={(e) => setData('billing_address', { ...data.billing_address, city: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">State *</label>
                        <select
                            value={data.billing_address.state}
                            onChange={(e) => setData('billing_address', { ...data.billing_address, state: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select state</option>
                            <option value="AL">Alabama</option>
                            <option value="CA">California</option>
                            <option value="FL">Florida</option>
                            <option value="TX">Texas</option>
                            {/* Add more states */}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">ZIP Code *</label>
                        <input
                            type="text"
                            value={data.billing_address.zip}
                            onChange={(e) => setData('billing_address', { ...data.billing_address, zip: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Primary Contact</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                    <input
                        type="text"
                        value={data.primary_contact.first_name}
                        onChange={(e) => setData('primary_contact', { ...data.primary_contact, first_name: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                    <input
                        type="text"
                        value={data.primary_contact.last_name}
                        onChange={(e) => setData('primary_contact', { ...data.primary_contact, last_name: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input
                        type="email"
                        value={data.primary_contact.email}
                        onChange={(e) => setData('primary_contact', { ...data.primary_contact, email: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                    <input
                        type="tel"
                        value={data.primary_contact.phone}
                        onChange={(e) => setData('primary_contact', { ...data.primary_contact, phone: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>
        </div>
    </div>
);

const ConfigurationStep: React.FC<StepComponentProps> = ({ data, setData, errors }) => (
    <div className="space-y-6">
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
                Account Status
            </label>
            <select
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <option value="pending">Pending</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
                Assigned Sales Representative
            </label>
            <select
                value={data.sales_rep_id}
                onChange={(e) => setData('sales_rep_id', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <option value="">Select sales rep</option>
                {/* This would be populated from props */}
                <option value="1">John Smith</option>
                <option value="2">Sarah Johnson</option>
            </select>
        </div>
    </div>
);

const ReviewStep: React.FC<StepComponentProps> = ({ data }) => (
    <div className="space-y-6">
        <div className="bg-gray-50 p-6 rounded-lg">
            <h3 className="text-lg font-medium text-gray-900 mb-4">Organization Summary</h3>
            <dl className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt className="text-sm font-medium text-gray-500">Name</dt>
                    <dd className="text-sm text-gray-900">{data.name}</dd>
                </div>
                <div>
                    <dt className="text-sm font-medium text-gray-500">Type</dt>
                    <dd className="text-sm text-gray-900">{data.type}</dd>
                </div>
                <div>
                    <dt className="text-sm font-medium text-gray-500">Tax ID</dt>
                    <dd className="text-sm text-gray-900">{data.tax_id || 'Not provided'}</dd>
                </div>
                <div>
                    <dt className="text-sm font-medium text-gray-500">Status</dt>
                    <dd className="text-sm text-gray-900">{data.status}</dd>
                </div>
            </dl>
        </div>

        <div className="bg-gray-50 p-6 rounded-lg">
            <h3 className="text-lg font-medium text-gray-900 mb-4">Primary Contact</h3>
            <p className="text-sm text-gray-900">
                {data.primary_contact.first_name} {data.primary_contact.last_name}
            </p>
            <p className="text-sm text-gray-600">{data.primary_contact.email}</p>
            {data.primary_contact.phone && (
                <p className="text-sm text-gray-600">{data.primary_contact.phone}</p>
            )}
        </div>
    </div>
);

export default function OrganizationWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [completedSteps, setCompletedSteps] = useState<number[]>([]);

    const { data, setData, post, processing, errors } = useForm<OrganizationFormData>({
        name: '',
        tax_id: '',
        type: '',
        status: 'pending',
        sales_rep_id: '',
        billing_address: {
            street: '',
            city: '',
            state: '',
            zip: ''
        },
        primary_contact: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            title: ''
        }
    });

    const progress = ((currentStep - 1) / (steps.length - 1)) * 100;

    const handleNext = () => {
        if (currentStep < steps.length) {
            setCompletedSteps([...completedSteps, currentStep]);
            setCurrentStep(currentStep + 1);
        }
    };

    const handlePrevious = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const handleSubmit = () => {
        post('/api/organizations', {
            onSuccess: () => {
                // Handle success
            }
        });
    };

    const renderStepContent = () => {
        const stepProps = { data, setData, errors };

        switch (currentStep) {
            case 1:
                return <BasicInformationStep {...stepProps} />;
            case 2:
                return <AddressContactStep {...stepProps} />;
            case 3:
                return <ConfigurationStep {...stepProps} />;
            case 4:
                return <ReviewStep {...stepProps} />;
            default:
                return null;
        }
    };

    return (
        <MainLayout>
            <Head title="Create Organization" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-6 w-6" />
                                Create New Organization
                            </CardTitle>
                            <Progress value={progress} className="mt-4" />
                        </CardHeader>

                        <CardContent>
                            {/* Step Navigation */}
                            <div className="mb-8">
                                <nav className="flex justify-between">
                                    {steps.map((step) => {
                                        const Icon = step.icon;
                                        const isCompleted = completedSteps.includes(step.id);
                                        const isCurrent = currentStep === step.id;

                                        return (
                                            <div key={step.id} className="flex flex-col items-center">
                                                <div className={`
                                                    flex items-center justify-center w-10 h-10 rounded-full border-2
                                                    ${isCompleted ? 'bg-green-100 border-green-500 text-green-600' :
                                                      isCurrent ? 'bg-blue-100 border-blue-500 text-blue-600' :
                                                      'bg-gray-100 border-gray-300 text-gray-400'}
                                                `}>
                                                    {isCompleted ? (
                                                        <CheckCircle2 className="h-5 w-5" />
                                                    ) : (
                                                        <Icon className="h-5 w-5" />
                                                    )}
                                                </div>
                                                <div className="mt-2 text-center">
                                                    <p className={`text-sm font-medium ${isCurrent ? 'text-blue-600' : 'text-gray-500'}`}>
                                                        {step.title}
                                                    </p>
                                                    <p className="text-xs text-gray-400 hidden md:block">
                                                        {step.description}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </nav>
                            </div>

                            {/* Step Content */}
                            <div className="mb-8">
                                {renderStepContent()}
                            </div>

                            {/* Navigation Buttons */}
                            <div className="flex justify-between">
                                <Button
                                    variant="outline"
                                    onClick={handlePrevious}
                                    disabled={currentStep === 1}
                                >
                                    Previous
                                </Button>

                                <div className="flex gap-2">
                                    {currentStep < steps.length ? (
                                        <Button onClick={handleNext}>
                                            Next
                                        </Button>
                                    ) : (
                                        <Button
                                            onClick={handleSubmit}
                                            disabled={processing}
                                            className="bg-green-600 hover:bg-green-700"
                                        >
                                            {processing ? 'Creating...' : 'Create Organization'}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
