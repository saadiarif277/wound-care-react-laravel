import React, { useState } from 'react';
import { ChevronDown, ChevronRight, FileText, Send, CheckCircle, Clock, AlertCircle, X, Download, Eye, Upload, Trash2 } from 'lucide-react';
import { Button } from '@/Components/Button';
import StatusUpdateModal from './StatusUpdateModal';
import DocumentViewerPanel from '@/Components/DocumentViewerPanel';

interface IVRData {
  status: string;
  sentDate: string;
  resultsReceivedDate: string;
  verifiedDate: string;
  notes: string;
  resultsFileUrl: string;
  rejectionReason?: string;
  files: DocumentFile[];
}

interface OrderFormData {
  status: string;
  submissionDate: string;
  reviewDate: string;
  approvalDate: string;
  notes: string;
  fileUrl: string;
  rejectionReason?: string;
  cancellationReason?: string;
  packingSlipUrl?: string;
  trackingNumber?: string;
  carrier?: string;
  files: DocumentFile[];
}

interface DocumentFile {
  id: string;
  name: string;
  type: string;
  uploadedAt: string;
  uploadedBy: string;
  fileSize: string;
  url: string;
}

interface IVRDocumentSectionProps {
  ivrData: IVRData;
  orderFormData: OrderFormData;
  orderId: number;
  onUpdateIVRStatus: (data: any) => Promise<void>;
  onUploadIVRResults: (file: File) => void;
  onUpdateOrderFormStatus: (data: any) => Promise<void>;
  onManufacturerSubmission?: () => void;
  isOpen?: boolean;
  onToggle?: () => void;
  userRole?: 'Provider' | 'OM' | 'Admin';
}

const IVRDocumentSection: React.FC<IVRDocumentSectionProps> = ({
  ivrData,
  orderFormData,
  orderId,
  onUpdateIVRStatus,
  onUploadIVRResults,
  onUpdateOrderFormStatus,
  onManufacturerSubmission,
  isOpen = true,
  onToggle,
  userRole = 'Admin',
}) => {
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [modalType, setModalType] = useState<'ivr' | 'order'>('ivr');
  const [modalCurrentStatus, setModalCurrentStatus] = useState('');
  const [modalNewStatus, setModalNewStatus] = useState('');
  const [showDocumentPanel, setShowDocumentPanel] = useState(false);
  const [documentPanelType, setDocumentPanelType] = useState<'ivr' | 'order-form'>('ivr');
  // Remove local notification state - let parent handle notifications

  const getStatusColor = (status: string | null | undefined) => {
    if (!status || typeof status !== 'string') {
      return 'text-gray-600 bg-gray-100';
    }

    switch (status.toLowerCase()) {
      case 'completed':
      case 'approved':
      case 'verified':
      case 'confirmed by manufacturer':
        return 'text-green-600 bg-green-100';
      case 'pending':
      case 'draft':
      case 'n/a':
        return 'text-yellow-600 bg-yellow-100';
      case 'sent':
      case 'submitted to manufacturer':
        return 'text-blue-600 bg-blue-100';
      case 'rejected':
      case 'canceled':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getStatusIcon = (status: string | null | undefined) => {
    if (!status || typeof status !== 'string') {
      return <Clock className="h-4 w-4" />;
    }

    switch (status.toLowerCase()) {
      case 'completed':
      case 'approved':
      case 'verified':
      case 'confirmed by manufacturer':
        return <CheckCircle className="h-4 w-4" />;
      case 'pending':
      case 'draft':
      case 'n/a':
        return <Clock className="h-4 w-4" />;
      case 'sent':
      case 'submitted to manufacturer':
        return <Send className="h-4 w-4" />;
      case 'rejected':
      case 'canceled':
        return <AlertCircle className="h-4 w-4" />;
      default:
        return <Clock className="h-4 w-4" />;
    }
  };

  const handleStatusChange = (type: 'ivr' | 'order', currentStatus: string, newStatus: string) => {
    setModalType(type);
    setModalCurrentStatus(currentStatus);
    setModalNewStatus(newStatus);
    setShowStatusModal(true);
  };

  const handleStatusUpdate = async (data: any) => {
    try {
      const statusType = modalType === 'ivr' ? 'ivr' : 'order';

      // Map frontend status values to backend expected values
      const statusMapping: Record<string, string> = {
        'Sent': 'sent',
        'Verified': 'verified',
        'Rejected': 'rejected',
        'Pending': 'pending',
        'N/A': 'n/a',
        'Submitted to Manufacturer': 'submitted_to_manufacturer',
        'Confirmed by Manufacturer': 'confirmed_by_manufacturer',
        'Canceled': 'canceled',
      };

      const status = statusMapping[data.status] || data.status.toLowerCase().replace(/ /g, '_');

      // Call backend API to update status
      const response = await fetch(`/admin/orders/${orderId}/change-status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          status: status,
          status_type: statusType,
          notes: data.comments,
          rejection_reason: data.rejectionReason,
          send_notification: data.sendNotification,
          carrier: data.carrier,
          tracking_number: data.trackingNumber,
        }),
      });

      if (response.ok) {
        const result = await response.json();

        // Update local state with the processed data
        const processedData = {
          ...data,
          status: data.status, // Keep the display status
          backendStatus: status, // Add the backend status for reference
        };

        if (modalType === 'ivr') {
          onUpdateIVRStatus(processedData);
        } else {
          onUpdateOrderFormStatus(processedData);
        }

        // Success - parent will handle notification via onUpdateIVRStatus/onUpdateOrderFormStatus
      } else {
        const errorData = await response.json();
        console.error('Status update failed:', errorData.error || `Failed to update ${statusType} status`);
        // Parent will handle error notification
      }
    } catch (error) {
      console.error('Status update error:', error);
      // Parent will handle error notification
    }
  };

  const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>, type: 'ivr' | 'order') => {
    const file = event.target.files?.[0];
    if (file) {
      if (type === 'ivr') {
        onUploadIVRResults(file);
      }
      // Handle order form file upload
    }
  };

  const removeFile = (fileId: string, type: 'ivr' | 'order') => {
    // Handle file removal
    console.log(`Removing file ${fileId} from ${type}`);
  };

  const handleViewDocument = (type: 'ivr' | 'order-form') => {
    setDocumentPanelType(type);
    setShowDocumentPanel(true);
  };

  return (
    <>
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div
          className="flex items-center justify-between p-6 cursor-pointer hover:bg-gray-50"
          onClick={onToggle}
        >
          <div className="flex items-center gap-3">
            <FileText className="h-5 w-5 text-blue-600" />
            <h3 className="text-lg font-semibold text-gray-900">
              IVR & Document Management
            </h3>
          </div>
          {onToggle && (
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-500">Manage IVR and Order Forms</span>
              {isOpen ? (
                <ChevronDown className="h-5 w-5 text-gray-400" />
              ) : (
                <ChevronRight className="h-5 w-5 text-gray-400" />
              )}
            </div>
          )}
        </div>

        {isOpen && (
          <div className="px-6 pb-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* IVR Management */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-md font-medium text-gray-900">IVR Management</h4>
                  <span className={`px-2 py-1 rounded-full text-xs font-medium flex items-center gap-1 ${getStatusColor(ivrData.status)}`}>
                    {getStatusIcon(ivrData.status)}
                    {ivrData.status}
                  </span>
                </div>

                <div className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Sent Date:</span>
                    <span className="font-medium">{ivrData.sentDate || 'Not sent'}</span>
                  </div>
                  {ivrData.resultsReceivedDate && (
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-600">Results Received:</span>
                      <span className="font-medium">{ivrData.resultsReceivedDate}</span>
                    </div>
                  )}
                  {ivrData.verifiedDate && (
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-600">Verified Date:</span>
                      <span className="font-medium">{ivrData.verifiedDate}</span>
                    </div>
                  )}
                </div>

                <div className="space-y-3">
                  <div className="flex gap-2">
                    <Button
                      onClick={() => handleViewDocument('ivr')}
                      className="flex-1"
                      variant="ghost"
                      size="sm"
                    >
                      <Eye className="h-4 w-4 mr-2" />
                      View IVR
                    </Button>
                  </div>

                  {userRole === 'Admin' && (
                    <div className="space-y-2">
                      <label className="block text-sm font-medium text-gray-700">IVR Status</label>
                      <select
                        value={ivrData.status}
                        onChange={(e) => handleStatusChange('ivr', ivrData.status, e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        <option value="n/a">N/A</option>
                        <option value="pending">Pending</option>
                        <option value="sent">Sent</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                      </select>
                    </div>
                  )}
                </div>

                {/* IVR Files */}
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <h5 className="text-sm font-medium text-gray-700">IVR Documents</h5>
                    {userRole === 'Admin' && (
                      <label className="cursor-pointer">
                        <input
                          type="file"
                          className="hidden"
                          accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                          onChange={(e) => handleFileUpload(e, 'ivr')}
                        />
                        <div className="flex items-center gap-1 text-blue-600 hover:text-blue-700 text-sm">
                          <Upload className="h-4 w-4" />
                          Add File
                        </div>
                      </label>
                    )}
                  </div>

                  {ivrData.files && ivrData.files.length > 0 ? (
                    <div className="space-y-2">
                      {ivrData.files.map((file) => (
                        <div key={file.id} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                          <div className="flex items-center gap-2">
                            <FileText className="h-4 w-4 text-gray-500" />
                            <span className="text-sm text-gray-700">{file.name}</span>
                          </div>
                          <div className="flex items-center gap-1">
                            <button
                              onClick={() => window.open(file.url, '_blank')}
                              className="text-blue-600 hover:text-blue-700"
                            >
                              <Eye className="h-4 w-4" />
                            </button>
                            <button
                              onClick={() => {
                                const link = window.document.createElement('a');
                                link.href = file.url;
                                link.download = file.name;
                                link.click();
                              }}
                              className="text-green-600 hover:text-green-700"
                            >
                              <Download className="h-4 w-4" />
                            </button>
                            {userRole === 'Admin' && (
                              <button
                                onClick={() => removeFile(file.id, 'ivr')}
                                className="text-red-600 hover:text-red-700"
                              >
                                <Trash2 className="h-4 w-4" />
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-4 text-gray-500 text-sm">
                      No IVR documents uploaded
                    </div>
                  )}
                </div>

                {ivrData.notes && (
                  <div className="p-3 bg-blue-50 rounded-md">
                    <p className="text-sm text-blue-800">{ivrData.notes}</p>
                  </div>
                )}

                {ivrData.rejectionReason && (
                  <div className="p-3 bg-red-50 rounded-md">
                    <p className="text-sm font-medium text-red-800 mb-1">Rejection Reason:</p>
                    <p className="text-sm text-red-700">{ivrData.rejectionReason}</p>
                  </div>
                )}
              </div>

              {/* Order Form Management */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-md font-medium text-gray-900">Order Form Management</h4>
                  <span className={`px-2 py-1 rounded-full text-xs font-medium flex items-center gap-1 ${getStatusColor(orderFormData.status)}`}>
                    {getStatusIcon(orderFormData.status)}
                    {orderFormData.status}
                  </span>
                </div>

                <div className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Submission Date:</span>
                    <span className="font-medium">{orderFormData.submissionDate || 'Not submitted'}</span>
                  </div>
                  {orderFormData.reviewDate && (
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-600">Review Date:</span>
                      <span className="font-medium">{orderFormData.reviewDate}</span>
                    </div>
                  )}
                  {orderFormData.approvalDate && (
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-600">Approval Date:</span>
                      <span className="font-medium">{orderFormData.approvalDate}</span>
                    </div>
                  )}
                </div>

                <div className="space-y-3">
                  <div className="flex gap-2">
                    <Button
                      onClick={() => handleViewDocument('order-form')}
                      className="flex-1"
                      variant="ghost"
                      size="sm"
                    >
                      <Eye className="h-4 w-4 mr-2" />
                      View Order Form
                    </Button>
                  </div>

                  {userRole === 'Admin' && (
                    <div className="space-y-2">
                      <label className="block text-sm font-medium text-gray-700">Order Form Status</label>
                      <select
                        value={orderFormData.status}
                        onChange={(e) => handleStatusChange('order', orderFormData.status, e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        <option value="pending">Pending</option>
                        <option value="submitted_to_manufacturer">Submitted to Manufacturer</option>
                        <option value="confirmed_by_manufacturer">Confirmed by Manufacturer</option>
                        <option value="rejected">Rejected</option>
                        <option value="canceled">Canceled</option>
                      </select>
                    </div>
                  )}

                  {/* Manufacturer Submission Button */}
                  {userRole === 'Admin' && orderFormData.status === 'pending' && onManufacturerSubmission && (
                    <div className="pt-2">
                      <Button
                        onClick={onManufacturerSubmission}
                        className="w-full"
                        variant="primary"
                        size="sm"
                      >
                        <Send className="h-4 w-4 mr-2" />
                        Submit to Manufacturer
                      </Button>
                    </div>
                  )}
                </div>

                {/* Order Form Files */}
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <h5 className="text-sm font-medium text-gray-700">Order Form Documents</h5>
                    {userRole === 'Admin' && (
                      <label className="cursor-pointer">
                        <input
                          type="file"
                          className="hidden"
                          accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                          onChange={(e) => handleFileUpload(e, 'order')}
                        />
                        <div className="flex items-center gap-1 text-blue-600 hover:text-blue-700 text-sm">
                          <Upload className="h-4 w-4" />
                          Add File
                        </div>
                      </label>
                    )}
                  </div>

                  {orderFormData.files && orderFormData.files.length > 0 ? (
                    <div className="space-y-2">
                      {orderFormData.files.map((file) => (
                        <div key={file.id} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                          <div className="flex items-center gap-2">
                            <FileText className="h-4 w-4 text-gray-500" />
                            <span className="text-sm text-gray-700">{file.name}</span>
                          </div>
                          <div className="flex items-center gap-1">
                            <button
                              onClick={() => window.open(file.url, '_blank')}
                              className="text-blue-600 hover:text-blue-700"
                            >
                              <Eye className="h-4 w-4" />
                            </button>
                            <button
                              onClick={() => {
                                const link = window.document.createElement('a');
                                link.href = file.url;
                                link.download = file.name;
                                link.click();
                              }}
                              className="text-green-600 hover:text-green-700"
                            >
                              <Download className="h-4 w-4" />
                            </button>
                            {userRole === 'Admin' && (
                              <button
                                onClick={() => removeFile(file.id, 'order')}
                                className="text-red-600 hover:text-red-700"
                              >
                                <Trash2 className="h-4 w-4" />
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-4 text-gray-500 text-sm">
                      No order form documents uploaded
                    </div>
                  )}
                </div>

                {/* Tracking Information for Confirmed Orders */}
                {orderFormData.status === 'confirmed_by_manufacturer' && (
                  <div className="space-y-3 p-4 bg-green-50 rounded-md">
                    <h5 className="text-sm font-medium text-green-800">Shipping Information</h5>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Carrier</label>
                        <input
                          type="text"
                          value={orderFormData.carrier || ''}
                          className="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                          placeholder="e.g., FedEx, UPS"
                          readOnly
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Tracking #</label>
                        <input
                          type="text"
                          value={orderFormData.trackingNumber || ''}
                          className="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                          placeholder="Tracking number"
                          readOnly
                        />
                      </div>
                    </div>
                  </div>
                )}

                {orderFormData.notes && (
                  <div className="p-3 bg-blue-50 rounded-md">
                    <p className="text-sm text-blue-800">{orderFormData.notes}</p>
                  </div>
                )}

                {orderFormData.rejectionReason && (
                  <div className="p-3 bg-red-50 rounded-md">
                    <p className="text-sm font-medium text-red-800 mb-1">Rejection Reason:</p>
                    <p className="text-sm text-red-700">{orderFormData.rejectionReason}</p>
                  </div>
                )}

                {orderFormData.cancellationReason && (
                  <div className="p-3 bg-red-50 rounded-md">
                    <p className="text-sm font-medium text-red-800 mb-1">Cancellation Reason:</p>
                    <p className="text-sm text-red-700">{orderFormData.cancellationReason}</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Status Update Modal */}
      <StatusUpdateModal
        isOpen={showStatusModal}
        onClose={() => setShowStatusModal(false)}
        onConfirm={handleStatusUpdate}
        type={modalType}
        currentStatus={modalCurrentStatus}
        newStatus={modalNewStatus}
        orderId={orderId.toString()}
      />

      {/* Document Viewer Panel */}
      <DocumentViewerPanel
        isOpen={showDocumentPanel}
        onClose={() => setShowDocumentPanel(false)}
        documentType={documentPanelType}
        orderId={orderId.toString()}
        title={documentPanelType === 'ivr' ? 'IVR Document' : 'Order Form Document'}
      />

      {/* Notifications handled by parent component */}
    </>
  );
};

export default IVRDocumentSection;
