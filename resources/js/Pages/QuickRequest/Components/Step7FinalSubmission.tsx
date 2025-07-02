import { useState, useEffect, useCallback } from 'react';
import { prepareDocuSealData } from './docusealUtils';
import { FiCheck, FiAlertCircle, FiUser, FiShoppingCart, FiShield, FiEdit3, FiActivity, FiHome, FiCreditCard } from 'react-icons/fi';
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
  const [openSections, setOpenSections] = useState({
    patient: true,
    insurance: true,
    clinical: true,
    product: true,
    provider: true,
    forms: true,
  });

  // DocuSeal Builder state
  const [builderToken, setBuilderToken] = useState<string | null>(null);
  const [builderProps, setBuilderProps] = useState<{
    templateId?: string;
    userEmail?: string;
    integrationEmail?: string;
    templateName?: string;
  } | null>(null);

  // Toggle section visibility
  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section as keyof typeof prev]
    }));
  };

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

  // Calculate total bill
  const calculateTotalBill = () => {
    if (!formData.selected_products) return 0;
    return formData.selected_products.reduce((total, item) => {
      const price = item.product?.price || 0;
      return total + (price * item.quantity);
    }, 0);
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

      const episodeData = {
        patient_fhir_id: formData.patient_fhir_id,
        patient_display_id: formData.patient_display_id,
        manufacturer_id: productToUse.manufacturer_id,
        status: 'ready_for_review',
        ivr_status: 'pending',
        metadata: {
          wound_type: formData.wound_type,
          wound_location: formData.wound_location,
          expected_service_date: formData.expected_service_date,
          primary_insurance: {
            name: formData.primary_insurance_name,
            member_id: formData.primary_member_id,
            plan_type: formData.primary_plan_type,
          },
          secondary_insurance: formData.has_secondary_insurance ? {
            name: formData.secondary_insurance_name,
            member_id: formData.secondary_member_id,
          } : null,
        }
      };

      console.log('Creating episode with data:', episodeData);

      const response = await axios.post('/api/v1/quick-request/create-episode', episodeData);

      if (response.data.success) {
        setEpisodeId(response.data.episode_id);
        console.log('Episode created successfully:', response.data.episode_id);
        return response.data.episode_id;
      } else {
        throw new Error(response.data.message || 'Failed to create episode');
      }
    } catch (error: any) {
      console.error('Error creating episode:', error);
      setError(error.response?.data?.message || error.message || 'Failed to create episode');
      throw error;
    } finally {
      setIsCreatingEpisode(false);
    }
  };

  // Create DocuSeal submission
  const createDocuSealSubmission = async () => {
    setIsCreatingSubmission(true);
    setError(null);

    try {
      if (!episodeId) {
        throw new Error('No episode ID available');
      }

      const selectedProduct = getSelectedProduct();
      if (!selectedProduct) {
        throw new Error('No product selected');
      }

      const docuSealData = prepareDocuSealData(formData, selectedProduct, episodeId);
      console.log('Prepared DocuSeal data:', docuSealData);

      const response = await axios.post('/api/v1/quick-request/generate-form-token', {
        episode_id: episodeId,
        form_data: docuSealData,
        template_id: selectedProduct.docuseal_template_id,
      });

      if (response.data.success) {
        setSubmissionUrl(response.data.submission_url);
        setSubmissionId(response.data.submission_id);
        setBuilderToken(response.data.builder_token);
        setBuilderProps(response.data.builder_props);
        console.log('DocuSeal submission created successfully');
      } else {
        throw new Error(response.data.message || 'Failed to create DocuSeal submission');
      }
    } catch (error: any) {
      console.error('Error creating DocuSeal submission:', error);
      setError(error.response?.data?.message || error.message || 'Failed to create DocuSeal submission');
    } finally {
      setIsCreatingSubmission(false);
    }
  };

  // Initialize submission process
  useEffect(() => {
    const initializeSubmission = async () => {
      if (!episodeId && !isCreatingEpisode) {
        try {
          await createEpisode();
        } catch (error) {
          console.error('Failed to create episode:', error);
        }
      }
    };

    initializeSubmission();
  }, [episodeId, isCreatingEpisode]);

  const handleDocuSealComplete = (data: any) => {
    console.log('DocuSeal completed:', data);
    setIsCompleted(true);
    // Call the parent's onSubmit function
    onSubmit();
  };

  const handleDocuSealError = (errorMessage: string) => {
    console.error('DocuSeal error:', errorMessage);
    setError(errorMessage);
  };

  const handleDocuSealSave = (data: any) => {
    console.log('DocuSeal saved:', data);
  };

  const handleDocuSealSend = (data: any) => {
    console.log('DocuSeal sent:', data);
  };

  const handleFinalSubmit = () => {
    if (!submissionUrl) {
      createDocuSealSubmission();
    }
  };

  // Check if order is complete
  const isOrderComplete = (): boolean => {
    return !!(
      formData?.patient_first_name &&
      formData?.patient_last_name &&
      formData?.patient_dob &&
      formData?.primary_insurance_name &&
      formData?.wound_type &&
      formData?.wound_location &&
      formData?.selected_products?.length > 0
    );
  };

  // If there's an error, show error state
  if (error) {
    return (
      <div className="max-w-6xl mx-auto space-y-6">
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <div className="flex items-center space-x-3 mb-4">
            <FiAlertCircle className={cn("w-6 h-6", t.text.error)} />
            <h3 className={cn("text-lg font-semibold", t.text.error)}>Error Creating Submission</h3>
          </div>
          <p className={cn("mb-4", t.text.secondary)}>{error}</p>
          <button
            onClick={() => {
              setError(null);
              setEpisodeId(null);
              setSubmissionUrl(null);
              setSubmissionId(null);
            }}
            className={cn("px-4 py-2 rounded-lg", t.button.primary.base, t.button.primary.hover)}
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  // If creating episode or submission, show loading state
  if (isCreatingEpisode || isCreatingSubmission) {
    return (
      <div className="max-w-6xl mx-auto space-y-6">
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <div className="flex items-center justify-center space-x-3">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <div>
              <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                {isCreatingEpisode ? 'Creating Episode...' : 'Creating DocuSeal Submission...'}
              </h3>
              <p className={cn("text-sm", t.text.secondary)}>Please wait while we prepare your submission.</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // If DocuSeal is ready, show the embed
  if (submissionUrl && builderToken) {
    return (
      <div className="max-w-6xl mx-auto space-y-6">
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <div className="flex items-center justify-between mb-6">
            <div>
              <h2 className={cn("text-2xl font-bold", t.text.primary)}>Complete Your Submission</h2>
              <p className={cn("text-sm mt-1", t.text.secondary)}>
                Please review and complete the DocuSeal form to finalize your order.
              </p>
            </div>
            <div className={cn("text-sm px-3 py-1 rounded-md", t.glass.frost)}>
              Episode #{episodeId}
            </div>
          </div>

          <DocuSealEmbed
            submissionUrl={submissionUrl}
            builderToken={builderToken}
            builderProps={builderProps}
            onComplete={handleDocuSealComplete}
            onError={handleDocuSealError}
            onSave={handleDocuSealSave}
            onSend={handleDocuSealSend}
          />
        </div>
      </div>
    );
  }

  // Main review display
  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className={cn("p-6 rounded-lg flex justify-between items-start", t.glass.card)}>
        <div>
          <h1 className={cn("text-2xl font-bold", t.text.primary)}>
            Review & Final Submission
          </h1>
          <p className={cn("text-sm mt-1", t.text.secondary)}>
            Please review all information before submitting your order
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={handleFinalSubmit}
            disabled={!isOrderComplete()}
            className={cn(
              "px-6 py-2 rounded-lg font-medium transition-all",
              isOrderComplete()
                ? `${t.button.primary.base} ${t.button.primary.hover}`
                : "bg-gray-300 text-gray-500 cursor-not-allowed"
            )}
          >
            Submit Order
          </button>
        </div>
      </div>

      {/* Patient & Insurance Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiUser />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Patient & Insurance</h3>
          </div>
          <button
            onClick={() => toggleSection('patient')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        {openSections.patient && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Patient Demographics */}
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Patient Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>
                    {formData.patient_first_name || ''} {formData.patient_last_name || ''}
                  </dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>DOB:</dt>
                  <dd className={t.text.primary}>{formData.patient_dob || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Gender:</dt>
                  <dd className={t.text.primary}>{formData.patient_gender || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                  <dd className={t.text.primary}>{formData.patient_phone || ''}</dd>
                </div>
                {formData.patient_email && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                    <dd className={t.text.primary}>{formData.patient_email}</dd>
                  </div>
                )}
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Address:</dt>
                  <dd className={t.text.primary}>
                    {formData.patient_address_line1 || ''}
                    <br />
                    {formData.patient_city || ''}, {formData.patient_state || ''} {formData.patient_zip || ''}
                  </dd>
                </div>
              </dl>
            </div>

            {/* Insurance Information */}
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Insurance Coverage</h4>

              {/* Primary Insurance */}
              <div className="mb-4">
                <h5 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Primary Insurance</h5>
                <dl className="space-y-1 text-sm">
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Payer:</dt>
                    <dd className={t.text.primary}>{formData.primary_insurance_name || ''}</dd>
                  </div>
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Plan Type:</dt>
                    <dd className={t.text.primary}>{formData.primary_plan_type || ''}</dd>
                  </div>
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Member ID:</dt>
                    <dd className={t.text.primary}>{formData.primary_member_id || ''}</dd>
                  </div>
                </dl>
              </div>

              {/* Secondary Insurance */}
              {formData.has_secondary_insurance && (
                <div>
                  <h5 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Secondary Insurance</h5>
                  <dl className="space-y-1 text-sm">
                    <div className="flex">
                      <dt className={cn("font-medium w-32", t.text.secondary)}>Payer:</dt>
                      <dd className={t.text.primary}>{formData.secondary_insurance_name || ''}</dd>
                    </div>
                    <div className="flex">
                      <dt className={cn("font-medium w-32", t.text.secondary)}>Member ID:</dt>
                      <dd className={t.text.primary}>{formData.secondary_member_id || ''}</dd>
                    </div>
                  </dl>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Clinical Information Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiActivity />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Clinical Information</h3>
          </div>
          <button
            onClick={() => toggleSection('clinical')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        {openSections.clinical && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Wound Details</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Wound Type:</dt>
                  <dd className={t.text.primary}>{formData.wound_type || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Location:</dt>
                  <dd className={t.text.primary}>{formData.wound_location || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Size:</dt>
                  <dd className={t.text.primary}>
                    {formData.wound_size_length && formData.wound_size_width
                      ? `${formData.wound_size_length} x ${formData.wound_size_width}cm`
                      : 'N/A'}
                  </dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Onset Date:</dt>
                  <dd className={t.text.primary}>{formData.wound_onset_date || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Expected Service:</dt>
                  <dd className={t.text.primary}>{formData.expected_service_date || ''}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Treatment History</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Failed Conservative:</dt>
                  <dd className={t.text.primary}>
                    {formData.failed_conservative_treatment ? 'Yes' : 'No'}
                  </dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Treatment Tried:</dt>
                  <dd className={t.text.primary}>{formData.treatment_tried || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Current Dressing:</dt>
                  <dd className={t.text.primary}>{formData.current_dressing || ''}</dd>
                </div>
              </dl>
            </div>
          </div>
        )}
      </div>

      {/* Product Selection Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiShoppingCart />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Product Selection</h3>
          </div>
          <button
            onClick={() => toggleSection('product')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        {openSections.product && (
          <div>
            {formData.selected_products && formData.selected_products.length > 0 ? (
              <div className="space-y-4">
                {formData.selected_products.map((item, index) => {
                  const product = getSelectedProduct();
                  return (
                    <div key={index} className={cn("p-4 rounded-lg", t.glass.frost)}>
                      <div className="flex justify-between items-start">
                        <div>
                          <h4 className={cn("font-medium", t.text.primary)}>
                            {product?.name || 'Product'}
                          </h4>
                          <p className={cn("text-sm", t.text.secondary)}>
                            Code: {product?.code || 'N/A'}
                          </p>
                          <p className={cn("text-sm", t.text.secondary)}>
                            Manufacturer: {product?.manufacturer || 'N/A'}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className={cn("font-medium", t.text.primary)}>
                            Qty: {item.quantity}
                          </p>
                          {item.size && (
                            <p className={cn("text-sm", t.text.secondary)}>
                              Size: {item.size}
                            </p>
                          )}
                          <p className={cn("text-sm", t.text.secondary)}>
                            ${product?.price || 0}
                          </p>
                        </div>
                      </div>
                    </div>
                  );
                })}
                <div className={cn("flex justify-between items-center pt-4 border-t", t.glass.border)}>
                  <span className={cn("font-medium", t.text.primary)}>Total Bill:</span>
                  <span className={cn("text-lg font-bold", t.text.primary)}>
                    ${calculateTotalBill().toFixed(2)}
                  </span>
                </div>
              </div>
            ) : (
              <p className={cn("text-sm", t.text.secondary)}>No products selected</p>
            )}
          </div>
        )}
      </div>

      {/* Provider & Facility Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiHome />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Provider & Facility</h3>
          </div>
          <button
            onClick={() => toggleSection('provider')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        {openSections.provider && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Provider Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{formData.provider_name || ''}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>NPI:</dt>
                  <dd className={t.text.primary}>{formData.provider_npi || ''}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Facility Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{formData.facility_name || ''}</dd>
                </div>
              </dl>
            </div>
          </div>
        )}
      </div>

      {/* Forms Status Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiShield />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Forms Status</h3>
          </div>
          <button
            onClick={() => toggleSection('forms')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        {openSections.forms && (
          <div className="space-y-4">
            <div className={cn("p-4 rounded-lg", t.glass.frost)}>
              <div className="flex justify-between items-center">
                <div>
                  <h4 className={cn("font-medium", t.text.primary)}>IVR Form</h4>
                  <p className={cn("text-sm", t.text.secondary)}>Insurance Verification Request</p>
                </div>
                <div className={cn("text-sm px-3 py-1 rounded-md", t.glass.frost)}>
                  Pending
                </div>
              </div>
            </div>

            <div className={cn("p-4 rounded-lg", t.glass.frost)}>
              <div className="flex justify-between items-center">
                <div>
                  <h4 className={cn("font-medium", t.text.primary)}>Order Form</h4>
                  <p className={cn("text-sm", t.text.secondary)}>Manufacturer Order Form</p>
                </div>
                <div className={cn("text-sm px-3 py-1 rounded-md", t.glass.frost)}>
                  Not Started
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Submit Button */}
      <div className="flex justify-center">
        <button
          onClick={handleFinalSubmit}
          disabled={!isOrderComplete()}
          className={cn(
            "px-8 py-3 rounded-lg font-medium transition-all",
            isOrderComplete()
              ? `${t.button.primary.base} ${t.button.primary.hover}`
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          )}
        >
          Submit Order
        </button>
      </div>

      {/* Validation Errors */}
      {!isOrderComplete() && (
        <div className={cn("p-4 rounded-lg", t.glass.card)}>
          <h4 className={cn("font-medium mb-2", t.text.error)}>Please complete the following:</h4>
          <ul className={cn("mt-2 space-y-1 text-sm", t.text.secondary)}>
            {!formData.patient_first_name && <li>• Patient first name</li>}
            {!formData.patient_last_name && <li>• Patient last name</li>}
            {!formData.patient_dob && <li>• Patient date of birth</li>}
            {!formData.primary_insurance_name && <li>• Primary insurance name</li>}
            {!formData.wound_type && <li>• Wound type</li>}
            {!formData.wound_location && <li>• Wound location</li>}
            {(!formData.selected_products || formData.selected_products.length === 0) && <li>• At least one product</li>}
          </ul>
        </div>
      )}
    </div>
  );
}
