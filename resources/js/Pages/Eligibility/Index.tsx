import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
    FiUser, FiCalendar, FiCreditCard, FiFileText,
    FiCheck, FiAlertCircle, FiClock, FiDollarSign,
    FiInfo, FiPlus, FiX, FiSearch, FiHome, FiHash
} from 'react-icons/fi';
import axios from 'axios';
import Select from 'react-select/async';
import { components } from 'react-select';
import PriorAuthForm from './PriorAuthForm';
import { toast } from 'react-hot-toast';
import { Toaster } from 'react-hot-toast';

interface ServiceCode {
    code: string;
    type: 'cpt' | 'hcpcs';
    description?: string;
}

interface FormData {
    patient: {
        first_name: string;
        last_name: string;
        date_of_birth: string;
        gender: 'male' | 'female' | 'other';
        member_id: string;
    };
    payer: {
        name: string;
        id: string;
        insurance_type: string;
    };
    service_date: string;
    service_codes: ServiceCode[];
}

interface EligibilityResponse {
    eligibility_status: string;
    benefits: {
        deductible: {
            individual: number;
            family: number;
            remaining: number;
        };
        coinsurance: {
            percentage: number;
            applies_after_deductible: boolean;
        };
        copay: {
            office_visit: number;
            specialist: number;
        };
        out_of_pocket: {
            individual: number;
            family: number;
            remaining: number;
        };
    };
    pre_auth_required: boolean;
    pre_auth_status: string;
    cost_estimate: {
        total_cost: number;
        insurance_pays: number;
        patient_responsibility: number;
        breakdown: {
            deductible_applied: number;
            coinsurance_amount: number;
            copay_amount: number;
        };
    };
    care_reminders: Array<{
        id: number;
        type: string;
        description: string;
        due_date: string;
        priority: string;
    }>;
    transaction_id: string;
    timestamp: string;
}

interface Provider {
    value: string;
    label: string;
    payer_id: string;
    payer_name: string;
    npi: string;
}

// Mock providers data - replace with actual API call
const mockProviders: Provider[] = [
    { value: '1', label: 'Dr. John Smith - Main Hospital', payer_id: 'MEDICARE', payer_name: 'Medicare', npi: '1234567890' },
    { value: '2', label: 'Downtown Medical Center', payer_id: 'AETNA', payer_name: 'Aetna', npi: '0987654321' },
    { value: '3', label: 'Dr. Sarah Johnson - Northside Clinic', payer_id: 'BCBS', payer_name: 'Blue Cross Blue Shield', npi: '1122334455' },
    { value: '4', label: 'City General Hospital', payer_id: 'CIGNA', payer_name: 'Cigna', npi: '5544332211' },
    { value: '5', label: 'Dr. Michael Brown - Specialty Care', payer_id: 'UNITED', payer_name: 'UnitedHealthcare', npi: '6677889900' },
];

interface ValidationErrors {
    patient?: {
        first_name?: string[];
        last_name?: string[];
        date_of_birth?: string[];
        gender?: string[];
        member_id?: string[];
    };
    payer?: {
        name?: string[];
        id?: string[];
        insurance_type?: string[];
    };
    service_date?: string[];
    service_codes?: string[];
}

interface InputProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    icon?: React.ReactNode;
    disabled?: boolean;
    placeholder?: string;
}

const Input = ({ label, value, onChange, type = 'text', required = false, icon = null, disabled = false, placeholder }: InputProps) => (
    <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
            {label}
            {required && <span className="text-red-500">*</span>}
        </label>
        <div className="relative">
            {icon && (
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    {icon}
                </div>
            )}
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={`w-full py-2 ${icon ? 'pl-10' : 'pl-3'} pr-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 ${disabled ? 'bg-gray-100' : ''}`}
                required={required}
                disabled={disabled}
                placeholder={placeholder}
            />
        </div>
    </div>
);

const EligibilityPage = () => {
    const [step, setStep] = useState(1);
    const [verificationResult, setVerificationResult] = useState<EligibilityResponse | null>(null);
    const [isVerifying, setIsVerifying] = useState(false);
    const [serviceCodes, setServiceCodes] = useState<ServiceCode[]>([{ code: '', type: 'hcpcs' }]);
    const [showPriorAuthForm, setShowPriorAuthForm] = useState(false);
    const [priorAuthResult, setPriorAuthResult] = useState<any>(null);
    const [selectedProvider, setSelectedProvider] = useState<Provider | null>(null);
    const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
        patient: {
            first_name: '',
            last_name: '',
            date_of_birth: '',
            gender: 'male',
            member_id: '',
        },
        payer: {
            name: '',
            id: '',
            insurance_type: '',
        },
        service_date: '',
        service_codes: serviceCodes,
    });

    // Load provider options for the searchable dropdown
    const loadProviderOptions = (inputValue: string): Promise<Provider[]> => {
        return new Promise((resolve) => {
            setTimeout(() => {
                const filtered = mockProviders.filter(option =>
                    option.label.toLowerCase().includes(inputValue.toLowerCase())
                );
                resolve(filtered);
            }, 300);
        });
    };

    // Handle provider selection
    const handleProviderChange = (selected: Provider | null) => {
        setSelectedProvider(selected);
        if (selected) {
            setData('payer', {
                name: selected.payer_name,
                id: selected.payer_id,
                insurance_type: data.payer.insurance_type
            });
        } else {
            setData('payer', {
                name: '',
                id: '',
                insurance_type: data.payer.insurance_type
            });
        }
    };

    // Custom provider option component
    const ProviderOption = (props: any) => (
        <components.Option {...props}>
            <div className="flex flex-col">
                <span className="font-medium">{props.data.label}</span>
                <span className="text-sm text-gray-500">
                    {props.data.payer_name} | NPI: {props.data.npi}
                </span>
            </div>
        </components.Option>
    );

    // Validate form data before submission
    const validateForm = (): boolean => {
        const errors: ValidationErrors = {};
        let isValid = true;

        // Validate patient information
        if (!data.patient.first_name.trim()) {
            errors.patient = {
                ...errors.patient,
                first_name: ['First name is required']
            };
            isValid = false;
        }

        if (!data.patient.last_name.trim()) {
            errors.patient = {
                ...errors.patient,
                last_name: ['Last name is required']
            };
            isValid = false;
        }

        if (!data.patient.date_of_birth) {
            errors.patient = {
                ...errors.patient,
                date_of_birth: ['Date of birth is required']
            };
            isValid = false;
        } else {
            // Validate date of birth is not in the future
            const dob = new Date(data.patient.date_of_birth);
            if (dob > new Date()) {
                errors.patient = {
                    ...errors.patient,
                    date_of_birth: ['Date of birth cannot be in the future']
                };
                isValid = false;
            }
        }

        if (!data.patient.gender) {
            errors.patient = {
                ...errors.patient,
                gender: ['Gender is required']
            };
            isValid = false;
        }

        if (!data.patient.member_id.trim()) {
            errors.patient = {
                ...errors.patient,
                member_id: ['Member ID is required']
            };
            isValid = false;
        }

        // Validate payer information
        if (!data.payer.id || !data.payer.name) {
            errors.payer = {
                ...errors.payer,
                id: ['Provider must be selected'],
                name: ['Provider must be selected']
            };
            isValid = false;
        }

        // Validate service date
        if (!data.service_date) {
            errors.service_date = ['Service date is required'];
            isValid = false;
        } else {
            // Validate service date is not in the past
            const serviceDate = new Date(data.service_date);
            if (serviceDate < new Date(new Date().setHours(0, 0, 0, 0))) {
                errors.service_date = ['Service date cannot be in the past'];
                isValid = false;
            }
        }

        // Validate service codes
        const hasEmptyServiceCodes = data.service_codes.some(code => !code.code.trim());
        if (hasEmptyServiceCodes) {
            errors.service_codes = ['All service codes must be filled'];
            isValid = false;
        }

        setValidationErrors(errors);
        return isValid;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Clear previous errors
        setValidationErrors({});

        // Validate form
        if (!validateForm()) {
            toast.error('Please fix the validation errors before submitting');
            return;
        }

        setIsSubmitting(true);
        setIsVerifying(true);

        try {
            const response = await axios.post('/eligibility/verify', data);
            setVerificationResult(response.data);
            toast.success('Eligibility verification completed successfully');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error: any) {
            console.error('Verification failed:', error);

            if (error.response?.status === 422) {
                // Handle validation errors from the server
                const serverErrors = error.response.data.errors;
                setValidationErrors(serverErrors);
                toast.error('Please check the form for errors');
            } else {
                // Handle other types of errors
                toast.error(error.response?.data?.message || 'Failed to verify eligibility. Please try again.');
            }
        } finally {
            setIsSubmitting(false);
            setIsVerifying(false);
        }
    };

    const addServiceCode = () => {
        setServiceCodes([...serviceCodes, { code: '', type: 'hcpcs' }]);
    };

    const removeServiceCode = (index: number) => {
        setServiceCodes(serviceCodes.filter((_, i) => i !== index));
    };

    const updateServiceCode = (index: number, field: keyof ServiceCode, value: string) => {
        const newServiceCodes = [...serviceCodes];
        newServiceCodes[index] = { ...newServiceCodes[index], [field]: value };
        setServiceCodes(newServiceCodes);
        setData('service_codes', newServiceCodes);
    };

    const handlePriorAuthSuccess = (response: any) => {
        setPriorAuthResult(response);
        setShowPriorAuthForm(false);
        // Update the verification result with the new prior auth status
        if (verificationResult) {
            setVerificationResult({
                ...verificationResult,
                pre_auth_status: response.prior_auth_status,
            });
        }
    };

    const BenefitCard = ({ title, value, icon: Icon, className = '' }: any) => (
        <div className={`bg-white rounded-lg p-4 shadow ${className}`}>
            <div className="flex items-center gap-2 text-gray-600 mb-2">
                <Icon className="w-5 h-5" />
                <h3 className="font-medium">{title}</h3>
            </div>
            <p className="text-2xl font-semibold text-gray-900">{value}</p>
        </div>
    );

    const ReminderCard = ({ reminder }: { reminder: EligibilityResponse['care_reminders'][0] }) => (
        <div className={`bg-white rounded-lg p-4 shadow ${
            reminder.priority === 'high' ? 'border-l-4 border-red-500' :
            reminder.priority === 'medium' ? 'border-l-4 border-yellow-500' :
            'border-l-4 border-blue-500'
        }`}>
            <div className="flex items-start justify-between">
                <div>
                    <h4 className="font-medium text-gray-900">{reminder.type.replace('_', ' ').toUpperCase()}</h4>
                    <p className="text-gray-600 mt-1">{reminder.description}</p>
                    <p className="text-sm text-gray-500 mt-2">Due: {new Date(reminder.due_date).toLocaleDateString()}</p>
                </div>
                <span className={`px-2 py-1 rounded text-xs font-medium ${
                    reminder.priority === 'high' ? 'bg-red-100 text-red-800' :
                    reminder.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-blue-100 text-blue-800'
                }`}>
                    {reminder.priority.toUpperCase()}
                </span>
            </div>
        </div>
    );

    return (
        <MainLayout>
            <Head title="Eligibility Verification" />
            <Toaster position="top-right" />

            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="px-4 py-6 sm:px-0">
                    <div className="flex justify-between items-center mb-8">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Eligibility Verification</h1>
                            <p className="mt-2 text-lg text-gray-600">
                                Verify patient insurance eligibility and benefits
                            </p>
                        </div>
                    </div>

                    {/* Verification Results */}
                    {verificationResult && (
                        <div className="mb-8 space-y-6 animate-fade-in">
                            {/* Eligibility Status Card */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                        <FiInfo className="text-indigo-600" />
                                        Eligibility Status
                                    </h2>
                                    <span className={`px-4 py-2 rounded-full text-sm font-medium ${
                                        verificationResult.eligibility_status === 'eligible'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    }`}>
                                        {verificationResult.eligibility_status.toUpperCase()}
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <BenefitCard
                                        title="Individual Deductible"
                                        value={`$${verificationResult.benefits.deductible.individual.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-indigo-50 to-white"
                                    />
                                    <BenefitCard
                                        title="Remaining Deductible"
                                        value={`$${verificationResult.benefits.deductible.remaining.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-green-50 to-white"
                                    />
                                    <BenefitCard
                                        title="Coinsurance"
                                        value={`${verificationResult.benefits.coinsurance.percentage}%`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-blue-50 to-white"
                                    />
                                    <BenefitCard
                                        title="Out of Pocket Remaining"
                                        value={`$${verificationResult.benefits.out_of_pocket.remaining.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-purple-50 to-white"
                                    />
                                </div>
                            </div>

                            {/* Cost Estimate Card */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiDollarSign className="text-indigo-600" />
                                    Cost Estimate
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <BenefitCard
                                        title="Total Cost"
                                        value={`$${verificationResult.cost_estimate.total_cost.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-indigo-50 to-white"
                                    />
                                    <BenefitCard
                                        title="Insurance Pays"
                                        value={`$${verificationResult.cost_estimate.insurance_pays.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-green-50 to-white"
                                    />
                                    <BenefitCard
                                        title="Patient Responsibility"
                                        value={`$${verificationResult.cost_estimate.patient_responsibility.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-blue-50 to-white"
                                    />
                                    <BenefitCard
                                        title="Deductible Applied"
                                        value={`$${verificationResult.cost_estimate.breakdown.deductible_applied.toFixed(2)}`}
                                        icon={FiDollarSign}
                                        className="bg-gradient-to-br from-purple-50 to-white"
                                    />
                                </div>
                            </div>

                            {/* Prior Authorization Card */}
                            {verificationResult.pre_auth_required && (
                                <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                    <div className="flex items-center justify-between mb-6">
                                        <h2 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                            <FiAlertCircle className="text-indigo-600" />
                                            Prior Authorization Required
                                        </h2>
                                        <span className={`px-4 py-2 rounded-full text-sm font-medium ${
                                            verificationResult.pre_auth_status === 'approved'
                                                ? 'bg-green-100 text-green-800'
                                                : verificationResult.pre_auth_status === 'denied'
                                                ? 'bg-red-100 text-red-800'
                                                : 'bg-yellow-100 text-yellow-800'
                                        }`}>
                                            {verificationResult.pre_auth_status.toUpperCase()}
                                        </span>
                                    </div>

                                    {priorAuthResult && (
                                        <div className="mb-6 p-6 bg-indigo-50 rounded-xl">
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-2">
                                                    <p className="font-medium text-gray-900">
                                                        Prior Auth Number: {priorAuthResult.prior_auth_number}
                                                    </p>
                                                    <p className="text-sm text-gray-600">
                                                        Submitted: {new Date(priorAuthResult.submission_date).toLocaleDateString()}
                                                    </p>
                                                    <p className="text-sm text-gray-600">
                                                        Estimated Processing: {priorAuthResult.estimated_processing_time}
                                                    </p>
                                                </div>
                                                <button
                                                    onClick={() => {/* Handle status check */}}
                                                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors"
                                                >
                                                    Check Status
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    {!showPriorAuthForm && !priorAuthResult && (
                                        <div className="text-center py-6">
                                            <p className="text-gray-600 mb-4">
                                                This service requires prior authorization. Please submit a prior authorization request.
                                            </p>
                                            <button
                                                onClick={() => setShowPriorAuthForm(true)}
                                                className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                            >
                                                Submit Prior Authorization
                                            </button>
                                        </div>
                                    )}

                                    {showPriorAuthForm && (
                                        <PriorAuthForm
                                            eligibilityTransactionId={verificationResult.transaction_id}
                                            onSuccess={handlePriorAuthSuccess}
                                            onCancel={() => setShowPriorAuthForm(false)}
                                        />
                                    )}
                                </div>
                            )}

                            {/* Care Reminders Card */}
                            {verificationResult.care_reminders.length > 0 && (
                                <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                    <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                        <FiInfo className="text-indigo-600" />
                                        Care Reminders
                                    </h2>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {verificationResult.care_reminders.map(reminder => (
                                            <ReminderCard key={reminder.id} reminder={reminder} />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Reset Button */}
                            <div className="flex justify-center">
                                <button
                                    onClick={() => {
                                        setVerificationResult(null);
                                        reset();
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    }}
                                    className="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                >
                                    <FiX className="mr-2 h-5 w-5" />
                                    Start New Verification
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Verification Form */}
                    {!verificationResult && (
                        <form onSubmit={handleSubmit} className="space-y-8">
                            {/* Provider Selection */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiHome className="text-indigo-600" />
                                    Provider Information
                                </h2>
                                <div className="max-w-2xl">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Select Provider
                                    </label>
                                    <Select
                                        value={selectedProvider}
                                        onChange={handleProviderChange}
                                        loadOptions={loadProviderOptions}
                                        defaultOptions
                                        components={{ Option: ProviderOption }}
                                        placeholder="Search provider..."
                                        className={`react-select-container ${validationErrors.payer?.id ? 'border-red-500' : ''}`}
                                        classNamePrefix="react-select"
                                        isClearable
                                        required
                                    />
                                    {validationErrors.payer?.id && (
                                        <p className="mt-1 text-sm text-red-600">{validationErrors.payer.id[0]}</p>
                                    )}
                                    {selectedProvider && (
                                        <div className="mt-4 p-4 bg-indigo-50 rounded-lg">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p className="text-sm text-gray-600">Payer</p>
                                                    <p className="font-medium text-gray-900">{selectedProvider.payer_name}</p>
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600">Payer ID</p>
                                                    <p className="font-medium text-gray-900">{selectedProvider.payer_id}</p>
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600">NPI</p>
                                                    <p className="font-medium text-gray-900">{selectedProvider.npi}</p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Payer Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiCreditCard className="text-indigo-600" />
                                    Payer Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Insurance Type *
                                        </label>
                                        <select
                                            value={data.payer.insurance_type}
                                            onChange={(e) => setData('payer', { ...data.payer, insurance_type: e.target.value })}
                                            className={`w-full py-2 pl-3 pr-8 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 ${
                                                validationErrors.payer?.insurance_type ? 'border-red-500' : ''
                                            }`}
                                            required
                                        >
                                            <option value="">Select Insurance Type</option>
                                            <option value="medicare">Medicare</option>
                                            <option value="medicaid">Medicaid</option>
                                            <option value="commercial">Commercial</option>
                                            <option value="tricare">TRICARE</option>
                                            <option value="va">VA</option>
                                            <option value="workers_comp">Workers Compensation</option>
                                            <option value="other">Other</option>
                                        </select>
                                        {validationErrors.payer?.insurance_type && (
                                            <p className="mt-1 text-sm text-red-600">{validationErrors.payer.insurance_type[0]}</p>
                                        )}
                                    </div>
                                    <Input
                                        label="Payer Name"
                                        value={data.payer.name}
                                        onChange={(value) => setData('payer', { ...data.payer, name: value })}
                                        required
                                        icon={<FiCreditCard className="text-gray-400" />}
                                    />
                                    <Input
                                        label="Payer ID"
                                        value={data.payer.id}
                                        onChange={(value) => setData('payer', { ...data.payer, id: value })}
                                        required
                                        icon={<FiHash className="text-gray-400" />}
                                    />
                                </div>
                            </div>

                            {/* Patient Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiUser className="text-indigo-600" />
                                    Patient Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <Input
                                        label="First Name"
                                        value={data.patient.first_name}
                                        onChange={(value) => setData('patient', { ...data.patient, first_name: value })}
                                        required
                                        icon={<FiUser className="text-gray-400" />}
                                    />
                                    <Input
                                        label="Last Name"
                                        value={data.patient.last_name}
                                        onChange={(value) => setData('patient', { ...data.patient, last_name: value })}
                                        required
                                        icon={<FiUser className="text-gray-400" />}
                                    />
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Date of Birth
                                        </label>
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <FiCalendar className="text-gray-400" />
                                            </div>
                                            <input
                                                type="date"
                                                value={data.patient.date_of_birth}
                                                onChange={(e) => setData('patient', { ...data.patient, date_of_birth: e.target.value })}
                                                className={`pl-10 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 ${
                                                    validationErrors.patient?.date_of_birth ? 'border-red-500' : ''
                                                }`}
                                                required
                                            />
                                        </div>
                                        {validationErrors.patient?.date_of_birth && (
                                            <p className="mt-1 text-sm text-red-600">{validationErrors.patient.date_of_birth[0]}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Gender
                                        </label>
                                        <select
                                            value={data.patient.gender}
                                            onChange={(e) => setData('patient', { ...data.patient, gender: e.target.value as 'male' | 'female' | 'other' })}
                                            className={`block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 ${
                                                validationErrors.patient?.gender ? 'border-red-500' : ''
                                            }`}
                                            required
                                        >
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                        {validationErrors.patient?.gender && (
                                            <p className="mt-1 text-sm text-red-600">{validationErrors.patient.gender[0]}</p>
                                        )}
                                    </div>
                                    <Input
                                        label="Member ID"
                                        value={data.patient.member_id}
                                        onChange={(value) => setData('patient', { ...data.patient, member_id: value })}
                                        required
                                        icon={<FiUser className="text-gray-400" />}
                                    />
                                </div>
                            </div>

                            {/* Service Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiFileText className="text-indigo-600" />
                                    Service Information
                                </h2>
                                <div className="space-y-6">
                                    <div className="max-w-md">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Service Date
                                        </label>
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <FiCalendar className="text-gray-400" />
                                            </div>
                                            <input
                                                type="date"
                                                value={data.service_date}
                                                onChange={e => setData('service_date', e.target.value)}
                                                className={`pl-10 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 ${
                                                    validationErrors.service_date ? 'border-red-500' : ''
                                                }`}
                                                required
                                            />
                                        </div>
                                        {validationErrors.service_date && (
                                            <p className="mt-1 text-sm text-red-600">{validationErrors.service_date[0]}</p>
                                        )}
                                    </div>

                                    <div>
                                        <div className="flex items-center justify-between mb-4">
                                            <label className="block text-sm font-medium text-gray-700">
                                                Service Codes
                                            </label>
                                            <button
                                                type="button"
                                                onClick={addServiceCode}
                                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors"
                                            >
                                                <FiPlus className="w-4 h-4 mr-2" />
                                                Add Code
                                            </button>
                                        </div>
                                        {validationErrors.service_codes && (
                                            <p className="mb-2 text-sm text-red-600">{validationErrors.service_codes[0]}</p>
                                        )}
                                        <div className="space-y-3">
                                            {serviceCodes.map((code, index) => (
                                                <div key={index} className="flex gap-4 items-start">
                                                    <div className="flex-grow">
                                                        <input
                                                            type="text"
                                                            value={code.code}
                                                            onChange={e => updateServiceCode(index, 'code', e.target.value)}
                                                            placeholder="Enter service code"
                                                            className={`block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 ${
                                                                validationErrors.service_codes ? 'border-red-500' : ''
                                                            }`}
                                                            required
                                                        />
                                                    </div>
                                                    <div className="w-40">
                                                        <select
                                                            value={code.type}
                                                            onChange={e => updateServiceCode(index, 'type', e.target.value as 'cpt' | 'hcpcs')}
                                                            className="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        >
                                                            <option value="cpt">CPT</option>
                                                            <option value="hcpcs">HCPCS</option>
                                                        </select>
                                                    </div>
                                                    {index > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeServiceCode(index)}
                                                            className="inline-flex items-center p-2 border border-transparent rounded-lg text-red-700 bg-red-100 hover:bg-red-200 transition-colors"
                                                        >
                                                            <FiX className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing || isVerifying || isSubmitting}
                                    className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-colors"
                                >
                                    {isVerifying || isSubmitting ? (
                                        <>
                                            <FiClock className="animate-spin -ml-1 mr-2 h-5 w-5" />
                                            Verifying...
                                        </>
                                    ) : (
                                        <>
                                            <FiCheck className="-ml-1 mr-2 h-5 w-5" />
                                            Verify Eligibility
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </MainLayout>
    );
};

// Add some custom styles for animations
const styles = `
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-out;
}
`;

// Add styles to the document
const styleSheet = document.createElement("style");
styleSheet.innerText = styles;
document.head.appendChild(styleSheet);

export default EligibilityPage;
