import React, { useState, useEffect } from 'react';
import { DocusealForm } from '@docuseal/react';
import { Loader2, FileText, AlertCircle, X } from 'lucide-react';

interface DocuSealTemplateViewerProps {
  templateId: string;
  docusealTemplateId: string;
  templateName: string;
  onClose?: () => void;
  className?: string;
}

export const DocuSealTemplateViewer: React.FC<DocuSealTemplateViewerProps> = ({
  templateId,
  docusealTemplateId,
  templateName,
  onClose,
  className = '',
}) => {
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // DocuSeal template preview URL
  const templateUrl = `https://app.docuseal.com/templates/${docusealTemplateId}/preview`;

  const handleLoad = () => {
    setIsLoading(false);
  };

  const handleError = (err: any) => {
    setIsLoading(false);
    setError('Failed to load template preview');
    console.error('Template preview error:', err);
  };

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center p-8 bg-red-50 rounded-lg">
        <AlertCircle className="w-12 h-12 text-red-500 mb-4" />
        <p className="text-red-700 font-medium">{error}</p>
      </div>
    );
  }

  return (
    <div className={`relative bg-white rounded-lg shadow-xl ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b">
        <div className="flex items-center gap-3">
          <FileText className="w-5 h-5 text-gray-600" />
          <h3 className="text-lg font-semibold text-gray-900">
            Template Preview: {templateName}
          </h3>
        </div>
        {onClose && (
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition"
          >
            <X className="w-5 h-5" />
          </button>
        )}
      </div>

      {/* Content */}
      <div className="relative" style={{ height: '600px' }}>
        {isLoading && (
          <div className="absolute inset-0 flex items-center justify-center bg-white bg-opacity-90 z-10">
            <div className="flex flex-col items-center">
              <Loader2 className="w-8 h-8 animate-spin text-blue-600 mb-4" />
              <p className="text-gray-600">Loading template preview...</p>
            </div>
          </div>
        )}

        {/* Iframe for template preview */}
        <iframe
          src={templateUrl}
          className="w-full h-full border-0"
          onLoad={handleLoad}
          onError={handleError}
          title={`DocuSeal Template: ${templateName}`}
        />
      </div>

      {/* Footer */}
      <div className="p-4 border-t bg-gray-50 flex justify-between items-center">
        <p className="text-sm text-gray-600">
          This is a preview of the DocuSeal template structure.
        </p>
        <div className="flex gap-2">
          <a
            href={`https://app.docuseal.com/templates/${docusealTemplateId}/edit`}
            target="_blank"
            rel="noopener noreferrer"
            className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition"
          >
            Edit in DocuSeal
          </a>
          {onClose && (
            <button
              onClick={onClose}
              className="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition"
            >
              Close
            </button>
          )}
        </div>
      </div>
    </div>
  );
};