import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import PatientInsuranceSection from './PatientInsuranceSection';
import ProductSection from './ProductSection';
import IVRDocumentSection from './IVRDocumentSection';
import ClinicalSection from './ClinicalSection';
import ProviderSection from './ProviderSection';
import AdditionalDocumentsSection from './AdditionalDocumentsSection';
import { OrderModals } from '@/Pages/QuickRequest/Orders/order/OrderModals';
import ManufacturerSubmissionModal from './ManufacturerSubmissionModal';
import { ArrowLeft } from 'lucide-react';

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
}

interface OrderDetailsProps {
  order: Order;
  can_update_status: boolean;
  can_view_ivr: boolean;
}

const OrderDetails: React.FC<OrderDetailsProps> = ({ order, can_update_status, can_view_ivr }) => {
  const [userRole, setUserRole] = useState<'Provider' | 'OM' | 'Admin'>('Admin');
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    patient: true,
    product: true,
    ivrDocument: true,
    clinical: true,
    provider: true,
    documents: true,
  });
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [showSuccessModal, setShowSuccessModal] = useState(false);
  const [showNoteModal, setShowNoteModal] = useState(false);
  const [showManufacturerSubmissionModal, setShowManufacturerSubmissionModal] = useState(false);
  const [confirmationChecked, setConfirmationChecked] = useState(false);
  const [adminNote, setAdminNote] = useState('');
  const [orderSubmitted, setOrderSubmitted] = useState(false);
  const [notificationMessage, setNotificationMessage] = useState<{ type: 'success' | 'error', message: string } | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [enhancedOrderData, setEnhancedOrderData] = useState<any>(null);

  // Fetch enhanced order details on component mount
  useEffect(() => {
    const fetchEnhancedOrderDetails = async () => {
      try {
        const response = await fetch(`/admin/orders/${order.id}/enhanced-details`, {
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
        });

        if (response.ok) {
          const data = await response.json();
          setEnhancedOrderData(data);
        } else {
          console.error('Failed to fetch enhanced order details');
        }
      } catch (error) {
        console.error('Error fetching enhanced order details:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchEnhancedOrderDetails();
  }, [order.id]);

  // IVR and Order Form data based on real data or defaults
  const [ivrData, setIVRData] = useState<{
    status: string;
    sentDate: string;
    resultsReceivedDate: string;
    verifiedDate: string;
    notes: string;
    resultsFileUrl: string;
    rejectionReason: string;
    files: Array<{
      id: string;
      name: string;
      type: string;
      uploadedAt: string;
      uploadedBy: string;
      fileSize: string;
      url: string;
    }>;
  }>({
    status: order.ivr_status || 'Pending',
    sentDate: '',
    resultsReceivedDate: '',
    verifiedDate: '',
    notes: '',
    resultsFileUrl: '',
    rejectionReason: '',
    files: [],
  });
  const [orderFormData, setOrderFormData] = useState<{
    status: string;
    submissionDate: string;
    reviewDate: string;
    approvalDate: string;
    notes: string;
    fileUrl: string;
    rejectionReason: string;
    cancellationReason: string;
    packingSlipUrl: string;
    trackingNumber: string;
    carrier: string;
    files: Array<{
      id: string;
      name: string;
      type: string;
      uploadedAt: string;
      uploadedBy: string;
      fileSize: string;
      url: string;
    }>;
  }>({
    status: order.order_form_status || 'Draft',
    submissionDate: '',
    reviewDate: '',
    approvalDate: '',
    notes: '',
    fileUrl: '',
    rejectionReason: '',
    cancellationReason: '',
    packingSlipUrl: '',
    trackingNumber: '',
    carrier: '',
    files: [],
  });

    // Handlers for IVR and Order Form status updates
  const handleUpdateIVRStatus = async (data: any) => {
    const { status, comments, rejectionReason, sendNotification } = data;

    try {
      // Map frontend status values to backend expected values
      const statusMapping: Record<string, string> = {
        'Sent': 'sent',
        'Verified': 'verified',
        'Rejected': 'rejected',
        'Pending': 'pending',
        'N/A': 'n/a',
      };

      const backendStatus = statusMapping[status] || status.toLowerCase().replace(/ /g, '_');

      // Call backend API to update order status
      console.log('Updating IVR status for order:', order.id, 'with data:', { status, backendStatus, comments, rejectionReason, sendNotification });
      const response = await fetch(`/admin/orders/${order.id}/change-status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          status: backendStatus,
          status_type: 'ivr',
          notes: comments,
          rejection_reason: rejectionReason,
          send_notification: sendNotification,
        }),
      });

      if (response.ok) {
        const result = await response.json();

        // Update local state
        setIVRData((prev) => ({
          ...prev,
          status,
          notes: comments || '',
          rejectionReason: rejectionReason || '',
          ...(status === 'Sent' && { sentDate: new Date().toLocaleDateString() }),
          ...(status === 'Verified' && { verifiedDate: new Date().toLocaleDateString() }),
        }));

        // Show success notification
        setNotificationMessage({
          type: 'success',
          message: result.message || 'IVR status updated successfully!'
        });
        setTimeout(() => setNotificationMessage(null), 5000);
      } else {
        console.error('Response not OK:', response.status, response.statusText);
        const responseText = await response.text();
        console.error('Response body:', responseText);

        try {
          const error = JSON.parse(responseText);
          setNotificationMessage({
            type: 'error',
            message: error.error || 'Failed to update IVR status'
          });
        } catch (parseError) {
          setNotificationMessage({
            type: 'error',
            message: `Failed to update IVR status (${response.status}): ${responseText.substring(0, 100)}`
          });
        }
        setTimeout(() => setNotificationMessage(null), 5000);
      }

    } catch (error) {
      console.error('Error updating IVR status:', error);
      setNotificationMessage({
        type: 'error',
        message: 'Failed to update IVR status. Please try again.'
      });
      setTimeout(() => setNotificationMessage(null), 5000);
    }
  };

  const handleUploadIVRResults = (file: File) => {
    const newFile = {
      id: Date.now().toString(),
      name: file.name,
      type: file.type,
      uploadedAt: new Date().toISOString(),
      uploadedBy: 'Admin',
      fileSize: `${(file.size / 1024).toFixed(1)} KB`,
      url: URL.createObjectURL(file),
    };

    setIVRData((prev) => ({
      ...prev,
      resultsFileUrl: file.name,
      resultsReceivedDate: new Date().toLocaleDateString(),
      files: [...prev.files, newFile],
    }));
  };

    const handleUpdateOrderFormStatus = async (data: any) => {
    const { status, comments, rejectionReason, cancellationReason, sendNotification, carrier, trackingNumber } = data;

    try {
      // Map frontend status values to backend expected values
      const statusMapping: Record<string, string> = {
        'Submitted to Manufacturer': 'submitted_to_manufacturer',
        'Confirmed by Manufacturer': 'confirmed_by_manufacturer',
        'Rejected': 'rejected',
        'Canceled': 'canceled',
        'Pending': 'pending',
      };

      const backendStatus = statusMapping[status] || status.toLowerCase().replace(/ /g, '_');

      // Call backend API to update order status
      const response = await fetch(`/admin/orders/${order.id}/change-status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          status: backendStatus,
          status_type: 'order',
          notes: comments,
          rejection_reason: rejectionReason,
          cancellation_reason: cancellationReason,
          send_notification: sendNotification,
          carrier,
          tracking_number: trackingNumber,
        }),
      });

      if (response.ok) {
        const result = await response.json();

        // Update local state
        setOrderFormData((prev) => ({
          ...prev,
          status,
          notes: comments || '',
          rejectionReason: rejectionReason || '',
          cancellationReason: cancellationReason || '',
          carrier: carrier || '',
          trackingNumber: trackingNumber || '',
          ...(status === 'Submitted to Manufacturer' && { submissionDate: new Date().toLocaleDateString() }),
          ...(status === 'Confirmed by Manufacturer' && { approvalDate: new Date().toLocaleDateString() }),
        }));

        // Show success notification
        setNotificationMessage({
          type: 'success',
          message: result.message || 'Order form status updated successfully!'
        });
        setTimeout(() => setNotificationMessage(null), 5000);
      } else {
        const error = await response.json();
        setNotificationMessage({
          type: 'error',
          message: error.error || 'Failed to update order form status'
        });
        setTimeout(() => setNotificationMessage(null), 5000);
      }

    } catch (error) {
      console.error('Error updating order form status:', error);
      setNotificationMessage({
        type: 'error',
        message: 'Failed to update order form status. Please try again.'
      });
      setTimeout(() => setNotificationMessage(null), 5000);
    }
  };

  const handleSendNotification = async (type: 'ivr' | 'order', status: string, comments: string) => {
    try {
      // Send email notification via Mailtrap
      const response = await fetch('/api/admin/send-notification', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          type,
          orderId: order.order_number,
          status,
          comments,
          recipientEmail: 'provider@example.com', // This would come from the order data
          recipientName: order.provider_name,
        }),
      });

      if (response.ok) {
        console.log('Notification sent successfully');
        setNotificationMessage({
          type: 'success',
          message: `Status updated and notification sent successfully!`
        });
        // Clear notification after 3 seconds
        setTimeout(() => setNotificationMessage(null), 3000);
      } else {
        console.error('Failed to send notification');
        setNotificationMessage({
          type: 'error',
          message: 'Status updated but notification failed to send. Please try again.'
        });
        setTimeout(() => setNotificationMessage(null), 5000);
      }
    } catch (error) {
      console.error('Error sending notification:', error);
      setNotificationMessage({
        type: 'error',
        message: 'Status updated but notification failed to send. Please try again.'
      });
      setTimeout(() => setNotificationMessage(null), 5000);
    }
  };

  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const isOrderComplete = () => {
    // Add your order completion logic here
    return true;
  };

  const handleSubmitOrder = () => {
    setShowSubmitModal(true);
  };

  const handleManufacturerSubmission = async (data: any) => {
    try {
      const response = await fetch(`/admin/orders/${order.id}/change-status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
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
            submitted_at: new Date().toISOString(),
            submitted_by: 'Admin',
          },
        }),
      });

      if (response.ok) {
        const result = await response.json();

        // Show success notification with email status
        const message = data.sendNotification && result.notification_sent
          ? 'Order submitted to manufacturer successfully! Email notification sent.'
          : 'Order submitted to manufacturer successfully!';

        setNotificationMessage({
          type: 'success',
          message: message
        });
        setTimeout(() => setNotificationMessage(null), 5000);

        // Update order form status
        setOrderFormData((prev) => ({
          ...prev,
          status: 'Submitted to Manufacturer',
          carrier: data.carrier,
          trackingNumber: data.trackingNumber,
          submissionDate: new Date().toLocaleDateString(),
        }));
      } else {
        const error = await response.json();
        setNotificationMessage({
          type: 'error',
          message: error.error || 'Failed to submit to manufacturer'
        });
        setTimeout(() => setNotificationMessage(null), 5000);
      }
    } catch (error) {
      console.error('Error submitting to manufacturer:', error);
      setNotificationMessage({
        type: 'error',
        message: 'Failed to submit to manufacturer. Please try again.'
      });
      setTimeout(() => setNotificationMessage(null), 5000);
    }
  };

  const confirmSubmission = () => {
    setShowSubmitModal(false);
    setOrderSubmitted(true);
    setShowSuccessModal(true);
  };

  const handleAddNote = () => {
    setShowNoteModal(false);
    // Handle adding note logic
  };

  const finishSubmission = () => {
    setShowSuccessModal(false);
    window.history.back();
  };

  // Create order data structure with real data
  const orderData = {
    orderNumber: order.order_number,
    createdDate: formatDate(order.created_at),
    createdBy: order.provider_name,
    patient: {
      name: order.patient_name,
      dob: '1980-01-01', // This would come from FHIR data
      gender: 'Male', // This would come from FHIR data
      phone: '(555) 123-4567', // This would come from FHIR data
      address: '123 Main St, City, State 12345', // This would come from FHIR data
      insurance: {
        primary: 'Blue Cross Blue Shield - 123456789', // This would come from FHIR data
        secondary: 'Medicare - 987654321', // This would come from FHIR data
      },
    },
    product: {
      name: order.product_name,
      code: 'BW-001', // This would come from product data
      quantity: 1, // This would come from order data
      size: '10cm x 10cm', // This would come from product data
      category: 'Wound Care Matrix', // This would come from product data
      manufacturer: order.manufacturer_name,
      shippingInfo: {
        speed: 'Standard',
        address: '123 Main St, City, State 12345',
      },
    },
    forms: {
      consent: true,
      assignmentOfBenefits: true,
      medicalNecessity: true,
    },
    clinical: {
      woundType: 'Diabetic Foot Ulcer', // This would come from FHIR data
      location: 'Right foot, plantar surface', // This would come from FHIR data
      size: '3.5 x 2.8cm', // This would come from FHIR data
      cptCodes: 'E11.621 - Type 2 diabetes with foot ulcer', // This would come from FHIR data
      placeOfService: 'Office',
      failedConservativeTreatment: true,
    },
    provider: {
      name: order.provider_name,
      npi: '1234567890', // This would come from provider data
      facility: order.facility_name,
    },
    submission: {
      informationAccurate: true,
      documentationMaintained: true,
      authorizePriorAuth: true,
    },
  };

  // Show loading state
  if (isLoading) {
    return (
      <MainLayout>
        <Head title={`Order Details - ${order.order_number}`} />
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
          <div className="container mx-auto px-4 py-8 max-w-6xl">
            <div className="flex items-center justify-center h-64">
              <div className="text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p className="text-gray-600">Loading order details...</p>
              </div>
            </div>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title={`Order Details - ${order.order_number}`} />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <div className="container mx-auto px-4 py-8 max-w-6xl">
          {/* Header */}
          <div className="flex items-center gap-4 mb-8">
            <button
              onClick={() => window.history.back()}
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
                <span>Order #{orderData.orderNumber}</span>
                <span>•</span>
                <span>Created {orderData.createdDate}</span>
                <span>•</span>
                <span>By {orderData.createdBy}</span>
              </div>
            </div>
          </div>

          {/* Order Sections */}
          <PatientInsuranceSection
            orderData={orderData}
            isOpen={!!openSections.patient}
            onToggle={toggleSection}
          />
          <ProductSection
            orderData={orderData}
            userRole={'Admin'}
            isOpen={!!openSections.product}
            onToggle={toggleSection}
          />
          <IVRDocumentSection
            ivrData={ivrData}
            orderFormData={orderFormData}
            orderId={order.id}
            onUpdateIVRStatus={handleUpdateIVRStatus}
            onUploadIVRResults={handleUploadIVRResults}
            onUpdateOrderFormStatus={handleUpdateOrderFormStatus}
            onManufacturerSubmission={() => setShowManufacturerSubmissionModal(true)}
            isOpen={!!openSections.ivrDocument}
            onToggle={() => toggleSection('ivrDocument')}
          />
          <ClinicalSection
            orderData={orderData}
            isOpen={!!openSections.clinical}
            onToggle={toggleSection}
          />
          <ProviderSection
            orderData={orderData}
            isOpen={!!openSections.provider}
            onToggle={toggleSection}
          />
          <AdditionalDocumentsSection
            documents={[]}
            isOpen={!!openSections.documents}
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

          {/* Enhanced Notification Toast */}
          {notificationMessage && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
              <div className={`max-w-md w-full mx-4 p-6 rounded-lg shadow-xl transform transition-all ${
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
                    className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
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
      </div>
    </MainLayout>
  );
};

export default OrderDetails;
