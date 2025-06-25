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
  const formRef = useRef<HTMLDivElement>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [useDirectUrl, setUseDirectUrl] = useState(false); // Try embedded first, fallback to direct URL

  useEffect(() => {
    const fetchToken = async () => {
      try {
        setIsLoading(true);
        setError(null);

        // Use the web route which uses standard session auth instead of Sanctum
        const response = await axios.post('/quick-requests/docuseal/generate-form-token', {
          user_email: 'limitless@mscwoundcare.com',
          integration_email: formData.provider_email || formData.patient_email || 'patient@example.com',
          prefill_data: formData, // Pass the form data as prefill_data
          manufacturerId,
          productCode
        });

        // API should respond with JWT generated on backend
        const jwt = response.data.jwt_token || response.data.token || response.data.jwt;

        if (!jwt) {
          throw new Error('No token received from server');
        }

        console.log('DocuSeal JWT token received for form:', {
          hasFormData: !!formData,
          fieldCount: Object.keys(formData || {}).length,
          sampleFields: Object.keys(formData || {}).slice(0, 5)
        });

        // Decode JWT to see payload (for debugging only)
        try {
          const [header, payload, signature] = jwt.split('.');
          const decodedPayload = JSON.parse(atob(payload));
          console.log('DocuSeal Form JWT payload:', {
            template_id: decodedPayload.template_id,
            submitter: decodedPayload.submitter,
            external_id: decodedPayload.external_id
          });
          setTemplateId(decodedPayload.template_id);
        } catch (e) {
          console.error('Could not decode JWT:', e);
        }

        setToken(jwt);
      } catch (err: any) {
        const msg = err.response?.data?.error || err.response?.data?.message || err.message || 'Failed to load document form';
        console.error('DocuSeal token fetch error:', err);
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

    useEffect(() => {
    if (!token || !formRef.current) return;

    let formElement: HTMLElement | null = null;
    let scriptElement: HTMLScriptElement | null = null;
    let messageHandler: ((event: MessageEvent) => void) | null = null;

    // Add a delay to ensure the container is fully rendered with proper dimensions
    const timeoutId = setTimeout(() => {
      if (!formRef.current) return;

      // Add minimal custom styling for the form
      const style = document.createElement('style');
      style.id = 'docuseal-form-style';
      style.textContent = `
        /* Make form take full width */
        docuseal-form {
          width: 100% !important;
          display: block !important;
          min-height: 600px !important;
          height: 80vh !important;
          background: white !important;
        }

        /* Style adjustments for the embedded form */
        docuseal-form iframe {
          width: 100% !important;
          min-height: 600px !important;
          height: 100% !important;
          border: none !important;
          background: white !important;
        }

        /* Ensure parent container has proper styling */
        .docuseal-form-container {
          width: 100% !important;
          min-height: 600px !important;
          height: 80vh !important;
          background: white !important;
          border: 1px solid #e5e7eb;
          border-radius: 8px;
          position: relative;
        }
      `;

      if (!document.getElementById('docuseal-form-style')) {
        document.head.appendChild(style);
      }

      // Check if script already exists
      scriptElement = document.querySelector('script[src="https://cdn.docuseal.com/js/form.js"]') as HTMLScriptElement;

      if (!scriptElement) {
        scriptElement = document.createElement('script');
        scriptElement.src = 'https://cdn.docuseal.com/js/form.js';
        scriptElement.async = true;
        document.body.appendChild(scriptElement);
      }

      // Function to create form element
      const createForm = () => {
        if (!formRef.current) return;

        // Clear any existing content
        formRef.current.innerHTML = '';

        // Create the form element
        formElement = document.createElement('docuseal-form');
        formElement.setAttribute('data-token', token);

        // Add explicit styling to ensure proper dimensions
        formElement.style.width = '100%';
        formElement.style.height = '100%';
        formElement.style.minHeight = '600px';
        formElement.style.display = 'block';

        // Set up event listeners for form events
        messageHandler = (event: MessageEvent) => {
          if (!event.data || typeof event.data !== 'object') return;

          console.log('DocuSeal form event received:', event.data.type, event.data);

          if (event.data.type === 'docuseal:submit' && onComplete) {
            console.log('DocuSeal form submitted:', event.data);
            onComplete(event.data);
          }

          if (event.data.type === 'docuseal:save' && onSave) {
            console.log('DocuSeal form saved:', event.data);
            onSave(event.data);
          }

          // Handle any error events
          if (event.data.type === 'docuseal:error') {
            console.error('DocuSeal form error:', event.data);
            setError(event.data.message || 'Form error occurred');
          }
        };

        window.addEventListener('message', messageHandler);

        formRef.current.appendChild(formElement);

        // Debug: Log when form element is created
        console.log('DocuSeal form element created and appended:', {
          token: token.substring(0, 20) + '...',
          formElement,
          tagName: formElement.tagName,
          attributes: Array.from(formElement.attributes).map(attr => ({ name: attr.name, value: attr.value })),
          customElementDefined: !!customElements.get('docuseal-form'),
          parentElement: formRef.current,
          containerDimensions: {
            width: formRef.current.offsetWidth,
            height: formRef.current.offsetHeight
          }
        });

        // Additional debugging: Check if iframe gets created
        const checkIframe = () => {
          const iframe = formElement?.querySelector('iframe');
          console.log('DocuSeal iframe check:', {
            iframeFound: !!iframe,
            iframeSrc: iframe?.src,
            formElementChildren: formElement?.children.length,
            formElementHTML: formElement?.innerHTML.substring(0, 200)
          });
        };

        // Check immediately and after a delay
        setTimeout(checkIframe, 1000);
        setTimeout(checkIframe, 3000);

        // If no iframe appears after 2 seconds, try fallback approach
        setTimeout(() => {
          const iframe = formElement?.querySelector('iframe');
          if (!iframe && formElement?.children.length === 0) {
            console.warn('DocuSeal custom element not working, trying direct URL approach...');
            setUseDirectUrl(true);
          }
        }, 2000);
      };

      // Create form once script is loaded
      const checkScriptLoaded = () => {
        console.log('Checking DocuSeal script status:', {
          docusealGlobal: !!(window as any).Docuseal,
          scriptLoaded: !!(scriptElement as any)?.loaded,
          customElementsDefined: !!customElements.get('docuseal-form'),
          scriptElement: scriptElement
        });

        // Check if DocuSeal global is available or custom element is defined
        if ((window as any).Docuseal || customElements.get('docuseal-form') || (scriptElement as any)?.loaded) {
          createForm();
        } else if (scriptElement) {
          scriptElement.onload = () => {
            (scriptElement as any).loaded = true;
            console.log('DocuSeal script loaded, creating form...');
            createForm();
          };

          scriptElement.onerror = () => {
            console.error('Failed to load DocuSeal script');
            setError('Failed to load DocuSeal script');
          };
        }
      };

      checkScriptLoaded();
    }, 300); // 300ms delay to ensure container is ready

    return () => {
      clearTimeout(timeoutId);

      // Clean up event listener
      if (messageHandler) {
        window.removeEventListener('message', messageHandler);
      }

      // Clean up form element
      if (formElement && formRef.current?.contains(formElement)) {
        formRef.current.removeChild(formElement);
      }

      // Clean up custom styles when component unmounts
      const customStyle = document.getElementById('docuseal-form-style');
      if (customStyle) {
        customStyle.remove();
      }
    };
  }, [token, onComplete, onSave]);

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
            <p className="text-gray-600 mb-6">Click the button below to open the IVR form in a new window</p>
            <button
              onClick={() => {
                const url = `https://docuseal.com/s/${token}`;
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

  return (
    <div className={`w-full ${className}`}>
      <div
        ref={formRef}
        className="docuseal-form-container w-full bg-white border border-gray-200 rounded-lg"
        style={{
          minHeight: '600px',
          height: '80vh',
          width: '100%',
          display: isLoading ? 'none' : 'block' // Hide until token is loaded
        }}
      >
        {/* Loading placeholder - will be replaced by DocuSeal form */}
      </div>
      {isLoading && (
        <div className="w-full min-h-[600px] bg-white border border-gray-200 rounded-lg flex items-center justify-center">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mx-auto" />
            <p className="mt-3 text-sm text-gray-600">Loading DocuSeal form...</p>
          </div>
        </div>
      )}
    </div>
  );
};
