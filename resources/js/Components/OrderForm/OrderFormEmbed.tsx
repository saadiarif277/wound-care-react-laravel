import React, { useEffect, useReducer, useRef, useCallback } from 'react';
import { DocusealForm } from '@docuseal/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { AlertCircle, Loader2, CheckCircle, FileText } from 'lucide-react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { toast } from 'sonner';
import api from '@/lib/api';

interface OrderFormEmbedProps {
  manufacturerId: string;
  productCode?: string;
  formData?: Record<string, any>;
  episodeId?: number;
  onComplete?: (data: any) => void;
  onError?: (error: string) => void;
  className?: string;
  debug?: boolean;
}

interface SubmissionResponse {
  success: boolean;
  data?: {
    slug: string;
    embed_url?: string;
  };
  slug?: string;
  error?: string;
  message?: string;
  fields_mapped?: number;
  mapping_method?: string;
}

interface TemplateResponse {
  success: boolean;
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
  onComplete,
  onError,
  className = '',
  debug = false
}) => {
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
    | { type: 'SET_NO_TEMPLATE' };

  const initialState: State = {
    status: 'loading',
    submissionSlug: null,
    error: null,
    progress: 'Initializing Order Form...',
    templateInfo: undefined
  };

  const reducer = (state: State, action: Action): State => {
    switch (action.type) {
      case 'SET_LOADING':
        return { ...state, status: 'loading', progress: action.payload, error: null };
      case 'SET_READY':
        return {
          ...state,
          status: 'ready',
          submissionSlug: action.payload.slug,
          templateInfo: action.payload.templateInfo,
          progress: 'Order form ready!'
        };
      case 'SET_ERROR':
        return { ...state, status: 'error', error: action.payload };
      case 'SET_COMPLETED':
        return { ...state, status: 'completed' };
      case 'SET_NO_TEMPLATE':
        return { ...state, status: 'no-template' };
      default:
        return state;
    }
  };

  const [state, dispatch] = useReducer(reducer, initialState);
  const mountedRef = useRef(true);

  // Clean submission creation
  const createSubmission = async () => {
    try {
      dispatch({ type: 'SET_LOADING', payload: 'Finding Order Form template...' });

                  // First, find the OrderForm template for this manufacturer
      const templateResponse = await api.get<TemplateResponse>(`/api/v1/docuseal/templates/order-form/${manufacturerId}`);

      if (!templateResponse.data?.template) {
        console.error('❌ No OrderForm template found:', templateResponse.data);

        // If we have debug info, log it
        if (templateResponse.data?.debug_info) {
          console.log('🔍 Debug info:', templateResponse.data.debug_info);
        }

        dispatch({ type: 'SET_NO_TEMPLATE' });
        return;
      }

      const template = templateResponse.data.template;

      dispatch({ type: 'SET_LOADING', payload: 'Creating Order Form submission...' });

      const payload = {
        manufacturerId,
        templateId: template.docuseal_template_id,
        productCode,
        documentType: 'OrderForm',
        formData,
        ...(episodeId && { episode_id: episodeId })
      };

      if (debug) {
        console.log('🚀 Creating Order Form submission:', payload);
      }

      const response = await api.post<SubmissionResponse>(
        '/api/v1/quick-request/docuseal/test-mapping',
        payload
      );

      if (!mountedRef.current) return;

      if (response.success && response.data?.slug) {
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

        if (debug) {
          console.log('✅ Order Form submission created:', response.data);
          console.log('Form URL:', response.data.embed_url);
        }
      } else if (response.slug) {
        // Fallback for different response structure
        dispatch({
          type: 'SET_READY',
          payload: {
            slug: response.slug,
            templateInfo: {
              templateId: template.docuseal_template_id,
              templateName: template.template_name
            }
          }
        });
      } else {
        throw new Error(response.error || response.message || 'Failed to create Order Form submission');
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

      if (data.type === 'docuseal.completed') {
        if (debug) {
          console.log('✅ Order Form completed:', data);
        }

        dispatch({ type: 'SET_COMPLETED' });
        onComplete?.(data.payload || data);
      }
    } catch (error) {
      console.error('Error handling Docuseal message:', error);
    }
  }, [debug, onComplete]);

  // Initialize component
  useEffect(() => {
    mountedRef.current = true;
    window.addEventListener('message', handleMessage);
    createSubmission();

    return () => {
      mountedRef.current = false;
      window.removeEventListener('message', handleMessage);
    };
  }, []);

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
    return (
      <div className={`flex flex-col items-center justify-center p-8 min-h-[400px] bg-green-50 dark:bg-green-900 rounded-lg ${className}`}>
        <CheckCircle className="w-16 h-16 text-green-600 mb-4" />
        <h3 className="text-lg font-medium text-green-900 dark:text-green-200 mb-2">
          Order Form Completed
        </h3>
        <p className="text-green-700 dark:text-green-300 text-center">
          Your order form has been successfully submitted.
        </p>
      </div>
    );
  }

  // Render Docuseal form using official React component
  return (
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
                console.log('Order Form completed:', data);
                dispatch({ type: 'SET_COMPLETED' });
                onComplete?.(data);
                toast.success('Order Form completed successfully!');
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
  );
};
