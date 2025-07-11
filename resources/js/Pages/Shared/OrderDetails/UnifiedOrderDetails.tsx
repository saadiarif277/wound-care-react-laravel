import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import PatientInsuranceSection from './PatientInsuranceSection';
import ProductSection from './ProductSection';
import IVRDocumentSection from './IVRDocumentSection';
import ClinicalSection from './ClinicalSection';
import ProviderSection from './ProviderSection';
import AdditionalDocumentsSection from './AdditionalDocumentsSection';
import { ArrowLeft } from 'lucide-react';
import { useFinancialPermissions } from '@/hooks/useFinancialPermissions';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

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
  total_order_value?: number;
  created_at: string;
  action_required: boolean;
  episode_id?: string;
  docuseal_submission_id?: string;
  place_of_service?: string;
  place_of_service_display?: string;
  wound_type?: string;
  wound_type_display?: string;
  expected_service_date?: string;
  patient: any;
  insurance: any;
  product: any;
  clinical: any;
  provider: any;
  facility: any;
  attestations: any;
  documents: any[];
  fhir: any;
}

interface UnifiedOrderDetailsProps {
  order: Order;
  can_update_status: boolean;
  can_view_ivr: boolean;
  userRole: string;
  roleRestrictions: any;
  routeContext: string;
  permissions: string[];
}

const UnifiedOrderDetails: React.FC<UnifiedOrderDetailsProps> = ({
  order,
  can_update_status,
  can_view_ivr,
  userRole,
  roleRestrictions,
  routeContext,
  permissions = []
}) => {
  const { theme } = useTheme();
  const t = themes[theme];
  const { props } = usePage();
  const auth = props.auth as any;

  // Financial permissions
  const financialPerms = useFinancialPermissions();

  // Component state
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    patient: true,
    product: true,
    ivrDocument: true,
    clinical: true,
    provider: true,
    documents: true,
  });

  const [notificationMessage, setNotificationMessage] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const handleBack = () => {
    // Navigate back based on route context
    switch (routeContext) {
      case 'provider':
        router.get('/');
        break;
      case 'sales':
        router.get('/orders/center');
        break;
      case 'admin':
      default:
        router.get('/admin/orders');
        break;
    }
  };

  const handleUpdateIVRStatus = (newStatus: string, notes?: string) => {
    // Implementation for IVR status update
    console.log('Update IVR status:', newStatus, notes);
  };

  const handleUpdateOrderFormStatus = (newStatus: string, notes?: string) => {
    // Implementation for order form status update
    console.log('Update order form status:', newStatus, notes);
  };

  // Get role-appropriate title
  const getPageTitle = () => {
    switch (routeContext) {
      case 'provider':
        return 'Order Details - Provider Portal';
      case 'sales':
        return 'Order Details - Sales Dashboard';
      case 'admin':
      default:
        return 'Order Details - Admin Center';
    }
  };

  // Get role-appropriate breadcrumb
  const getBreadcrumb = () => {
    switch (routeContext) {
      case 'provider':
        return 'Provider Portal > Orders';
      case 'sales':
        return 'Sales Dashboard > Order Management';
      case 'admin':
      default:
        return 'Admin Center > Order Management';
    }
  };

  return (
    <MainLayout>
      <Head title={getPageTitle()} />
      
      <div className={cn("min-h-screen", t.background.base)}>
        <div className="container mx-auto px-4 py-8">
          {/* Header */}
          <div className={cn(
            "flex justify-between items-start mb-8 p-6 rounded-2xl",
            t.glass.card,
            t.glass.border,
            t.shadows.glass
          )}>
            <div className="flex items-center gap-4">
              <Button 
                variant="ghost" 
                onClick={handleBack}
                className={cn("p-2", t.glass.hover)}
              >
                <ArrowLeft className="h-4 w-4" />
              </Button>
              <div>
                <h1 className={cn("text-3xl font-bold mb-2", t.text.primary)}>
                  Order Details
                </h1>
                <div className={cn("flex items-center gap-4 text-sm", t.text.secondary)}>
                  <span>{getBreadcrumb()}</span>
                  <span>•</span>
                  <span>Order #{order.order_number}</span>
                  <span>•</span>
                  <span>Patient: {order.patient_display_id}</span>
                  <span>•</span>
                  <span>Provider: {order.provider_name}</span>
                </div>
              </div>
            </div>
            
            <div className="flex items-center gap-3">
              <div className={cn(
                "px-3 py-1 rounded-lg text-sm font-medium",
                order.order_status === 'approved' ? 'bg-green-100 text-green-800' :
                order.order_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                order.order_status === 'denied' ? 'bg-red-100 text-red-800' :
                'bg-gray-100 text-gray-800'
              )}>
                {order.order_status}
              </div>
              
              {/* Role-specific actions */}
              {routeContext === 'admin' && can_update_status && (
                <div className="flex gap-2">
                  <Button variant="outline" size="sm">
                    Update Status
                  </Button>
                  <Button size="sm">
                    Process Order
                  </Button>
                </div>
              )}
            </div>
          </div>

          {/* Order Sections */}
          <div className="space-y-6">
            {/* Patient & Insurance Section */}
            <PatientInsuranceSection
              orderData={{
                orderNumber: order.order_number,
                createdDate: order.created_at,
                createdBy: order.provider_name,
                patient: order.patient,
                insurance: order.insurance,
                forms: { 
                  ivrStatus: order.ivr_status, 
                  orderFormStatus: order.order_form_status 
                },
                submission: { documents: order.documents }
              }}
              isOpen={openSections.patient}
              onToggle={() => toggleSection('patient')}
            />

            {/* Product Section with Financial Permissions */}
            <ProductSection
              orderData={{
                orderNumber: order.order_number,
                createdDate: order.created_at,
                createdBy: order.provider_name,
                patient: order.patient,
                product: order.product,
                forms: { 
                  ivrStatus: order.ivr_status, 
                  orderFormStatus: order.order_form_status 
                },
                submission: { documents: order.documents },
                pricing: {
                  totalOrderValue: order.total_order_value,
                  unitPrice: order.product?.price,
                  yourPrice: order.product?.customer_price || order.product?.price,
                  nationalASP: order.product?.national_asp,
                  mscPrice: order.product?.msc_price
                }
              }}
              isOpen={openSections.product}
              onToggle={() => toggleSection('product')}
              // Apply financial permissions
              showPricing={financialPerms.shouldShowPricing}
              pricingLevel={financialPerms.accessLevel}
            />

            {/* IVR Document Section */}
            <IVRDocumentSection
              orderData={{
                orderNumber: order.order_number,
                createdDate: order.created_at,
                episodeId: order.episode_id,
                ivrStatus: order.ivr_status,
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
              onManufacturerSubmission={() => {}}
              isOpen={openSections.ivrDocument}
              onToggle={() => toggleSection('ivrDocument')}
              userRole={userRole}
            />

            {/* Clinical Section */}
            <ClinicalSection
              orderData={{
                orderNumber: order.order_number,
                createdDate: order.created_at,
                createdBy: order.provider_name,
                patient: order.patient,
                product: order.product,
                forms: { 
                  ivrStatus: order.ivr_status, 
                  orderFormStatus: order.order_form_status 
                },
                clinical: {
                  ...order.clinical,
                  location: order.clinical?.woundLocation || 'N/A',
                  cptCodes: order.clinical?.cptCodes || 'N/A',
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

            {/* Provider Section - Only show to non-providers */}
            {routeContext !== 'provider' && (
              <ProviderSection
                orderData={{
                  orderNumber: order.order_number,
                  createdDate: order.created_at,
                  createdBy: order.provider_name,
                  patient: order.patient,
                  product: order.product,
                  forms: { 
                    ivrStatus: order.ivr_status, 
                    orderFormStatus: order.order_form_status 
                  },
                  clinical: order.clinical,
                  provider: order.provider,
                  facility: order.facility,
                  submission: { documents: order.documents }
                }}
                isOpen={openSections.provider}
                onToggle={() => toggleSection('provider')}
              />
            )}

            {/* Additional Documents Section */}
            <AdditionalDocumentsSection
              orderData={{
                orderNumber: order.order_number,
                createdDate: order.created_at,
                createdBy: order.provider_name,
                patient: order.patient,
                product: order.product,
                forms: { 
                  ivrStatus: order.ivr_status, 
                  orderFormStatus: order.order_form_status 
                },
                clinical: order.clinical,
                provider: order.provider,
                submission: { 
                  documents: order.documents || [],
                  submissionId: order.docuseal_submission_id,
                  submissionDate: order.created_at,
                  submissionStatus: order.order_status,
                  additionalFiles: [],
                  notes: ''
                }
              }}
              isOpen={openSections.documents}
              onToggle={() => toggleSection('documents')}
              userRole={userRole}
            />
          </div>
        </div>

        {/* Notification Toast */}
        {notificationMessage && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div className={cn(
              "max-w-md w-full mx-4 p-6 rounded-lg shadow-xl",
              notificationMessage.type === 'success'
                ? 'bg-green-50 border border-green-200'
                : 'bg-red-50 border border-red-200'
            )}>
              <div className="flex items-center gap-3 mb-4">
                <div className={cn(
                  "flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center",
                  notificationMessage.type === 'success'
                    ? 'bg-green-100 text-green-600'
                    : 'bg-red-100 text-red-600'
                )}>
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
                  <h3 className={cn(
                    "text-lg font-semibold",
                    notificationMessage.type === 'success'
                      ? 'text-green-800'
                      : 'text-red-800'
                  )}>
                    {notificationMessage.type === 'success' ? 'Success!' : 'Error!'}
                  </h3>
                  <p className={cn(
                    "text-sm",
                    notificationMessage.type === 'success'
                      ? 'text-green-700'
                      : 'text-red-700'
                  )}>
                    {notificationMessage.message}
                  </p>
                </div>
              </div>
              <div className="flex justify-end">
                <button
                  onClick={() => setNotificationMessage(null)}
                  className={cn(
                    "px-4 py-2 rounded-md text-sm font-medium",
                    notificationMessage.type === 'success'
                      ? 'bg-green-600 text-white hover:bg-green-700'
                      : 'bg-red-600 text-white hover:bg-red-700'
                  )}
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

export default UnifiedOrderDetails; 