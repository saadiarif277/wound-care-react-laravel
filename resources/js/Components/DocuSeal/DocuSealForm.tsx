import React, { useState, useEffect } from 'react';
import { DocusealForm } from '@docuseal/react';

interface DocuSealFormProps {
    submissionId: string;
    signingUrl: string;
    onComplete?: (data: any) => void;
    onError?: (error: any) => void;
    className?: string;
}

export default function DocuSealFormComponent({
    submissionId,
    signingUrl,
    onComplete,
    onError,
    className = ''
}: DocuSealFormProps) {
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const handleComplete = (data: any) => {
        console.log('DocuSeal form completed:', data);
        if (onComplete) {
            onComplete(data);
        }
    };

    const handleError = (error: any) => {
        console.error('DocuSeal form error:', error);
        setError(error.message || 'An error occurred');
        if (onError) {
            onError(error);
        }
    };

    const handleLoad = () => {
        setIsLoading(false);
    };

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-md p-4">
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-red-800">
                            Error Loading Document
                        </h3>
                        <div className="mt-2 text-sm text-red-700">
                            <p>{error}</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className={`relative ${className}`}>
            {isLoading && (
                <div className="absolute inset-0 bg-gray-50 flex items-center justify-center z-10">
                    <div className="flex items-center space-x-2">
                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        <span className="text-gray-600">Loading document...</span>
                    </div>
                </div>
            )}
            
            <DocusealForm
                src={signingUrl}
                onComplete={handleComplete}
                onError={handleError}
                onLoad={handleLoad}
                className="w-full h-full min-h-[600px]"
            />
        </div>
    );
} 