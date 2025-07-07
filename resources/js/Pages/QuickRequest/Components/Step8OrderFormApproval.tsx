import { useState, useEffect } from 'react';
import { router } from '@inertiajs/core';
import { FiCheckCircle, FiAlertCircle, FiFileText, FiArrowRight, FiSkipForward, FiSend } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import axios from 'axios';
import { useManufacturers } from '@/Hooks/useManufacturers';
// Make sure useManufacturers is exported from manufacturerFields.ts

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

  // Duration fields
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

  // NEW: Manufacturer order form (replaces DocuSeal)
  manufacturer_submission_id?: string;
  ivr_completed_at?: string;

  // Order Form
  order_form_submission_id?: string;
  order_form_completed_at?: string;
  order_form_skipped?: boolean;

  episode_id?: string;

  [key: string]: any;
}

interface Step8Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  onNext: () => void;
  onSkip: () => void;
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

export default function Step8OrderFormApproval({
  formData,
  updateFormData,
  onNext,
  onSkip,
  products,
  providers = [],
  facilities = [],
  errors
}: Step8Props) {
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

  // Use manufacturers hook
  const { getManufacturerByName } = useManufacturers();

  const [showOrderForm, setShowOrderForm] = useState(false);
  const [isCompleted, setIsCompleted] = useState(false);
  const [isSkipped, setIsSkipped] = useState(false);
  const [submissionError, setSubmissionError] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [orderFormSubmission, setOrderFormSubmission] = useState<any>(null);

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
  const manufacturerConfig = selectedProduct?.manufacturer 
    ? getManufacturerByName(selectedProduct.manufacturer)
    : null;

  // Check if order form is available for this manufacturer
  const hasOrderForm = manufacturerConfig?.has_order_form === true || manufacturerConfig?.order_form_template_id;

  // Debug logging
  console.log('Step 8 - Selected Product:', {
    name: selectedProduct?.name,
    manufacturer: selectedProduct?.manufacturer,
    manufacturer_id: selectedProduct?.manufacturer_id,
    hasOrderForm: hasOrderForm
  });

  // Check if this step was already completed or skipped
  useEffect(() => {
    if (formData.order_form_completed_at) {
      setIsCompleted(true);
    } else if (formData.order_form_skipped) {
      setIsSkipped(true);
    }
  }, [formData.order_form_completed_at, formData.order_form_skipped]);

  // Auto-skip if no order form available
  useEffect(() => {
    if (!hasOrderForm && !isCompleted && !isSkipped) {
      handleSkip();
    }
  }, [hasOrderForm, isCompleted, isSkipped]);

  const handleShowOrderForm = () => {
    setShowOrderForm(true);
  };

  const handleSkip = () => {
    updateFormData({ 
      order_form_skipped: true,
      order_form_completed_at: new Date().toISOString()
    });
    setIsSkipped(true);
    setTimeout(() => onNext(), 1000);
  };

  // NEW: Handle manufacturer order form submission
  const handleOrderFormComplete = async (submissionData?: any) => {
    setIsProcessing(true);
    
    try {
      // Mark as completed
      updateFormData({
        order_form_completed_at: new Date().toISOString(),
        order_form_submission_id: submissionData?.submission_id || 'manual_completion_' + Date.now()
      });
      
      setOrderFormSubmission(submissionData);
      setIsCompleted(true);
      setSubmissionError(null);
      
      // Auto-advance to next step
      setTimeout(() => onNext(), 2000);
      
    } catch (error) {
      console.error('Order form completion error:', error);
      setSubmissionError('Failed to complete order form. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleOrderFormError = (error: string) => {
    setSubmissionError(error);
    setIsProcessing(false);
  };

  const handleManualNext = () => {
    onNext();
  };

  // No product selected
  if (!selectedProduct) {
    return (
      <div className={cn("text-center py-12", t.text.secondary)}>
        <FiAlertCircle className="h-12 w-12 mx-auto mb-4 text-red-500" />
        <p className="text-lg font-medium mb-2">No Product Selected</p>
        <p>Please go back and select a product to continue.</p>
      </div>
    );
  }

  // Step was skipped
  if (isSkipped) {
    return (
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <FiSkipForward className={cn("h-16 w-16 mx-auto mb-4 text-blue-500")} />
        <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
          Order Form Skipped
        </h3>
        <p className={cn("text-sm", t.text.secondary)}>
          You've chosen to skip the order form review step.
        </p>
        <p className={cn("text-sm mt-3", t.text.secondary)}>
          Proceeding to final review...
        </p>
        
        <button
          onClick={handleManualNext}
          className={cn(
            "mt-4 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
            theme === 'dark' 
              ? 'bg-blue-700 hover:bg-blue-600 text-white' 
              : 'bg-blue-600 hover:bg-blue-700 text-white'
          )}
        >
          Continue to Review
          <FiArrowRight className="ml-2 h-4 w-4" />
        </button>
      </div>
    );
  }

  // Step was completed
  if (isCompleted) {
    return (
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <FiCheckCircle className={cn("h-16 w-16 mx-auto mb-4 text-green-500")} />
        <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
          Order Form Completed Successfully
        </h3>
        <p className={cn("text-sm", t.text.secondary)}>
          The order form has been reviewed and submitted.
        </p>
        
        {orderFormSubmission && (
          <div className={cn("mt-4 p-3 rounded-lg", 
            theme === 'dark' ? 'bg-green-900/20 border border-green-800' : 'bg-green-50 border border-green-200'
          )}>
            <div className="text-sm space-y-1">
              <p className={cn("font-medium", t.text.primary)}>
                Submission #{orderFormSubmission.submission_id || formData.order_form_submission_id}
              </p>
            </div>
          </div>
        )}
        
        <p className={cn("text-sm mt-3", t.text.secondary)}>
          Proceeding to final review...
        </p>
        
        <button
          onClick={handleManualNext}
          className={cn(
            "mt-4 inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
            theme === 'dark' 
              ? 'bg-blue-700 hover:bg-blue-600 text-white' 
              : 'bg-blue-600 hover:bg-blue-700 text-white'
          )}
        >
          Continue to Review
          <FiArrowRight className="ml-2 h-4 w-4" />
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Simple Title */}
      <div className="mb-6">
        <h2 className={cn("text-xl font-semibold", t.text.primary)}>
          Review Order Form
        </h2>
        <p className={cn("text-sm mt-1", t.text.secondary)}>
          Optional step to review and confirm all order details
        </p>
      </div>

      {/* Choice Section */}
      {!showOrderForm && !submissionError && (
        <div className={cn("p-4 rounded-lg", t.glass.card)}>
          <p className={cn("text-sm mb-4", t.text.secondary)}>
            You can review the manufacturer's order form or skip this optional step.
          </p>
          
          <div className="flex gap-3">
            <button
              onClick={handleShowOrderForm}
              className={cn(
                "inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                theme === 'dark' 
                  ? 'bg-blue-700 hover:bg-blue-600 text-white' 
                  : 'bg-blue-600 hover:bg-blue-700 text-white'
              )}
            >
              <FiFileText className="mr-2 h-4 w-4" />
              Review Order Form
            </button>
            
            <button
              onClick={handleSkip}
              className={cn(
                "inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors",
                theme === 'dark' 
                  ? 'bg-gray-700 hover:bg-gray-600 text-gray-300' 
                  : 'bg-gray-300 hover:bg-gray-400 text-gray-700'
              )}
            >
              <FiSkipForward className="mr-2 h-4 w-4" />
              Skip This Step
            </button>
          </div>
        </div>
      )}

      {/* NEW: Manufacturer Order Form (replaces DocuSeal) */}
      {showOrderForm && !isCompleted && !submissionError && (
        <div className={cn("rounded-lg", t.glass.card)}>
          <div className="relative">
            {/* Processing overlay */}
            {isProcessing && (
              <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10 rounded-lg">
                <div className="bg-white p-6 rounded-lg text-center max-w-sm">
                  <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mx-auto mb-3" />
                  <p className="text-gray-900 font-medium">Processing Order Form...</p>
                  <p className="text-gray-600 text-sm mt-1">Finalizing your order details</p>
                </div>
              </div>
            )}
            
            {/* Order Form Summary */}
            <div className="p-6 space-y-6">
              <div className="text-center">
                <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                  Order Form Summary
                </h3>
                <p className={cn("text-sm mt-1", t.text.secondary)}>
                  Review your order details for {selectedProduct.manufacturer}
                </p>
              </div>

              {/* Product Information */}
              <div className={cn("p-4 rounded-lg border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                <h4 className={cn("text-sm font-medium mb-3", t.text.primary)}>
                  Product Information
                </h4>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Product:</p>
                    <p className={t.text.secondary}>{selectedProduct.name}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Code:</p>
                    <p className={t.text.secondary}>{selectedProduct.code}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Manufacturer:</p>
                    <p className={t.text.secondary}>{selectedProduct.manufacturer}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Quantity:</p>
                    <p className={t.text.secondary}>{formData.selected_products?.[0]?.quantity || 1}</p>
                  </div>
                </div>
              </div>

              {/* Patient Information */}
              <div className={cn("p-4 rounded-lg border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                <h4 className={cn("text-sm font-medium mb-3", t.text.primary)}>
                  Patient Information
                </h4>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Name:</p>
                    <p className={t.text.secondary}>{formData.patient_first_name} {formData.patient_last_name}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>DOB:</p>
                    <p className={t.text.secondary}>{formData.patient_dob || 'Not provided'}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Insurance:</p>
                    <p className={t.text.secondary}>{formData.primary_insurance_name || 'Not provided'}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Member ID:</p>
                    <p className={t.text.secondary}>{formData.primary_member_id || 'Not provided'}</p>
                  </div>
                </div>
              </div>

              {/* Clinical Information */}
              <div className={cn("p-4 rounded-lg border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                <h4 className={cn("text-sm font-medium mb-3", t.text.primary)}>
                  Clinical Information
                </h4>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Wound Type:</p>
                    <p className={t.text.secondary}>{formData.wound_type || 'Not specified'}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Location:</p>
                    <p className={t.text.secondary}>{formData.wound_location || 'Not specified'}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Primary Diagnosis:</p>
                    <p className={t.text.secondary}>{formData.primary_diagnosis_code || 'Not specified'}</p>
                  </div>
                  <div>
                    <p className={cn("font-medium", t.text.primary)}>Provider:</p>
                    <p className={t.text.secondary}>{formData.provider_name || 'Auto-filled'}</p>
                  </div>
                </div>
              </div>

              {/* Confirmation */}
              <div className="text-center">
                <p className={cn("text-sm mb-4", t.text.secondary)}>
                  Please review the information above and confirm your order.
                </p>
                
                <div className="flex justify-center gap-4">
                  <button
                    onClick={() => handleOrderFormComplete({ submission_id: `order_${Date.now()}` })}
                    disabled={isProcessing}
                    className={cn(
                      "inline-flex items-center px-6 py-3 text-sm font-medium rounded-lg transition-colors",
                      theme === 'dark' 
                        ? 'bg-green-700 hover:bg-green-600 text-white disabled:bg-gray-700' 
                        : 'bg-green-600 hover:bg-green-700 text-white disabled:bg-gray-400'
                    )}
                  >
                    {isProcessing ? (
                      <>
                        <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2" />
                        Processing...
                      </>
                    ) : (
                      <>
                        <FiSend className="mr-2 h-4 w-4" />
                        Confirm Order
                      </>
                    )}
                  </button>
                  
                  <button
                    onClick={() => {
                      setShowOrderForm(false);
                      setSubmissionError(null);
                    }}
                    disabled={isProcessing}
                    className={cn(
                      "inline-flex items-center px-6 py-3 text-sm font-medium rounded-lg transition-colors",
                      theme === 'dark' 
                        ? 'bg-gray-700 hover:bg-gray-600 text-gray-300 disabled:bg-gray-800' 
                        : 'bg-gray-300 hover:bg-gray-400 text-gray-700 disabled:bg-gray-200'
                    )}
                  >
                    Back to Options
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Error State */}
      {submissionError && (
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
                Error Loading Order Form
              </h4>
              <p className={cn(
                "text-sm mt-1",
                theme === 'dark' ? 'text-red-400' : 'text-red-700'
              )}>
                {submissionError}
              </p>
              <div className="flex gap-2 mt-3">
                <button
                  onClick={() => {
                    setSubmissionError(null);
                    setShowOrderForm(false);
                  }}
                  className={cn(
                    "text-sm underline",
                    theme === 'dark' ? 'text-red-300' : 'text-red-600'
                  )}
                >
                  Try Again
                </button>
                <span className={cn("text-sm", theme === 'dark' ? 'text-red-400' : 'text-red-700')}>
                  or
                </span>
                <button
                  onClick={handleSkip}
                  className={cn(
                    "text-sm underline",
                    theme === 'dark' ? 'text-red-300' : 'text-red-600'
                  )}
                >
                  Skip This Step
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Validation Errors */}
      {errors.order_form && (
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
            {errors.order_form}
          </p>
        </div>
      )}
    </div>
  );
}
