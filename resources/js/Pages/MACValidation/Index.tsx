import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
    FiUser, FiCalendar, FiFileText, FiCheck, FiAlertCircle,
    FiClock, FiMapPin, FiInfo, FiPlus, FiX, FiSearch,
    FiShield, FiAlertTriangle, FiCheckCircle
} from 'react-icons/fi';
import { toast } from 'react-hot-toast';
import { Toaster } from 'react-hot-toast';

interface ValidationRule {
    rule_code: string;
    policy_id: string;
    applies_to_codes: string[];
    rule_type: 'coverage' | 'documentation' | 'frequency';
    severity: 'error' | 'warning' | 'info';
    message: string;
    resolution_guidance: string;
    policy_reference: string;
}

interface ValidationResult {
    status: 'passed' | 'passed_with_warnings' | 'failed';
    mac_jurisdiction: string;
    carrier: string;
    rules_checked: ValidationRule[];
    documentation_requirements: string[];
    timestamp: string;
}

interface FormData {
    patient: {
        zip_code: string;
        age: number;
        gender: 'male' | 'female' | 'other';
    };
    diagnoses: {
        primary: string;
        secondary: string[];
    };
    wound: {
        type: string;
        location: string;
        size: string;
        duration_weeks: number;
        depth: string;
        tissue_type: string;
        infection_status: boolean;
        exposed_structures: boolean;
    };
    prior_care: {
        treatments: string[];
        duration_weeks: number;
    };
    lab_values: {
        hba1c?: number;
        abi?: number;
        albumin?: number;
    };
    service: {
        codes: string[];
        date: string;
    };
    provider: {
        specialty: string;
        facility_type: string;
        zip_code: string;
    };
}

// Dummy data for MAC jurisdictions
const macJurisdictions = [
    { id: 'J1', name: 'Noridian Healthcare Solutions', states: ['CA', 'NV', 'AZ', 'HI'] },
    { id: 'J2', name: 'Novitas Solutions', states: ['TX', 'OK', 'AR', 'LA'] },
    { id: 'J3', name: 'Palmetto GBA', states: ['NC', 'SC', 'VA', 'WV'] },
    { id: 'J4', name: 'WPS Government Health Administrators', states: ['MI', 'IN', 'OH'] },
];

// Dummy validation rules
const dummyRules: ValidationRule[] = [
    {
        rule_code: 'DFU-001',
        policy_id: 'LCD-12345',
        applies_to_codes: ['A9271', 'A9272'],
        rule_type: 'coverage',
        severity: 'error',
        message: 'Diabetic Foot Ulcer (DFU) requires minimum 4 weeks of conservative care',
        resolution_guidance: 'Document 4 weeks of conservative care including offloading and wound care',
        policy_reference: 'LCD-12345, Section 2.1'
    },
    {
        rule_code: 'DOC-001',
        policy_id: 'LCD-12345',
        applies_to_codes: ['A9271', 'A9272'],
        rule_type: 'documentation',
        severity: 'warning',
        message: 'Wound measurements must be documented weekly',
        resolution_guidance: 'Include weekly wound measurements in documentation',
        policy_reference: 'LCD-12345, Section 3.2'
    }
];

const MACValidationPage = () => {
    const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);
    const [isValidating, setIsValidating] = useState(false);
    const [selectedJurisdiction, setSelectedJurisdiction] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
        patient: {
            zip_code: '',
            age: 0,
            gender: 'male'
        },
        diagnoses: {
            primary: '',
            secondary: []
        },
        wound: {
            type: '',
            location: '',
            size: '',
            duration_weeks: 0,
            depth: '',
            tissue_type: '',
            infection_status: false,
            exposed_structures: false
        },
        prior_care: {
            treatments: [],
            duration_weeks: 0
        },
        lab_values: {},
        service: {
            codes: [],
            date: ''
        },
        provider: {
            specialty: '',
            facility_type: '',
            zip_code: ''
        }
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsValidating(true);

        try {
            // Simulate API call with dummy data
            await new Promise(resolve => setTimeout(resolve, 1500));

            const dummyResult: ValidationResult = {
                status: 'passed_with_warnings',
                mac_jurisdiction: 'Noridian Healthcare Solutions',
                carrier: 'Noridian',
                rules_checked: dummyRules,
                documentation_requirements: [
                    'Weekly wound measurements',
                    'Conservative care documentation',
                    'Offloading documentation'
                ],
                timestamp: new Date().toISOString()
            };

            setValidationResult(dummyResult);
            toast.success('MAC Validation completed');
        } catch (error) {
            toast.error('Validation failed. Please try again.');
        } finally {
            setIsValidating(false);
        }
    };

    const ValidationStatusBadge = ({ status }: { status: ValidationResult['status'] }) => {
        const statusConfig = {
            passed: { color: 'bg-green-100 text-green-800', icon: FiCheckCircle },
            passed_with_warnings: { color: 'bg-yellow-100 text-yellow-800', icon: FiAlertTriangle },
            failed: { color: 'bg-red-100 text-red-800', icon: FiAlertCircle }
        };

        const config = statusConfig[status];
        const Icon = config.icon;

        return (
            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${config.color}`}>
                <Icon className="w-4 h-4 mr-1" />
                {status.replace(/_/g, ' ').toUpperCase()}
            </span>
        );
    };

    const RuleCard = ({ rule }: { rule: ValidationRule }) => {
        const severityConfig = {
            error: { color: 'border-red-500 bg-red-50', icon: FiAlertCircle },
            warning: { color: 'border-yellow-500 bg-yellow-50', icon: FiAlertTriangle },
            info: { color: 'border-blue-500 bg-blue-50', icon: FiInfo }
        };

        const config = severityConfig[rule.severity];
        const Icon = config.icon;

        return (
            <div className={`border-l-4 ${config.color} p-4 rounded-r-lg`}>
                <div className="flex items-start gap-3">
                    <Icon className="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <div>
                        <h4 className="font-medium text-gray-900">{rule.message}</h4>
                        <p className="mt-1 text-sm text-gray-600">{rule.resolution_guidance}</p>
                        <p className="mt-2 text-xs text-gray-500">Policy Reference: {rule.policy_reference}</p>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <MainLayout>
            <Head title="MAC Validation" />
            <Toaster position="top-right" />

            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="px-4 py-6 sm:px-0">
                    <div className="flex justify-between items-center mb-8">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">MAC Validation</h1>
                            <p className="mt-2 text-lg text-gray-600">
                                Validate orders against MAC-specific coverage policies
                            </p>
                        </div>
                    </div>

                    {/* Validation Results */}
                    {validationResult && (
                        <div className="mb-8 space-y-6 animate-fade-in">
                            {/* Validation Status Card */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                        <FiShield className="text-indigo-600" />
                                        Validation Results
                                    </h2>
                                    <ValidationStatusBadge status={validationResult.status} />
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <p className="text-sm text-gray-600">MAC Jurisdiction</p>
                                        <p className="font-medium text-gray-900">{validationResult.mac_jurisdiction}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Carrier</p>
                                        <p className="font-medium text-gray-900">{validationResult.carrier}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Validation Time</p>
                                        <p className="font-medium text-gray-900">
                                            {new Date(validationResult.timestamp).toLocaleString()}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Rules Checked */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiFileText className="text-indigo-600" />
                                    Rules Checked
                                </h2>
                                <div className="space-y-4">
                                    {validationResult.rules_checked.map((rule, index) => (
                                        <RuleCard key={index} rule={rule} />
                                    ))}
                                </div>
                            </div>

                            {/* Documentation Requirements */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiFileText className="text-indigo-600" />
                                    Documentation Requirements
                                </h2>
                                <ul className="space-y-3">
                                    {validationResult.documentation_requirements.map((req, index) => (
                                        <li key={index} className="flex items-start gap-3">
                                            <FiCheck className="w-5 h-5 text-green-500 mt-0.5" />
                                            <span className="text-gray-700">{req}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Reset Button */}
                            <div className="flex justify-center">
                                <button
                                    onClick={() => {
                                        setValidationResult(null);
                                        reset();
                                    }}
                                    className="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                                >
                                    <FiX className="mr-2 h-5 w-5" />
                                    Start New Validation
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Validation Form */}
                    {!validationResult && (
                        <form onSubmit={handleSubmit} className="space-y-8">
                            {/* Provider Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiMapPin className="text-indigo-600" />
                                    Provider Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Provider ZIP Code *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.provider.zip_code}
                                            onChange={e => setData('provider', { ...data.provider, zip_code: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter ZIP code"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Provider Specialty *
                                        </label>
                                        <select
                                            value={data.provider.specialty}
                                            onChange={e => setData('provider', { ...data.provider, specialty: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Specialty</option>
                                            <option value="wound_care">Wound Care</option>
                                            <option value="podiatry">Podiatry</option>
                                            <option value="dermatology">Dermatology</option>
                                            <option value="vascular">Vascular</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Facility Type *
                                        </label>
                                        <select
                                            value={data.provider.facility_type}
                                            onChange={e => setData('provider', { ...data.provider, facility_type: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Facility Type</option>
                                            <option value="hospital">Hospital</option>
                                            <option value="clinic">Clinic</option>
                                            <option value="wound_center">Wound Center</option>
                                            <option value="private_practice">Private Practice</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {/* Patient Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiUser className="text-indigo-600" />
                                    Patient Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Patient ZIP Code *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.patient.zip_code}
                                            onChange={e => setData('patient', { ...data.patient, zip_code: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter ZIP code"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Age *
                                        </label>
                                        <input
                                            type="number"
                                            value={data.patient.age}
                                            onChange={e => setData('patient', { ...data.patient, age: parseInt(e.target.value) })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            min="0"
                                            max="120"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Gender *
                                        </label>
                                        <select
                                            value={data.patient.gender}
                                            onChange={e => setData('patient', { ...data.patient, gender: e.target.value as 'male' | 'female' | 'other' })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {/* Wound Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiInfo className="text-indigo-600" />
                                    Wound Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Type *
                                        </label>
                                        <select
                                            value={data.wound.type}
                                            onChange={e => setData('wound', { ...data.wound, type: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Wound Type</option>
                                            <option value="dfu">Diabetic Foot Ulcer</option>
                                            <option value="vlu">Venous Leg Ulcer</option>
                                            <option value="pressure">Pressure Ulcer</option>
                                            <option value="surgical">Surgical Wound</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Location *
                                        </label>
                                        <select
                                            value={data.wound.location}
                                            onChange={e => setData('wound', { ...data.wound, location: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Location</option>
                                            <option value="foot">Foot</option>
                                            <option value="ankle">Ankle</option>
                                            <option value="leg">Leg</option>
                                            <option value="sacrum">Sacrum</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Size (cm²) *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.wound.size}
                                            onChange={e => setData('wound', { ...data.wound, size: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="e.g., 2x3"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Duration (weeks) *
                                        </label>
                                        <input
                                            type="number"
                                            value={data.wound.duration_weeks}
                                            onChange={e => setData('wound', { ...data.wound, duration_weeks: parseInt(e.target.value) })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            min="0"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Service Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiFileText className="text-indigo-600" />
                                    Service Information
                                </h2>
                                <div className="space-y-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Service Date *
                                        </label>
                                        <input
                                            type="date"
                                            value={data.service.date}
                                            onChange={e => setData('service', { ...data.service, date: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Service Codes *
                                        </label>
                                        <div className="space-y-2">
                                            {data.service.codes.map((code, index) => (
                                                <div key={index} className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={code}
                                                        onChange={e => {
                                                            const newCodes = [...data.service.codes];
                                                            newCodes[index] = e.target.value;
                                                            setData('service', { ...data.service, codes: newCodes });
                                                        }}
                                                        className="flex-1 py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="Enter HCPCS/CPT code"
                                                        required
                                                    />
                                                    {index > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                const newCodes = data.service.codes.filter((_, i) => i !== index);
                                                                setData('service', { ...data.service, codes: newCodes });
                                                            }}
                                                            className="p-2 text-red-600 hover:text-red-800"
                                                        >
                                                            <FiX className="w-5 h-5" />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                            <button
                                                type="button"
                                                onClick={() => setData('service', { ...data.service, codes: [...data.service.codes, ''] })}
                                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                            >
                                                <FiPlus className="w-4 h-4 mr-2" />
                                                Add Code
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing || isValidating}
                                    className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-colors"
                                >
                                    {isValidating ? (
                                        <>
                                            <FiClock className="animate-spin -ml-1 mr-2 h-5 w-5" />
                                            Validating...
                                        </>
                                    ) : (
                                        <>
                                            <FiShield className="-ml-1 mr-2 h-5 w-5" />
                                            Validate Order
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

// Add animation styles
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

export default MACValidationPage;
