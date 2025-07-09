import React, { useState, useEffect, useMemo } from 'react';
import { FiCheckCircle, FiAlertCircle, FiArrowRight, FiCheck, FiInfo, FiCreditCard, FiRefreshCw, FiUpload, FiFile, FiFileText, FiSkipForward } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { useManufacturers } from '@/Hooks/useManufacturers';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import { useCallback } from 'react';
import { AuthButton } from '@/Components/ui/auth-button';
import api from '@/lib/api';
// Removed useAuthState import - Inertia handles authentication automatically

// This component now relies on the backend orchestrator for all data aggregation
// No frontend data preparation is needed - the backend is the single source of truth

// This component now relies on the backend orchestrator for all data aggregation
// No frontend data preparation is needed - the backend is the single source of truth

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
  const [submissionData, setSubmissionData] = useState<any>(null);
  const [submissionError, setSubmissionError] = useState<string | null>(null);
  const [showOrderForm, setShowOrderForm] = useState(false);
  const [pollInterval, setPollInterval] = useState<NodeJS.Timeout | null>(null);
  const [additionalDocuments, setAdditionalDocuments] = useState<File[]>([]);
  const [uploadProgress, setUploadProgress] = useState<Record<string, number>>({});

  // Use global auth state
  // Removed useAuthState - Inertia handles authentication automatically

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

  // Effect to auto-hide the insurance card success message
  useEffect(() => {
    if (insuranceCardSuccess) {
      const timer = setTimeout(() => {
        setInsuranceCardSuccess(false);
      }, 5000);

      // This cleanup function will run if the component unmounts before the timer fires
      return () => clearTimeout(timer);
    }
  }, [insuranceCardSuccess]);

  // Clean up any intervals on unmount
  useEffect(() => {
    return () => {
      if (pollInterval) {
        clearInterval(pollInterval);
      }
    };
  }, [pollInterval]);

  // Insurance card upload handler
  const handleInsuranceCardUpload = async (file: File, side: 'front' | 'back') => {
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
        const response = await api.post('/api/insurance-card/analyze', apiFormData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        const data = response.data;
        
        if (data.success && data.data) {
          const updates: any = {};

          // Update insurance information
          if (data.data.payer_name) updates.primary_insurance_name = data.data.payer_name;
          if (data.data.payer_id) updates.primary_member_id = data.data.payer_id;

          updates.insurance_card_auto_filled = true;
          updateFormData(updates);
          setInsuranceCardSuccess(true);
        } else {
          setSubmissionError('Insurance card uploaded but could not extract data. Please enter information manually.');
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
        setSubmissionError('Unable to process insurance card. Please try again or enter information manually.');
      } finally {
        setIsProcessingInsuranceCard(false);
      }
    }
  };

  // Enhanced completion handler for DocuSeal form
  const handleDocusealFormComplete = (event: any) => {
    try {
      console.log('Docuseal form completed:', event);
      
      // Update form data with completion status and submission data
      updateFormData({
        final_submission_completed: true,
        final_submission_data: event,
        docuseal_submission_id: event.slug || event.submission_id,
        episode_id: event.episode_id,
        ivr_completed: true,
        docuseal_completed_at: new Date().toISOString()
      });
      
      console.log('✅ IVR form completed successfully');
      
      // Automatically proceed to next step after a short delay
      setTimeout(() => {
        if (onNext) {
          onNext();
        }
      }, 1500);
      
    } catch (error) {
      console.error('Error processing DocuSeal completion:', error);
      setSubmissionError('Form completed but there was an error processing the result.');
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

  // Auto-show DocusealEmbed when all conditions are met
  const shouldShowEmbed = !manufacturersLoading && selectedProduct && templateId && !formData.docuseal_submission_id;

  const handleSkip = () => {
    if (onNext) {
      onNext();
    }
  };

  const handleShowOrderForm = () => {
    setShowOrderForm(true);
  };

  return (
    <div className="space-y-6">
      {/* Main content - Inertia handles authentication automatically */}
          {/* Header */}
          <div className="mb-6">
            <h2 className={cn("text-xl font-semibold", theme === 'dark' ? 'text-white' : 'text-gray-900')}>
              Insurance Verification Request (IVR)
            </h2>
            <p className={cn("text-sm mt-1", theme === 'dark' ? 'text-gray-400' : 'text-gray-600')}>
              Generate and complete the manufacturer's insurance verification form
            </p>
          </div>

          {/* FHIR Data Enhancement Info - shown only when DocusealEmbed is loading */}
          {shouldShowEmbed && (
            <div className={cn(
              "p-4 rounded-lg border mb-4",
              theme === 'dark'
                ? 'bg-blue-900/20 border-blue-700'
                : 'bg-blue-50 border-blue-200'
            )}>
              <div className="flex items-center mb-2">
                <FiInfo className={cn(
                  "w-4 h-4 mr-2",
                  theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
                )} />
                <h4 className={cn("font-medium", theme === 'dark' ? 'text-blue-300' : 'text-blue-800')}>
                  FHIR-Enhanced IVR Form Loading
                </h4>
              </div>
              <p className={cn("text-sm", theme === 'dark' ? 'text-blue-400' : 'text-blue-700')}>
                The system is automatically pre-filling the IVR form using comprehensive FHIR data including patient demographics, provider credentials, clinical diagnosis, and insurance coverage.
              </p>
            </div>
          )}

          {/* Auto-show DocusealEmbed when ready */}
          {shouldShowEmbed && (
            <div className="w-full">
              <DocusealEmbed
                manufacturerId={String(manufacturerConfig?.id || selectedProduct?.manufacturer_id || '')}
                templateId={templateId}
                productCode={selectedProduct?.q_code || selectedProduct?.code || ''}
                documentType="IVR"
                formData={formData}
                episodeId={formData.episode_id ? parseInt(formData.episode_id) : undefined}
                onComplete={handleDocusealFormComplete}
                onError={(error) => setSubmissionError(error)}
                className="w-full"
                debug={true}
                submissionUrl=""
                builderToken=""
                builderProps={null}
              />
            </div>
          )}

          {/* Completion Status */}
          {formData.docuseal_submission_id && (
            <div className={cn(
              "p-6 rounded-lg border-l-4",
              theme === 'dark'
                ? 'bg-green-900/20 border-green-700'
                : 'bg-green-50 border-green-500'
            )}>
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <FiCheck className={cn(
                    "w-5 h-5 mr-3",
                    theme === 'dark' ? 'text-green-400' : 'text-green-600'
                  )} />
                  <div>
                    <h3 className={cn("font-medium", theme === 'dark' ? 'text-green-300' : 'text-green-800')}>
                      IVR Form Available
                      {formData.fhir_data_used && (
                        <span className={cn("ml-2 text-xs px-2 py-1 rounded", 
                          theme === 'dark' ? 'bg-blue-900/50 text-blue-300' : 'bg-blue-100 text-blue-800')}>
                          FHIR Enhanced
                        </span>
                      )}
                    </h3>
                    <p className={cn("text-sm mt-1", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                      Please complete the form below
                      {formData.completeness_percentage && (
                        <span className="ml-2 font-medium">
                          ({formData.completeness_percentage}% pre-filled)
                        </span>
                      )}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Show completion status when form is completed */}
          {formData.docuseal_submission_id && formData.docuseal_submission_id !== 'NO_IVR_REQUIRED' && (
            <div className={cn(
              "p-6 rounded-lg border-l-4",
              theme === 'dark'
                ? 'bg-green-900/20 border-green-700'
                : 'bg-green-50 border-green-500'
            )}>
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <FiCheck className={cn(
                    "w-5 h-5 mr-3",
                    theme === 'dark' ? 'text-green-400' : 'text-green-600'
                  )} />
                  <div>
                    <h3 className={cn("font-medium", theme === 'dark' ? 'text-green-300' : 'text-green-800')}>
                      IVR Form Completed
                    </h3>
                    <p className={cn("text-sm mt-1", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                      Ready for Final Review
                    </p>
                  </div>
                </div>
                <AuthButton
                  onClick={handleSkip}
                  className={cn(
                    "px-4 py-2 rounded-lg font-medium flex items-center gap-2",
                    theme === 'dark'
                      ? 'bg-blue-600 hover:bg-blue-700 text-white'
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                  )}
                >
                  Continue to Final Review
                  <FiArrowRight className="w-4 h-4" />
                </AuthButton>
              </div>
            </div>
          )}

          {/* Error State */}
          {submissionError && (
            <div className={cn(
              "p-6 rounded-lg border-l-4",
              theme === 'dark'
                ? 'bg-red-900/20 border-red-700'
                : 'bg-red-50 border-red-500'
            )}>
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <FiAlertCircle className={cn(
                    "w-5 h-5 mr-3",
                    theme === 'dark' ? 'text-red-400' : 'text-red-600'
                  )} />
                  <div>
                    <h3 className={cn("font-medium", theme === 'dark' ? 'text-red-300' : 'text-red-800')}>
                      Error Generating IVR
                    </h3>
                    <p className={cn("text-sm mt-1", theme === 'dark' ? 'text-red-400' : 'text-red-700')}>
                      {submissionError}
                    </p>
                  </div>
                </div>
                <div className="flex gap-2">
                  <AuthButton
                    onClick={() => {
                      setSubmissionError(null);
                      window.location.reload();
                    }}
                    className={cn(
                      "px-4 py-2 rounded-lg font-medium",
                      theme === 'dark'
                        ? 'bg-blue-600 hover:bg-blue-700 text-white'
                        : 'bg-blue-600 hover:bg-blue-700 text-white'
                    )}
                  >
                    Try Again
                  </AuthButton>
                  <AuthButton
                    onClick={handleSkip}
                    variant="secondary"
                    className={cn(
                      "px-4 py-2 rounded-lg font-medium",
                      theme === 'dark'
                        ? 'text-gray-300 border-gray-600'
                        : 'text-gray-700 border-gray-300'
                    )}
                  >
                    Skip This Step
                  </AuthButton>
                </div>
              </div>
            </div>
          )}

          {/* Skip option when no form is needed */}
          {!showOrderForm && !submissionError && !formData.docuseal_submission_id && !shouldShowEmbed && (
            <div className={cn("p-4 rounded-lg", theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100')}>
              <p className={cn("text-sm mb-4", theme === 'dark' ? 'text-gray-400' : 'text-gray-600')}>
                No IVR form is required for this product, or you can skip this optional step.
              </p>
              
              <div className="flex gap-3">
                <AuthButton
                  onClick={handleSkip}
                  className={cn(
                    "px-4 py-2 rounded-lg font-medium flex items-center gap-2",
                    theme === 'dark'
                      ? 'bg-blue-600 hover:bg-blue-700 text-white'
                      : 'bg-blue-600 hover:bg-blue-700 text-white'
                  )}
                >
                  <FiSkipForward className="w-4 h-4" />
                  Continue to Final Review
                </AuthButton>
              </div>
            </div>
          )}

          {/* Insurance Card Re-upload Option */}
          {supportsInsuranceUpload && !formData.insurance_card_front && !formData.docuseal_submission_id && (
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
          {shouldShowEmbed && !submissionError && (
            <div className={cn("mb-4 text-sm", t.text.secondary)}>
              <p>Please review the pre-filled information and sign where indicated.</p>
            </div>
          )}



          {/* Docuseal Form or Error Status */}
          <div className={cn("rounded-lg", t.glass.card, "w-full max-w-full")}>
            {submissionError ? (
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
                        <AuthButton
                          onClick={() => {
                            setSubmissionError('');
                            window.location.reload();
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
                        </AuthButton>
                        
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
                            <AuthButton
                              onClick={() => window.location.reload()}
                              className={cn(
                                "mt-2 text-sm underline",
                                theme === 'dark' ? 'text-blue-300' : 'text-blue-600'
                              )}
                            >
                              Refresh Page
                            </AuthButton>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="relative">
                {/* This section is now handled by the auto-show logic above */}
                {formData.docuseal_submission_id && (
                  <div className="text-center py-8">
                    <FiCheckCircle className="h-12 w-12 mx-auto mb-4 text-green-500" />
                    <h3 className="text-lg font-medium mb-2">IVR Form Completed</h3>
                    <p className="text-sm text-gray-600">
                      Your insurance verification request has been submitted successfully.
                    </p>
                  </div>
                )}
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
