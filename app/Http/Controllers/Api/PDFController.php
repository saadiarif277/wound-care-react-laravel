<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PDF\PDFMappingService;
use App\Services\PDF\AzurePDFStorageService;
use App\Models\PDF\PdfDocument;
use App\Models\PDF\PdfSignature;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class PDFController extends Controller
{
    private PDFMappingService $pdfService;
    private AzurePDFStorageService $azureService;

    public function __construct(
        PDFMappingService $pdfService,
        AzurePDFStorageService $azureService
    ) {
        $this->pdfService = $pdfService;
        $this->azureService = $azureService;
    }

    /**
     * Generate IVR PDF for episode
     */
    public function generateIVR(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:patient_manufacturer_ivr_episodes,id',
            'for_review' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $episode = PatientManufacturerIVREpisode::with(['manufacturer', 'order'])
                ->findOrFail($request->episode_id);

            // Check permissions
            if (!auth()->user()->can('view', $episode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to access this episode'
                ], 403);
            }

            // Generate PDF
            $pdfDocument = $this->pdfService->generateIVRForReview($episode);

            // Generate secure URL for viewing
            $secureUrl = $this->azureService->generateSecureUrl(
                $pdfDocument->file_path,
                $pdfDocument->azure_container,
                60 // 1 hour expiration
            );

            // Log access
            $pdfDocument->logAccess('generated', auth()->user(), [
                'for_review' => $request->boolean('for_review'),
                'episode_id' => $episode->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $pdfDocument->document_id,
                    'status' => $pdfDocument->status,
                    'url' => $secureUrl,
                    'expires_in' => 3600, // seconds
                    'signature_status' => $pdfDocument->signature_status,
                    'requires_signatures' => $pdfDocument->template->signatureConfigs()
                        ->where('is_required', true)
                        ->pluck('signature_type')
                        ->toArray()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate IVR PDF', [
                'episode_id' => $request->episode_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add signature to PDF
     */
    public function addSignature(Request $request, string $documentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'signature_type' => 'required|in:patient,provider,witness,sales_rep,admin',
            'signature_data' => 'required|string', // Base64 encoded signature
            'signer_name' => 'required|string|max:255',
            'signer_email' => 'nullable|email|max:255',
            'signer_title' => 'nullable|string|max:255',
            'geo_location' => 'nullable|array',
            'geo_location.latitude' => 'nullable|numeric|between:-90,90',
            'geo_location.longitude' => 'nullable|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Find document
            $document = PdfDocument::where('document_id', $documentId)
                ->with(['template.signatureConfigs'])
                ->firstOrFail();

            // Check if document is expired
            if ($document->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document has expired and cannot be signed'
                ], 400);
            }

            // Check if document is already completed
            if ($document->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Document is already completed'
                ], 400);
            }

            // Check if this signature type is allowed
            $signatureConfig = $document->template->signatureConfigs()
                ->where('signature_type', $request->signature_type)
                ->first();

            if (!$signatureConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'This signature type is not configured for this document'
                ], 400);
            }

            // Add signature using service
            $signedPdfPath = $this->pdfService->addSignatureToPDF($document, $request->all());

            // Upload signed PDF
            $signedBlobName = str_replace('.pdf', '_signed.pdf', $document->file_path);
            $signedUrl = $this->azureService->uploadPDF($signedPdfPath, $signedBlobName, [
                'document_id' => $document->document_id,
                'signed_by' => $request->signer_name,
                'signature_type' => $request->signature_type
            ]);

            // Create signature record
            $signature = PdfSignature::create([
                'document_id' => $document->id,
                'user_id' => auth()->id(),
                'signature_type' => $request->signature_type,
                'signer_name' => $request->signer_name,
                'signer_email' => $request->signer_email,
                'signer_title' => $request->signer_title,
                'signature_data' => $request->signature_data,
                'signature_hash' => hash('sha256', $request->signature_data),
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'geo_location' => $request->geo_location,
                'audit_data' => [
                    'browser' => $request->header('User-Agent'),
                    'referrer' => $request->header('referer'),
                    'session_id' => session()->getId()
                ],
                'is_valid' => true
            ]);

            // Update document with new signed version
            $document->update([
                'file_path' => $signedBlobName,
                'azure_blob_url' => $signedUrl
            ]);

            // Update signature status
            $document->updateSignatureStatus();

            // Log access
            $document->logAccess('signed', auth()->user(), [
                'signature_type' => $request->signature_type,
                'signer_name' => $request->signer_name
            ]);

            DB::commit();

            // Generate new secure URL
            $secureUrl = $this->azureService->generateSecureUrl(
                $document->file_path,
                $document->azure_container,
                60
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $document->document_id,
                    'status' => $document->fresh()->status,
                    'url' => $secureUrl,
                    'signature_id' => $signature->id,
                    'signature_status' => $document->fresh()->signature_status,
                    'is_complete' => $document->fresh()->isFullySigned()
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to add signature', [
                'document_id' => $documentId,
                'signature_type' => $request->signature_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add signature: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PDF document status
     */
    public function getStatus(string $documentId): JsonResponse
    {
        try {
            $document = PdfDocument::where('document_id', $documentId)
                ->with(['template.signatureConfigs', 'signatures'])
                ->firstOrFail();

            // Check permissions
            if (!auth()->user()->can('view', $document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to access this document'
                ], 403);
            }

            // Log access
            $document->logAccess('status_check', auth()->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $document->document_id,
                    'status' => $document->status,
                    'document_type' => $document->document_type,
                    'is_expired' => $document->isExpired(),
                    'expires_at' => $document->expires_at?->toISOString(),
                    'signature_status' => $document->signature_status,
                    'signatures' => $document->signatures->map(function ($sig) {
                        return [
                            'type' => $sig->signature_type,
                            'signer_name' => $sig->signer_name,
                            'signed_at' => $sig->signed_at->toISOString(),
                            'is_valid' => $sig->is_valid
                        ];
                    }),
                    'required_signatures' => $document->template->signatureConfigs()
                        ->where('is_required', true)
                        ->pluck('signature_type')
                        ->toArray(),
                    'is_complete' => $document->isFullySigned()
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }
    }

    /**
     * Get secure download URL
     */
    public function getDownloadUrl(string $documentId): JsonResponse
    {
        try {
            $document = PdfDocument::where('document_id', $documentId)->firstOrFail();

            // Check permissions
            if (!auth()->user()->can('download', $document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to download this document'
                ], 403);
            }

            // Generate secure URL
            $secureUrl = $this->azureService->generateSecureUrl(
                $document->file_path,
                $document->azure_container,
                15 // 15 minutes for download
            );

            // Log access
            $document->logAccess('download', auth()->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $secureUrl,
                    'expires_in' => 900, // seconds
                    'filename' => basename($document->file_path),
                    'content_type' => 'application/pdf'
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }
    }

    /**
     * Cancel/expire a document
     */
    public function cancelDocument(string $documentId): JsonResponse
    {
        try {
            $document = PdfDocument::where('document_id', $documentId)->firstOrFail();

            // Check permissions
            if (!auth()->user()->can('cancel', $document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to cancel this document'
                ], 403);
            }

            // Check if already completed
            if ($document->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel a completed document'
                ], 400);
            }

            // Update status
            $document->update([
                'status' => 'cancelled',
                'metadata' => array_merge($document->metadata ?? [], [
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now()->toISOString(),
                    'cancellation_reason' => request('reason', 'User requested cancellation')
                ])
            ]);

            // Log access
            $document->logAccess('cancelled', auth()->user(), [
                'reason' => request('reason')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document cancelled successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }
    }

    /**
     * List documents for an episode
     */
    public function listEpisodeDocuments(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:patient_manufacturer_ivr_episodes,id',
            'status' => 'nullable|in:draft,generated,pending_signature,partially_signed,completed,expired,cancelled',
            'document_type' => 'nullable|in:ivr,order_form,shipping_label,invoice,other'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = PdfDocument::where('episode_id', $request->episode_id)
                ->with(['template', 'signatures', 'generatedBy']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('document_type')) {
                $query->where('document_type', $request->document_type);
            }

            $documents = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $documents->map(function ($doc) {
                    return [
                        'document_id' => $doc->document_id,
                        'document_type' => $doc->document_type,
                        'status' => $doc->status,
                        'template_name' => $doc->template->template_name,
                        'generated_at' => $doc->generated_at?->toISOString(),
                        'expires_at' => $doc->expires_at?->toISOString(),
                        'is_expired' => $doc->isExpired(),
                        'generated_by' => $doc->generatedBy?->name,
                        'signature_count' => $doc->signatures->count(),
                        'is_complete' => $doc->isFullySigned()
                    ];
                })
            ]);

        } catch (Exception $e) {
            Log::error('Failed to list episode documents', [
                'episode_id' => $request->episode_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents'
            ], 500);
        }
    }
}