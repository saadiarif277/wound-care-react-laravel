import React, { useState, useEffect } from 'react';
import { AlertTriangle, CheckCircle, Clock, Shield, Info, Loader2, ExternalLink } from 'lucide-react';

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
  issues: Array<{
    type: 'error' | 'warning' | 'info';
    message: string;
    resolution?: string;
    lcd_reference?: string;
  }>;
  requirements_met: {
    coverage: boolean;
    documentation: boolean;
    frequency: boolean;
    medical_necessity: boolean;
    billing_compliance: boolean;
    prior_authorization: boolean;
  };
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

  // Run MAC validation
  const runMacValidation = async () => {
    setIsValidating(true);
    try {
      const response = await fetch('/api/v1/validation-builder/validate-order', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          patient_data: formData.patient_api_input,
          clinical_data: formData.clinical_data,
          wound_type: formData.wound_type,
          facility_id: formData.facility_id,
          expected_service_date: formData.expected_service_date,
          provider_specialty: userSpecialty,
          selected_products: formData.selected_products,
        }),
      });

      const result = await response.json();

      if (result.success) {
        setValidationResult(result.data);
        updateFormData({
          mac_validation_results: result.data,
          mac_validation_status: result.data.status
        });
      } else {
        console.error('Validation failed:', result.message);
      }
    } catch (error) {
      console.error('MAC validation error:', error);
    } finally {
      setIsValidating(false);
    }
  };

  // Run eligibility check
  const runEligibilityCheck = async () => {
    setIsCheckingEligibility(true);
    try {
      const response = await fetch('/api/v1/eligibility/check', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          patient_data: formData.patient_api_input,
          payer_name: formData.payer_name,
          service_date: formData.expected_service_date,
          procedure_codes: formData.selected_products?.map((p: any) => p.hcpcs_code) || [],
        }),
      });

      const result = await response.json();

      if (result.success) {
        setEligibilityResult(result.data);
        updateFormData({
          eligibility_results: result.data,
          eligibility_status: result.data.status
        });
      } else {
        console.error('Eligibility check failed:', result.message);
      }
    } catch (error) {
      console.error('Eligibility check error:', error);
    } finally {
      setIsCheckingEligibility(false);
    }
  };

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

  const renderMacValidation = () => {
    const statusDisplay = validationResult ? getStatusDisplay(validationResult.status) : getStatusDisplay('pending');
    const StatusIcon = statusDisplay.icon;

    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-medium text-gray-900 flex items-center">
            <Shield className="h-5 w-5 mr-2 text-blue-600" />
            MAC Validation
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
              {isValidating ? 'Validating...' : 'Run Validation'}
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
                    MAC Validation {validationResult.status.charAt(0).toUpperCase() + validationResult.status.slice(1)}
                    {validationResult.score && (
                      <span className="ml-2 text-xs">
                        (Score: {validationResult.score}/100)
                      </span>
                    )}
                  </>
                ) : (
                  'Validation Pending'
                )}
              </h4>
              {validationResult && (
                <div className="mt-2 text-sm text-gray-700">
                  <p><strong>MAC Contractor:</strong> {validationResult.mac_contractor}</p>
                  <p><strong>Jurisdiction:</strong> {validationResult.jurisdiction}</p>
                </div>
              )}
            </div>
          </div>
        </div>

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
                    {issue.lcd_reference && (
                      <div className="mt-2">
                        <a
                          href={issue.lcd_reference}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center text-xs text-blue-600 hover:text-blue-800"
                        >
                          View LCD Reference
                          <ExternalLink className="h-3 w-3 ml-1" />
                        </a>
                      </div>
                    )}
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
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {Object.entries(eligibilityResult.benefits).map(([benefit, value]) => (
              value !== undefined && (
                <div key={benefit} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <span className="text-sm font-medium text-gray-700 capitalize">
                    {benefit.replace(/_/g, ' ')}
                  </span>
                  <span className="text-sm text-gray-900">
                    {typeof value === 'number' ? `$${value.toFixed(2)}` : value}
                  </span>
                </div>
              )
            ))}
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
          Automated MAC validation and insurance eligibility verification ensure compliance and coverage before submission.
        </p>
      </div>

      {/* Information Banner */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
          <div>
            <h3 className="text-sm font-medium text-blue-900">Automated Validation Process</h3>
            <p className="text-sm text-blue-700 mt-1">
              Our system automatically validates your request against Medicare MAC requirements and checks insurance eligibility
              to reduce claim denials and ensure proper reimbursement.
            </p>
          </div>
        </div>
      </div>

      {/* MAC Validation Section */}
      {renderMacValidation()}

      {/* Eligibility Check Section */}
      {renderEligibilityCheck()}

      {/* Summary */}
      {(validationResult || eligibilityResult) && (
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-sm font-medium text-gray-900 mb-3">Validation Summary</h4>
          <div className="space-y-2 text-sm">
            {validationResult && (
              <div className="flex items-center justify-between">
                <span>MAC Validation:</span>
                <span className={`font-medium ${getStatusDisplay(validationResult.status).color}`}>
                  {validationResult.status.charAt(0).toUpperCase() + validationResult.status.slice(1)}
                </span>
              </div>
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
