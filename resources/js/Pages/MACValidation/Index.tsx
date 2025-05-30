import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
    FiUser, FiCalendar, FiFileText, FiCheck, FiAlertCircle,
    FiClock, FiMapPin, FiInfo, FiPlus, FiX, FiSearch,
    FiShield, FiAlertTriangle, FiCheckCircle, FiZap, FiSettings
} from 'react-icons/fi';
import { toast } from 'react-hot-toast';
import { Toaster } from 'react-hot-toast';

interface QuickValidationResult {
    success: boolean;
    data: {
        validation_id: string;
        status: 'passed' | 'passed_with_warnings' | 'failed';
        mac_contractor: string;
        mac_jurisdiction: string;
        mac_phone?: string;
        mac_website?: string;
        mac_data_source?: 'cms_api' | 'cms_lcd_data' | 'fallback_mapping';
        basic_coverage: boolean;
        quick_issues: string[];
        estimated_time_saved: string;
        recommendation: 'proceed' | 'review_required' | 'full_validation_needed';
        performance_summary?: {
            total_response_time_ms: number;
            cms_api_calls: number;
            policies_analyzed: number;
            cache_efficiency: string;
            data_freshness: string;
        };
        cms_insights: {
            data_source?: 'cms_api' | 'cached';
            state_searched?: string;
            api_response_time?: string;
            lcds_found: number;
            ncds_found: number;
            articles_found: number;
            service_coverage: {
                code: string;
                status: 'likely_covered' | 'needs_review' | 'not_covered' | 'invalid';
                description?: string;
                requires_prior_auth: boolean;
                coverage_notes: string[];
                frequency_limit?: string;
                lcd_matches?: number;
                ncd_matches?: number;
            }[];
            common_modifiers: Record<string, string>;
            key_documentation: string[];
            relevant_lcds: {
                documentTitle: string;
                documentId?: string;
                contractor?: string;
                effectiveDate?: string;
                summary?: string;
            }[];
            relevant_ncds: {
                documentTitle: string;
                documentId?: string;
                ncdNumber?: string;
                effectiveDate?: string;
                summary?: string;
            }[];
            performance_metrics?: {
                total_api_calls: number;
                response_time_ms: number;
                policies_analyzed: number;
                codes_analyzed: number;
            };
        };
    };
}

interface ThoroughValidationResult {
    success: boolean;
    data: {
        validation_id: string;
        status: 'passed' | 'passed_with_warnings' | 'failed' | 'requires_review';
        compliance_score: number;
        confidence_level: string;
        mac_contractor: string;
        mac_jurisdiction: string;
        mac_region: string;
        mac_phone?: string;
        mac_website?: string;
        addressing_method: string;
        validation_results: {
            overall_status: string;
            validations: Array<{
                rule: string;
                status: 'passed' | 'failed' | 'warning';
                message: string;
                cms_reference?: string;
            }>;
            validation_summary: {
                total_checks: number;
                passed: number;
                warnings: number;
                failed: number;
            };
        };
        cms_compliance: {
            lcds_found: number;
            ncds_found: number;
            technology_assessments: number;
            nca_tracking_items: number;
            coverage_policies: string[];
            coverage_strength: string;
            evidence_level: string;
        };
        detailed_coverage_analysis: {
            service_coverage_summary: any[];
            policy_coverage_strength: string;
            evidence_level: string;
            technology_assessment_insights: any[];
            nca_status_insights: any[];
            coverage_gaps: string[];
            coverage_opportunities: string[];
        };
        clinical_requirements: string[];
        documentation_requirements: string[];
        prior_authorization_analysis: {
            codes_requiring_auth: Array<{
                code: string;
                description: string;
                urgency: string;
                estimated_processing_time: string;
            }>;
            total_codes_requiring_auth: number;
            recommendations: string[];
        };
        frequency_limitations: Array<{
            code: string;
            frequency_limit: string;
            description: string;
        }>;
        billing_considerations: string[];
        reimbursement_analysis: {
            total_estimated_reimbursement: number;
            code_estimates: Record<string, any>;
            reimbursement_factors: {
                geographic_adjustment: number;
                facility_vs_non_facility: string;
                estimated_patient_responsibility: number;
            };
            disclaimer: string;
        };
        reimbursement_risk: 'low' | 'medium' | 'high';
        risk_factors: string[];
        recommendations: string[];
        quality_measures: string[];
        performance_metrics: {
            total_processing_time_ms: number;
            cms_api_calls: number;
            policies_analyzed: number;
            data_sources_consulted: number;
            cache_efficiency: string;
        };
        validated_at: string;
        data_freshness: string;
        analysis_depth: string;
    };
}

interface QuickFormData {
    patient_zip: string;
    service_codes: string[];
    wound_type: string;
    service_date: string;
}

interface ThoroughFormData {
    patient: {
        address: string;
        city: string;
        state: string;
        zip_code: string;
        age: number;
        gender: 'male' | 'female' | 'other';
    };
    provider: {
        facility_name: string;
        address: string;
        city: string;
        state: string;
        zip_code: string;
        npi: string;
        specialty: string;
        facility_type: string;
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
}

const MACValidationPage = () => {
    const [validationMode, setValidationMode] = useState<'quick' | 'thorough'>('quick');
    const [quickResult, setQuickResult] = useState<QuickValidationResult['data'] | null>(null);
    const [thoroughResult, setThoroughResult] = useState<ThoroughValidationResult['data'] | null>(null);
    const [isValidating, setIsValidating] = useState(false);

    // Helper functions for safe array/object access
    const safeArray = (arr: any): any[] => Array.isArray(arr) ? arr : [];
    const hasItems = (arr: any): boolean => Array.isArray(arr) && arr.length > 0;

    // Quick Check Form
    const { data: quickData, setData: setQuickData, processing: quickProcessing, errors: quickErrors, reset: resetQuick } = useForm<QuickFormData>({
        patient_zip: '',
        service_codes: [''],
        wound_type: '',
        service_date: ''
    });

    // Thorough Validation Form
    const { data: thoroughData, setData: setThoroughData, post: postThorough, processing: thoroughProcessing, errors: thoroughErrors, reset: resetThorough } = useForm<ThoroughFormData>({
        patient: {
            address: '',
            city: '',
            state: '',
            zip_code: '',
            age: 0,
            gender: 'male'
        },
        provider: {
            facility_name: '',
            address: '',
            city: '',
            state: '',
            zip_code: '',
            npi: '',
            specialty: '',
            facility_type: ''
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
            codes: [''],
            date: ''
        }
    });

    const handleQuickValidation = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsValidating(true);

        try {
            const cleanedData = {
                ...quickData,
                service_codes: quickData.service_codes.filter(code => code.trim() !== '')
            };

            if (cleanedData.service_codes.length === 0) {
                toast.error('Please enter at least one service code');
                setIsValidating(false);
                return;
            }

            const response = await fetch('/api/mac-validation/quick-check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(cleanedData)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                setQuickResult(result.data);
                toast.success('Quick MAC validation completed');
            } else {
                if (result.errors) {
                    Object.values(result.errors).flat().forEach((error: any) => {
                        toast.error(error);
                    });
                } else {
                    toast.error(result.message || 'Quick validation failed');
                }
                console.error('Validation failed:', result);
            }
        } catch (error) {
            console.error('Quick validation error:', error);
            toast.error('Quick validation failed. Please try again.');
        } finally {
            setIsValidating(false);
        }
    };

    const handleThoroughValidation = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsValidating(true);

        try {
            const response = await fetch('/api/mac-validation/thorough-validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(thoroughData)
            });

            const result = await response.json();

            if (result.success) {
                setThoroughResult(result.data);
                toast.success('Thorough MAC validation completed');
            } else {
                if (response.status === 422 && result.errors) {
                    Object.entries(result.errors).forEach(([field, messages]) => {
                        (messages as string[]).forEach((message: string) => {
                            toast.error(`${field}: ${message}`);
                            console.error(`Validation Error - ${field}: ${message}`);
                        });
                    });
                } else {
                    toast.error(result.message || 'Thorough validation failed');
                    console.error('Thorough validation failed:', result.message || result);
                }
            }
        } catch (error) {
            toast.error('Thorough validation failed. Please try again.');
            console.error('Thorough validation error:', error);
        } finally {
            setIsValidating(false);
        }
    };

    const copyQuickDataToThorough = () => {
        setThoroughData('patient', { ...thoroughData.patient, zip_code: quickData.patient_zip });
        setThoroughData('wound', { ...thoroughData.wound, type: quickData.wound_type });
        setThoroughData('service', { ...thoroughData.service, codes: quickData.service_codes, date: quickData.service_date });
        setValidationMode('thorough');
        toast.success('Quick check data copied to thorough validation');
    };

    const ValidationModeToggle = () => (
        <div className="flex rounded-lg bg-gray-100 p-1 mb-8">
            <button
                onClick={() => setValidationMode('quick')}
                className={`flex-1 flex items-center justify-center px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                    validationMode === 'quick'
                        ? 'bg-white text-indigo-700 shadow-sm'
                        : 'text-gray-500 hover:text-gray-900'
                }`}
            >
                <FiZap className="w-4 h-4 mr-2" />
                Quick Check
            </button>
            <button
                onClick={() => setValidationMode('thorough')}
                className={`flex-1 flex items-center justify-center px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                    validationMode === 'thorough'
                        ? 'bg-white text-indigo-700 shadow-sm'
                        : 'text-gray-500 hover:text-gray-900'
                }`}
            >
                <FiSettings className="w-4 h-4 mr-2" />
                Thorough Validation
            </button>
        </div>
    );

    const QuickValidationStatusBadge = ({ status }: { status: QuickValidationResult['data']['status'] }) => {
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

    const ThoroughValidationStatusBadge = ({ status }: { status: ThoroughValidationResult['data']['status'] }) => {
        const statusConfig = {
            passed: { color: 'bg-green-100 text-green-800', icon: FiCheckCircle },
            passed_with_warnings: { color: 'bg-yellow-100 text-yellow-800', icon: FiAlertTriangle },
            failed: { color: 'bg-red-100 text-red-800', icon: FiAlertCircle },
            requires_review: { color: 'bg-orange-100 text-orange-800', icon: FiAlertTriangle }
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
                                Choose between quick coverage check or comprehensive validation
                            </p>
                        </div>
                    </div>

                    <ValidationModeToggle />

                    {/* Quick Check Results */}
                    {quickResult && validationMode === 'quick' && (
                        <div className="mb-8 space-y-6 animate-fade-in">
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                        <FiZap className="text-indigo-600" />
                                        Quick Validation Results
                                    </h2>
                                    <QuickValidationStatusBadge status={quickResult.status} />
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <p className="text-sm text-gray-600">MAC Contractor</p>
                                        <p className="font-medium text-gray-900">{quickResult.mac_contractor}</p>
                                        {quickResult.mac_phone && (
                                            <p className="text-sm text-gray-600 mt-1">Phone: {quickResult.mac_phone}</p>
                                        )}
                                        {quickResult.mac_website && (
                                            <a href={quickResult.mac_website} target="_blank" rel="noopener noreferrer" className="text-sm text-indigo-600 hover:text-indigo-800 mt-1 inline-block">
                                                Visit MAC Website →
                                            </a>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Jurisdiction</p>
                                        <p className="font-medium text-gray-900">{quickResult.mac_jurisdiction}</p>
                                        <p className="text-xs text-gray-500 mt-1">
                                            Data: {quickResult.mac_data_source === 'cms_api' ?
                                                <span className="text-green-600 font-medium">Live CMS API</span> :
                                                quickResult.mac_data_source === 'cms_lcd_data' ?
                                                <span className="text-blue-600 font-medium">CMS LCD Data</span> :
                                                quickResult.mac_data_source === 'fallback_mapping' ?
                                                <span className="text-orange-600 font-medium">Official MAC Mapping</span> :
                                                <span className="text-yellow-600 font-medium">Cached Reference</span>
                                            }
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Basic Coverage</p>
                                        <p className={`font-medium ${quickResult.basic_coverage ? 'text-green-600' : 'text-red-600'}`}>
                                            {quickResult.basic_coverage ? 'Likely Covered' : 'Coverage Uncertain'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">Time Saved</p>
                                        <p className="font-medium text-gray-900">{quickResult.estimated_time_saved}</p>
                                    </div>
                                </div>

                                {/* Performance Metrics Display */}
                                {quickResult?.performance_summary && (
                                    <div className="mt-6 bg-gray-50 rounded-lg p-4">
                                        <h3 className="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                                            <FiZap className="text-indigo-600" />
                                            Performance Metrics
                                        </h3>
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <p className="text-gray-600">Response Time</p>
                                                <p className="font-medium text-gray-900">{quickResult.performance_summary.total_response_time_ms}ms</p>
                                            </div>
                                            <div>
                                                <p className="text-gray-600">API Calls</p>
                                                <p className="font-medium text-gray-900">{quickResult.performance_summary.cms_api_calls}</p>
                                            </div>
                                            <div>
                                                <p className="text-gray-600">Policies Analyzed</p>
                                                <p className="font-medium text-gray-900">{quickResult.performance_summary.policies_analyzed}</p>
                                            </div>
                                            <div>
                                                <p className="text-gray-600">Data Freshness</p>
                                                <p className="font-medium text-green-600">{quickResult.performance_summary.data_freshness.replace('_', ' ')}</p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Enhanced Service Coverage Display */}
                                {quickResult?.cms_insights?.service_coverage && hasItems(quickResult.cms_insights.service_coverage) && (
                                    <div className="mt-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-3 flex items-center gap-2">
                                            <FiFileText className="text-indigo-600" />
                                            Service Code Coverage Analysis
                                        </h3>
                                        <div className="space-y-3">
                                            {quickResult.cms_insights.service_coverage.map((coverage, index) => (
                                                <div key={index} className="bg-white border border-gray-200 rounded-lg p-4">
                                                    <div className="flex items-start justify-between mb-2">
                                                        <div>
                                                            <span className="font-mono text-sm font-medium text-gray-900">{coverage.code}</span>
                                                            {coverage.description && (
                                                                <p className="text-sm text-gray-600 mt-1">{coverage.description}</p>
                                                            )}
                                                        </div>
                                                        <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                            coverage.status === 'likely_covered' ? 'bg-green-100 text-green-800' :
                                                            coverage.status === 'needs_review' ? 'bg-yellow-100 text-yellow-800' :
                                                            coverage.status === 'not_covered' ? 'bg-red-100 text-red-800' :
                                                            'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {coverage.status.replace('_', ' ')}
                                                        </span>
                                                    </div>

                                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs text-gray-600 mt-3">
                                                        <div>
                                                            <span className="block font-medium">LCD Matches</span>
                                                            <span>{coverage.lcd_matches || 0}</span>
                                                        </div>
                                                        <div>
                                                            <span className="block font-medium">NCD Matches</span>
                                                            <span>{coverage.ncd_matches || 0}</span>
                                                        </div>
                                                        <div>
                                                            <span className="block font-medium">Prior Auth</span>
                                                            <span className={coverage.requires_prior_auth ? 'text-yellow-600 font-medium' : 'text-green-600'}>
                                                                {coverage.requires_prior_auth ? 'Required' : 'Not Required'}
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span className="block font-medium">Frequency Limit</span>
                                                            <span>{coverage.frequency_limit || 'None specified'}</span>
                                                        </div>
                                                    </div>

                                                    {hasItems(coverage.coverage_notes) && (
                                                        <div className="mt-3 pt-3 border-t border-gray-100">
                                                            <p className="text-xs font-medium text-gray-900 mb-1">Coverage Notes:</p>
                                                            <ul className="text-xs text-gray-600 space-y-1">
                                                                {safeArray(coverage.coverage_notes).slice(0, 3).map((note, noteIndex) => (
                                                                    <li key={noteIndex} className="flex items-start gap-1">
                                                                        <span className="text-gray-400">•</span>
                                                                        <span>{note}</span>
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Enhanced Documentation Requirements */}
                                {quickResult?.cms_insights?.key_documentation && hasItems(quickResult.cms_insights.key_documentation) && (
                                    <div className="mt-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-3 flex items-center gap-2">
                                            <FiInfo className="text-indigo-600" />
                                            Key Documentation Requirements
                                        </h3>
                                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <ul className="space-y-2">
                                                {safeArray(quickResult.cms_insights.key_documentation).map((requirement, index) => (
                                                    <li key={index} className="flex items-start gap-2 text-sm text-blue-900">
                                                        <FiCheck className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                                        <span>{requirement}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                )}

                                {/* CMS Policy References */}
                                {(hasItems(quickResult?.cms_insights?.relevant_lcds) || hasItems(quickResult?.cms_insights?.relevant_ncds)) && (
                                    <div className="mt-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-3 flex items-center gap-2">
                                            <FiFileText className="text-indigo-600" />
                                            CMS Policy References
                                        </h3>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {hasItems(quickResult?.cms_insights?.relevant_lcds) && (
                                                <div>
                                                    <h4 className="text-sm font-medium text-gray-900 mb-2">Local Coverage Determinations (LCDs)</h4>
                                                    <div className="space-y-2">
                                                        {safeArray(quickResult.cms_insights.relevant_lcds).slice(0, 3).map((lcd, index) => (
                                                            <div key={index} className="bg-gray-50 rounded-lg p-3">
                                                                <p className="text-sm font-medium text-gray-900">{lcd.documentTitle}</p>
                                                                {lcd.contractor && (
                                                                    <p className="text-xs text-gray-600 mt-1">Contractor: {lcd.contractor}</p>
                                                                )}
                                                                {lcd.effectiveDate && (
                                                                    <p className="text-xs text-gray-600">Effective: {new Date(lcd.effectiveDate).toLocaleDateString()}</p>
                                                                )}
                                                                {lcd.summary && (
                                                                    <p className="text-xs text-gray-700 mt-2 line-clamp-2">{lcd.summary}</p>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {hasItems(quickResult?.cms_insights?.relevant_ncds) && (
                                                <div>
                                                    <h4 className="text-sm font-medium text-gray-900 mb-2">National Coverage Determinations (NCDs)</h4>
                                                    <div className="space-y-2">
                                                        {safeArray(quickResult.cms_insights.relevant_ncds).slice(0, 3).map((ncd, index) => (
                                                            <div key={index} className="bg-gray-50 rounded-lg p-3">
                                                                <p className="text-sm font-medium text-gray-900">{ncd.documentTitle}</p>
                                                                {ncd.ncdNumber && (
                                                                    <p className="text-xs text-gray-600 mt-1">NCD: {ncd.ncdNumber}</p>
                                                                )}
                                                                {ncd.effectiveDate && (
                                                                    <p className="text-xs text-gray-600">Effective: {new Date(ncd.effectiveDate).toLocaleDateString()}</p>
                                                                )}
                                                                {ncd.summary && (
                                                                    <p className="text-xs text-gray-700 mt-2 line-clamp-2">{ncd.summary}</p>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {hasItems(quickResult?.quick_issues) && (
                                    <div className="mt-6">
                                        <h3 className="text-lg font-medium text-gray-900 mb-3">Quick Issues Found</h3>
                                        <ul className="space-y-2">
                                            {safeArray(quickResult?.quick_issues).map((issue, index) => (
                                                <li key={index} className="flex items-start gap-2">
                                                    <FiAlertTriangle className="w-5 h-5 text-yellow-500 mt-0.5" />
                                                    <span className="text-gray-700">{issue}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                <div className="mt-6 flex gap-4">
                                    <button
                                        onClick={() => {
                                            setQuickResult(null);
                                            resetQuick();
                                        }}
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        <FiX className="mr-2 h-4 w-4" />
                                        New Quick Check
                                    </button>
                                    {quickResult.recommendation === 'full_validation_needed' && (
                                        <button
                                            onClick={copyQuickDataToThorough}
                                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700"
                                        >
                                            <FiSettings className="mr-2 h-4 w-4" />
                                            Thorough Validation
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Thorough Validation Results */}
                    {thoroughResult && validationMode === 'thorough' && (
                        <div className="mb-8 space-y-6 animate-fade-in">
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl font-semibold text-gray-900 flex items-center gap-2">
                                        <FiShield className="text-indigo-600" />
                                        Comprehensive Validation Results
                                        <span className="text-sm font-normal text-gray-500 ml-2">({thoroughResult.analysis_depth})</span>
                                    </h2>
                                    <ThoroughValidationStatusBadge status={thoroughResult.status} />
                                </div>

                                {/* Key Metrics Summary */}
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                                    <div className="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4">
                                        <p className="text-sm text-blue-600 font-medium">Compliance Score</p>
                                        <p className="text-2xl font-bold text-blue-900">{thoroughResult.compliance_score}%</p>
                                        <p className="text-xs text-blue-700">Confidence: {thoroughResult.confidence_level}</p>
                                    </div>
                                    <div className="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4">
                                        <p className="text-sm text-green-600 font-medium">Reimbursement Risk</p>
                                        <p className={`text-2xl font-bold ${
                                            thoroughResult.reimbursement_risk === 'low' ? 'text-green-700' :
                                            thoroughResult.reimbursement_risk === 'medium' ? 'text-yellow-700' : 'text-red-700'
                                        }`}>
                                            {thoroughResult.reimbursement_risk.toUpperCase()}
                                        </p>
                                        <p className="text-xs text-green-700">Est: ${thoroughResult.reimbursement_analysis.total_estimated_reimbursement}</p>
                                    </div>
                                    <div className="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4">
                                        <p className="text-sm text-purple-600 font-medium">MAC Contractor</p>
                                        <p className="text-lg font-bold text-purple-900">{thoroughResult.mac_contractor}</p>
                                        <p className="text-xs text-purple-700">{thoroughResult.mac_jurisdiction}</p>
                                    </div>
                                    <div className="bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg p-4">
                                        <p className="text-sm text-orange-600 font-medium">Processing Time</p>
                                        <p className="text-2xl font-bold text-orange-900">{(thoroughResult.performance_metrics.total_processing_time_ms / 1000).toFixed(1)}s</p>
                                        <p className="text-xs text-orange-700">{thoroughResult.performance_metrics.cms_api_calls} API calls</p>
                                    </div>
                                </div>

                                {/* Validation Summary */}
                                <div className="mb-8">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Validation Summary</h3>
                                    <div className="bg-gray-50 rounded-lg p-4">
                                        <div className="grid grid-cols-4 gap-4 text-center">
                                            <div>
                                                <p className="text-2xl font-bold text-green-600">{thoroughResult.validation_results.validation_summary.passed}</p>
                                                <p className="text-sm text-gray-600">Passed</p>
                                            </div>
                                            <div>
                                                <p className="text-2xl font-bold text-yellow-600">{thoroughResult.validation_results.validation_summary.warnings}</p>
                                                <p className="text-sm text-gray-600">Warnings</p>
                                            </div>
                                            <div>
                                                <p className="text-2xl font-bold text-red-600">{thoroughResult.validation_results.validation_summary.failed}</p>
                                                <p className="text-sm text-gray-600">Failed</p>
                                            </div>
                                            <div>
                                                <p className="text-2xl font-bold text-gray-600">{thoroughResult.validation_results.validation_summary.total_checks}</p>
                                                <p className="text-sm text-gray-600">Total Checks</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Detailed Validation Results */}
                                {hasItems(thoroughResult.validation_results.validations) && (
                                    <div className="mb-8">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Detailed Validation Results</h3>
                                        <div className="space-y-3">
                                            {thoroughResult.validation_results.validations.map((validation, index) => (
                                                <div key={index} className="bg-white border border-gray-200 rounded-lg p-4">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-2 mb-2">
                                                                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                                    validation.status === 'passed' ? 'bg-green-100 text-green-800' :
                                                                    validation.status === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                                                                    'bg-red-100 text-red-800'
                                                                }`}>
                                                                    {validation.status === 'passed' ? <FiCheckCircle className="w-3 h-3 mr-1" /> :
                                                                     validation.status === 'warning' ? <FiAlertTriangle className="w-3 h-3 mr-1" /> :
                                                                     <FiAlertCircle className="w-3 h-3 mr-1" />}
                                                                    {validation.status.toUpperCase()}
                                                                </span>
                                                                <span className="font-medium text-gray-900">{validation.rule}</span>
                                                            </div>
                                                            <p className="text-sm text-gray-700">{validation.message}</p>
                                                            {validation.cms_reference && (
                                                                <p className="text-xs text-blue-600 mt-1">{validation.cms_reference}</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* CMS Compliance & Coverage Analysis */}
                                <div className="mb-8">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">CMS Compliance & Coverage Analysis</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="bg-blue-50 rounded-lg p-4">
                                            <h4 className="font-medium text-blue-900 mb-3">Coverage Strength</h4>
                                            <div className="space-y-2">
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-blue-700">Policy Coverage:</span>
                                                    <span className="font-medium text-blue-900">{thoroughResult.cms_compliance.coverage_strength}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-blue-700">Evidence Level:</span>
                                                    <span className="font-medium text-blue-900">{thoroughResult.cms_compliance.evidence_level}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-blue-700">LCDs Found:</span>
                                                    <span className="font-medium text-blue-900">{thoroughResult.cms_compliance.lcds_found}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-blue-700">NCDs Found:</span>
                                                    <span className="font-medium text-blue-900">{thoroughResult.cms_compliance.ncds_found}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="bg-green-50 rounded-lg p-4">
                                            <h4 className="font-medium text-green-900 mb-3">Advanced Analysis</h4>
                                            <div className="space-y-2">
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-green-700">Technology Assessments:</span>
                                                    <span className="font-medium text-green-900">{thoroughResult.cms_compliance.technology_assessments}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-green-700">NCA Tracking Items:</span>
                                                    <span className="font-medium text-green-900">{thoroughResult.cms_compliance.nca_tracking_items}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-green-700">Data Sources:</span>
                                                    <span className="font-medium text-green-900">{thoroughResult.performance_metrics.data_sources_consulted}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-sm text-green-700">Cache Efficiency:</span>
                                                    <span className="font-medium text-green-900">{thoroughResult.performance_metrics.cache_efficiency}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Prior Authorization Analysis */}
                                {thoroughResult.prior_authorization_analysis.total_codes_requiring_auth > 0 && (
                                    <div className="mb-8">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Prior Authorization Requirements</h3>
                                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <div className="flex items-center gap-2 mb-3">
                                                <FiAlertTriangle className="text-yellow-600" />
                                                <span className="font-medium text-yellow-800">
                                                    {thoroughResult.prior_authorization_analysis.total_codes_requiring_auth} code(s) require prior authorization
                                                </span>
                                            </div>
                                            <div className="space-y-3">
                                                {thoroughResult.prior_authorization_analysis.codes_requiring_auth.map((code, index) => (
                                                    <div key={index} className="bg-white rounded-lg p-3">
                                                        <div className="flex justify-between items-start">
                                                            <div>
                                                                <span className="font-mono text-sm font-medium">{code.code}</span>
                                                                <p className="text-sm text-gray-600 mt-1">{code.description}</p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="text-xs text-yellow-600 font-medium">{code.urgency.replace('_', ' ')}</p>
                                                                <p className="text-xs text-gray-500">{code.estimated_processing_time}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {hasItems(thoroughResult.prior_authorization_analysis.recommendations) && (
                                                <div className="mt-4">
                                                    <h5 className="font-medium text-yellow-800 mb-2">Recommendations:</h5>
                                                    <ul className="space-y-1">
                                                        {thoroughResult.prior_authorization_analysis.recommendations.map((rec, index) => (
                                                            <li key={index} className="text-sm text-yellow-700 flex items-start gap-1">
                                                                <span className="text-yellow-500">•</span>
                                                                <span>{rec}</span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Reimbursement Analysis */}
                                <div className="mb-8">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Reimbursement Analysis</h3>
                                    <div className="bg-green-50 rounded-lg p-4">
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                            <div>
                                                <p className="text-sm text-green-600">Total Estimated Reimbursement</p>
                                                <p className="text-2xl font-bold text-green-800">
                                                    ${thoroughResult.reimbursement_analysis.total_estimated_reimbursement.toFixed(2)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-sm text-green-600">Patient Responsibility (Est.)</p>
                                                <p className="text-xl font-bold text-green-800">
                                                    ${thoroughResult.reimbursement_analysis.reimbursement_factors.estimated_patient_responsibility.toFixed(2)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-sm text-green-600">Geographic Adjustment</p>
                                                <p className="text-xl font-bold text-green-800">
                                                    {thoroughResult.reimbursement_analysis.reimbursement_factors.geographic_adjustment.toFixed(2)}x
                                                </p>
                                            </div>
                                        </div>
                                        <p className="text-xs text-green-700 italic">{thoroughResult.reimbursement_analysis.disclaimer}</p>
                                    </div>
                                </div>

                                {/* Clinical & Documentation Requirements */}
                                <div className="mb-8">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Clinical Requirements</h3>
                                            <div className="bg-blue-50 rounded-lg p-4">
                                                <ul className="space-y-2">
                                                    {thoroughResult.clinical_requirements.map((req, index) => (
                                                        <li key={index} className="text-sm text-blue-800 flex items-start gap-2">
                                                            <FiCheck className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                                            <span>{req}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Documentation Requirements</h3>
                                            <div className="bg-indigo-50 rounded-lg p-4">
                                                <ul className="space-y-2">
                                                    {thoroughResult.documentation_requirements.slice(0, 6).map((req, index) => (
                                                        <li key={index} className="text-sm text-indigo-800 flex items-start gap-2">
                                                            <FiFileText className="w-4 h-4 text-indigo-600 mt-0.5 flex-shrink-0" />
                                                            <span>{req}</span>
                                                        </li>
                                                    ))}
                                                    {thoroughResult.documentation_requirements.length > 6 && (
                                                        <li className="text-sm text-indigo-600 italic">
                                                            +{thoroughResult.documentation_requirements.length - 6} more requirements...
                                                        </li>
                                                    )}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Risk Factors & Recommendations */}
                                {(hasItems(thoroughResult.risk_factors) || hasItems(thoroughResult.recommendations)) && (
                                    <div className="mb-8">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            {hasItems(thoroughResult.risk_factors) && (
                                                <div>
                                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Risk Factors</h3>
                                                    <div className="bg-red-50 rounded-lg p-4">
                                                        <ul className="space-y-2">
                                                            {thoroughResult.risk_factors.map((risk, index) => (
                                                                <li key={index} className="text-sm text-red-800 flex items-start gap-2">
                                                                    <FiAlertTriangle className="w-4 h-4 text-red-600 mt-0.5 flex-shrink-0" />
                                                                    <span>{risk}</span>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                </div>
                                            )}
                                            {hasItems(thoroughResult.recommendations) && (
                                                <div>
                                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Recommendations</h3>
                                                    <div className="bg-green-50 rounded-lg p-4">
                                                        <ul className="space-y-2">
                                                            {thoroughResult.recommendations.map((rec, index) => (
                                                                <li key={index} className="text-sm text-green-800 flex items-start gap-2">
                                                                    <FiCheckCircle className="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" />
                                                                    <span>{rec}</span>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Quality Measures */}
                                {hasItems(thoroughResult.quality_measures) && (
                                    <div className="mb-8">
                                        <h3 className="text-lg font-medium text-gray-900 mb-4">Quality Measures</h3>
                                        <div className="bg-purple-50 rounded-lg p-4">
                                            <ul className="space-y-2">
                                                {thoroughResult.quality_measures.map((measure, index) => (
                                                    <li key={index} className="text-sm text-purple-800 flex items-start gap-2">
                                                        <FiShield className="w-4 h-4 text-purple-600 mt-0.5 flex-shrink-0" />
                                                        <span>{measure}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                )}

                                <div className="mt-6 flex gap-4">
                                    <button
                                        onClick={() => {
                                            setThoroughResult(null);
                                            resetThorough();
                                        }}
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        <FiX className="mr-2 h-4 w-4" />
                                        New Comprehensive Validation
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Quick Validation Form */}
                    {validationMode === 'quick' && !quickResult && (
                        <form onSubmit={handleQuickValidation} className="space-y-8">
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiZap className="text-indigo-600" />
                                    Quick MAC Check
                                    <span className="text-sm font-normal text-gray-500 ml-2">(~30 seconds)</span>
                                </h2>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Patient ZIP Code *
                                        </label>
                                        <input
                                            type="text"
                                            value={quickData.patient_zip}
                                            onChange={e => setQuickData('patient_zip', e.target.value)}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter patient ZIP code"
                                            pattern="[0-9]{5}"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Type *
                                        </label>
                                        <select
                                            value={quickData.wound_type}
                                            onChange={e => setQuickData('wound_type', e.target.value)}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Wound Type</option>
                                            <option value="dfu">Diabetic Foot Ulcer</option>
                                            <option value="vlu">Venous Leg Ulcer</option>
                                            <option value="pressure">Pressure Ulcer</option>
                                            <option value="surgical">Surgical Wound</option>
                                            <option value="arterial">Arterial Ulcer</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Service Date *
                                        </label>
                                        <input
                                            type="date"
                                            value={quickData.service_date}
                                            onChange={e => setQuickData('service_date', e.target.value)}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="mt-6">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Service Codes (HCPCS/CPT) *
                                    </label>
                                    <div className="space-y-2">
                                        {quickData.service_codes.map((code, index) => (
                                            <div key={index} className="flex gap-2">
                                                <input
                                                    type="text"
                                                    value={code}
                                                    onChange={e => {
                                                        const newCodes = [...quickData.service_codes];
                                                        newCodes[index] = e.target.value;
                                                        setQuickData('service_codes', newCodes);
                                                    }}
                                                    className="flex-1 py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                    placeholder="Enter HCPCS/CPT code"
                                                    required
                                                />
                                                {index > 0 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const newCodes = quickData.service_codes.filter((_, i) => i !== index);
                                                            setQuickData('service_codes', newCodes);
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
                                            onClick={() => setQuickData('service_codes', [...quickData.service_codes, ''])}
                                            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                        >
                                            <FiPlus className="w-4 h-4 mr-2" />
                                            Add Code
                                        </button>
                                    </div>
                                </div>

                                <div className="mt-8 flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={quickProcessing || isValidating}
                                        className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-colors"
                                    >
                                        {isValidating ? (
                                            <>
                                                <FiClock className="animate-spin -ml-1 mr-2 h-5 w-5" />
                                                Quick Checking...
                                            </>
                                        ) : (
                                            <>
                                                <FiZap className="-ml-1 mr-2 h-5 w-5" />
                                                Quick Check
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>
                        </form>
                    )}

                    {/* Thorough Validation Form */}
                    {validationMode === 'thorough' && !thoroughResult && (
                        <form onSubmit={handleThoroughValidation} className="space-y-8">
                            {/* Patient Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiUser className="text-indigo-600" />
                                    Patient Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Patient Address *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.patient.address}
                                            onChange={e => setThoroughData('patient', { ...thoroughData.patient, address: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter street address"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            City *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.patient.city}
                                            onChange={e => setThoroughData('patient', { ...thoroughData.patient, city: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter city"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            State *
                                        </label>
                                        <select
                                            value={thoroughData.patient.state}
                                            onChange={e => setThoroughData('patient', { ...thoroughData.patient, state: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select State</option>
                                            {['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'].map(state => (
                                                <option key={state} value={state}>{state}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            ZIP Code *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.patient.zip_code}
                                            onChange={e => setThoroughData('patient', { ...thoroughData.patient, zip_code: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter ZIP code"
                                            pattern="[0-9]{5}"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Age *
                                        </label>
                                        <input
                                            type="number"
                                            value={thoroughData.patient.age}
                                            onChange={e => setThoroughData('patient', { ...thoroughData.patient, age: parseInt(e.target.value) || 0 })}
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
                                            value={thoroughData.patient.gender}
                                            onChange={e => setThoroughData('patient', { ...thoroughData.patient, gender: e.target.value as 'male' | 'female' | 'other' })}
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

                            {/* Provider Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiMapPin className="text-indigo-600" />
                                    Provider Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Facility Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.provider.facility_name}
                                            onChange={e => setThoroughData('provider', { ...thoroughData.provider, facility_name: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="Enter facility name"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            NPI *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.provider.npi}
                                            onChange={e => setThoroughData('provider', { ...thoroughData.provider, npi: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="10-digit NPI"
                                            pattern="[0-9]{10}"
                                            maxLength={10}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Provider Specialty *
                                        </label>
                                        <select
                                            value={thoroughData.provider.specialty}
                                            onChange={e => setThoroughData('provider', { ...thoroughData.provider, specialty: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Specialty</option>
                                            <option value="wound_care">Wound Care Specialist</option>
                                            <option value="podiatry">Podiatry</option>
                                            <option value="vascular_surgery">Vascular Surgery</option>
                                            <option value="plastic_surgery">Plastic Surgery</option>
                                            <option value="family_medicine">Family Medicine</option>
                                            <option value="internal_medicine">Internal Medicine</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Facility Type
                                        </label>
                                        <select
                                            value={thoroughData.provider.facility_type}
                                            onChange={e => setThoroughData('provider', { ...thoroughData.provider, facility_type: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <option value="">Select Facility Type</option>
                                            <option value="outpatient">Outpatient Clinic</option>
                                            <option value="hospital">Hospital</option>
                                            <option value="wound_center">Wound Care Center</option>
                                            <option value="snf">Skilled Nursing Facility</option>
                                            <option value="home_health">Home Health</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {/* Wound Details */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiInfo className="text-indigo-600" />
                                    Wound Details
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Type *
                                        </label>
                                        <select
                                            value={thoroughData.wound.type}
                                            onChange={e => setThoroughData('wound', { ...thoroughData.wound, type: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        >
                                            <option value="">Select Wound Type</option>
                                            <option value="dfu">Diabetic Foot Ulcer</option>
                                            <option value="vlu">Venous Leg Ulcer</option>
                                            <option value="pressure">Pressure Ulcer</option>
                                            <option value="surgical">Surgical Wound</option>
                                            <option value="arterial">Arterial Ulcer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Location *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.wound.location}
                                            onChange={e => setThoroughData('wound', { ...thoroughData.wound, location: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="e.g., Right heel, Left lower leg"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Size
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.wound.size}
                                            onChange={e => setThoroughData('wound', { ...thoroughData.wound, size: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="e.g., 3cm x 2cm x 1cm"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Duration (weeks) *
                                        </label>
                                        <input
                                            type="number"
                                            value={thoroughData.wound.duration_weeks}
                                            onChange={e => setThoroughData('wound', { ...thoroughData.wound, duration_weeks: parseInt(e.target.value) || 0 })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            min="0"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Wound Depth
                                        </label>
                                        <select
                                            value={thoroughData.wound.depth}
                                            onChange={e => setThoroughData('wound', { ...thoroughData.wound, depth: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <option value="">Select Depth</option>
                                            <option value="superficial">Superficial</option>
                                            <option value="partial_thickness">Partial Thickness</option>
                                            <option value="full_thickness">Full Thickness</option>
                                            <option value="deep">Deep</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Tissue Type
                                        </label>
                                        <select
                                            value={thoroughData.wound.tissue_type}
                                            onChange={e => setThoroughData('wound', { ...thoroughData.wound, tissue_type: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <option value="">Select Tissue Type</option>
                                            <option value="granulation">Granulation</option>
                                            <option value="slough">Slough</option>
                                            <option value="eschar">Eschar</option>
                                            <option value="necrotic">Necrotic</option>
                                            <option value="epithelial">Epithelial</option>
                                        </select>
                                    </div>
                                    <div className="md:col-span-2">
                                        <div className="flex items-center space-x-6">
                                            <label className="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    checked={thoroughData.wound.infection_status}
                                                    onChange={e => setThoroughData('wound', { ...thoroughData.wound, infection_status: e.target.checked })}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Active infection present</span>
                                            </label>
                                            <label className="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    checked={thoroughData.wound.exposed_structures}
                                                    onChange={e => setThoroughData('wound', { ...thoroughData.wound, exposed_structures: e.target.checked })}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Exposed structures (bone, tendon, etc.)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Diagnoses */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiFileText className="text-indigo-600" />
                                    Diagnoses
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Primary Diagnosis (ICD-10) *
                                        </label>
                                        <input
                                            type="text"
                                            value={thoroughData.diagnoses.primary}
                                            onChange={e => setThoroughData('diagnoses', { ...thoroughData.diagnoses, primary: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                            placeholder="e.g., L97.429"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Secondary Diagnoses (ICD-10)
                                        </label>
                                        <div className="space-y-2">
                                            {thoroughData.diagnoses.secondary.map((diagnosis, index) => (
                                                <div key={index} className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={diagnosis}
                                                        onChange={e => {
                                                            const newDiagnoses = [...thoroughData.diagnoses.secondary];
                                                            newDiagnoses[index] = e.target.value;
                                                            setThoroughData('diagnoses', { ...thoroughData.diagnoses, secondary: newDiagnoses });
                                                        }}
                                                        className="flex-1 py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="e.g., E11.9"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const newDiagnoses = thoroughData.diagnoses.secondary.filter((_, i) => i !== index);
                                                            setThoroughData('diagnoses', { ...thoroughData.diagnoses, secondary: newDiagnoses });
                                                        }}
                                                        className="p-2 text-red-600 hover:text-red-800"
                                                    >
                                                        <FiX className="w-5 h-5" />
                                                    </button>
                                                </div>
                                            ))}
                                            <button
                                                type="button"
                                                onClick={() => setThoroughData('diagnoses', { ...thoroughData.diagnoses, secondary: [...thoroughData.diagnoses.secondary, ''] })}
                                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                            >
                                                <FiPlus className="w-4 h-4 mr-2" />
                                                Add Secondary Diagnosis
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Prior Care */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiClock className="text-indigo-600" />
                                    Prior Care History
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Duration of Prior Care (weeks)
                                        </label>
                                        <input
                                            type="number"
                                            value={thoroughData.prior_care.duration_weeks}
                                            onChange={e => setThoroughData('prior_care', { ...thoroughData.prior_care, duration_weeks: parseInt(e.target.value) || 0 })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            min="0"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Previous Treatments
                                        </label>
                                        <div className="space-y-2">
                                            {thoroughData.prior_care.treatments.map((treatment, index) => (
                                                <div key={index} className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={treatment}
                                                        onChange={e => {
                                                            const newTreatments = [...thoroughData.prior_care.treatments];
                                                            newTreatments[index] = e.target.value;
                                                            setThoroughData('prior_care', { ...thoroughData.prior_care, treatments: newTreatments });
                                                        }}
                                                        className="flex-1 py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="e.g., Standard wound care, Debridement"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const newTreatments = thoroughData.prior_care.treatments.filter((_, i) => i !== index);
                                                            setThoroughData('prior_care', { ...thoroughData.prior_care, treatments: newTreatments });
                                                        }}
                                                        className="p-2 text-red-600 hover:text-red-800"
                                                    >
                                                        <FiX className="w-5 h-5" />
                                                    </button>
                                                </div>
                                            ))}
                                            <button
                                                type="button"
                                                onClick={() => setThoroughData('prior_care', { ...thoroughData.prior_care, treatments: [...thoroughData.prior_care.treatments, ''] })}
                                                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                            >
                                                <FiPlus className="w-4 h-4 mr-2" />
                                                Add Treatment
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Lab Values */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiSearch className="text-indigo-600" />
                                    Lab Values (Optional)
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            HbA1c (%)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.1"
                                            value={thoroughData.lab_values.hba1c || ''}
                                            onChange={e => setThoroughData('lab_values', { ...thoroughData.lab_values, hba1c: e.target.value ? parseFloat(e.target.value) : undefined })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="e.g., 7.2"
                                            min="0"
                                            max="20"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            ABI (Ankle-Brachial Index)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            value={thoroughData.lab_values.abi || ''}
                                            onChange={e => setThoroughData('lab_values', { ...thoroughData.lab_values, abi: e.target.value ? parseFloat(e.target.value) : undefined })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="e.g., 0.9"
                                            min="0"
                                            max="2"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Albumin (g/dL)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.1"
                                            value={thoroughData.lab_values.albumin || ''}
                                            onChange={e => setThoroughData('lab_values', { ...thoroughData.lab_values, albumin: e.target.value ? parseFloat(e.target.value) : undefined })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            placeholder="e.g., 3.5"
                                            min="0"
                                            max="10"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Service Information */}
                            <div className="bg-white shadow-lg rounded-xl p-8 border border-gray-100">
                                <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                                    <FiCalendar className="text-indigo-600" />
                                    Service Information
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Service Date *
                                        </label>
                                        <input
                                            type="date"
                                            value={thoroughData.service.date}
                                            onChange={e => setThoroughData('service', { ...thoroughData.service, date: e.target.value })}
                                            className="w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Service Codes (HCPCS/CPT) *
                                        </label>
                                        <div className="space-y-2">
                                            {thoroughData.service.codes.map((code, index) => (
                                                <div key={index} className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={code}
                                                        onChange={e => {
                                                            const newCodes = [...thoroughData.service.codes];
                                                            newCodes[index] = e.target.value;
                                                            setThoroughData('service', { ...thoroughData.service, codes: newCodes });
                                                        }}
                                                        className="flex-1 py-2 px-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                        placeholder="Enter HCPCS/CPT code"
                                                        required
                                                    />
                                                    {index > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                const newCodes = thoroughData.service.codes.filter((_, i) => i !== index);
                                                                setThoroughData('service', { ...thoroughData.service, codes: newCodes });
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
                                                onClick={() => setThoroughData('service', { ...thoroughData.service, codes: [...thoroughData.service.codes, ''] })}
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
                                    disabled={thoroughProcessing || isValidating}
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
                                            Run Thorough Validation
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

export default MACValidationPage;
