import React, { useEffect, useState, useCallback } from 'react';

// Declare the DocuSeal builder element
declare global {
    namespace JSX {
        interface IntrinsicElements {
            'docuseal-builder': React.DetailedHTMLProps<React.HTMLAttributes<HTMLElement>, HTMLElement> & {
                'data-token'?: string;
                'data-host'?: string;
                'data-template-id'?: string;
                'data-user-email'?: string;
                'data-integration-email'?: string;
                'data-name'?: string;
                'data-document-urls'?: string;
                'data-folder-name'?: string;
                'data-extract-fields'?: string;
                'data-with-send-button'?: string;
                'data-with-sign-yourself-button'?: string;
                'data-custom-css'?: string;
            };
        }
    }
}

interface DocuSealEmbedProps {
    embedUrl?: string; // Keep for backward compatibility but won't use
    submissionId?: string; // Keep for backward compatibility
    templateId?: string;
    jwtToken?: string;
    userEmail?: string;
    integrationEmail?: string;
    templateName?: string;
    documentUrls?: string[];
    onComplete: (data: any) => void;
    onError?: (error: string) => void;
    onSave?: (data: any) => void;
    onSend?: (data: any) => void;
    className?: string;
}

export const DocuSealEmbed: React.FC<DocuSealEmbedProps> = ({
    embedUrl, // Legacy prop
    submissionId, // Legacy prop
    templateId,
    jwtToken,
    userEmail = 'limitless@mscwoundcare.com', // Default to your DocuSeal account email
    integrationEmail,
    templateName = 'MSC Wound Care IVR Form',
    documentUrls = [],
    onComplete,
    onError,
    onSave,
    onSend,
    className = ''
}) => {
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [isScriptLoaded, setIsScriptLoaded] = useState(false);

    const handleMessage = useCallback((event: MessageEvent) => {
        // Security: Verify the message origin is from DocuSeal
        if (!event.origin.includes('docuseal.com') && !event.origin.includes('api.docuseal.com')) {
            return;
        }

        console.log('DocuSeal builder message received:', event.data);

        // Handle DocuSeal builder events
        if (event.data.type === 'builder.load' || event.data.event === 'load') {
            console.log('DocuSeal builder loaded');
            setIsLoading(false);
        } else if (event.data.type === 'builder.save' || event.data.event === 'save') {
            console.log('DocuSeal template saved:', event.data);
            onSave?.(event.data);
        } else if (event.data.type === 'builder.send' || event.data.event === 'send') {
            console.log('DocuSeal template sent for signing:', event.data);
            onSend?.(event.data);
            onComplete(event.data); // Treat send as completion
        } else if (event.data.type === 'builder.complete' || event.data.event === 'complete') {
            console.log('DocuSeal builder process completed:', event.data);
            onComplete(event.data);
        } else if (event.data.type === 'builder.error' || event.data.event === 'error') {
            const errorMsg = event.data.message || 'An error occurred with the DocuSeal builder';
            console.error('DocuSeal builder error:', errorMsg);
            setError(errorMsg);
            onError?.(errorMsg);
        }
    }, [onComplete, onError, onSave, onSend]);

    useEffect(() => {
        // Load DocuSeal builder script
        const script = document.createElement('script');
        script.src = 'https://cdn.docuseal.com/js/builder.js';
        script.async = true;

        script.onload = () => {
            console.log('DocuSeal builder script loaded');
            setIsScriptLoaded(true);
            setIsLoading(false);
        };

        script.onerror = () => {
            const errorMsg = 'Failed to load DocuSeal builder script';
            console.error(errorMsg);
            setError(errorMsg);
            onError?.(errorMsg);
        };

        // Check if script is already loaded
        const existingScript = document.querySelector('script[src="https://cdn.docuseal.com/js/builder.js"]');
        if (existingScript) {
            setIsScriptLoaded(true);
            setIsLoading(false);
        } else {
            document.head.appendChild(script);
        }

        // Listen for DocuSeal events
        window.addEventListener('message', handleMessage);

        // Set a timeout to remove loading state if no events are received
        const loadingTimeout = setTimeout(() => {
            console.log('DocuSeal builder loading timeout - assuming ready');
            setIsLoading(false);
        }, 10000);

        return () => {
            window.removeEventListener('message', handleMessage);
            clearTimeout(loadingTimeout);
        };
    }, [handleMessage, onError]);

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <svg className="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 className="mt-2 text-sm font-medium text-red-800">Error Loading DocuSeal Builder</h3>
                <p className="mt-1 text-sm text-red-600">{error}</p>
                <div className="mt-4 space-y-2">
                    {jwtToken && <p className="text-xs text-red-500">JWT Token: {jwtToken.substring(0, 50)}...</p>}
                    {templateId && <p className="text-xs text-red-500">Template ID: {templateId}</p>}
                    <button
                        onClick={() => window.location.reload()}
                        className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        Reload Page
                    </button>
                </div>
            </div>
        );
    }

    console.log('Rendering DocuSeal builder with props:', {
        templateId,
        userEmail,
        integrationEmail,
        templateName,
        documentUrls,
        jwtToken: jwtToken ? `${jwtToken.substring(0, 20)}...` : 'none'
    });

    return (
        <div className={`relative ${className}`}>
            {isLoading && (
                <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                        <p className="mt-2 text-sm text-gray-600">Loading DocuSeal Form Builder...</p>
                        <p className="mt-1 text-xs text-gray-500">
                            {isScriptLoaded ? 'Initializing builder...' : 'Loading script...'}
                        </p>
                    </div>
                </div>
            )}

            <div className="docuseal-builder-container border border-gray-200 rounded-lg overflow-hidden">
                {isScriptLoaded && (
                    <docuseal-builder
                        data-token={jwtToken}
                        data-user-email={userEmail}
                        data-integration-email={integrationEmail}
                        data-template-id={templateId}
                        data-name={templateName}
                        data-document-urls={documentUrls.length > 0 ? JSON.stringify(documentUrls) : undefined}
                        data-folder-name="MSC Wound Care"
                        data-extract-fields="true"
                        data-with-send-button="true"
                        data-with-sign-yourself-button="false"
                        data-custom-css={`
                            .builder-container {
                                min-height: 800px;
                            }
                            .btn-primary {
                                background-color: #3b82f6 !important;
                            }
                        `}
                        style={{ minHeight: '800px', width: '100%' }}
                    />
                )}
            </div>

            {/* Debug info */}
            <div className="mt-2 text-xs text-gray-500">
                <details>
                    <summary className="cursor-pointer">Debug Info</summary>
                    <div className="mt-1 space-y-1">
                        <p>Script Loaded: {isScriptLoaded ? 'Yes' : 'No'}</p>
                        <p>Template ID: {templateId || 'None'}</p>
                        <p>User Email: {userEmail}</p>
                        <p>Integration Email: {integrationEmail || 'None'}</p>
                        <p>Template Name: {templateName}</p>
                        <p>Document URLs: {documentUrls.length > 0 ? documentUrls.join(', ') : 'None'}</p>
                        <p>JWT Token: {jwtToken ? `${jwtToken.substring(0, 20)}...` : 'None'}</p>
                        <p>Loading: {isLoading ? 'Yes' : 'No'}</p>
                        {/* Legacy props for backward compatibility */}
                        {embedUrl && <p>Legacy Embed URL: {embedUrl}</p>}
                        {submissionId && <p>Legacy Submission ID: {submissionId}</p>}
                    </div>
                </details>
            </div>
        </div>
    );
};
