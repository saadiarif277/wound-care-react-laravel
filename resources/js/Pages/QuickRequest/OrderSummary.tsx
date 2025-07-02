import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { SectionCard } from './Orders/order/SectionCard';
import { InfoRow } from './Orders/order/InfoRow';
import { CheckCircle, FileText, User, Package, MapPin, Clock, Paperclip, Eye, Building } from 'lucide-react';

interface OrderData {
  order: any;
  episode: any;
  patient: {
    name: string;
    fhir_id: string;
    display_id: string;
  };
  provider: {
    name: string;
    npi: string;
  };
  facility: {
    name: string;
  };
  product: {
    name: string;
    code: string;
    manufacturer: string;
    size: string;
    quantity: number;
  };
  submission: {
    docuseal_submission_id: string;
    completed_at: string;
    pdf_url: string;
  };
  status: {
    current: string;
    display: string;
    ivr_completed: boolean;
  };
}

interface OrderSummaryProps {
  orderData: OrderData;
  submissionId?: string;
  episodeId?: string;
}

export default function OrderSummary({
  orderData,
  submissionId,
  episodeId
}: OrderSummaryProps) {
  const [openSections, setOpenSections] = useState({
    summary: true,
    provider: true,
    attachments: true,
    nextSteps: true
  });

  const toggleSection = (section: keyof typeof openSections) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
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

  const statusConfig = getStatusConfig(orderData.status.current);

  const handleViewPdf = () => {
    if (orderData.submission.pdf_url) {
      window.open(orderData.submission.pdf_url, '_blank');
    }
  };

  return (
    <>
      <Head title="Order Summary" />

      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <div className="container mx-auto px-4 py-8 max-w-4xl">

          {/* Header */}
          <div className="text-center mb-8">
            <div className="flex justify-center mb-4">
              <CheckCircle className="h-16 w-16 text-green-500" />
            </div>
            <h1 className="text-3xl font-bold text-slate-900 mb-2">
              Order Details
            </h1>
            <p className="text-lg text-muted-foreground mb-4">
              Order #{orderData.order.id} - {orderData.patient.display_id}
            </p>
          </div>

          {/* Status and PDF Actions */}
          <div className="flex items-center justify-between mb-6 p-4 bg-white rounded-lg border shadow-sm">
            <div>
              <h3 className="text-lg font-medium text-slate-900 mb-2">
                Current Status
              </h3>
              <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border ${statusConfig.color}`}>
                {orderData.status.display}
              </span>
            </div>

            {orderData.submission.pdf_url && (
              <Button
                onClick={handleViewPdf}
                className="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50"
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
            onToggle={(section) => toggleSection(section as keyof typeof openSections)}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Patient Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <User className="h-4 w-4 mr-2" />
                  Patient
                </h5>
                <InfoRow label="Patient Name" value={orderData.patient.name || 'N/A'} />
                <InfoRow label="Patient ID" value={orderData.patient.display_id || 'N/A'} />
                <InfoRow label="FHIR ID" value={orderData.patient.fhir_id || 'N/A'} />
              </div>

              {/* Product Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <Package className="h-4 w-4 mr-2" />
                  Product
                </h5>
                <InfoRow label="Product Name" value={orderData.product.name || 'N/A'} />
                <InfoRow label="Product Code" value={orderData.product.code || 'N/A'} />
                <InfoRow label="Manufacturer" value={orderData.product.manufacturer || 'N/A'} />
                <InfoRow label="Size" value={orderData.product.size || 'N/A'} />
                <InfoRow label="Quantity" value={orderData.product.quantity?.toString() || 'N/A'} />
              </div>
            </div>
          </SectionCard>

          {/* Provider & Facility Section */}
          <SectionCard
            title="Provider & Facility Information"
            icon={Building}
            sectionKey="provider"
            isOpen={openSections.provider}
            onToggle={(section) => toggleSection(section as keyof typeof openSections)}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Provider Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <User className="h-4 w-4 mr-2" />
                  Provider
                </h5>
                <InfoRow label="Provider Name" value={orderData.provider.name || 'N/A'} />
                <InfoRow label="NPI" value={orderData.provider.npi || 'N/A'} />
              </div>

              {/* Facility Column */}
              <div className="space-y-1">
                <h5 className="font-medium text-slate-900 mb-2 flex items-center">
                  <Building className="h-4 w-4 mr-2" />
                  Facility
                </h5>
                <InfoRow label="Facility Name" value={orderData.facility.name || 'N/A'} />
              </div>
            </div>
          </SectionCard>

          {/* Submission Status */}
          <SectionCard
            title="Submission Status"
            icon={Paperclip}
            sectionKey="attachments"
            isOpen={openSections.attachments}
            onToggle={(section) => toggleSection(section as keyof typeof openSections)}
          >
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-700">
                  DocuSeal Submission ID:
                </span>
                <span className="text-sm font-medium text-slate-900">
                  {orderData.submission.docuseal_submission_id || 'N/A'}
                </span>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-sm text-slate-700">
                  IVR Completed:
                </span>
                <span className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium ${
                  orderData.status.ivr_completed
                    ? "bg-green-100 text-green-800"
                    : "bg-red-100 text-red-800"
                }`}>
                  {orderData.status.ivr_completed ? 'Yes' : 'No'}
                </span>
              </div>

              {orderData.submission.completed_at && (
                <div className="flex items-center justify-between">
                  <span className="text-sm text-slate-700">
                    Completed At:
                  </span>
                  <span className="text-sm font-medium text-slate-900">
                    {new Date(orderData.submission.completed_at).toLocaleString()}
                  </span>
                </div>
              )}
            </div>
          </SectionCard>

          {/* Next Steps */}
          <SectionCard
            title="What happens next?"
            icon={Clock}
            sectionKey="nextSteps"
            isOpen={openSections.nextSteps}
            onToggle={(section) => toggleSection(section as keyof typeof openSections)}
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
              onClick={() => window.history.back()}
              className="px-8 py-2 bg-white border border-gray-300 hover:bg-gray-50"
            >
              Back to Orders
            </Button>
          </div>

        </div>
      </div>
    </>
  );
}
