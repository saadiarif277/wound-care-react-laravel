import { useState } from 'react';
import { router } from '@inertiajs/react';
import { FiUser, FiActivity, FiShoppingCart, FiFileText, FiCheck, FiEdit3, FiAlertCircle } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface QuickRequestFormData {
  // Patient Information
  patient_first_name?: string;
  patient_last_name?: string;
  patient_dob?: string;
  patient_gender?: string;
  patient_phone?: string;
  patient_email?: string;
  patient_address_line1?: string;
  patient_city?: string;
  patient_state?: string;
  patient_zip?: string;

  // Insurance Information
  primary_insurance_name?: string;
  primary_member_id?: string;
  primary_plan_type?: string;
  has_secondary_insurance?: boolean;
  secondary_insurance_name?: string;
  secondary_member_id?: string;

  // Clinical Information
  wound_type?: string;
  wound_location?: string;
  wound_size_length?: string;
  wound_size_width?: string;
  wound_size_depth?: string;
  primary_diagnosis_code?: string;
  secondary_diagnosis_code?: string;
  diagnosis_code?: string;

  // Product Selection
  selected_products?: Array<{
    product_id: number;
    quantity: number;
    size?: string;
    product?: any;
  }>;

  // Episode tracking
  episode_id?: string;
  patient_display_id?: string;
  patient_fhir_id?: string;

  [key: string]: any;
}

interface Step6Props {
  formData: QuickRequestFormData;
  updateFormData: (data: Partial<QuickRequestFormData>) => void;
  products: Array<any>;
  providers: Array<any>;
  facilities: Array<any>;
  errors: Record<string, string>;
  onSubmit: () => void;
  isSubmitting: boolean;
  orderId?: string;
}

export default function Step6ReviewSubmit({
  formData,
  products,
  errors,
  onSubmit,
  isSubmitting
}: Step6Props) {
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmChecked, setConfirmChecked] = useState(false);

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

  const handleEdit = (section: string) => {
    // In the Quick Request flow, navigate to the appropriate step
    const stepMap: Record<string, number> = {
      'patient-insurance': 0,
      'clinical-billing': 1,
      'product-selection': 2,
      'ivr-form': 3
    };

    const step = stepMap[section];
    if (step !== undefined) {
      // Navigate to the specific step
      router.visit(`/quick-request/create?step=${step}`);
    }
  };

  const handleSubmit = async () => {
    if (!confirmChecked) return;

    try {
      await onSubmit();
    } catch (error) {
      console.error('Submission error:', error);
    }
  };

  const isOrderComplete = () => {
    // Check required fields per section
    const patientComplete = !!(
      formData.patient_first_name &&
      formData.patient_last_name &&
      formData.patient_dob &&
      formData.primary_insurance_name &&
      formData.primary_member_id
    );

    const clinicalComplete = !!(
      formData.wound_type &&
      formData.wound_location &&
      formData.wound_size_length &&
      formData.wound_size_width
    );

    const productsComplete = formData.selected_products && formData.selected_products.length > 0;

    return patientComplete && clinicalComplete && productsComplete;
  };

  const getSelectedProductDetails = (productId: number) => {
    return products.find(p => p.id === productId);
  };

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className={cn("p-6 rounded-lg flex justify-between items-start", t.glass.card)}>
        <div>
          <h1 className={cn("text-2xl font-bold", t.text.primary)}>
            Review & Confirm Order
          </h1>
          <p className={cn("text-sm mt-1", t.text.secondary)}>
            Please review all information before submitting
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => setShowConfirmModal(true)}
            disabled={!isOrderComplete() || isSubmitting}
            className={cn(
              "px-6 py-2 rounded-lg font-medium transition-all",
              isOrderComplete() && !isSubmitting
                ? `${t.button.primary.base} ${t.button.primary.hover}`
                : "bg-gray-300 text-gray-500 cursor-not-allowed"
            )}
          >
            {isSubmitting ? 'Submitting...' : 'Submit Order'}
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
            onClick={() => handleEdit('patient-insurance')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Patient Demographics */}
          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Patient Information</h4>
            <dl className="space-y-2 text-sm">
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                <dd className={t.text.primary}>
                  {formData.patient_first_name} {formData.patient_last_name}
                </dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>DOB:</dt>
                <dd className={t.text.primary}>{formData.patient_dob}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Gender:</dt>
                <dd className={t.text.primary}>{formData.patient_gender}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                <dd className={t.text.primary}>{formData.patient_phone}</dd>
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
                  {formData.patient_address_line1}
                  <br />
                  {formData.patient_city}, {formData.patient_state} {formData.patient_zip}
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
                  <dd className={t.text.primary}>{formData.primary_insurance_name}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Plan:</dt>
                  <dd className={t.text.primary}>{formData.primary_plan_type}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Policy #:</dt>
                  <dd className={t.text.primary}>{formData.primary_member_id}</dd>
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
                    <dd className={t.text.primary}>{formData.secondary_insurance_name}</dd>
                  </div>
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Policy #:</dt>
                    <dd className={t.text.primary}>{formData.secondary_member_id}</dd>
                  </div>
                </dl>
              </div>
            )}
          </div>
        </div>
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
            onClick={() => handleEdit('clinical-billing')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Wound Assessment</h4>
            <dl className="space-y-2 text-sm">
              <div className="flex">
                <dt className={cn("font-medium w-32", t.text.secondary)}>Wound Type:</dt>
                <dd className={t.text.primary}>{formData.wound_type}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-32", t.text.secondary)}>Location:</dt>
                <dd className={t.text.primary}>{formData.wound_location}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-32", t.text.secondary)}>Size:</dt>
                <dd className={t.text.primary}>
                  {formData.wound_size_length} × {formData.wound_size_width} × {formData.wound_size_depth} cm
                </dd>
              </div>
            </dl>
          </div>

          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Diagnosis Codes</h4>
            <dl className="space-y-2 text-sm">
              {formData.primary_diagnosis_code && (
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Primary:</dt>
                  <dd className={t.text.primary}>{formData.primary_diagnosis_code}</dd>
                </div>
              )}
              {formData.secondary_diagnosis_code && (
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Secondary:</dt>
                  <dd className={t.text.primary}>{formData.secondary_diagnosis_code}</dd>
                </div>
              )}
              {formData.diagnosis_code && (
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Diagnosis:</dt>
                  <dd className={t.text.primary}>{formData.diagnosis_code}</dd>
                </div>
              )}
            </dl>
          </div>
        </div>
      </div>

      {/* Products Section */}
      <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-3">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiShoppingCart />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Selected Products</h3>
          </div>
          <button
            onClick={() => handleEdit('product-selection')}
            className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
          >
            <FiEdit3 className="w-4 h-4" />
          </button>
        </div>

        <div className="space-y-4">
          {formData.selected_products && formData.selected_products.length > 0 ? (
            formData.selected_products.map((item, index) => {
              const product = getSelectedProductDetails(item.product_id);
              return (
                <div key={index} className={cn("p-4 rounded-lg", t.glass.frost)}>
                  <div className="flex justify-between items-start">
                    <div>
                      <h4 className={cn("font-medium", t.text.primary)}>{product?.name || 'Unknown Product'}</h4>
                      <p className={cn("text-sm", t.text.secondary)}>Manufacturer: {product?.manufacturer}</p>
                      <p className={cn("text-sm", t.text.secondary)}>SKU: {product?.sku}</p>
                    </div>
                    <div className="text-right">
                      <p className={cn("font-medium", t.text.primary)}>Qty: {item.quantity}</p>
                      {item.size && <p className={cn("text-sm", t.text.secondary)}>Size: {item.size}</p>}
                    </div>
                  </div>
                </div>
              );
            })
          ) : (
            <div className={cn("text-center py-8", t.text.secondary)}>
              <FiAlertCircle className="mx-auto h-12 w-12 mb-4 opacity-50" />
              <p>No products selected</p>
            </div>
          )}
        </div>
      </div>

      {/* Episode Information */}
      {formData.episode_id && (
        <div className={cn("rounded-lg border p-6", t.glass.border, t.glass.base)}>
          <div className="flex items-center space-x-3 mb-4">
            <div className={cn("p-2 rounded-lg", t.glass.frost)}>
              <FiCheck />
            </div>
            <h3 className={cn("font-medium", t.text.primary)}>Episode Information</h3>
          </div>

          <dl className="space-y-2 text-sm">
            <div className="flex">
              <dt className={cn("font-medium w-32", t.text.secondary)}>Episode ID:</dt>
              <dd className={t.text.primary}>{formData.episode_id}</dd>
            </div>
            {formData.patient_display_id && (
              <div className="flex">
                <dt className={cn("font-medium w-32", t.text.secondary)}>Patient Display ID:</dt>
                <dd className={t.text.primary}>{formData.patient_display_id}</dd>
              </div>
            )}
            {formData.patient_fhir_id && (
              <div className="flex">
                <dt className={cn("font-medium w-32", t.text.secondary)}>FHIR Patient ID:</dt>
                <dd className={t.text.primary}>{formData.patient_fhir_id}</dd>
              </div>
            )}
          </dl>
        </div>
      )}

      {/* Confirmation Modal */}
      {showConfirmModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className={cn("p-6 rounded-lg max-w-md w-full mx-4", t.glass.card)}>
            <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>Confirm Order Submission</h3>
            <p className={cn("text-sm mb-4", t.text.secondary)}>
              Are you sure you want to submit this order? This action cannot be undone.
            </p>
            <div className="flex items-center mb-4">
              <input
                type="checkbox"
                id="confirm-checkbox"
                checked={confirmChecked}
                onChange={(e) => setConfirmChecked(e.target.checked)}
                className="mr-2"
              />
              <label htmlFor="confirm-checkbox" className={cn("text-sm", t.text.secondary)}>
                I confirm that all information is accurate and complete
              </label>
            </div>
            <div className="flex justify-end space-x-3">
              <button
                onClick={() => setShowConfirmModal(false)}
                className={cn("px-4 py-2 rounded-lg", t.button.secondary.base, t.button.secondary.hover)}
              >
                Cancel
              </button>
              <button
                onClick={handleSubmit}
                disabled={!confirmChecked || isSubmitting}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium",
                  confirmChecked && !isSubmitting
                    ? `${t.button.primary.base} ${t.button.primary.hover}`
                    : "bg-gray-300 text-gray-500 cursor-not-allowed"
                )}
              >
                {isSubmitting ? 'Submitting...' : 'Submit Order'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
