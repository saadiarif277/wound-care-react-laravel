import { useState, useEffect } from 'react';
import { router } from '@inertiajs/core';
import { FiCheckCircle, FiAlertCircle, FiFileText, FiArrowRight, FiCheck, FiInfo, FiCreditCard } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import { useManufacturers } from '@/Hooks/useManufacturers';
import DocumentUploadCard from '@/Components/DocumentUploadCard';
import { DocumentUpload } from '@/types/document-upload';
import axios from 'axios';

// Prepare Docuseal data function (moved from deleted docusealUtils.ts)
const prepareDocusealData = ({ formData, products, providers, facilities }: any) => {
  const selectedProduct = formData.selected_products?.[0];
  const product = products?.find((p: any) => p.id === selectedProduct?.product_id);
  const provider = providers?.find((p: any) => p.id === formData.provider_id);
  const facility = facilities?.find((f: any) => f.id === formData.facility_id);

  // Extract Q codes from all selected products for product checkbox automation
  const selectedProductCodes = formData.selected_products?.map((selectedProd: any) => {
    const prod = products?.find((p: any) => p.id === selectedProd.product_id);
    return prod?.q_code || prod?.code;
  }).filter(Boolean) || [];

  // Get product names for additional matching
  const selectedProductNames = formData.selected_products?.map((selectedProd: any) => {
    const prod = products?.find((p: any) => p.id === selectedProd.product_id);
    return prod?.name;
  }).filter(Boolean) || [];

  return {
    // Patient Information
    patient_name: `${formData.patient_first_name || ''} ${formData.patient_last_name || ''}`.trim(),
    patient_first_name: formData.patient_first_name || '',
    patient_last_name: formData.patient_last_name || '',
    patient_dob: formData.patient_dob || '',
    patient_gender: formData.patient_gender || '',
    
    // Provider Information (structured for field mapping)
    provider_name: provider?.name || formData.provider_name || '',
    provider_npi: provider?.npi || formData.provider_npi || '',
    provider_ptan: provider?.ptan || formData.provider_ptan || '',
    provider_email: formData.provider_email || '',
    provider: {
      name: provider?.name || formData.provider_name || '',
      npi: provider?.npi || formData.provider_npi || '',
      ptan: provider?.ptan || formData.provider_ptan || '',
      credentials: provider?.credentials || formData.provider_credentials || '',
      email: formData.provider_email || '',
    },
    
    // Facility Information (structured for field mapping)
    facility_name: facility?.name || formData.facility_name || '',
    facility_address: facility?.address || '',
    facility: {
      name: facility?.name || formData.facility_name || '',
      address: facility?.address || '',
      email: facility?.email || '',
      phone: facility?.phone || '',
      contact_name: facility?.contact_name || facility?.name || '',
      npi: facility?.npi || facility?.group_npi || '',
      ptan: facility?.ptan || facility?.facility_ptan || '',
    },
    
    // Product Information
    product_name: product?.name || '',
    product_code: product?.code || product?.q_code || '',
    product_manufacturer: product?.manufacturer || '',
    manufacturer_id: product?.manufacturer_id || null,
    
    // Product Selection Arrays (for field mapping computations)
    selected_product_codes: selectedProductCodes,
    selected_product_names: selectedProductNames,
    
    // Insurance Information
    primary_insurance_name: formData.primary_insurance_name || '',
    primary_member_id: formData.primary_member_id || '',
    
    // Clinical Information
    wound_type: formData.wound_type || '',
    wound_location: formData.wound_location || '',
    wound_size_length: formData.wound_size_length || '',
    wound_size_width: formData.wound_size_width || '',
    wound_size_depth: formData.wound_size_depth || '',
    
    // Other fields
    ...formData
  };
};

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
  
  // Provider Information
  provider_id?: number;
  provider_name?: string;
  provider_npi?: string;
  provider_ptan?: string;
  provider_email?: string;
  provider_credentials?: string;
  
  // Facility Information
  facility_id?: number;
  facility_name?: string;
  facility_address?: string;
  
  // Product Information
  selected_products?: SelectedProduct[];
  
  // Insurance Information
  primary_insurance_name?: string;
  primary_member_id?: string;
  insurance_card_front?: File;
  insurance_card_back?: File;
  insurance_card_auto_filled?: boolean;
  
  // Clinical Information
  wound_type?: string;
  wound_location?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  wound_duration_years?: number;
  wound_duration_months?: number;
  wound_duration_weeks?: number;
  wound_duration_days?: number;
  
  // ICD Codes
  primary_diagnosis_code?: string;
  secondary_diagnosis_code?: string;
  diagnosis_code?: string;
  
  // Other
  expected_service_date?: string;
  place_of_service?: string;
  global_period_status?: boolean;
  global_period_cpt?: string;
  global_period_surgery_date?: string;
  
  // Docuseal
  docuseal_submission_id?: string;
  ivr_completed_at?: string;
  episode_id?: string;
  
  // Form metadata
  [key: string]: any;
}

interface Step7Props {
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  products: Array<any>;
  providers?: Array<any>;
  facilities?: Array<any>;
  errors?: Record<string, string>;
}

// Product to template mapping
const PRODUCT_TEMPLATE_MAP: Record<number, string> = {
  // Add your product ID to template ID mappings here
};

// Q Code to template mapping
const Q_CODE_TEMPLATE_MAP: Record<string, string> = {
  'Q4128': '1233918',  // Centurion Allopatch
};

export default function Step7DocusealIVR({
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
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedTemplate, setSelectedTemplate] = useState<any>(null);
  const debugMode = true; // Enable debug mode
  
  // Insurance card re-upload states
  const [showInsuranceUpload, setShowInsuranceUpload] = useState(false);
  const [insuranceCardSuccess, setInsuranceCardSuccess] = useState(false);

  // Use the manufacturers hook
  const { manufacturers, loading: manufacturersLoading, getManufacturerByName } = useManufacturers();

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
  
  // Get Docuseal template ID - prefer direct mapping, fallback to Q code, then manufacturer config
  const getTemplateId = (): string | undefined => {
    if (!selectedProduct) return undefined;
    
    // First check our direct product mapping
    if (selectedProduct.id && PRODUCT_TEMPLATE_MAP[selectedProduct.id]) {
      console.log('Using direct product template mapping');
      return PRODUCT_TEMPLATE_MAP[selectedProduct.id];
    }
    
    // Second, check Q code mapping
    if (selectedProduct.q_code && Q_CODE_TEMPLATE_MAP[selectedProduct.q_code]) {
      console.log('Using Q code template mapping for:', selectedProduct.q_code);
      return Q_CODE_TEMPLATE_MAP[selectedProduct.q_code];
    }
    
    // Fallback to manufacturer config
    const config = selectedProduct.manufacturer ? getManufacturerByName(selectedProduct.manufacturer) : null;
    if (config?.docuseal_template_id) {
      console.log('Using manufacturer template mapping');
      return config.docuseal_template_id;
    }
    
    return undefined;
  };
  
  const templateId = getTemplateId();
  const manufacturerConfig = selectedProduct?.manufacturer 
    ? getManufacturerByName(selectedProduct.manufacturer)
    : null;
  
  // Check if manufacturer supports insurance upload in IVR
  const supportsInsuranceUpload = manufacturerConfig?.supports_insurance_upload_in_ivr === true;

  // Handle document uploads
  const handleDocumentsChange = (documents: DocumentUpload[]) => {
    // Find insurance card upload
    const insuranceUpload = documents.find(doc => doc.type === 'insurance_card');
    
    if (insuranceUpload) {
      // Update form data with the files
      updateFormData({
        insurance_card_front: insuranceUpload.files.primary?.file,
        insurance_card_back: insuranceUpload.files.secondary?.file,
      });
    }
  };

  const handleInsuranceDataExtracted = (data: any) => {
    const updates: any = {};

    // Update insurance information from extracted data
    if (data.payer_name) updates.primary_insurance_name = data.payer_name;
    if (data.payer_id) updates.primary_member_id = data.payer_id;
    
    updates.insurance_card_auto_filled = true;
    updateFormData(updates);
    setInsuranceCardSuccess(true);

    setTimeout(() => {
      setInsuranceCardSuccess(false);
    }, 5000);
  };

  // Debug logging
  console.log('Selected Product:', {
    name: selectedProduct?.name,
    id: selectedProduct?.id,
    manufacturer: selectedProduct?.manufacturer,
    templateId: templateId,
    manufacturer_id: selectedProduct?.manufacturer_id,
    code: selectedProduct?.code
  });
  console.log('All Manufacturers loaded:', manufacturers.map(m => ({ id: m.id, name: m.name, docuseal_template_id: m.docuseal_template_id })));
  console.log('Manufacturers loading:', manufacturersLoading);
  console.log('Looking for manufacturer:', selectedProduct?.manufacturer);
  console.log('Manufacturer Config found:', manufacturerConfig);
  console.log('Signature Required:', manufacturerConfig?.signature_required);

  // Get provider and facility details
  const provider = formData.provider_id ? providers.find(p => p.id === formData.provider_id) : null;
  const facility = formData.facility_id ? facilities.find(f => f.id === formData.facility_id) : null;

  // Build Docuseal payload using shared utility. This data will be passed to Docuseal.
  const preparedDocusealData = prepareDocusealData({ formData, products, providers, facilities });

  // Build extended payload for Docuseal
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

  // Merge everything into a single payload object to send to Docuseal
  const extendedDocusealData = {
    ...formData,
    ...preparedDocusealData,
    
    // Product details
    product_details: productDetails,
    selected_products: formData.selected_products,
    
    // Calculated fields
    wound_size_total: totalWoundSize.toFixed(2),
    wound_duration_formatted: woundDuration,
    diagnosis_codes_display: diagnosisCodeDisplay,
    
    // Service date formatting
    service_date: formData.expected_service_date,
    
    // Add provider PTAN directly
    provider_ptan: provider?.ptan || formData.provider_ptan || '',
    
    // Place of service mapping
    place_of_service: formData.place_of_service || '11', // Default to office
    
    // Debug info
    _debug: debugMode ? {
      template_id: templateId,
      manufacturer: selectedProduct?.manufacturer,
      product_id: selectedProduct?.id,
      product_code: selectedProduct?.code
    } : undefined
  };

  // Enhanced completion handler with FHIR integration and redirect
  const handleDocusealComplete = async (submissionData: any) => {
    setIsProcessing(true);
    
    try {
      console.log('🎉 Docuseal form completed:', submissionData);
      
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
        
        setRedirectTimeout(timeout as unknown as number);
      } else {
        console.error('Enhanced submission processing failed:', response.data.error);
        setIsCompleted(true); // Still mark as completed, but show warning
      }
      
    } catch (error) {
      console.error('Error processing Docuseal completion:', error);
      setSubmissionError('Form completed but there was an error processing the submission.');
    } finally {
      setIsProcessing(false);
    }
  };
  
  const handleDocusealError = (error: string) => {
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

  // Set NO_IVR_REQUIRED when manufacturer doesn't require signature
  useEffect(() => {
    if (!manufacturersLoading && manufacturerConfig !== undefined) {
      if (!manufacturerConfig || !manufacturerConfig.signature_required) {
        if (!formData.docuseal_submission_id) {
          updateFormData({ docuseal_submission_id: 'NO_IVR_REQUIRED' });
        }
      }
    }
  }, [manufacturersLoading, manufacturerConfig, formData.docuseal_submission_id, updateFormData]);

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
                <DocumentUploadCard
                  title=""
                  description=""
                  onDocumentsChange={handleDocumentsChange}
                  onInsuranceDataExtracted={handleInsuranceDataExtracted}
                  allowMultiple={false}
                  className="border-0 shadow-none p-0"
                />
              )}

              {insuranceCardSuccess && (
                <div className={cn(
                  "mt-3 p-3 rounded-lg flex items-center",
                  theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50'
                )}>
                  <FiCheck className="h-5 w-5 mr-2 text-green-500" />
                  <span className={cn("text-sm", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                    Insurance information updated successfully!
                  </span>
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
                <FiArrowRight className="mr-2 h-4 w-4" />
                Go to Order Summary Now
              </button>
            </div>
          </div>
        ) : submissionError ? (
          <div className="p-8 text-center">
            <FiAlertCircle className={cn("h-16 w-16 mx-auto mb-4 text-red-500")} />
            <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
              Submission Error
            </h3>
            <p className={cn("text-sm", t.text.secondary)}>
              {submissionError}
            </p>
          </div>
        ) : templateId ? (
          <div className="w-full">
            <DocusealEmbed
                  templateId={templateId}
                  onComplete={handleDocusealComplete}
                  onError={handleDocusealError}
                  className="w-full"
                  manufacturerId={''}
                  productCode={''}
                />
          </div>
        ) : (
          <div className="p-8 text-center">
            <FiFileText className={cn("h-16 w-16 mx-auto mb-4", t.text.tertiary)} />
            <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
              No Template Found
            </h3>
            <p className={cn("text-sm", t.text.secondary)}>
              Unable to find IVR template for the selected product.
            </p>
          </div>
        )}
      </div>

      {/* Debug Information */}
      {debugMode && !isCompleted && (
        <div className={cn("mt-4 p-4 rounded-lg text-xs font-mono", 
          theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
        )}>
          <p>Product: {selectedProduct?.name} (ID: {selectedProduct?.id})</p>
          <p>Manufacturer: {selectedProduct?.manufacturer}</p>
          <p>Template ID: {templateId || 'Not found'}</p>
          <p>Signature Required: {manufacturerConfig?.signature_required ? 'Yes' : 'No'}</p>
        </div>
      )}
    </div>
  );
}