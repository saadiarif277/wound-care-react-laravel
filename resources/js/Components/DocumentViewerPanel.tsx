import React, { useState, useEffect } from 'react';
import { X, Download, Eye, FileText, AlertCircle, Loader2 } from 'lucide-react';
import { Button } from '@/Components/Button';
import axios from 'axios';

interface DocumentViewerPanelProps {
  isOpen: boolean;
  onClose: () => void;
  documentType: 'ivr' | 'order-form';
  orderId: string;
  title: string;
}

interface DocumentData {
  url: string;
  filename: string;
  contentType: string;
  size?: string;
}

const DocumentViewerPanel: React.FC<DocumentViewerPanelProps> = ({
  isOpen,
  onClose,
  documentType,
  orderId,
  title,
}) => {
  const [documentData, setDocumentData] = useState<DocumentData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && orderId) {
      fetchDocumentData();
    }
  }, [isOpen, orderId, documentType]);

  const fetchDocumentData = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const endpoint = documentType === 'ivr'
        ? `/admin/orders/${orderId}/ivr-document`
        : `/admin/orders/${orderId}/order-form-document`;

      const response = await axios.get(endpoint, {
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      if (response.status !== 200) {
        throw new Error(`Failed to fetch ${documentType} document`);
      }

      const data = response.data;
      setDocumentData(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load document');
    } finally {
      setIsLoading(false);
    }
  };

  const handleDownload = () => {
    if (documentData?.url) {
      const link = document.createElement('a');
      link.href = documentData.url;
      link.download = documentData.filename || `${documentType}-${orderId}.pdf`;
      link.click();
    }
  };

  const handleView = () => {
    if (documentData?.url) {
      window.open(documentData.url, '_blank');
    }
  };

  const getStatusColor = (type: string) => {
    switch (type) {
      case 'ivr':
        return 'text-blue-600 bg-blue-100';
      case 'order-form':
        return 'text-green-600 bg-green-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getIcon = (type: string) => {
    switch (type) {
      case 'ivr':
        return <FileText className="h-5 w-5" />;
      case 'order-form':
        return <FileText className="h-5 w-5" />;
      default:
        return <FileText className="h-5 w-5" />;
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={onClose}
      />

      {/* Panel */}
      <div className="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-xl transform transition-transform duration-300 ease-in-out">
        <div className="flex flex-col h-full">
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b border-gray-200">
            <div className="flex items-center gap-3">
              <div className={`p-2 rounded-lg ${getStatusColor(documentType)}`}>
                {getIcon(documentType)}
              </div>
              <div>
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                <p className="text-sm text-gray-500">Order #{orderId}</p>
              </div>
            </div>
            <button
              onClick={onClose}
              className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <X className="h-5 w-5 text-gray-500" />
            </button>
          </div>

          {/* Content */}
          <div className="flex-1 p-4 overflow-y-auto">
            {isLoading && (
              <div className="flex items-center justify-center h-32">
                <div className="text-center">
                  <Loader2 className="h-8 w-8 animate-spin text-blue-600 mx-auto mb-2" />
                  <p className="text-gray-600">Loading document...</p>
                </div>
              </div>
            )}

            {error && (
              <div className="flex items-center justify-center h-32">
                <div className="text-center">
                  <AlertCircle className="h-8 w-8 text-red-600 mx-auto mb-2" />
                  <p className="text-red-600 font-medium">Error</p>
                  <p className="text-gray-600 text-sm">{error}</p>
                </div>
              </div>
            )}

            {documentData && !isLoading && !error && (
              <div className="space-y-4">
                {/* Document Info */}
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Filename:</span>
                      <span className="text-sm font-medium text-gray-900">
                        {documentData.filename || `${documentType}-${orderId}.pdf`}
                      </span>
                    </div>
                    {documentData.size && (
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-600">Size:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {documentData.size}
                        </span>
                      </div>
                    )}
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Type:</span>
                      <span className="text-sm font-medium text-gray-900">
                        {documentData.contentType}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="space-y-3">
                  <Button
                    onClick={handleView}
                    className="w-full"
                    variant="ghost"
                  >
                    <Eye className="h-4 w-4 mr-2" />
                    View Document
                  </Button>

                  <Button
                    onClick={handleDownload}
                    className="w-full"
                  >
                    <Download className="h-4 w-4 mr-2" />
                    Download Document
                  </Button>
                </div>

                {/* Preview Area */}
                <div className="border border-gray-200 rounded-lg p-4">
                  <h4 className="text-sm font-medium text-gray-900 mb-2">Document Preview</h4>
                  <div className="bg-gray-100 rounded-lg p-4 text-center">
                    <FileText className="h-12 w-12 text-gray-400 mx-auto mb-2" />
                    <p className="text-sm text-gray-600">
                      Click "View Document" to see the full document in a new tab
                    </p>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="p-4 border-t border-gray-200">
            <Button
              onClick={onClose}
              variant="ghost"
              className="w-full"
            >
              Close
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DocumentViewerPanel;
