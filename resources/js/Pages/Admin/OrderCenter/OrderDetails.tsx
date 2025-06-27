import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import PatientInsuranceSection from './PatientInsuranceSection';
import ProductSection from './ProductSection';
import IVRDocumentSection from './IVRDocumentSection';
import ClinicalSection from './ClinicalSection';
import ProviderSection from './ProviderSection';
import AdditionalDocumentsSection from './AdditionalDocumentsSection';
import { OrderModals } from '@/Pages/QuickRequest/Orders/order/OrderModals';
import { ArrowLeft } from 'lucide-react';

interface Order {
  id: string;
  order_number: string;
  patient_name: string;
  patient_display_id: string;
  provider_name: string;
  facility_name: string;
  manufacturer_name: string;
  product_name: string;
  order_status: string;
  total_order_value: number;
  created_at: string;
  action_required: boolean;
}

interface OrderDetailsProps {
  order: Order;
  onBack: () => void;
}

const OrderDetails: React.FC<OrderDetailsProps> = ({ order, onBack }) => {
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
  const [confirmationChecked, setConfirmationChecked] = useState(false);
  const [adminNote, setAdminNote] = useState('');
  const [orderSubmitted, setOrderSubmitted] = useState(false);
  const [notificationMessage, setNotificationMessage] = useState<{ type: 'success' | 'error', message: string } | null>(null);

  // Dummy IVR and Order Form data for admin
  const [ivrData, setIVRData] = useState({
    status: 'Pending',
    sentDate: '2024-07-01',
    resultsReceivedDate: '',
    verifiedDate: '',
    notes: '',
    resultsFileUrl: '',
    rejectionReason: '',
  });
  const [orderFormData, setOrderFormData] = useState({
    status: 'Draft',
    submissionDate: '2024-07-01',
    reviewDate: '',
    approvalDate: '',
    notes: '',
    fileUrl: '',
    rejectionReason: '',
    cancellationReason: '',
    packingSlipUrl: '',
    trackingNumber: '',
    carrier: '',
  });

  // Handlers for IVR and Order Form status updates
  const handleUpdateIVRStatus = async (status: string, notes?: string, rejectionReason?: string) => {
    setIVRData((prev) => ({
      ...prev,
      status,
      notes: notes || '',
      rejectionReason: rejectionReason || '',
      ...(status === 'Sent' && { sentDate: new Date().toLocaleDateString() }),
      ...(status === 'Verified' && { verifiedDate: new Date().toLocaleDateString() }),
    }));

    // Send notification for status changes that require notification
    if (['Sent', 'Verified', 'Rejected'].includes(status)) {
      await handleSendNotification('ivr', status, notes || '');
    }
  };

  const handleUploadIVRResults = (file: File) => {
    setIVRData((prev) => ({
      ...prev,
      resultsFileUrl: file.name,
      resultsReceivedDate: new Date().toLocaleDateString()
    }));
  };

  const handleUpdateOrderFormStatus = async (status: string, notes?: string, rejectionReason?: string, cancellationReason?: string) => {
    setOrderFormData((prev) => ({
      ...prev,
      status,
      notes: notes || '',
      rejectionReason: rejectionReason || '',
      cancellationReason: cancellationReason || '',
      ...(status === 'Submitted to Manufacturer' && { submissionDate: new Date().toLocaleDateString() }),
      ...(status === 'Confirmed by Manufacturer' && { approvalDate: new Date().toLocaleDateString() }),
    }));

    // Send notification for status changes that require notification
    if (['Submitted to Manufacturer', 'Confirmed by Manufacturer', 'Rejected', 'Canceled'].includes(status)) {
      await handleSendNotification('order', status, notes || '');
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
    onBack();
  };

  // Create order data structure with only available/dummy fields
  const orderData = {
    orderNumber: order.order_number,
    createdDate: formatDate(order.created_at),
    createdBy: order.provider_name,
    patient: {
      name: order.patient_name,
      dob: '1980-01-01',
      gender: 'Male',
      phone: '(555) 123-4567',
      address: '123 Main St, City, State 12345',
      insurance: {
        primary: 'Blue Cross Blue Shield - 123456789',
        secondary: 'Medicare - 987654321',
      },
    },
    product: {
      name: order.product_name,
      code: 'BW-001',
      quantity: 1,
      size: '10cm x 10cm',
      category: 'Wound Care Matrix',
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
      woundType: 'Diabetic Foot Ulcer',
      location: 'Right foot, plantar surface',
      size: '3.5 x 2.8cm',
      cptCodes: 'E11.621 - Type 2 diabetes with foot ulcer',
      placeOfService: 'Office',
      failedConservativeTreatment: true,
    },
    provider: {
      name: order.provider_name,
      npi: '1234567890',
      facility: order.facility_name,
    },
    submission: {
      informationAccurate: true,
      documentationMaintained: true,
      authorizePriorAuth: true,
    },
  };

  return (
    <MainLayout>
      <Head title={`Order Details - ${order.order_number}`} />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <div className="container mx-auto px-4 py-8 max-w-6xl">
          {/* Header */}
          <div className="flex items-center gap-4 mb-8">
            <button
              onClick={onBack}
              className="p-2 rounded-lg bg-gray-200 hover:bg-gray-300"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-3xl font-bold text-slate-900 mb-2">
                Order Details
              </h1>
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
            orderId={order.order_number}
            onUpdateIVRStatus={handleUpdateIVRStatus}
            onUploadIVRResults={handleUploadIVRResults}
            onUpdateOrderFormStatus={handleUpdateOrderFormStatus}
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

          {/* Modals (if needed for admin actions) */}

          {/* Notification Toast */}
          {notificationMessage && (
            <div className={`fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
              notificationMessage.type === 'success'
                ? 'bg-green-500 text-white'
                : 'bg-red-500 text-white'
            }`}>
              <div className="flex items-center gap-2">
                <span>{notificationMessage.message}</span>
                <button
                  onClick={() => setNotificationMessage(null)}
                  className="ml-2 hover:opacity-75"
                >
                  ×
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderDetails;
