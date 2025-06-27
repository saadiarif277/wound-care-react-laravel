import React, { useState, useEffect } from 'react';
import { useForm, Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import Alert from '@/Components/Alert/Alert';
import AsyncSelect from 'react-select/async';
import { FiCheck, FiAlertTriangle, FiX, FiLoader, FiDownload, FiClock } from 'react-icons/fi';
import { api, handleApiResponse } from '@/lib/api';

interface Provider {
    value: string;
    label: string;
    payer_id: string;
    payer_name: string;
    npi: string;
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

interface ServiceCode {
    code: string;
    type: 'hcpcs' | 'cpt' | 'icd10';
}

interface EligibilityResponse {
    eligible: boolean;
    coverage_details: {
        active: boolean;
        copay?: number;
        deductible?: number;
        out_of_pocket_max?: number;
        benefits?: string[];
    };
    prior_auth_required: boolean;
    limitations?: string[];
    effective_date?: string;
    termination_date?: string;
    member_id: string;
    payer_id: string;
}

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
    const [eligibilityHistory, setEligibilityHistory] = useState<any[]>([]);

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

    // Load provider options from API
    const loadProviderOptions = async (inputValue: string): Promise<Provider[]> => {
        try {
            if (inputValue.length < 2) return [];

            const results = await api.providers.search(inputValue);

            return results.map((provider: any) => ({
                value: provider.id,
                label: `${provider.name} - ${provider.facility?.name || 'Unknown Facility'}`,
                payer_id: provider.primary_payer_id || '',
                payer_name: provider.primary_payer_name || '',
                npi: provider.npi || ''
            }));
        } catch (error) {
            console.error('Error loading provider options:', error);
            return [];
        }
    };

    // Load eligibility history on component mount
    useEffect(() => {
        const loadHistory = async () => {
            try {
                const history = await api.eligibility.getHistory();
                setEligibilityHistory(history);
            } catch (error) {
                console.error('Error loading eligibility history:', error);
            }
        };

        loadHistory();
    }, []);

    const handleProviderSelection = (selectedProvider: Provider | null) => {
        setSelectedProvider(selectedProvider);
        if (selectedProvider) {
            setData('payer', {
                name: selectedProvider.payer_name,
                id: selectedProvider.payer_id,
                insurance_type: 'commercial', // Default, can be updated
            });
        }
    };

    const addServiceCode = () => {
        const newCodes = [...serviceCodes, { code: '', type: 'hcpcs' as const }];
        setServiceCodes(newCodes);
        setData('service_codes', newCodes);
    };

    const removeServiceCode = (index: number) => {
        const newCodes = serviceCodes.filter((_, i) => i !== index);
        setServiceCodes(newCodes);
        setData('service_codes', newCodes);
    };

    const updateServiceCode = (index: number, field: keyof ServiceCode, value: string) => {
        const newCodes = [...serviceCodes];
        newCodes[index] = { ...newCodes[index], [field]: value };
        setServiceCodes(newCodes);
        setData('service_codes', newCodes);
    };

    const handleEligibilityCheck = async () => {
        setIsVerifying(true);
        setValidationErrors({});

        try {
            // Validate required fields
            const validation = validateForm();
            if (!validation.isValid) {
                setValidationErrors(validation.errors);
                setIsVerifying(false);
                return;
            }

            const response = await api.eligibility.checkEligibility(data);
            setVerificationResult(response);
            setStep(2);
        } catch (error) {
            console.error('Eligibility check failed:', error);
            setValidationErrors({
                patient: { first_name: [error instanceof Error ? error.message : 'Eligibility check failed'] }
            });
        } finally {
            setIsVerifying(false);
        }
    };

    const validateForm = () => {
        const errors: ValidationErrors = {};
        let isValid = true;

        // Patient validation
        if (!data.patient.first_name.trim()) {
            errors.patient = { ...errors.patient, first_name: ['First name is required'] };
            isValid = false;
        }
        if (!data.patient.last_name.trim()) {
            errors.patient = { ...errors.patient, last_name: ['Last name is required'] };
            isValid = false;
        }
        if (!data.patient.date_of_birth) {
            errors.patient = { ...errors.patient, date_of_birth: ['Date of birth is required'] };
            isValid = false;
        }
        if (!data.patient.member_id.trim()) {
            errors.patient = { ...errors.patient, member_id: ['Member ID is required'] };
            isValid = false;
        }

        // Payer validation
        if (!data.payer.name.trim()) {
            errors.payer = { ...errors.payer, name: ['Payer name is required'] };
            isValid = false;
        }

        // Service date validation
        if (!data.service_date) {
            errors.service_date = ['Service date is required'];
            isValid = false;
        }

        // Service codes validation
        const validCodes = serviceCodes.filter(code => code.code.trim());
        if (validCodes.length === 0) {
            errors.service_codes = ['At least one service code is required'];
            isValid = false;
        }

        return { isValid, errors };
    };

    const handlePriorAuthRequest = async () => {
        if (!verificationResult) return;

        setIsSubmitting(true);
        try {
            // This would typically submit a prior auth request
            // For now, we'll just simulate success
            setPriorAuthResult({
                request_id: 'PA-' + Date.now(),
                status: 'submitted',
                estimated_processing_time: '3-5 business days'
            });
            setShowPriorAuthForm(false);
        } catch (error) {
            console.error('Prior auth request failed:', error);
        } finally {
            setIsSubmitting(false);
        }
    };

    const resetForm = () => {
        setStep(1);
        setVerificationResult(null);
        setPriorAuthResult(null);
        setShowPriorAuthForm(false);
        setSelectedProvider(null);
        setServiceCodes([{ code: '', type: 'hcpcs' }]);
        setValidationErrors({});
        reset();
    };

    const getFieldError = (field: string, subfield?: string) => {
        if (subfield) {
            return validationErrors[field as keyof ValidationErrors]?.[subfield as any]?.[0];
        }
        return validationErrors[field as keyof ValidationErrors] as string[] | undefined;
    };

    return (
        <MainLayout>
            <Head title="Eligibility Verification" />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Eligibility Verification</h1>
                        <p className="text-gray-600">Verify patient insurance coverage and benefits</p>
                    </div>

                    {step > 1 && (
                        <button
                            onClick={resetForm}
                            className="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600"
                        >
                            New Verification
                        </button>
                    )}
                </div>

                {/* Step Indicator */}
                <div className="flex items-center space-x-4 bg-white p-4 rounded-lg shadow-sm border">
                    <div className={`flex items-center ${step >= 1 ? 'text-blue-600' : 'text-gray-400'}`}>
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                            step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200'
                        }`}>
                            1
                        </div>
                        <span className="ml-2 font-medium">Patient Info</span>
                    </div>
                    <div className="w-8 h-0.5 bg-gray-300"></div>
                    <div className={`flex items-center ${step >= 2 ? 'text-blue-600' : 'text-gray-400'}`}>
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                            step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200'
                        }`}>
                            2
                        </div>
                        <span className="ml-2 font-medium">Results</span>
                    </div>
                </div>

                {step === 1 && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h2 className="text-lg font-semibold mb-6">Patient & Insurance Information</h2>

                        <div className="space-y-6">
                            {/* Provider Selection */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Provider/Facility
                                </label>
                                <AsyncSelect
                                    loadOptions={loadProviderOptions}
                                    onChange={handleProviderSelection}
                                    value={selectedProvider}
                                    placeholder="Search for provider or facility..."
                                    className="react-select-container"
                                    classNamePrefix="react-select"
                                    isClearable
                                />
                                <p className="text-sm text-gray-500 mt-1">
                                    This will auto-populate payer information
                                </p>
                            </div>

                            {/* Patient Information */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        First Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.patient.first_name}
                                        onChange={(e) => setData('patient', { ...data.patient, first_name: e.target.value })}
                                        className={`w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 ${
                                            getFieldError('patient', 'first_name') ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                        placeholder="Patient's first name"
                                    />
                                    {getFieldError('patient', 'first_name') && (
                                        <p className="text-red-500 text-sm mt-1">{getFieldError('patient', 'first_name')}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Last Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.patient.last_name}
                                        onChange={(e) => setData('patient', { ...data.patient, last_name: e.target.value })}
                                        className={`w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 ${
                                            getFieldError('patient', 'last_name') ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                        placeholder="Patient's last name"
                                    />
                                    {getFieldError('patient', 'last_name') && (
                                        <p className="text-red-500 text-sm mt-1">{getFieldError('patient', 'last_name')}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Date of Birth *
                                    </label>
                                    <input
                                        type="date"
                                        value={data.patient.date_of_birth}
                                        onChange={(e) => setData('patient', { ...data.patient, date_of_birth: e.target.value })}
                                        className={`w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 ${
                                            getFieldError('patient', 'date_of_birth') ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    />
                                    {getFieldError('patient', 'date_of_birth') && (
                                        <p className="text-red-500 text-sm mt-1">{getFieldError('patient', 'date_of_birth')}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Gender
                                    </label>
                                    <select
                                        value={data.patient.gender}
                                        onChange={(e) => setData('patient', { ...data.patient, gender: e.target.value as 'male' | 'female' | 'other' })}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Member ID *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.patient.member_id}
                                        onChange={(e) => setData('patient', { ...data.patient, member_id: e.target.value })}
                                        className={`w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 ${
                                            getFieldError('patient', 'member_id') ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                        placeholder="Insurance member ID"
                                    />
                                    {getFieldError('patient', 'member_id') && (
                                        <p className="text-red-500 text-sm mt-1">{getFieldError('patient', 'member_id')}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Service Date *
                                    </label>
                                    <input
                                        type="date"
                                        value={data.service_date}
                                        onChange={(e) => setData('service_date', e.target.value)}
                                        className={`w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 ${
                                            getFieldError('service_date') ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    />
                                    {getFieldError('service_date') && (
                                        <p className="text-red-500 text-sm mt-1">{getFieldError('service_date')?.[0]}</p>
                                    )}
                                </div>
                            </div>

                            {/* Payer Information */}
                            <div className="border-t pt-6">
                                <h3 className="text-md font-semibold mb-4">Insurance/Payer Information</h3>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Payer Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.payer.name}
                                            onChange={(e) => setData('payer', { ...data.payer, name: e.target.value })}
                                            className={`w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500 ${
                                                getFieldError('payer', 'name') ? 'border-red-500' : 'border-gray-300'
                                            }`}
                                            placeholder="e.g., Aetna, BCBS, Medicare"
                                        />
                                        {getFieldError('payer', 'name') && (
                                            <p className="text-red-500 text-sm mt-1">{getFieldError('payer', 'name')}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Payer ID
                                        </label>
                                        <input
                                            type="text"
                                            value={data.payer.id}
                                            onChange={(e) => setData('payer', { ...data.payer, id: e.target.value })}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Payer identifier"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Insurance Type
                                        </label>
                                        <select
                                            value={data.payer.insurance_type}
                                            onChange={(e) => setData('payer', { ...data.payer, insurance_type: e.target.value })}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="">Select type</option>
                                            <option value="commercial">Commercial</option>
                                            <option value="medicare">Medicare</option>
                                            <option value="medicaid">Medicaid</option>
                                            <option value="medicare_advantage">Medicare Advantage</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {/* Service Codes */}
                            <div className="border-t pt-6">
                                <div className="flex justify-between items-center mb-4">
                                    <h3 className="text-md font-semibold">Service Codes</h3>
                                    <button
                                        type="button"
                                        onClick={addServiceCode}
                                        className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700"
                                    >
                                        Add Code
                                    </button>
                                </div>

                                {serviceCodes.map((serviceCode, index) => (
                                    <div key={index} className="flex gap-4 mb-3">
                                        <div className="flex-1">
                                            <input
                                                type="text"
                                                value={serviceCode.code}
                                                onChange={(e) => updateServiceCode(index, 'code', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="Service code"
                                            />
                                        </div>
                                        <div className="w-32">
                                            <select
                                                value={serviceCode.type}
                                                onChange={(e) => updateServiceCode(index, 'type', e.target.value as 'hcpcs' | 'cpt' | 'icd10')}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            >
                                                <option value="hcpcs">HCPCS</option>
                                                <option value="cpt">CPT</option>
                                                <option value="icd10">ICD-10</option>
                                            </select>
                                        </div>
                                        {serviceCodes.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeServiceCode(index)}
                                                className="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                ))}
                                {getFieldError('service_codes') && (
                                    <p className="text-red-500 text-sm mt-1">{getFieldError('service_codes')?.[0]}</p>
                                )}
                            </div>

                            {/* Submit Button */}
                            <div className="flex justify-end">
                                <button
                                    onClick={handleEligibilityCheck}
                                    disabled={isVerifying}
                                    className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                                >
                                    {isVerifying ? (
                                        <>
                                            <FiLoader className="animate-spin mr-2" />
                                            Verifying...
                                        </>
                                    ) : (
                                        'Check Eligibility'
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {step === 2 && verificationResult && (
                    <div className="space-y-6">
                        {/* Eligibility Results */}
                        <div className="bg-white rounded-lg shadow-sm border">
                            <div className="p-6 border-b">
                                <div className="flex items-center">
                                    {verificationResult.eligible ? (
                                        <FiCheck className="h-6 w-6 text-green-500 mr-3" />
                                    ) : (
                                        <FiX className="h-6 w-6 text-red-500 mr-3" />
                                    )}
                                    <h2 className="text-lg font-semibold">
                                        Eligibility Status: {verificationResult.eligible ? 'Eligible' : 'Not Eligible'}
                                    </h2>
                                </div>
                            </div>

                            <div className="p-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 className="font-medium text-gray-900 mb-3">Coverage Details</h3>
                                        <div className="space-y-2 text-sm">
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">Status:</span>
                                                <span className={verificationResult.coverage_details.active ? 'text-green-600' : 'text-red-600'}>
                                                    {verificationResult.coverage_details.active ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                            {verificationResult.coverage_details.copay && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">Copay:</span>
                                                    <span>${verificationResult.coverage_details.copay}</span>
                                                </div>
                                            )}
                                            {verificationResult.coverage_details.deductible && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">Deductible:</span>
                                                    <span>${verificationResult.coverage_details.deductible}</span>
                                                </div>
                                            )}
                                            {verificationResult.effective_date && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">Effective Date:</span>
                                                    <span>{new Date(verificationResult.effective_date).toLocaleDateString()}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="font-medium text-gray-900 mb-3">Authorization</h3>
                                        <div className="space-y-2 text-sm">
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">Prior Auth Required:</span>
                                                <span className={verificationResult.prior_auth_required ? 'text-yellow-600' : 'text-green-600'}>
                                                    {verificationResult.prior_auth_required ? 'Yes' : 'No'}
                                                </span>
                                            </div>
                                            {verificationResult.limitations && verificationResult.limitations.length > 0 && (
                                                <div>
                                                    <span className="text-gray-600">Limitations:</span>
                                                    <ul className="list-disc list-inside mt-1">
                                                        {verificationResult.limitations.map((limitation, index) => (
                                                            <li key={index} className="text-gray-700">{limitation}</li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {verificationResult.prior_auth_required && !priorAuthResult && (
                                    <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                                        <div className="flex items-center">
                                            <FiAlertTriangle className="h-5 w-5 text-yellow-600 mr-2" />
                                            <p className="text-yellow-800">
                                                Prior authorization is required for this service. Would you like to submit a request?
                                            </p>
                                        </div>
                                        <div className="mt-3">
                                            <button
                                                onClick={() => setShowPriorAuthForm(true)}
                                                className="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700"
                                            >
                                                Request Prior Authorization
                                            </button>
                                        </div>
                                    </div>
                                )}

                                {priorAuthResult && (
                                    <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                                        <div className="flex items-center">
                                            <FiCheck className="h-5 w-5 text-green-600 mr-2" />
                                            <p className="text-green-800">
                                                Prior authorization request submitted successfully.
                                            </p>
                                        </div>
                                        <div className="mt-2 text-sm text-green-700">
                                            <p>Request ID: {priorAuthResult.request_id}</p>
                                            <p>Estimated processing time: {priorAuthResult.estimated_processing_time}</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Prior Auth Form Modal */}
                        {showPriorAuthForm && (
                            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                                <div className="bg-white rounded-lg max-w-md w-full p-6">
                                    <h3 className="text-lg font-semibold mb-4">Request Prior Authorization</h3>
                                    <p className="text-gray-600 mb-4">
                                        This will submit a prior authorization request to the payer. The request typically takes 3-5 business days to process.
                                    </p>
                                    <div className="flex justify-end space-x-3">
                                        <button
                                            onClick={() => setShowPriorAuthForm(false)}
                                            className="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={handlePriorAuthRequest}
                                            disabled={isSubmitting}
                                            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            {isSubmitting ? 'Submitting...' : 'Submit Request'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Recent Eligibility History */}
                {eligibilityHistory.length > 0 && (
                    <div className="bg-white rounded-lg shadow-sm border">
                        <div className="p-6 border-b">
                            <h3 className="text-lg font-semibold">Recent Eligibility Checks</h3>
                        </div>
                        <div className="divide-y">
                            {eligibilityHistory.slice(0, 5).map((item: any, index) => (
                                <div key={index} className="p-4 hover:bg-gray-50">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <p className="font-medium">{item.patient_name}</p>
                                            <p className="text-sm text-gray-600">{item.payer_name}</p>
                                            <p className="text-xs text-gray-500">{new Date(item.created_at).toLocaleString()}</p>
                                        </div>
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            item.eligible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        }`}>
                                            {item.eligible ? 'Eligible' : 'Not Eligible'}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </MainLayout>
    );
};

export default EligibilityPage;
