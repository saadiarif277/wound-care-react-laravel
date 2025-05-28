<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\DocusealSubmission;
use App\Services\DocusealService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

class DocusealController extends Controller
{
    public function __construct(
        private DocusealService $docusealService
    ) {
        $this->middleware('auth');
        $this->middleware('permission:manage-orders');
    }

    /**
     * Generate document from a template
     * POST /api/v1/admin/docuseal/generate-document
     */
    public function generateDocument(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
        ]);

        try {
            $order = Order::where('id', $request->order_id)->firstOrFail();

            // Check if user has permission to access this order
            if (!$this->canAccessOrder($order)) {
                return response()->json([
                    'error' => 'Unauthorized access to order'
                ], 403);
            }

            // Generate documents for the order
            $submissions = $this->docusealService->generateDocumentsForOrder($order);

            if (empty($submissions)) {
                return response()->json([
                    'error' => 'No documents could be generated'
                ], 400);
            }

            // Return the first submission (primary document)
            $submission = $submissions[0];

            return response()->json([
                'submission_id' => $submission->id,
                'docuseal_submission_id' => $submission->docuseal_submission_id,
                'status' => $submission->status,
                'document_url' => $submission->signing_url,
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);

        } catch (Exception $e) {
            Log::error('DocuSeal document generation failed', [
                'order_id' => $request->order_id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Document generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document submission status
     * GET /api/v1/admin/docuseal/submissions/{submission_id}/status
     */
    public function getSubmissionStatus(string $submissionId): JsonResponse
    {
        try {
            $submission = DocusealSubmission::with('order')->findOrFail($submissionId);

            // Check if user has permission to access this submission
            if (!$submission->order || !$this->canAccessOrder($submission->order)) {
                return response()->json([
                    'error' => 'Unauthorized access to submission'
                ], 403);
            }

            // Get latest status from DocuSeal API
            $docusealStatus = $this->docusealService->getSubmissionStatus($submission->docuseal_submission_id);

            // Update local status if different
            if ($docusealStatus['status'] !== $submission->status) {
                $submission->update([
                    'status' => $docusealStatus['status'],
                    'completed_at' => $docusealStatus['status'] === 'completed' ? now() : null,
                ]);
            }

            return response()->json([
                'submission_id' => $submission->id,
                'docuseal_submission_id' => $submission->docuseal_submission_id,
                'status' => $submission->status,
                'completed_at' => $submission->completed_at?->toISOString(),
                'download_url' => $submission->isCompleted()
                    ? route('docuseal.download', $submission->id)
                    : null,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get DocuSeal submission status', [
                'submission_id' => $submissionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get submission status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download completed document
     * GET /api/v1/admin/docuseal/submissions/{submission_id}/download
     */
    public function downloadDocument(string $submissionId)
    {
        try {
            $submission = DocusealSubmission::with('order')->findOrFail($submissionId);

            // Check if user has permission to access this submission
            if (!$submission->order || !$this->canAccessOrder($submission->order)) {
                return response()->json([
                    'error' => 'Unauthorized access to submission'
                ], 403);
            }

            // Check if document is completed
            if (!$submission->isCompleted()) {
                return response()->json([
                    'error' => 'Document is not completed yet'
                ], 400);
            }

            // Get document URL from DocuSeal
            $documentUrl = $this->docusealService->downloadDocument($submission->docuseal_submission_id);

            if (!$documentUrl) {
                return response()->json([
                    'error' => 'Document not available for download'
                ], 404);
            }

            // Redirect to the document URL
            return redirect($documentUrl);

        } catch (Exception $e) {
            Log::error('Failed to download DocuSeal document', [
                'submission_id' => $submissionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to download document',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can access the given order
     */
    private function canAccessOrder(Order $order): bool
    {
        $user = Auth::user();

        // MSC Admins can access all orders
        if ($user->hasPermission('manage-all-organizations')) {
            return true;
        }

        // Office Managers can only access orders from their facility
        if ($user->hasPermission('manage-orders')) {
            // Security-critical: Ensure user can only access orders from their organization
            return $user->organization_id === $order->organization_id;
        }

        return false;
    }
}



