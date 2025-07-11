import React from 'react';
import { ChevronDown, ChevronUp, FileText, Download, Eye, Upload, Calendar, User } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface AdditionalDocumentsSectionProps {
  orderData: {
    orderNumber: string;
    createdDate: string;
    createdBy: string;
    patient: any;
    product: any;
    forms: {
      ivrStatus: string;
      orderFormStatus: string;
    };
    clinical: any;
    provider: any;
    submission: {
      documents: any[];
      submissionId?: string;
      submissionDate?: string;
      submissionStatus?: string;
      additionalFiles?: any[];
      notes?: string;
    };
  };
  isOpen: boolean;
  onToggle: () => void;
  onUploadDocument?: () => void;
  onViewDocument?: (documentId: string) => void;
  onDownloadDocument?: (documentId: string) => void;
  userRole?: string;
}

const AdditionalDocumentsSection: React.FC<AdditionalDocumentsSectionProps> = ({
  orderData,
  isOpen,
  onToggle,
  onUploadDocument,
  onViewDocument,
  onDownloadDocument,
  userRole = 'provider'
}) => {
  const { theme } = useTheme();
  const colors = themes[theme];

  const formatDate = (dateString: string | undefined) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString();
    } catch {
      return dateString;
    }
  };

  const getDocumentIcon = (fileName: string) => {
    const extension = fileName?.split('.').pop()?.toLowerCase();
    
    switch (extension) {
      case 'pdf':
        return <FileText className="w-4 h-4 text-red-600" />;
      case 'doc':
      case 'docx':
        return <FileText className="w-4 h-4 text-blue-600" />;
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
        return <Eye className="w-4 h-4 text-green-600" />;
      default:
        return <FileText className="w-4 h-4 text-gray-600" />;
    }
  };

  const getStatusBadge = (status: string) => {
    const baseClasses = "px-2 py-1 rounded text-xs font-medium";
    
    switch (status?.toLowerCase()) {
      case 'completed':
      case 'approved':
        return cn(baseClasses, "bg-green-100 text-green-800");
      case 'pending':
      case 'in_progress':
        return cn(baseClasses, "bg-yellow-100 text-yellow-800");
      case 'rejected':
        return cn(baseClasses, "bg-red-100 text-red-800");
      default:
        return cn(baseClasses, "bg-gray-100 text-gray-800");
    }
  };

  const renderDocument = (document: any, index: number) => {
    return (
      <div key={index} className="bg-white border border-gray-200 rounded-lg p-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            {getDocumentIcon(document.name || document.fileName)}
            <div>
              <p className="font-medium text-gray-900">{document.name || document.fileName || `Document ${index + 1}`}</p>
              <p className="text-sm text-gray-500">
                {document.size ? `${(document.size / 1024).toFixed(1)} KB` : 'Unknown size'} • 
                {document.type || 'Unknown type'}
              </p>
              {document.uploadedAt && (
                <p className="text-xs text-gray-400">
                  Uploaded: {formatDate(document.uploadedAt)}
                </p>
              )}
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            {document.status && (
              <span className={getStatusBadge(document.status)}>
                {document.status}
              </span>
            )}
            
            <div className="flex gap-1">
              {onViewDocument && (
                <button
                  onClick={() => onViewDocument(document.id)}
                  className="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                  title="View Document"
                >
                  <Eye className="w-4 h-4" />
                </button>
              )}
              
              {onDownloadDocument && (
                <button
                  onClick={() => onDownloadDocument(document.id)}
                  className="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition-colors"
                  title="Download Document"
                >
                  <Download className="w-4 h-4" />
                </button>
              )}
            </div>
          </div>
        </div>

        {document.description && (
          <div className="mt-2 pt-2 border-t border-gray-100">
            <p className="text-sm text-gray-600">{document.description}</p>
          </div>
        )}
      </div>
    );
  };

  const canUploadDocuments = userRole === 'provider' || userRole === 'admin';

  return (
    <div className={cn(
      "rounded-lg border transition-all duration-200",
      colors.card,
      colors.border,
      "hover:shadow-lg"
    )}>
      <button
        onClick={onToggle}
        className={cn(
          "w-full p-4 flex items-center justify-between text-left transition-colors",
          colors.hover
        )}
      >
        <div className="flex items-center gap-3">
          <FileText className="w-5 h-5 text-orange-600" />
          <h3 className="text-lg font-semibold text-gray-900">
            Additional Documents & Submission
          </h3>
        </div>
        {isOpen ? (
          <ChevronUp className="w-5 h-5 text-gray-400" />
        ) : (
          <ChevronDown className="w-5 h-5 text-gray-400" />
        )}
      </button>

      {isOpen && (
        <div className="p-4 pt-0 space-y-4">
          {/* Submission Information */}
          <div className="bg-gradient-to-r from-orange-50 to-yellow-50 p-4 rounded-lg">
            <h4 className="font-semibold text-gray-800 flex items-center gap-2 mb-3">
              <Calendar className="w-4 h-4" />
              Submission Details
            </h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-600">Submission ID:</span>
                <span className="font-medium">{orderData.submission?.submissionId || 'N/A'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">Submission Date:</span>
                <span className="font-medium">{formatDate(orderData.submission?.submissionDate)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">Submitted By:</span>
                <span className="font-medium">{orderData.createdBy || 'N/A'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">Status:</span>
                <span className={getStatusBadge(orderData.submission?.submissionStatus || 'pending')}>
                  {orderData.submission?.submissionStatus || 'Pending'}
                </span>
              </div>
            </div>
          </div>

          {/* Main Documents */}
          {orderData.submission?.documents && orderData.submission.documents.length > 0 && (
            <div className="space-y-3">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <FileText className="w-4 h-4" />
                Primary Documents
              </h4>
              <div className="space-y-2">
                {orderData.submission?.documents?.map((document, index) => 
                  renderDocument(document, index)
                )}
              </div>
            </div>
          )}

          {/* Additional Files */}
          {orderData.submission?.additionalFiles && orderData.submission.additionalFiles.length > 0 && (
            <div className="space-y-3 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <FileText className="w-4 h-4" />
                Additional Files
              </h4>
              <div className="space-y-2">
                {orderData.submission?.additionalFiles?.map((file, index) => 
                  renderDocument(file, index + 1000) // Use high index to avoid conflicts
                )}
              </div>
            </div>
          )}

          {/* No Documents Message */}
          {(!orderData.submission?.documents || orderData.submission?.documents.length === 0) &&
           (!orderData.submission?.additionalFiles || orderData.submission?.additionalFiles.length === 0) && (
            <div className="text-center py-8 bg-gray-50 rounded-lg">
              <FileText className="w-12 h-12 text-gray-400 mx-auto mb-3" />
              <p className="text-gray-500 mb-2">No documents uploaded yet</p>
              <p className="text-sm text-gray-400">Documents will appear here once they are uploaded</p>
            </div>
          )}

          {/* Upload Section */}
          {canUploadDocuments && onUploadDocument && (
            <div className="pt-4 border-t border-gray-200">
              <div className="flex items-center justify-between">
                <h4 className="font-semibold text-gray-800">Upload Documents</h4>
                <button
                  onClick={onUploadDocument}
                  className="flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors"
                >
                  <Upload className="w-4 h-4" />
                  Upload File
                </button>
              </div>
              <p className="text-sm text-gray-600 mt-2">
                Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)
              </p>
            </div>
          )}

          {/* Submission Notes */}
          {orderData.submission?.notes && (
            <div className="space-y-2 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <User className="w-4 h-4" />
                Submission Notes
              </h4>
              <div className="bg-blue-50 p-3 rounded-lg">
                <p className="text-sm text-gray-700 whitespace-pre-wrap">{orderData.submission?.notes}</p>
              </div>
            </div>
          )}

          {/* Document Summary */}
          <div className="pt-4 border-t border-gray-200">
            <div className="bg-gray-50 p-3 rounded-lg">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div>
                  <p className="text-sm text-gray-600">Total Documents</p>
                  <p className="text-xl font-bold text-gray-900">
                    {(orderData.submission?.documents?.length || 0) + (orderData.submission?.additionalFiles?.length || 0)}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-gray-600">Primary Documents</p>
                  <p className="text-xl font-bold text-blue-900">
                    {orderData.submission?.documents?.length || 0}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-gray-600">Additional Files</p>
                  <p className="text-xl font-bold text-orange-900">
                    {orderData.submission?.additionalFiles?.length || 0}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdditionalDocumentsSection; 