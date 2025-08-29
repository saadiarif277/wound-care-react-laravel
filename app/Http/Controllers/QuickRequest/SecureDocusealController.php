<?php

namespace App\Http\Controllers\QuickRequest;

use App\Http\Controllers\Controller;
use App\Services\DocusealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecureDocusealController extends Controller
{
    public function __construct(
        protected DocusealService $docusealService
    ) {}

    /**
     * Create secure submission with RBAC
     */
    public function createSecureSubmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|string',
            'order_id' => 'required|integer|exists:orders,id',
            'episode_id' => 'nullable|integer',
            'prefill_data' => 'required|array'
        ]);

        try {
            $result = $this->docusealService->createSecureSubmission(
                $validated['template_id'],
                $validated['prefill_data'],
                $validated['order_id'],
                $validated['episode_id']
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to create secure submission', [
                'error' => $e->getMessage(),
                'order_id' => $validated['order_id']
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to create submission'
            ], 500);
        }
    }

    /**
     * Download document with RBAC
     */
    public function downloadSecureDocument(Request $request, string $submissionId): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id'
        ]);

        try {
            $documentUrl = $this->docusealService->downloadSecureDocument(
                $submissionId,
                $validated['order_id']
            );

            return response()->json([
                'success' => true,
                'document_url' => $documentUrl
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to download document', [
                'error' => $e->getMessage(),
                'submission_id' => $submissionId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to download document'
            ], 500);
        }
    }

    /**
     * Get document status with RBAC
     */
    public function getDocumentStatus(int $orderId): JsonResponse
    {
        try {
            $status = $this->docusealService->getDocumentStatus($orderId);

            return response()->json([
                'success' => true,
                'documents' => $status
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to get document status', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get document status'
            ], 500);
        }
    }

    /**
     * Get document audit trail
     */
    public function getDocumentAuditTrail(int $orderId): JsonResponse
    {
        // Check permissions
        if (!auth()->user()->can('view-audit-logs')) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient permissions'
            ], 403);
        }

        try {
            $auditTrail = \DB::table('document_audit_trail')
                ->where('order_id', $orderId)
                ->join('users', 'document_audit_trail.user_id', '=', 'users.id')
                ->select(
                    'document_audit_trail.*',
                    'users.name as user_name',
                    'users.email as user_email'
                )
                ->orderBy('document_audit_trail.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'audit_trail' => $auditTrail
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get audit trail', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get audit trail'
            ], 500);
        }
    }
}
