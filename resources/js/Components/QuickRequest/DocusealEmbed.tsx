import React, { useEffect, useReducer, useRef, useCallback } from 'react';
import { DocusealForm } from '@docuseal/react';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { AlertCircle, Loader2, CheckCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { toast } from 'sonner';
import api from '@/lib/api';

interface DocusealEmbedProps {
  manufacturerId: string;
  templateId?: string;
  productCode: string;
  documentType?: 'IVR' | 'OrderForm';
  formData?: Record<string, any>;
  episodeId?: number;
  onComplete?: (data: any) => void;
  onError?: (error: string) => void;
  className?: string;
  debug?: boolean;
}

interface SubmissionResponse {
  success: boolean;
  slug?: string;
  submission_id?: string;
  template_id?: string;
  error?: string;
  message?: string;
}

/**
 * Modern DocuSeal Embed Component - 2025 Edition
 * 
 * Clean, simple implementation focused on:
 * - Modern React patterns with hooks
 * - Proper TypeScript definitions  
 * - Clean error handling
 * - Simple state management
 * - No complex legacy patterns
 */
export const DocusealEmbed: React.FC<DocusealEmbedProps> = ({
  manufacturerId,
  templateId,
  productCode,
  documentType = 'IVR',
  formData = {},
  episodeId,
  onComplete,
  onError,
  className = '',
  debug = false
}) => {
  // State management with useReducer
  interface State {
    status: 'loading' | 'ready' | 'error' | 'completed';
    submissionSlug: string | null;
    error: string | null;
    progress: string;
  }

  type Action = 
    | { type: 'SET_LOADING'; payload: string }
    | { type: 'SET_READY'; payload: string }
    | { type: 'SET_ERROR'; payload: string }
    | { type: 'SET_COMPLETED' };

  const initialState: State = {
    status: 'loading',
    submissionSlug: null,
    error: null,
    progress: 'Initializing...'
  };

  const reducer = (state: State, action: Action): State => {
    switch (action.type) {
      case 'SET_LOADING':
        return { ...state, status: 'loading', progress: action.payload, error: null };
      case 'SET_READY':
        return { ...state, status: 'ready', submissionSlug: action.payload, progress: 'Form ready!' };
      case 'SET_ERROR':
        return { ...state, status: 'error', error: action.payload };
      case 'SET_COMPLETED':
        return { ...state, status: 'completed' };
      default:
        return state;
    }
  };

  const [state, dispatch] = useReducer(reducer, initialState);
  const mountedRef = useRef(true);

  // Clean submission creation
  const createSubmission = async () => {
    try {
      dispatch({ type: 'SET_LOADING', payload: 'Creating form...' });

      const payload = {
        manufacturerId,
        templateId,
        productCode,
        documentType,
        formData,
        ...(episodeId && { episode_id: episodeId })
      };

      if (debug) {
        console.log('🚀 Creating DocuSeal submission:', payload);
      }

      const response = await api.post<SubmissionResponse>(
        '/api/v1/quick-request/docuseal/test-mapping', 
        payload
      );

      if (!mountedRef.current) return;

      if (response.success && response.data?.slug) {
        dispatch({ type: 'SET_READY', payload: response.data.slug });
        
        if (debug) {
          console.log('✅ DocuSeal submission created:', response.data);
          console.log('Form URL:', response.data.embed_url);
          console.log('Fields mapped:', response.fields_mapped);
          console.log('Mapping method:', response.mapping_method);
        }
      } else if (response.slug) {
        // Fallback for different response structure
        dispatch({ type: 'SET_READY', payload: response.slug });
      } else {
        throw new Error(response.error || response.message || 'Failed to create submission');
      }
    } catch (error: any) {
      console.error('❌ DocuSeal submission error:', error);
      
      if (!mountedRef.current) return;

      const errorMessage = error.response?.data?.message || 
                          error.response?.data?.error || 
                          error.message || 
                          'Failed to load form';

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
          console.log('✅ DocuSeal form completed:', data);
        }
        
        dispatch({ type: 'SET_COMPLETED' });
        onComplete?.(data.payload || data);
      }
    } catch (error) {
      console.error('Error handling DocuSeal message:', error);
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
          Preparing Your Form
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

  // Render error state
  if (state.status === 'error') {
    return (
      <Card className={className}>
        <CardContent className="pt-6">
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <strong>Failed to Load Form:</strong> {state.error}
            </AlertDescription>
          </Alert>
          <Button
            onClick={handleRetry}
            variant="outline"
            className="mt-4"
          >
            <Loader2 className="w-4 h-4 mr-2" />
            Try Again
          </Button>
        </CardContent>
      </Card>
    );
  }

  // Render completed state
  if (state.status === 'completed') {
    return (
      <Card className={className}>
        <CardContent className="pt-6">
          <Alert className="border-green-200 bg-green-50">
            <CheckCircle className="h-4 w-4 text-green-600" />
            <AlertDescription className="text-green-800">
              <strong>Form Completed Successfully!</strong> Your {documentType} has been submitted and saved.
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    );
  }

  // Render DocuSeal form using official React component
  return (
    <Card className={className}>
      <CardHeader>
        <CardTitle>{documentType} Form</CardTitle>
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
                console.log('DocuSeal form completed:', data);
                dispatch({ type: 'SET_COMPLETED' });
                onComplete?.(data);
                toast.success(`${documentType} form completed successfully!`);
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
            <span className="ml-3 text-gray-600">Preparing {documentType} form...</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default DocusealEmbed;


