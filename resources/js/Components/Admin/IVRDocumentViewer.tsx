import React, { useState, useEffect } from 'react';
import axios from 'axios';
import {
  FileText,
  Download,
  Eye,
  ExternalLink,
  AlertCircle,
  CheckCircle,
  Clock,
  RefreshCw,
  X,
  Info,
  Shield,
  Activity,
} from 'lucide-react';

interface IVRDocumentViewerProps {
  episodeId: string;
  className?: string;
  onDocumentLoaded?: (data: any) => void;
  onError?: (error: string) => void;
}

interface IVRDocumentData {
  document_url?: string;
  audit_log_url?: string;
  download_count: number;
  last_viewed?: string;
}

const IVRDocumentViewer: React.FC<IVRDocumentViewerProps> = ({
  episodeId,
  className = '',
  onDocumentLoaded,
  onError,
}) => {
  const [documentData, setDocumentData] = useState<IVRDocumentData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showPreview, setShowPreview] = useState(false);

  const fetchIVRDocument = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.get(`/admin/episodes/${episodeId}/ivr-document`);

      if (response.data.success) {
        setDocumentData(response.data);
        onDocumentLoaded?.(response.data);
      } else {
        throw new Error(response.data.error || 'Failed to load IVR document');
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.error || err.message || 'Failed to load IVR document';
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (episodeId) {
      fetchIVRDocument();
    }
  }, [episodeId]);

  const handleViewDocument = () => {
    if (documentData?.document_url) {
      window.open(documentData.document_url, '_blank');
      setShowPreview(false);
    }
  };

  const handleDownloadDocument = () => {
    if (documentData?.document_url) {
      const link = document.createElement('a');
      link.href = documentData.document_url;
      link.download = `IVR_Document_${episodeId}.pdf`;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  };

  const handleViewAuditLog = () => {
    if (documentData?.audit_log_url) {
      window.open(documentData.audit_log_url, '_blank');
    }
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

  if (loading) {
    return (
      <div className={`bg-white rounded-lg border border-gray-200 p-6 ${className}`}>
        <div className="flex items-center justify-center space-x-2">
          <RefreshCw className="h-5 w-5 animate-spin text-blue-600" />
          <span className="text-gray-600">Loading IVR document...</span>
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
            <h3 className="text-lg font-semibold text-red-800">IVR Document Error</h3>
            <p className="text-red-600 mt-1">{error}</p>
            <button
              onClick={fetchIVRDocument}
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

  if (!documentData) {
    return (
      <div className={`bg-white rounded-lg border border-gray-200 p-6 ${className}`}>
        <div className="flex items-center space-x-3">
          <Info className="h-6 w-6 text-gray-400" />
          <div>
            <h3 className="text-lg font-semibold text-gray-800">No IVR Document</h3>
            <p className="text-gray-600 mt-1">No IVR document is available for this episode.</p>
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
            <FileText className="h-6 w-6 text-blue-600" />
            <div>
              <h3 className="text-lg font-semibold text-gray-900">IVR Document</h3>
              <p className="text-sm text-gray-500">Insurance Verification Request</p>
            </div>
          </div>
          <div className="flex items-center space-x-2">
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              <CheckCircle className="h-3 w-3 mr-1" />
              Available
            </span>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Document Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div className="bg-blue-50 rounded-lg p-4">
            <div className="flex items-center">
              <Download className="h-5 w-5 text-blue-600 mr-2" />
              <div>
                <p className="text-sm font-medium text-blue-900">Downloads</p>
                <p className="text-2xl font-bold text-blue-600">{documentData.download_count}</p>
              </div>
            </div>
          </div>

          <div className="bg-green-50 rounded-lg p-4">
            <div className="flex items-center">
              <Eye className="h-5 w-5 text-green-600 mr-2" />
              <div>
                <p className="text-sm font-medium text-green-900">Last Viewed</p>
                <p className="text-sm text-green-600">
                  {documentData.last_viewed ? formatDate(documentData.last_viewed) : 'Never'}
                </p>
              </div>
            </div>
          </div>

          <div className="bg-purple-50 rounded-lg p-4">
            <div className="flex items-center">
              <Shield className="h-5 w-5 text-purple-600 mr-2" />
              <div>
                <p className="text-sm font-medium text-purple-900">Audit Log</p>
                <p className="text-sm text-purple-600">
                  {documentData.audit_log_url ? 'Available' : 'Not available'}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap gap-3">
          <button
            onClick={handleViewDocument}
            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <Eye className="h-4 w-4 mr-2" />
            View Document
          </button>

          <button
            onClick={handleDownloadDocument}
            className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            <Download className="h-4 w-4 mr-2" />
            Download PDF
          </button>

          {documentData.audit_log_url && (
            <button
              onClick={handleViewAuditLog}
              className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
            >
              <Activity className="h-4 w-4 mr-2" />
              View Audit Log
            </button>
          )}

          <button
            onClick={fetchIVRDocument}
            className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
          >
            <RefreshCw className="h-4 w-4 mr-2" />
            Refresh
          </button>
        </div>

        {/* Security Notice */}
        <div className="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
          <div className="flex items-start">
            <Shield className="h-5 w-5 text-yellow-600 mr-2 mt-0.5" />
            <div>
              <h4 className="text-sm font-medium text-yellow-800">Security Notice</h4>
              <p className="text-sm text-yellow-700 mt-1">
                This document contains sensitive patient information. Please ensure you're in a secure location when viewing or downloading.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Preview Modal */}
      {showPreview && documentData.document_url && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
            <div className="flex items-center justify-between p-4 border-b">
              <h3 className="text-lg font-semibold">IVR Document Preview</h3>
              <button
                onClick={() => setShowPreview(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="h-6 w-6" />
              </button>
            </div>
            <div className="p-4">
              <iframe
                src={documentData.document_url}
                className="w-full h-96 border border-gray-200 rounded"
                title="IVR Document Preview"
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default IVRDocumentViewer;
