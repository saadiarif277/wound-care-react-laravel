// resources/js/Pages/QuickRequest/Components/Step4Confirmation.tsx
import { useState, useEffect } from 'react';
import { FiCheck, FiAlertCircle, FiExternalLink, FiX } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
<<<<<<< HEAD
import { getManufacturerConfig } from './manufacturerFields';
=======
import { useManufacturers } from '@/hooks/useManufacturers';
>>>>>>> origin/provider-side
import { DocuSealEmbed } from '@/Components/QuickRequest/DocuSealEmbed';

interface Step4Props {
  formData: any;
  updateFormData: (data: any) => void;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    sizes?: string[];
  }>;
  facilities: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  onSubmit: () => void;
  isSubmitting: boolean;
}

export default function Step4Confirmation({
  formData,
  updateFormData,
  products,
  facilities,
  onSubmit,
  isSubmitting
}: Step4Props) {
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

<<<<<<< HEAD
=======
  // Use manufacturers hook
  const { getManufacturerByName } = useManufacturers();

>>>>>>> origin/provider-side
  const [showIVRFrame, setShowIVRFrame] = useState(false);
  const [ivrSigned, setIvrSigned] = useState(false);
  // ASHLEY'S REQUIREMENT: ALL orders require IVR completion
  const [_signatureRequired, setSignatureRequired] = useState(true);
  const [docusealSubmissionId, setDocusealSubmissionId] = useState<string | null>(null);
  const [ivrError, setIvrError] = useState<string | null>(null);

  const selectedProduct = products.find(p => p.id === formData.product_id);
  const selectedFacility = facilities.find(f => f.id === formData.facility_id);
<<<<<<< HEAD
  const manufacturerConfig = selectedProduct ? getManufacturerConfig(selectedProduct.manufacturer) : null;
=======
  const manufacturerConfig = selectedProduct ? getManufacturerByName(selectedProduct.manufacturer) : null;
>>>>>>> origin/provider-side

  // Debug logging
  console.log('Step4Confirmation Debug:', {
    formData: {
      product_id: formData.product_id,
      size: formData.size,
      quantity: formData.quantity,
      facility_id: formData.facility_id
    },
    selectedProduct,
    selectedFacility,
    products: products.length,
    facilities: facilities.length
  });

  const getTemplateId = () => {
<<<<<<< HEAD
    // Get template ID from manufacturer config
    if (manufacturerConfig?.docusealTemplateId) {
      return manufacturerConfig.docusealTemplateId;
    }

    // Fallback template map if not in config
    const templateMap: Record<string, string> = {
      'Acell': '1234567',  // TODO: Replace with actual Acell template ID
      'Organogenesis': '2345678',  // TODO: Replace with actual Organogenesis template ID
      // Add other manufacturers as needed
    };

    return templateMap[selectedProduct?.manufacturer || ''] || '1234567'; // Default template
=======
    // Get template ID from manufacturer config (API data)
    if (manufacturerConfig?.docuseal_template_id) {
      return manufacturerConfig.docuseal_template_id;
    }

    // No fallback - template IDs should come from database
    console.error('No DocuSeal template ID found for manufacturer:', selectedProduct?.manufacturer);
    return null;
>>>>>>> origin/provider-side
  };

  useEffect(() => {
    // ASHLEY'S REQUIREMENT: ALL orders require IVR completion during submission
    // This ensures providers generate IVR before admin review, not after
    setSignatureRequired(true);

    // Check if IVR was already completed (for form persistence)
    if (formData.docuseal_submission_id) {
      setIvrSigned(true);
      setDocusealSubmissionId(formData.docuseal_submission_id);
    }
  }, [formData.docuseal_submission_id]);

  const handleIVRSign = () => {
    setShowIVRFrame(true);
    setIvrError(null);
  };

  const handleIVRComplete = (submissionId: string) => {
    setDocusealSubmissionId(submissionId);
    setIvrSigned(true);
    setShowIVRFrame(false);
    // Update form data with DocuSeal submission ID
    updateFormData({ docuseal_submission_id: submissionId });
  };

  const handleIVRError = (error: string) => {
    setIvrError(error);
    // Don't close the modal so user can see the error
  };

  const closeIVRModal = () => {
    setShowIVRFrame(false);
    setIvrError(null);
  };

  const formatPatientDisplay = () => {
    const firstName = formData.patient_first_name || '';
    const lastName = formData.patient_last_name || '';
    const firstTwo = firstName.substring(0, 2).toUpperCase();
    const lastTwo = lastName.substring(0, 2).toUpperCase();
    const randomNum = Math.floor(Math.random() * 9000) + 1000;
    return `${firstTwo}${lastTwo}${randomNum}`;
  };

  // ASHLEY'S REQUIREMENT: Cannot submit without IVR completion
  const canSubmit = ivrSigned && !isSubmitting;

  return (
    <div className="space-y-6">
      {/* Step Title */}
      <div>
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Step 4: Review & IVR Completion
        </h2>
        <p className={cn("mt-2", t.text.secondary)}>
          Review your order details and complete the required IVR form
        </p>
      </div>

      {/* Order Summary */}
      <div className={cn("p-6 rounded-lg", t.glass.base)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Order Summary
        </h3>

        <div className="space-y-4">
          {/* Patient Information */}
          <div>
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
              Patient Information
            </h4>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Display ID:</span>
                <span className={cn("ml-2", t.text.primary)}>{formatPatientDisplay()}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>DOB:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.patient_dob}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Member ID:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.patient_member_id || 'N/A'}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Payer:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.payer_name}</span>
              </div>
            </div>
          </div>

          {/* Product Details */}
          {selectedProduct && (
            <div>
              <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                Product Details
              </h4>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <span className={cn("font-medium", t.text.tertiary)}>Product:</span>
                  <span className={cn("ml-2", t.text.primary)}>
                    {selectedProduct.code} - {selectedProduct.name}
                  </span>
                </div>
                <div>
                  <span className={cn("font-medium", t.text.tertiary)}>Manufacturer:</span>
                  <span className={cn("ml-2", t.text.primary)}>{selectedProduct.manufacturer}</span>
                </div>
                <div>
                  <span className={cn("font-medium", t.text.tertiary)}>Size:</span>
                  <span className={cn("ml-2", t.text.primary)}>{formData.size || 'Not selected'}</span>
                </div>
                <div>
                  <span className={cn("font-medium", t.text.tertiary)}>Quantity:</span>
                  <span className={cn("ml-2", t.text.primary)}>{formData.quantity || 1}</span>
                </div>
              </div>
            </div>
          )}

          {/* Service Details */}
          <div>
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
              Service Details
            </h4>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Expected Service Date:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.expected_service_date}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Delivery Date:</span>
                <span className={cn("ml-2", t.text.primary)}>
                  {formData.delivery_date || 'Day before service'}
                </span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Wound Type:</span>
                <span className={cn("ml-2", t.text.primary)}>{formData.wound_type}</span>
              </div>
              <div>
                <span className={cn("font-medium", t.text.tertiary)}>Shipping Speed:</span>
                <span className={cn("ml-2", t.text.primary)}>
                  {formData.shipping_speed?.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase()) || 'Not selected'}
                </span>
              </div>
            </div>
          </div>

          {/* Shipping Information */}
          {selectedFacility && (
            <div>
              <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                Shipping Information
              </h4>
              <div className="text-sm">
                <div className="mb-2">
                  <span className={cn("font-medium", t.text.tertiary)}>Facility:</span>
                  <span className={cn("ml-2", t.text.primary)}>{selectedFacility.name}</span>
                </div>
                {selectedFacility.address && (
                  <div>
                    <span className={cn("font-medium", t.text.tertiary)}>Address:</span>
                    <span className={cn("ml-2", t.text.primary)}>{selectedFacility.address}</span>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* IVR Requirement - ALWAYS REQUIRED per Ashley's feedback */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4 flex items-center", t.text.primary)}>
          <FiAlertCircle className="mr-2 text-blue-500" />
          Insurance Verification Request (IVR) Required
        </h3>

        <div className={cn("p-4 rounded-lg mb-4",
          theme === 'dark' ? 'bg-blue-900/20' : 'bg-blue-50'
        )}>
          <p className={cn("text-sm", theme === 'dark' ? 'text-blue-400' : 'text-blue-700')}>
            All orders require IVR completion before submission. This ensures proper insurance
            verification and streamlines the admin review process. The form has been pre-filled
            with your order details.
          </p>
        </div>

        {!ivrSigned ? (
          <button
            onClick={handleIVRSign}
            className={cn(
              "w-full px-6 py-3 rounded-lg font-medium transition-all hover:shadow-lg flex items-center justify-center",
              "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white"
            )}
          >
            Complete IVR Form & Signature
            <FiExternalLink className="ml-2" />
          </button>
        ) : (
          <div className={cn("p-4 rounded-lg flex items-center",
            theme === 'dark' ? 'bg-green-900/20' : 'bg-green-50'
          )}>
            <FiCheck className="h-5 w-5 text-green-500 mr-2" />
            <div>
              <p className={cn("text-sm font-medium", theme === 'dark' ? 'text-green-400' : 'text-green-700')}>
                IVR completed successfully
              </p>
              <p className={cn("text-xs mt-1", theme === 'dark' ? 'text-green-500' : 'text-green-600')}>
                Submission ID: {docusealSubmissionId}
              </p>
            </div>
          </div>
        )}

        {ivrError && (
          <div className={cn("mt-4 p-4 rounded-lg",
            theme === 'dark' ? 'bg-red-900/20' : 'bg-red-50'
          )}>
            <p className={cn("text-sm", theme === 'dark' ? 'text-red-400' : 'text-red-700')}>
              Error: {ivrError}
            </p>
          </div>
        )}

        {showIVRFrame && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div className={cn("w-full max-w-5xl h-[90vh] rounded-lg overflow-hidden", t.glass.card)}>
              <div className="p-4 border-b flex items-center justify-between">
                <h3 className={cn("text-lg font-medium", t.text.primary)}>
                  Complete IVR Form & Signature
                </h3>
                <button
                  onClick={closeIVRModal}
                  className={cn(
                    "p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700",
                    t.text.secondary
                  )}
                >
                  <FiX className="h-5 w-5" />
                </button>
              </div>
              <div className="h-[calc(100%-4rem)] overflow-auto">
                <DocuSealEmbed
                  manufacturerId={formData.manufacturer_id?.toString() || '1'}
                  productCode={formData.product_code || ''}
                  formData={{
                    ...formData,
                    provider_email: formData.provider_email || 'provider@example.com',
                    provider_name: formData.provider_name || formData.provider_name,
                    docuseal_submission_id: docusealSubmissionId
                  }}
                  onComplete={handleIVRComplete}
                  onError={handleIVRError}
                  className="w-full h-full min-h-[600px]"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Attestations Summary */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          Attestations & Authorizations
        </h3>

        <div className="space-y-2">
          <div className="flex items-center">
            <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              Clinical attestations confirmed
            </span>
          </div>
          <div className="flex items-center">
            <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              Prior authorization consent provided
            </span>
          </div>
          <div className="flex items-center">
            <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            <span className={cn("text-sm", t.text.secondary)}>
              Provider authorization completed
            </span>
          </div>
          <div className="flex items-center">
            {ivrSigned ? (
              <FiCheck className="h-4 w-4 text-green-500 mr-2" />
            ) : (
              <div className="h-4 w-4 rounded-full border-2 border-gray-300 mr-2" />
            )}
            <span className={cn("text-sm", t.text.secondary)}>
              Insurance verification request completed
            </span>
          </div>
        </div>
      </div>

      {/* Submit Section */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <p className={cn("mb-4", t.text.secondary)}>
          By submitting this order, you confirm that all information is accurate and complete,
          and that the IVR has been properly generated for admin review.
        </p>

        <button
          onClick={onSubmit}
          disabled={!canSubmit}
          className={cn(
            "px-8 py-3 rounded-lg font-medium transition-all inline-flex items-center",
            canSubmit
              ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          )}
        >
          {isSubmitting ? (
            <>
              <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              Submitting Order with IVR...
            </>
          ) : (
            <>
              <FiCheck className="mr-2" />
              Submit Order for Admin Review
            </>
          )}
        </button>

        {!canSubmit && !isSubmitting && (
          <p className="mt-2 text-sm text-orange-500">
            Please complete the IVR form before submitting your order
          </p>
        )}
      </div>
    </div>
  );
}
