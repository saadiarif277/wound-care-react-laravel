import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { prepareDocuSealData } from './docusealUtils';
import { FiCheckCircle, FiAlertCircle, FiFileText, FiArrowRight } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { DocuSealEmbed } from '@/Components/QuickRequest/DocuSealEmbed';
import { getManufacturerByProduct, getManufacturerConfig } from '../manufacturerFields';
import axios from 'axios';


interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
}

interface FormData {
  // Patient Information
  patient_first_name?: string;
  patient_last_name?: string;
  patient_dob?: string;
  patient_gender?: string;
  patient_member_id?: string;
  patient_address_line1?: string;
  patient_address_line2?: string;
  patient_city?: string;
  patient_state?: string;
  patient_zip?: string;
  patient_phone?: string;
  patient_email?: string;

  // Provider Information
  provider_id?: number | null;
  provider_name?: string;
  provider_email?: string;
  provider_npi?: string;
  facility_name?: string;

  // Product Selection
  selected_products?: SelectedProduct[];
  manufacturer_fields?: Record<string, any>;

  // Clinical Information
  wound_type?: string;
  wound_location?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  primary_diagnosis_code?: string;
  secondary_diagnosis_code?: string;
  diagnosis_code?: string;

  // New duration fields
  wound_duration_days?: string;
  wound_duration_weeks?: string;
  wound_duration_months?: string;
  wound_duration_years?: string;

  // Prior application fields
  prior_applications?: string;
  prior_application_product?: string;
  prior_application_within_12_months?: boolean;

  // Hospice fields
  hospice_status?: boolean;
  hospice_family_consent?: boolean;
  hospice_clinically_necessary?: boolean;

  // Insurance
  primary_insurance_name?: string;
  primary_member_id?: string;
  primary_plan_type?: string;

  // DocuSeal
  docuseal_submission_id?: string;
  episode_id?: string;

  [key: string]: any;
}

interface Step7Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    manufacturer_id?: number;
    available_sizes?: any;
    price_per_sq_cm?: number;
  }>;
  providers?: Array<{
    id: number;
    name: string;
    credentials?: string;
    npi?: string;
  }>;
  facilities?: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  errors: Record<string, string>;
}

export default function Step7DocuSealIVR({
  formData,
  updateFormData,
  products,
  providers = [],
  facilities = [],
  errors
}: Step7Props) {
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const [isCompleted, setIsCompleted] = useState(false);
  const [submissionError, setSubmissionError] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [enhancedSubmission, setEnhancedSubmission] = useState<any>(null);
  const [redirectTimeout, setRedirectTimeout] = useState<number | null>(null);

  // Get the selected product
  const getSelectedProduct = () => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return null;
    }

    const firstProduct = formData.selected_products[0];
    if (!firstProduct?.product_id) {
      return null;
    }

    return products.find(p => p.id === firstProduct.product_id);
  };

  const selectedProduct = getSelectedProduct();
  let manufacturerConfig = selectedProduct ? getManufacturerByProduct(selectedProduct.name) : null;

  // If no config found by product name, try by manufacturer name
  if (!manufacturerConfig && selectedProduct?.manufacturer) {
    manufacturerConfig = getManufacturerConfig(selectedProduct.manufacturer);
  }

  // Debug logging
  console.log('Selected Product:', {
    name: selectedProduct?.name,
    manufacturer: selectedProduct?.manufacturer,
    manufacturer_id: selectedProduct?.manufacturer_id,
    code: selectedProduct?.code
  });
  console.log('Manufacturer Config found:', manufacturerConfig);
  console.log('Signature Required:', manufacturerConfig?.signatureRequired);

  // Get provider and facility details
  const provider = formData.provider_id ? providers.find(p => p.id === formData.provider_id) : null;
  const facility = formData.facility_id ? facilities.find(f => f.id === formData.facility_id) : null;

  // Build DocuSeal payload using shared utility. This data will be passed to DocuSeal.
  const preparedDocuSealData = prepareDocuSealData({ formData, products, providers, facilities });

  // Build extended payload for DocuSeal
  const productDetails = formData.selected_products?.map((item: any) => {
    const prod = products.find(p => p.id === item.product_id);
    return {
      name: prod?.name || '',
      code: prod?.code || '',
      size: item.size || 'Standard',
      quantity: item.quantity,
      manufacturer: prod?.manufacturer || '',
      manufacturer_id: prod?.manufacturer_id || prod?.id // Include manufacturer_id
    };
  }) || [];

  // Calculate total wound size
  const woundSizeLength = parseFloat(formData.wound_size_length || '0');
  const woundSizeWidth = parseFloat(formData.wound_size_width || '0');
  const totalWoundSize = woundSizeLength * woundSizeWidth;

  // Format wound duration
  const durationParts: string[] = [];
  if (formData.wound_duration_years) durationParts.push(`${formData.wound_duration_years} years`);
  if (formData.wound_duration_months) durationParts.push(`${formData.wound_duration_months} months`);
  if (formData.wound_duration_weeks) durationParts.push(`${formData.wound_duration_weeks} weeks`);
  if (formData.wound_duration_days) durationParts.push(`${formData.wound_duration_days} days`);
  const woundDuration = durationParts.length > 0 ? durationParts.join(', ') : 'Not specified';

  // Format diagnosis codes
  let diagnosisCodeDisplay = '';
  if (formData.primary_diagnosis_code && formData.secondary_diagnosis_code) {
    diagnosisCodeDisplay = `Primary: ${formData.primary_diagnosis_code}, Secondary: ${formData.secondary_diagnosis_code}`;
  } else if (formData.diagnosis_code) {
    diagnosisCodeDisplay = formData.diagnosis_code;
  }

  // Merge everything into a single payload object to send to DocuSeal
  const extendedDocuSealData = {
    ...formData,

    // Provider Information
    provider_name: provider?.name || formData.provider_name || '',
    provider_credentials: provider?.credentials || '',
    provider_npi: provider?.npi || formData.provider_npi || '',
    provider_email: formData.provider_email || 'provider@example.com',

    // Facility Information
    facility_name: facility?.name || formData.facility_name || '',
    facility_address: facility?.address || '',

    // Product Information
    product_name: selectedProduct?.name,
    product_code: selectedProduct?.code,
    product_manufacturer: selectedProduct?.manufacturer,
    manufacturer_id: selectedProduct?.manufacturer_id,
    product_details: productDetails,
    product_details_text: productDetails.map((p: any) =>
      `${p.name} (${p.code}) - Size: ${p.size}, Qty: ${p.quantity}`
    ).join('\n'),

    // Clinical Information
    total_wound_size: `${totalWoundSize} sq cm`,
    wound_dimensions: `${formData.wound_size_length || '0'} × ${formData.wound_size_width || '0'} × ${formData.wound_size_depth || '0'} cm`,
    wound_duration: woundDuration,
    wound_duration_days: formData.wound_duration_days || '',
    wound_duration_weeks: formData.wound_duration_weeks || '',
    wound_duration_months: formData.wound_duration_months || '',
    wound_duration_years: formData.wound_duration_years || '',

    // Diagnosis codes
    diagnosis_codes_display: diagnosisCodeDisplay,
    primary_diagnosis_code: formData.primary_diagnosis_code || '',
    secondary_diagnosis_code: formData.secondary_diagnosis_code || '',
    diagnosis_code: formData.diagnosis_code || '',

    // Prior applications
    prior_applications: formData.prior_applications || '0',
    prior_application_product: formData.prior_application_product || '',
    prior_application_within_12_months: formData.prior_application_within_12_months ? 'Yes' : 'No',

    // Hospice information
    hospice_status: formData.hospice_status ? 'Yes' : 'No',
    hospice_family_consent: formData.hospice_family_consent ? 'Yes' : 'No',
    hospice_clinically_necessary: formData.hospice_clinically_necessary ? 'Yes' : 'No',

    // Manufacturer Fields (convert booleans to Yes/No for display)
    ...Object.entries(formData.manufacturer_fields || {}).reduce((acc, [key, value]) => {
      acc[key] = typeof value === 'boolean' ? (value ? 'Yes' : 'No') : value;
      return acc;
    }, {} as Record<string, any>),

    // Date fields
    service_date: formData.expected_service_date || new Date().toISOString().split('T')[0],

    // Signature fields (for DocuSeal template)
    provider_signature_required: true,
    provider_signature_date: new Date().toISOString().split('T')[0]
  };

  // Override preparedDocuSealData with the extended version
  Object.assign(preparedDocuSealData, extendedDocuSealData);

  // Enhanced completion handler with FHIR integration and redirect
  const handleDocuSealComplete = async (submissionData: any) => {
    setIsProcessing(true);
    
    try {
      console.log('🎉 DocuSeal form completed:', submissionData);
      
      const submissionId = submissionData.submission_id || submissionData.id || 'unknown';
      
      // Update form data immediately
      updateFormData({ 
        docuseal_submission_id: submissionId,
        ivr_completed_at: new Date().toISOString() 
      });
      
      // Call our enhanced service to finalize the submission
      const response = await axios.post('/api/v1/enhanced-docuseal/finalize-submission', {
        submission_id: submissionId,
        episode_id: formData.episode_id,
        form_data: formData,
        completion_data: submissionData
      });
      
      if (response.data.success) {
        setEnhancedSubmission(response.data);
        setIsCompleted(true);
        
        // Redirect to order summary after 3 seconds
        const timeout = setTimeout(() => {
          router.visit(`/quick-request/order-summary/${response.data.order_id}`, {
            method: 'get',
            data: {
              submission_id: submissionId,
              episode_id: formData.episode_id
            }
          });
        }, 3000);
        
        setRedirectTimeout(timeout);
      } else {
        console.error('Enhanced submission processing failed:', response.data.error);
        setIsCompleted(true); // Still mark as completed, but show warning
      }
      
    } catch (error) {
      console.error('Error processing DocuSeal completion:', error);
      setSubmissionError('Form completed but there was an error processing the submission.');
    } finally {
      setIsProcessing(false);
    }
  };
  
  const handleDocuSealError = (error: string) => {
    setSubmissionError(error);
    setIsProcessing(false);
  };
  
  const handleManualRedirect = () => {
    if (redirectTimeout) {
      clearTimeout(redirectTimeout);
    }
    
    const orderId = enhancedSubmission?.order_id || formData.episode_id || 'unknown';
    router.visit(`/quick-request/order-summary/${orderId}`, {
      method: 'get',
      data: {
        submission_id: formData.docuseal_submission_id,
        episode_id: formData.episode_id
      }
    });
  };
  
  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (redirectTimeout) {
        clearTimeout(redirectTimeout);
      }
    };
  }, [redirectTimeout]);

  // No product selected
  if (!selectedProduct) {
    return (
      <div className={cn("text-center py-12", t.text.secondary)}>
        <p>Please select a product first</p>
      </div>
    );
  }

  // No IVR required for this manufacturer
  if (!manufacturerConfig || !manufacturerConfig?.signatureRequired) {
    // Set a placeholder submission ID when IVR is not required
    React.useEffect(() => {
      if (!formData.docuseal_submission_id) {
        updateFormData({ docuseal_submission_id: 'NO_IVR_REQUIRED' });
      }
    }, []);

    return (
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <FiCheckCircle className={cn("h-12 w-12 mx-auto mb-4 text-green-500")} />
        <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>
          No IVR Required
        </h3>
        <p className={cn("text-sm", t.text.secondary)}>
          {selectedProduct?.name} does not require an IVR form submission.
        </p>
        <p className={cn("text-sm mt-2", t.text.secondary)}>
          You can proceed to submit your order.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className={cn("p-4 rounded-lg", t.glass.card)}>
        <div className="flex items-start">
          <FiFileText className={cn("h-5 w-5 mt-0.5 flex-shrink-0 mr-3", t.text.secondary)} />
          <div>
            <h3 className={cn("text-lg font-medium", t.text.primary)}>
              Independent Verification Request (IVR)
            </h3>
            <p className={cn("text-sm mt-1", t.text.secondary)}>
              {manufacturerConfig?.name} requires an electronic signature on their IVR form
            </p>
          </div>
        </div>
      </div>

      {/* Instructions */}
      {!isCompleted && !submissionError && (
        <div className={cn(
          "p-4 rounded-lg border",
          theme === 'dark'
            ? 'bg-blue-900/20 border-blue-800'
            : 'bg-blue-50 border-blue-200'
        )}>
          <div className="flex items-start">
            <FiAlertCircle className={cn(
              "h-5 w-5 mt-0.5 flex-shrink-0 mr-3",
              theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
            )} />
            <div>
              <h4 className={cn(
                "text-sm font-medium",
                theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
              )}>
                Please Review and Sign
              </h4>
              <ul className={cn(
                "mt-2 space-y-1 text-sm",
                theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
              )}>
                <li>• All order details have been pre-filled in the form</li>
                <li>• Review the information for accuracy</li>
                <li>• Sign electronically where indicated</li>
                <li>• Click "Complete" when finished</li>
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* DocuSeal Form or Completion Status */}
      <div className={cn("rounded-lg", t.glass.card)}>
        {isCompleted ? (
          <div className="p-8 text-center">
            <FiCheckCircle className={cn("h-16 w-16 mx-auto mb-4 text-green-500")} />
            <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
              IVR Form Completed Successfully
            </h3>
            <p className={cn("text-sm", t.text.secondary)}>
              The IVR form has been signed and submitted.
            </p>
            
            {/* Enhanced submission info */}
            {enhancedSubmission && (
              <div className={cn("mt-4 p-3 rounded-lg", 
                theme === 'dark' ? 'bg-green-900/20 border border-green-800' : 'bg-green-50 border border-green-200'
              )}>
                <div className="text-sm space-y-1">
                  <p className={cn("font-medium", t.text.primary)}>
                    Order #{enhancedSubmission.order_id || 'N/A'}
                  </p>
                  <p className={t.text.secondary}>
                    FHIR Integration: {enhancedSubmission.fhir_data_used || 0} fields mapped
                  </p>
                  <p className={t.text.secondary}>
                    Template: {enhancedSubmission.template_name || 'Standard IVR'}
                  </p>
                </div>
              </div>
            )}
            
            <p className={cn("text-sm mt-3", t.text.secondary)}>
              Submission ID: <span className="font-mono">{formData.docuseal_submission_id}</span>
            </p>
            
            {/* Redirect countdown */}
            <div className={cn("mt-6 p-4 rounded-lg border", 
              theme === 'dark' ? 'bg-blue-900/20 border-blue-800' : 'bg-blue-50 border-blue-200'
            )}>
              <p className={cn("text-sm font-medium mb-2", 
                theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
              )}>
                Redirecting to Order Summary...
              </p>
              <p className={cn("text-xs", 
                theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
              )}>
                You will be automatically redirected to view your complete order details.
              </p>
              
              <button
                onClick={handleManualRedirect}
                className={cn(
                  "mt-3 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                  theme === 'dark' 
                    ? 'bg-blue-700 hover:bg-blue-600 text-white' 
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                )}
              >
                View Order Summary Now
                <FiArrowRight className="ml-2 h-4 w-4" />
              </button>
            </div>
          </div>
        ) : submissionError ? (
          <div className="p-8">
            <div className={cn(
              "p-4 rounded-lg border",
              theme === 'dark'
                ? 'bg-red-900/20 border-red-800'
                : 'bg-red-50 border-red-200'
            )}>
              <div className="flex items-start">
                <FiAlertCircle className={cn(
                  "h-5 w-5 mt-0.5 flex-shrink-0 mr-3",
                  theme === 'dark' ? 'text-red-400' : 'text-red-600'
                )} />
                <div>
                  <h4 className={cn(
                    "text-sm font-medium",
                    theme === 'dark' ? 'text-red-300' : 'text-red-900'
                  )}>
                    Error Loading IVR Form
                  </h4>
                  <p className={cn(
                    "text-sm mt-1",
                    theme === 'dark' ? 'text-red-400' : 'text-red-700'
                  )}>
                    {submissionError}
                  </p>
                  <button
                    onClick={() => {
                      setSubmissionError(null);
                      setIsCompleted(false);
                    }}
                    className={cn(
                      "mt-3 text-sm underline",
                      theme === 'dark' ? 'text-red-300' : 'text-red-600'
                    )}
                  >
                    Try Again
                  </button>
                </div>
              </div>
            </div>
          </div>
        ) : (
          <div className="relative">
            {/* Processing overlay */}
            {isProcessing && (
              <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10 rounded-lg">
                <div className="bg-white p-6 rounded-lg text-center max-w-sm">
                  <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mx-auto mb-3" />
                  <p className="text-gray-900 font-medium">Processing Submission...</p>
                  <p className="text-gray-600 text-sm mt-1">Integrating with FHIR and creating your order</p>
                </div>
              </div>
            )}
            
            <DocuSealEmbed
              manufacturerId={selectedProduct?.manufacturer_id?.toString() || '1'}
              productCode={selectedProduct?.code || ''}
              formData={preparedDocuSealData}
              episodeId={formData.episode_id ? parseInt(formData.episode_id) : undefined}
              onComplete={handleDocuSealComplete}
              onError={handleDocuSealError}
              className="w-full h-full min-h-[600px]"
            />
          </div>
        )}
      </div>

      {/* Validation Errors */}
      {errors.docuseal && (
        <div className={cn(
          "p-4 rounded-lg border",
          theme === 'dark'
            ? 'bg-red-900/20 border-red-800'
            : 'bg-red-50 border-red-200'
        )}>
          <p className={cn(
            "text-sm",
            theme === 'dark' ? 'text-red-400' : 'text-red-600'
          )}>
            {errors.docuseal}
          </p>
        </div>
      )}

      {/* Order Summary */}
      <div className={cn(
        "p-4 rounded-lg border",
        theme === 'dark'
          ? 'bg-gray-800 border-gray-700'
          : 'bg-gray-50 border-gray-200'
      )}>
        <h4 className={cn("text-sm font-medium mb-3", t.text.primary)}>
          Order Summary
        </h4>
        <div className="space-y-2 text-sm">
          <div className="flex justify-between">
            <span className={t.text.secondary}>Patient:</span>
            <span className={t.text.primary}>
              {formData.patient_first_name} {formData.patient_last_name}
            </span>
          </div>
          <div className="flex justify-between">
            <span className={t.text.secondary}>Product:</span>
            <span className={t.text.primary}>
              {selectedProduct?.name} ({selectedProduct?.code})
            </span>
          </div>
          <div className="flex justify-between">
            <span className={t.text.secondary}>Provider:</span>
            <span className={t.text.primary}>
              {provider?.name || 'Not specified'}
            </span>
          </div>
          <div className="flex justify-between">
            <span className={t.text.secondary}>Service Date:</span>
            <span className={t.text.primary}>
              {formData.expected_service_date || 'Not specified'}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
