import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import PatientInsuranceSection from './PatientInsuranceSection';
import ProductSection from './ProductSection';
import IVRDocumentSection from './IVRDocumentSection';
import ClinicalSection from './ClinicalSection';
import ProviderSection from './ProviderSection';
import AdditionalDocumentsSection from './AdditionalDocumentsSection';
import ManufacturerSubmissionModal from './ManufacturerSubmissionModal';
import { ArrowLeft } from 'lucide-react';
import axios from 'axios';

interface Order {
  id: number;
  order_number: string;
  patient_name: string;
  patient_display_id: string;
  provider_name: string;
  facility_name: string;
  manufacturer_name: string;
  product_name: string;
  order_status: string;
  ivr_status: string;
  order_form_status: string;
  total_order_value: number;
  created_at: string;
  action_required: boolean;
  episode_id?: string;
  docuseal_submission_id?: string;
  ivr_document_url?: string;
  place_of_service?: string;
  place_of_service_display?: string;
  wound_type?: string;
  wound_type_display?: string;
  expected_service_date?: string;
}

interface OrderData {
  patient: {
    name: string;
    firstName?: string;
    lastName?: string;
    dob?: string;
    gender?: string;
    phone?: string;
    email?: string;
    address?: string;
    memberId?: string;
    displayId?: string;
    isSubscriber?: boolean;
  };
  insurance: {
    primary: {
      name: string;
      memberId?: string;
      planType?: string;
    };
    secondary: {
      name?: string;
      memberId?: string;
      planType?: string;
    };
    hasSecondary?: boolean;
  };
  product: {
    name: string;
    code: string;
    quantity: number;
    size: string;
    category: string;
    manufacturer: string;
    manufacturerId?: number;
    selectedProducts?: Array<{
      product_id: number;
      quantity: number;
    }>;
    shippingInfo: {
      speed: string;
      instructions?: string;
    };
  };
  clinical: {
    woundType: string;
    woundLocation?: string;
    size: string;
    depth?: string;
    diagnosisCodes?: string[];
    primaryDiagnosis?: string;
    clinicalNotes?: string;
    failedConservativeTreatment?: boolean;
  };
  provider: {
    id?: number;
    name: string;
    npi?: string;
    email?: string;
  };
  facility: {
    id?: number;
    name?: string;
    address?: string;
    phone?: string;
  };
  forms: {
    ivrStatus: string;
    docusealStatus: string;
    consent: boolean;
    assignmentOfBenefits: boolean;
    medicalNecessity: boolean;
    authorizePriorAuth?: boolean;
  };
  orderPreferences?: {
    expectedServiceDate?: string;
    shippingSpeed?: string;
    placeOfService?: string;
    deliveryInstructions?: string;
  };
  attestations?: {
    failedConservativeTreatment?: boolean;
    informationAccurate?: boolean;
    medicalNecessityEstablished?: boolean;
    maintainDocumentation?: boolean;
    authorizePriorAuth?: boolean;
  };
  adminNotes?: {
    note?: string;
    addedAt?: string;
  };
  manufacturerFields?: any[];
  documents?: any[];
  preAuth?: {
    required?: boolean;
    status?: string;
    submittedAt?: string;
    approvedAt?: string;
    deniedAt?: string;
  };
}

interface OrderDetailsProps {
  order: Order & OrderData;
  can_update_status: boolean;
  can_view_ivr: boolean;
  userRole?: 'Provider' | 'OM' | 'Admin';
  roleRestrictions?: {
    can_view_financials: boolean;
    can_see_discounts: boolean;
    can_see_msc_pricing: boolean;
    can_see_order_totals: boolean;
    can_see_commission: boolean;
    pricing_access_level: string;
    commission_access_level: string;
  };
  navigationRoute?: string;
}

const OrderDetails: React.FC<OrderDetailsProps> = ({
  order,
  can_update_status,
  can_view_ivr,
  userRole = 'Admin',
  roleRestrictions,
  navigationRoute
}) => {
  // Debug: Check if order has the required data
  console.log('OrderDetails Debug:', {
    order,
    hasPatient: !!order.patient,
    patientName: order.patient?.name,
    hasInsurance: !!order.insurance,
    hasClinical: !!order.clinical,
    hasProduct: !!order.product,
  });

  // Add error handling for missing order data
  if (!order.patient || !order.insurance || !order.clinical || !order.product) {
    return (
      <MainLayout>
        <Head title="Order Details - Error" />
        <div className="container mx-auto px-4 py-8 max-w-6xl">
          <div className="bg-red-50 border border-red-200 rounded-lg p-6">
            <h2 className="text-xl font-semibold text-red-800 mb-2">Error Loading Order Details</h2>
            <p className="text-red-700">The order data could not be loaded. Please try refreshing the page or contact support.</p>
            <button
              onClick={() => window.location.reload()}
              className="mt-4 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            >
              Refresh Page
            </button>
          </div>
        </div>
      </MainLayout>
    );
  }
  const [openSections, setOpenSections] = useState({
    patient: true,
    product: true,
    ivrDocument: true,
    clinical: true,
    provider: true,
    documents: true,
  });
  const [showManufacturerSubmissionModal, setShowManufacturerSubmissionModal] = useState(false);
  const [notificationMessage, setNotificationMessage] = useState<{
    type: 'success' | 'error',
    message: string
  } | null>(null);

  const formatDate = (dateString?: string): string => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const toggleSection = (section: keyof typeof openSections) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const handleUpdateIVRStatus = async (data: any) => {
    try {
      const response = await axios.post(`/admin/orders/${order.id}/change-status`, {
        status: data.status,
        status_type: 'ivr',
        notes: data.comments,
        rejection_reason: data.rejectionReason,
        send_notification: data.sendNotification,
      });

      if (response.status === 200) {
        setNotificationMessage({
          type: 'success',
          message: 'IVR status updated successfully!'
        });
        // Refresh the page after successful update
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        const error = response.data;
        setNotificationMessage({
          type: 'error',
          message: error.error || 'Failed to update IVR status'
        });
      }
    } catch (err) {
      setNotificationMessage({
        type: 'error',
        message: 'Failed to update IVR status. Please try again.'
      });
    }
    setTimeout(() => setNotificationMessage(null), 5000);
  };

  const handleUpdateOrderFormStatus = async (data: any) => {
    try {
      const response = await axios.post(`/admin/orders/${order.id}/change-status`, {
        status: data.status,
        status_type: 'order',
        notes: data.comments,
        rejection_reason: data.rejectionReason,
        cancellation_reason: data.cancellationReason,
        send_notification: data.sendNotification,
        carrier: data.carrier,
        tracking_number: data.trackingNumber,
      });

      if (response.status === 200) {
        setNotificationMessage({
          type: 'success',
          message: 'Order form status updated successfully!'
        });
        // Refresh the page after successful update
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        const error = response.data;
        setNotificationMessage({
          type: 'error',
          message: error.error || 'Failed to update order form status'
        });
      }
    } catch (err) {
      setNotificationMessage({
        type: 'error',
        message: 'Failed to update order form status. Please try again.'
      });
    }
    setTimeout(() => setNotificationMessage(null), 5000);
  };

  const handleManufacturerSubmission = async (data: any) => {
    try {
      const response = await axios.post(`/admin/orders/${order.id}/change-status`, {
        status: 'submitted_to_manufacturer',
        status_type: 'order',
        notes: data.specialInstructions,
        send_notification: data.sendNotification,
        carrier: data.carrier,
        tracking_number: data.trackingNumber,
        shipping_info: {
          carrier: data.carrier,
          tracking_number: data.trackingNumber,
          shipping_address: data.shippingAddress,
          shipping_contact: data.shippingContact,
          shipping_phone: data.shippingPhone,
          shipping_email: data.shippingEmail,
          special_instructions: data.specialInstructions,
        },
      });

      if (response.status === 200) {
        setNotificationMessage({
          type: 'success',
          message: 'Order submitted to manufacturer successfully!'
        });
        // Refresh the page after successful update
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        const error = response.data;
        setNotificationMessage({
          type: 'error',
          message: error.error || 'Failed to submit to manufacturer'
        });
      }
    } catch (err) {
      setNotificationMessage({
        type: 'error',
        message: 'Failed to submit to manufacturer. Please try again.'
      });
    }
    setTimeout(() => setNotificationMessage(null), 5000);
  };

  return (
    <MainLayout>
      <Head title={`Order Details - ${order.order_number}`} />
      <div className="container mx-auto px-4 py-8 max-w-6xl">
        {/* Header */}
        <div className="flex items-center gap-4 mb-8">
          <button
            onClick={() => {
              if (navigationRoute) {
                router.get(route(navigationRoute));
              } else if (userRole === 'Admin') {
                router.get(route('admin.orders.index'));
              } else {
                router.get(route('dashboard'));
              }
            }}
            className="p-2 rounded-lg bg-gray-200 hover:bg-gray-300"
          >
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div className="flex-1">
            <div className="flex items-center gap-4 mb-2">
              <h1 className="text-3xl font-bold text-slate-900">
                Order Details
              </h1>
              <div className="flex items-center gap-2">
                <span className={`px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1 ${
                  order.ivr_status === 'verified' ? 'text-green-600 bg-green-100' :
                  order.ivr_status === 'sent' ? 'text-blue-600 bg-blue-100' :
                  order.ivr_status === 'rejected' ? 'text-red-600 bg-red-100' :
                  'text-yellow-600 bg-yellow-100'
                }`}>
                  IVR: {order.ivr_status || 'Pending'}
                </span>
                <span className={`px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1 ${
                  order.order_form_status === 'confirmed_by_manufacturer' ? 'text-green-600 bg-green-100' :
                  order.order_form_status === 'submitted_to_manufacturer' ? 'text-blue-600 bg-blue-100' :
                  order.order_form_status === 'rejected' ? 'text-red-600 bg-red-100' :
                  'text-yellow-600 bg-yellow-100'
                }`}>
                  Order: {order.order_form_status || 'Pending'}
                </span>
              </div>
            </div>
            <div className="flex items-center gap-4 text-sm text-muted-foreground">
              <span>Order #{order.order_number}</span>
              <span>•</span>
              <span>Created {formatDate(order.created_at)}</span>
              <span>•</span>
              <span>By {order.provider_name}</span>
            </div>
          </div>
        </div>

        {/* Order Sections */}
        <PatientInsuranceSection
          orderData={{
            orderNumber: order.order_number,
            createdDate: order.created_at,
            createdBy: order.provider_name,
            patient: {
              ...order.patient,
              dob: order.patient?.dob || 'N/A',
              gender: order.patient?.gender || 'N/A',
              phone: order.patient?.phone || 'N/A',
              address: order.patient?.address || 'N/A',
              insurance: {
                primary: order.insurance?.primary?.name || 'N/A',
                secondary: order.insurance?.secondary?.name || 'N/A'
              }
            },
            insurance: {
              primary: order.insurance?.primary?.name || 'N/A',
              secondary: order.insurance?.secondary?.name || 'N/A',
              primaryPlanType: order.insurance?.primary?.planType,
              secondaryPlanType: order.insurance?.secondary?.planType
            },
            product: order.product,
            forms: { ivrStatus: order.ivr_status, orderFormStatus: order.order_form_status },
            clinical: order.clinical,
            provider: order.provider,
            submission: { documents: order.documents }
          }}
          isOpen={openSections.patient}
          onToggle={() => toggleSection('patient')}
        />

        <ProductSection
          orderData={{
            orderNumber: order.order_number,
            createdDate: order.created_at,
            createdBy: order.provider_name,
            patient: order.patient,
            product: {
              ...order.product,
              shippingInfo: {
                speed: order.product?.shippingInfo?.speed || 'standard',
                address: order.facility?.address || 'N/A'
              }
            },
            orderPreferences: {
              expectedServiceDate: order.expected_service_date,
              shippingSpeed: order.product?.shippingInfo?.speed,
              placeOfService: order.place_of_service_display,
              deliveryInstructions: order.product?.shippingInfo?.instructions
            },
            forms: { ivrStatus: order.ivr_status, orderFormStatus: order.order_form_status },
            clinical: order.clinical,
            provider: order.provider,
            submission: { documents: order.documents },
            total_amount: order.total_order_value
          }}
          userRole={userRole}
          isOpen={openSections.product}
          onToggle={() => toggleSection('product')}
        />

        <IVRDocumentSection
          ivrData={{
            status: order.ivr_status || 'N/A',
            sentDate: order.created_at,
            resultsReceivedDate: '',
            verifiedDate: '',
            notes: '',
            resultsFileUrl: '',
            files: order.documents || [],
            ivrDocumentUrl: order.ivr_document_url,
            docusealSubmissionId: order.docuseal_submission_id
          }}
          orderFormData={{
            status: order.order_form_status || 'N/A',
            submissionDate: order.created_at,
            reviewDate: '',
            approvalDate: '',
            notes: '',
            fileUrl: '',
            files: order.documents || []
          }}
          orderId={order.id}
          onUpdateIVRStatus={handleUpdateIVRStatus}
          onUploadIVRResults={() => {}}
          onUpdateOrderFormStatus={handleUpdateOrderFormStatus}
          onManufacturerSubmission={() => setShowManufacturerSubmissionModal(true)}
          isOpen={openSections.ivrDocument}
          onToggle={() => toggleSection('ivrDocument')}
          userRole={userRole}
        />

        <ClinicalSection
          orderData={{
            orderNumber: order.order_number,
            createdDate: order.created_at,
            createdBy: order.provider_name,
            patient: order.patient,
            product: order.product,
            forms: { ivrStatus: order.ivr_status, orderFormStatus: order.order_form_status },
            clinical: {
              ...order.clinical,
              location: order.clinical?.woundLocation || 'N/A',
              cptCodes: 'N/A',
              placeOfService: order.place_of_service_display || 'N/A',
              serviceDate: order.expected_service_date,
              failedConservativeTreatment: order.clinical?.failedConservativeTreatment || false
            },
            provider: order.provider,
            submission: { documents: order.documents }
          }}
          isOpen={openSections.clinical}
          onToggle={() => toggleSection('clinical')}
        />

        <ProviderSection
          orderData={{
            orderNumber: order.order_number,
            createdDate: order.created_at,
            createdBy: order.provider_name,
            patient: order.patient,
            product: order.product,
            forms: { ivrStatus: order.ivr_status, orderFormStatus: order.order_form_status },
            clinical: order.clinical,
            provider: order.provider,
            facility: order.facility,
            submission: { documents: order.documents }
          }}
          isOpen={openSections.provider}
          onToggle={() => toggleSection('provider')}
        />

        <AdditionalDocumentsSection
          documents={order.documents || []}
          isOpen={openSections.documents}
          onToggle={() => toggleSection('documents')}
        />

        {/* Modals */}
        <ManufacturerSubmissionModal
          isOpen={showManufacturerSubmissionModal}
          onClose={() => setShowManufacturerSubmissionModal(false)}
          onConfirm={handleManufacturerSubmission}
          orderId={order.id.toString()}
          orderNumber={order.order_number}
          manufacturerName={order.manufacturer_name}
        />

        {/* Notification Toast */}
        {notificationMessage && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div className={`max-w-md w-full mx-4 p-6 rounded-lg shadow-xl ${
              notificationMessage.type === 'success'
                ? 'bg-green-50 border border-green-200'
                : 'bg-red-50 border border-red-200'
            }`}>
              <div className="flex items-center gap-3 mb-4">
                <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
                  notificationMessage.type === 'success'
                    ? 'bg-green-100 text-green-600'
                    : 'bg-red-100 text-red-600'
                }`}>
                  {notificationMessage.type === 'success' ? (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                  ) : (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                    </svg>
                  )}
                </div>
                <div className="flex-1">
                  <h3 className={`text-lg font-semibold ${
                    notificationMessage.type === 'success'
                      ? 'text-green-800'
                      : 'text-red-800'
                  }`}>
                    {notificationMessage.type === 'success' ? 'Success!' : 'Error!'}
                  </h3>
                  <p className={`text-sm ${
                    notificationMessage.type === 'success'
                      ? 'text-green-700'
                      : 'text-red-700'
                  }`}>
                    {notificationMessage.message}
                  </p>
                </div>
              </div>
              <div className="flex justify-end">
                <button
                  onClick={() => setNotificationMessage(null)}
                  className={`px-4 py-2 rounded-md text-sm font-medium ${
                    notificationMessage.type === 'success'
                      ? 'bg-green-600 text-white hover:bg-green-700'
                      : 'bg-red-600 text-white hover:bg-red-700'
                  }`}
                >
                  OK
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
};

export default OrderDetails;
