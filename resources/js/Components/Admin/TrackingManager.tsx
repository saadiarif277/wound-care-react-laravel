import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
  Truck,
  Package,
  Clock,
  CheckCircle,
  AlertCircle,
  QrCode,
  Globe
} from 'lucide-react';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface TrackingManagerProps {
  order?: any;
  episode?: any;
  existingTracking?: {
    tracking_number?: string;
    carrier?: string;
    shipped_at?: string;
    estimated_delivery?: string;
  };
}

const carriers = [
  { value: 'ups', label: 'UPS', trackingUrl: 'https://www.ups.com/track?tracknum=' },
  { value: 'fedex', label: 'FedEx', trackingUrl: 'https://www.fedex.com/fedextrack/?tracknumbers=' },
  { value: 'usps', label: 'USPS', trackingUrl: 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=' },
  { value: 'dhl', label: 'DHL', trackingUrl: 'https://www.dhl.com/en/express/tracking.html?AWB=' },
  { value: 'ontrac', label: 'OnTrac', trackingUrl: 'https://www.ontrac.com/tracking?number=' },
  { value: 'other', label: 'Other', trackingUrl: null }
];

const TrackingManager: React.FC<TrackingManagerProps> = ({
  order,
  episode,
  existingTracking
}) => {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [trackingNumber, setTrackingNumber] = useState(existingTracking?.tracking_number || '');
  const [carrier, setCarrier] = useState(existingTracking?.carrier || 'ups');
  const [estimatedDelivery, setEstimatedDelivery] = useState(existingTracking?.estimated_delivery || '');
  const [isUpdating, setIsUpdating] = useState(false);
  const [error, setError] = useState('');

  const canUpdate = episode
    ? episode.status === 'sent_to_manufacturer'
    : order?.order_status === 'submitted_to_manufacturer';

  const hasTracking = existingTracking?.tracking_number;

  const handleUpdateTracking = () => {
    if (!trackingNumber.trim()) {
      setError('Please enter a tracking number');
      return;
    }

    setIsUpdating(true);
    setError('');

    const endpoint = episode
      ? route('admin.episodes.update-tracking', episode.id)
      : route('admin.orders.update-tracking', order.id);

    router.post(endpoint, {
      tracking_number: trackingNumber,
      carrier: carrier,
      estimated_delivery: estimatedDelivery || null
    }, {
      onSuccess: () => {
        setIsUpdating(false);
      },
      onError: () => {
        setIsUpdating(false);
        setError('Failed to update tracking information. Please try again.');
      }
    });
  };

  const getTrackingUrl = () => {
    const carrierInfo = carriers.find(c => c.value === (existingTracking?.carrier || carrier));
    if (carrierInfo?.trackingUrl && (existingTracking?.tracking_number || trackingNumber)) {
      return carrierInfo.trackingUrl + (existingTracking?.tracking_number || trackingNumber);
    }
    return null;
  };

  if (!canUpdate && !hasTracking) {
    return null;
  }

  return (
    <div className={`${t.glass.card} ${t.glass.border} rounded-xl p-6`}>
      <div className="flex items-center justify-between mb-6">
        <h3 className={`text-lg font-semibold ${t.text.primary} flex items-center gap-2`}>
          <Truck className="w-5 h-5 text-indigo-500" />
          Tracking Information
        </h3>
        {hasTracking && (
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
            <span className="text-xs text-green-600 dark:text-green-400">Tracking Active</span>
          </div>
          )}
        </div>

      {hasTracking ? (
        // Display existing tracking info
        <div className="space-y-4">
          <div className={`${t.glass.base} rounded-lg p-4`}>
            <div className="flex items-start justify-between">
              <div className="space-y-3 flex-1">
                <div>
                  <p className={`text-sm font-medium ${t.text.secondary}`}>Tracking Number</p>
                  <p className={`text-lg font-mono ${t.text.primary}`}>{existingTracking.tracking_number}</p>
        </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className={`text-sm font-medium ${t.text.secondary}`}>Carrier</p>
                    <p className={`${t.text.primary}`}>
                      {carriers.find(c => c.value === existingTracking.carrier)?.label || existingTracking.carrier}
                    </p>
      </div>

                  {existingTracking.shipped_at && (
                    <div>
                      <p className={`text-sm font-medium ${t.text.secondary}`}>Shipped Date</p>
                      <p className={`${t.text.primary}`}>
                        {new Date(existingTracking.shipped_at).toLocaleDateString()}
                      </p>
            </div>
                  )}
          </div>

                {existingTracking.estimated_delivery && (
                  <div>
                    <p className={`text-sm font-medium ${t.text.secondary}`}>Estimated Delivery</p>
                    <p className={`${t.text.primary}`}>
                      {new Date(existingTracking.estimated_delivery).toLocaleDateString()}
                    </p>
                  </div>
                )}
              </div>

              {/* QR Code placeholder */}
              <div className={`${t.glass.base} rounded-lg p-3 ml-4`}>
                <QrCode className="w-16 h-16 text-gray-400" />
          </div>
        </div>
      </div>

          {/* Track Package Button */}
          {getTrackingUrl() && (
            <a
              href={getTrackingUrl() || '#'}
              target="_blank"
              rel="noopener noreferrer"
              className={`w-full flex items-center justify-center gap-2 px-4 py-2 ${t.button.ghost.base} rounded-lg`}
            >
              <Globe className="w-4 h-4" />
              Track Package
            </a>
          )}

          {/* Update Tracking (if allowed) */}
          {canUpdate && (
            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
              <p className={`text-sm ${t.text.secondary} mb-3`}>Need to update tracking information?</p>
              <button
                onClick={() => {
                  setTrackingNumber(existingTracking.tracking_number || '');
                  setCarrier(existingTracking.carrier || 'ups');
                  setEstimatedDelivery(existingTracking.estimated_delivery || '');
                }}
                className={`text-sm ${t.button.ghost} rounded-lg px-3 py-1.5`}
              >
                Edit Tracking
              </button>
            </div>
          )}
        </div>
      ) : (
        // Add tracking form
        <div className="space-y-4">
          <div>
            <label className={`block text-sm font-medium ${t.text.primary} mb-2`}>
              Tracking Number
            </label>
            <input
              type="text"
              value={trackingNumber}
              onChange={(e) => {
                setTrackingNumber(e.target.value);
                setError('');
              }}
              placeholder="Enter tracking number..."
              className={`w-full px-3 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            />
          </div>

          <div>
            <label className={`block text-sm font-medium ${t.text.primary} mb-2`}>
              Carrier
            </label>
            <select
              value={carrier}
              onChange={(e) => setCarrier(e.target.value)}
              className={`w-full px-3 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            >
              {carriers.map(c => (
                <option key={c.value} value={c.value}>{c.label}</option>
              ))}
            </select>
          </div>

          <div>
            <label className={`block text-sm font-medium ${t.text.primary} mb-2`}>
              Estimated Delivery Date (Optional)
            </label>
            <input
              type="date"
              value={estimatedDelivery}
              onChange={(e) => setEstimatedDelivery(e.target.value)}
              min={new Date().toISOString().split('T')[0]}
              className={`w-full px-3 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            />
          </div>

          {/* Error Message */}
          {error && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="flex items-center gap-2 text-red-500 text-sm"
            >
              <AlertCircle className="w-4 h-4" />
              {error}
        </motion.div>
          )}

          {/* Status Messages */}
          <div className={`${t.glass.base} rounded-lg p-4`}>
            <div className="flex items-center gap-2 mb-2">
              <CheckCircle className="w-4 h-4 text-green-500" />
              <span className={`text-sm ${t.text.secondary}`}>
                Order has been sent to manufacturer
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Clock className="w-4 h-4 text-blue-500" />
              <span className={`text-sm ${t.text.secondary}`}>
                Awaiting shipment tracking
              </span>
            </div>
          </div>

          {/* Update Button */}
                  <button
            onClick={handleUpdateTracking}
            disabled={isUpdating || !trackingNumber.trim()}
            className={`w-full flex items-center justify-center gap-2 px-4 py-3 ${
              isUpdating || !trackingNumber.trim()
                ? 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed'
                : t.button.primary
            } rounded-lg font-medium transition-all`}
          >
            {isUpdating ? (
              <>
                <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
                Updating...
              </>
            ) : (
              <>
                <Package className="w-5 h-5" />
                Add Tracking Information
              </>
            )}
          </button>
        </div>
      )}
    </div>
  );
};

export default TrackingManager;
