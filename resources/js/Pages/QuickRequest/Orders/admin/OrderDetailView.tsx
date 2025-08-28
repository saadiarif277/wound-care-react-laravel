
import React, { useState } from 'react';
import { Button } from '../ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';
import { AdminOrderData, AdminActionProps, OrderStatus, IVRStatus, OrderFormStatus } from '../types/adminTypes';
import { AdminActionModals } from './AdminActionModals';
import { IVRSection } from './IVRSection';
import { OrderFormSection } from './OrderFormSection';
import { SectionCard } from '../order/SectionCard';
import { ArrowLeft, FileText, Upload, User, Stethoscope, Package, Building2, ClipboardList, History } from 'lucide-react';

interface OrderDetailViewProps extends AdminActionProps {
  order: AdminOrderData;
  onBack: () => void;
}

export const OrderDetailView: React.FC<OrderDetailViewProps> = ({
  order,
  onBack,
  onStatusChange,
  onGenerateIVR,
  onSubmitToManufacturer,
  onUploadDocument,
  onUpdateIVRStatus,
  onUpdateOrderFormStatus,
  onUploadIVRResults
}) => {
  const [showApprovalModal, setShowApprovalModal] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    patient: true,
    provider: true,
    clinical: true,
    product: true,
    forms: true,
    documents: true,
    history: true
  });

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const getStatusColor = (status: OrderStatus | IVRStatus | OrderFormStatus): string => {
    switch (status) {
      // Order Status colors
      case 'Pending IVR': return 'bg-gray-100 text-gray-800';
      case 'IVR Sent': return 'bg-blue-100 text-blue-800';
      case 'IVR Verified': return 'bg-purple-100 text-purple-800';
      case 'Approved': return 'bg-green-100 text-green-800';
      case 'Denied': return 'bg-red-100 text-red-800';
      case 'Send Back': return 'bg-orange-100 text-orange-800';
      case 'Submitted to Manufacturer': return 'bg-emerald-100 text-emerald-800';
      case 'Confirmed & Shipped': return 'bg-teal-100 text-teal-800';

      // IVR Status colors
      case 'N/A': return 'bg-gray-100 text-gray-800';
      case 'Rejected': return 'bg-red-100 text-red-800';

      // Order Form Status colors
      case 'Draft': return 'bg-yellow-100 text-yellow-800';
      case 'Submitted': return 'bg-blue-100 text-blue-800';
      case 'Under Review': return 'bg-orange-100 text-orange-800';

      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const canApprove = order.orderStatus === 'IVR Verified';
  const canSubmitToManufacturer = order.orderStatus === 'Approved';

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="container mx-auto px-4 py-8 max-w-6xl">
        {/* Header */}
        <div className="flex justify-between items-start mb-8">
          <div className="flex items-center gap-4">
            <Button variant="ghost" onClick={onBack} className="p-2">
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <div>
              <h1 className="text-3xl font-bold text-slate-900 mb-2">Order Details</h1>
              <div className="flex items-center gap-4 text-sm text-muted-foreground">
                <span>Order #{order.orderNumber}</span>
                <span>•</span>
                <span>Patient: {order.patientIdentifier}</span>
                <span>•</span>
                <span>Provider: {order.providerName}</span>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <Badge className={getStatusColor(order.orderStatus)}>
              {order.orderStatus}
            </Badge>
            <div className="flex gap-2">
              {canApprove && (
                <Button onClick={() => setShowApprovalModal(true)}>
                  Review Order
                </Button>
              )}
              {canSubmitToManufacturer && (
                <Button onClick={() => onSubmitToManufacturer(order.orderNumber)}>
                  Submit to Manufacturer
                </Button>
              )}
              <Button variant="outline" onClick={() => setShowUploadModal(true)}>
                <Upload className="h-4 w-4 mr-2" />
                Upload Document
              </Button>
            </div>
          </div>
        </div>

        {/* Patient & Insurance Section */}
        <SectionCard
          title="Patient & Insurance Information"
          icon={User}
          sectionKey="patient"
          isOpen={Boolean(openSections.patient)}
          onToggle={toggleSection}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              <div>
                <span className="font-medium text-sm">Patient ID:</span>
                <div className="text-sm">{order.patientIdentifier}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Expected Service Date:</span>
                <div className="text-sm">{order.expectedServiceDate || 'Not specified'}</div>
              </div>
            </div>
          </div>
        </SectionCard>

        {/* Provider Section */}
        <SectionCard
          title="Provider Information"
          icon={Building2}
          sectionKey="provider"
          isOpen={openSections.provider ?? false}
          onToggle={toggleSection}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              <div>
                <span className="font-medium text-sm">Provider:</span>
                <div className="text-sm">{order.providerName}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Facility Contact:</span>
                <div className="text-sm">{order.facilityContact || 'Not specified'}</div>
              </div>
            </div>
          </div>
        </SectionCard>

        {/* Clinical Section */}
        <SectionCard
          title="Clinical Information"
          icon={Stethoscope}
          sectionKey="clinical"
          isOpen={openSections.clinical ?? false}
          onToggle={toggleSection}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              <div>
                <span className="font-medium text-sm">Wound Type:</span>
                <div className="text-sm">{order.woundType}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Applications (12 months):</span>
                <div className="text-sm">{order.applicationCount}</div>
              </div>
            </div>
            <div className="space-y-3">
              <div>
                <span className="font-medium text-sm">Diagnosis Codes:</span>
                <div className="text-sm ml-4">
                  {order.diagnosisCodes.map((code, index) => (
                    <div key={index}>{code.code} - {code.description}</div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </SectionCard>

        {/* Product Section */}
        <SectionCard
          title="Product Information"
          icon={Package}
          sectionKey="product"
          isOpen={openSections.product ?? false}
          onToggle={toggleSection}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              <div>
                <span className="font-medium text-sm">Product:</span>
                <div className="text-sm">{order.productName}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Manufacturer:</span>
                <div className="text-sm">{order.manufacturerName}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Size:</span>
                <div className="text-sm">{order.sizes.join(', ')}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Quantity:</span>
                <div className="text-sm">{order.quantity}</div>
              </div>
            </div>
            <div className="space-y-3">
              <div>
                <span className="font-medium text-sm">Total ASP Price:</span>
                <div className="text-sm">${order.totalAspPrice.toFixed(2)}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Amount Due:</span>
                <div className="text-sm">${order.amountDue.toFixed(2)}</div>
              </div>
              <div>
                <span className="font-medium text-sm">Shipping Speed:</span>
                <div className="text-sm">{order.shippingSpeed || 'Standard'}</div>
              </div>
            </div>
          </div>
        </SectionCard>

        {/* Forms Section */}
        <SectionCard
          title="IVR Form & Order Form"
          icon={ClipboardList}
          sectionKey="forms"
          isOpen={openSections.forms ?? false}
          onToggle={toggleSection}
        >
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-slate-900">IVR Form</h3>
                <Badge className={getStatusColor(order.ivrData.status || 'Pending IVR')}>
                  {order.ivrData.status || 'Pending IVR'}
                </Badge>
              </div>
              <IVRSection
                order={order}
                onUpdateIVRStatus={onUpdateIVRStatus}
                onUploadIVRResults={onUploadIVRResults}
                onGenerateIVR={onGenerateIVR}
              />
            </div>
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-slate-900">Order Form</h3>
                <Badge className={getStatusColor(order.orderFormData.status || 'Not Started')}>
                  {order.orderFormData.status || 'Not Started'}
                </Badge>
              </div>
              <OrderFormSection
                order={order}
                onUpdateOrderFormStatus={onUpdateOrderFormStatus}
              />
            </div>
          </div>
        </SectionCard>

        {/* Supporting Documents Section */}
        <SectionCard
          title="Supporting Documents"
          icon={FileText}
          sectionKey="documents"
          isOpen={openSections.documents ?? false}
          onToggle={toggleSection}
        >
          <div className="space-y-2">
            {order.supportingDocuments.map((doc, index) => (
              <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center gap-3">
                  <FileText className="h-4 w-4" />
                  <div className="text-sm">
                    <div className="font-medium">{doc.documentType}</div>
                    <div className="text-muted-foreground">{doc.dateUploaded}</div>
                  </div>
                </div>
                <Button variant="ghost" size="sm">View</Button>
              </div>
            ))}
          </div>
        </SectionCard>

        {/* Action History Section */}
        <SectionCard
          title="Action History"
          icon={History}
          sectionKey="history"
          isOpen={openSections.history ?? false}
          onToggle={toggleSection}
        >
          <div className="space-y-4">
            {order.actionHistory.map((action, index) => (
              <div key={index} className="flex items-start gap-3">
                <div className="w-2 h-2 bg-primary rounded-full mt-2"></div>
                <div className="flex-1 text-sm">
                  <div className="font-medium">{action.action}</div>
                  <div className="text-muted-foreground">
                    {action.actor} • {new Date(action.timestamp).toLocaleString()}
                  </div>
                  {action.notes && (
                    <div className="mt-1 text-muted-foreground italic">{action.notes}</div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </SectionCard>

        {/* Modals */}
        <AdminActionModals
          order={order}
          showIVRModal={false}
          showApprovalModal={showApprovalModal}
          showUploadModal={showUploadModal}
          onIVRModalChange={() => {}}
          onApprovalModalChange={setShowApprovalModal}
          onUploadModalChange={setShowUploadModal}
          onGenerateIVR={onGenerateIVR}
          onStatusChange={onStatusChange}
          onUploadDocument={onUploadDocument}
        />
      </div>
    </div>
  );
};
