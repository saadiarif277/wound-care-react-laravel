import { useState } from 'react';
import { FiCheck, FiAlertCircle, FiFileText, FiShoppingCart, FiDollarSign, FiUser, FiCalendar, FiPackage, FiTruck, FiActivity } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { cn } from '@/lib/utils';
import DocuSealIVRForm from '@/Components/DocuSeal/DocuSealIVRForm';

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
}

interface QuickRequestFormData {
  // Patient Information
  patient_first_name?: string;
  patient_last_name?: string;
  patient_dob?: string;
  patient_gender?: 'male' | 'female' | 'other' | 'unknown';
  patient_member_id?: string;
  patient_address_line1?: string;
  patient_address_line2?: string;
  patient_city?: string;
  patient_state?: string;
  patient_zip?: string;
  patient_phone?: string;

  // Clinical Information
  wound_types?: string[];
  wound_location?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  wound_onset_date?: string;
  failed_conservative_treatment?: boolean;
  treatment_tried?: string;
  current_dressing?: string;
  expected_service_date?: string;

  // Products
  selected_products?: SelectedProduct[];

  // Shipping
  shipping_same_as_patient?: boolean;
  shipping_address_line1?: string;
  shipping_address_line2?: string;
  shipping_city?: string;
  shipping_state?: string;
  shipping_zip?: string;
  delivery_notes?: string;

  // Insurance
  insurance_type?: string;
  payer_name?: string;
  payer_id?: string;
  primary_insurance_name?: string;

  // Provider
  provider_name?: string;
  provider_npi?: string;
  facility_name?: string;

  // Manufacturer fields
  manufacturer_fields?: Record<string, any>;
}

interface Step6Props {
  formData: QuickRequestFormData;
  updateFormData: (data: Partial<QuickRequestFormData>) => void;
  products: Array<{
    id: number;
    code: string;
    name: string;
    manufacturer: string;
    manufacturer_id?: number;
    available_sizes?: any;
    price_per_sq_cm?: number;
    docuseal_template_id?: string;
    signature_required?: boolean;
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
  isSubmitting: boolean;
}

export default function Step6ReviewSubmit({
  formData,
  products,
  errors,
  onSubmit
}: Step6Props) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isCompleted, setIsCompleted] = useState(false);
  const { theme = 'dark' } = useTheme();
  const t = themes[theme];

  // Get the selected product
  const getSelectedProduct = () => {
    if (!formData.selected_products || formData.selected_products.length === 0) {
      return null;
    }

    const selectedProductId = formData.selected_products[0]?.product_id;
    return products.find(p => p.id === selectedProductId);
  };

  const selectedProduct = getSelectedProduct();

  // Get the appropriate template ID from the product
  const templateId = selectedProduct?.docuseal_template_id || null;
  const signatureRequired = selectedProduct?.signature_required || false;

  const handleSubmit = async () => {
    setIsSubmitting(true);
    try {
      await onSubmit();
    } catch (error) {
      console.error('Error submitting order:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatAddress = (prefix: string = '') => {
    const addressLine1 = String(formData[`${prefix}address_line1` as keyof QuickRequestFormData] || '');
    const addressLine2 = String(formData[`${prefix}address_line2` as keyof QuickRequestFormData] || '');
    const city = String(formData[`${prefix}city` as keyof QuickRequestFormData] || '');
    const state = String(formData[`${prefix}state` as keyof QuickRequestFormData] || '');
    const zip = String(formData[`${prefix}zip` as keyof QuickRequestFormData] || '');

    if (!addressLine1) return 'Not provided';

    return (
      <>
        {addressLine1}
        {addressLine2 && <>, {addressLine2}</>}
        <br />
        {city}, {state} {zip}
      </>
    );
  };

  return (
    <div className="space-y-6">
      {/* Order Summary Header */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h2 className={cn("text-xl font-semibold mb-4", t.text.primary)}>
          Review Order Details
        </h2>
        <p className={cn("text-sm", t.text.secondary)}>
          Please review all information before submitting your order.
        </p>
      </div>

      {/* Patient Information */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <div className="flex items-center mb-4">
          <FiUser className={cn("w-5 h-5 mr-2", t.text.secondary)} />
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            Patient Information
          </h3>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <span className={cn("font-medium", t.text.primary)}>Name:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.patient_first_name} {formData.patient_last_name}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>DOB:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.patient_dob || 'Not provided'}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Member ID:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.patient_member_id || 'Not provided'}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Phone:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.patient_phone || 'Not provided'}
            </span>
          </div>
        </div>
        <div className="mt-4">
          <span className={cn("font-medium", t.text.primary)}>Address:</span>
          <div className={cn("ml-2 mt-1", t.text.secondary)}>
            {formatAddress('patient_')}
          </div>
        </div>
      </div>

      {/* Clinical Information */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <div className="flex items-center mb-4">
          <FiActivity className={cn("w-5 h-5 mr-2", t.text.secondary)} />
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            Clinical Information
          </h3>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <span className={cn("font-medium", t.text.primary)}>Wound Type:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.wound_types?.join(', ') || 'Not specified'}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Location:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.wound_location || 'Not specified'}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Dimensions:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.wound_size_length || '0'} × {formData.wound_size_width || '0'} × {formData.wound_size_depth || '0'} cm
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Onset Date:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.wound_onset_date || 'Not provided'}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Service Date:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.expected_service_date || 'Not provided'}
            </span>
          </div>
          <div>
            <span className={cn("font-medium", t.text.primary)}>Failed Conservative Treatment:</span>
            <span className={cn("ml-2", t.text.secondary)}>
              {formData.failed_conservative_treatment ? 'Yes' : 'No'}
            </span>
          </div>
        </div>
      </div>

      {/* Product Selection */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <div className="flex items-center mb-4">
          <FiShoppingCart className={cn("w-5 h-5 mr-2", t.text.secondary)} />
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            Selected Product
          </h3>
        </div>
        {selectedProduct ? (
          <div className="space-y-3">
            <div className={cn("p-4 rounded-lg", t.glass.frost)}>
              <h4 className={cn("font-medium", t.text.primary)}>
                {selectedProduct.name}
              </h4>
              <p className={cn("text-sm mt-1", t.text.secondary)}>
                {selectedProduct.code} • {selectedProduct.manufacturer}
              </p>
              <div className="mt-3 space-y-2">
                {formData.selected_products?.map((item, index) => (
                  <div key={index} className={cn("flex justify-between text-sm", t.text.secondary)}>
                    <span>
                      Size: {item.size || 'Standard'} • Qty: {item.quantity}
                    </span>
                    {selectedProduct.price_per_sq_cm && (
                      <span>
                        ${((item.size ? parseFloat(item.size) : 1) * selectedProduct.price_per_sq_cm * item.quantity).toFixed(2)}
                      </span>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        ) : (
          <p className={cn("text-sm", t.text.secondary)}>No product selected</p>
        )}
      </div>

      {/* Shipping Information */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <div className="flex items-center mb-4">
          <FiTruck className={cn("w-5 h-5 mr-2", t.text.secondary)} />
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>
            Shipping Information
          </h3>
        </div>
        <div className={cn("text-sm", t.text.secondary)}>
          {formData.shipping_same_as_patient ? (
            <p>Same as patient address</p>
          ) : (
            <div>{formatAddress('shipping_')}</div>
          )}
          {formData.delivery_notes && (
            <div className="mt-4">
              <span className={cn("font-medium", t.text.primary)}>Delivery Notes:</span>
              <p className={cn("mt-1", t.text.secondary)}>{formData.delivery_notes}</p>
            </div>
          )}
        </div>
      </div>

      {/* Order Total */}
      {selectedProduct && selectedProduct.price_per_sq_cm && (
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <FiDollarSign className={cn("w-5 h-5 mr-2", t.text.secondary)} />
              <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                Order Total
              </h3>
            </div>
            <span className={cn("text-2xl font-bold", t.text.primary)}>
              ${formData.selected_products?.reduce((total, item) => {
                const size = item.size ? parseFloat(item.size) : 1;
                return total + (size * (selectedProduct.price_per_sq_cm || 0) * item.quantity);
              }, 0).toFixed(2)}
            </span>
          </div>
          <p className={cn("text-xs mt-2", t.text.tertiary)}>
            * Pricing shown is National ASP
          </p>
        </div>
      )}

      {/* DocuSeal IVR Section */}
      {signatureRequired && templateId && (
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <div className="mb-4">
            <div className="flex items-center">
              <FiFileText className={cn("w-5 h-5 mr-2", t.text.secondary)} />
              <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                Electronic Signature Required
              </h3>
            </div>
            <p className={cn("text-sm mt-1", t.text.secondary)}>
              {selectedProduct?.manufacturer} requires an electronic signature on their IVR form
            </p>
          </div>

          <DocuSealIVRForm
            templateId={templateId}
            formData={formData}
            onComplete={() => setIsCompleted(true)}
            onError={(error) => console.error('DocuSeal error:', error)}
          />

          {isCompleted && (
            <div className={cn("mt-4 p-4 rounded-lg flex items-center", t.status.success)}>
              <FiCheck className="w-5 h-5 mr-2" />
              <span>IVR form completed successfully!</span>
            </div>
          )}
        </div>
      )}

      {/* Submit Button */}
      <div className="flex justify-end space-x-4">
        <button
          type="button"
          onClick={handleSubmit}
          disabled={isSubmitting || (signatureRequired && !isCompleted)}
          className={cn(
            "px-6 py-3 rounded-lg font-medium transition-all duration-200",
            isSubmitting || (signatureRequired && !isCompleted)
              ? "bg-gray-300 text-gray-500 cursor-not-allowed"
              : `${t.button.primary.base} ${t.button.primary.hover}`
          )}
        >
          {isSubmitting ? 'Submitting...' : 'Submit Order'}
        </button>
      </div>

      {/* Error Display */}
      {Object.keys(errors).length > 0 && (
        <div className={cn("mt-4 p-4 rounded-lg", t.status.error)}>
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
