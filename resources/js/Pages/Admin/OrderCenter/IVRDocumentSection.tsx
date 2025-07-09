import React, { useState } from 'react';
import { ChevronDown, ChevronRight, FileText, Send, CheckCircle, Clock, AlertCircle, X, Download, Eye, Upload, Trash2, Loader2 } from 'lucide-react';
import { Button } from '@/Components/Button';
import StatusUpdateModal from './StatusUpdateModal';
import DocumentViewerPanel from '@/Components/DocumentViewerPanel';
import axios from 'axios';

interface IVRData {
  status: string;
  sentDate: string;
  resultsReceivedDate: string;
  verifiedDate: string;
  notes: string;
  resultsFileUrl: string;
  rejectionReason?: string;
  files: DocumentFile[];
  ivrDocumentUrl?: string;
  docusealSubmissionId?: string;
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
  const [isLoadingIVR, setIsLoadingIVR] = useState(false);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

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
    setIsUpdatingStatus(true);
    try {
      const statusType = modalType === 'ivr' ? 'ivr' : 'order';

      const statusMapping: Record<string, string> = {
        'Sent': 'sent',
        'Verified': 'verified',
        'Rejected': 'rejected',
        'Pending': 'pending',
        'N/A': 'n/a',
        'submitted_to_manufacturer': 'submitted_to_manufacturer',
        'confirmed_by_manufacturer': 'confirmed_by_manufacturer',
        'Canceled': 'canceled',
      };

      const status = statusMapping[data.status] || data.status.toLowerCase().replace(/ /g, '_');

      const response = await axios.post(`/admin/orders/${orderId}/change-status`, {
        status: status,
        status_type: statusType,
        notes: data.comments,
        rejection_reason: data.rejectionReason,
        send_notification: data.sendNotification,
        carrier: data.carrier,
        tracking_number: data.trackingNumber,
      });

      if (response.status === 200) {
        const processedData = {
          ...data,
          status: data.status,
          backendStatus: status,
        };

        if (modalType === 'ivr') {
          onUpdateIVRStatus(processedData);
        } else {
          onUpdateOrderFormStatus(processedData);
        }
      } else {
        const errorData = response.data;
        console.error('Status update failed:', errorData.error || `Failed to update ${statusType} status`);
      }
    } catch (error) {
      console.error('Status update error:', error);
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>, type: 'ivr' | 'order') => {
    const file = event.target.files?.[0];
    if (file) {
      if (type === 'ivr') {
        onUploadIVRResults(file);
      }
    }
  };

  const removeFile = (fileId: string, type: 'ivr' | 'order') => {
    console.log(`Removing file ${fileId} from ${type}`);
  };

  const handleViewDocument = (type: 'ivr' | 'order-form') => {
    setDocumentPanelType(type);
    setShowDocumentPanel(true);
  };

  const handleViewIVR = async () => {
    if (ivrData.docusealSubmissionId) {
      setIsLoadingIVR(true);
      try {
        const response = await fetch(`/admin/orders/${orderId}/docuseal-document`);
        const data = await response.json();

        if (data.success && data.document_url) {
          console.log('🔍 Opening Docuseal document URL:', data.document_url);
          window.open(data.document_url, '_blank');
        } else {
          console.error('❌ Failed to get document URL:', data.message);
          const fallbackUrl = `https://docuseal.com/e/${ivrData.docusealSubmissionId}`;
          console.log('🔍 Using fallback URL:', fallbackUrl);
          window.open(fallbackUrl, '_blank');
        }
      } catch (error) {
        console.error('❌ Error fetching document URL:', error);
        const fallbackUrl = `https://docuseal.com/e/${ivrData.docusealSubmissionId}`;
        console.log('🔍 Using fallback URL:', fallbackUrl);
        window.open(fallbackUrl, '_blank');
      } finally {
        setIsLoadingIVR(false);
      }
    } else if (ivrData.ivrDocumentUrl) {
      window.open(ivrData.ivrDocumentUrl, '_blank');
    } else {
      handleViewDocument('ivr');
    }
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
                  <div className="flex gap-2">
                    <Button
                      onClick={handleViewIVR}
                      className="flex-1"
                      variant="ghost"
                      size="sm"
                      disabled={isLoadingIVR}
                    >
                      {isLoadingIVR ? (
                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                      ) : (
                        <Eye className="h-4 w-4 mr-2" />
                      )}
                      {isLoadingIVR ? 'Loading...' : 'View IVR Form'}
                    </Button>
                  </div>

                  {userRole === 'Admin' && (
                    <div className="space-y-2">
                      <label className="block text-sm font-medium text-gray-700">IVR Status</label>
                      <select
                        value={ivrData.status}
                        onChange={(e) => handleStatusChange('ivr', ivrData.status, e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        disabled={isUpdatingStatus}
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

                {/* Disabled Order Status Message */}
                <div className="p-4 bg-gray-50 rounded-lg border border-gray-200">
                  <div className="flex items-center gap-3">
                    <div className="flex-shrink-0">
                      <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                        <Clock className="h-4 w-4 text-gray-500" />
                      </div>
                    </div>
                    <div className="flex-1">
                      <h5 className="text-sm font-medium text-gray-900 mb-1">Order Status Feature Coming Soon</h5>
                      <p className="text-sm text-gray-600">
                        The order status management feature is currently under development.
                        This section will be enabled once the feature is available.
                      </p>
                    </div>
                  </div>
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
                        disabled={isUpdatingStatus}
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
                        disabled={isUpdatingStatus}
                      >
                        {isUpdatingStatus ? (
                          <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                        ) : (
                          <Send className="h-4 w-4 mr-2" />
                        )}
                        {isUpdatingStatus ? 'Updating...' : 'Submit to Manufacturer'}
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
    </>
  );
};

export default IVRDocumentSection;
