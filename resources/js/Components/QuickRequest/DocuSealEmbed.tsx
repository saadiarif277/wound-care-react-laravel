import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';

interface DocuSealEmbedProps {
  manufacturerId: string;
  productCode: string;
  formData?: any; // Pre-filled form data from quick request
  onComplete?: (data: any) => void;
  onSave?: (data: any) => void;
  onError?: (error: string) => void;
  className?: string;
}

export const DocuSealEmbed: React.FC<DocuSealEmbedProps> = ({
  manufacturerId,
  productCode,
  formData = {}, // Default to empty object
  onComplete,
  onSave,
  onError,
  className = '',
}) => {
  const [token, setToken] = useState<string | null>(null);
  const [templateId, setTemplateId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [useDirectUrl, setUseDirectUrl] = useState(true); // Default to direct URL, but allow embedded option

  useEffect(() => {
    const fetchToken = async () => {
      try {
        setIsLoading(true);
        setError(null);

        // Use the new submission slug endpoint to get a proper DocuSeal slug
        console.log('Sending DocuSeal request with data:', {
          manufacturerId,
          productCode,
          formDataKeys: Object.keys(formData || {}),
          hasFormData: !!formData,
          sampleData: Object.keys(formData || {}).slice(0, 5)
        });

        const response = await axios.post('/quick-requests/docuseal/generate-submission-slug', {
          user_email: 'limitless@mscwoundcare.com',
          integration_email: formData.provider_email || formData.patient_email || 'patient@example.com',
          prefill_data: formData, // Pass the form data as prefill_data
          manufacturerId,
          productCode
        }, {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          timeout: 30000 // 30 second timeout
        });

        // API should respond with a proper DocuSeal submission slug
        const slug = response.data.slug;

        if (!slug) {
          throw new Error('No slug received from server');
        }

        console.log('DocuSeal submission slug received:', {
          slug: slug,
          hasFormData: !!formData,
          fieldCount: Object.keys(formData || {}).length,
          sampleFields: Object.keys(formData || {}).slice(0, 5),
          submission_id: response.data.submission_id,
          template_id: response.data.template_id
        });

        setTemplateId(response.data.template_id);
        setToken(slug); // Store the slug as the token
      } catch (err: any) {
        console.error('DocuSeal token fetch error:', {
          error: err,
          response: err.response?.data,
          status: err.response?.status,
          config: err.config,
          url: err.config?.url,
          method: err.config?.method
        });

        let msg = 'Failed to load document form';

        // Handle different error types
        if (err.response?.status === 401) {
          msg = 'Authentication failed. Please check API configuration.';
        } else if (err.response?.status === 403) {
          msg = 'Permission denied. You may not have access to this feature.';
        } else if (err.response?.status === 422) {
          msg = err.response?.data?.message || 'Invalid request data';
        } else if (err.response?.status === 500) {
          msg = 'Server error occurred. Please try again or contact support.';
        } else if (err.response?.data?.error) {
          msg = err.response.data.error;
        } else if (err.response?.data?.message) {
          msg = err.response.data.message;
        } else if (err.message) {
          msg = err.message;
        }

        setError(msg);
        if (onError) {
          onError(msg);
        }
      } finally {
        setIsLoading(false);
      }
    };

    fetchToken();
  }, [manufacturerId, productCode, formData, onError]);

  // Add embedded form logic using proper DocuSeal slug (not JWT)
  useEffect(() => {
    if (!token || useDirectUrl) return;

    // Only try embedded form if user explicitly chooses it
    const script = document.createElement('script');
    script.src = 'https://cdn.docuseal.com/js/form.js';
    script.async = true;
    document.body.appendChild(script);

    script.onload = () => {
      const container = document.getElementById('docuseal-embed-container');
      if (container) {
        container.innerHTML = `<docuseal-form data-src="https://docuseal.com/s/${token}"></docuseal-form>`;
      }
    };

    return () => {
      if (document.body.contains(script)) {
        document.body.removeChild(script);
      }
    };
  }, [token, useDirectUrl]);

  if (error) {
    return (
      <div className={`bg-red-50 border border-red-200 rounded-lg p-4 text-center ${className}`}>
        <p className="text-red-800 font-medium">Error loading form</p>
        <p className="text-red-600 text-sm mt-1">{error}</p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className={`flex items-center justify-center ${className}`} style={{ minHeight: '600px' }}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mx-auto" />
          <p className="mt-3 text-sm text-gray-600">Loading form...</p>
        </div>
      </div>
    );
  }

      // If using direct URL, show a button to open DocuSeal in new window
  if (useDirectUrl && token) {
    return (
      <div className={`w-full ${className}`}>
        <div className="w-full min-h-[600px] bg-white border border-gray-200 rounded-lg flex items-center justify-center">
          <div className="text-center p-8">
            <div className="mb-4">
              <svg className="w-16 h-16 mx-auto text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">DocuSeal Form Ready</h3>
            <p className="text-gray-600 mb-4">Choose how you'd like to access the IVR form:</p>

            <div className="mb-6 flex gap-2">
              <button
                onClick={() => setUseDirectUrl(false)}
                className="px-3 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50"
              >
                Try Embedded
              </button>
              <button
                onClick={() => setUseDirectUrl(true)}
                className="px-3 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50"
              >
                Use New Window
              </button>
            </div>
            <button
              onClick={() => {
                const url = `https://docuseal.com/s/${token}`;
                console.log('Opening DocuSeal URL:', url);
                console.log('Slug length:', token.length);
                console.log('Slug preview:', token.substring(0, 20) + '...');
                window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
              }}
              className="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors"
            >
              Open IVR Form
            </button>
            <p className="text-sm text-gray-500 mt-4">
              The form will open in a new window with all your information pre-filled
            </p>
          </div>
        </div>
      </div>
    );
  }

  // If not using direct URL, show embedded form
  if (!useDirectUrl && token) {
    return (
      <div className={`w-full ${className}`}>
        <div className="mb-4 flex justify-between items-center">
          <h3 className="text-lg font-medium text-gray-900">Embedded DocuSeal Form</h3>
          <button
            onClick={() => setUseDirectUrl(true)}
            className="px-3 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50"
          >
            Switch to New Window
          </button>
        </div>
        <div
          id="docuseal-embed-container"
          className="w-full bg-white border border-gray-200 rounded-lg"
          style={{
            minHeight: '600px',
            height: '80vh',
            width: '100%'
          }}
        >
          {/* DocuSeal form will be inserted here */}
        </div>
      </div>
    );
  }

  return null;
};
