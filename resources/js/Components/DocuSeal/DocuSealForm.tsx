import React, { useState, useCallback } from 'react';
import { DocusealForm } from '@docuseal/react';

interface DocuSealCompletionData {
    submissionId: string;
    status: 'completed' | 'signed';
    timestamp: string;
    documentUrl?: string;
}

interface DocuSealError {
    code?: string;
    message: string;
    details?: Record<string, unknown>;
}

interface DocuSealFormProps {
    submissionId: string;
    signingUrl: string;
    onComplete?: (data: DocuSealCompletionData) => void;
    onError?: (error: DocuSealError) => void;
    className?: string;
    ariaLabel?: string;
}

// Proper logging utility instead of console
const logEvent = (level: 'info' | 'error', message: string, data?: Record<string, unknown>) => {
    const logData = {
        timestamp: new Date().toISOString(),
        component: 'DocuSealForm',
        message,
        ...data
    };

    // In production, this would integrate with your logging service
    if (level === 'error') {
        console.error('[DocuSeal Error]', logData);
        // TODO: Send to error tracking service (e.g., Sentry)
    } else {
        // TODO: Send to analytics service
    }
};

export default function DocuSealFormComponent({
    submissionId,
    signingUrl,
    onComplete,
    onError,
    className = '',
    ariaLabel = 'Document signing form'
}: DocuSealFormProps) {
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<DocuSealError | null>(null);

    // Sanitize and validate signing URL for security
    const isValidUrl = useCallback((url: string): boolean => {
        try {
            const parsedUrl = new URL(url);
            // Ensure it's HTTPS and from expected domain
            return parsedUrl.protocol === 'https:' &&
                   (parsedUrl.hostname.includes('docuseal.com') ||
                    parsedUrl.hostname.includes('docuseal.co'));
        } catch {
            return false;
        }
    }, []);

    const handleComplete = useCallback((data: unknown) => {
        try {
            // Validate and type the completion data
            const completionData: DocuSealCompletionData = {
                submissionId,
                status: 'completed',
                timestamp: new Date().toISOString(),
                ...(typeof data === 'object' && data !== null ? data : {})
            };

            logEvent('info', 'Document signing completed', {
                submissionId,
                status: completionData.status
            });

            onComplete?.(completionData);
        } catch (err) {
            const error: DocuSealError = {
                code: 'COMPLETION_HANDLER_ERROR',
                message: 'Failed to process completion data',
                details: { originalData: data }
            };
            handleError(error);
        }
    }, [submissionId, onComplete]);

    const handleError = useCallback((error: unknown) => {
        const formattedError: DocuSealError = {
            code: 'DOCUSEAL_ERROR',
            message: 'An error occurred while processing the document',
            ...(error && typeof error === 'object' ? error : { details: { originalError: error } })
        };

        logEvent('error', 'DocuSeal form error occurred', {
            submissionId,
            error: formattedError
        });

        setError(formattedError);
        onError?.(formattedError);
    }, [submissionId, onError]);

    const handleLoad = useCallback(() => {
        setIsLoading(false);
        logEvent('info', 'Document form loaded successfully', { submissionId });
    }, [submissionId]);

    // Security check for URL
    if (!isValidUrl(signingUrl)) {
        const securityError: DocuSealError = {
            code: 'INVALID_URL',
            message: 'Invalid or insecure document URL provided'
        };

        logEvent('error', 'Security validation failed', {
            submissionId,
            error: securityError
        });

        return (
            <div
                className="bg-red-50 border border-red-200 rounded-md p-4"
                role="alert"
                aria-live="assertive"
            >
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg
                            className="h-5 w-5 text-red-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-red-800">
                            Security Error
                        </h3>
                        <div className="mt-2 text-sm text-red-700">
                            <p>The document URL is invalid or insecure. Please contact support.</p>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div
                className="bg-red-50 border border-red-200 rounded-md p-4"
                role="alert"
                aria-live="assertive"
            >
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg
                            className="h-5 w-5 text-red-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-red-800">
                            Error Loading Document
                        </h3>
                        <div className="mt-2 text-sm text-red-700">
                            <p>{error.message}</p>
                            {error.code && (
                                <p className="text-xs mt-1">Error Code: {error.code}</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div
            className={`relative ${className}`}
            role="main"
            aria-label={ariaLabel}
        >
            {isLoading && (
                <div
                    className="absolute inset-0 bg-gray-50 flex items-center justify-center z-10"
                    aria-live="polite"
                    aria-label="Loading document"
                >
                    <div className="flex items-center space-x-2">
                        <div
                            className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"
                            aria-hidden="true"
                        ></div>
                        <span className="text-gray-600">Loading document...</span>
                    </div>
                </div>
            )}

            <DocusealForm
                src={signingUrl}
                onComplete={handleComplete}
                onLoad={handleLoad}
                className="w-full h-full min-h-[600px]"
                aria-label="Document signing interface"
            />
        </div>
    );
}