import React, { useState } from 'react';
import { ChevronDown, ChevronRight, FileText, Send, CheckCircle, Clock, AlertCircle, X, MessageSquare, Mail, Download, Eye } from 'lucide-react';
import { Button } from '@/Components/Button';

interface IVRData {
  status: string;
  sentDate: string;
  resultsReceivedDate: string;
  verifiedDate: string;
  notes: string;
  resultsFileUrl: string;
  rejectionReason?: string;
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
}

interface IVRDocumentSectionProps {
  ivrData: IVRData;
  orderFormData: OrderFormData;
  orderId: string;
  onUpdateIVRStatus: (status: string, notes?: string, rejectionReason?: string) => void;
  onUploadIVRResults: (file: File) => void;
  onUpdateOrderFormStatus: (status: string, notes?: string, rejectionReason?: string, cancellationReason?: string) => void;
  isOpen?: boolean;
  onToggle?: () => void;
}

const IVRDocumentSection: React.FC<IVRDocumentSectionProps> = ({
  ivrData,
  orderFormData,
  orderId,
  onUpdateIVRStatus,
  onUploadIVRResults,
  onUpdateOrderFormStatus,
  isOpen = true,
  onToggle,
}) => {
  const [showIVRComment, setShowIVRComment] = useState(false);
  const [showOrderComment, setShowOrderComment] = useState(false);
  const [ivrComment, setIVRComment] = useState('');
  const [orderComment, setOrderComment] = useState('');
  const [ivrRejectionReason, setIVRRejectionReason] = useState('');
  const [orderRejectionReason, setOrderRejectionReason] = useState('');
  const [orderCancellationReason, setOrderCancellationReason] = useState('');
  const [trackingInfo, setTrackingInfo] = useState({
    carrier: '',
    trackingNumber: ''
  });

  const getStatusColor = (status: string) => {
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

  const getStatusIcon = (status: string) => {
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

  const handleIVRStatusUpdate = (status: string) => {
    if (status === 'Rejected' && !ivrRejectionReason) {
      alert('Please provide a rejection reason');
      return;
    }

    onUpdateIVRStatus(status, ivrComment, ivrRejectionReason);

    setShowIVRComment(false);
    setIVRComment('');
    setIVRRejectionReason('');
  };

  const handleOrderStatusUpdate = (status: string) => {
    if (status === 'Rejected' && !orderRejectionReason) {
      alert('Please provide a rejection reason');
      return;
    }

    if (status === 'Canceled' && !orderCancellationReason) {
      alert('Please provide a cancellation reason');
      return;
    }

    onUpdateOrderFormStatus(status, orderComment, orderRejectionReason, orderCancellationReason);

    setShowOrderComment(false);
    setOrderComment('');
    setOrderRejectionReason('');
    setOrderCancellationReason('');
  };

  return (
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
                    onClick={() => window.open('/admin/ivr/view/' + orderId, '_blank')}
                    className="flex-1"
                    variant="ghost"
                    size="sm"
                  >
                    <Eye className="h-4 w-4 mr-2" />
                    View IVR
                  </Button>
                  <Button
                    onClick={() => window.open('/admin/ivr/download/' + orderId, '_blank')}
                    className="flex-1"
                    variant="ghost"
                    size="sm"
                  >
                    <Download className="h-4 w-4 mr-2" />
                    Download IVR
                  </Button>
                </div>

                <div className="space-y-2">
                  <label className="block text-sm font-medium text-gray-700">IVR Status</label>
                  <select
                    value={ivrData.status}
                    onChange={(e) => {
                      const status = e.target.value;
                      if (status === 'Rejected') {
                        setShowIVRComment(true);
                      } else {
                        handleIVRStatusUpdate(status);
                      }
                    }}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="N/A">N/A</option>
                    <option value="Pending">Pending</option>
                    <option value="Sent">Sent</option>
                    <option value="Verified">Verified</option>
                    <option value="Rejected">Rejected</option>
                  </select>
                </div>

                {showIVRComment && (
                  <div className="space-y-3 p-4 bg-gray-50 rounded-md">
                    <div className="flex items-center justify-between">
                      <h5 className="text-sm font-medium">Add Comment & Notification</h5>
                      <button
                        onClick={() => setShowIVRComment(false)}
                        className="text-gray-400 hover:text-gray-600"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>

                    {ivrData.status === 'Rejected' && (
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Rejection Reason *
                        </label>
                        <textarea
                          value={ivrRejectionReason}
                          onChange={(e) => setIVRRejectionReason(e.target.value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          rows={2}
                          placeholder="Please provide rejection reason..."
                        />
                      </div>
                    )}

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Comments (Optional)
                      </label>
                      <textarea
                        value={ivrComment}
                        onChange={(e) => setIVRComment(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        rows={2}
                        placeholder="Add comments for the requestor..."
                      />
                    </div>

                    <div className="flex gap-2">
                      <Button
                        onClick={() => handleIVRStatusUpdate(ivrData.status)}
                        className="flex-1"
                        size="sm"
                      >
                        Update Status
                      </Button>
                      <Button
                        onClick={() => setShowIVRComment(false)}
                        variant="ghost"
                        size="sm"
                      >
                        Cancel
                      </Button>
                    </div>
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
                    onClick={() => window.open('/admin/order-form/download/' + orderId, '_blank')}
                    className="flex-1"
                    variant="ghost"
                    size="sm"
                  >
                    <Eye className="h-4 w-4 mr-2" />
                    View Order Form
                  </Button>
                  <Button
                    onClick={() => window.open('/admin/order-form/download/' + orderId, '_blank')}
                    className="flex-1"
                    variant="ghost"
                    size="sm"
                  >
                    <Download className="h-4 w-4 mr-2" />
                    Download
                  </Button>
                </div>

                <div className="space-y-2">
                  <label className="block text-sm font-medium text-gray-700">Order Form Status</label>
                  <select
                    value={orderFormData.status}
                    onChange={(e) => {
                      const status = e.target.value;
                      if (status === 'Rejected' || status === 'Canceled') {
                        setShowOrderComment(true);
                      } else {
                        handleOrderStatusUpdate(status);
                      }
                    }}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="Pending">Pending</option>
                    <option value="Submitted to Manufacturer">Submitted to Manufacturer</option>
                    <option value="Confirmed by Manufacturer">Confirmed by Manufacturer</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Canceled">Canceled</option>
                  </select>
                </div>

                {showOrderComment && (
                  <div className="space-y-3 p-4 bg-gray-50 rounded-md">
                    <div className="flex items-center justify-between">
                      <h5 className="text-sm font-medium">Add Comment & Notification</h5>
                      <button
                        onClick={() => setShowOrderComment(false)}
                        className="text-gray-400 hover:text-gray-600"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>

                    {orderFormData.status === 'Rejected' && (
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Rejection Reason *
                        </label>
                        <textarea
                          value={orderRejectionReason}
                          onChange={(e) => setOrderRejectionReason(e.target.value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          rows={2}
                          placeholder="Please provide rejection reason..."
                        />
                      </div>
                    )}

                    {orderFormData.status === 'Canceled' && (
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Cancellation Reason *
                        </label>
                        <textarea
                          value={orderCancellationReason}
                          onChange={(e) => setOrderCancellationReason(e.target.value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          rows={2}
                          placeholder="Please provide cancellation reason..."
                        />
                      </div>
                    )}

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Comments (Optional)
                      </label>
                      <textarea
                        value={orderComment}
                        onChange={(e) => setOrderComment(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        rows={2}
                        placeholder="Add comments for the requestor..."
                      />
                    </div>

                    <div className="flex gap-2">
                      <Button
                        onClick={() => handleOrderStatusUpdate(orderFormData.status)}
                        className="flex-1"
                        size="sm"
                      >
                        Update Status
                      </Button>
                      <Button
                        onClick={() => setShowOrderComment(false)}
                        variant="ghost"
                        size="sm"
                      >
                        Cancel
                      </Button>
                    </div>
                  </div>
                )}
              </div>

              {/* Tracking Information for Confirmed Orders */}
              {orderFormData.status === 'Confirmed by Manufacturer' && (
                <div className="space-y-3 p-4 bg-green-50 rounded-md">
                  <h5 className="text-sm font-medium text-green-800">Shipping Information</h5>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Carrier</label>
                      <input
                        type="text"
                        value={trackingInfo.carrier}
                        onChange={(e) => setTrackingInfo(prev => ({ ...prev, carrier: e.target.value }))}
                        className="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="e.g., FedEx, UPS"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-700 mb-1">Tracking #</label>
                      <input
                        type="text"
                        value={trackingInfo.trackingNumber}
                        onChange={(e) => setTrackingInfo(prev => ({ ...prev, trackingNumber: e.target.value }))}
                        className="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="Tracking number"
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
  );
};

export default IVRDocumentSection;
