import React, { useState, useEffect } from 'react';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface DocuSealSubmission {
    submission_id: string;
    docuseal_submission_id: string;
    document_type: string;
    status: 'pending' | 'completed' | 'expired' | 'cancelled';
    created_at: string;
    completed_at?: string;
    signing_url?: string;
    download_url?: string;
}

interface SubmissionManagerProps {
    orderId: string;
    onDocumentSign?: (submissionId: string) => void;
    onDocumentDownload?: (submissionId: string) => void;
    className?: string;
}

export default function SubmissionManager({
    orderId,
    onDocumentSign,
    onDocumentDownload,
    className = ''
}: SubmissionManagerProps) {
    const [submissions, setSubmissions] = useState<DocuSealSubmission[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshing, setRefreshing] = useState(false);

    const fetchSubmissions = async () => {
        try {
            const response = await fetch(`/api/v1/admin/docuseal/orders/${orderId}/submissions`, {
                headers: {
                    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.getAttribute('content')}`,
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch submissions');
            }

            const data = await response.json();
            setSubmissions(data.submissions || []);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    const refreshSubmissions = async () => {
        setRefreshing(true);
        await fetchSubmissions();
    };

    const handleSignDocument = (submission: DocuSealSubmission) => {
        if (submission.signing_url) {
            window.open(submission.signing_url, '_blank');
            if (onDocumentSign) {
                onDocumentSign(submission.submission_id);
            }
        }
    };

    const handleDownloadDocument = async (submission: DocuSealSubmission) => {
        if (submission.download_url) {
            window.open(submission.download_url, '_blank');
            if (onDocumentDownload) {
                onDocumentDownload(submission.submission_id);
            }
        }
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            pending: { variant: 'secondary' as const, label: 'Pending' },
            completed: { variant: 'default' as const, label: 'Completed' },
            expired: { variant: 'destructive' as const, label: 'Expired' },
            cancelled: { variant: 'outline' as const, label: 'Cancelled' },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.pending;
        return <Badge variant={config.variant}>{config.label}</Badge>;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    useEffect(() => {
        fetchSubmissions();
    }, [orderId]);

    if (loading) {
        return (
            <div className={`flex items-center justify-center p-8 ${className}`}>
                <div className="flex items-center space-x-2">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                    <span className="text-gray-600">Loading submissions...</span>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className={`bg-red-50 border border-red-200 rounded-md p-4 ${className}`}>
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-red-800">Error Loading Submissions</h3>
                        <div className="mt-2 text-sm text-red-700">
                            <p>{error}</p>
                        </div>
                        <div className="mt-3">
                            <Button variant="outline" size="sm" onClick={fetchSubmissions}>
                                Try Again
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className={className}>
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-medium text-gray-900">Document Submissions</h3>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={refreshSubmissions}
                    disabled={refreshing}
                >
                    {refreshing ? (
                        <>
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 mr-2"></div>
                            Refreshing...
                        </>
                    ) : (
                        'Refresh'
                    )}
                </Button>
            </div>

            {submissions.length === 0 ? (
                <Card>
                    <CardContent className="p-6 text-center">
                        <div className="text-gray-500">
                            <svg className="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p className="text-sm">No document submissions found for this order.</p>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-4">
                    {submissions.map((submission) => (
                        <Card key={submission.submission_id}>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        {submission.document_type.replace(/([A-Z])/g, ' $1').trim()}
                                    </CardTitle>
                                    {getStatusBadge(submission.status)}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p className="text-sm text-gray-500">Created</p>
                                        <p className="text-sm font-medium">{formatDate(submission.created_at)}</p>
                                    </div>
                                    {submission.completed_at && (
                                        <div>
                                            <p className="text-sm text-gray-500">Completed</p>
                                            <p className="text-sm font-medium">{formatDate(submission.completed_at)}</p>
                                        </div>
                                    )}
                                </div>

                                <div className="flex space-x-2">
                                    {submission.status === 'pending' && submission.signing_url && (
                                        <Button
                                            size="sm"
                                            onClick={() => handleSignDocument(submission)}
                                        >
                                            Sign Document
                                        </Button>
                                    )}

                                    {submission.status === 'completed' && submission.download_url && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleDownloadDocument(submission)}
                                        >
                                            Download
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
