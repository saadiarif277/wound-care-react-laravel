import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  FiUser,
  FiActivity,
  FiShoppingCart,
  FiAlertCircle,
  FiCheck,
  FiHome,
  FiShield,
  FiCreditCard,
  FiMessageSquare
} from 'react-icons/fi';
import OrderReviewSummary from './OrderReviewSummary';
import { AuthButton } from '@/Components/ui/auth-button';

interface Step6Props {
  formData: any;
  products: Array<any>;
  providers: Array<any>;
  facilities: Array<any>;
  errors: Record<string, string>;
  onSubmit: () => void;
  isSubmitting: boolean;
  orderId?: string;
}

// Helper function to safely map form data to order data structure (same as Index.tsx)
const mapFormDataToOrderData = (formData: any, providers: Array<any> = [], facilities: Array<any> = []): any => {
  // Helper function to get provider details
  const getProviderDetails = (providerId: any) => {
    if (!providerId) return null;
    return providers.find(provider => provider.id == providerId) ||
           providers.find(provider => provider.id === providerId);
  };

  // Helper function to get facility details
  const getFacilityDetails = (facilityId: any) => {
    if (!facilityId) return null;
    return facilities.find(facility => facility.id == facilityId) ||
           facilities.find(facility => facility.id === facilityId);
  };

  // Get provider and facility details
  const providerDetails = getProviderDetails(formData?.provider_id);
  const facilityDetails = getFacilityDetails(formData?.facility_id);

  return {
    orderNumber: formData?.episode_id || 'N/A',
    orderStatus: formData?.order_status || 'draft',
    createdDate: formData?.created_at ? new Date(formData.created_at).toLocaleDateString() : new Date().toLocaleDateString(),
    createdBy: providerDetails?.name || formData?.provider_name || 'N/A',
    patient: {
      fullName: `${formData?.patient_first_name || ''} ${formData?.patient_last_name || ''}`.trim() || 'N/A',
      dateOfBirth: formData?.patient_dob || 'N/A',
      phone: formData?.patient_phone || 'N/A',
      email: formData?.patient_email || 'N/A',
      address: formData?.patient_address || 'N/A',
      primaryInsurance: {
        payerName: formData?.primary_insurance_name || 'N/A',
        planName: formData?.primary_plan_type || 'N/A',
        policyNumber: formData?.primary_member_id || 'N/A',
      },
      secondaryInsurance: formData?.has_secondary_insurance ? {
        payerName: formData?.secondary_insurance_name || 'N/A',
        planName: formData?.secondary_plan_type || 'N/A',
        policyNumber: formData?.secondary_member_id || 'N/A',
      } : null,
      insuranceCardUploaded: !!formData?.insurance_card_front,
    },
    provider: {
      name: providerDetails?.name || formData?.provider_name || 'N/A',
      facilityName: facilityDetails?.name || formData?.facility_name || 'N/A',
      facilityAddress: facilityDetails?.address || formData?.facility_address || formData?.service_address || 'N/A',
      organization: providerDetails?.organization?.name || formData?.organization_name || 'N/A',
      npi: providerDetails?.npi || formData?.provider_npi || 'N/A',
    },
    clinical: {
      woundType: formData?.wound_type || 'N/A',
      woundSize: formData?.wound_size_length && formData?.wound_size_width
        ? `${formData.wound_size_length} x ${formData.wound_size_width}cm`
        : 'N/A',
      diagnosisCodes: Array.isArray(formData?.diagnosis_codes)
        ? formData.diagnosis_codes.map((code: any) => ({
            code: typeof code === 'string' ? code : code?.code || 'N/A',
            description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
          }))
        : [],
      icd10Codes: Array.isArray(formData?.icd10_codes)
        ? formData.icd10_codes.map((code: any) => ({
            code: typeof code === 'string' ? code : code?.code || 'N/A',
            description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
          }))
        : [],
      procedureInfo: formData?.procedure_info || 'N/A',
      priorApplications: parseInt(formData?.prior_applications) || 0,
      anticipatedApplications: parseInt(formData?.anticipated_applications) || 0,
      facilityInfo: facilityDetails?.name || formData?.facility_name || 'N/A',
    },
    product: {
      name: formData?.selected_products?.[0]?.product?.name || 'N/A',
      sizes: formData?.selected_products?.map((p: any) => p?.size || 'Standard') || ['N/A'],
      quantity: parseInt(formData?.selected_products?.[0]?.quantity) || 1,
      aspPrice: parseFloat(formData?.selected_products?.[0]?.product?.price) || 0,
      discountedPrice: parseFloat(formData?.selected_products?.[0]?.product?.discounted_price) ||
                      parseFloat(formData?.selected_products?.[0]?.product?.price) || 0,
      coverageWarnings: formData?.coverage_warnings || [],
    },
    ivrForm: {
      status: formData?.docuseal_submission_id ? 'Completed' : 'Not Started',
      submissionDate: formData?.ivr_completed_at || 'N/A',
      documentLink: formData?.ivr_document_link || '',
    },
    orderForm: {
      status: formData?.order_form_status || 'Not Sent',
      submissionDate: formData?.order_form_completed_at || 'N/A',
      documentLink: formData?.order_form_link || '',
    },
  };
};

export default function Step6ReviewSubmit({
  formData,
  products,
  providers = [],
  facilities = [],
  errors,
  onSubmit,
  isSubmitting
}: Step6Props) {
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmChecked, setConfirmChecked] = useState(false);
  const [adminNote, setAdminNote] = useState('');
  const [openSections, setOpenSections] = useState({
    patient: true,
    insurance: true,
    clinical: true,
    product: true,
    provider: true,
    forms: true,
    shipping: false,
    billing: false,
  });

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

  // Create order data with proper mapping
  const orderData = mapFormDataToOrderData(formData, providers, facilities);

  // Toggle section visibility
  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section as keyof typeof prev]
    }));
  };

  // Helper function to check if order is complete
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

  // Helper function to get selected product details from form data
  const getSelectedProductDetails = (item: any) => {
    // First try to get from the product object stored in form data
    if (item.product) {
      return item.product;
    }
    // Fallback to products array if needed
    return products.find(product => product.id === item.product_id);
  };

  // Calculate total bill from form data
  const calculateTotalBill = () => {
    if (!formData.selected_products) return 0;
    return formData.selected_products.reduce((total: number, item: any) => {
      const product = getSelectedProductDetails(item);
      const price = product?.price || product?.discounted_price || 0;
      return total + (price * item.quantity);
    }, 0);
  };

  const handleSubmit = async () => {
    if (!confirmChecked) return;

    setShowConfirmModal(false);
    if (onSubmit) {
      // Pass the admin note to the onSubmit function
      await onSubmit();
    }
  };

  // Early return if formData is not available
  if (!formData) {
    return (
      <div className="max-w-6xl mx-auto space-y-6">
        <div className={cn("p-6 rounded-lg", t.glass.card)}>
          <p className={cn("text-center", t.text.secondary)}>Loading form data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className={cn("p-6 rounded-lg flex justify-between items-start", t.glass.card)}>
        <div>
          <h1 className={cn("text-2xl font-bold", t.text.primary)}>
            Review & Confirm Order
          </h1>
          <p className={cn("text-sm mt-1", t.text.secondary)}>
            Please review all information before submitting your order
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <AuthButton
            onClick={() => setShowConfirmModal(true)}
            disabled={!isOrderComplete()}
            className={cn(
              "px-6 py-2 rounded-lg font-medium transition-all",
              isOrderComplete() && !isSubmitting
                ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
                : "bg-gray-300 text-gray-500 cursor-not-allowed"
            )}
          >
            {isSubmitting ? 'Submitting...' : 'Submit Order'}
          </AuthButton>
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
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('patient')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.patient ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.patient && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Patient Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{orderData.patient.fullName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>DOB:</dt>
                  <dd className={t.text.primary}>{orderData.patient.dateOfBirth}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                  <dd className={t.text.primary}>{orderData.patient.phone}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                  <dd className={t.text.primary}>{orderData.patient.email}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Address:</dt>
                  <dd className={t.text.primary}>{orderData.patient.address}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Insurance Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Primary:</dt>
                  <dd className={t.text.primary}>{orderData.patient.primaryInsurance.payerName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Plan:</dt>
                  <dd className={t.text.primary}>{orderData.patient.primaryInsurance.planName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Policy:</dt>
                  <dd className={t.text.primary}>{orderData.patient.primaryInsurance.policyNumber}</dd>
                </div>
                {orderData.patient.secondaryInsurance && (
                  <>
                    <div className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Secondary:</dt>
                      <dd className={t.text.primary}>{orderData.patient.secondaryInsurance.payerName}</dd>
                    </div>
                    <div className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Plan:</dt>
                      <dd className={t.text.primary}>{orderData.patient.secondaryInsurance.planName}</dd>
                    </div>
                    <div className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Policy:</dt>
                      <dd className={t.text.primary}>{orderData.patient.secondaryInsurance.policyNumber}</dd>
                    </div>
                  </>
                )}
              </dl>
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
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('clinical')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.clinical ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.clinical && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Wound Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Type:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.woundType}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Size:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.woundSize}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Location:</dt>
                  <dd className={t.text.primary}>{formData.wound_location || 'N/A'}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Prior Apps:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.priorApplications}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Anticipated:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.anticipatedApplications}</dd>
                </div>
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Diagnosis Codes</h4>
              <dl className="space-y-2 text-sm">
                {orderData.clinical.diagnosisCodes.length > 0 ? (
                  orderData.clinical.diagnosisCodes.map((code: any, index: number) => (
                    <div key={index} className="flex">
                      <dt className={cn("font-medium w-24", t.text.secondary)}>Code {index + 1}:</dt>
                      <dd className={t.text.primary}>{code.code} - {code.description}</dd>
                    </div>
                  ))
                ) : (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Codes:</dt>
                    <dd className={t.text.primary}>No diagnosis codes</dd>
                  </div>
                )}
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
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('product')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.product ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.product && (
          <div>
            {formData.selected_products && formData.selected_products.length > 0 ? (
              <div className="space-y-4">
                {formData.selected_products.map((item: any, index: number) => {
                  const product = getSelectedProductDetails(item);
                  return (
                    <div key={index} className={cn("p-4 rounded-lg", t.glass.frost)}>
                      <div className="flex justify-between items-start">
                        <div>
                          <h4 className={cn("font-medium", t.text.primary)}>
                            {product?.name || item.product_name || 'Product'}
                          </h4>
                          <p className={cn("text-sm", t.text.secondary)}>
                            Code: {product?.code || product?.q_code || item.product_code || 'N/A'}
                          </p>
                          <p className={cn("text-sm", t.text.secondary)}>
                            Manufacturer: {product?.manufacturer || item.manufacturer || 'N/A'}
                          </p>
                          {product?.description && (
                            <p className={cn("text-sm", t.text.secondary)}>
                              {product.description}
                            </p>
                          )}
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
                          <div className="mt-2">
                            <p className={cn("text-sm", t.text.secondary)}>
                              Price: ${(product?.price || product?.discounted_price || 0).toFixed(2)}
                            </p>
                            {product?.discounted_price && product?.price && product.discounted_price !== product.price && (
                              <p className={cn("text-xs text-green-600", t.text.secondary)}>
                                Original: ${product.price.toFixed(2)}
                              </p>
                            )}
                          </div>
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
          <div className="flex items-center space-x-2">
            <button
              onClick={() => toggleSection('provider')}
              className={cn("p-2 rounded-lg hover:bg-white/10 transition-colors", t.glass.frost)}
            >
              {openSections.provider ? '−' : '+'}
            </button>
          </div>
        </div>

        {openSections.provider && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Provider Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{orderData.provider.name}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>NPI:</dt>
                  <dd className={t.text.primary}>{orderData.provider.npi}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Organization:</dt>
                  <dd className={t.text.primary}>{orderData.provider.organization}</dd>
                </div>
                {formData.provider_credentials && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Credentials:</dt>
                    <dd className={t.text.primary}>{formData.provider_credentials}</dd>
                  </div>
                )}
                {formData.provider_email && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                    <dd className={t.text.primary}>{formData.provider_email}</dd>
                  </div>
                )}
              </dl>
            </div>

            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Facility Information</h4>
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                  <dd className={t.text.primary}>{orderData.provider.facilityName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Address:</dt>
                  <dd className={t.text.primary}>{orderData.provider.facilityAddress}</dd>
                </div>
                {formData.facility_phone && (
                  <div className="flex">
                    <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                    <dd className={t.text.primary}>{formData.facility_phone}</dd>
                  </div>
                )}
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
            {openSections.forms ? '−' : '+'}
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
                  {orderData.ivrForm.status}
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
                  {orderData.orderForm.status}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Submit Button at Bottom */}
      <div className="flex justify-center">
        <AuthButton
          onClick={() => setShowConfirmModal(true)}
          disabled={!isOrderComplete()}
          className={cn(
            "px-8 py-3 rounded-lg font-medium transition-all",
            isOrderComplete() && !isSubmitting
              ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
              : "bg-gray-300 text-gray-500 cursor-not-allowed"
          )}
        >
          {isSubmitting ? 'Submitting...' : 'Submit Order'}
        </AuthButton>
      </div>

      {/* Confirmation Modal */}
      {showConfirmModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className={cn("p-6 rounded-lg max-w-md w-full mx-4", t.glass.card)}>
            <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>Confirm Order Submission</h3>
            <p className={cn("text-sm mb-4", t.text.secondary)}>
              Are you sure you want to submit this order? This action cannot be undone.
            </p>

            {/* Admin Notes Section */}
            <div className="mb-4">
              <label htmlFor="admin-note" className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                Admin Notes (Optional)
              </label>
              <textarea
                id="admin-note"
                value={adminNote}
                onChange={(e) => setAdminNote(e.target.value)}
                placeholder="Add any admin notes or comments about this order..."
                className={cn(
                  "w-full h-24 p-3 rounded-lg resize-none",
                  "border border-gray-300 dark:border-gray-600",
                  "bg-white dark:bg-gray-800",
                  "text-gray-900 dark:text-gray-100",
                  "focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                )}
              />
            </div>

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
              <AuthButton
                onClick={() => setShowConfirmModal(false)}
                variant="secondary"
                className={cn("px-4 py-2 rounded-lg")}
              >
                Cancel
              </AuthButton>
              <AuthButton
                onClick={handleSubmit}
                disabled={!confirmChecked}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium",
                  confirmChecked && !isSubmitting
                    ? "bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg"
                    : "bg-gray-300 text-gray-500 cursor-not-allowed"
                )}
              >
                {isSubmitting ? 'Submitting...' : 'Submit Order'}
              </AuthButton>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

