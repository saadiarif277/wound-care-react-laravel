import React, { useState } from 'react';
import { X, AlertCircle, CheckCircle, Clock, Truck, Mail } from 'lucide-react';
import { Button } from '@/Components/Button';

interface ManufacturerSubmissionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (data: ManufacturerSubmissionData) => Promise<void>;
  orderId: string;
  orderNumber: string;
  manufacturerName: string;
}

interface ManufacturerSubmissionData {
  carrier: string;
  trackingNumber: string;
  shippingAddress: string;
  shippingContact: string;
  shippingPhone: string;
  shippingEmail: string;
  specialInstructions: string;
  sendNotification: boolean;
}

const ManufacturerSubmissionModal: React.FC<ManufacturerSubmissionModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  orderId,
  orderNumber,
  manufacturerName,
}) => {
  const [carrier, setCarrier] = useState('');
  const [trackingNumber, setTrackingNumber] = useState('');
  const [shippingAddress, setShippingAddress] = useState('');
  const [shippingContact, setShippingContact] = useState('');
  const [shippingPhone, setShippingPhone] = useState('');
  const [shippingEmail, setShippingEmail] = useState('');
  const [specialInstructions, setSpecialInstructions] = useState('');
  const [sendNotification, setSendNotification] = useState(true);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [emailStatus, setEmailStatus] = useState<'pending' | 'sent' | 'failed'>('pending');

  const handleConfirm = async () => {
    if (!carrier.trim() || !trackingNumber.trim()) {
      setError('Please provide both carrier and tracking number.');
      return;
    }

    setIsLoading(true);
    setError('');

    try {
      const submissionData: ManufacturerSubmissionData = {
        carrier: carrier.trim(),
        trackingNumber: trackingNumber.trim(),
        shippingAddress: shippingAddress.trim(),
        shippingContact: shippingContact.trim(),
        shippingPhone: shippingPhone.trim(),
        shippingEmail: shippingEmail.trim(),
        specialInstructions: specialInstructions.trim(),
        sendNotification,
      };

      await onConfirm(submissionData);

      // Simulate email sending status
      if (sendNotification) {
        setEmailStatus('sent');
      }

      handleClose();
    } catch (err) {
      setError('Failed to submit to manufacturer. Please try again.');
      if (sendNotification) {
        setEmailStatus('failed');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleClose = () => {
    setCarrier('');
    setTrackingNumber('');
    setShippingAddress('');
    setShippingContact('');
    setShippingPhone('');
    setShippingEmail('');
    setSpecialInstructions('');
    setSendNotification(true);
    setError('');
    setEmailStatus('pending');
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b">
          <div className="flex items-center gap-3">
            <Truck className="h-6 w-6 text-blue-600" />
            <div>
              <h2 className="text-xl font-semibold text-gray-900">
                Submit to Manufacturer
              </h2>
              <p className="text-sm text-gray-600">
                Order #{orderNumber} - {manufacturerName}
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
          {/* Shipping Information */}
          <div className="space-y-4">
            <h3 className="font-medium text-gray-900 flex items-center gap-2">
              <Truck className="h-5 w-5 text-blue-600" />
              Shipping Information
            </h3>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Carrier *
                </label>
                <input
                  type="text"
                  value={carrier}
                  onChange={(e) => setCarrier(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="e.g., FedEx, UPS, USPS"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tracking Number *
                </label>
                <input
                  type="text"
                  value={trackingNumber}
                  onChange={(e) => setTrackingNumber(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter tracking number"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Shipping Address
              </label>
              <textarea
                value={shippingAddress}
                onChange={(e) => setShippingAddress(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                rows={2}
                placeholder="Enter shipping address"
              />
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Contact Name
                </label>
                <input
                  type="text"
                  value={shippingContact}
                  onChange={(e) => setShippingContact(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Contact name"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Phone
                </label>
                <input
                  type="tel"
                  value={shippingPhone}
                  onChange={(e) => setShippingPhone(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Phone number"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email
                </label>
                <input
                  type="email"
                  value={shippingEmail}
                  onChange={(e) => setShippingEmail(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Email address"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Special Instructions
              </label>
              <textarea
                value={specialInstructions}
                onChange={(e) => setSpecialInstructions(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                rows={3}
                placeholder="Any special shipping instructions or notes..."
              />
            </div>
          </div>

          {/* Notification Options */}
          <div className="space-y-3">
            <h3 className="font-medium text-gray-900 flex items-center gap-2">
              <Mail className="h-5 w-5 text-green-600" />
              Email Notification
            </h3>

            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="sendNotification"
                checked={sendNotification}
                onChange={(e) => setSendNotification(e.target.checked)}
                className="rounded"
              />
              <label htmlFor="sendNotification" className="text-sm text-gray-700">
                Send email notification to provider and facility
              </label>
            </div>

            {sendNotification && (
              <div className="bg-blue-50 border border-blue-200 rounded-md p-3">
                <p className="text-sm text-blue-700">
                  Email will be sent to the provider and facility contacts with order submission details.
                </p>
              </div>
            )}
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-md p-3">
              <p className="text-sm text-red-600">{error}</p>
            </div>
          )}

          {/* Email Status */}
          {emailStatus !== 'pending' && (
            <div className={`border rounded-md p-3 ${
              emailStatus === 'sent'
                ? 'bg-green-50 border-green-200'
                : 'bg-red-50 border-red-200'
            }`}>
              <div className="flex items-center gap-2">
                {emailStatus === 'sent' ? (
                  <CheckCircle className="h-5 w-5 text-green-600" />
                ) : (
                  <AlertCircle className="h-5 w-5 text-red-600" />
                )}
                <p className={`text-sm ${
                  emailStatus === 'sent' ? 'text-green-700' : 'text-red-700'
                }`}>
                  {emailStatus === 'sent'
                    ? 'Email notification sent successfully!'
                    : 'Email notification failed to send. Please try again.'
                  }
                </p>
              </div>
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
            className="min-w-[120px]"
          >
            {isLoading ? 'Submitting...' : 'Submit to Manufacturer'}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default ManufacturerSubmissionModal;
