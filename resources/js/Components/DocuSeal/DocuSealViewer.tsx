import React, { useEffect, useState } from 'react';
import { DocusealForm } from '@docuseal/react';
import { Loader2, FileText, AlertCircle } from 'lucide-react';
import axios from 'axios';

interface DocuSealViewerProps {
  templateId: string;
  folderId?: string;
  submissionId?: string;
  email?: string;
  fields?: Record<string, any>;
  onComplete?: (data: any) => void;
  onLoad?: () => void;
  onError?: (error: any) => void;
  mode?: 'fill' | 'review' | 'sign';
  className?: string;
  name?: string;
  orderId?: string;
  isDemo?: boolean;
}

export const DocuSealViewer: React.FC<DocuSealViewerProps> = ({
  templateId,
  folderId,
  submissionId,
  email = 'noreply@mscwound.com',
  fields = {},
  onComplete,
  onLoad,
  onError,
  mode = 'fill',
  className = '',
  name = 'Document Form',
  orderId,
  isDemo = false,
}) => {
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [embedUrl, setEmbedUrl] = useState<string | null>(null);

  useEffect(() => {
    const createSubmissionAndGetUrl = async () => {
      try {
        setIsLoading(true);
        setError(null);

        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Use demo endpoint if isDemo is true
        const endpoint = isDemo ? '/docuseal/demo/create-submission' : '/docuseal/create-submission';
        
        console.log('CSRF token:', csrfToken);
        console.log('Making request to:', endpoint);
        console.log('Is demo:', isDemo);

        // Create DocuSeal submission with pre-filled data
        const response = await axios.post(endpoint, {
          template_id: templateId,
          email: email,
          name: name,
          fields: fields,
          send_email: false, // Don't send email for embedded forms
          order_id: orderId,
        }, {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken || '',
          },
          withCredentials: true, // Important for cookie-based auth
        });

        console.log('DocuSeal response:', response.data);
        
        const { signing_url, submission_id, submitter_slug, data } = response.data;

        if (signing_url) {
          setEmbedUrl(signing_url);
        } else if (submitter_slug) {
          // Construct URL from slug if no direct URL provided
          setEmbedUrl(`https://docuseal.com/s/${submitter_slug}`);
        } else {
          console.error('No signing URL or slug in response:', response.data);
          throw new Error('No signing URL received from server');
        }

      } catch (err: any) {
        console.error('Failed to create DocuSeal submission:', err);
        console.error('Error response:', err.response?.data);
        console.error('Error status:', err.response?.status);
        
        // Handle 422 error specifically
        if (err.response?.status === 422) {
          const errorDetails = err.response?.data?.error || err.response?.data?.message || 'Invalid submission data';
          setError(`Validation error: ${errorDetails}. Please check that all required fields are provided with correct field names.`);
        } else {
          const errorMessage = err.response?.data?.message || err.response?.data?.error || 'Failed to load DocuSeal form';
          setError(errorMessage);
        }
        
        if (onError) {
          onError(err);
        }
      } finally {
        setIsLoading(false);
      }
    };

    if (templateId && !submissionId) {
      createSubmissionAndGetUrl();
    } else if (submissionId) {
      // For reviewing existing submissions, use the submission ID directly
      setEmbedUrl(`https://docuseal.com/s/${submissionId}`);
      setIsLoading(false);
    }
  }, [templateId, submissionId, email, name, orderId]);

  const handleLoad = () => {
    setIsLoading(false);
    if (onLoad) {
      onLoad();
    }
  };

  const handleError = (err: any) => {
    setIsLoading(false);
    setError('Failed to load DocuSeal form');
    if (onError) {
      onError(err);
    }
  };

  const handleComplete = (data: any) => {
    console.log('DocuSeal form completed:', data);
    if (onComplete) {
      onComplete(data);
    }
  };

  // Custom styles for DocuSeal iframe
  const customCss = `
    .docuseal-form {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .docuseal-submit-button {
      background-color: #3B82F6;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 500;
    }
    .docuseal-submit-button:hover {
      background-color: #2563EB;
    }
  `;

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center p-8 bg-red-50 rounded-lg">
        <AlertCircle className="w-12 h-12 text-red-500 mb-4" />
        <p className="text-red-700 font-medium">{error}</p>
      </div>
    );
  }

  if (!embedUrl) {
    return (
      <div className="flex items-center justify-center p-8">
        <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
      </div>
    );
  }

  return (
    <div className={`relative ${className}`}>
      {isLoading && (
        <div className="absolute inset-0 flex items-center justify-center bg-white bg-opacity-90 z-10">
          <div className="flex flex-col items-center">
            <Loader2 className="w-8 h-8 animate-spin text-blue-600 mb-4" />
            <p className="text-gray-600">Loading DocuSeal form...</p>
          </div>
        </div>
      )}
      
      <DocusealForm
        src={embedUrl}
        email={email}
        values={fields}
        customCss={customCss}
        onComplete={handleComplete}
        onLoad={handleLoad}
        onError={handleError}
      />
    </div>
  );
};