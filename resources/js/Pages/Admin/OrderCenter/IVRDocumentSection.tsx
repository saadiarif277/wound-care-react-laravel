import React, { useState } from 'react';
import { ChevronDown, ChevronRight, FileText, Send, CheckCircle, Clock, AlertCircle, X, Download, Eye, Upload, Trash2, Loader2, Edit } from 'lucide-react';
import { Button } from '@/Components/Button';
import StatusUpdateModal from './StatusUpdateModal';
import DocumentViewerPanel from '@/Components/DocumentViewerPanel';
import { OrderFormModal } from '@/Components/OrderForm/OrderFormModal';
import { OrderFormEmbed } from '@/Components/OrderForm/OrderFormEmbed';
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
  alteredIvrFile?: {
    name: string;
    url: string;
    uploadedAt: string;
    uploadedBy: string;
  };
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
  shippingInfo?: {
    carrier: string;
    tracking_number: string;
    submitted_at: string;
    submitted_by: string;
  };
  files: DocumentFile[];
  alteredOrderFormFile?: {
    name: string;
    url: string;
    uploadedAt: string;
    uploadedBy: string;
  };
  order_form_submission_id?: string; // Added for existing submission
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
  orderData?: {
    order_number?: string;
    manufacturer_name?: string;
    manufacturer_id?: number;
    patient_name?: string;
    patient_email?: string;
    integration_email?: string;
    episode_id?: number;
    product_id?: number;
    product_request_id?: number;
    clinical_summary?: any;
  };
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
  orderData = {}
}) => {
  // Debug logging to see what data is being received
  console.log('🔍 IVRDocumentSection debug data:', {
    ivrData,
    orderFormData,
    orderId,
    userRole,
    orderData,
    hasOrderFormSubmissionId: !!orderFormData.order_form_submission_id,
    orderFormSubmissionId: orderFormData.order_form_submission_id
  });
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [modalType, setModalType] = useState<'ivr' | 'order'>('ivr');
  const [modalCurrentStatus, setModalCurrentStatus] = useState('');
  const [modalNewStatus, setModalNewStatus] = useState('');
  const [showDocumentPanel, setShowDocumentPanel] = useState(false);
  const [documentPanelType, setDocumentPanelType] = useState<'ivr' | 'order-form'>('ivr');
  const [isLoadingIVR, setIsLoadingIVR] = useState(false);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const [showSignerUrls, setShowSignerUrls] = useState(false);
  const [signerUrls, setSignerUrls] = useState<any[]>([]);
  const [isLoadingSignerUrls, setIsLoadingSignerUrls] = useState(false);
  const [isUploadingFile, setIsUploadingFile] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [showOrderFormModal, setShowOrderFormModal] = useState(false);
  const [showOrderFormEmbed, setShowOrderFormEmbed] = useState(false);
  const [productManufacturerId, setProductManufacturerId] = useState<string | null>(null);
  const [showShippingEditModal, setShowShippingEditModal] = useState(false);

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

      // Create FormData to handle file uploads
      const formData = new FormData();
      formData.append('status', status);
      formData.append('status_type', statusType);
      formData.append('notes', data.comments || '');
      formData.append('rejection_reason', data.rejectionReason || '');
      formData.append('cancellation_reason', data.cancellationReason || '');
      formData.append('send_notification', data.sendNotification ? '1' : '0');
      formData.append('carrier', data.carrier || '');
      formData.append('tracking_number', data.trackingNumber || '');

      // Add status documents if any
      if (data.statusDocuments && data.statusDocuments.length > 0) {
        data.statusDocuments.forEach((file: File, index: number) => {
          formData.append(`status_documents[${index}]`, file);
        });
      }

      // Add notification documents if any
      if (data.notificationDocuments && data.notificationDocuments.length > 0) {
        data.notificationDocuments.forEach((file: File, index: number) => {
          formData.append(`notification_documents[${index}]`, file);
        });
      }

      const response = await axios.post(`/admin/orders/${orderId}/change-status`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
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

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>, type: 'ivr' | 'order') => {
    const file = event.target.files?.[0];
    if (!file) return;

    setIsUploadingFile(true);
    setUploadProgress(0);

    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('file_type', type === 'ivr' ? 'ivr' : 'order_form');

      const response = await axios.post(`/admin/orders/${orderId}/upload-file`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            setUploadProgress(progress);
          }
        },
      });

      if (response.data.success) {
        // Refresh the page to show the uploaded file
        window.location.reload();
      } else {
        console.error('Upload failed:', response.data.message);
        alert('Upload failed: ' + response.data.message);
      }
    } catch (error) {
      console.error('Upload error:', error);
      alert('Upload failed. Please try again.');
    } finally {
      setIsUploadingFile(false);
      setUploadProgress(0);
      // Reset the file input
      event.target.value = '';
    }
  };

  const removeFile = async (type: 'ivr' | 'order') => {
    if (!confirm('Are you sure you want to remove this file?')) return;

    try {
      const response = await axios.delete(`/admin/orders/${orderId}/remove-file`, {
        data: {
          file_type: type === 'ivr' ? 'ivr' : 'order_form',
        },
      });

      if (response.data.success) {
        // Refresh the page to reflect the change
        window.location.reload();
      } else {
        console.error('Remove failed:', response.data.message);
        alert('Remove failed: ' + response.data.message);
      }
    } catch (error) {
      console.error('Remove error:', error);
      alert('Remove failed. Please try again.');
    }
  };

  const handleViewDocument = (type: 'ivr' | 'order-form') => {
    setDocumentPanelType(type);
    setShowDocumentPanel(true);
  };

    const handleViewAllSigners = async () => {
    if (!ivrData.docusealSubmissionId) return;

    setIsLoadingSignerUrls(true);
    try {
      // Use the controller method to get submission slugs
      const response = await fetch(`/api/v1/admin/docuseal/submissions/${ivrData.docusealSubmissionId}/slugs`);
      const data = await response.json();

      if (data.success && data.slugs) {
        setSignerUrls(data.slugs);
        setShowSignerUrls(true);
      } else {
        console.error('❌ Failed to get signer URLs:', data.message);
      }
    } catch (error) {
      console.error('❌ Error fetching signer URLs:', error);
    } finally {
      setIsLoadingSignerUrls(false);
    }
  };

    const handleViewIVR = async () => {
    // First, check if there's an uploaded IVR file
    if (ivrData.alteredIvrFile?.url) {
      window.open(ivrData.alteredIvrFile.url, '_blank');
      return;
    }

    // Then check for Docuseal submission
    if (ivrData.docusealSubmissionId) {
      setIsLoadingIVR(true);
      try {
        // Use the new controller method to get the document URL
        const response = await fetch(`/admin/orders/${orderId}/docuseal-document-url`);
        const data = await response.json();

        if (data.success && data.document_url) {
          console.log('🔍 Opening Docuseal document URL:', data.document_url);
          window.open(data.document_url, '_blank');
        } else {
          console.error('❌ Failed to get document URL:', data.message);
          // Fallback to the old method
          const fallbackResponse = await fetch(`/admin/orders/${orderId}/docuseal-document`);
          const fallbackData = await fallbackResponse.json();

          if (fallbackData.success && fallbackData.document_url) {
            console.log('🔍 Opening Docuseal document URL:', fallbackData.document_url);
            window.open(fallbackData.document_url, '_blank');
          } else {
            const fallbackUrl = `https://docuseal.com/e/${ivrData.docusealSubmissionId}`;
            console.log('🔍 Using fallback URL:', fallbackUrl);
            window.open(fallbackUrl, '_blank');
          }
        }
      } catch (error) {
        console.error('❌ Error fetching document URL:', error);
        // Try the old fallback method
        try {
          const fallbackResponse = await fetch(`/admin/orders/${orderId}/docuseal-document`);
          const fallbackData = await fallbackResponse.json();

          if (fallbackData.success && fallbackData.document_url) {
            console.log('🔍 Opening Docuseal document URL:', fallbackData.document_url);
            window.open(fallbackData.document_url, '_blank');
          } else {
            const fallbackUrl = `https://docuseal.com/e/${ivrData.docusealSubmissionId}`;
            console.log('🔍 Using fallback URL:', fallbackUrl);
            window.open(fallbackUrl, '_blank');
          }
        } catch (fallbackError) {
          console.error('❌ Fallback method also failed:', fallbackError);
          const fallbackUrl = `https://docuseal.com/e/${ivrData.docusealSubmissionId}`;
          console.log('🔍 Using final fallback URL:', fallbackUrl);
          window.open(fallbackUrl, '_blank');
        }
      } finally {
        setIsLoadingIVR(false);
      }
    } else if (ivrData.ivrDocumentUrl) {
      window.open(ivrData.ivrDocumentUrl, '_blank');
    } else {
      handleViewDocument('ivr');
    }
  };

  const handleViewOrderForm = async () => {
    console.log('🔍 handleViewOrderForm called with:', {
      orderFormData,
      hasSubmissionId: !!orderFormData.order_form_submission_id,
      submissionId: orderFormData.order_form_submission_id
    });

    // Check if we have an existing order form submission
    if (orderFormData.order_form_submission_id) {
      console.log('🔍 Opening existing order form submission:', orderFormData.order_form_submission_id);

      // Try to open the existing submission directly (like IVR does)
      try {
        // Use the new controller method to get the document URL
        const response = await fetch(`/admin/orders/${orderId}/order-form-document-url`);
        const data = await response.json();

        if (data.success && data.document_url) {
          console.log('🔍 Opening Order Form document URL:', data.document_url);
          window.open(data.document_url, '_blank');
        } else {
          console.error('❌ Failed to get document URL:', data.message);
          // Fallback to direct DocuSeal URL
          const fallbackUrl = `https://docuseal.com/e/${orderFormData.order_form_submission_id}`;
          console.log('🔍 Using fallback URL for existing order form:', fallbackUrl);
          window.open(fallbackUrl, '_blank');
        }
      } catch (error) {
        console.error('❌ Error opening order form:', error);
        // Fallback to direct DocuSeal URL
        const fallbackUrl = `https://docuseal.com/e/${orderFormData.order_form_submission_id}`;
        console.log('🔍 Using fallback URL due to error:', fallbackUrl);
        window.open(fallbackUrl, '_blank');
      }
    } else {
      console.log('🔍 Opening modal for new order form');
      // Open modal for filling new order form
      setShowOrderFormEmbed(true);
    }
  };

  // Function to get manufacturer_id from product
  const getManufacturerIdFromProduct = async () => {
    console.log('🔍 getManufacturerIdFromProduct called with:', {
      product_id: orderData.product_id,
      orderData: orderData
    });

    if (!orderData.product_id) {
      console.error('❌ No product_id available in orderData');
      return null;
    }

    try {
      console.log('📡 Calling API for product:', orderData.product_id);
      const response = await axios.get(`/api/v1/products/${orderData.product_id}`);
      console.log('📡 API response:', response.data);

      if (response.data.success && response.data.product) {
        const manufacturerId = response.data.product.manufacturer_id;
        console.log('✅ Found manufacturer_id:', manufacturerId);
        setProductManufacturerId(manufacturerId?.toString() || null);
        return manufacturerId?.toString();
      } else {
        console.error('❌ API response not successful:', response.data);
      }
    } catch (error) {
      console.error('❌ Error fetching product manufacturer:', error);
    }
    return null;
  };

  // Get manufacturer_id when component mounts or product_id changes
  React.useEffect(() => {
    console.log('🔍 useEffect triggered:', {
      product_id: orderData.product_id,
      ivr_status: ivrData.status,
      ivr_verified: ivrData.status?.toLowerCase() === 'verified'
    });

    if (orderData.product_id && ivrData.status?.toLowerCase() === 'verified') {
      console.log('✅ Conditions met, calling getManufacturerIdFromProduct');
      getManufacturerIdFromProduct();
    } else {
      console.log('❌ Conditions not met for manufacturer lookup');
    }
  }, [orderData.product_id, ivrData.status]);

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
                  {ivrData.docusealSubmissionId ? (
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
                  ) : (
                    <div className="text-sm text-gray-500 bg-gray-50 p-3 rounded-md border">
                      IVR has not been submitted by the provider yet.
                    </div>
                  )}
                  {/* {ivrData.docusealSubmissionId && (
                      <Button
                        onClick={handleViewAllSigners}
                        className="flex-1"
                        variant="secondary"
                        size="sm"
                        disabled={isLoadingSignerUrls}
                      >
                        {isLoadingSignerUrls ? (
                          <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                        ) : (
                          <FileText className="h-4 w-4 mr-2" />
                        )}
                        {isLoadingSignerUrls ? 'Loading...' : 'All Signers'}
                      </Button>
                    )} */}
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
                        <div className="flex items-center justify-between text-blue-600 hover:text-blue-700 text-sm">
                          <Upload className="h-4 w-4" />
                          Add File
                        </div>
                      </label>
                    )}
                  </div>

                  {/* Show uploaded IVR file if available */}
                  {ivrData.alteredIvrFile ? (
                    <div className="space-y-2">
                      <div className="flex items-center justify-between p-2 bg-green-50 rounded border border-green-200">
                        <div className="flex items-center gap-2">
                          <FileText className="h-4 w-4 text-green-600" />
                          <div>
                            <span className="text-sm font-medium text-green-800">{ivrData.alteredIvrFile.name}</span>
                            <div className="text-xs text-green-600">
                              Uploaded by {ivrData.alteredIvrFile.uploadedBy} on {new Date(ivrData.alteredIvrFile.uploadedAt).toLocaleDateString()}
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-1">
                          <button
                            onClick={() => window.open(ivrData.alteredIvrFile!.url, '_blank')}
                            className="text-blue-600 hover:text-blue-700"
                          >
                            <Eye className="h-4 w-4" />
                          </button>
                          <button
                            onClick={() => {
                              const link = window.document.createElement('a');
                              link.href = ivrData.alteredIvrFile!.url;
                              link.download = ivrData.alteredIvrFile!.name;
                              link.click();
                            }}
                            className="text-green-600 hover:text-blue-700"
                          >
                            <Download className="h-4 w-4" />
                          </button>
                          {userRole === 'Admin' && (
                            <button
                              onClick={() => removeFile('ivr')}
                              className="text-red-600 hover:text-red-700"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  ) : ivrData.files && ivrData.files.length > 0 ? (
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
                              className="text-green-600 hover:text-blue-700"
                            >
                              <Download className="h-4 w-4" />
                            </button>
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
                  {orderFormData.order_form_submission_id ? (
                    <div className="flex gap-2">
                      <Button
                        onClick={handleViewOrderForm}
                        className="flex-1"
                        variant="ghost"
                        size="sm"
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        View Order Form
                      </Button>
                    </div>
                  ) : (
                    <div className="text-sm text-gray-500 bg-gray-50 p-3 rounded-md border">
                      Order Form has not been submitted by the provider yet.
                    </div>
                  )}

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

                  {/* Show uploaded order form file if available */}
                  {orderFormData.alteredOrderFormFile ? (
                    <div className="space-y-2">
                      <div className="flex items-center justify-between p-2 bg-green-50 rounded border border-green-200">
                        <div className="flex items-center gap-2">
                          <FileText className="h-4 w-4 text-green-600" />
                          <div>
                            <span className="text-sm font-medium text-green-800">{orderFormData.alteredOrderFormFile.name}</span>
                            <div className="text-xs text-green-600">
                              Uploaded by {orderFormData.alteredOrderFormFile.uploadedBy} on {new Date(orderFormData.alteredOrderFormFile.uploadedAt).toLocaleDateString()}
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-1">
                          <button
                            onClick={() => window.open(orderFormData.alteredOrderFormFile!.url, '_blank')}
                            className="text-blue-600 hover:text-blue-700"
                          >
                            <Eye className="h-4 w-4" />
                          </button>
                          <button
                            onClick={() => {
                              const link = window.document.createElement('a');
                              link.href = orderFormData.alteredOrderFormFile!.url;
                              link.download = orderFormData.alteredOrderFormFile!.name;
                              link.click();
                            }}
                            className="text-green-600 hover:text-blue-700"
                          >
                            <Download className="h-4 w-4" />
                          </button>
                          {userRole === 'Admin' && (
                            <button
                              onClick={() => removeFile('order')}
                              className="text-red-600 hover:text-red-700"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  ) : orderFormData.files && orderFormData.files.length > 0 ? (
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
                              className="text-green-600 hover:text-blue-700"
                            >
                              <Download className="h-4 w-4" />
                            </button>
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

                {/* Tracking Information for Confirmed Orders Only */}
                {orderFormData.status === 'confirmed_by_manufacturer' && (
                  <div className="space-y-3 p-4 bg-blue-50 rounded-md">
                    <div className="flex items-center justify-between">
                      <h5 className="text-sm font-medium text-blue-800">Shipping Information</h5>
                      {userRole === 'Admin' && (
                        <Button
                          onClick={() => setShowShippingEditModal(true)}
                          variant="ghost"
                          size="sm"
                          className="text-blue-600 hover:text-blue-700"
                        >
                          <Edit className="h-4 w-4 mr-1" />
                          Edit
                        </Button>
                      )}
                    </div>
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
                    {orderFormData.shippingInfo && (
                      <div className="text-xs text-gray-600">
                        <p>Submitted: {new Date(orderFormData.shippingInfo.submitted_at).toLocaleDateString()}</p>
                        <p>By: {orderFormData.shippingInfo.submitted_by}</p>
                      </div>
                    )}
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

      {/* Signer URLs Modal */}
      {showSignerUrls && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
          <div className="max-w-2xl w-full mx-4 p-6 rounded-lg shadow-xl bg-white">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Docuseal Signer URLs</h3>
              <button
                onClick={() => setShowSignerUrls(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="space-y-3">
              {signerUrls.map((signer, index) => (
                <div key={signer.id || index} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <span className="text-sm font-medium text-blue-600">
                          {signer.name ? signer.name.charAt(0).toUpperCase() : 'S'}
                        </span>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-900">
                          {signer.name || `Signer ${index + 1}`}
                        </p>
                        <p className="text-xs text-gray-500">{signer.email}</p>
                      </div>
                    </div>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      signer.status === 'completed' ? 'text-green-600 bg-green-100' :
                      signer.status === 'pending' ? 'text-yellow-600 bg-yellow-100' :
                      'text-gray-600 bg-gray-100'
                    }`}>
                      {signer.status}
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => window.open(signer.url, '_blank')}
                      className="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors"
                    >
                      <Eye className="h-4 w-4 mr-2 inline" />
                      Open Document
                    </button>
                    <button
                      onClick={() => {
                        navigator.clipboard.writeText(signer.url);
                        // You could add a toast notification here
                      }}
                      className="px-3 py-2 border border-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-50 transition-colors"
                    >
                      Copy URL
                    </button>
                  </div>

                  {signer.completed_at && (
                    <p className="text-xs text-gray-500 mt-2">
                      Completed: {new Date(signer.completed_at).toLocaleString()}
                    </p>
                  )}
                </div>
              ))}
            </div>

            <div className="flex justify-end mt-6">
              <button
                onClick={() => setShowSignerUrls(false)}
                className="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Order Form Embed */}
      {showOrderFormEmbed && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col">
            <div className="flex items-center justify-between p-6 border-b">
              <h2 className="text-xl font-semibold text-gray-900">
                {orderFormData.order_form_submission_id ? 'View Order Form' : 'Fill Order Form'}
              </h2>
              <button
                onClick={() => setShowOrderFormEmbed(false)}
                className="text-gray-400 hover:text-gray-600 transition-colors"
              >
                <X className="h-6 w-6" />
              </button>
            </div>
            <div className="flex-1 p-6 overflow-auto">
              {(() => {
                const finalManufacturerId = productManufacturerId || orderData.manufacturer_id?.toString() || '1';
                const hasExistingSubmission = orderFormData.order_form_submission_id;

                console.log('🔍 OrderFormEmbed debug:', {
                  productManufacturerId,
                  orderData_manufacturer_id: orderData.manufacturer_id,
                  finalManufacturerId,
                  orderData: orderData,
                  hasExistingSubmission,
                  existingSubmissionId: orderFormData.order_form_submission_id
                });

                return (
                  <OrderFormEmbed
                    manufacturerId={finalManufacturerId}
                    productCode=""
                    formData={{
                      order_number: orderData.order_number || `Order #${orderId}`,
                      manufacturer_name: orderData.manufacturer_name || 'Manufacturer',
                      patient_name: orderData.patient_name || 'Patient Name',
                      patient_email: orderData.patient_email || 'patient@example.com',
                      integration_email: orderData.integration_email || 'integration@example.com',
                      episode_id: orderData.episode_id
                    }}
                    episodeId={orderData.episode_id}
                    productRequestId={orderId}
                    existingSubmissionId={hasExistingSubmission ? orderFormData.order_form_submission_id : undefined}
                    onComplete={(data) => {
                      console.log('Order form completed:', data);
                      // Don't close the modal automatically - let user close it manually
                      // setShowOrderFormEmbed(false);
                      // You can add additional logic here to update the order form status
                    }}
                    onError={(error) => {
                      console.error('Order form error:', error);
                      // You can add error handling logic here
                    }}
                    onOrderFormSubmit={(submissionId) => {
                      console.log('Order form submitted with ID:', submissionId);
                      // You can add logic here to update the order form status
                    }}
                    className="h-full"
                    debug={true}
                  />
                );
              })()}
            </div>
          </div>
        </div>
      )}

      {/* Order Form Modal */}
      <OrderFormModal
        isOpen={showOrderFormModal}
        onClose={() => setShowOrderFormModal(false)}
        orderId={orderId.toString()}
        orderData={{
          id: orderId.toString(),
          order_number: orderData.order_number || `Order #${orderId}`,
          manufacturer_name: orderData.manufacturer_name || 'Manufacturer',
          manufacturer_id: orderData.manufacturer_id || 1,
          patient_name: orderData.patient_name || 'Patient Name',
          patient_email: orderData.patient_email || 'patient@example.com',
          integration_email: orderData.integration_email || 'integration@example.com',
          episode_id: orderData.episode_id || 1,
          ivr_status: ivrData.status,
          order_form_status: orderFormData.status,
          docuseal_submission_id: ivrData.docusealSubmissionId,
          order_form_submission_id: orderFormData.fileUrl,
          product_request_id: orderId
        }}
        onOrderFormComplete={(data) => {
          console.log('Order form completed:', data);
          setShowOrderFormModal(false);
          // You can add additional logic here to update the order form status
        }}
      />

      {/* Shipping Edit Modal */}
      {showShippingEditModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div className="flex items-center justify-between p-6 border-b">
              <h2 className="text-xl font-semibold text-gray-900">Edit Shipping Information</h2>
              <button
                onClick={() => setShowShippingEditModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="h-6 w-6" />
              </button>
            </div>

            <div className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Carrier *</label>
                <input
                  type="text"
                  id="editCarrier"
                  defaultValue={orderFormData.carrier || ''}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="e.g., FedEx, UPS"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Tracking Number *</label>
                <input
                  type="text"
                  id="editTrackingNumber"
                  defaultValue={orderFormData.trackingNumber || ''}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Tracking number"
                />
              </div>
            </div>

            <div className="flex items-center justify-end gap-3 p-6 border-t">
              <Button
                onClick={() => setShowShippingEditModal(false)}
                variant="ghost"
              >
                Cancel
              </Button>
              <Button
                onClick={async () => {
                  const carrier = (document.getElementById('editCarrier') as HTMLInputElement)?.value;
                  const trackingNumber = (document.getElementById('editTrackingNumber') as HTMLInputElement)?.value;

                  if (!carrier || !trackingNumber) {
                    alert('Please provide both carrier and tracking number');
                    return;
                  }

                  try {
                    const response = await axios.post(`/admin/orders/${orderId}/change-status`, {
                      status: 'confirmed_by_manufacturer',
                      status_type: 'order',
                      carrier: carrier,
                      tracking_number: trackingNumber,
                      notes: 'Shipping information updated',
                      send_notification: false,
                    });

                    if (response.status === 200) {
                      setShowShippingEditModal(false);
                      // Refresh the page to show updated information
                      window.location.reload();
                    }
                  } catch (error) {
                    console.error('Failed to update shipping info:', error);
                    alert('Failed to update shipping information. Please try again.');
                  }
                }}
              >
                Update Shipping
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default IVRDocumentSection;
