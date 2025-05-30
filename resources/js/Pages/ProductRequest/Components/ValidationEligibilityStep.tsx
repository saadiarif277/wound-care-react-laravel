import React, { useState, useEffect } from 'react';
import { AlertTriangle, CheckCircle, Clock, Shield, Info, Loader2, ExternalLink, FileText, Database } from 'lucide-react';
import { apiPost, apiGet, handleApiResponse } from '@/lib/api';

interface ValidationEligibilityStepProps {
  formData: any;
  updateFormData: (data: any) => void;
  userSpecialty?: string;
}

interface ValidationResult {
  status: 'passed' | 'warning' | 'failed' | 'pending';
  score: number;
  mac_contractor: string;
  jurisdiction: string;
  validation_type: string;
  cms_compliance: {
    lcds_checked: number;
    ncds_checked: number;
    articles_checked: number;
    compliance_score: number;
  };
  issues: Array<{
    type: 'error' | 'warning' | 'info';
    message: string;
    resolution?: string;
    lcd_reference?: string;
    cms_document_id?: string;
  }>;
  requirements_met: {
    coverage: boolean;
    documentation: boolean;
    frequency: boolean;
    medical_necessity: boolean;
    billing_compliance: boolean;
    prior_authorization: boolean;
  };
  daily_monitoring_enabled?: boolean;
  reimbursement_risk?: 'low' | 'medium' | 'high';
  pre_order_validation: boolean;
}

interface EligibilityResult {
  status: 'eligible' | 'not_eligible' | 'needs_review' | 'pending';
  benefits: {
    copay?: number;
    deductible?: number;
    coinsurance?: number;
    out_of_pocket_max?: number;
  };
  prior_authorization_required: boolean;
  coverage_details: string;
}

const ValidationEligibilityStep: React.FC<ValidationEligibilityStepProps> = ({
  formData,
  updateFormData,
  userSpecialty = 'wound_care_specialty'
}) => {
  const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);
  const [eligibilityResult, setEligibilityResult] = useState<EligibilityResult | null>(null);
  const [isValidating, setIsValidating] = useState(false);
  const [isCheckingEligibility, setIsCheckingEligibility] = useState(false);
  const [autoValidationEnabled, setAutoValidationEnabled] = useState(true);
  const [cmsData, setCmsData] = useState<any>(null);
  const [isLoadingCmsData, setIsLoadingCmsData] = useState(false);

  // Determine validation type based on specialty and wound type
  const getValidationType = () => {
    if (userSpecialty === 'vascular_surgery' || formData.wound_type === 'arterial_ulcer') {
      return 'vascular_wound_care';
    }
    if (userSpecialty === 'wound_care_specialty') {
      return 'wound_care_only';
    }
    return 'wound_care_only';
  };

  // Load CMS coverage data for the specialty
  const loadCmsData = async () => {
    setIsLoadingCmsData(true);
    try {
      // Get facility state for MAC jurisdiction
      const facilityState = formData.facility?.state || 'CA'; // Default to CA if not available

      // Fetch CMS LCDs, NCDs, and Articles for the specialty
      const [lcdsResponse, ncdsResponse, articlesResponse] = await Promise.all([
        apiGet(`/api/v1/validation-builder/cms-lcds?specialty=${userSpecialty}&state=${facilityState}`),
        apiGet(`/api/v1/validation-builder/cms-ncds?specialty=${userSpecialty}`),
        apiGet(`/api/v1/validation-builder/cms-articles?specialty=${userSpecialty}&state=${facilityState}`)
      ]);

      const [lcds, ncds, articles] = await Promise.all([
        handleApiResponse(lcdsResponse),
        handleApiResponse(ncdsResponse),
        handleApiResponse(articlesResponse)
      ]);

      setCmsData({
        lcds: lcds.data?.lcds || [],
        ncds: ncds.data?.ncds || [],
        articles: articles.data?.articles || [],
        state: facilityState
      });
    } catch (error) {
      console.error('Error loading CMS data:', error);
    } finally {
      setIsLoadingCmsData(false);
    }
  };

  // Run comprehensive MAC validation using CMS Coverage API and ValidationBuilder
  const runMacValidation = async () => {
    setIsValidating(true);
    try {
      // Use ValidationBuilder with CMS Coverage API integration for pre-order validation
      const validationType = getValidationType();
      const facilityState = formData.facility?.state || 'CA';

      const response = await apiPost('/api/v1/validation-builder/validate-product-request', {
        patient_data: formData.patient_api_input,
        clinical_data: formData.clinical_data,
        wound_type: formData.wound_type,
        facility_id: formData.facility_id,
        facility_state: facilityState,
        expected_service_date: formData.expected_service_date,
        provider_specialty: userSpecialty,
        selected_products: formData.selected_products,
        validation_type: validationType,
        enable_cms_integration: true,
        enable_mac_validation: true,
        state: facilityState
      });

      const result = await handleApiResponse(response);

      if (result.success) {
        // Transform ValidationBuilder result to comprehensive MAC validation format
        const macValidationResult = {
          status: result.data.overall_status || 'pending',
          score: result.data.compliance_score || 0,
          mac_contractor: result.data.mac_contractor || 'Unknown',
          jurisdiction: result.data.jurisdiction || 'Unknown',
          validation_type: validationType,
          cms_compliance: result.data.cms_compliance || {
            lcds_checked: cmsData?.lcds?.length || 0,
            ncds_checked: cmsData?.ncds?.length || 0,
            articles_checked: cmsData?.articles?.length || 0,
            compliance_score: result.data.cms_compliance_score || 0
          },
          issues: result.data.issues || [],
          requirements_met: result.data.requirements_met || {
            coverage: false,
            documentation: false,
            frequency: false,
            medical_necessity: false,
            billing_compliance: false,
            prior_authorization: false
          },
          daily_monitoring_enabled: false, // Not applicable for pre-order validation
          reimbursement_risk: result.data.reimbursement_risk || 'medium',
          pre_order_validation: true, // Flag to indicate this is pre-order validation
          validation_builder_results: result.data
        };

        setValidationResult(macValidationResult);
        updateFormData({
          mac_validation_results: macValidationResult,
          mac_validation_status: macValidationResult.status,
          pre_order_compliance_score: macValidationResult.score,
          cms_compliance_data: macValidationResult.cms_compliance
        });
      } else {
        console.error('Pre-order MAC validation failed:', result.message);

        // Show user-friendly error
        setValidationResult({
          status: 'failed',
          score: 0,
          mac_contractor: 'Unknown',
          jurisdiction: 'Unknown',
          validation_type: validationType,
          cms_compliance: {
            lcds_checked: 0,
            ncds_checked: 0,
            articles_checked: 0,
            compliance_score: 0
          },
          issues: [{
            type: 'error',
            message: 'Unable to complete MAC validation. Please check your clinical data and try again.',
            resolution: 'Ensure all required clinical assessment sections are completed.'
          }],
          requirements_met: {
            coverage: false,
            documentation: false,
            frequency: false,
            medical_necessity: false,
            billing_compliance: false,
            prior_authorization: false
          },
          pre_order_validation: true,
          reimbursement_risk: 'high'
        });
      }
    } catch (error) {
      console.error('Pre-order MAC validation error:', error);

      // Show error state
      setValidationResult({
        status: 'failed',
        score: 0,
        mac_contractor: 'Unknown',
        jurisdiction: 'Unknown',
        validation_type: getValidationType(),
        cms_compliance: {
          lcds_checked: 0,
          ncds_checked: 0,
          articles_checked: 0,
          compliance_score: 0
        },
        issues: [{
          type: 'error',
          message: 'MAC validation service is currently unavailable.',
          resolution: 'Please try again in a few moments or contact support if the issue persists.'
        }],
        requirements_met: {
          coverage: false,
          documentation: false,
          frequency: false,
          medical_necessity: false,
          billing_compliance: false,
          prior_authorization: false
        },
        pre_order_validation: true,
        reimbursement_risk: 'high'
      });
    } finally {
      setIsValidating(false);
    }
  };

  // Run comprehensive eligibility check with Availity Coverages API
  const runEligibilityCheck = async () => {
    setIsCheckingEligibility(true);
    try {
      const response = await apiPost(`/api/product-requests/${formData.id}/eligibility-check`, {});
      const result = await handleApiResponse(response);

      if (result.success) {
        // Transform Availity response to our expected format
        const eligibilityData = result.results || {};

        const eligibilityResult = {
          status: eligibilityData.status || 'needs_review',
          coverage_id: eligibilityData.coverage_id,
          control_number: eligibilityData.control_number,
          payer: eligibilityData.payer || {},
          benefits: {
            plans: eligibilityData.benefits?.plans || [],
            copay: eligibilityData.benefits?.copay_amount,
            deductible: eligibilityData.benefits?.deductible_amount,
            coinsurance: eligibilityData.benefits?.coinsurance_percentage,
            out_of_pocket_max: eligibilityData.benefits?.out_of_pocket_max
          },
          prior_authorization_required: eligibilityData.prior_authorization_required || false,
          coverage_details: eligibilityData.coverage_details || {},
          validation_messages: eligibilityData.validation_messages || [],
          checked_at: eligibilityData.checked_at || new Date().toISOString(),
          raw_response: eligibilityData.response_raw
        };

        setEligibilityResult(eligibilityResult);
        updateFormData({
          eligibility_results: eligibilityResult,
          eligibility_status: eligibilityResult.status,
          pre_auth_required_determination: eligibilityResult.prior_authorization_required ? 'required' : 'not_required'
        });
      } else {
        console.error('Eligibility check failed:', result.message);

        // Show fallback eligibility result
        const fallbackResult = {
          status: 'needs_review' as const,
          coverage_id: null,
          control_number: null,
          payer: {
            name: formData.payer_name || 'Unknown Payer'
          },
          benefits: {
            plans: [],
            copay: null,
            deductible: null,
            coinsurance: null,
            out_of_pocket_max: null
          },
          prior_authorization_required: false,
          coverage_details: {
            status: 'Manual review required - API unavailable'
          },
          validation_messages: [{
            field: 'eligibility_check',
            code: 'API_ERROR',
            errorMessage: result.message || 'Unable to verify eligibility automatically'
          }],
          checked_at: new Date().toISOString(),
          error: result.message
        };

        setEligibilityResult(fallbackResult);
        updateFormData({
          eligibility_results: fallbackResult,
          eligibility_status: fallbackResult.status,
          pre_auth_required_determination: 'pending'
        });
      }
    } catch (error) {
      console.error('Error during eligibility check:', error);

      // Show error state
      const errorResult = {
        status: 'needs_review' as const,
        coverage_id: null,
        control_number: null,
        payer: {
          name: formData.payer_name || 'Unknown Payer'
        },
        benefits: {
          plans: [],
          copay: null,
          deductible: null,
          coinsurance: null,
          out_of_pocket_max: null
        },
        prior_authorization_required: false,
        coverage_details: {
          status: 'Error occurred during eligibility verification'
        },
        validation_messages: [{
          field: 'eligibility_check',
          code: 'SYSTEM_ERROR',
          errorMessage: 'System error occurred during eligibility verification'
        }],
        checked_at: new Date().toISOString(),
        error: error instanceof Error ? error.message : 'Unknown error'
      };

      setEligibilityResult(errorResult);
      updateFormData({
        eligibility_results: errorResult,
        eligibility_status: errorResult.status,
        pre_auth_required_determination: 'pending'
      });
    } finally {
      setIsCheckingEligibility(false);
    }
  };

  // Load CMS data when component mounts
  useEffect(() => {
    loadCmsData();
  }, [userSpecialty, formData.facility?.state]);

  // Auto-run validation when form data changes
  useEffect(() => {
    if (autoValidationEnabled && formData.clinical_data && Object.keys(formData.clinical_data).length > 0) {
      const timer = setTimeout(() => {
        runMacValidation();
      }, 2000); // Debounce validation calls

      return () => clearTimeout(timer);
    }
  }, [formData.clinical_data, autoValidationEnabled]);

  // Get status color and icon
  const getStatusDisplay = (status: string) => {
    switch (status) {
      case 'passed':
        return {
          color: 'text-green-600',
          bgColor: 'bg-green-50',
          borderColor: 'border-green-200',
          icon: CheckCircle
        };
      case 'warning':
        return {
          color: 'text-yellow-600',
          bgColor: 'bg-yellow-50',
          borderColor: 'border-yellow-200',
          icon: AlertTriangle
        };
      case 'failed':
        return {
          color: 'text-red-600',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200',
          icon: AlertTriangle
        };
      default:
        return {
          color: 'text-gray-600',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200',
          icon: Clock
        };
    }
  };

  const renderCmsDataSection = () => {
    if (!cmsData) return null;

    return (
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div className="flex items-start">
          <Database className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div className="flex-1">
            <h3 className="text-sm font-medium text-blue-900">CMS Coverage Data Loaded</h3>
            <p className="text-sm text-blue-700 mt-1">
              Live CMS coverage data has been loaded for {userSpecialty.replace(/_/g, ' ')} in {cmsData.state}
            </p>
            <div className="mt-2 grid grid-cols-3 gap-4 text-xs">
              <div className="text-blue-700">
                <span className="font-medium">{cmsData.lcds.length}</span> LCDs
              </div>
              <div className="text-blue-700">
                <span className="font-medium">{cmsData.ncds.length}</span> NCDs
              </div>
              <div className="text-blue-700">
                <span className="font-medium">{cmsData.articles.length}</span> Articles
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderMacValidation = () => {
    const statusDisplay = validationResult ? getStatusDisplay(validationResult.status) : getStatusDisplay('pending');
    const StatusIcon = statusDisplay.icon;

    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-medium text-gray-900 flex items-center">
            <Shield className="h-5 w-5 mr-2 text-blue-600" />
            Pre-Order MAC Validation
            {validationResult?.pre_order_validation && (
              <span className="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                Pre-Order Check
              </span>
            )}
          </h3>
          <div className="flex items-center space-x-3">
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={autoValidationEnabled}
                onChange={(e) => setAutoValidationEnabled(e.target.checked)}
                className="mr-2"
              />
              <span className="text-sm text-gray-600">Auto-validate</span>
            </label>
            <button
              onClick={runMacValidation}
              disabled={isValidating}
              className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {isValidating ? (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <Shield className="h-4 w-4 mr-2" />
              )}
              {isValidating ? 'Validating...' : 'Run Pre-Order Validation'}
            </button>
          </div>
        </div>

        {/* Validation Status */}
        <div className={`rounded-lg border p-4 ${statusDisplay.bgColor} ${statusDisplay.borderColor}`}>
          <div className="flex items-start">
            <StatusIcon className={`h-5 w-5 mt-0.5 mr-3 ${statusDisplay.color}`} />
            <div className="flex-1">
              <h4 className={`text-sm font-medium ${statusDisplay.color}`}>
                {validationResult ? (
                  <>
                    Pre-Order MAC Validation {validationResult.status.charAt(0).toUpperCase() + validationResult.status.slice(1)}
                    {validationResult.score && (
                      <span className="ml-2 text-xs">
                        (Compliance Score: {validationResult.score}/100)
                      </span>
                    )}
                  </>
                ) : (
                  'Pre-Order MAC Validation Pending'
                )}
              </h4>
              {validationResult && (
                <div className="mt-2 text-sm text-gray-700 space-y-1">
                  <p><strong>MAC Contractor:</strong> {validationResult.mac_contractor}</p>
                  <p><strong>Jurisdiction:</strong> {validationResult.jurisdiction}</p>
                  <p><strong>Validation Type:</strong> {validationResult.validation_type?.replace(/_/g, ' ')}</p>
                  {validationResult.pre_order_validation && (
                    <p className="text-blue-700 font-medium">
                      ✓ Validated before order creation - compliance verified upfront
                    </p>
                  )}
                  {validationResult.reimbursement_risk && (
                    <p><strong>Reimbursement Risk:</strong>
                      <span className={`ml-1 font-medium ${
                        validationResult.reimbursement_risk === 'low' ? 'text-green-600' :
                        validationResult.reimbursement_risk === 'medium' ? 'text-yellow-600' : 'text-red-600'
                      }`}>
                        {validationResult.reimbursement_risk.toUpperCase()}
                      </span>
                    </p>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* CMS Compliance Section */}
        {validationResult?.cms_compliance && (
          <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
            <div className="flex items-start">
              <FileText className="h-5 w-5 text-indigo-600 mt-0.5 mr-3 flex-shrink-0" />
              <div className="flex-1">
                <h4 className="text-sm font-medium text-indigo-900">CMS Coverage Compliance</h4>
                <div className="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div>
                    <span className="text-indigo-700 font-medium">{validationResult.cms_compliance.lcds_checked}</span>
                    <span className="text-indigo-600 ml-1">LCDs Checked</span>
                  </div>
                  <div>
                    <span className="text-indigo-700 font-medium">{validationResult.cms_compliance.ncds_checked}</span>
                    <span className="text-indigo-600 ml-1">NCDs Checked</span>
                  </div>
                  <div>
                    <span className="text-indigo-700 font-medium">{validationResult.cms_compliance.articles_checked}</span>
                    <span className="text-indigo-600 ml-1">Articles Checked</span>
                  </div>
                  <div>
                    <span className="text-indigo-700 font-medium">{validationResult.cms_compliance.compliance_score}%</span>
                    <span className="text-indigo-600 ml-1">CMS Compliance</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Validation Requirements */}
        {validationResult && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {Object.entries(validationResult.requirements_met).map(([requirement, met]) => (
              <div key={requirement} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-sm font-medium text-gray-700 capitalize">
                  {requirement.replace(/_/g, ' ')}
                </span>
                <div className="flex items-center">
                  {met ? (
                    <CheckCircle className="h-4 w-4 text-green-600" />
                  ) : (
                    <AlertTriangle className="h-4 w-4 text-red-600" />
                  )}
                  <span className={`ml-2 text-xs ${met ? 'text-green-600' : 'text-red-600'}`}>
                    {met ? 'Met' : 'Not Met'}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Daily Monitoring Status */}
        {validationResult?.daily_monitoring_enabled && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-4">
            <div className="flex items-start">
              <CheckCircle className="h-5 w-5 text-green-600 mt-0.5 mr-3 flex-shrink-0" />
              <div>
                <h4 className="text-sm font-medium text-green-900">Daily Monitoring Enabled</h4>
                <p className="text-sm text-green-700 mt-1">
                  This validation will be automatically monitored daily for compliance changes and policy updates.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Validation Issues */}
        {validationResult?.issues && validationResult.issues.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium text-gray-900">Issues & Recommendations</h4>
            {validationResult.issues.map((issue, index) => (
              <div
                key={index}
                className={`p-4 rounded-lg border ${
                  issue.type === 'error'
                    ? 'bg-red-50 border-red-200'
                    : issue.type === 'warning'
                    ? 'bg-yellow-50 border-yellow-200'
                    : 'bg-blue-50 border-blue-200'
                }`}
              >
                <div className="flex items-start">
                  <AlertTriangle
                    className={`h-4 w-4 mt-0.5 mr-3 ${
                      issue.type === 'error'
                        ? 'text-red-600'
                        : issue.type === 'warning'
                        ? 'text-yellow-600'
                        : 'text-blue-600'
                    }`}
                  />
                  <div className="flex-1">
                    <p className="text-sm font-medium text-gray-900">{issue.message}</p>
                    {issue.resolution && (
                      <p className="mt-1 text-sm text-gray-600">
                        <strong>Resolution:</strong> {issue.resolution}
                      </p>
                    )}
                    <div className="mt-2 flex space-x-4">
                      {issue.lcd_reference && (
                        <a
                          href={issue.lcd_reference}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center text-xs text-blue-600 hover:text-blue-800"
                        >
                          View LCD Reference
                          <ExternalLink className="h-3 w-3 ml-1" />
                        </a>
                      )}
                      {issue.cms_document_id && (
                        <span className="text-xs text-gray-500">
                          CMS Doc: {issue.cms_document_id}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  const renderEligibilityCheck = () => {
    const statusDisplay = eligibilityResult ? getStatusDisplay(eligibilityResult.status) : getStatusDisplay('pending');
    const StatusIcon = statusDisplay.icon;

    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-medium text-gray-900 flex items-center">
            <CheckCircle className="h-5 w-5 mr-2 text-green-600" />
            Insurance Eligibility
          </h3>
          <button
            onClick={runEligibilityCheck}
            disabled={isCheckingEligibility || !formData.patient_api_input?.member_id}
            className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
          >
            {isCheckingEligibility ? (
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
            ) : (
              <CheckCircle className="h-4 w-4 mr-2" />
            )}
            {isCheckingEligibility ? 'Checking...' : 'Check Eligibility'}
          </button>
        </div>

        {/* Eligibility Status */}
        <div className={`rounded-lg border p-4 ${statusDisplay.bgColor} ${statusDisplay.borderColor}`}>
          <div className="flex items-start">
            <StatusIcon className={`h-5 w-5 mt-0.5 mr-3 ${statusDisplay.color}`} />
            <div className="flex-1">
              <h4 className={`text-sm font-medium ${statusDisplay.color}`}>
                {eligibilityResult ? (
                  `Eligibility ${eligibilityResult.status.charAt(0).toUpperCase() + eligibilityResult.status.slice(1).replace('_', ' ')}`
                ) : (
                  'Eligibility Check Pending'
                )}
              </h4>
              {eligibilityResult?.coverage_details && (
                <p className="mt-1 text-sm text-gray-700">{eligibilityResult.coverage_details}</p>
              )}
            </div>
          </div>
        </div>

        {/* Benefits Information */}
        {eligibilityResult?.benefits && (
          <div className="space-y-4">
            <h4 className="text-sm font-medium text-gray-900">Coverage Benefits</h4>

            {/* Payer Information */}
            {eligibilityResult.payer && (
              <div className="bg-gray-50 rounded-lg p-3">
                <h5 className="text-sm font-medium text-gray-700 mb-2">Payer Information</h5>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                  {eligibilityResult.payer.name && (
                    <div><span className="font-medium">Name:</span> {eligibilityResult.payer.name}</div>
                  )}
                  {eligibilityResult.payer.response_name && (
                    <div><span className="font-medium">Response Name:</span> {eligibilityResult.payer.response_name}</div>
                  )}
                  {eligibilityResult.payer.id && (
                    <div><span className="font-medium">Payer ID:</span> {eligibilityResult.payer.id}</div>
                  )}
                  {eligibilityResult.control_number && (
                    <div><span className="font-medium">Control Number:</span> {eligibilityResult.control_number}</div>
                  )}
                </div>
              </div>
            )}

            {/* Plans Information */}
            {eligibilityResult.benefits.plans && eligibilityResult.benefits.plans.length > 0 && (
              <div className="bg-gray-50 rounded-lg p-3">
                <h5 className="text-sm font-medium text-gray-700 mb-2">Plan Information</h5>
                {eligibilityResult.benefits.plans.map((plan: any, index: number) => (
                  <div key={index} className="border-b border-gray-200 last:border-b-0 pb-2 mb-2 last:mb-0">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                      {plan.plan_name && (
                        <div><span className="font-medium">Plan:</span> {plan.plan_name}</div>
                      )}
                      {plan.group_number && (
                        <div><span className="font-medium">Group:</span> {plan.group_number}</div>
                      )}
                      {plan.insurance_type && (
                        <div><span className="font-medium">Type:</span> {plan.insurance_type}</div>
                      )}
                      {plan.effective_date && (
                        <div><span className="font-medium">Effective:</span> {new Date(plan.effective_date).toLocaleDateString()}</div>
                      )}
                      {plan.termination_date && (
                        <div><span className="font-medium">Expires:</span> {new Date(plan.termination_date).toLocaleDateString()}</div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Financial Benefits */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {eligibilityResult.benefits.copay !== null && eligibilityResult.benefits.copay !== undefined && (
                <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                  <span className="text-sm font-medium text-gray-700">Copay</span>
                  <span className="text-sm text-gray-900">${eligibilityResult.benefits.copay.toFixed(2)}</span>
                </div>
              )}
              {eligibilityResult.benefits.deductible !== null && eligibilityResult.benefits.deductible !== undefined && (
                <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                  <span className="text-sm font-medium text-gray-700">Deductible</span>
                  <span className="text-sm text-gray-900">${eligibilityResult.benefits.deductible.toFixed(2)}</span>
                </div>
              )}
              {eligibilityResult.benefits.coinsurance !== null && eligibilityResult.benefits.coinsurance !== undefined && (
                <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                  <span className="text-sm font-medium text-gray-700">Coinsurance</span>
                  <span className="text-sm text-gray-900">{eligibilityResult.benefits.coinsurance}%</span>
                </div>
              )}
              {eligibilityResult.benefits.out_of_pocket_max !== null && eligibilityResult.benefits.out_of_pocket_max !== undefined && (
                <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                  <span className="text-sm font-medium text-gray-700">Out of Pocket Max</span>
                  <span className="text-sm text-gray-900">${eligibilityResult.benefits.out_of_pocket_max.toFixed(2)}</span>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Validation Messages */}
        {eligibilityResult?.validation_messages && eligibilityResult.validation_messages.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium text-gray-900">Validation Messages</h4>
            {eligibilityResult.validation_messages.map((message: any, index: number) => (
              <div key={index} className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div className="flex items-start">
                  <AlertTriangle className="h-4 w-4 text-yellow-600 mt-0.5 mr-3" />
                  <div className="flex-1">
                    <p className="text-sm font-medium text-yellow-900">
                      {message.code || 'Validation Message'}
                    </p>
                    <p className="text-sm text-yellow-700">{message.errorMessage || message.message}</p>
                    {message.field && (
                      <p className="text-xs text-yellow-600 mt-1">Field: {message.field}</p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Coverage Details */}
        {eligibilityResult?.coverage_details && (
          <div className="bg-gray-50 rounded-lg p-3">
            <h5 className="text-sm font-medium text-gray-700 mb-2">Coverage Details</h5>
            <div className="text-sm space-y-1">
              {eligibilityResult.coverage_details.status && (
                <div><span className="font-medium">Status:</span> {eligibilityResult.coverage_details.status}</div>
              )}
              {eligibilityResult.coverage_details.as_of_date && (
                <div><span className="font-medium">As of Date:</span> {new Date(eligibilityResult.coverage_details.as_of_date).toLocaleDateString()}</div>
              )}
              {eligibilityResult.coverage_details.to_date && (
                <div><span className="font-medium">To Date:</span> {new Date(eligibilityResult.coverage_details.to_date).toLocaleDateString()}</div>
              )}
              {eligibilityResult.checked_at && (
                <div><span className="font-medium">Checked:</span> {new Date(eligibilityResult.checked_at).toLocaleString()}</div>
              )}
            </div>
          </div>
        )}

        {/* Prior Authorization */}
        {eligibilityResult?.prior_authorization_required && (
          <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex items-start">
              <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5 mr-3" />
              <div>
                <h4 className="text-sm font-medium text-yellow-900">Prior Authorization Required</h4>
                <p className="text-sm text-yellow-700 mt-1">
                  This service requires prior authorization from the insurance provider before proceeding.
                </p>
                <button className="mt-2 inline-flex items-center px-3 py-2 border border-yellow-300 shadow-sm text-sm leading-4 font-medium rounded-md text-yellow-700 bg-yellow-50 hover:bg-yellow-100">
                  Initiate Prior Authorization
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="space-y-8">
      <div>
        <h2 className="text-lg sm:text-xl font-semibold text-gray-900 mb-4">Validation & Eligibility</h2>
        <p className="text-sm text-gray-600 mb-6">
          Comprehensive Medicare MAC validation with live CMS coverage data and insurance eligibility verification
          ensure compliance and coverage before submission.
        </p>
      </div>

      {/* Information Banner */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h3 className="text-sm font-medium text-blue-900">Pre-Order MAC Validation Process</h3>
            <p className="text-sm text-blue-700 mt-1">
              Our system validates your product request against Medicare MAC requirements using live CMS coverage data
              (LCDs, NCDs, Articles) <strong>before</strong> creating an order. This proactive approach prevents claim denials
              and ensures compliance from the start.
            </p>
            {isLoadingCmsData && (
              <div className="mt-2 flex items-center text-sm text-blue-600">
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Loading CMS coverage data for validation...
              </div>
            )}
          </div>
        </div>
      </div>

      {/* CMS Data Section */}
      {renderCmsDataSection()}

      {/* MAC Validation Section */}
      {renderMacValidation()}

      {/* Eligibility Check Section */}
      {renderEligibilityCheck()}

      {/* Summary */}
      {(validationResult || eligibilityResult) && (
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-sm font-medium text-gray-900 mb-3">Pre-Order Validation Summary</h4>
          <div className="space-y-2 text-sm">
            {validationResult && (
              <>
                <div className="flex items-center justify-between">
                  <span>Pre-Order MAC Validation:</span>
                  <span className={`font-medium ${getStatusDisplay(validationResult.status).color}`}>
                    {validationResult.status.charAt(0).toUpperCase() + validationResult.status.slice(1)}
                  </span>
                </div>
                {validationResult.cms_compliance && (
                  <div className="flex items-center justify-between">
                    <span>CMS Compliance Score:</span>
                    <span className="font-medium text-indigo-600">
                      {validationResult.cms_compliance.compliance_score}%
                    </span>
                  </div>
                )}
                {validationResult.reimbursement_risk && (
                  <div className="flex items-center justify-between">
                    <span>Reimbursement Risk:</span>
                    <span className={`font-medium ${
                      validationResult.reimbursement_risk === 'low' ? 'text-green-600' :
                      validationResult.reimbursement_risk === 'medium' ? 'text-yellow-600' : 'text-red-600'
                    }`}>
                      {validationResult.reimbursement_risk.toUpperCase()}
                    </span>
                  </div>
                )}
                {validationResult.pre_order_validation && validationResult.status === 'passed' && (
                  <div className="mt-3 p-2 bg-green-50 border border-green-200 rounded">
                    <p className="text-green-700 text-xs font-medium">
                      ✓ Ready for order creation - all MAC requirements validated
                    </p>
                  </div>
                )}
              </>
            )}
            {eligibilityResult && (
              <div className="flex items-center justify-between">
                <span>Insurance Eligibility:</span>
                <span className={`font-medium ${getStatusDisplay(eligibilityResult.status).color}`}>
                  {eligibilityResult.status.charAt(0).toUpperCase() + eligibilityResult.status.slice(1).replace('_', ' ')}
                </span>
              </div>
            )}
            {eligibilityResult?.prior_authorization_required && (
              <div className="flex items-center justify-between">
                <span>Prior Authorization:</span>
                <span className="font-medium text-yellow-600">Required</span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default ValidationEligibilityStep;
