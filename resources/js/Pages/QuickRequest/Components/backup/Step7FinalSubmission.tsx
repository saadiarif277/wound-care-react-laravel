import { useState, useEffect, useCallback } from 'react';
import { FiCheck, FiAlertCircle, FiUser, FiShoppingCart, FiShield } from 'react-icons/fi';
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
  formData: FormData;
  updateFormData: (data: Partial<FormData>) => void;
  products: Array<{
    id: number;
    name: string;
    code: string;
    manufacturer: string;
    manufacturer_id?: number;
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
  // Debug logging
  console.log('=== Step7FinalSubmission Debug ===');
  console.log('formData.selected_products:', formData.selected_products);
  console.log('products passed to component:', products?.length, products?.map(p => ({ id: p.id, name: p.name })));

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

  const [episodeId, setEpisodeId] = useState<string | null>(null);
  const [submissionUrl, setSubmissionUrl] = useState<string | null>(null);
  const [submissionId, setSubmissionId] = useState<string | null>(null);
  const [isCreatingEpisode, setIsCreatingEpisode] = useState(false);
  const [isCreatingSubmission, setIsCreatingSubmission] = useState(false);
  const [isCompleted, setIsCompleted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // DocuSeal Builder state
  const [builderToken, setBuilderToken] = useState<string | null>(null);
  const [builderProps, setBuilderProps] = useState<{
    templateId?: string;
    userEmail?: string;
    integrationEmail?: string;
    templateName?: string;
  } | null>(null);

  // Get selected product details
  const getSelectedProduct = () => {
    console.log('=== getSelectedProduct Debug Info ===');
    console.log('formData.selected_products:', formData.selected_products);
    console.log('products array length:', products.length);
    console.log('products array:', products.map(p => ({ id: p.id, name: p.name, code: p.code })));

    if (!formData.selected_products || formData.selected_products.length === 0) {
      console.log('No selected products found');
      return null;
    }

    const selectedProductId = formData.selected_products[0]?.product_id;
    console.log('Looking for product with ID:', selectedProductId);

    const foundProduct = products.find(p => p.id === selectedProductId);
    console.log('Found product in products array:', foundProduct);

    // If not found in products array, use the stored product object
    if (!foundProduct && formData.selected_products[0]?.product) {
      console.log('Using stored product object:', formData.selected_products[0].product);
      return formData.selected_products[0].product;
    }

    return foundProduct;
  };

  // Create episode before DocuSeal
  const createEpisode = async () => {
    setIsCreatingEpisode(true);
    setError(null);

    try {
      const selectedProduct = getSelectedProduct();
      if (!selectedProduct) {
        // Provide more detailed error information
        const errorDetails = {
          selectedProducts: formData.selected_products,
          productsCount: products.length,
          firstProductId: formData.selected_products?.[0]?.product_id,
          availableProductIds: products.map(p => p.id)
        };
        console.error('No product selected - Debug info:', errorDetails);

        // Try to use the product object from selected_products if available
        const productFromSelection = formData.selected_products?.[0]?.product;
        if (productFromSelection && productFromSelection.id) {
          console.log('Using product from selection object:', productFromSelection);
          // Continue with the product from selection
        } else {
          throw new Error(`No product selected. Debug: ${JSON.stringify(errorDetails)}`);
        }
      }

      // Use either the found product or the product from selection
      const productToUse = selectedProduct || formData.selected_products?.[0]?.product;

      // Prepare comprehensive form data for episode creation
      // Try to get manufacturer_id from multiple sources
      let manufacturerId = productToUse?.manufacturer_id ||
                          (products.find(p => p.id === productToUse?.id)?.manufacturer_id) ||
                          formData.selected_products?.[0]?.product?.manufacturer_id;

      // If still no manufacturer_id, try to fetch it from the selected product
      if (!manufacturerId && formData.selected_products?.[0]?.product_id) {
        console.warn('No manufacturer_id found, will need to fetch from backend');
        // For now, we'll let the backend handle this by looking up the product
        manufacturerId = null;
      }

      const episodeData = {
        patient_id: formData.patient_id || 'new-patient',
        patient_fhir_id: formData.patient_fhir_id || 'pending-fhir-id',
        patient_display_id: formData.patient_display_id || `${formData.patient_first_name?.substring(0, 2)}${formData.patient_last_name?.substring(0, 2)}${Math.floor(Math.random() * 10000)}`,
        manufacturer_id: manufacturerId,
        selected_product_id: productToUse?.id || formData.selected_products?.[0]?.product_id, // Include product ID for backend lookup
        form_data: {
          ...formData,
          selected_product_id: productToUse?.id,
          facility_id: formData.facility_id,
          // Ensure product sizes are included
          selected_products: formData.selected_products?.map((p: { product_id: number; quantity: number; size?: string; product?: any; }) => ({
            ...p,
            product_name: products.find(prod => prod.id === p.product_id)?.name || p.product?.name,
            product_code: products.find(prod => prod.id === p.product_id)?.code || p.product?.code,
            manufacturer: products.find(prod => prod.id === p.product_id)?.manufacturer || p.product?.manufacturer,
          }))
        }
      };

      console.log('Creating episode with data:', episodeData);

      const response = await axios.post('/api/quick-request/create-episode', episodeData);

      if (response.data.success) {
        setEpisodeId(response.data.episode_id);

        // Update form data with episode info
        updateFormData({
          episode_id: response.data.episode_id,
          manufacturer_id: response.data.manufacturer_id
        });

        console.log('Episode created successfully:', {
          episode_id: response.data.episode_id,
          manufacturer_id: response.data.manufacturer_id
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
    if (isCreatingSubmission) return;
    setIsCreatingSubmission(true);

    try {
      // Get selected product for manufacturer information
      const selectedProduct = getSelectedProduct();
      if (!selectedProduct) {
        throw new Error('No product selected for DocuSeal submission');
      }

      // Determine manufacturer ID for template resolution
      const manufacturerId = selectedProduct.manufacturer_id ||
                           products.find(p => p.id === selectedProduct.id)?.manufacturer_id;

      console.log('Creating DocuSeal submission for manufacturer:', {
        manufacturer_id: manufacturerId,
        manufacturer_name: selectedProduct.manufacturer,
        product_name: selectedProduct.name
      });

      const builderData = {
        template_type: 'final_submission',
        use_builder: true, // Force builder mode for better UX
        prefill_data: {
          // Patient Information
          patient_first_name: formData.patient_first_name,
          patient_last_name: formData.patient_last_name,
          patient_dob: formData.patient_dob,
          patient_gender: formData.patient_gender,
          patient_member_id: formData.patient_member_id,
          patient_address_line1: formData.patient_address_line1,
          patient_address_line2: formData.patient_address_line2,
          patient_city: formData.patient_city,
          patient_state: formData.patient_state,
          patient_zip: formData.patient_zip,
          patient_phone: formData.patient_phone,
          patient_email: formData.patient_email,

          // Provider Information
          provider_id: formData.provider_id,
          provider_name: providers.find(p => p.id === formData.provider_id)?.name,
          provider_npi: providers.find(p => p.id === formData.provider_id)?.npi,
          facility_id: formData.facility_id,
          facility_name: facilities.find(f => f.id === formData.facility_id)?.name,

          // Clinical Information
          wound_type: formData.wound_type,
          wound_location: formData.wound_location,
          wound_size_length: formData.wound_size_length,
          wound_size_width: formData.wound_size_width,
          wound_size_depth: formData.wound_size_depth,
          wound_onset_date: formData.wound_onset_date,
          failed_conservative_treatment: formData.failed_conservative_treatment,
          treatment_tried: formData.treatment_tried,
          current_dressing: formData.current_dressing,
          expected_service_date: formData.expected_service_date,

          // Insurance Information
          primary_insurance_name: formData.primary_insurance_name,
          primary_member_id: formData.primary_member_id,
          primary_plan_type: formData.primary_plan_type,
          primary_payer_phone: formData.primary_payer_phone,
          has_secondary_insurance: formData.has_secondary_insurance,
          secondary_insurance_name: formData.secondary_insurance_name,
          secondary_member_id: formData.secondary_member_id,

          // Product Information
          selected_products: formData.selected_products,
          manufacturer_id: manufacturerId,
          manufacturer_name: selectedProduct.manufacturer,

          // Shipping Information
          shipping_same_as_patient: formData.shipping_same_as_patient,
          shipping_address_line1: formData.shipping_address_line1,
          shipping_address_line2: formData.shipping_address_line2,
          shipping_city: formData.shipping_city,
          shipping_state: formData.shipping_state,
          shipping_zip: formData.shipping_zip,
          delivery_notes: formData.delivery_notes,

          // Episode Information
          episode_id: episodeId,

          // Complete form data for debugging
          ...formData
        }
      };

      console.log('Sending DocuSeal builder request with data:', {
        template_type: builderData.template_type,
        use_builder: builderData.use_builder,
        manufacturer_id: manufacturerId,
        episode_id: episodeId
      });

      const response = await axios.post('/quickrequest/docuseal/create-final-submission', builderData);
      const data = response.data;

      console.log('DocuSeal response:', data);

      if (data.success && data.jwt_token) {
        // Builder mode - store JWT token and builder props
        setBuilderToken(data.jwt_token);

        // Determine template ID - try multiple sources
        let templateId = data.template_id;
        if (!templateId || templateId === 'null' || templateId === null) {
          // Fallback to a default template based on manufacturer
          const manufacturerKey = selectedProduct.manufacturer?.replace(/\s+/g, '').toLowerCase();
          console.warn('No template ID returned, using fallback for manufacturer:', manufacturerKey);
          templateId = null; // Let DocuSeal create a blank template
        }

        setBuilderProps({
          templateId: templateId,
          userEmail: data.user_email || 'limitless@mscwoundcare.com',
          integrationEmail: data.integration_email || data.user_email || 'limitless@mscwoundcare.com',
          templateName: data.template_name || `MSC ${selectedProduct.manufacturer} IVR Form`
        });

        console.log('DocuSeal builder token generated:', {
          template_id: templateId,
          user_email: data.user_email,
          template_name: data.template_name,
          has_jwt_token: !!data.jwt_token
        });
      } else {
        // Legacy mode - use embed URL
        if (data.embed_url) {
          setSubmissionUrl(data.embed_url);
          setSubmissionId(data.submission_id);

          console.log('DocuSeal submission created:', {
            template_id: data.template_id,
            manufacturer: data.manufacturer,
            submission_id: data.submission_id
          });
        } else {
          throw new Error(data.error || 'Failed to create DocuSeal submission - no embed URL or JWT token returned');
        }
      }

      // Update form data with submission info
      updateFormData({
        final_submission_id: data.submission_id || 'builder-mode',
        docuseal_template_id: data.template_id,
        docuseal_jwt_token: data.jwt_token
      });

        } catch (error: any) {
      console.error('Error creating DocuSeal submission:', error);
      let errorMessage = 'Failed to create submission';

      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (error?.response?.data?.error) {
        errorMessage = error.response.data.error;
      } else if (error?.response?.data?.message) {
        errorMessage = error.response.data.message;
      }

      setError(errorMessage);
    } finally {
      setIsCreatingSubmission(false);
    }
  };

  // Memoize createEpisode and createDocuSealSubmission to avoid unnecessary re-renders
  const createEpisodeMemo = useCallback(createEpisode, [formData, products]);
  const createDocuSealSubmissionMemo = useCallback(createDocuSealSubmission, [formData, products, providers, facilities, episodeId]);

  // Initialize episode and DocuSeal submission on component mount
  useEffect(() => {
    const initializeSubmission = async () => {
      // First create episode if not already created
      if (!episodeId && !isCreatingEpisode && !error) {
        const episodeCreated = await createEpisodeMemo();
        // If episode created successfully, proceed with DocuSeal submission
        if (episodeCreated) {
          await createDocuSealSubmissionMemo();
        }
      } else if (episodeId && !submissionUrl && !builderToken && !isCreatingSubmission && !error) {
        // Episode exists but DocuSeal submission not created yet
        await createDocuSealSubmissionMemo();
      }
    };
    initializeSubmission();
    // Add all referenced variables to the dependency array
  }, [
    episodeId,
    isCreatingEpisode,
    error,
    submissionUrl,
    builderToken,
    isCreatingSubmission,
    createEpisodeMemo,
    createDocuSealSubmissionMemo
  ]);

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
            token={builderToken}
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
            Url={submissionUrl}
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
