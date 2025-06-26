import React, { useEffect, useState } from 'react';
import axios from 'axios';

interface DocuSealEmbedProps {
  manufacturerId: string;
  productCode: string;
  formData?: any; // Pre-filled form data from quick request
  episodeId?: number; // Episode ID for FHIR integration
  onComplete?: (data: any) => void;
  onSave?: (data: any) => void;
  onError?: (error: string) => void;
  className?: string;
}

export const DocuSealEmbed: React.FC<DocuSealEmbedProps> = ({
  manufacturerId,
  productCode,
  formData = {}, // Default to empty object
  episodeId, // Episode ID for enhanced FHIR integration
  onError,
  className = '',
}) => {
  const [token, setToken] = useState<string | null>(null);
  const [, setTemplateId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [useDirectUrl, setUseDirectUrl] = useState(true); // Default to direct URL, but allow embedded option
  const [integrationInfo, setIntegrationInfo] = useState<any>(null); // Store integration details

  useEffect(() => {
    // Add ref to track if component is mounted to prevent race conditions
    let isMounted = true;
    let requestInProgress = false;

    const fetchToken = async () => {
      // Prevent duplicate requests and race conditions
      if (requestInProgress || !isMounted) {
        console.warn('DocuSeal request already in progress or component unmounted, skipping duplicate call');
        return;
      }

      requestInProgress = true;

      try {
        if (!isMounted) return; // Double-check mounting status

        setIsLoading(true);
        setError(null);

        // Enhanced request with FHIR integration support
        const requestData = {
          user_email: 'limitless@mscwoundcare.com',
          integration_email: formData.provider_email || formData.patient_email || 'patient@example.com',
          prefill_data: formData, // Pass the form data as prefill_data
          manufacturerId,
          productCode,
          ...(episodeId && { episode_id: episodeId }) // Include episode ID if available for FHIR integration
        };

        console.log('Sending enhanced DocuSeal request with FHIR support:', {
          manufacturerId,
          productCode,
          episodeId,
          formDataKeys: Object.keys(formData || {}),
          hasFormData: !!formData,
          hasEpisode: !!episodeId,
          sampleData: Object.keys(formData || {}).slice(0, 5)
        });

        const response = await axios.post('/quick-requests/docuseal/generate-submission-slug', requestData, {
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

        console.log('DocuSeal submission created successfully:', {
          slug: slug,
          hasFormData: !!formData,
          fieldCount: Object.keys(formData || {}).length,
          sampleFields: Object.keys(formData || {}).slice(0, 5),
          submission_id: response.data.submission_id,
          template_id: response.data.template_id,
          integration_type: response.data.integration_type,
          fhir_data_used: response.data.fhir_data_used,
          fields_mapped: response.data.fields_mapped
        });

        // Store integration info for display
        setIntegrationInfo({
          type: response.data.integration_type,
          fhirDataUsed: response.data.fhir_data_used || 0,
          fieldsMapped: response.data.fields_mapped || 0,
          templateName: response.data.template_name,
          manufacturer: response.data.manufacturer
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
        if (onError && isMounted) {
          onError(msg);
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
        requestInProgress = false;
      }
    };

    fetchToken();

    // Cleanup function to prevent memory leaks and race conditions
    return () => {
      isMounted = false;
    };
  }, [manufacturerId, productCode, formData, episodeId, onError]);

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
          <p className="mt-3 text-sm text-gray-600">
            {episodeId ? 'Loading form with FHIR data...' : 'Loading form...'}
          </p>
        </div>
      </div>
    );
  }

  // If using direct URL, show a button to open DocuSeal in new window
  if (useDirectUrl && token) {
    return (
      <div className={`w-full ${className}`}>
        {/* Integration Info Banner */}
        {integrationInfo && (
          <div className={`mb-4 p-3 rounded-lg text-sm ${
            integrationInfo.type === 'fhir_enhanced'
              ? 'bg-green-50 border border-green-200 text-green-800'
              : 'bg-blue-50 border border-blue-200 text-blue-800'
          }`}>
            <div className="flex items-center gap-2">
              {integrationInfo.type === 'fhir_enhanced' ? (
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
              ) : (
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                </svg>
              )}
              <span className="font-medium">
                {integrationInfo.type === 'fhir_enhanced'
                  ? 'FHIR-Enhanced Integration'
                  : 'Standard Integration'
                }
              </span>
            </div>
            <div className="mt-1 text-xs">
              {integrationInfo.type === 'fhir_enhanced' ? (
                <>
                  Using FHIR patient data • {integrationInfo.fhirDataUsed} fields from healthcare records •
                  {integrationInfo.fieldsMapped} total fields mapped
                </>
              ) : (
                <>
                  Using form data only • {integrationInfo.fieldsMapped} fields mapped
                </>
              )}
            </div>
            {integrationInfo.templateName && (
              <div className="mt-1 text-xs opacity-75">
                Template: {integrationInfo.templateName} ({integrationInfo.manufacturer})
              </div>
            )}
          </div>
        )}

        <div className="w-full min-h-[600px] bg-white border border-gray-200 rounded-lg flex items-center justify-center">
          <div className="text-center p-8">
            <div className="mb-4">
              <svg className="w-16 h-16 mx-auto text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">DocuSeal Form Ready</h3>
            <p className="text-gray-600 mb-4">
              {integrationInfo?.type === 'fhir_enhanced'
                ? 'Your form has been pre-populated with FHIR patient data'
                : 'Choose how you\'d like to access the IVR form'
              }
            </p>

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
