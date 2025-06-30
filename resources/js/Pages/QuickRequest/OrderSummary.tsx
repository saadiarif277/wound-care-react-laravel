import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { SectionCard } from './Orders/order/SectionCard';
import { InfoRow } from './Orders/order/InfoRow';
import { CheckCircle, FileText, User, Package, MapPin, Clock, Paperclip, Eye } from 'lucide-react';

interface OrderSummaryProps {
  orderId: number;
  formValues: Record<string, any>;
  status?: string;
  documentUrl?: string;
  referenceNumber?: string;
}

interface SummaryData {
  patient: {
    name: string;
    dob: string;
    primaryInsurance: string;
  };
  product: {
    code: string;
    size: string;
    applicationType: string;
  };
  coding: {
    primaryIcd10: string;
    primaryCpt: string;
    woundType: string;
  };
  logistics: {
    anticipatedApplicationDate: string;
    placeOfService: string;
    provider: string;
  };
  attachments: {
    insuranceCardFront: boolean;
    insuranceCardBack: boolean;
  };
}

export default function OrderSummary({ 
  orderId, 
  formValues, 
  status = 'pending_admin_review',
  documentUrl,
  referenceNumber 
}: OrderSummaryProps) {
  const [openSections, setOpenSections] = useState({
    summary: true,
    attachments: true,
    nextSteps: true
  });

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  // Build summary data from form values using the normalized field schema
  const summaryData: SummaryData = {
    patient: {
      name: formValues.patient_name || `${formValues.patient_first_name || ''} ${formValues.patient_last_name || ''}`.trim(),
      dob: formValues.patient_dob || 'Not specified',
      primaryInsurance: formValues.primary_ins_name || formValues.primary_insurance_name || 'Not specified',
    },
    product: {
      code: formValues.product_code || (formValues.selected_products?.[0]?.product?.code) || 'Not specified',
      size: formValues.product_size || (formValues.selected_products?.[0]?.size) || 'Not specified',
      applicationType: formValues.application_type || 'New Application',
    },
    coding: {
      primaryIcd10: formValues.icd10_primary || formValues.primary_diagnosis_code || 'Not specified',
      primaryCpt: formValues.cpt_primary || 'Not specified',
      woundType: formValues.wound_type || (formValues.wound_types?.join(', ')) || 'Not specified',
    },
    logistics: {
      anticipatedApplicationDate: formValues.anticipated_application_date || formValues.expected_service_date || 'Not specified',
      placeOfService: formValues.place_of_service || 'Not specified',
      provider: formValues.provider_name || 'Not specified',
    },
    attachments: {
      insuranceCardFront: !!(formValues.attachments_ins_card || formValues.attachments_ins_card_front),
      insuranceCardBack: !!formValues.attachments_ins_card_back,
    }
  };

  // Status configuration
  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'pending_admin_review':
        return { 
          color: 'bg-yellow-100 text-yellow-800 border-yellow-200', 
          label: 'Pending Admin Review',
          darkColor: 'bg-yellow-900/30 text-yellow-300 border-yellow-700'
        };
      case 'pending_manufacturer':
        return { 
          color: 'bg-blue-100 text-blue-800 border-blue-200', 
          label: 'Pending Manufacturer',
          darkColor: 'bg-blue-900/30 text-blue-300 border-blue-700'
        };
      case 'approved':
        return { 
          color: 'bg-green-100 text-green-800 border-green-200', 
          label: 'Approved',
          darkColor: 'bg-green-900/30 text-green-300 border-green-700'
        };
      default:
        return { 
          color: 'bg-gray-100 text-gray-800 border-gray-200', 
          label: status,
          darkColor: 'bg-gray-900/30 text-gray-300 border-gray-700'
        };
    }
  };

  const statusConfig = getStatusConfig(status);

  const handleContinueToFullReview = () => {
    // Navigate to the full order review page
    window.location.href = `/quick-requests/orders/${orderId}/review`;
  };

  const handleViewPdf = () => {
    if (documentUrl) {
      window.open(documentUrl, '_blank');
    }
  };

  return (
    <>
      <Head title="Order Summary" />
      
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <div className="container mx-auto px-4 py-8 max-w-4xl">
          
          {/* Success Header */}
          <div className="text-center mb-8">
            <div className="flex justify-center mb-4">
              <CheckCircle className="h-16 w-16 text-green-500" />
            </div>
            <h1 className="text-3xl font-bold text-slate-900 mb-2">
              Order Submitted Successfully!
            </h1>
            <p className="text-lg text-muted-foreground mb-4">
              Your order has been submitted and is now being processed.
            </p>
            
            {/* Reference Number */}
            {referenceNumber && (
              <div className="inline-flex items-center px-4 py-2 rounded-full bg-slate-100 border">
                <span className="text-xs uppercase tracking-wider text-slate-600 mr-2">
                  Reference Number:
                </span>
                <span className="font-mono font-medium text-slate-900">
                  {referenceNumber}
                </span>
              </div>
            )}
          </div>

          {/* Status and PDF Actions */}
          <div className="flex items-center justify-between mb-6 p-4 bg-white rounded-lg border shadow-sm">
            <div>
              <h3 className="text-lg font-medium text-slate-900 mb-2">
                Current Status
              </h3>
              <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border ${statusConfig.color}`}>
                {statusConfig.label}
              </span>
            </div>
            
            {documentUrl && (
              <Button
                onClick={handleViewPdf}
                variant="outline"
                className="flex items-center gap-2"
              >
                <Eye className="h-4 w-4" />
                View Signed Form
              </Button>
            )}
          </div>

          {/* Order Summary Section */}
          <SectionCard
            title="Order Summary"
            icon={FileText}
            sectionKey="summary"
            isOpen={openSections.summary}
            onToggle={toggleSection}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Patient Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <User className="h-4 w-4 mr-2" />
                  Patient
                </h5>
                <InfoRow label="Patient Name" value={summaryData.patient.name} />
                <InfoRow label="Date of Birth" value={summaryData.patient.dob} />
                <InfoRow label="Primary Insurance" value={summaryData.patient.primaryInsurance} />
              </div>

              {/* Product Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <Package className="h-4 w-4 mr-2" />
                  Product
                </h5>
                <InfoRow label="Product Code" value={summaryData.product.code} />
                <InfoRow label="Product Size" value={summaryData.product.size} />
                <InfoRow label="Application Type" value={summaryData.product.applicationType} />
              </div>

              {/* Coding Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <FileText className="h-4 w-4 mr-2" />
                  Coding
                </h5>
                <InfoRow label="Primary ICD-10" value={summaryData.coding.primaryIcd10} />
                <InfoRow label="Primary CPT" value={summaryData.coding.primaryCpt} />
                <InfoRow label="Wound Type" value={summaryData.coding.woundType} />
              </div>

              {/* Logistics Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <MapPin className="h-4 w-4 mr-2" />
                  Logistics
                </h5>
                <InfoRow 
                  label="Anticipated Application Date" 
                  value={summaryData.logistics.anticipatedApplicationDate} 
                />
                <InfoRow label="Place of Service" value={summaryData.logistics.placeOfService} />
                <InfoRow label="Provider" value={summaryData.logistics.provider} />
              </div>
            </div>
          </SectionCard>

          {/* Attachments Status */}
          <SectionCard
            title="Attachments"
            icon={Paperclip}
            sectionKey="attachments"
            isOpen={openSections.attachments}
            onToggle={toggleSection}
          >
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-700">
                  Insurance Card (Front):
                </span>
                <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${
                  summaryData.attachments.insuranceCardFront
                    ? "bg-green-100 text-green-800"
                    : "bg-red-100 text-red-800"
                }`}>
                  {summaryData.attachments.insuranceCardFront ? 'Uploaded' : 'Missing'}
                </span>
              </div>
              
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-700">
                  Insurance Card (Back):
                </span>
                <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${
                  summaryData.attachments.insuranceCardBack
                    ? "bg-green-100 text-green-800"
                    : "bg-red-100 text-red-800"
                }`}>
                  {summaryData.attachments.insuranceCardBack ? 'Uploaded' : 'Missing'}
                </span>
              </div>
            </div>
          </SectionCard>

          {/* Next Steps */}
          <SectionCard
            title="What happens next?"
            icon={Clock}
            sectionKey="nextSteps"
            isOpen={openSections.nextSteps}
            onToggle={toggleSection}
          >
            <div className="space-y-3">
              <div className="flex items-start">
                <div className="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 flex items-center justify-center mr-3 mt-0.5">
                  <span className="text-xs font-medium text-blue-600">1</span>
                </div>
                <div>
                  <p className="text-sm font-medium text-slate-900">Admin Review</p>
                  <p className="text-sm text-slate-600">Your order will be reviewed by our admin team</p>
                </div>
              </div>
              
              <div className="flex items-start">
                <div className="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 flex items-center justify-center mr-3 mt-0.5">
                  <span className="text-xs font-medium text-blue-600">2</span>
                </div>
                <div>
                  <p className="text-sm font-medium text-slate-900">Manufacturer Processing</p>
                  <p className="text-sm text-slate-600">Once approved, it will be sent to the manufacturer</p>
                </div>
              </div>
              
              <div className="flex items-start">
                <div className="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 flex items-center justify-center mr-3 mt-0.5">
                  <span className="text-xs font-medium text-blue-600">3</span>
                </div>
                <div>
                  <p className="text-sm font-medium text-slate-900">Order Fulfillment</p>
                  <p className="text-sm text-slate-600">You'll receive notifications about status changes and tracking information</p>
                </div>
              </div>
            </div>
          </SectionCard>

          {/* Action Buttons */}
          <div className="flex justify-center gap-4 mt-8">
            <Button
              onClick={handleContinueToFullReview}
              className="bg-primary hover:bg-primary/90 text-primary-foreground px-8 py-2"
            >
              View Full Order Details
            </Button>
          </div>

        </div>
      </div>
    </>
  );
}
