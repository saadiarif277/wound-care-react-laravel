import { useState, useEffect, useMemo } from 'react';
import { FiCheckCircle, FiAlertCircle, FiArrowRight, FiCheck, FiInfo, FiCreditCard, FiRefreshCw, FiUpload, FiBrain, FiMail, FiSend, FiFileText } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { useManufacturers } from '@/Hooks/useManufacturers';
import { fetchWithCSRF, hasPermission, handleAPIError } from '@/utils/csrf';
import { getIVRFormByManufacturer } from '@/config/localIVRFormMapping';
import FieldMappingConfidence from '@/Components/ML/FieldMappingConfidence';

// NEW: Manufacturer IVR System - No DocuSeal dependency
// Uses email-based manufacturer responses with our new SmartEmailSender system

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

  // NEW: Manufacturer IVR System
  manufacturer_submission_id?: string;
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

// NEW: Manufacturer IVR submission replaces DocuSeal template mapping

export default function Step7PDFIIVR({
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
  const [isSubmittingToManufacturer, setIsSubmittingToManufacturer] = useState(false);
  const [manufacturerSubmissionSuccess, setManufacturerSubmissionSuccess] = useState(false);

  // Insurance card re-upload states
  const [showInsuranceUpload, setShowInsuranceUpload] = useState(false);
  const [isProcessingInsuranceCard, setIsProcessingInsuranceCard] = useState(false);
  const [insuranceCardSuccess, setInsuranceCardSuccess] = useState(false);

  // ML Field Mapping States
  const [mlResults, setMLResults] = useState<any>(null);
  const [isProcessingML, setIsProcessingML] = useState(false);
  const [mlError, setMLError] = useState<string>('');

  // PDF States
  const [pdfDocumentId, setPdfDocumentId] = useState<string>('');
  const [pdfUrl, setPdfUrl] = useState<string>('');
  const [isGeneratingPDF, setIsGeneratingPDF] = useState(false);
  const [pdfError, setPdfError] = useState<string>('');
  const [showPDF, setShowPDF] = useState(false);

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

    if (frontCard && backCard) {
      setIsProcessingInsuranceCard(true);
      try {
        const formData = new FormData();
        formData.append('insurance_card_front', frontCard);
        formData.append('insurance_card_back', backCard);

        const response = await fetchWithCSRF('/api/v1/quick-request/ai-extract', {
          method: 'POST',
          body: formData,
        });

        if (response.ok) {
          const result = await response.json();
          if (result.success && result.data) {
            // Update form data with extracted information
            updateFormData({
              primary_insurance_name: result.data.primary_insurance_name || formData.primary_insurance_name,
              primary_member_id: result.data.primary_member_id || formData.primary_member_id,
              primary_plan_type: result.data.primary_plan_type || formData.primary_plan_type,
              insurance_extraction_confidence: result.data.confidence || 0,
              insurance_extraction_processed: true,
            });
            setInsuranceCardSuccess(true);
          }
        }
      } catch (error) {
        console.error('Insurance card processing error:', error);
        // Don't show error to user as the cards are still uploaded
      } finally {
        setIsProcessingInsuranceCard(false);
      }
    }
  };

  // ML Field Mapping Integration
  const handleMLFieldMapping = async () => {
    if (!selectedProduct?.manufacturer || !formData.episode_id) {
      setMLError('Missing manufacturer or episode information for ML mapping');
      return;
    }

    setIsProcessingML(true);
    setMLError('');

    try {
      console.log('🧠 Starting ML field mapping for:', selectedProduct.manufacturer);

      // Get the active PDF template for this manufacturer
      const templateResponse = await fetchWithCSRF(`/api/manufacturers/${selectedProduct.manufacturer_id || selectedProduct.manufacturer}/template`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      let templateId = null;
      if (templateResponse.ok) {
        const templateData = await templateResponse.json();
        templateId = templateData.data?.pdf_template_id || templateData.data?.docuseal_template_id;
      }

      const response = await fetchWithCSRF('/api/v1/ml/field-mapping/map-episode', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          episode_id: formData.episode_id,
          manufacturer_name: selectedProduct.manufacturer,
          manufacturer_id: selectedProduct.manufacturer_id,
          document_type: 'IVR',
          template_id: templateId || 'default',
          product_info: {
            product_id: selectedProduct.id,
            product_name: selectedProduct.name,
            product_code: selectedProduct.code,
            q_code: selectedProduct.q_code,
            manufacturer: selectedProduct.manufacturer,
            quantity: formData.selected_products?.[0]?.quantity || 1,
            size: formData.selected_products?.[0]?.size,
          },
          additional_data: {
            // Include current form data for context
            patient_info: {
              first_name: formData.patient_first_name,
              last_name: formData.patient_last_name,
              dob: formData.patient_dob,
              gender: formData.patient_gender,
              member_id: formData.patient_member_id,
              address: formData.patient_address_line1,
              city: formData.patient_city,
              state: formData.patient_state,
              zip: formData.patient_zip,
              phone: formData.patient_phone,
              email: formData.patient_email,
            },
            clinical_info: {
              wound_type: formData.wound_type,
              wound_location: formData.wound_location,
              wound_size: {
                length: formData.wound_size_length,
                width: formData.wound_size_width,
                depth: formData.wound_size_depth,
              },
              diagnosis_code: formData.primary_diagnosis_code || formData.diagnosis_code,
              duration: {
                days: formData.wound_duration_days,
                weeks: formData.wound_duration_weeks,
                months: formData.wound_duration_months,
                years: formData.wound_duration_years,
              },
              prior_applications: formData.prior_applications,
              prior_application_product: formData.prior_application_product,
              prior_application_within_12_months: formData.prior_application_within_12_months,
              hospice_status: formData.hospice_status,
              hospice_family_consent: formData.hospice_family_consent,
              hospice_clinically_necessary: formData.hospice_clinically_necessary,
            },
            insurance_info: {
              primary_name: formData.primary_insurance_name,
              member_id: formData.primary_member_id,
              plan_type: formData.primary_plan_type,
            },
            provider_info: {
              provider_id: formData.provider_id,
              provider_name: formData.provider_name,
              provider_email: formData.provider_email,
              provider_npi: formData.provider_npi,
              facility_name: formData.facility_name,
            }
          }
        }),
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          console.log('✅ ML field mapping successful:', result.data);
          setMLResults(result.data);
          
          // Update form data with ML-mapped fields
          if (result.data.mapped_fields) {
            updateFormData({
              manufacturer_fields: {
                ...formData.manufacturer_fields,
                ...result.data.mapped_fields,
                // Add metadata about ML mapping
                _ml_enhanced: true,
                _ml_confidence: result.data.metadata?.confidence_score || 0,
                _ml_timestamp: new Date().toISOString(),
                _template_id: templateId,
                _manufacturer_id: selectedProduct.manufacturer_id,
              }
            });
          }
          
          // Auto-generate PDF after successful ML mapping
          setTimeout(() => {
            generatePDF();
          }, 500);
        } else {
          throw new Error(result.message || 'ML field mapping failed');
        }
      } else {
        throw new Error(`ML API error: ${response.status}`);
      }
    } catch (error: any) {
      console.error('❌ ML field mapping error:', error);
      setMLError(error.message || 'Failed to process ML field mapping');
      
      // Still try to generate PDF even if ML mapping fails
      setTimeout(() => {
        generatePDF();
      }, 1000);
    } finally {
      setIsProcessingML(false);
    }
  };

  // Handle ML feedback from user
  const handleMLFeedback = async (sourceField: string, targetField: string, success: boolean, userFeedback?: string) => {
    try {
      await fetchWithCSRF('/api/v1/ml/field-mapping/feedback', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          source_field: sourceField,
          target_field: targetField,
          manufacturer_name: selectedProduct?.manufacturer,
          document_type: 'IVR',
          success: success,
          user_feedback: userFeedback,
          context: {
            episode_id: formData.episode_id,
            template_id: manufacturerConfig?.docuseal_template_id,
          }
        }),
      });

      console.log('✅ ML feedback recorded:', { sourceField, targetField, success });
    } catch (error) {
      console.error('❌ Failed to record ML feedback:', error);
    }
  };

  // Auto-trigger ML mapping when component loads with valid data
  useEffect(() => {
    if (selectedProduct?.manufacturer && formData.episode_id && !mlResults && !isProcessingML) {
      // Auto-trigger ML mapping after a short delay
      const timer = setTimeout(() => {
        handleMLFieldMapping();
      }, 1000);
      
      return () => clearTimeout(timer);
    }
  }, [selectedProduct?.manufacturer, formData.episode_id]);

  // Generate PDF for IVR with ML-enhanced data
  const generatePDF = async () => {
    if (!formData.episode_id || !selectedProduct) {
      setPdfError('Episode ID and product selection are required to generate PDF');
      return;
    }

    setIsGeneratingPDF(true);
    setPdfError('');

    try {
      console.log('📄 Generating IVR PDF for episode:', formData.episode_id);

      const response = await fetchWithCSRF('/api/v1/pdf/generate-ivr', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          episode_id: formData.episode_id,
          manufacturer_id: selectedProduct.manufacturer_id,
          manufacturer_name: selectedProduct.manufacturer,
          product_id: selectedProduct.id,
          template_selection: {
            document_type: 'ivr',
            manufacturer_id: selectedProduct.manufacturer_id,
            manufacturer_name: selectedProduct.manufacturer,
          },
          // Include ML-enhanced data if available
          form_data: {
            ...formData,
            // Include ML-mapped fields
            manufacturer_fields: formData.manufacturer_fields,
            // Product context
            selected_product: selectedProduct,
            selected_products: formData.selected_products,
          },
          for_review: true // Generate for review, not final submission
        }),
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          console.log('✅ PDF generated successfully:', result.data);
          setPdfDocumentId(result.data.document_id);
          setPdfUrl(result.data.pdf_url || result.data.download_url);
          setShowPDF(true);
          
          // Update form data with PDF document ID
          updateFormData({
            pdf_document_id: result.data.document_id,
            pdf_url: result.data.pdf_url || result.data.download_url,
          });
          
          // Mark as completed once PDF is generated
          setIsCompleted(true);
        } else {
          throw new Error(result.message || 'Failed to generate PDF');
        }
      } else {
        throw new Error(`PDF generation failed: ${response.status}`);
      }
    } catch (error: any) {
      console.error('❌ PDF generation error:', error);
      setPdfError(error.message || 'Failed to generate IVR PDF');
    } finally {
      setIsGeneratingPDF(false);
    }
  };

  // Auto-generate PDF when ML mapping is complete
  useEffect(() => {
    if (mlResults && !pdfDocumentId && !isGeneratingPDF && !pdfError) {
      // Generate PDF after ML mapping completes
      const timer = setTimeout(() => {
        generatePDF();
      }, 500);
      
      return () => clearTimeout(timer);
    }
  }, [mlResults, pdfDocumentId]);

  // NEW: Handle manufacturer IVR submission
  const handleManufacturerSubmission = async () => {
    // Check permissions first
    if (!hasPermission('create-product-requests')) {
      setSubmissionError('You do not have permission to submit manufacturer requests. Please contact your administrator.');
      return;
    }

    if (!selectedProduct || !selectedProduct.manufacturer) {
      setSubmissionError('No manufacturer selected for IVR submission.');
      return;
    }

    setIsSubmittingToManufacturer(true);
    setSubmissionError('');

    try {
      // Create manufacturer submission using our new system
      const submissionData = {
        episode_id: formData.episode_id,
        manufacturer_name: selectedProduct.manufacturer,
        manufacturer_id: selectedProduct.manufacturer_id,
        product_info: {
          product_id: selectedProduct.id,
          product_name: selectedProduct.name,
          product_code: selectedProduct.code,
          q_code: selectedProduct.q_code,
          quantity: formData.selected_products?.[0]?.quantity || 1,
          size: formData.selected_products?.[0]?.size,
        },
        patient_info: {
          first_name: formData.patient_first_name,
          last_name: formData.patient_last_name,
          dob: formData.patient_dob,
          gender: formData.patient_gender,
          member_id: formData.patient_member_id,
          address: {
            line1: formData.patient_address_line1,
            line2: formData.patient_address_line2,
            city: formData.patient_city,
            state: formData.patient_state,
            zip: formData.patient_zip,
          },
          phone: formData.patient_phone,
          email: formData.patient_email,
        },
        provider_info: {
          provider_id: formData.provider_id,
          provider_name: formData.provider_name,
          provider_email: formData.provider_email,
          provider_npi: formData.provider_npi,
          facility_name: formData.facility_name,
        },
        clinical_info: {
          wound_type: formData.wound_type,
          wound_location: formData.wound_location,
          wound_size: {
            length: formData.wound_size_length,
            width: formData.wound_size_width,
            depth: formData.wound_size_depth,
          },
          primary_diagnosis_code: formData.primary_diagnosis_code,
          secondary_diagnosis_code: formData.secondary_diagnosis_code,
          wound_duration: {
            days: formData.wound_duration_days,
            weeks: formData.wound_duration_weeks,
            months: formData.wound_duration_months,
            years: formData.wound_duration_years,
          },
          prior_applications: formData.prior_applications,
          prior_application_product: formData.prior_application_product,
          prior_application_within_12_months: formData.prior_application_within_12_months,
          hospice_status: formData.hospice_status,
          hospice_family_consent: formData.hospice_family_consent,
          hospice_clinically_necessary: formData.hospice_clinically_necessary,
        },
        insurance_info: {
          primary_insurance_name: formData.primary_insurance_name,
          primary_member_id: formData.primary_member_id,
          primary_plan_type: formData.primary_plan_type,
          has_insurance_cards: !!(formData.insurance_card_front || formData.insurance_card_back),
        },
        attachments: {
          insurance_card_front: formData.insurance_card_front,
          insurance_card_back: formData.insurance_card_back,
        },
        pdf_document_id: pdfDocumentId || formData.pdf_document_id, // Include the generated PDF document ID
      };

      const response = await fetchWithCSRF('/api/v1/quick-request/create-manufacturer-submission', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(submissionData),
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          // Update form data with submission ID
          updateFormData({
            manufacturer_submission_id: result.data.submission_id,
            manufacturer_submission_status: 'pending',
            manufacturer_submission_sent_at: new Date().toISOString(),
          });
          
          setManufacturerSubmissionSuccess(true);
          setIsCompleted(true);
          
          // Auto-advance to next step after a short delay
          setTimeout(() => {
            onNext?.();
          }, 2000);
        } else {
          setSubmissionError(result.message || 'Failed to submit manufacturer request.');
        }
      } else {
        const errorData = await response.json().catch(() => ({}));
        setSubmissionError(errorData.message || 'Failed to submit manufacturer request.');
      }
    } catch (error) {
      console.error('Manufacturer submission error:', error);
      setSubmissionError('An error occurred while submitting to the manufacturer. Please try again.');
    } finally {
      setIsSubmittingToManufacturer(false);
    }
  };

  // Auto-submit when component loads if we have all required data
  useEffect(() => {
    if (
      !isCompleted &&
      !isSubmittingToManufacturer &&
      !manufacturerSubmissionSuccess &&
      selectedProduct &&
      formData.patient_first_name &&
      formData.patient_last_name &&
      formData.episode_id &&
      !formData.manufacturer_submission_id
    ) {
      // Auto-submit after a brief delay to allow UI to render
      const timer = setTimeout(() => {
        handleManufacturerSubmission();
      }, 1000);
      
      return () => clearTimeout(timer);
    }
  }, [selectedProduct, formData, isCompleted, isSubmittingToManufacturer, manufacturerSubmissionSuccess]);

  // Show loading state while manufacturers are loading
  if (manufacturersLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600" />
        <p className={cn("text-sm ml-3", t.text.secondary)}>
          Loading manufacturer information...
        </p>
      </div>
    );
  }

  // Show error if no product selected
  if (!selectedProduct) {
    return (
      <div className={cn("text-center py-12", t.text.secondary)}>
        <FiAlertCircle className="h-12 w-12 mx-auto mb-4 text-red-500" />
        <p className="text-lg font-medium mb-2">No Product Selected</p>
        <p>Please go back and select a product to continue with the IVR submission.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="text-center">
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Manufacturer IVR Submission
        </h2>
        <p className={cn("text-sm mt-2", t.text.secondary)}>
          Submitting Insurance Verification Request to {selectedProduct.manufacturer}
        </p>
      </div>

      {/* Product Information */}
      <div className={cn("p-4 rounded-lg", t.glass.card, "border", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
        <h3 className={cn("text-lg font-semibold mb-2", t.text.primary)}>
          Selected Product
        </h3>
        <div className="space-y-2">
          <p className={cn("text-sm", t.text.primary)}>
            <strong>Product:</strong> {selectedProduct.name}
          </p>
          <p className={cn("text-sm", t.text.secondary)}>
            <strong>Code:</strong> {selectedProduct.code}
            {selectedProduct.q_code && <span> ({selectedProduct.q_code})</span>}
          </p>
          <p className={cn("text-sm", t.text.secondary)}>
            <strong>Manufacturer:</strong> {selectedProduct.manufacturer}
          </p>
          {formData.selected_products?.[0]?.quantity && (
            <p className={cn("text-sm", t.text.secondary)}>
              <strong>Quantity:</strong> {formData.selected_products[0].quantity}
            </p>
          )}
          {formData.selected_products?.[0]?.size && (
            <p className={cn("text-sm", t.text.secondary)}>
              <strong>Size:</strong> {formData.selected_products[0].size}
            </p>
          )}
        </div>
      </div>

      {/* ML Field Mapping Status */}
      <div className={cn("p-4 rounded-lg", t.glass.card, "border", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
        <div className="flex items-center justify-between mb-3">
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            <FiBrain className="inline mr-2" />
            AI Field Mapping
          </h3>
          {isProcessingML && (
            <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-600" />
          )}
        </div>
        
        {isProcessingML ? (
          <div className="flex items-center">
            <div className="animate-pulse h-2 bg-blue-600 rounded-full w-32 mr-3"></div>
            <span className={cn("text-sm", t.text.secondary)}>
              Processing field mapping with AI...
            </span>
          </div>
        ) : mlResults ? (
          <div className="space-y-3">
            <div className="flex items-center">
              <FiCheckCircle className="h-5 w-5 text-green-500 mr-2" />
              <span className={cn("text-sm font-medium", t.text.primary)}>
                AI mapping completed successfully
              </span>
            </div>
            
            {/* ML Confidence Component */}
            <FieldMappingConfidence
              mlResults={mlResults}
              onFeedback={handleMLFeedback}
              isReadOnly={false}
            />
          </div>
        ) : mlError ? (
          <div className="flex items-center">
            <FiAlertCircle className="h-5 w-5 text-red-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              AI mapping failed: {mlError}
            </span>
          </div>
        ) : (
          <div className="flex items-center">
            <FiInfo className="h-5 w-5 text-blue-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              AI field mapping will start automatically...
            </span>
          </div>
        )}
      </div>

      {/* PDF Generation Status */}
      <div className={cn("p-4 rounded-lg", t.glass.card, "border", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
        <div className="flex items-center justify-between mb-3">
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            <FiFileText className="inline mr-2" />
            IVR Document Generation
          </h3>
          {isGeneratingPDF && (
            <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-600" />
          )}
        </div>
        
        {isGeneratingPDF ? (
          <div className="flex items-center">
            <div className="animate-pulse h-2 bg-blue-600 rounded-full w-32 mr-3"></div>
            <span className={cn("text-sm", t.text.secondary)}>
              Generating PDF document with your data...
            </span>
          </div>
        ) : pdfDocumentId && pdfUrl ? (
          <div className="space-y-3">
            <div className="flex items-center">
              <FiCheckCircle className="h-5 w-5 text-green-500 mr-2" />
              <span className={cn("text-sm font-medium", t.text.primary)}>
                IVR document generated successfully
              </span>
            </div>
            
            <div className="flex space-x-3">
              <a
                href={pdfUrl}
                target="_blank"
                rel="noopener noreferrer"
                className={cn(
                  "inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors",
                  theme === 'dark'
                    ? 'bg-blue-700 hover:bg-blue-600 text-white'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                )}
              >
                <FiEye className="mr-1 h-4 w-4" />
                View PDF
              </a>
              
              <button
                onClick={() => setShowPDF(!showPDF)}
                className={cn(
                  "inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors",
                  theme === 'dark'
                    ? 'bg-gray-700 hover:bg-gray-600 text-gray-200'
                    : 'bg-gray-200 hover:bg-gray-300 text-gray-700'
                )}
              >
                {showPDF ? 'Hide Preview' : 'Show Preview'}
              </button>
            </div>
            
            {showPDF && (
              <div className="mt-4 p-4 border rounded-lg">
                <iframe
                  src={pdfUrl}
                  className="w-full h-96 border-0"
                  title="IVR Document Preview"
                />
              </div>
            )}
          </div>
        ) : pdfError ? (
          <div className="space-y-3">
            <div className="flex items-center">
              <FiAlertCircle className="h-5 w-5 text-red-500 mr-2" />
              <span className={cn("text-sm", t.text.secondary)}>
                PDF generation failed: {pdfError}
              </span>
            </div>
            
            <button
              onClick={generatePDF}
              className={cn(
                "inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors",
                theme === 'dark'
                  ? 'bg-red-700 hover:bg-red-600 text-white'
                  : 'bg-red-600 hover:bg-red-700 text-white'
              )}
            >
              <FiRefreshCw className="mr-1 h-4 w-4" />
              Retry PDF Generation
            </button>
          </div>
        ) : (
          <div className="flex items-center">
            <FiInfo className="h-5 w-5 text-blue-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              PDF will be generated after AI mapping completes...
            </span>
          </div>
        )}
      </div>

      {/* Main Content */}
      <div className="space-y-6">
        {submissionError ? (
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
                   submissionError.includes('server') ? 'Server Error' : 'Error Submitting IVR'}
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
                      setManufacturerSubmissionSuccess(false);
                      handleManufacturerSubmission();
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
        ) : showPDF && pdfUrl ? (
          /* PDF Review State */
          <div className="space-y-4">
            <div className={cn(
              "p-4 rounded-lg border",
              theme === 'dark' ? 'bg-blue-900/20 border-blue-800' : 'bg-blue-50 border-blue-200'
            )}>
              <div className="flex items-center mb-2">
                <FiFileText className={cn(
                  "h-5 w-5 mr-2",
                  theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
                )} />
                <h4 className={cn(
                  "text-sm font-medium",
                  theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
                )}>
                  Review IVR Form
                </h4>
              </div>
              <p className={cn(
                "text-sm",
                theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
              )}>
                Please review the IVR form below before submitting to the manufacturer.
              </p>
            </div>

            {/* PDF Viewer */}
            <div className={cn(
              "rounded-lg overflow-hidden border",
              theme === 'dark' ? 'border-gray-700' : 'border-gray-200'
            )}>
              <iframe
                src={pdfUrl}
                className="w-full h-[600px]"
                title="IVR Form Preview"
              />
            </div>

            {/* Action Buttons */}
            <div className="flex justify-between items-center">
              <button
                onClick={() => {
                  setShowPDF(false);
                  setPdfUrl('');
                  setPdfDocumentId('');
                  generatePDF();
                }}
                className={cn(
                  "inline-flex items-center px-4 py-2 text-sm font-medium rounded-md transition-colors",
                  theme === 'dark'
                    ? 'bg-gray-700 hover:bg-gray-600 text-white'
                    : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
                )}
              >
                <FiRefreshCw className="mr-2 h-4 w-4" />
                Regenerate PDF
              </button>

              <button
                onClick={handleManufacturerSubmission}
                disabled={isSubmittingToManufacturer}
                className={cn(
                  "inline-flex items-center px-6 py-2 text-sm font-medium rounded-md transition-colors",
                  theme === 'dark'
                    ? 'bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-700'
                    : 'bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-300'
                )}
              >
                {isSubmittingToManufacturer ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2" />
                    Submitting...
                  </>
                ) : (
                  <>
                    <FiSend className="mr-2 h-4 w-4" />
                    Submit to Manufacturer
                  </>
                )}
              </button>
            </div>
          </div>
        ) : manufacturerSubmissionSuccess ? (
          /* Success State */
          <div className={cn(
            "p-6 rounded-lg border text-center",
            theme === 'dark'
              ? 'bg-green-900/20 border-green-800'
              : 'bg-green-50 border-green-200'
          )}>
            <FiCheckCircle className={cn(
              "h-12 w-12 mx-auto mb-4",
              theme === 'dark' ? 'text-green-400' : 'text-green-600'
            )} />
            <h3 className={cn(
              "text-lg font-semibold mb-2",
              theme === 'dark' ? 'text-green-300' : 'text-green-900'
            )}>
              IVR Submitted Successfully!
            </h3>
            <p className={cn(
              "text-sm mb-4",
              theme === 'dark' ? 'text-green-400' : 'text-green-700'
            )}>
              Your Insurance Verification Request has been sent to {selectedProduct.manufacturer}. 
              You will receive email notifications about the approval status.
            </p>
            <div className={cn(
              "flex items-center justify-center space-x-2 text-sm",
              theme === 'dark' ? 'text-green-400' : 'text-green-700'
            )}>
              <FiMail className="h-4 w-4" />
              <span>Email sent to manufacturer</span>
            </div>
          </div>
        ) : isProcessingML || isGeneratingPDF ? (
          /* Processing State */
          <div className={cn(
            "p-6 rounded-lg border text-center",
            theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'
          )}>
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mx-auto mb-4" />
            <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>
              {isProcessingML ? 'Analyzing Form Fields...' : 'Generating IVR PDF...'}
            </h3>
            <p className={cn("text-sm", t.text.secondary)}>
              {isProcessingML 
                ? 'Using AI to map your data to manufacturer fields'
                : 'Creating PDF document with your information'}
            </p>
            {mlError && (
              <div className={cn(
                "mt-4 p-3 rounded-md text-sm",
                theme === 'dark' ? 'bg-red-900/20 text-red-400' : 'bg-red-50 text-red-700'
              )}>
                ML Error: {mlError}
              </div>
            )}
            {pdfError && (
              <div className={cn(
                "mt-4 p-3 rounded-md text-sm",
                theme === 'dark' ? 'bg-red-900/20 text-red-400' : 'bg-red-50 text-red-700'
              )}>
                PDF Error: {pdfError}
              </div>
            )}
          </div>
        ) : (
          /* Submission in Progress */
          <div className="relative">
            {/* Processing overlay */}
            {isSubmittingToManufacturer && (
              <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10 rounded-lg">
                <div className="bg-white p-6 rounded-lg text-center max-w-sm">
                  <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-600 mx-auto mb-3" />
                  <p className="text-gray-900 font-medium">Submitting to Manufacturer...</p>
                  <p className="text-gray-600 text-sm mt-1">Preparing IVR email with all documentation</p>
                </div>
              </div>
            )}

            {/* Submission Form */}
            <div className={cn("p-6 rounded-lg", t.glass.card, "border", theme === 'dark' ? 'border-gray-700' : 'border-gray-200')}>
              <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                Preparing IVR Submission
              </h3>
              
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
                      Patient Information
                    </h4>
                    <div className={cn("p-3 rounded border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                      <p className={cn("text-sm", t.text.secondary)}>
                        {formData.patient_first_name} {formData.patient_last_name}
                      </p>
                      <p className={cn("text-xs mt-1", t.text.secondary)}>
                        DOB: {formData.patient_dob || 'Not provided'}
                      </p>
                      <p className={cn("text-xs", t.text.secondary)}>
                        Member ID: {formData.patient_member_id || 'Not provided'}
                      </p>
                    </div>
                  </div>
                  
                  <div>
                    <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
                      Provider Information
                    </h4>
                    <div className={cn("p-3 rounded border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                      <p className={cn("text-sm", t.text.secondary)}>
                        {formData.provider_name || 'Provider information will be added automatically'}
                      </p>
                      <p className={cn("text-xs mt-1", t.text.secondary)}>
                        NPI: {formData.provider_npi || 'Auto-filled'}
                      </p>
                      <p className={cn("text-xs", t.text.secondary)}>
                        Facility: {formData.facility_name || 'Auto-filled'}
                      </p>
                    </div>
                  </div>
                </div>
                
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
                    Clinical Information
                  </h4>
                  <div className={cn("p-3 rounded border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                    <p className={cn("text-sm", t.text.secondary)}>
                      <strong>Wound Type:</strong> {formData.wound_type || 'Not specified'}
                    </p>
                    <p className={cn("text-sm", t.text.secondary)}>
                      <strong>Location:</strong> {formData.wound_location || 'Not specified'}
                    </p>
                    <p className={cn("text-sm", t.text.secondary)}>
                      <strong>Primary Diagnosis:</strong> {formData.primary_diagnosis_code || 'Not specified'}
                    </p>
                  </div>
                </div>
                
                <div>
                  <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
                    Insurance Information
                  </h4>
                  <div className={cn("p-3 rounded border", theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200')}>
                    <p className={cn("text-sm", t.text.secondary)}>
                      <strong>Primary Insurance:</strong> {formData.primary_insurance_name || 'Not provided'}
                    </p>
                    <p className={cn("text-sm", t.text.secondary)}>
                      <strong>Member ID:</strong> {formData.primary_member_id || 'Not provided'}
                    </p>
                    <p className={cn("text-sm", t.text.secondary)}>
                      <strong>Plan Type:</strong> {formData.primary_plan_type || 'Not provided'}
                    </p>
                  </div>
                </div>
              </div>
              
              <div className="mt-6 flex justify-center">
                <button
                  onClick={handleManufacturerSubmission}
                  disabled={isSubmittingToManufacturer}
                  className={cn(
                    "inline-flex items-center px-6 py-3 text-sm font-medium rounded-lg transition-colors",
                    theme === 'dark'
                      ? 'bg-blue-700 hover:bg-blue-600 text-white disabled:bg-gray-700'
                      : 'bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-400'
                  )}
                >
                  {isSubmittingToManufacturer ? (
                    <>
                      <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2" />
                      Submitting...
                    </>
                  ) : (
                    <>
                      <FiSend className="mr-2 h-4 w-4" />
                      Submit IVR to Manufacturer
                    </>
                  )}
                </button>
              </div>
            </div>
            
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
              
              {/* Instructions */}
              <div className={cn("p-3 rounded-md", theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50')}>
                <div className="flex items-start">
                  <FiInfo className={cn("h-4 w-4 mt-0.5 mr-2 flex-shrink-0", theme === 'dark' ? 'text-blue-400' : 'text-blue-600')} />
                  <div>
                    <p className={cn("text-sm", theme === 'dark' ? 'text-blue-300' : 'text-blue-800')}>
                      <strong>What happens next:</strong>
                    </p>
                    <ul className={cn("text-sm mt-1 space-y-1", theme === 'dark' ? 'text-blue-400' : 'text-blue-700')}>
                      <li>• An email will be sent to {selectedProduct.manufacturer} with all patient and clinical information</li>
                      <li>• Insurance cards and supporting documents will be attached automatically</li>
                      <li>• You'll receive email notifications when the manufacturer responds</li>
                      <li>• The manufacturer can approve or deny the request via email</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Validation Errors */}
      {errors.manufacturer_submission && (
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
            {errors.manufacturer_submission}
          </p>
        </div>
      )}

    </div>
  );
}
