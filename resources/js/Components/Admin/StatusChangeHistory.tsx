import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  History,
  Clock,
  User,
  AlertCircle,
  CheckCircle,
  XCircle,
  ArrowRight,
  RefreshCw,
  Info,
} from 'lucide-react';

interface StatusChangeHistoryProps {
  orderId: string;
  className?: string;
}

interface StatusChange {
  id: number;
  previous_status: string | null;
  new_status: string;
  changed_by: string;
  notes: string | null;
  created_at: string;
  is_significant: boolean;
  description: string;
}

const StatusChangeHistory: React.FC<StatusChangeHistoryProps> = ({
  orderId,
  className = '',
}) => {
  const [history, setHistory] = useState<StatusChange[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchStatusHistory = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get(`/admin/orders/${orderId}/status-history`);

      if (response.data.success) {
        setHistory(response.data.history);
      } else {
        throw new Error(response.data.error || 'Failed to load status history');
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.error || err.message || 'Failed to load status history';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (orderId) {
      fetchStatusHistory();
    }
  }, [orderId]);

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      pending: 'bg-yellow-100 text-yellow-800',
      pending_ivr: 'bg-orange-100 text-orange-800',
      ivr_sent: 'bg-blue-100 text-blue-800',
      ivr_confirmed: 'bg-green-100 text-green-800',
      approved: 'bg-green-100 text-green-800',
      sent_back: 'bg-red-100 text-red-800',
      denied: 'bg-red-100 text-red-800',
      submitted_to_manufacturer: 'bg-purple-100 text-purple-800',
      shipped: 'bg-indigo-100 text-indigo-800',
      delivered: 'bg-green-100 text-green-800',
      cancelled: 'bg-gray-100 text-gray-800',
    };

    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  const getStatusIcon = (status: string) => {
    const icons: Record<string, React.ReactNode> = {
      pending: <Clock className="h-4 w-4" />,
      pending_ivr: <Clock className="h-4 w-4" />,
      ivr_sent: <CheckCircle className="h-4 w-4" />,
      ivr_confirmed: <CheckCircle className="h-4 w-4" />,
      approved: <CheckCircle className="h-4 w-4" />,
      sent_back: <XCircle className="h-4 w-4" />,
      denied: <XCircle className="h-4 w-4" />,
      submitted_to_manufacturer: <CheckCircle className="h-4 w-4" />,
      shipped: <CheckCircle className="h-4 w-4" />,
      delivered: <CheckCircle className="h-4 w-4" />,
      cancelled: <XCircle className="h-4 w-4" />,
    };

    return icons[status] || <Info className="h-4 w-4" />;
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

  const formatStatus = (status: string) => {
    const labels: Record<string, string> = {
      pending: 'Pending',
      pending_ivr: 'Pending IVR',
      ivr_sent: 'IVR Sent',
      ivr_confirmed: 'IVR Confirmed',
      approved: 'Approved',
      sent_back: 'Sent Back',
      denied: 'Denied',
      submitted_to_manufacturer: 'Submitted to Manufacturer',
      shipped: 'Shipped',
      delivered: 'Delivered',
      cancelled: 'Cancelled',
    };

    return labels[status] || status;
  };

  if (loading) {
    return (
      <div className={`bg-white rounded-lg border border-gray-200 p-6 ${className}`}>
        <div className="flex items-center justify-center space-x-2">
          <RefreshCw className="h-5 w-5 animate-spin text-blue-600" />
          <span className="text-gray-600">Loading status history...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`bg-white rounded-lg border border-red-200 p-6 ${className}`}>
        <div className="flex items-center space-x-3">
          <AlertCircle className="h-6 w-6 text-red-500" />
          <div>
            <h3 className="text-lg font-semibold text-red-800">Status History Error</h3>
            <p className="text-red-600 mt-1">{error}</p>
            <button
              onClick={fetchStatusHistory}
              className="mt-3 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              <RefreshCw className="h-4 w-4 mr-2" />
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg border border-gray-200 ${className}`}>
      {/* Header */}
      <div className="px-6 py-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <History className="h-6 w-6 text-gray-600" />
            <div>
              <h3 className="text-lg font-semibold text-gray-900">Status History</h3>
              <p className="text-sm text-gray-500">Order status changes and updates</p>
            </div>
          </div>
          <button
            onClick={fetchStatusHistory}
            className="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
          >
            <RefreshCw className="h-4 w-4 mr-2" />
            Refresh
          </button>
        </div>
      </div>

      {/* Content */}
      <div className="p-6">
        {history.length === 0 ? (
          <div className="text-center py-8">
            <Info className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">No Status Changes</h3>
            <p className="text-gray-500">No status changes have been recorded for this order yet.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {history.map((change, index) => (
              <div
                key={change.id}
                className={`relative p-4 rounded-lg border ${
                  change.is_significant
                    ? 'bg-blue-50 border-blue-200'
                    : 'bg-gray-50 border-gray-200'
                }`}
              >
                {/* Timeline connector */}
                {index < history.length - 1 && (
                  <div className="absolute left-6 top-12 w-0.5 h-8 bg-gray-300" />
                )}

                <div className="flex items-start space-x-4">
                  {/* Status icon */}
                  <div className="flex-shrink-0">
                    <div className={`w-12 h-12 rounded-full flex items-center justify-center ${
                      change.is_significant ? 'bg-blue-100' : 'bg-gray-100'
                    }`}>
                      {getStatusIcon(change.new_status)}
                    </div>
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center space-x-2">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(change.new_status)}`}>
                          {formatStatus(change.new_status)}
                        </span>
                        {change.is_significant && (
                          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            Significant
                          </span>
                        )}
                      </div>
                      <span className="text-sm text-gray-500">
                        {formatDate(change.created_at)}
                      </span>
                    </div>

                    <div className="flex items-center space-x-2 text-sm text-gray-600 mb-2">
                      <User className="h-4 w-4" />
                      <span>Changed by {change.changed_by}</span>
                    </div>

                    {change.previous_status && (
                      <div className="flex items-center space-x-2 text-sm text-gray-600 mb-2">
                        <span className={getStatusColor(change.previous_status)}>
                          {formatStatus(change.previous_status)}
                        </span>
                        <ArrowRight className="h-4 w-4" />
                        <span className={getStatusColor(change.new_status)}>
                          {formatStatus(change.new_status)}
                        </span>
                      </div>
                    )}

                    {change.notes && (
                      <div className="mt-2 p-3 bg-white rounded border border-gray-200">
                        <p className="text-sm text-gray-700">{change.notes}</p>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default StatusChangeHistory;
