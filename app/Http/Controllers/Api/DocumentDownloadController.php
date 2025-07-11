<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Docuseal\DocusealSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DocumentDownloadController extends Controller
{
    /**
     * Get available documents for an order
     */
    public function getOrderDocuments(Request $request, $orderId): JsonResponse
    {
        try {
            $order = Order::with(['episode', 'facility'])->findOrFail($orderId);
            $user = Auth::user();

            // Check permissions
            if (!$order->canUserDownloadDocuments($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to access documents for this order.',
                ], 403);
            }

            $documents = $order->available_documents;

            Log::info('Order documents retrieved', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'document_count' => count($documents),
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $orderId,
                'documents' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order documents', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents.',
            ], 500);
        }
    }

    /**
     * Get available documents for an episode
     */
    public function getEpisodeDocuments(Request $request, $episodeId): JsonResponse
    {
        try {
            $episode = PatientManufacturerIVREpisode::with(['orders', 'orders.facility'])->findOrFail($episodeId);
            $user = Auth::user();

            // Check permissions
            if (!$episode->canUserDownloadDocuments($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to access documents for this episode.',
                ], 403);
            }

            $documents = $episode->available_documents;

            Log::info('Episode documents retrieved', [
                'episode_id' => $episodeId,
                'user_id' => $user->id,
                'document_count' => count($documents),
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episodeId,
                'documents' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve episode documents', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents.',
            ], 500);
        }
    }

    /**
     * Download a specific document by submission ID
     */
    public function downloadDocument(Request $request, $submissionId): JsonResponse
    {
        try {
            $submission = DocusealSubmission::with(['order', 'order.episode', 'order.facility'])->findOrFail($submissionId);
            $user = Auth::user();

            // Check permissions
            if (!$submission->order || !$submission->order->canUserDownloadDocuments($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to download this document.',
                ], 403);
            }

            // Verify document is completed and has URL
            if ($submission->status !== 'completed' || !$submission->document_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document is not available for download.',
                ], 404);
            }

            // Log download attempt
            Log::info('Document download requested', [
                'submission_id' => $submissionId,
                'user_id' => $user->id,
                'document_type' => $submission->document_type,
                'order_id' => $submission->order_id,
            ]);

            // Return download information
            return response()->json([
                'success' => true,
                'download_url' => $submission->document_url,
                'document_type' => $submission->document_type,
                'completed_at' => $submission->completed_at,
                'audit_log_url' => $submission->metadata['audit_log_url'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to download document', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare document for download.',
            ], 500);
        }
    }

    /**
     * Proxy download a document (for additional security/tracking)
     */
    public function proxyDownload(Request $request, $submissionId)
    {
        try {
            $submission = DocusealSubmission::with(['order', 'order.episode', 'order.facility'])->findOrFail($submissionId);
            $user = Auth::user();

            // Check permissions
            if (!$submission->order || !$submission->order->canUserDownloadDocuments($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to download this document.',
                ], 403);
            }

            // Verify document is completed and has URL
            if ($submission->status !== 'completed' || !$submission->document_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document is not available for download.',
                ], 404);
            }

            // Track download
            $this->trackDocumentDownload($submission, $user);

            // Fetch document from DocuSeal
            $response = Http::timeout(30)->get($submission->document_url);

            if (!$response->successful()) {
                Log::error('Failed to fetch document from DocuSeal', [
                    'submission_id' => $submissionId,
                    'url' => $submission->document_url,
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Document temporarily unavailable.',
                ], 502);
            }

            // Determine filename
            $filename = $this->generateDocumentFilename($submission);

            Log::info('Document downloaded successfully', [
                'submission_id' => $submissionId,
                'user_id' => $user->id,
                'filename' => $filename,
            ]);

            // Return the document
            return response($response->body())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, must-revalidate');

        } catch (\Exception $e) {
            Log::error('Failed to proxy download document', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download document.',
            ], 500);
        }
    }

    /**
     * Track document download for audit purposes
     */
    private function trackDocumentDownload(DocusealSubmission $submission, $user): void
    {
        try {
            // Update submission metadata with download tracking
            $metadata = $submission->metadata ?? [];
            $downloads = $metadata['downloads'] ?? [];
            
            $downloads[] = [
                'downloaded_at' => now()->toIso8601String(),
                'downloaded_by' => $user->id,
                'downloaded_by_email' => $user->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ];

            $metadata['downloads'] = $downloads;
            $metadata['download_count'] = count($downloads);
            $metadata['last_downloaded_at'] = now()->toIso8601String();

            $submission->update(['metadata' => $metadata]);

            Log::info('Document download tracked', [
                'submission_id' => $submission->id,
                'user_id' => $user->id,
                'total_downloads' => count($downloads),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track document download', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a descriptive filename for the document
     */
    private function generateDocumentFilename(DocusealSubmission $submission): string
    {
        $order = $submission->order;
        $type = $submission->document_type;
        $date = $submission->completed_at->format('Y-m-d');
        
        $patientId = $order->episode?->patient_display_id ?? 'Unknown';
        $orderNumber = $order->order_number ?? $order->id;

        return sprintf(
            '%s_%s_Order%s_%s.pdf',
            $type,
            $patientId,
            $orderNumber,
            $date
        );
    }
}
