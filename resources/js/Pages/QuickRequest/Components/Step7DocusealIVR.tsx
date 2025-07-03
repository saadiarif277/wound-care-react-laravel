import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { FiCheckCircle, FiAlertCircle, FiFileText, FiArrowRight, FiUser, FiShield, FiHeart, FiClock, FiStar, FiCheck, FiInfo, FiCreditCard, FiRefreshCw, FiUpload } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { DocusealEmbed } from '@/Components/QuickRequest/DocusealEmbed';
import { useManufacturers } from '@/Hooks/useManufacturers';
import axios from 'axios';
import { Button } from '@/Components/ui/Button';

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
  onNext?: () => void;
}

// Direct product-to-template mapping
// This eliminates the complex manufacturer lookup
const PRODUCT_TEMPLATE_MAP: Record<number, string> = {
  10: '1233913',  // Amnio AMP → MEDLIFE SOLUTIONS template
  // Add more product mappings as needed
  // Format: [product_id]: 'docuseal_template_id'
};

// Q-Code to template mapping for more reliable matching
const Q_CODE_TEMPLATE_MAP: Record<string, string> = {
  'Q4162': '1233913',  // MedLife template
  'Q4151': '1233918',  // Centurion AmnioBand
  'Q4128': '1233918',  // Centurion Allopatch
};

export default function Step7DocusealIVR({
  formData,
  updateFormData,
  products,
  providers = [],
  facilities = [],
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
  const [enhancedSubmission, setEnhancedSubmission] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedTemplate, setSelectedTemplate] = useState<any>(null);
  const [debugMode, setDebugMode] = useState(true); // Enable debug mode

  // Insurance card re-upload states
  const [showInsuranceUpload, setShowInsuranceUpload] = useState(false);
  const [isProcessingInsuranceCard, setIsProcessingInsuranceCard] = useState(false);
  const [insuranceCardSuccess, setInsuranceCardSuccess] = useState(false);

  // Use the manufacturers hook
  const { manufacturers, loading: manufacturersLoading, getManufacturerByName } = useManufacturers();

  // Get the selected product
  const getSelectedProduct = () => {
    console.log('=== IVR Step getSelectedProduct Debug ===');
    console.log('formData.selected_products:', formData.selected_products);
    console.log('formData.selected_products length:', formData.selected_products?.length);
    console.log('products array length:', products.length);

    if (!formData.selected_products || formData.selected_products.length === 0) {
      console.log('❌ No selected products found in IVR step');
      return null;
    }

    const firstProduct = formData.selected_products[0];
    console.log('First selected product:', firstProduct);

    if (!firstProduct?.product_id) {
      console.log('❌ First product missing product_id');
      return null;
    }

    // First try to find the product in the products array
    let foundProduct = products.find(p => p.id === firstProduct.product_id);

    // If not found in products array, use the product data from selected_products
    if (!foundProduct && firstProduct.product) {
      console.log('✅ Using product data from selected_products[0].product');
      foundProduct = firstProduct.product;
    } else if (foundProduct) {
      console.log('✅ Product found in products array');
    } else {
      console.log('❌ Product not found in products array and no product data in selected_products');
      console.log('Available product IDs:', products.map(p => p.id));
    }

    return foundProduct;
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
    // First try to find the product in the products array
    let prod = products.find(p => p.id === item.product_id);

    // If not found in products array, use the product data from selected_products
    if (!prod && item.product) {
      prod = item.product;
    }

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
    wound_size_total: totalWoundSize, // Send as number, not string with units
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

    // Signature fields (for Docuseal template)
    provider_signature_required: true,
    provider_signature_date: new Date().toISOString().split('T')[0]
  };

  // Override preparedDocusealData with the extended version
  Object.assign(preparedDocusealData, extendedDocusealData);

  // Log the final data being sent to Docuseal
  console.log('📊 Docuseal Form Data Prepared:', {
    fields: Object.keys(preparedDocusealData).length,
    hasPatientData: !!preparedDocusealData.patient_name,
    hasInsuranceData: !!preparedDocusealData.primary_insurance_name,
    hasProviderData: !!preparedDocusealData.provider_name,
    hasFacilityData: !!preparedDocusealData.facility_name,
    hasClinicalData: !!preparedDocusealData.wound_type,
    actualFormData: {
      patient_name: preparedDocusealData.patient_name,
      patient_dob: preparedDocusealData.patient_dob,
      provider_name: preparedDocusealData.provider_name,
      provider_npi: preparedDocusealData.provider_npi,
      primary_insurance_name: preparedDocusealData.primary_insurance_name,
      facility_name: preparedDocusealData.facility_name
    }
  });

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

      try {
        const apiFormData = new FormData();
        apiFormData.append('insurance_card_front', frontCard);
        if (backCard) {
          apiFormData.append('insurance_card_back', backCard);
        }

        const response = await fetch('/api/insurance-card/analyze', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
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
          }
        }
      } catch (error) {
        console.error('Error processing insurance card:', error);
      } finally {
        setIsProcessingInsuranceCard(false);
      }
    }
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

      // Since the Docuseal form is completed successfully,
      // mark as completed but don't auto-redirect
      console.log('✅ Docuseal submission completed successfully:', submissionId);

      setIsCompleted(true);

      // Show success message
      setSubmissionError(''); // Clear any previous errors

      // Don't auto-redirect - let user click next manually

    } catch (error) {
      console.error('Error processing Docuseal completion:', error);
      setSubmissionError('Form completed but there was an error processing the submission.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleDocusealError = (error: string) => {
    console.error('Docuseal error:', error);
    setSubmissionError(error);
    setIsProcessing(false);
  };

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
                      setSubmissionError('');
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

            <DocusealEmbed
              manufacturerId={manufacturerConfig?.id?.toString() || selectedProduct?.manufacturer_id?.toString() || '1'}
              templateId={templateId}
              productCode={selectedProduct?.code || ''}
              formData={preparedDocusealData}
              episodeId={formData.episode_id ? parseInt(formData.episode_id) : undefined}
              onComplete={handleDocusealComplete}
              onError={handleDocusealError}
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

    </div>
  );
}
