import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import PatientInsuranceSection from '@/Pages/Admin/OrderCenter/PatientInsuranceSection';
import ProductSection from '@/Pages/Admin/OrderCenter/ProductSection';
import IVRDocumentSection from '@/Pages/Admin/OrderCenter/IVRDocumentSection';
import ClinicalSection from '@/Pages/Admin/OrderCenter/ClinicalSection';
import ProviderSection from '@/Pages/Admin/OrderCenter/ProviderSection';
import AdditionalDocumentsSection from '@/Pages/Admin/OrderCenter/AdditionalDocumentsSection';
import { OrderFormModal } from '@/Components/OrderForm/OrderFormModal';
import { ArrowLeft, FileText } from 'lucide-react';

import { formatHumanReadableDate } from '@/utils/dateUtils';
import axios from 'axios';

interface ProductRequest {
  id: number;
  request_number: string;
  order_status: string;
  step: number;
  step_description: string;
  wound_type: string;
  expected_service_date: string;
  patient_display: string;
  patient_fhir_id: string;
  payer_name: string;
  clinical_summary?: any;
  mac_validation_results?: any;
  mac_validation_status?: string;
  eligibility_results?: any;
  eligibility_status?: string;
  pre_auth_required?: boolean;
  clinical_opportunities?: any;
  total_amount?: number;
  created_at: string;
  products?: Array<{
    id: number;
    name: string;
    q_code: string;
    quantity: number;
    size?: string;
    unit_price: number;
    total_price: number;
  }>;
  // Add fields that match the Order interface
  order_number?: string;
  patient_name?: string;
  patient_display_id?: string;
  provider_name?: string;
  facility_name?: string;
  manufacturer_name?: string;
  product_name?: string;
  ivr_status?: string;
  order_form_status?: string;
  total_order_value?: number;
  action_required?: boolean;
  episode_id?: string;
  docuseal_submission_id?: string;
  ivr_document_url?: string;
  place_of_service?: string;
  place_of_service_display?: string;
  wound_type_display?: string;
  documents?: any[];
  // Add patient and insurance data structure
  patient?: {
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
  insurance?: {
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
  product?: {
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
  clinical?: {
    woundType: string;
    woundLocation?: string;
    size: string;
    depth?: string;
    diagnosisCodes?: string[];
    primaryDiagnosis?: string;
    clinicalNotes?: string;
    failedConservativeTreatment?: boolean;
  };
  provider?: {
    id?: number;
    name: string;
    npi?: string;
    email?: string;
  };
  facility?: {
    id?: number;
    name?: string;
    address?: string;
    phone?: string;
  };
}

interface ProductRequestShowProps {
  request: ProductRequest;
  roleRestrictions?: {
    can_view_financials: boolean;
    can_see_discounts: boolean;
    can_see_msc_pricing: boolean;
    can_see_order_totals: boolean;
    can_see_commission: boolean;
    pricing_access_level: string;
    commission_access_level: string;
  };
}

const ProductRequestShow: React.FC<ProductRequestShowProps> = ({
  request,
  roleRestrictions = {
    can_view_financials: true,
    can_see_discounts: true,
    can_see_msc_pricing: true,
    can_see_order_totals: true,
    can_see_commission: false,
    pricing_access_level: 'full',
    commission_access_level: 'none'
  }
}) => {
  const [openSections, setOpenSections] = useState({
    patient: true,
    product: true,
    ivrDocument: true,
    clinical: true,
    provider: true,
    documents: true,
  });
  const [notificationMessage, setNotificationMessage] = useState<{
    type: 'success' | 'error',
    message: string
  } | null>(null);
  const [showOrderFormModal, setShowOrderFormModal] = useState(false);

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

  // Transform ProductRequest to match Order structure
  const orderData = {
    id: request.id,
    order_number: request.request_number,
    patient_name: request.patient_display,
    patient_display_id: request.patient_fhir_id,
    provider_name: request.provider?.name || 'Unknown Provider',
    facility_name: request.facility?.name || 'Unknown Facility',
    manufacturer_name: request.product?.manufacturer || 'Unknown Manufacturer',
    product_name: request.product?.name || 'Unknown Product',
    order_status: request.order_status,
    ivr_status: request.ivr_status || 'pending',
    order_form_status: request.order_form_status || 'pending',
    total_order_value: request.total_amount || 0,
    created_at: request.created_at,
    action_required: false,
    episode_id: request.episode_id,
    docuseal_submission_id: request.docuseal_submission_id,
    ivr_document_url: request.ivr_document_url,
    place_of_service: request.place_of_service,
    place_of_service_display: request.place_of_service_display,
    wound_type: request.wound_type,
    wound_type_display: request.wound_type_display,
    expected_service_date: request.expected_service_date,
    documents: request.documents || [],
    patient: request.patient || {
      name: request.patient_display,
      firstName: request.patient_display?.split(' ')[0] || '',
      lastName: request.patient_display?.split(' ').slice(1).join(' ') || '',
      dob: '',
      gender: '',
      phone: '',
      email: '',
      address: '',
      memberId: '',
      displayId: request.patient_fhir_id,
      isSubscriber: true
    },
    insurance: request.insurance || {
      primary: {
        name: request.payer_name,
        memberId: '',
        planType: ''
      },
      secondary: {
        name: '',
        memberId: '',
        planType: ''
      },
      hasSecondary: false
    },
    product: request.product || {
      name: request.product_name || 'Unknown Product',
      code: request.products?.[0]?.q_code || '',
      quantity: request.products?.[0]?.quantity || 1,
      size: request.products?.[0]?.size || '',
      category: '',
      manufacturer: request.manufacturer_name || '',
      manufacturerId: undefined,
      selectedProducts: request.products?.map(p => ({
        product_id: p.id,
        quantity: p.quantity
      })) || [],
      shippingInfo: {
        speed: 'standard',
        instructions: ''
      }
    },
    clinical: request.clinical || {
      woundType: request.wound_type,
      woundLocation: '',
      size: '',
      depth: '',
      diagnosisCodes: [],
      primaryDiagnosis: '',
      clinicalNotes: '',
      failedConservativeTreatment: false
    },
    provider: request.provider || {
      id: undefined,
      name: 'Unknown Provider',
      npi: '',
      email: ''
    },
    facility: request.facility || {
      id: undefined,
      name: 'Unknown Facility',
      address: '',
      phone: ''
    },
    clinical_summary: request.clinical_summary
  };

  // Debug: Check if request has the required data
  console.log('ProductRequestShow Debug:', {
    request,
    hasPatient: !!orderData.patient,
    patientName: orderData.patient?.name,
    hasInsurance: !!orderData.insurance,
    hasClinical: !!orderData.clinical,
    hasProduct: !!orderData.product,
    manufacturerInfo: {
      manufacturer_name: request.manufacturer_name,
      product_manufacturer: request.product?.manufacturer,
      product_manufacturerId: request.product?.manufacturerId,
      orderData_manufacturer_name: orderData.manufacturer_name,
      orderData_product_manufacturerId: orderData.product?.manufacturerId
    }
  });

  // Add error handling for missing request data
  if (!orderData.patient || !orderData.insurance || !orderData.clinical || !orderData.product) {
    return (
      <MainLayout>
        <Head title="Product Request Details - Error" />
        <div className="container mx-auto px-4 py-8 max-w-6xl">
          <div className="bg-red-50 border border-red-200 rounded-lg p-6">
            <h2 className="text-xl font-semibold text-red-800 mb-2">Error Loading Product Request Details</h2>
            <p className="text-red-700">The product request data could not be loaded. Please try refreshing the page or contact support.</p>
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

  return (
    <MainLayout>
      <Head title={`Product Request Details - ${request.request_number}`} />
      <div className="container mx-auto px-4 py-8 max-w-6xl">
        {/* Header */}
        <div className="flex items-center gap-4 mb-8">
          <button
            onClick={() => router.get('/product-requests')}
            className="p-2 rounded-lg bg-gray-200 hover:bg-gray-300"
          >
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div className="flex-1">
            <div className="flex items-center gap-4 mb-2">
              <h1 className="text-3xl font-bold text-slate-900">
                Product Request Details
              </h1>
              <div className="flex items-center gap-2">
                <span className={`px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1 ${
                  orderData.ivr_status === 'verified' ? 'text-green-600 bg-green-100' :
                  orderData.ivr_status === 'sent' ? 'text-blue-600 bg-blue-100' :
                  orderData.ivr_status === 'rejected' ? 'text-red-600 bg-red-100' :
                  'text-yellow-600 bg-yellow-100'
                }`}>
                  IVR: {orderData.ivr_status || 'Pending'}
                </span>
                <span className={`px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1 ${
                  orderData.order_form_status === 'confirmed_by_manufacturer' ? 'text-green-600 bg-green-100' :
                  orderData.order_form_status === 'submitted_to_manufacturer' ? 'text-blue-600 bg-green-100' :
                  orderData.order_form_status === 'rejected' ? 'text-red-600 bg-red-100' :
                  'text-yellow-600 bg-yellow-100'
                }`}>
                  Order: {orderData.order_form_status || 'Pending'}
                </span>
              </div>

              {/* View Order Form Button */}
              <div className="flex items-center gap-2">
                <Button
                  onClick={() => setShowOrderFormModal(true)}
                  className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
                >
                  <FileText className="h-4 w-4" />
                  View Order Form
                </Button>
              </div>
            </div>
            <div className="flex items-center gap-4 text-sm text-muted-foreground">
              <span>Request #{request.request_number}</span>
              <span>•</span>
              <span>Created {formatDate(request.created_at)}</span>
              <span>•</span>
              <span>By {orderData.provider_name}</span>
            </div>
          </div>
        </div>

        {/* Request Sections */}
        <PatientInsuranceSection
          orderData={{
            orderNumber: orderData.order_number,
            createdDate: orderData.created_at,
            createdBy: orderData.provider_name,
            patient: {
              ...orderData.patient,
              dob: orderData.patient?.dob || 'N/A',
              gender: orderData.patient?.gender || 'N/A',
              phone: orderData.patient?.phone || 'N/A',
              address: orderData.patient?.address || 'N/A',
              insurance: {
                primary: orderData.insurance?.primary?.name || 'N/A',
                secondary: orderData.insurance?.secondary?.name || 'N/A'
              }
            },
            insurance: {
              primary: orderData.insurance?.primary?.name || 'N/A',
              secondary: orderData.insurance?.secondary?.name || 'N/A',
              primaryPlanType: orderData.insurance?.primary?.planType,
              secondaryPlanType: orderData.insurance?.secondary?.planType
            },
            product: orderData.product,
            forms: { ivrStatus: orderData.ivr_status, orderFormStatus: orderData.order_form_status },
            clinical: orderData.clinical,
            provider: orderData.provider,
            submission: { documents: orderData.documents }
          }}
          isOpen={openSections.patient}
          onToggle={() => toggleSection('patient')}
        />

        <ProductSection
          orderData={{
            orderNumber: orderData.order_number,
            createdDate: orderData.created_at,
            createdBy: orderData.provider_name,
            patient: orderData.patient,
            product: {
              ...orderData.product,
              shippingInfo: {
                speed: orderData.product?.shippingInfo?.speed || 'standard',
                address: orderData.facility?.address || 'N/A'
              }
            },
            orderPreferences: {
              expectedServiceDate: orderData.expected_service_date,
              shippingSpeed: orderData.product?.shippingInfo?.speed,
              placeOfService: orderData.place_of_service_display,
              deliveryInstructions: orderData.product?.shippingInfo?.instructions
            },
            forms: { ivrStatus: orderData.ivr_status, orderFormStatus: orderData.order_form_status },
            clinical: orderData.clinical,
            provider: orderData.provider,
            submission: { documents: orderData.documents },
            total_amount: orderData.total_order_value
          }}
          userRole="Provider"
          isOpen={openSections.product}
          onToggle={() => toggleSection('product')}
        />

        <IVRDocumentSection
          ivrData={{
            status: orderData.ivr_status || 'N/A',
            sentDate: orderData.created_at,
            resultsReceivedDate: '',
            verifiedDate: '',
            notes: '',
            resultsFileUrl: '',
            files: orderData.documents || [],
            ivrDocumentUrl: orderData.ivr_document_url,
            docusealSubmissionId: orderData.docuseal_submission_id
          }}
          orderFormData={{
            status: orderData.order_form_status || 'N/A',
            submissionDate: orderData.created_at,
            reviewDate: '',
            approvalDate: '',
            notes: '',
            fileUrl: '',
            files: orderData.documents || []
          }}
          orderId={orderData.id}
          onUpdateIVRStatus={async () => {}}
          onUploadIVRResults={() => {}}
          onUpdateOrderFormStatus={async () => {}}
          isOpen={openSections.ivrDocument}
          onToggle={() => toggleSection('ivrDocument')}
          userRole="Provider"
          orderData={{
            order_number: orderData.order_number,
            manufacturer_name: orderData.manufacturer_name,
            manufacturer_id: orderData.product?.manufacturerId,
            patient_name: orderData.patient_name,
            patient_email: orderData.patient?.email,
            integration_email: 'integration@mscwoundcare.com',
            episode_id: orderData.episode_id ? parseInt(orderData.episode_id) : undefined,
            product_id: orderData.product?.selectedProducts?.[0]?.product_id,
            clinical_summary: orderData.clinical_summary
          }}
        />

        <ClinicalSection
          orderData={{
            orderNumber: orderData.order_number,
            createdDate: orderData.created_at,
            createdBy: orderData.provider_name,
            patient: orderData.patient,
            product: orderData.product,
            forms: { ivrStatus: orderData.ivr_status, orderFormStatus: orderData.order_form_status },
            clinical: {
              ...orderData.clinical,
              location: orderData.clinical?.woundLocation || 'N/A',
              cptCodes: 'N/A',
              placeOfService: orderData.place_of_service_display || 'N/A',
              serviceDate: orderData.expected_service_date,
              failedConservativeTreatment: orderData.clinical?.failedConservativeTreatment || false
            },
            provider: orderData.provider,
            submission: { documents: orderData.documents }
          }}
          isOpen={openSections.clinical}
          onToggle={() => toggleSection('clinical')}
        />

        <ProviderSection
          orderData={{
            orderNumber: orderData.order_number,
            createdDate: orderData.created_at,
            createdBy: orderData.provider_name,
            patient: orderData.patient,
            product: orderData.product,
            forms: { ivrStatus: orderData.ivr_status, orderFormStatus: orderData.order_form_status },
            clinical: orderData.clinical,
            provider: orderData.provider,
            facility: orderData.facility,
            submission: { documents: orderData.documents }
          }}
          isOpen={openSections.provider}
          onToggle={() => toggleSection('provider')}
        />

        <AdditionalDocumentsSection
          documents={orderData.documents || []}
          isOpen={openSections.documents}
          onToggle={() => toggleSection('documents')}
        />

        {/* Order Form Modal */}
        <OrderFormModal
          isOpen={showOrderFormModal}
          onClose={() => setShowOrderFormModal(false)}
          orderId={orderData.id.toString()}
          orderData={{
            id: orderData.id.toString(),
            order_number: orderData.order_number || `Request #${request.request_number}`,
            manufacturer_name: orderData.manufacturer_name || 'Manufacturer',
            manufacturer_id: orderData.product?.manufacturerId || 1,
            patient_name: orderData.patient_name || orderData.patient?.name,
            patient_email: orderData.patient?.email || 'patient@example.com',
            integration_email: 'integration@mscwoundcare.com',
            episode_id: orderData.episode_id ? parseInt(orderData.episode_id) : undefined,
            ivr_status: orderData.ivr_status,
            order_form_status: orderData.order_form_status,
            docuseal_submission_id: orderData.docuseal_submission_id,
            order_form_submission_id: '',
            product_request_id: request.id,
            // Add comprehensive data for auto-fill
            facility: {
              name: orderData.facility_name,
              address: orderData.facility?.address,
              city: orderData.facility?.city,
              state: orderData.facility?.state,
              zip: orderData.facility?.zip,
              phone: orderData.facility?.phone
            },
            provider: {
              name: orderData.provider_name,
              email: orderData.provider?.email,
              phone: orderData.provider?.phone,
              npi: orderData.provider?.npi
            },
            product: {
              name: orderData.product_name,
              quantity: orderData.product?.quantity,
              size: orderData.product?.size,
              price: orderData.total_order_value
            },
            clinical: {
              wound_type: orderData.wound_type,
              expected_service_date: orderData.expected_service_date
            }
          }}
          onOrderFormComplete={(data) => {
            console.log('Order form completed:', data);
            // Don't close the modal automatically - let user close it manually
            // setShowOrderFormModal(false);
            // You can add additional logic here to update the order form status
          }}
        />
      </div>
    </MainLayout>
  );
};

export default ProductRequestShow;
