import { useState } from 'react';
import { FiCheckCircle, FiAlertCircle, FiFileText } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import DocuSealIVRForm from '@/Components/DocuSeal/DocuSealIVRForm';
import { getManufacturerByProduct } from '../manufacturerFields';

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
  wound_types?: string[];
  wound_location?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  yellow_diagnosis_code?: string;
  orange_diagnosis_code?: string;

  // Insurance
  primary_insurance_name?: string;
  primary_member_id?: string;
  primary_plan_type?: string;

  // DocuSeal
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
    name: string;
    manufacturer: string;
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

export default function Step7DocuSealIVR({
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
  const manufacturerConfig = selectedProduct ? getManufacturerByProduct(selectedProduct.name) : null;

  // Get provider and facility details
  const provider = formData.provider_id ? providers.find(p => p.id === formData.provider_id) : null;
  const facility = formData.facility_id ? facilities.find(f => f.id === formData.facility_id) : null;

  // Prepare form data for DocuSeal
  const prepareDocuSealData = () => {
    if (!selectedProduct) return formData;

    // Get the product with full details
    const product = products.find(p => p.id === formData.selected_products?.[0]?.product_id);
    
    // Format selected products for display
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

    // Format wound types
    const woundTypesDisplay = formData.wound_types?.join(', ') || 'Not specified';

    return {
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
      product_name: selectedProduct.name,
      product_code: selectedProduct.code,
      product_manufacturer: selectedProduct.manufacturer,
      manufacturer_id: product?.manufacturer_id || selectedProduct.manufacturer_id, // Add manufacturer_id
      product_details: productDetails,
      product_details_text: productDetails.map((p: any) =>
        `${p.name} (${p.code}) - Size: ${p.size}, Qty: ${p.quantity}`
      ).join('\n'),

      // Clinical Information
      wound_types_display: woundTypesDisplay,
      total_wound_size: `${totalWoundSize} sq cm`,
      wound_dimensions: `${formData.wound_size_length || '0'} × ${formData.wound_size_width || '0'} × ${formData.wound_size_depth || '0'} cm`,

      // Manufacturer Fields (convert booleans to Yes/No for display)
      ...Object.entries(formData.manufacturer_fields || {}).reduce((acc, [key, value]) => {
        acc[key] = typeof value === 'boolean' ? (value ? 'Yes' : 'No') : value;
        return acc;
      }, {} as Record<string, any>),

      // Date fields
      service_date: formData.expected_service_date || new Date().toISOString().split('T')[0],
      submission_date: new Date().toISOString().split('T')[0],

      // Signature fields (for DocuSeal template)
      provider_signature_required: true,
      provider_signature_date: new Date().toISOString().split('T')[0]
    };
  };

  const handleDocuSealComplete = (submissionId: string) => {
    setIsCompleted(true);
    updateFormData({ docuseal_submission_id: submissionId });
  };

  const handleDocuSealError = (error: string) => {
    setSubmissionError(error);
  };

  // No product selected
  if (!selectedProduct) {
    return (
      <div className={cn("text-center py-12", t.text.secondary)}>
        <p>Please select a product first</p>
      </div>
    );
  }

  // No IVR required for this manufacturer
  if (!manufacturerConfig || !manufacturerConfig.signatureRequired) {
    return (
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <FiCheckCircle className={cn("h-12 w-12 mx-auto mb-4 text-green-500")} />
        <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>
          No IVR Required
        </h3>
        <p className={cn("text-sm", t.text.secondary)}>
          {selectedProduct.name} does not require an IVR form submission.
        </p>
        <p className={cn("text-sm mt-2", t.text.secondary)}>
          You can proceed to submit your order.
        </p>
      </div>
    );
  }

  // Get the appropriate template ID
  const templateId = manufacturerConfig.docusealTemplateId || '123456'; // Replace with your default DocuSeal template ID

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className={cn("p-4 rounded-lg", t.glass.card)}>
        <div className="flex items-start">
          <FiFileText className={cn("h-5 w-5 mt-0.5 flex-shrink-0 mr-3", t.text.secondary)} />
          <div>
            <h3 className={cn("text-lg font-medium", t.text.primary)}>
              Independent Verification Request (IVR)
            </h3>
            <p className={cn("text-sm mt-1", t.text.secondary)}>
              {manufacturerConfig.name} requires an electronic signature on their IVR form
            </p>
          </div>
        </div>
      </div>

      {/* Instructions */}
      {!isCompleted && !submissionError && (
        <div className={cn(
          "p-4 rounded-lg border",
          theme === 'dark'
            ? 'bg-blue-900/20 border-blue-800'
            : 'bg-blue-50 border-blue-200'
        )}>
          <div className="flex items-start">
            <FiAlertCircle className={cn(
              "h-5 w-5 mt-0.5 flex-shrink-0 mr-3",
              theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
            )} />
            <div>
              <h4 className={cn(
                "text-sm font-medium",
                theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
              )}>
                Please Review and Sign
              </h4>
              <ul className={cn(
                "mt-2 space-y-1 text-sm",
                theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
              )}>
                <li>• All order details have been pre-filled in the form</li>
                <li>• Review the information for accuracy</li>
                <li>• Sign electronically where indicated</li>
                <li>• Click "Complete" when finished</li>
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* DocuSeal Form or Completion Status */}
      <div className={cn("rounded-lg", t.glass.card)}>
        {isCompleted ? (
          <div className="p-8 text-center">
            <FiCheckCircle className={cn("h-16 w-16 mx-auto mb-4 text-green-500")} />
            <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
              IVR Form Completed Successfully
            </h3>
            <p className={cn("text-sm", t.text.secondary)}>
              The IVR form has been signed and submitted.
            </p>
            <p className={cn("text-sm mt-2", t.text.secondary)}>
              Submission ID: <span className="font-mono">{formData.docuseal_submission_id}</span>
            </p>
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
                      setSubmissionError(null);
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
          <DocuSealIVRForm
            formData={prepareDocuSealData()}
            templateId={templateId}
            onComplete={handleDocuSealComplete}
            onError={handleDocuSealError}
            episodeId={formData.episode_id}
          />
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

      {/* Order Summary */}
      <div className={cn(
        "p-4 rounded-lg border",
        theme === 'dark'
          ? 'bg-gray-800 border-gray-700'
          : 'bg-gray-50 border-gray-200'
      )}>
        <h4 className={cn("text-sm font-medium mb-3", t.text.primary)}>
          Order Summary
        </h4>
        <div className="space-y-2 text-sm">
          <div className="flex justify-between">
            <span className={t.text.secondary}>Patient:</span>
            <span className={t.text.primary}>
              {formData.patient_first_name} {formData.patient_last_name}
            </span>
          </div>
          <div className="flex justify-between">
            <span className={t.text.secondary}>Product:</span>
            <span className={t.text.primary}>
              {selectedProduct.name} ({selectedProduct.code})
            </span>
          </div>
          <div className="flex justify-between">
            <span className={t.text.secondary}>Provider:</span>
            <span className={t.text.primary}>
              {provider?.name || 'Not specified'}
            </span>
          </div>
          <div className="flex justify-between">
            <span className={t.text.secondary}>Service Date:</span>
            <span className={t.text.primary}>
              {formData.expected_service_date || 'Not specified'}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
