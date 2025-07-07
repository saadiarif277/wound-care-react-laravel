import { useState, useEffect, useMemo } from 'react';
import { FiCheckCircle, FiAlertCircle, FiArrowRight, FiCheck, FiInfo, FiCreditCard, FiRefreshCw, FiUpload, FiBrain } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { useManufacturers } from '@/Hooks/useManufacturers';
import { fetchWithCSRF, hasPermission, handleAPIError } from '@/utils/csrf';
import { getIVRFormByManufacturer } from '@/config/localIVRFormMapping';
// @ts-ignore
import { DocusealForm } from '@docuseal/react';

// AI-powered IVR form filling - no more DocuSeal dependency
// Uses the medical AI service for intelligent field mapping and form pre-filling

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

  // Docuseal
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
    q_code?: string;
    name: string;
    manufacturer: string;
    manufacturer_id?: number;
    available_sizes?: any;
    price_per_sq_cm?: number;
  }>;
  // Removed providers and facilities - backend handles this data
  errors: Record<string, string>;
  onNext?: () => void;
}

// Template mapping is now handled dynamically through manufacturer configuration

export default function Step7DocusealIVR({
  formData,
  updateFormData,
  products,
  errors,
  onNext
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
  const [submissionError, setSubmissionError] = useState<string>('');
  const [isProcessing, setIsProcessing] = useState(false);
  const [enhancedSubmission] = useState<any>(null);
  const [docusealSlug, setDocusealSlug] = useState<string>('');
  const [isLoadingSlug, setIsLoadingSlug] = useState(false);

  // Insurance card re-upload states
  const [showInsuranceUpload, setShowInsuranceUpload] = useState(false);
  const [isProcessingInsuranceCard, setIsProcessingInsuranceCard] = useState(false);
  const [insuranceCardSuccess, setInsuranceCardSuccess] = useState(false);

  // Use the manufacturers hook
  const { manufacturers, loading: manufacturersLoading, getManufacturerByName } = useManufacturers();

  // Get the selected product - memoized to prevent excessive recalculation
  const selectedProduct = useMemo(() => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return null;
    }

    const firstProduct = formData.selected_products[0];
    if (!firstProduct?.product_id) {
      return null;
    }

    // First try to find the product in the products array
    let foundProduct = products.find(p => p.id === firstProduct.product_id);

    // If not found in products array, use the product data from selected_products
    if (!foundProduct && firstProduct.product) {
      foundProduct = firstProduct.product;
    }

    return foundProduct;
  }, [formData.selected_products, products]);

  // Get Docuseal template ID from manufacturer configuration - memoized
  const templateId = useMemo(() => {
    if (!selectedProduct || manufacturersLoading) return undefined;

    // Use manufacturer config to get the template ID
    const config = selectedProduct.manufacturer ? getManufacturerByName(selectedProduct.manufacturer) : null;
    if (config?.docuseal_template_id) {
      return config.docuseal_template_id;
    }

    return undefined;
  }, [selectedProduct, manufacturersLoading, getManufacturerByName]);

  const manufacturerConfig = useMemo(() => {
    return (!manufacturersLoading && selectedProduct?.manufacturer)
      ? getManufacturerByName(selectedProduct.manufacturer)
      : null;
  }, [manufacturersLoading, selectedProduct?.manufacturer, getManufacturerByName]);

  // Check if manufacturer supports insurance upload in IVR
  const supportsInsuranceUpload = manufacturerConfig?.supports_insurance_upload_in_ivr === true;

  // The backend orchestrator now handles all provider, facility, and organization data
  // This component only needs to handle patient/clinical/insurance data from the form

  // Insurance card upload handler
  const handleInsuranceCardUpload = async (file: File, side: 'front' | 'back') => {
    // Check permissions first
    if (!hasPermission('create-product-requests')) {
      setSubmissionError('You do not have permission to upload insurance cards. Please contact your administrator.');
      return;
    }

    // Store file in form data
    updateFormData({ [`insurance_card_${side}`]: file });

    // Try to process with Azure Document Intelligence
    const frontCard = side === 'front' ? file : formData.insurance_card_front;
    const backCard = side === 'back' ? file : formData.insurance_card_back;

    if (frontCard) {
      setIsProcessingInsuranceCard(true);
      setInsuranceCardSuccess(false);
      setSubmissionError(''); // Clear any previous errors

      try {
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);
        if (backCard) {
          apiFormData.append('insurance_card_back', backCard);
        }

        // Use the enhanced fetch function with automatic CSRF token handling
        const response = await fetchWithCSRF('/api/insurance-card/analyze', {
          method: 'POST',
          body: apiFormData,
        });

        if (response.ok) {
          const result = await response.json();

          if (result.success && result.data) {
            const updates: any = {};

            // Update insurance information
            if (result.data.payer_name) updates.primary_insurance_name = result.data.payer_name;
            if (result.data.payer_id) updates.primary_member_id = result.data.payer_id;

            updates.insurance_card_auto_filled = true;
            updateFormData(updates);
            setInsuranceCardSuccess(true);

            setTimeout(() => {
              setInsuranceCardSuccess(false);
            }, 5000);
          } else {
            setSubmissionError('Insurance card uploaded but could not extract data. Please enter information manually.');
          }
        } else {
          const errorMessage = handleAPIError(response, 'Insurance card upload');
          setSubmissionError(errorMessage);
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
        setSubmissionError('Unable to process insurance card. Please try again or enter information manually.');
      } finally {
        setIsProcessingInsuranceCard(false);
      }
    }
  };

  // Fetch DocuSeal slug when component loads
  const fetchDocusealSlug = async () => {
    if (!formData.episode_id || !selectedProduct?.manufacturer) {
      return;
    }

    setIsLoadingSlug(true);
    setSubmissionError('');

    try {
      const response = await fetchWithCSRF('/api/v1/quick-request/create-ivr-submission', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          episode_id: formData.episode_id,
          manufacturer_name: selectedProduct.manufacturer,
        }),
      });

      if (response.ok) {
        const result = await response.json();
        console.log('✅ DocuSeal submission created:', result);

        if (result.slug) {
          setDocusealSlug(result.slug);
          updateFormData({
            docuseal_submission_id: result.submission_id,
          });
        } else {
          throw new Error('No slug received from API');
        }
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to create DocuSeal submission');
      }
    } catch (error) {
      console.error('Error fetching DocuSeal slug:', error);
      setSubmissionError(`Failed to initialize form: ${error instanceof Error ? error.message : String(error)}`);
    } finally {
      setIsLoadingSlug(false);
    }
  };

  // Load DocuSeal embed URL when component mounts and has required data
  useEffect(() => {
    if (!manufacturersLoading && formData.episode_id && selectedProduct?.manufacturer && templateId && !docusealSlug && !formData.docuseal_submission_id) {
      fetchDocusealSlug();
    }
  }, [manufacturersLoading, formData.episode_id, selectedProduct?.manufacturer, templateId, docusealSlug, formData.docuseal_submission_id, fetchDocusealSlug]);

  // Enhanced completion handler for DocuSeal form
  const handleDocusealFormComplete = (event: any) => {
    setIsProcessing(true);

    try {
      console.log('🎉 DocuSeal form completed:', event);

      // Update form data with completion
      updateFormData({
        ivr_completed_at: new Date().toISOString(),
        docuseal_completed: true,
      });

      setIsCompleted(true);
      setSubmissionError('');

    } catch (error) {
      console.error('Error processing DocuSeal completion:', error);
      setSubmissionError('Form completed but there was an error processing the result.');
    } finally {
      setIsProcessing(false);
    }
  };

  // Set NO_IVR_REQUIRED when manufacturer doesn't require signature
  useEffect(() => {
    if (!manufacturersLoading && selectedProduct?.manufacturer) {
      const config = getManufacturerByName(selectedProduct.manufacturer);
      if (config !== undefined && !config.signature_required) {
        if (!formData.docuseal_submission_id) {
          updateFormData({ docuseal_submission_id: 'NO_IVR_REQUIRED' });
        }
      }
    }
  }, [manufacturersLoading, selectedProduct?.manufacturer, formData.docuseal_submission_id, updateFormData, getManufacturerByName]);

  // No product selected
  if (!selectedProduct) {
    return (
      <div className={cn("text-center py-12", t.text.secondary)}>
        <p>Please select a product first</p>
      </div>
    );
  }

  // Show loading state while manufacturers are being fetched
  if (manufacturersLoading) {
    return (
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mx-auto mb-4" />
        <p className={cn("text-sm", t.text.secondary)}>
          Loading manufacturer configuration...
        </p>
      </div>
    );
  }

  // No IVR required if no template ID found
  if (!templateId) {
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

        {/* Manual next button for no IVR required case */}
        <div className={cn("mt-6 p-4 rounded-lg border",
          theme === 'dark' ? 'bg-blue-900/20 border-blue-800' : 'bg-blue-50 border-blue-200'
        )}>
          <button
            onClick={() => onNext && onNext()}
            className={cn(
              "inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
              theme === 'dark'
                ? 'bg-blue-700 hover:bg-blue-600 text-white'
                : 'bg-blue-600 hover:bg-blue-700 text-white'
            )}
          >
            Continue to Final Review
            <FiArrowRight className="ml-2 h-4 w-4" />
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Simple Title */}
      <div className="mb-6">
        <h2 className={cn("text-xl font-semibold", t.text.primary)}>
          Complete IVR Form
        </h2>
        {formData.episode_id && (
          <p className={cn("text-sm mt-1", t.text.secondary)}>
            Your information has been pre-filled for your convenience
          </p>
        )}
      </div>

      {/* Insurance Card Re-upload Option */}
      {supportsInsuranceUpload && !formData.insurance_card_front && !isCompleted && (
        <div className={cn("p-4 rounded-lg", t.glass.card, "border", theme === 'dark' ? 'border-blue-800' : 'border-blue-200')}>
          <div className="flex items-start">
            <FiInfo className={cn("h-5 w-5 mt-0.5 flex-shrink-0 mr-3", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')} />
            <div className="flex-1">
              <h3 className={cn("text-sm font-medium mb-1", t.text.primary)}>
                Insurance Card Upload
              </h3>
              <p className={cn("text-sm mb-3", t.text.secondary)}>
                Upload your insurance card now to have it attached to the IVR form
              </p>

              {!showInsuranceUpload ? (
                <button
                  type="button"
                  onClick={() => setShowInsuranceUpload(true)}
                  className={cn(
                    "inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                    theme === 'dark'
                      ? 'bg-blue-700 hover:bg-blue-600 text-white'
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                  )}
                >
                  <FiCreditCard className="mr-2 h-4 w-4" />
                  Upload Insurance Card
                </button>
              ) : (
                <div className="space-y-4">
                  {/* Insurance Upload UI */}
                  <div className="grid grid-cols-2 gap-4">
                    {/* Front Card Upload */}
                    <div className="space-y-2">
                      <label className={cn("text-sm font-medium", t.text.primary)}>Front of Card</label>
                      {!formData.insurance_card_front ? (
                        <div
                          onClick={() => {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/*,application/pdf';
                            input.onchange = (e) => {
                              const file = (e.target as HTMLInputElement).files?.[0];
                              if (file) handleInsuranceCardUpload(file, 'front');
                            };
                            input.click();
                          }}
                          className={cn(
                            "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all",
                            theme === 'dark'
                              ? 'border-gray-700 hover:border-blue-500 hover:bg-gray-800'
                              : 'border-gray-300 hover:border-blue-500 hover:bg-gray-50'
                          )}
                        >
                          <FiUpload className="mx-auto h-6 w-6 mb-1 text-gray-400" />
                          <p className={cn("text-xs font-medium", t.text.secondary)}>
                            Click to upload
                          </p>
                        </div>
                      ) : (
                        <div className={cn(
                          "border rounded-lg p-3",
                          theme === 'dark' ? 'border-gray-700 bg-gray-800' : 'border-gray-300 bg-gray-50'
                        )}>
                          <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2 flex-1 min-w-0">
                              <FiCheck className="h-4 w-4 text-green-500 flex-shrink-0" />
                              <span className={cn("text-sm truncate", t.text.primary)}>
                                {formData.insurance_card_front.name || 'Front uploaded'}
                              </span>
                            </div>
                            <button
                              type="button"
                              onClick={() => updateFormData({ insurance_card_front: null })}
                              className="ml-2 text-red-500 hover:text-red-700"
                            >
                              <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        </div>
                      )}
                    </div>

                    {/* Back Card Upload */}
                    <div className="space-y-2">
                      <label className={cn("text-sm font-medium", t.text.primary)}>Back of Card</label>
                      {!formData.insurance_card_back ? (
                        <div
                          onClick={() => {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/*,application/pdf';
                            input.onchange = (e) => {
                              const file = (e.target as HTMLInputElement).files?.[0];
                              if (file) handleInsuranceCardUpload(file, 'back');
                            };
                            input.click();
                          }}
                          className={cn(
                            "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all",
                            theme === 'dark'
                              ? 'border-gray-700 hover:border-blue-500 hover:bg-gray-800'
                              : 'border-gray-300 hover:border-blue-500 hover:bg-gray-50'
                          )}
                        >
                          <FiUpload className="mx-auto h-6 w-6 mb-1 text-gray-400" />
                          <p className={cn("text-xs font-medium", t.text.secondary)}>
                            Click to upload
                          </p>
                        </div>
                      ) : (
                        <div className={cn(
                          "border rounded-lg p-3",
                          theme === 'dark' ? 'border-gray-700 bg-gray-800' : 'border-gray-300 bg-gray-50'
                        )}>
                          <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2 flex-1 min-w-0">
                              <FiCheck className="h-4 w-4 text-green-500 flex-shrink-0" />
                              <span className={cn("text-sm truncate", t.text.primary)}>
                                {formData.insurance_card_back.name || 'Back uploaded'}
                              </span>
                            </div>
                            <button
                              type="button"
                              onClick={() => updateFormData({ insurance_card_back: null })}
                              className="ml-2 text-red-500 hover:text-red-700"
                            >
                              <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Processing status */}
                  {isProcessingInsuranceCard && (
                    <div className="flex items-center justify-center">
                      <FiRefreshCw className="animate-spin h-5 w-5 mr-2 text-blue-500" />
                      <span className={cn("text-sm", t.text.secondary)}>Processing insurance card...</span>
                    </div>
                  )}

                  {insuranceCardSuccess && (
                    <div className={cn(
                      "p-3 rounded-lg flex items-center",
                      theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50'
                    )}>
                      <FiCheck className="h-5 w-5 mr-2 text-green-500" />
                      <span className={cn("text-sm", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                        Insurance information updated successfully!
                      </span>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Simple Instructions */}
      {!isCompleted && !submissionError && (
        <div className={cn("mb-4 text-sm", t.text.secondary)}>
          <p>Please review the pre-filled information and sign where indicated.</p>
        </div>
      )}



      {/* Docuseal Form or Completion Status */}
      <div className={cn("rounded-lg", t.glass.card, "w-full max-w-full")}>
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

            {/* Manual next button */}
            <div className={cn("mt-6 p-4 rounded-lg border",
              theme === 'dark' ? 'bg-blue-900/20 border-blue-800' : 'bg-blue-50 border-blue-200'
            )}>
              <p className={cn("text-sm font-medium mb-2",
                theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
              )}>
                Ready for Final Review
              </p>
              <p className={cn("text-xs",
                theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
              )}>
                Click the button below to proceed to the final review step and complete your order.
              </p>

              <button
                onClick={() => onNext && onNext()}
                className={cn(
                  "mt-3 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                  theme === 'dark'
                    ? 'bg-blue-700 hover:bg-blue-600 text-white'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                )}
              >
                Continue to Final Review
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
                <div className="flex-1">
                  <h4 className={cn(
                    "text-sm font-medium",
                    theme === 'dark' ? 'text-red-300' : 'text-red-900'
                  )}>
                    {submissionError.includes('permission') ? 'Permission Error' : 
                     submissionError.includes('session') || submissionError.includes('expired') ? 'Session Error' :
                     submissionError.includes('server') ? 'Server Error' : 'Error Loading IVR Form'}
                  </h4>
                  <p className={cn(
                    "text-sm mt-1",
                    theme === 'dark' ? 'text-red-400' : 'text-red-700'
                  )}>
                    {submissionError}
                  </p>
                  <div className="mt-4 space-y-2">
                    <button
                      onClick={() => {
                        setSubmissionError('');
                        setIsCompleted(false);
                      }}
                      className={cn(
                        "inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors mr-3",
                        theme === 'dark'
                          ? 'bg-red-700 hover:bg-red-600 text-white'
                          : 'bg-red-600 hover:bg-red-700 text-white'
                      )}
                    >
                      <FiRefreshCw className="mr-1 h-4 w-4" />
                      Try Again
                    </button>
                    
                    {submissionError.includes('permission') && (
                      <div className={cn(
                        "mt-3 p-3 rounded-md",
                        theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50'
                      )}>
                        <p className={cn(
                          "text-sm",
                          theme === 'dark' ? 'text-blue-300' : 'text-blue-800'
                        )}>
                          <strong>Need Help?</strong> Contact your administrator to request the 
                          "create-product-requests" permission to access this feature.
                        </p>
                      </div>
                    )}
                    
                    {(submissionError.includes('session') || submissionError.includes('expired')) && (
                      <div className={cn(
                        "mt-3 p-3 rounded-md",
                        theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50'
                      )}>
                        <p className={cn(
                          "text-sm",
                          theme === 'dark' ? 'text-blue-300' : 'text-blue-800'
                        )}>
                          <strong>Session Expired:</strong> Please refresh the page and try again.
                        </p>
                        <button
                          onClick={() => window.location.reload()}
                          className={cn(
                            "mt-2 text-sm underline",
                            theme === 'dark' ? 'text-blue-300' : 'text-blue-600'
                          )}
                        >
                          Refresh Page
                        </button>
                      </div>
                    )}
                  </div>
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

            {/* Loading state while fetching slug */}
            {isLoadingSlug ? (
              <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mx-auto mb-4" />
                <p className={cn("text-sm ml-3", t.text.secondary)}>
                  Initializing form...
                </p>
              </div>
            ) : docusealSlug ? (
              /* DocuSeal Form Embed */
              <div className="w-full">
                <DocusealForm
                  src={`https://docuseal.com/s/${docusealSlug}`}
                  onComplete={handleDocusealFormComplete}
                />
              </div>
            ) : (
              <div className={cn("text-center py-12", t.text.secondary)}>
                <p>Form could not be loaded. Please try again.</p>
                <button
                  onClick={fetchDocusealSlug}
                  className={cn(
                    "mt-4 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                    theme === 'dark'
                      ? 'bg-blue-700 hover:bg-blue-600 text-white'
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                  )}
                >
                  <FiRefreshCw className="mr-2 h-4 w-4" />
                  Retry
                </button>
              </div>
            )}
            
            {/* Documents Section for IVR */}
            <div className={cn("mt-6 p-4 rounded-lg", t.glass.card, "border", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
              <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                Documents for IVR
              </h3>
              
              {/* Auto-attached Insurance Cards */}
              {(formData.insurance_card_front || formData.insurance_card_back) && (
                <div className="mb-4">
                  <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
                    Insurance Cards (Auto-attached)
                  </h4>
                  <div className="grid grid-cols-2 gap-3">
                    {formData.insurance_card_front && (
                      <div className={cn(
                        "p-3 rounded border",
                        theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'
                      )}>
                        <div className="flex items-center space-x-2">
                          <FiCheck className="h-4 w-4 text-green-500 flex-shrink-0" />
                          <span className={cn("text-sm", t.text.primary)}>Front Card</span>
                        </div>
                        <p className={cn("text-xs mt-1", t.text.secondary)}>
                          {formData.insurance_card_front.name || 'Insurance card front'}
                        </p>
                      </div>
                    )}
                    {formData.insurance_card_back && (
                      <div className={cn(
                        "p-3 rounded border",
                        theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'
                      )}>
                        <div className="flex items-center space-x-2">
                          <FiCheck className="h-4 w-4 text-green-500 flex-shrink-0" />
                          <span className={cn("text-sm", t.text.primary)}>Back Card</span>
                        </div>
                        <p className={cn("text-xs mt-1", t.text.secondary)}>
                          {formData.insurance_card_back.name || 'Insurance card back'}
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              )}
              
            </div>
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

    </div>
  );
}
