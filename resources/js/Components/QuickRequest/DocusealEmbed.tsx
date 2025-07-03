import { useEffect, useState, useCallback, useRef } from 'react';
import axios from 'axios';
import { AlertCircle, Bug, CheckCircle2, ExternalLink, FileText, Shield, Clock, Heart, Zap, Award, Brain } from 'lucide-react';

// Better TypeScript interfaces
interface FormData {
  patient_email?: string;
  provider_email?: string;
  patient_name?: string;
  provider_name?: string;
  [key: string]: any;
}

interface IntegrationInfo {
  type: 'fhir_enhanced' | 'standard';
  fhirDataUsed: number;
  fieldsMapped: number;
  templateName?: string;
  manufacturer?: string;
}

interface DocusealResponse {
  slug: string;
  submission_id: string;
  template_id: string;
  integration_type: 'fhir_enhanced' | 'standard';
  fhir_data_used?: number;
  fields_mapped?: number;
  template_name?: string;
  manufacturer?: string;
  ai_mapping_used?: boolean;
  ai_confidence?: number;
  mapping_method?: 'ai' | 'static' | 'hybrid';
}

interface DocusealEmbedProps {
  manufacturerId: string;
  templateId?: string; // Direct template ID mapping
  productCode: string;
  documentType?: 'IVR' | 'OrderForm'; // NEW: Document type parameter
  formData?: FormData;
  episodeId?: number;
  onComplete?: (data: any) => void;
  onSave?: (data: any) => void;
  onError?: (error: string) => void;
  onSend?: (data: any) => void; // NEW: Callback for when form is sent
  className?: string;
  debug?: boolean;
  submissionUrl: string;
  builderToken: string;
  builderProps: {
    templateId?: string;
    userEmail?: string;
    integrationEmail?: string;
    templateName?: string;
  } | null;
}

export const DocusealEmbed: React.FC<DocusealEmbedProps> = ({
  manufacturerId,
  templateId,
  productCode,
  documentType = 'IVR', // Default to IVR for backward compatibility
  formData = {}, // Default to empty object
  episodeId, // Episode ID for enhanced FHIR integration
  onComplete,
  onSave,
  onError,
  className = '',
  debug = true // Enable debug mode by default for now
}) => {
  const [token, setToken] = useState<string | null>(null);
  const [, setTemplateId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [useDirectUrl, setUseDirectUrl] = useState(false); // Default to embedded for better UX
  const [integrationInfo, setIntegrationInfo] = useState<IntegrationInfo | null>(null); // Store integration details
  const [mappingProgress, setMappingProgress] = useState<string>(''); // Track AI mapping progress
  const isMountedRef = useRef(true);
  const requestInProgressRef = useRef(false);
  const [ setDebugInfo ] = useState<any>(null);

  // Memoized token fetch function
  const fetchToken = useCallback(async () => {
    // Prevent duplicate requests and race conditions
    if (requestInProgressRef.current || !isMountedRef.current) {
      console.warn('Docuseal request already in progress or component unmounted, skipping duplicate call');
      return;
    }

    requestInProgressRef.current = true;

    try {
      if (!isMountedRef.current) return;

      setIsLoading(true);
      setError(null);
      setMappingProgress('Initializing form...');

      // Comprehensive logging of formData
      if (debug) {
        console.group('🔍 DocusealEmbed Debug - Form Data Analysis');
        console.log('Template ID:', templateId);
        console.log('Document Type:', documentType);
        console.log('Manufacturer ID:', manufacturerId);
        console.log('Episode ID:', episodeId);
        console.log('Form Data Keys:', Object.keys(formData));
        console.log('Form Data Keys Count:', Object.keys(formData).length);

        // Log all fields grouped by category
        console.group('📋 Patient Information');
        const patientFields = Object.entries(formData).filter(([key]) =>
            key.includes('patient') || key === 'patient_name'
        );
        patientFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.group('👨‍⚕️ Provider Information');
        const providerFields = Object.entries(formData).filter(([key]) =>
            key.includes('provider') || key.includes('physician') || key.includes('doctor')
        );
        providerFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.group('🏥 Facility Information');
        const facilityFields = Object.entries(formData).filter(([key]) =>
            key.includes('facility') || key.includes('practice') || key.includes('office')
        );
        facilityFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.group('🩹 Clinical Information');
        const clinicalFields = Object.entries(formData).filter(([key]) =>
            key.includes('wound') || key.includes('diagnosis') || key.includes('icd') ||
            key.includes('cpt') || key.includes('procedure')
        );
        clinicalFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.group('🏥 Insurance Information');
        const insuranceFields = Object.entries(formData).filter(([key]) =>
            key.includes('insurance') || key.includes('member')
        );
        insuranceFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.group('📦 Product Information');
        const productFields = Object.entries(formData).filter(([key]) =>
            key.includes('product') || key.includes('manufacturer')
        );
        productFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.group('📄 All Other Fields');
        const otherFields = Object.entries(formData).filter(([key]) =>
            !key.includes('patient') && !key.includes('provider') && !key.includes('physician') &&
            !key.includes('facility') && !key.includes('practice') && !key.includes('wound') &&
            !key.includes('diagnosis') && !key.includes('insurance') && !key.includes('product') &&
            !key.includes('manufacturer') && !key.includes('icd') && !key.includes('cpt')
        );
        otherFields.forEach(([key, value]) => console.log(`  ${key}:`, value));
        console.groupEnd();

        console.log('Full Form Data:', formData);
        console.groupEnd();
      }

      // Enhanced request with FHIR integration support
      const requestData = {
        user_email: 'limitless@mscwoundcare.com',
        integration_email: formData.provider_email || formData.patient_email || 'patient@example.com',
        prefill_data: formData,
        manufacturerId,
        templateId, // Direct template ID if provided
        productCode,
        documentType, // NEW: Include document type in request
        ...(episodeId && { episode_id: episodeId })
      };

      console.log('Sending enhanced Docuseal request with FHIR support:', {
        manufacturerId,
        templateId,
        productCode,
        episodeId,
        formDataKeys: Object.keys(formData || {}),
        hasFormData: !!formData,
        hasEpisode: !!episodeId,
        sampleData: Object.keys(formData || {}).slice(0, 5)
      });

      // Update progress
      if (episodeId) {
        setMappingProgress('Loading FHIR data...');
      } else if (Object.keys(formData || {}).length > 0) {
        setMappingProgress('Mapping form fields with AI...');
      }

      const response = await axios.post<DocusealResponse>(
        '/quick-requests/docuseal/generate-submission-slug',
        requestData,
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          timeout: 30000
        }
      );

      const { slug, template_id, integration_type, fhir_data_used, fields_mapped, template_name, manufacturer, ai_mapping_used, ai_confidence } = response.data;

      if (!slug) {
        throw new Error('No slug received from server');
      }

      console.log('Docuseal submission created successfully:', response.data);

      // Store integration info for display
      setIntegrationInfo({
        type: integration_type,
        fhirDataUsed: fhir_data_used || 0,
        fieldsMapped: fields_mapped || 0,
        templateName: template_name,
        manufacturer: manufacturer
      });

      // Update progress based on AI usage
      if (ai_mapping_used) {
        setMappingProgress(`AI mapping complete! (${Math.round((ai_confidence || 0) * 100)}% confidence)`);
      } else {
        setMappingProgress('Form ready!');
      }

      setTemplateId(template_id);
      setToken(slug);

      if (debug) {
        console.group('🔍 DocusealEmbed Debug - API Response');
        console.log('Response Status:', response.status);
        console.log('Response Data:', response.data);
        console.log('Slug:', response.data.slug);
        console.log('Fields Mapped:', response.data.fields_mapped);
        console.log('Mapping Method:', response.data.mapping_method);
        console.log('Integration Type:', response.data.integration_type);
        console.groupEnd();

        // Store debug info for display
        setDebugInfo({
          request: {
            templateId,
            manufacturerId,
            documentType,
            episodeId,
            formDataKeys: Object.keys(formData),
            formDataSample: {
              patient_name: formData.patient_name,
              physician_npi: formData.physician_npi,
              facility_name: formData.facility_name
            }
          },
          response: response.data
        });
      }
    } catch (err: any) {
      console.error('Docuseal token fetch error:', {
        error: err,
        response: err.response?.data,
        status: err.response?.status
      });

      let msg = 'Failed to load document form';

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
      if (onError && isMountedRef.current) {
        onError(msg);
      }
    } finally {
      if (isMountedRef.current) {
        setIsLoading(false);
      }
      requestInProgressRef.current = false;
    }
  }, [manufacturerId, productCode, documentType, formData, episodeId, onError, debug]);

  useEffect(() => {
    isMountedRef.current = true;
    fetchToken();

    return () => {
      isMountedRef.current = false;
    };
  }, [fetchToken]);

  // Enhanced embedded form logic with completion handling
  useEffect(() => {
    if (!token || useDirectUrl) return;

    const script = document.createElement('script');
    script.src = 'https://cdn.docuseal.com/js/form.js';
    script.async = true;
    document.body.appendChild(script);

    script.onload = () => {
      const container = document.getElementById('docuseal-embed-container');
      if (container) {
        container.innerHTML = `<docuseal-form data-src="https://docuseal.com/s/${token}"></docuseal-form>`;

        // Add event listeners for form completion
        const docusealForm = container.querySelector('docuseal-form');
        if (docusealForm) {
          docusealForm.addEventListener('completed', (event: any) => {
            console.log('Docuseal form completed:', event.detail);
            if (onComplete) {
              onComplete(event.detail);
            }
          });

          docusealForm.addEventListener('save', (event: any) => {
            console.log('Docuseal form saved:', event.detail);
            if (onSave) {
              onSave(event.detail);
            }
          });
        }
      }
    };

    return () => {
      if (document.body.contains(script)) {
        document.body.removeChild(script);
      }
    };
  }, [token, useDirectUrl, onComplete, onSave]);

  if (error) {
    return (
      <div className={`relative overflow-hidden ${className}`}>
        {/* Error Background Pattern */}
        <div className="absolute inset-0 bg-gradient-to-br from-red-50 to-red-100 opacity-50" />
        <div className="absolute inset-0" style={{
          backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ef4444' fill-opacity='0.05' fill-rule='nonzero'%3E%3Ccircle cx='30' cy='30' r='3'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
        }} />

        <div className="relative z-10 bg-white/90 backdrop-blur-sm border border-red-200 rounded-2xl p-8 text-center shadow-lg">
          <div className="flex justify-center mb-4">
            <div className="p-3 bg-red-100 rounded-full">
              <AlertCircle className="h-8 w-8 text-red-600" />
            </div>
          </div>

          <h3 className="text-xl font-semibold text-gray-900 mb-2">Unable to Load Form</h3>
          <p className="text-red-700 font-medium mb-4">{error}</p>

          <div className="space-y-3">
            <button
              onClick={() => {
                setError(null);
                fetchToken();
              }}
              className="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-3 px-6 rounded-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg"
            >
              <Zap className="h-4 w-4" />
              Try Again
            </button>

            <div className="flex gap-2">
              <button
                onClick={() => window.location.reload()}
                className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
              >
                Refresh Page
              </button>
              <button
                onClick={() => {
                  if (onError) onError('User reported issue: ' + error);
                }}
                className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-1"
              >
                <Bug className="h-4 w-4" />
                Report Issue
              </button>
            </div>
          </div>

          <div className="mt-6 p-4 bg-red-50 rounded-lg border border-red-200">
            <p className="text-sm text-red-800">
              <strong>Need help?</strong> Contact our support team at{' '}
              <a href="mailto:support@mscwoundcare.com" className="underline hover:no-underline">
                support@mscwoundcare.com
              </a>
            </p>
          </div>
        </div>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className={`relative overflow-hidden ${className}`} style={{ minHeight: '600px' }}>
        {/* Animated Background */}
        <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
          <div className="absolute inset-0" style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%233b82f6' fill-opacity='0.03' fill-rule='nonzero'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
          }} />
        </div>

        <div className="relative z-10 flex items-center justify-center h-full">
          <div className="text-center p-8 max-w-md">
            {/* Enhanced Loading Animation */}
            <div className="relative mb-6">
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="w-16 h-16 border-4 border-blue-200 rounded-full animate-pulse" />
              </div>
              <div className="relative flex items-center justify-center">
                <div className="w-16 h-16 border-4 border-transparent border-t-blue-600 border-r-blue-500 rounded-full animate-spin" />
                <div className="absolute">
                  {episodeId ? (
                    <Heart className="h-6 w-6 text-blue-600 animate-pulse" />
                  ) : documentType === 'OrderForm' ? (
                    <FileText className="h-6 w-6 text-blue-600 animate-pulse" />
                  ) : (
                    <Shield className="h-6 w-6 text-blue-600 animate-pulse" />
                  )}
                </div>
              </div>
            </div>

            {/* Dynamic Loading Messages */}
            <h3 className="text-lg font-semibold text-gray-800 mb-2">
              {episodeId ? 'Preparing Your Smart Form' :
               documentType === 'OrderForm' ? 'Loading Order Form' :
               'Setting Up Insurance Verification'}
            </h3>

            <div className="space-y-2 text-sm text-gray-600">
              <p className="flex items-center justify-center gap-2">
                <Clock className="h-4 w-4 animate-pulse" />
                {mappingProgress || (episodeId ? 'Fetching your healthcare data...' :
                 documentType === 'OrderForm' ? 'Preparing manufacturer order form...' :
                 'Creating personalized IVR form...')}
              </p>

              {episodeId && (
                <div className="mt-4 p-3 bg-white/80 backdrop-blur rounded-lg border border-blue-200">
                  <div className="flex items-center gap-2 text-blue-700">
                    <Award className="h-4 w-4" />
                    <span className="font-medium">FHIR-Enhanced Experience</span>
                  </div>
                  <p className="text-xs text-blue-600 mt-1">
                    Your form will be pre-filled with data from your healthcare records
                  </p>
                </div>
              )}

              {/* AI Mapping Indicator */}
              {mappingProgress && (
                <div className="mt-3 p-3 bg-purple-50 backdrop-blur rounded-lg border border-purple-200">
                  <div className="flex items-center gap-2 text-purple-700">
                    <Brain className="h-4 w-4 animate-pulse" />
                    <span className="font-medium">AI-Powered Field Mapping</span>
                  </div>
                  <p className="text-xs text-purple-600 mt-1">
                    Using artificial intelligence to optimize form filling
                  </p>
                </div>
              )}
            </div>

            {/* Progress Indicators */}
            <div className="mt-6">
              <div className="flex justify-center space-x-2">
                <div className="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                <div className="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                <div className="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
              </div>
            </div>

            {/* Security Assurance */}
            <div className="mt-6 p-3 bg-green-50 rounded-lg border border-green-200">
              <div className="flex items-center justify-center gap-2 text-green-700">
                <Shield className="h-4 w-4" />
                <span className="text-xs font-medium">HIPAA Compliant & Secure</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // If using direct URL, show a button to open Docuseal in new window
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
            <h3 className="text-lg font-medium text-gray-900 mb-2">Docuseal Form Ready</h3>
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
                console.log('Opening Docuseal URL:', url);
                console.log('Slug length:', token.length);
                console.log('Slug preview:', token.substring(0, 20) + '...');
                window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
              }}
              className="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition-colors"
            >
              Open {documentType === 'OrderForm' ? 'Order Form' : 'IVR Form'}
            </button>
            <p className="text-sm text-gray-500 mt-4">
              The form will open in a new window with all your information pre-filled
            </p>
          </div>
        </div>
      </div>
    );
  }

  // Show embedded form by default
  if (token) {
    return (
      <div className={`w-full ${className}`}>
        {/* Compact Status Bar */}
        {integrationInfo && integrationInfo.fieldsMapped > 0 && (
          <div className="mb-2 p-2 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
            <div className="flex items-center gap-2 text-sm">
              <CheckCircle2 className="w-4 h-4 text-green-600" />
              <span className="text-green-800">
                <span className="font-medium">{integrationInfo.fieldsMapped}</span> fields pre-filled
              </span>
              {mappingProgress.includes('AI') && (
                <span className="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">
                  AI-Powered
                </span>
              )}
            </div>
            <button
              onClick={() => {
                const url = `https://docuseal.com/s/${token}`;
                window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
              }}
              className="text-sm text-blue-600 hover:text-blue-800 underline"
            >
              Open in new tab
            </button>
          </div>
        )}

        {/* Embedded Form Container */}
        {!useDirectUrl ? (
          <div className="relative">
            <div
              id="docuseal-embed-container"
              className="w-full bg-white rounded-lg shadow-lg overflow-hidden"
              style={{
                minHeight: '800px',
                height: '100vh',
                maxHeight: '1200px',
                width: '100%',
                maxWidth: '100%'
              }}
            >
              {/* Docuseal form will be inserted here */}
            </div>

            {/* Loading overlay while form loads */}
            <div className="absolute inset-0 bg-white/50 backdrop-blur-sm flex items-center justify-center transition-opacity duration-500 pointer-events-none opacity-0" id="docuseal-loading-overlay">
              <div className="text-center">
                <div className="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-3"></div>
                <p className="text-sm text-gray-600">Loading form...</p>
              </div>
            </div>
          </div>
        ) : (
          <div className="bg-white rounded-lg shadow-lg p-8 text-center">
            <div className="max-w-md mx-auto">
              <FileText className="w-16 h-16 text-blue-600 mx-auto mb-4" />
              <h3 className="text-xl font-semibold text-gray-900 mb-2">Form Ready</h3>
              <p className="text-gray-600 mb-6">
                Your {documentType === 'OrderForm' ? 'order' : 'insurance verification'} form has been prepared
                {integrationInfo && integrationInfo.fieldsMapped > 0 && ` with ${integrationInfo.fieldsMapped} pre-filled fields`}.
              </p>
              <button
                onClick={() => {
                  const url = `https://docuseal.com/s/${token}`;
                  window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
                }}
                className="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                <ExternalLink className="w-4 h-4" />
                Open Form in New Window
              </button>
              <button
                onClick={() => setUseDirectUrl(false)}
                className="mt-3 text-sm text-gray-600 hover:text-gray-800 underline"
              >
                or embed it here instead
              </button>
            </div>
          </div>
        )}
      </div>
    );
  }

  return null;
};
