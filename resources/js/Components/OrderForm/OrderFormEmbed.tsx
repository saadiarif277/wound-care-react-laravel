import React, { useEffect, useReducer, useRef, useCallback, useState } from 'react';
import { DocusealForm } from '@docuseal/react';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { AlertCircle, Loader2, CheckCircle, FileText, Send } from 'lucide-react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { toast } from 'sonner';
import api from '@/lib/api';

interface OrderFormEmbedProps {
  manufacturerId: string;
  productCode: string;
  formData: any;
  episodeId?: number;
  productRequestId?: number;
  onComplete?: (data: any) => void;
  onError?: (error: string) => void;
  onOrderFormSubmit?: (submissionId: string) => void;
  existingSubmissionId?: string; // New prop for existing submissions
  className?: string;
  debug?: boolean;
}

interface SubmissionResponse {
  success?: boolean;
  data?: {
    submitter_slug?: string;
    slug?: string;
    signing_url?: string;
    submission_id?: string;
    status?: string;
    error?: string;
  };
  error?: string;
  message?: string;
}

interface TemplateResponse {
  success: boolean;
  data?: {
    template?: any;
    debug_info?: any;
  };
  template?: any;
  debug_info?: any;
}

/**
 * Order Form Embed Component
 *
 * Specialized component for displaying OrderForm documents from Docuseal
 * Automatically finds the appropriate OrderForm template based on manufacturer
 */
export const OrderFormEmbed: React.FC<OrderFormEmbedProps> = ({
  manufacturerId,
  productCode = '',
  formData = {},
  episodeId,
  productRequestId,
  onComplete,
  onError,
  onOrderFormSubmit,
  existingSubmissionId,
  className = '',
  debug = false
}) => {
  const [submissionId, setSubmissionId] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [prefillData, setPrefillData] = useState<any>(null);
  const [manufacturerConfig, setManufacturerConfig] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);

  // State management with useReducer
  interface State {
    status: 'loading' | 'ready' | 'error' | 'completed' | 'no-template';
    submissionSlug: string | null;
    error: string | null;
    progress: string;
    templateInfo?: {
      templateId: string;
      templateName: string;
    };
  }

  type Action =
    | { type: 'SET_LOADING'; payload: string }
    | { type: 'SET_READY'; payload: { slug: string; templateInfo: State['templateInfo'] } }
    | { type: 'SET_ERROR'; payload: string }
    | { type: 'SET_COMPLETED' }
    | { type: 'SET_NO_TEMPLATE' }
    | { type: 'SET_SUBMISSION_SLUG'; payload: string };

  const initialState: State = {
    status: 'loading',
    submissionSlug: null,
    error: null,
    progress: 'Initializing Order Form...',
    templateInfo: undefined
  };

  const reducer = (state: State, action: Action): State => {
    console.log('🔍 Reducer called with action:', action.type, 'payload:', (action as any).payload);
    console.log('🔍 Current state:', state);

    let newState: State;
    switch (action.type) {
      case 'SET_LOADING':
        newState = { ...state, status: 'loading', progress: (action as any).payload, error: null };
        break;
      case 'SET_READY':
        newState = {
          ...state,
          status: 'ready',
          submissionSlug: (action as any).payload.slug,
          templateInfo: (action as any).payload.templateInfo,
          progress: 'Order form ready!'
        };
        break;
      case 'SET_ERROR':
        newState = { ...state, status: 'error', error: (action as any).payload };
        break;
      case 'SET_COMPLETED':
        newState = { ...state, status: 'completed' };
        break;
      case 'SET_NO_TEMPLATE':
        newState = { ...state, status: 'no-template' };
        break;
      case 'SET_SUBMISSION_SLUG':
        newState = { ...state, submissionSlug: (action as any).payload };
        break;
      default:
        newState = state;
    }

    console.log('🔍 New state after reducer:', newState);
    return newState;
  };

  const [state, dispatch] = useReducer(reducer, initialState);
  const mountedRef = useRef(true);

  // Load ACZ pre-fill data if available
  const loadACZPrefillData = async () => {
    // Check if we have the ACZ template ID (852554) instead of just manufacturer ID
    if (!productRequestId || !manufacturerConfig?.order_form_template_id || manufacturerConfig.order_form_template_id !== 852554) {
      console.log('❌ ACZ pre-fill not available:', {
        productRequestId,
        templateId: manufacturerConfig?.order_form_template_id,
        isACZTemplate: manufacturerConfig?.order_form_template_id === 852554
      });
      return;
    }

    try {
      const response = await api.get(`/api/product-requests/${productRequestId}/order-form-prefill`);
      const data = (response as any).data;

      if (data.success) {
        setPrefillData(data.data);
        console.log('✅ ACZ pre-fill data loaded:', data.data);
      }
    } catch (error) {
      console.error('Error loading ACZ pre-fill data:', error);
    }
  };

  // Load manufacturer configuration with fallback for ACZ
  const loadManufacturerConfig = async () => {
    console.log('🔍 loadManufacturerConfig called with manufacturerId:', manufacturerId);

    try {
      dispatch({ type: 'SET_LOADING', payload: 'Loading manufacturer configuration...' });

      // For ACZ manufacturer (ID: 1), use hardcoded template ID as fallback
      if (manufacturerId === '1') {
        const config = {
          id: 1,
          name: 'ACZ & Associates',
          order_form_template_id: 852554,
          docuseal_template_id: 852554
        };
        setManufacturerConfig(config);
        console.log('✅ Using ACZ fallback configuration:', config);
        return;
      }

      // For other manufacturers, try to load from API
      const response = await api.get(`/api/v1/manufacturers/${manufacturerId}`);
      const config = (response as any).data.data;

      if (!config.order_form_template_id && !config.docuseal_template_id) {
        setError('No order form template configured for this manufacturer');
        return;
      }

      setManufacturerConfig(config);
      console.log('✅ Manufacturer config loaded from API:', config);
    } catch (err: any) {
      console.error('Error loading manufacturer config:', err);

      // If API fails and it's ACZ, use fallback
      if (manufacturerId === '1') {
        const config = {
          id: 1,
          name: 'ACZ & Associates',
          order_form_template_id: 852554,
          docuseal_template_id: 852554
        };
        setManufacturerConfig(config);
        console.log('✅ Using ACZ fallback configuration after API failure:', config);
      } else {
        setError(err.response?.data?.message || 'Failed to load manufacturer configuration');
      }
    }
  };

  // Clean submission creation
  const createSubmission = async () => {
    try {
      dispatch({ type: 'SET_LOADING', payload: 'Finding Order Form template...' });

      // Use manufacturerConfig if available, otherwise try to find template
      let template;

      if (manufacturerConfig) {
        template = {
          docuseal_template_id: manufacturerConfig.order_form_template_id || manufacturerConfig.docuseal_template_id,
          template_name: manufacturerConfig.name || 'Order Form Template'
        };
        console.log('✅ Using manufacturer config:', template);
        console.log('🔍 Template ID type:', typeof template.docuseal_template_id, 'Value:', template.docuseal_template_id);
      } else {
        // Fallback: try to find template via API
        const templateResponse = await api.get<TemplateResponse>(`/api/v1/docuseal/templates/order-form/${manufacturerId}`);

        if (debug) {
          console.log('🔍 Template API Response:', templateResponse);
        }

        // Check for template in both possible response structures
        template = templateResponse.data?.template || templateResponse.template;

        if (!template) {
          console.error('❌ No OrderForm template found:', templateResponse);

          // If we have debug info, log it
          if (templateResponse.debug_info) {
            console.log('🔍 Debug info:', templateResponse.debug_info);
          }

          dispatch({ type: 'SET_NO_TEMPLATE' });
          return;
        }
      }

      dispatch({ type: 'SET_LOADING', payload: 'Getting pre-fill data...' });

      // Step 1: Get pre-fill data from order-form-prefill endpoint
      let prefillDataForSubmission = {};

      if (manufacturerConfig?.order_form_template_id === 852554 && productRequestId) {
        try {
          const prefillResponse = await api.get(`/api/product-requests/${productRequestId}/order-form-prefill`) as any;

          if (debug) {
            console.log('📡 Pre-fill API Response:', prefillResponse);
          }

          // The API response structure is: { success: true, data: { docuSeal_fields: [...] } }
          if (prefillResponse.success && prefillResponse.data?.data?.docuSeal_fields) {
            prefillDataForSubmission = prefillResponse.data.data.docuSeal_fields;
            console.log('✅ Pre-fill data loaded for submission:', prefillDataForSubmission);
          } else {
            console.warn('⚠️ No pre-fill data available, proceeding with empty form');
            if (debug) {
              console.log('🔍 Pre-fill response structure:', {
                success: prefillResponse.success,
                hasData: !!prefillResponse.data,
                hasNestedData: !!prefillResponse.data?.data,
                hasDocuSealFields: !!prefillResponse.data?.data?.docuSeal_fields,
                responseKeys: Object.keys(prefillResponse),
                dataKeys: prefillResponse.data ? Object.keys(prefillResponse.data) : []
              });
            }
          }
        } catch (prefillError) {
          console.warn('⚠️ Failed to load pre-fill data, proceeding with empty form:', prefillError);
        }
      }

      dispatch({ type: 'SET_LOADING', payload: 'Creating Order Form submission...' });

      // Step 2: Create DocuSeal submission with pre-filled data
      const submissionPayload = {
        template_id: String(template.docuseal_template_id),
        manufacturer_id: manufacturerId,
        form_data: prefillDataForSubmission, // Use the pre-fill data we just loaded
        episode_id: episodeId || null
      };

      if (debug) {
        console.log('🚀 Creating Order Form submission:', submissionPayload);
        console.log('🔍 Template details:', {
          template_id: template.docuseal_template_id,
          template_name: template.template_name,
          manufacturer_id: manufacturerId
        });
        console.log('📤 Submission payload:', submissionPayload);
        console.log('🔍 Pre-fill data for submission:', {
          hasPrefillData: !!prefillDataForSubmission,
          prefillDataKeys: Object.keys(prefillDataForSubmission),
          prefillDataCount: Object.keys(prefillDataForSubmission).length,
          sampleFields: Object.entries(prefillDataForSubmission).slice(0, 3)
        });
        console.log('🔍 Full pre-fill data structure:', prefillDataForSubmission);
      }

      const response = await api.post<SubmissionResponse>(
        '/api/v1/docuseal/orderform/create-submission',
        submissionPayload
      );

      if (debug) {
        console.log('📡 Submission API Response:', response);
      }

      if (!mountedRef.current) return;

      // Handle the response from the new OrderForm API endpoint
      if (response.data?.submitter_slug) {
        dispatch({
          type: 'SET_READY',
          payload: {
            slug: response.data.submitter_slug,
            templateInfo: {
              templateId: template.docuseal_template_id,
              templateName: template.template_name
            }
          }
        });

        if (debug) {
          console.log('✅ Order Form submission created:', response.data);
          console.log('Form URL:', `https://docuseal.com/s/${response.data.submitter_slug}`);
        }
      } else if (response.data?.slug) {
        // Alternative response structure
        dispatch({
          type: 'SET_READY',
          payload: {
            slug: response.data.slug,
            templateInfo: {
              templateId: template.docuseal_template_id,
              templateName: template.template_name
            }
          }
        });
      } else {
        throw new Error(response.data?.error || response.error || response.message || 'Failed to create Order Form submission');
      }
    } catch (error: any) {
      console.error('❌ Order Form submission error:', error);

      if (!mountedRef.current) return;

      const errorMessage = error.response?.data?.message ||
                          error.response?.data?.error ||
                          error.message ||
                          'Failed to load Order Form';

      dispatch({ type: 'SET_ERROR', payload: errorMessage });
      onError?.(errorMessage);
    }
  };

  // Handle form completion via postMessage
  const handleMessage = useCallback((event: MessageEvent) => {
    if (event.origin !== 'https://docuseal.com') return;

    try {
      const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;

      console.log('🔍 DocuSeal postMessage received:', data);

      if (data.type === 'docuseal.completed') {
        console.log('✅ DocuSeal form completed via postMessage:', data);

        // Capture submission ID from postMessage
        let capturedSubmissionId = null;

        if (data.payload?.submission_id) {
          capturedSubmissionId = data.payload.submission_id;
        } else if (data.payload?.id) {
          capturedSubmissionId = data.payload.id;
        } else if (data.payload?.uuid) {
          capturedSubmissionId = data.payload.uuid;
        }

        if (capturedSubmissionId) {
          setSubmissionId(capturedSubmissionId);
          console.log('✅ Submission ID captured from postMessage:', capturedSubmissionId);
        }

        dispatch({ type: 'SET_COMPLETED' });
        onComplete?.(data.payload || data);
      }
    } catch (error) {
      console.error('Error handling DocuSeal message:', error);
    }
  }, [onComplete]);

  // Handle order form submission
  const handleOrderFormSubmit = async () => {
    if (!productRequestId || !submissionId) {
      toast.error('Missing required data for submission');
      return;
    }

    try {
      setIsSubmitting(true);

      console.log('🚀 Submitting order form with:', {
        productRequestId,
        submissionId,
        manufacturerId
      });

      const response = await api.post(`/api/product-requests/${productRequestId}/order-form-submit`, {
        submission_id: String(submissionId), // Ensure it's always a string
        manufacturer_id: manufacturerId
      }) as any;

      if (response.success) {
        toast.success('Order form submitted successfully!');
        console.log('✅ Order form submitted:', response);

        // Notify parent component that submission is complete
        onOrderFormSubmit?.(submissionId);

        // Don't automatically close the modal - let user close it manually
        // onComplete?.({ submission_id: submissionId, status: 'submitted' });

      } else {
        toast.error(response.message || 'Failed to submit order form');
        console.error('❌ Order form submission failed:', response);
      }
    } catch (err: any) {
      console.error('❌ Error submitting order form:', err);
      toast.error('Failed to submit order form: ' + (err.response?.data?.message || err.message));
    } finally {
      setIsSubmitting(false);
    }
  };

  // Function to fetch submission slug for existing submissions
  const fetchExistingSubmissionSlug = async (submissionId: string) => {
    try {
      console.log('🔍 Fetching submission slug for existing submission:', submissionId);

      // Call the API to get the submission slug
      const response = await fetch(`/admin/orders/${productRequestId}/order-form-document-url`);
      const data = await response.json();

      if (data.success && data.document_url) {
        // Extract the slug from the document URL
        const urlParts = data.document_url.split('/');
        const slug = urlParts[urlParts.length - 1];
        console.log('✅ Found submission slug:', slug);
        return slug;
      } else {
        console.warn('⚠️ Failed to get submission slug from API, using submission ID as fallback');
        return submissionId; // Fallback to using submission ID as slug
      }
    } catch (error) {
      console.error('❌ Error fetching submission slug:', error);
      return submissionId; // Fallback to using submission ID as slug
    }
  };

  // Initialize component
  useEffect(() => {
    mountedRef.current = true;
    window.addEventListener('message', handleMessage);

    if (existingSubmissionId) {
      console.log('🔍 Loading existing order form submission:', existingSubmissionId);
      // Load existing submission instead of creating new one
      dispatch({ type: 'SET_LOADING', payload: 'Loading existing order form...' });

      // For existing submissions, fetch the submission slug from DocuSeal
      fetchExistingSubmissionSlug(existingSubmissionId).then((slug) => {
        dispatch({
          type: 'SET_SUBMISSION_SLUG',
          payload: slug
        });
        dispatch({ type: 'SET_COMPLETED' }); // Mark as completed since it's existing
        setSubmissionId(existingSubmissionId);
        console.log('✅ Existing order form submission loaded with slug:', slug);
      });
    } else {
      console.log('🔍 Creating new order form submission');
      // Load manufacturer config and create new submission
      loadManufacturerConfig();
    }

    return () => {
      mountedRef.current = false;
      window.removeEventListener('message', handleMessage);
    };
  }, [existingSubmissionId]);

  // Debug effect to track submission ID changes
  useEffect(() => {
    if (debug) {
      console.log('🔍 Submission ID changed:', submissionId);
    }
  }, [submissionId, debug]);

  // Load pre-fill data when manufacturer config is available
  useEffect(() => {
    if (manufacturerConfig) {
      loadACZPrefillData();
    }
  }, [manufacturerConfig]);

  // Trigger createSubmission for new submissions
  useEffect(() => {
    if (!existingSubmissionId && manufacturerConfig && manufacturerConfig.order_form_template_id === 852554) {
      console.log('🔍 Triggering createSubmission for new submission');
      createSubmission();
    }
  }, [existingSubmissionId, manufacturerConfig]);

  // Retry handler
  const handleRetry = () => {
    dispatch({ type: 'SET_LOADING', payload: 'Retrying...' });
    createSubmission();
  };

  // Render loading state
  if (state.status === 'loading') {
    return (
      <div className={`flex flex-col items-center justify-center p-8 min-h-[400px] bg-gray-50 dark:bg-gray-900 rounded-lg ${className}`}>
        <Loader2 className="w-8 h-8 animate-spin text-blue-600 mb-4" />
        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
          Preparing Your Order Form
        </h3>
        <p className="text-gray-600 dark:text-gray-400 text-center">
          {state.progress}
        </p>
        {episodeId && (
          <div className="mt-4 px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm rounded-full">
            FHIR Enhanced
          </div>
        )}
      </div>
    );
  }

  // Render no template state
  if (state.status === 'no-template') {
    return (
      <div className={`flex flex-col items-center justify-center p-8 min-h-[400px] bg-yellow-50 dark:bg-yellow-900 rounded-lg ${className}`}>
        <FileText className="w-12 h-12 text-yellow-600 mb-4" />
        <h3 className="text-lg font-medium text-yellow-900 dark:text-yellow-200 mb-2">
          No Order Form Template Available
        </h3>
        <p className="text-yellow-700 dark:text-yellow-300 text-center mb-4">
          No Order Form template has been configured for manufacturer ID: {manufacturerId}
        </p>
        <p className="text-yellow-600 dark:text-yellow-400 text-sm text-center mb-4">
          Please contact your administrator to set up the required Order Form template.
        </p>
        <div className="text-xs text-yellow-500 text-center">
          <p>Debug: Check the browser console for more details</p>
          <p>You can also visit /test-orderform-templates to see available templates</p>
        </div>
      </div>
    );
  }

  // Render error state
  if (state.status === 'error') {
    return (
      <div className={`flex flex-col items-center justify-center p-8 min-h-[400px] bg-red-50 dark:bg-red-900 rounded-lg ${className}`}>
        <AlertCircle className="w-12 h-12 text-red-600 mb-4" />
        <h3 className="text-lg font-medium text-red-900 dark:text-red-200 mb-2">
          Failed to Load Order Form
        </h3>
        <p className="text-red-700 dark:text-red-300 text-center mb-4">
          {state.error}
        </p>
        <Button onClick={handleRetry} variant="ghost" size="sm">
          Try Again
        </Button>
      </div>
    );
  }

  // Render completed state
  if (state.status === 'completed') {
    console.log('🔍 Rendering completed state with:', {
      submissionId,
      productRequestId,
      state
    });

    return (
      <div className={`flex flex-col items-center justify-center p-8 min-h-[400px] bg-green-50 dark:bg-green-900 rounded-lg ${className}`}>
        <CheckCircle className="w-16 h-16 text-green-600 mb-4" />
        <h3 className="text-lg font-medium text-green-900 dark:text-green-200 mb-2">
          Order Form Completed
        </h3>
        <p className="text-green-700 dark:text-green-300 text-center mb-6">
          Your order form has been successfully submitted.
          {submissionId && (
            <div className="mt-2 text-sm text-green-600">
              Submission ID: {submissionId}
            </div>
          )}
        </p>

        {/* Submission Buttons */}
        {submissionId && productRequestId && (
          <div className="flex flex-col gap-3 w-full max-w-md">
            {existingSubmissionId ? (
              <div className="text-center p-4 bg-green-50 rounded-md border border-green-200">
                <CheckCircle className="w-8 h-8 text-green-600 mx-auto mb-2" />
                <p className="text-green-800 font-medium">Order Form Already Submitted</p>
                <p className="text-green-600 text-sm">Submission ID: {submissionId}</p>
                <p className="text-green-600 text-sm mt-1">This form was previously submitted and cannot be modified.</p>
              </div>
            ) : (
              <Button
                onClick={handleOrderFormSubmit}
                disabled={isSubmitting}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white"
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Submitting...
                  </>
                ) : (
                  <>
                    <Send className="w-4 h-4 mr-2" />
                    Submit Order Form
                  </>
                )}
              </Button>
            )}
          </div>
        )}

        {/* Fallback: Manual submission ID input for testing */}
        {!submissionId && productRequestId && !existingSubmissionId && (
          <div className="flex flex-col gap-3 w-full max-w-md">
            <div className="text-sm text-gray-600 mb-2">
              No submission ID captured. You can manually enter one for testing:
            </div>
            <input
              type="text"
              placeholder="Enter submission ID manually"
              className="px-3 py-2 border border-gray-300 rounded-md"
              onChange={(e) => setSubmissionId(e.target.value)}
            />
            <Button
              onClick={handleOrderFormSubmit}
              disabled={!submissionId || isSubmitting}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white"
            >
              Submit Order Form
            </Button>
          </div>
        )}

        {/* Existing submission info */}
        {existingSubmissionId && !submissionId && (
          <div className="text-center p-4 bg-blue-50 rounded-md border border-blue-200">
            <FileText className="w-8 h-8 text-blue-600 mx-auto mb-2" />
            <p className="text-blue-800 font-medium">Loading Existing Order Form</p>
            <p className="text-blue-600 text-sm">Please wait while we load your previous submission...</p>
          </div>
        )}

        {/* Debug info */}
        {debug && (
          <div className="mt-4 p-3 bg-blue-50 rounded text-xs text-blue-800">
            <div>Debug: submissionId = {submissionId || 'null'}</div>
            <div>Debug: productRequestId = {productRequestId || 'null'}</div>
            <div>Debug: state.status = {state.status}</div>
          </div>
        )}
      </div>
    );
  }

  // Render Docuseal form using official React component
  return (
    <>
      <Card className={className}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <FileText className="w-5 h-5 text-blue-600" />
            Order Form
            {state.templateInfo && (
              <span className="text-sm font-normal text-gray-500">
                ({state.templateInfo.templateName})
              </span>
            )}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {state.submissionSlug ? (
            <div className="space-y-4">
              {debug && (
                <Alert>
                  <AlertDescription>
                    Debug: Submission Slug = {state.submissionSlug}
                  </AlertDescription>
                </Alert>
              )}

              <DocusealForm
                src={`https://docuseal.com/s/${state.submissionSlug}`}
                email={formData.integration_email || formData.patient_email}
                onComplete={(data) => {
                  console.log('🔍 DocusealForm onComplete called with data:', data);
                  console.log('🔍 Current state before dispatch:', state);

                  // Capture submission ID from various possible sources
                  let capturedSubmissionId = null;

                  if (data?.submission_id) {
                    capturedSubmissionId = data.submission_id;
                  } else if (data?.id) {
                    capturedSubmissionId = data.id;
                  } else if (data?.uuid) {
                    capturedSubmissionId = data.uuid;
                  } else if (typeof data === 'string') {
                    // Sometimes DocuSeal just returns the submission ID as a string
                    capturedSubmissionId = data;
                  }

                  if (capturedSubmissionId) {
                    setSubmissionId(capturedSubmissionId);
                    console.log('✅ Submission ID captured from DocusealForm:', capturedSubmissionId);
                  } else {
                    console.warn('⚠️ No submission ID found in completion data:', data);
                    // Generate a fallback submission ID for testing
                    const fallbackId = `order-form-${Date.now()}`;
                    setSubmissionId(fallbackId);
                    console.log('🔄 Using fallback submission ID:', fallbackId);
                  }

                  dispatch({ type: 'SET_COMPLETED' });
                  onComplete?.(data);
                  toast.success('Order Form completed successfully!');

                  console.log('🔍 State after dispatch:', state);
                  console.log('🔍 Submission ID state after set:', capturedSubmissionId);

                  // Don't automatically close the modal - let user close it manually
                  // The modal will stay open until user clicks the close button or submits the form
                }}
                customCss={`
                  .form-container {
                    background-color: #ffffff;
                    border-radius: 0.5rem;
                  }
                  .submit-form-button {
                    background-color: rgb(59 130 246);
                    border-radius: 0.375rem;
                    font-weight: 500;
                  }
                  .submit-form-button:hover {
                    background-color: rgb(37 99 235);
                  }
                  .field-area-active {
                    border-color: rgb(59 130 246);
                  }
                `}
              />
            </div>
          ) : (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
              <span className="ml-3 text-gray-600">Preparing Order Form...</span>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
};
