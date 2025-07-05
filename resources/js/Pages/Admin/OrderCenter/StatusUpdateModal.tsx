import React, { useState } from 'react';
import { X, AlertCircle, CheckCircle, Clock } from 'lucide-react';
import { Button } from '@/Components/Button';

interface StatusUpdateModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (data: StatusUpdateData) => Promise<void>;
  type: 'ivr' | 'order';
  currentStatus: string;
  newStatus: string;
  orderId: string;
}

interface StatusUpdateData {
  status: string;
  comments: string;
  rejectionReason?: string;
  cancellationReason?: string;
  sendNotification: boolean;
  carrier?: string;
  trackingNumber?: string;
}

const StatusUpdateModal: React.FC<StatusUpdateModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  type,
  currentStatus,
  newStatus,
  orderId,
}) => {
  const [comments, setComments] = useState('');
  const [rejectionReason, setRejectionReason] = useState('');
  const [cancellationReason, setCancellationReason] = useState('');
  const [sendNotification, setSendNotification] = useState(true);
  const [carrier, setCarrier] = useState('');
  const [trackingNumber, setTrackingNumber] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const getStatusIcon = (status: string | null | undefined) => {
    if (!status || typeof status !== 'string') {
      return <Clock className="h-5 w-5 text-gray-500" />;
    }

    switch (status.toLowerCase()) {
      case 'completed':
      case 'approved':
      case 'verified':
      case 'confirmed by manufacturer':
        return <CheckCircle className="h-5 w-5 text-green-500" />;
      case 'pending':
      case 'draft':
      case 'n/a':
        return <Clock className="h-5 w-5 text-yellow-500" />;
      case 'sent':
      case 'submitted to manufacturer':
        return <Clock className="h-5 w-5 text-blue-500" />;
      case 'rejected':
      case 'canceled':
        return <AlertCircle className="h-5 w-5 text-red-500" />;
      default:
        return <Clock className="h-5 w-5 text-gray-500" />;
    }
  };

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



  const handleConfirm = async () => {
    if ((newStatus === 'Rejected' && !rejectionReason) ||
        (newStatus === 'Canceled' && !cancellationReason)) {
      setError('Please provide a reason for this status change.');
      return;
    }

    setIsLoading(true);
    setError('');

    try {
      const updateData: StatusUpdateData = {
        status: newStatus,
        comments,
        rejectionReason: newStatus === 'Rejected' ? rejectionReason : undefined,
        cancellationReason: newStatus === 'Canceled' ? cancellationReason : undefined,
        sendNotification,
        carrier: (newStatus === 'Submitted to Manufacturer' || newStatus === 'Confirmed by Manufacturer') ? carrier : undefined,
        trackingNumber: (newStatus === 'Submitted to Manufacturer' || newStatus === 'Confirmed by Manufacturer') ? trackingNumber : undefined,
      };

      await onConfirm(updateData);
      handleClose();
    } catch (err) {
      setError('Failed to update status. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleClose = () => {
    setComments('');
    setRejectionReason('');
    setCancellationReason('');
    setSendNotification(true);
    setCarrier('');
    setTrackingNumber('');
    setError('');
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b">
          <div className="flex items-center gap-3">
            {getStatusIcon(newStatus)}
            <div>
              <h2 className="text-xl font-semibold text-gray-900">
                Update {type.toUpperCase()} Status
              </h2>
              <p className="text-sm text-gray-600">
                Order #{orderId} - {type.toUpperCase()}
              </p>
            </div>
          </div>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Status Change Confirmation */}
          <div className="bg-gray-50 p-4 rounded-lg">
            <h3 className="font-medium text-gray-900 mb-2">Status Change</h3>
            <div className="flex items-center gap-3">
              <span className="text-sm text-gray-600">From:</span>
              <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(currentStatus)}`}>
                {currentStatus}
              </span>
              <span className="text-sm text-gray-600">→</span>
              <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(newStatus)}`}>
                {newStatus}
              </span>
            </div>
          </div>

          {/* Required Fields */}
          {(newStatus === 'Rejected' || newStatus === 'Canceled') && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                {newStatus === 'Rejected' ? 'Rejection' : 'Cancellation'} Reason *
              </label>
              <textarea
                value={newStatus === 'Rejected' ? rejectionReason : cancellationReason}
                onChange={(e) => newStatus === 'Rejected' ? setRejectionReason(e.target.value) : setCancellationReason(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                rows={3}
                placeholder={`Please provide ${newStatus.toLowerCase()} reason...`}
              />
            </div>
          )}

          {/* Comments */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Comments (Optional)
            </label>
            <textarea
              value={comments}
              onChange={(e) => setComments(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              rows={3}
              placeholder="Add comments for the requestor..."
            />
          </div>

          {/* Shipping Information for Manufacturer Submission and Confirmed Orders */}
          {(newStatus === 'Submitted to Manufacturer' || newStatus === 'Confirmed by Manufacturer') && (
            <div className="space-y-3">
              <h3 className="font-medium text-gray-900">Shipping Information</h3>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Carrier</label>
                  <input
                    type="text"
                    value={carrier}
                    onChange={(e) => setCarrier(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="e.g., FedEx, UPS"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                  <input
                    type="text"
                    value={trackingNumber}
                    onChange={(e) => setTrackingNumber(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Tracking number"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Notification Options */}
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="sendNotification"
              checked={sendNotification}
              onChange={(e) => setSendNotification(e.target.checked)}
              className="rounded"
            />
            <label htmlFor="sendNotification" className="text-sm text-gray-700">
              Send notification to Provider/OM
            </label>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-md p-3">
              <p className="text-sm text-red-600">{error}</p>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 p-6 border-t">
          <Button
            onClick={handleClose}
            variant="ghost"
            disabled={isLoading}
          >
            Cancel
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={isLoading}
            className="min-w-[100px]"
          >
            {isLoading ? 'Updating...' : 'Update Status'}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default StatusUpdateModal;
