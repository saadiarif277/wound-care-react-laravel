import { useState, useEffect } from 'react';
import { FiFileText, FiCheck, FiAlertCircle, FiUser, FiActivity, FiShoppingCart, FiShield } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { DocuSealEmbed } from '@/Components/QuickRequest/DocuSealEmbed';
import axios from 'axios';

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
  provider_id?: number;
  provider_name?: string;
  provider_npi?: string;
  facility_id?: number;
  facility_name?: string;

  // Clinical Information
  wound_type?: string;
  wound_location?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  wound_onset_date?: string;
  failed_conservative_treatment?: boolean;
  treatment_tried?: string;
  current_dressing?: string;
  expected_service_date?: string;

  // Insurance Information
  primary_insurance_name?: string;
  primary_member_id?: string;
  primary_plan_type?: string;
  primary_payer_phone?: string;
  has_secondary_insurance?: boolean;
  secondary_insurance_name?: string;
  secondary_member_id?: string;

  // Product Selection
  selected_products?: Array<{
    product_id: number;
    quantity: number;
    size?: string;
    product?: any;
  }>;

  // Shipping
  shipping_same_as_patient?: boolean;
  shipping_address_line1?: string;
  shipping_address_line2?: string;
  shipping_city?: string;
  shipping_state?: string;
  shipping_zip?: string;
  delivery_notes?: string;

  // DocuSeal tracking
  final_submission_id?: string;

  [key: string]: any;
}

interface Step7Props {
  formData: QuickRequestFormData;
  updateFormData: (data: Partial<QuickRequestFormData>) => void;
  products: Array<{
    id: number;
    name: string;
    code: string;
    manufacturer: string;
    available_sizes?: number[];
    price_per_sq_cm?: number;
  }>;
  providers: Array<{
    id: number;
    name: string;
    npi?: string;
    credentials?: string;
  }>;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  errors: Record<string, string>;
  onSubmit: () => void;
}

export default function Step7FinalSubmission({
  formData,
  updateFormData,
  products,
  providers,
  facilities,
  errors,
  onSubmit
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

  const [submissionUrl, setSubmissionUrl] = useState<string | null>(null);
  const [submissionId, setSubmissionId] = useState<string | null>(null);
  const [isCreatingSubmission, setIsCreatingSubmission] = useState(false);
  const [isCompleted, setIsCompleted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [builderToken, setBuilderToken] = useState<string | null>(null);
  const [builderProps, setBuilderProps] = useState<any | null>(null);
  const [episodeId, setEpisodeId] = useState<string | null>(null);
  const [isCreatingEpisode, setIsCreatingEpisode] = useState(false);

  // Get selected product details
  const getSelectedProduct = () => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return null;
    }
    const selectedProductId = formData.selected_products[0]?.product_id;
    return products.find(p => p.id === selectedProductId);
  };

  // Get provider details
  const getProviderDetails = () => {
    return providers.find(p => p.id === formData.provider_id);
  };

  // Get facility details
  const getFacilityDetails = () => {
    return facilities.find(f => f.id === formData.facility_id);
  };

  // Create episode before DocuSeal
  const createEpisode = async () => {
    setIsCreatingEpisode(true);
    setError(null);

    try {
      const selectedProduct = getSelectedProduct();
      if (!selectedProduct) {
        throw new Error('No product selected');
      }

      // Prepare comprehensive form data for episode creation
      const episodeData = {
        patient_id: formData.patient_id || 'new-patient',
        patient_fhir_id: formData.patient_fhir_id || 'pending-fhir-id',
        patient_display_id: formData.patient_display_id || `${formData.patient_first_name?.substring(0, 2)}${formData.patient_last_name?.substring(0, 2)}${Math.floor(Math.random() * 10000)}`,
        selected_product_id: selectedProduct.id,
        facility_id: formData.facility_id,
        form_data: {
          ...formData,
          // Ensure product sizes are included
          selected_products: formData.selected_products?.map(p => ({
            ...p,
            product_name: products.find(prod => prod.id === p.product_id)?.name,
            product_code: products.find(prod => prod.id === p.product_id)?.code,
            manufacturer: products.find(prod => prod.id === p.product_id)?.manufacturer,
          }))
        }
      };

      const response = await axios.post('/quickrequest/prepare-docuseal-ivr', episodeData);
      
      if (response.data.success) {
        setEpisodeId(response.data.episode_id);
        // Store the DocuSeal URL and submission ID from episode creation
        setSubmissionUrl(response.data.docuseal_url);
        setSubmissionId(response.data.docuseal_submission_id);
        
        // Update form data with episode info
        updateFormData({
          episode_id: response.data.episode_id,
          docuseal_submission_id: response.data.docuseal_submission_id
        });

        console.log('Episode created successfully:', {
          episode_id: response.data.episode_id,
          submission_id: response.data.docuseal_submission_id
        });

        return true;
      } else {
        throw new Error(response.data.error || 'Failed to create episode');
      }
    } catch (error) {
      console.error('Error creating episode:', error);
      setError(error instanceof Error ? error.message : 'Failed to create episode');
      return false;
    } finally {
      setIsCreatingEpisode(false);
    }
  };

  // Create DocuSeal submission with prepopulated data (final submission form)
  const createDocuSealSubmission = async () => {
    setIsCreatingSubmission(true);
    setError(null);

    try {
      const selectedProduct = getSelectedProduct();
      const providerDetails = getProviderDetails();
      const facilityDetails = getFacilityDetails();

      // Use the new builder approach for final submission
      const builderData = {
        use_builder: true,
        template_type: 'final_submission',
        episode_id: episodeId, // Include episode ID for linking
        prefill_data: {
          // Include episode information
          episode_id: episodeId || '',
          ivr_submission_id: formData.docuseal_submission_id || '',
          
          // Include the selected products array for manufacturer template selection
          selected_products: formData.selected_products || [],

          // Patient Information
          patient_first_name: formData.patient_first_name || '',
          patient_last_name: formData.patient_last_name || '',
          patient_dob: formData.patient_dob || '',
          patient_gender: formData.patient_gender || '',
          patient_member_id: formData.patient_member_id || '',
          patient_address: `${formData.patient_address_line1 || ''} ${formData.patient_address_line2 || ''}`.trim(),
          patient_city: formData.patient_city || '',
          patient_state: formData.patient_state || '',
          patient_zip: formData.patient_zip || '',
          patient_phone: formData.patient_phone || '',
          patient_email: formData.patient_email || '',

          // Provider Information
          provider_name: providerDetails?.name || '',
          provider_npi: providerDetails?.npi || '',
          provider_credentials: providerDetails?.credentials || '',
          facility_name: facilityDetails?.name || '',
          facility_address: facilityDetails?.address || '',

          // Clinical Information
          wound_type: formData.wound_types?.join(', ') || formData.wound_type || '',
          wound_location: formData.wound_location || '',
          wound_location_details: formData.wound_location_details || '',
          wound_size: `${formData.wound_size_length || 0} x ${formData.wound_size_width || 0} x ${formData.wound_size_depth || 0} cm`,
          wound_onset_date: formData.wound_onset_date || '',
          wound_duration: formData.wound_duration || '',
          failed_conservative_treatment: formData.failed_conservative_treatment ? 'Yes' : 'No',
          treatment_tried: formData.treatment_tried || '',
          current_dressing: formData.current_dressing || '',
          expected_service_date: formData.expected_service_date || '',

          // Insurance Information
          primary_insurance: formData.primary_insurance_name || '',
          primary_member_id: formData.primary_member_id || '',
          primary_plan_type: formData.primary_plan_type || '',
          primary_payer_phone: formData.primary_payer_phone || '',
          has_secondary_insurance: formData.has_secondary_insurance ? 'Yes' : 'No',
          secondary_insurance: formData.secondary_insurance_name || '',
          secondary_member_id: formData.secondary_member_id || '',

          // Product Information with SIZES
          selected_product_name: selectedProduct?.name || '',
          selected_product_code: selectedProduct?.code || '',
          selected_product_manufacturer: selectedProduct?.manufacturer || '',
          product_quantity: formData.selected_products?.[0]?.quantity || 0,
          product_size: formData.selected_products?.[0]?.size || '',
          product_size_label: formData.selected_products?.[0]?.size_label || formData.selected_products?.[0]?.size || '',

          // Shipping Information
          shipping_same_as_patient: formData.shipping_same_as_patient ? 'Yes' : 'No',
          shipping_address: formData.shipping_same_as_patient
            ? `${formData.patient_address_line1 || ''} ${formData.patient_address_line2 || ''}`.trim()
            : `${formData.shipping_address_line1 || ''} ${formData.shipping_address_line2 || ''}`.trim(),
          shipping_city: formData.shipping_same_as_patient ? formData.patient_city : formData.shipping_city,
          shipping_state: formData.shipping_same_as_patient ? formData.patient_state : formData.shipping_state,
          shipping_zip: formData.shipping_same_as_patient ? formData.patient_zip : formData.shipping_zip,
          delivery_notes: formData.delivery_notes || '',
          shipping_speed: formData.shipping_speed || '',

          // Additional metadata
          submission_date: new Date().toISOString().split('T')[0],
          total_wound_area: (parseFloat(formData.wound_size_length || '0') * parseFloat(formData.wound_size_width || '0')).toString(),
          
          // Include all other form data for comprehensive prefill
          ...formData
        }
      };

      const response = await axios.post('/quickrequest/docuseal/create-final-submission', builderData);
      const data = response.data;

      if (data.success && data.jwt_token) {
        // Builder mode - store JWT token and builder props
        setBuilderToken(data.jwt_token);
        setBuilderProps({
          templateId: data.template_id,
          userEmail: data.user_email,
          integrationEmail: data.integration_email,
          templateName: data.template_name
        });

        console.log('DocuSeal builder token generated:', {
          template_id: data.template_id,
          user_email: data.user_email,
          template_name: data.template_name
        });
      } else {
        // Legacy mode - use embed URL
        setSubmissionUrl(data.embed_url);
        setSubmissionId(data.submission_id);

        console.log('DocuSeal submission created:', {
          template_id: data.template_id,
          manufacturer: data.manufacturer,
          submission_id: data.submission_id
        });
      }

      // Update form data with submission info
      updateFormData({
        final_submission_id: data.submission_id || 'builder-mode'
      });

    } catch (error) {
      console.error('Error creating DocuSeal submission:', error);
      setError(error instanceof Error ? error.message : 'Failed to create submission');
    } finally {
      setIsCreatingSubmission(false);
    }
  };

  // Initialize episode and DocuSeal submission on component mount
  useEffect(() => {
    const initializeSubmission = async () => {
      // First create episode if not already created
      if (!episodeId && !isCreatingEpisode && !error) {
        const episodeCreated = await createEpisode();
        
        // If episode created successfully, proceed with DocuSeal submission
        if (episodeCreated) {
          await createDocuSealSubmission();
        }
      } else if (episodeId && !submissionUrl && !builderToken && !isCreatingSubmission && !error) {
        // Episode exists but DocuSeal submission not created yet
        await createDocuSealSubmission();
      }
    };

    initializeSubmission();
  }, [episodeId]);

  const handleDocuSealComplete = (data: any) => {
    console.log('Final DocuSeal submission completed:', data);
    setIsCompleted(true);

    // Update form data
    updateFormData({
      final_submission_completed: true,
      final_submission_data: data
    });
  };

  const handleDocuSealError = (errorMessage: string) => {
    setError(errorMessage);
  };

  const handleDocuSealSave = (data: any) => {
    console.log('DocuSeal template saved:', data);
    // Template was saved but not yet sent
  };

  const handleDocuSealSend = (data: any) => {
    console.log('DocuSeal template sent for signing:', data);
    setIsCompleted(true);

    // Update form data
    updateFormData({
      final_submission_completed: true,
      final_submission_data: data,
      template_sent_for_signing: true
    });
  };

  const handleFinalSubmit = () => {
    if (!isCompleted) {
      alert('Please complete the submission form before proceeding.');
      return;
    }
    
    // Ensure episode ID is included in final submission
    updateFormData({
      episode_id: episodeId,
      final_submission_completed: true
    });
    
    onSubmit();
  };

  if (error) {
    return (
      <div className="space-y-6">
        <div className={cn("p-6 rounded-lg", t.status.error)}>
          <div className="flex items-center">
            <FiAlertCircle className="w-5 h-5 mr-2" />
            <h3 className="text-lg font-semibold">Error Creating Submission</h3>
          </div>
          <p className="mt-2">{error}</p>
          <button
            onClick={async () => {
              setError(null);
              if (!episodeId) {
                await createEpisode();
              } else {
                await createDocuSealSubmission();
              }
            }}
            disabled={isCreatingSubmission || isCreatingEpisode}
            className={cn(
              "mt-4 px-4 py-2 rounded-lg font-medium",
              t.button.primary.base,
              t.button.primary.hover
            )}
          >
            {(isCreatingSubmission || isCreatingEpisode) ? 'Retrying...' : 'Retry'}
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
          Final Order Submission
        </h2>
        <p className={cn("text-sm", t.text.secondary)}>
          Please review and submit your complete order request. This form has been prepopulated with all the information you provided.
        </p>
      </div>

      {/* Quick Summary */}
      <div className={cn("p-4 rounded-lg", t.glass.frost)}>
        <h3 className={cn("text-lg font-semibold mb-3", t.text.primary)}>Order Summary</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div className="flex items-center">
            <FiUser className={cn("w-4 h-4 mr-2", t.text.secondary)} />
            <span className={t.text.secondary}>
              {formData.patient_first_name} {formData.patient_last_name}
            </span>
          </div>
          <div className="flex items-center">
            <FiShield className={cn("w-4 h-4 mr-2", t.text.secondary)} />
            <span className={t.text.secondary}>
              {formData.primary_insurance_name}
            </span>
          </div>
          <div className="flex items-center">
            <FiShoppingCart className={cn("w-4 h-4 mr-2", t.text.secondary)} />
            <span className={t.text.secondary}>
              {getSelectedProduct()?.name}
            </span>
          </div>
        </div>
      </div>

      {/* DocuSeal Form */}
      {(isCreatingEpisode || isCreatingSubmission) ? (
        <div className={cn("p-8 text-center", t.glass.card, "rounded-lg")}>
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            {isCreatingEpisode ? 'Creating Episode & IVR' : 'Preparing DocuSeal Form Builder'}
          </h3>
          <p className={cn("text-sm", t.text.secondary)}>
            {isCreatingEpisode 
              ? 'Creating episode record and preparing IVR form with your product selection...' 
              : 'Please wait while we prepare your interactive form builder...'}
          </p>
          {episodeId && (
            <p className={cn("text-xs mt-2", t.text.secondary)}>
              Episode ID: {episodeId}
            </p>
          )}
        </div>
      ) : builderToken && builderProps ? (
        <div className="space-y-4">
          {isCompleted && (
            <div className={cn("p-4 rounded-lg flex items-center", t.status.success)}>
              <FiCheck className="w-5 h-5 mr-2" />
              <span>Form builder process completed successfully!</span>
            </div>
          )}

          <DocuSealEmbed
            jwtToken={builderToken}
            templateId={builderProps.templateId}
            userEmail={builderProps.userEmail}
            integrationEmail={builderProps.integrationEmail}
            templateName={builderProps.templateName}
            onComplete={handleDocuSealComplete}
            onError={handleDocuSealError}
            onSave={handleDocuSealSave}
            onSend={handleDocuSealSend}
            className="min-h-[800px]"
          />
        </div>
      ) : submissionUrl && submissionId ? (
        <div className="space-y-4">
          {isCompleted && (
            <div className={cn("p-4 rounded-lg flex items-center", t.status.success)}>
              <FiCheck className="w-5 h-5 mr-2" />
              <span>Submission form completed successfully!</span>
            </div>
          )}

          <DocuSealEmbed
            embedUrl={submissionUrl}
            submissionId={submissionId}
            onComplete={handleDocuSealComplete}
            onError={handleDocuSealError}
            className="min-h-[800px]"
          />
        </div>
      ) : null}

      {/* Final Submit Button */}
      <div className="flex justify-end space-x-4">
        <button
          onClick={handleFinalSubmit}
          disabled={!isCompleted}
          className={cn(
            "px-6 py-3 rounded-lg font-medium transition-all duration-200",
            isCompleted
              ? `${t.button.primary.base} ${t.button.primary.hover}`
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          )}
        >
          {isCompleted ? 'Submit Order Request' : 'Complete Form to Continue'}
        </button>
      </div>

      {/* Error Display */}
      {Object.keys(errors).length > 0 && (
        <div className={cn("p-4 rounded-lg", t.status.error)}>
          <div className="flex items-start">
            <FiAlertCircle className="w-5 h-5 mr-2 flex-shrink-0" />
            <div>
              <h4 className="font-medium">Please fix the following errors:</h4>
              <ul className="mt-2 space-y-1 text-sm">
                {Object.entries(errors).map(([field, error]) => (
                  <li key={field}>• {error}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
