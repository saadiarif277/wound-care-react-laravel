import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { FileText, Send, CheckCircle, AlertCircle, Loader2 } from 'lucide-react';
import { toast } from 'react-hot-toast';

interface IvrGeneratorProps {
  orderId: string;
  orderNumber: string;
  manufacturerName: string;
  onComplete?: () => void;
}

export const IvrGenerator: React.FC<IvrGeneratorProps> = ({
  orderId,
  orderNumber,
  manufacturerName,
  onComplete
}) => {
  const [isGenerating, setIsGenerating] = useState(false);
  const [generatedDocument, setGeneratedDocument] = useState<{
    url: string;
    submissionId: string;
  } | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleGenerateIvr = async () => {
    setIsGenerating(true);
    setError(null);

    // For demo purposes, simulate the IVR generation
    if (orderId.startsWith('demo-')) {
      // Simulate API call delay
      setTimeout(() => {
        setGeneratedDocument({
          url: '/demo-ivr-document.pdf',
          submissionId: 'demo-submission-' + Date.now()
        });
        toast.success('IVR document generated successfully!');
        setIsGenerating(false);
      }, 2000);
      return;
    }

    try {
      // Call the backend to generate IVR for real orders
      await router.post(`/admin/orders/${orderId}/generate-ivr`, {
        ivr_required: true,
      }, {
        preserveScroll: true,
        onSuccess: (page) => {
          // Get the generated document info from the response
          const response = page.props as any;
          if (response.ivr_document_url && response.docuseal_submission_id) {
            setGeneratedDocument({
              url: response.ivr_document_url,
              submissionId: response.docuseal_submission_id
            });
            toast.success('IVR document generated successfully!');
          }
        },
        onError: (errors) => {
          setError('Failed to generate IVR document. Please try again.');
          toast.error('Error generating IVR document');
          console.error(errors);
        },
        onFinish: () => {
          setIsGenerating(false);
        }
      });
    } catch (err) {
      setError('An unexpected error occurred');
      setIsGenerating(false);
    }
  };

  const handleSendToManufacturer = async () => {
    if (!generatedDocument) return;

    // For demo purposes, simulate sending to manufacturer
    if (orderId.startsWith('demo-')) {
      setTimeout(() => {
        toast.success(`IVR sent to ${manufacturerName} successfully!`);
        if (onComplete) {
          onComplete();
        }
      }, 1000);
      return;
    }

    try {
      await router.post(`/admin/orders/${orderId}/send-ivr-to-manufacturer`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`IVR sent to ${manufacturerName} successfully!`);
          if (onComplete) {
            onComplete();
          }
        },
        onError: () => {
          toast.error('Failed to send IVR to manufacturer');
        }
      });
    } catch (err) {
      toast.error('An unexpected error occurred');
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <h3 className="text-lg font-semibold text-gray-900 mb-4">
        IVR Document Generation
      </h3>

      <div className="space-y-4">
        {/* Step 1: Generate IVR */}
        <div className={`p-4 rounded-lg border ${
          generatedDocument ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'
        }`}>
          <div className="flex items-start space-x-3">
            <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
              generatedDocument ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-600'
            }`}>
              {isGenerating ? (
                <Loader2 className="w-4 h-4 animate-spin" />
              ) : generatedDocument ? (
                <CheckCircle className="w-4 h-4" />
              ) : (
                <span className="text-sm font-medium">1</span>
              )}
            </div>
            <div className="flex-1">
              <h4 className="text-sm font-medium text-gray-900">
                Generate IVR Document
              </h4>
              <p className="text-sm text-gray-600 mt-1">
                Create ACZ IVR form for order #{orderNumber}
              </p>
              
              {!generatedDocument && (
                <button
                  onClick={handleGenerateIvr}
                  disabled={isGenerating}
                  className="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isGenerating ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Generating...
                    </>
                  ) : (
                    <>
                      <FileText className="w-4 h-4 mr-2" />
                      Generate IVR
                    </>
                  )}
                </button>
              )}

              {generatedDocument && (
                <div className="mt-3 flex items-center space-x-3">
                  <span className="text-sm text-green-600 font-medium">
                    Document generated successfully
                  </span>
                  <a
                    href={generatedDocument.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm text-blue-600 hover:text-blue-800 underline"
                  >
                    View Document
                  </a>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Step 2: Send to Manufacturer */}
        <div className={`p-4 rounded-lg border ${
          generatedDocument ? 'border-gray-200 bg-gray-50' : 'border-gray-200 bg-gray-100 opacity-50'
        }`}>
          <div className="flex items-start space-x-3">
            <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
              generatedDocument ? 'bg-blue-500 text-white' : 'bg-gray-300 text-gray-600'
            }`}>
              <span className="text-sm font-medium">2</span>
            </div>
            <div className="flex-1">
              <h4 className="text-sm font-medium text-gray-900">
                Send to Manufacturer
              </h4>
              <p className="text-sm text-gray-600 mt-1">
                Email IVR document to {manufacturerName}
              </p>
              
              {generatedDocument && (
                <button
                  onClick={handleSendToManufacturer}
                  className="mt-3 inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700"
                >
                  <Send className="w-4 h-4 mr-2" />
                  Send to {manufacturerName}
                </button>
              )}
            </div>
          </div>
        </div>

        {/* Error Display */}
        {error && (
          <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="flex items-start space-x-3">
              <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm text-red-800">{error}</p>
              </div>
            </div>
          </div>
        )}

        {/* Info Box */}
        <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <h4 className="text-sm font-medium text-blue-900 mb-2">
            About IVR Generation
          </h4>
          <ul className="text-sm text-blue-800 space-y-1">
            <li>• 90% of fields are auto-populated from system data</li>
            <li>• Only minimal PHI is accessed from FHIR</li>
            <li>• Document is generated without requiring signatures</li>
            <li>• Manufacturer receives PDF via email for processing</li>
          </ul>
        </div>
      </div>
    </div>
  );
};