import React from 'react';
import { Truck, Package, CheckCircle, Clock } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';

interface TrackingInfoProps {
  trackingNumber?: string;
  carrier?: string;
  shippedAt?: string;
  deliveredAt?: string;
  orderStatus: string;
}

export default function TrackingInfo({
  trackingNumber,
  carrier,
  shippedAt,
  deliveredAt,
  orderStatus,
}: TrackingInfoProps) {
  const getCarrierUrl = (carrier: string, trackingNumber: string) => {
    const carrierUrls = {
      ups: `https://www.ups.com/track?loc=en_US&tracknum=${trackingNumber}`,
      fedex: `https://www.fedex.com/fedextrack/?trknbr=${trackingNumber}`,
      usps: `https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=${trackingNumber}`,
      dhl: `https://www.dhl.com/en/express/tracking.html?AWB=${trackingNumber}`,
    };
    
    return carrierUrls[carrier.toLowerCase() as keyof typeof carrierUrls] || '#';
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (!trackingNumber && orderStatus !== 'shipped' && orderStatus !== 'delivered') {
    return null;
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-medium text-gray-900 dark:text-white flex items-center">
          <Truck className="w-5 h-5 mr-2 text-gray-500" />
          Tracking Information
        </h3>
        {orderStatus === 'delivered' ? (
          <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
            <CheckCircle className="w-3 h-3 mr-1" />
            Delivered
          </Badge>
        ) : orderStatus === 'shipped' ? (
          <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
            <Package className="w-3 h-3 mr-1" />
            In Transit
          </Badge>
        ) : (
          <Badge className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <Clock className="w-3 h-3 mr-1" />
            Processing
          </Badge>
        )}
      </div>

      <div className="space-y-4">
        {trackingNumber && (
          <div>
            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
              Tracking Number
            </label>
            <div className="mt-1 flex items-center space-x-2">
              <span className="text-sm font-mono text-gray-900 dark:text-white">
                {trackingNumber}
              </span>
              {carrier && (
                <a
                  href={getCarrierUrl(carrier, trackingNumber)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
                >
                  Track on {carrier.toUpperCase()} →
                </a>
              )}
            </div>
          </div>
        )}

        {carrier && (
          <div>
            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
              Carrier
            </label>
            <p className="mt-1 text-sm text-gray-900 dark:text-white">
              {carrier.toUpperCase()}
            </p>
          </div>
        )}

        {shippedAt && (
          <div>
            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
              Shipped Date
            </label>
            <p className="mt-1 text-sm text-gray-900 dark:text-white">
              {formatDate(shippedAt)}
            </p>
          </div>
        )}

        {deliveredAt && (
          <div>
            <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
              Delivered Date
            </label>
            <p className="mt-1 text-sm text-gray-900 dark:text-white">
              {formatDate(deliveredAt)}
            </p>
          </div>
        )}

        {!trackingNumber && orderStatus === 'shipped' && (
          <div className="text-sm text-gray-500 dark:text-gray-400 italic">
            Tracking information will be available soon.
          </div>
        )}
      </div>
    </div>
  );
}