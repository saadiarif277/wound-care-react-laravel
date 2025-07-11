import React from 'react';
import { ChevronDown, ChevronUp, FileText, Download, Upload, CheckCircle, Clock, AlertCircle } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface IVRDocumentSectionProps {
  orderData: {
    orderNumber: string;
    createdDate: string;
    episodeId?: string;
    ivrStatus: string;
    docusealSubmissionId?: string;
  };
  orderFormData: {
    status: string;
    submissionDate: string;
    reviewDate: string;
    approvalDate: string;
    notes: string;
    fileUrl: string;
    files: any[];
  };
  orderId: number;
  onUpdateIVRStatus: (newStatus: string, notes?: string) => void;
  onUploadIVRResults: () => void;
  onUpdateOrderFormStatus: (newStatus: string, notes?: string) => void;
  onManufacturerSubmission: () => void;
  isOpen: boolean;
  onToggle: () => void;
  userRole: string;
}

const IVRDocumentSection: React.FC<IVRDocumentSectionProps> = ({
  orderData,
  orderFormData,
  orderId,
  onUpdateIVRStatus,
  onUploadIVRResults,
  onUpdateOrderFormStatus,
  onManufacturerSubmission,
  isOpen,
  onToggle,
  userRole
}) => {
  const { theme } = useTheme();
  const colors = themes[theme];

  const getStatusIcon = (status: string) => {
    switch (status?.toLowerCase()) {
      case 'completed':
      case 'approved':
        return <CheckCircle className="w-4 h-4 text-green-600" />;
      case 'in_progress':
      case 'pending':
        return <Clock className="w-4 h-4 text-yellow-600" />;
      default:
        return <AlertCircle className="w-4 h-4 text-gray-600" />;
    }
  };

  const getStatusBadge = (status: string) => {
    const baseClasses = "px-3 py-1 rounded-full text-sm font-medium";
    
    switch (status?.toLowerCase()) {
      case 'completed':
      case 'approved':
        return cn(baseClasses, "bg-green-100 text-green-800");
      case 'in_progress':
      case 'pending':
        return cn(baseClasses, "bg-yellow-100 text-yellow-800");
      case 'rejected':
        return cn(baseClasses, "bg-red-100 text-red-800");
      default:
        return cn(baseClasses, "bg-gray-100 text-gray-800");
    }
  };

  const renderField = (label: string, value: string | undefined, className?: string) => {
    return (
      <div className={cn("flex justify-between items-center py-2", className)}>
        <span className="font-medium text-gray-700">{label}:</span>
        <span className="text-gray-900">{value || 'N/A'}</span>
      </div>
    );
  };

  const renderActionButtons = () => {
    if (userRole === 'provider') {
      return (
        <div className="flex gap-2">
          <button
            onClick={onUploadIVRResults}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <Upload className="w-4 h-4" />
            Upload IVR Results
          </button>
        </div>
      );
    }

    if (userRole === 'admin') {
      return (
        <div className="flex gap-2">
          <button
            onClick={() => onUpdateIVRStatus('approved')}
            className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
          >
            <CheckCircle className="w-4 h-4" />
            Approve IVR
          </button>
          <button
            onClick={onManufacturerSubmission}
            className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
          >
            <FileText className="w-4 h-4" />
            Send to Manufacturer
          </button>
        </div>
      );
    }

    return null;
  };

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
          <FileText className="w-5 h-5 text-purple-600" />
          <h3 className="text-lg font-semibold text-gray-900">
            IVR Document & Order Form
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
          {/* IVR Status */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <FileText className="w-4 h-4" />
                IVR Status
              </h4>
              <div className="bg-gray-50 p-3 rounded-lg">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {getStatusIcon(orderData.ivrStatus)}
                    <span className="font-medium">IVR Status</span>
                  </div>
                  <span className={getStatusBadge(orderData.ivrStatus)}>
                    {orderData.ivrStatus || 'Not Started'}
                  </span>
                </div>
                {orderData.docusealSubmissionId && (
                  <div className="mt-2 text-sm text-gray-600">
                    Submission ID: {orderData.docusealSubmissionId}
                  </div>
                )}
              </div>
            </div>

            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <FileText className="w-4 h-4" />
                Order Form Status
              </h4>
              <div className="bg-gray-50 p-3 rounded-lg">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {getStatusIcon(orderFormData.status)}
                    <span className="font-medium">Form Status</span>
                  </div>
                  <span className={getStatusBadge(orderFormData.status)}>
                    {orderFormData.status || 'Not Started'}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Document Details */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800">Document Details</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {renderField("Submission Date", orderFormData.submissionDate)}
              {renderField("Review Date", orderFormData.reviewDate)}
              {renderField("Approval Date", orderFormData.approvalDate)}
              {renderField("Episode ID", orderData.episodeId)}
            </div>
          </div>

          {/* Files & Documents */}
          {orderFormData.files && orderFormData.files.length > 0 && (
            <div className="space-y-2 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800">Associated Files</h4>
              <div className="space-y-2">
                {orderFormData.files.map((file, index) => (
                  <div key={index} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                    <div className="flex items-center gap-2">
                      <FileText className="w-4 h-4 text-gray-600" />
                      <span className="text-sm font-medium">{file.name || `Document ${index + 1}`}</span>
                    </div>
                    <button className="flex items-center gap-1 text-blue-600 hover:text-blue-700 text-sm">
                      <Download className="w-4 h-4" />
                      Download
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Notes */}
          {orderFormData.notes && (
            <div className="space-y-2 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800">Notes</h4>
              <div className="bg-gray-50 p-3 rounded-lg">
                <p className="text-sm text-gray-700">{orderFormData.notes}</p>
              </div>
            </div>
          )}

          {/* Action Buttons */}
          <div className="pt-4 border-t border-gray-200">
            {renderActionButtons()}
          </div>
        </div>
      )}
    </div>
  );
};

export default IVRDocumentSection; 