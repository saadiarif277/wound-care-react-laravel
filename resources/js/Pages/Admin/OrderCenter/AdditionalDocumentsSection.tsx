import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { FileText, Download, Eye, Image, File, FileImage, FileType } from 'lucide-react';
import { Button } from '@/Components/Button';

interface Document {
  id: string;
  name: string;
  type: 'insurance_card' | 'medical_record' | 'prescription' | 'consent_form' | 'ivr' | 'order_form' | 'other';
  fileType: 'pdf' | 'image' | 'document';
  uploadedAt: string;
  uploadedBy: string;
  fileSize: string;
  url: string;
}

interface AdditionalDocumentsSectionProps {
  documents: Document[];
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const AdditionalDocumentsSection: React.FC<AdditionalDocumentsSectionProps> = ({
  documents,
  isOpen,
  onToggle
}) => {
  const getFileIcon = (fileType: string, documentType: string) => {
    if (fileType === 'image' || documentType === 'insurance_card') {
      return <FileImage className="h-5 w-5 text-blue-500" />;
    } else if (fileType === 'pdf') {
      return <FileType className="h-5 w-5 text-red-500" />;
    } else {
      return <File className="h-5 w-5 text-gray-500" />;
    }
  };

  const getDocumentTypeLabel = (type: string) => {
    switch (type) {
      case 'insurance_card':
        return 'Insurance Card';
      case 'medical_record':
        return 'Medical Record';
      case 'prescription':
        return 'Prescription';
      case 'consent_form':
        return 'Consent Form';
      case 'ivr':
        return 'IVR Form';
      case 'order_form':
        return 'Order Form';
      case 'other':
        return 'Other Document';
      default:
        return 'Document';
    }
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'insurance_card':
        return 'bg-blue-100 text-blue-800';
      case 'medical_record':
        return 'bg-green-100 text-green-800';
      case 'prescription':
        return 'bg-purple-100 text-purple-800';
      case 'consent_form':
        return 'bg-orange-100 text-orange-800';
      case 'ivr':
        return 'bg-indigo-100 text-indigo-800';
      case 'order_form':
        return 'bg-teal-100 text-teal-800';
      case 'other':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const displayDocuments = documents;

  return (
    <SectionCard
      title="Additional Documents"
      icon={FileText}
      sectionKey="documents"
      isOpen={isOpen}
      onToggle={onToggle}
    >
      <div className="space-y-4">
        {displayDocuments.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            <FileText className="h-12 w-12 mx-auto mb-4 text-gray-300" />
            <p>No additional documents uploaded yet.</p>
          </div>
        ) : (
          <div className="grid gap-4">
            {displayDocuments.map((document) => (
              <div
                key={document.id}
                className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
              >
                <div className="flex items-center gap-3 flex-1">
                  <div className="flex-shrink-0">
                    {getFileIcon(document.fileType, document.type)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <h4 className="text-sm font-medium text-gray-900 truncate">
                        {document.name}
                      </h4>
                      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getTypeColor(document.type)}`}>
                        {getDocumentTypeLabel(document.type)}
                      </span>
                    </div>
                    <div className="flex items-center gap-4 text-xs text-gray-500">
                      <span>{document.fileSize}</span>
                      <span>•</span>
                      <span>Uploaded {formatDate(document.uploadedAt)}</span>
                      <span>•</span>
                      <span>by {document.uploadedBy}</span>
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                  <Button
                    onClick={() => window.open(document.url, '_blank')}
                    variant="ghost"
                    size="sm"
                    className="text-blue-600 hover:text-blue-700"
                  >
                    <Eye className="h-4 w-4 mr-1" />
                    View
                  </Button>
                  <Button
                    onClick={async () => {
                      try {
                        // Use secure API endpoint for downloads
                        const response = await fetch(document.url, {
                          method: 'GET',
                          headers: {
                            'Accept': 'application/octet-stream',
                          },
                        });
                        
                        if (!response.ok) {
                          throw new Error('Download failed');
                        }
                        
                        const blob = await response.blob();
                        const downloadUrl = window.URL.createObjectURL(blob);
                        const link = window.document.createElement('a');
                        link.href = downloadUrl;
                        link.download = document.name;
                        link.click();
                        window.URL.revokeObjectURL(downloadUrl);
                      } catch (error) {
                        console.error('Download failed:', error);
                        // Fallback to direct URL if API fails
                        window.open(document.url, '_blank');
                      }
                    }}
                    variant="ghost"
                    size="sm"
                    className="text-green-600 hover:text-green-700"
                  >
                    <Download className="h-4 w-4 mr-1" />
                    Download
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </SectionCard>
  );
};

export default AdditionalDocumentsSection;
