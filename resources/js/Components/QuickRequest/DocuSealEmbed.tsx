import React, { useEffect, useState } from 'react';
import { DocusealBuilder } from '@docuseal/react';
import axios from 'axios';

interface DocuSealEmbedProps {
  manufacturerId: string;
  productCode: string;
  onComplete?: (submissionId: string) => void;
  onError?: (error: string) => void;
  className?: string;
  customCss?: string;
}

export const DocuSealEmbed: React.FC<DocuSealEmbedProps> = ({
  manufacturerId,
  productCode,
  onComplete,
  onError,
  className = '',
  customCss = '',
}) => {
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchToken = async () => {
      try {
        // Use the web route which uses standard session auth instead of Sanctum
        const response = await axios.post('/quick-requests/docuseal/generate-builder-token', {
          manufacturerId,
          productCode
        });
        
        // API should respond with JWT generated on backend
        const jwt = response.data.builderToken || response.data.token || response.data.jwt;
        
        if (!jwt) {
          throw new Error('No token received from server');
        }
        
        setToken(jwt);
        setIsLoading(false);
      } catch (err: any) {
        const msg = err.response?.data?.error || err.message || 'An error occurred loading the DocuSeal form';
        console.error('DocuSeal token fetch error:', err);
        setError(msg);
        setIsLoading(false);
        if (onError) {
          onError(msg);
        }
      }
    };

    fetchToken();
  }, [manufacturerId, productCode, onError]);

  if (error) {
    return (
      <div className={`bg-red-50 border border-red-200 rounded-lg p-4 text-center ${className}`}>
        <p className="text-red-800 text-sm">Error loading DocuSeal form</p>
        <p className="text-red-600 text-xs">{error}</p>
      </div>
    );
  }

  if (isLoading || !token) {
    return (
      <div className={`flex items-center justify-center ${className}`} style={{ minHeight: '600px' }}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto" />
          <p className="mt-2 text-sm text-gray-600">Loading IVR form...</p>
        </div>
      </div>
    );
  }

  return (
    <div className={className} style={{ minHeight: '600px' }}>
      <DocusealBuilder 
        token={token}
        customCss={customCss}
        onComplete={(data: any) => {
          console.log('DocuSeal form completed:', data);
          if (onComplete && data.submissionId) {
            onComplete(data.submissionId);
          }
        }}
        onError={(errorInfo: any) => {
          console.error('DocuSeal error:', errorInfo);
          const errorMsg = errorInfo.message || 'An error occurred with the DocuSeal form';
          if (onError) {
            onError(errorMsg);
          }
        }}
      />
    </div>
  );
};
