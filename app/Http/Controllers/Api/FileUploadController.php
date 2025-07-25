<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\QuickRequest\QuickRequestFileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    public function __construct(
        private QuickRequestFileService $fileService
    ) {}

    /**
     * Upload multiple files for a specific document type
     */
    public function uploadMultipleFiles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:clinical_documents,demographics,supporting_docs,insurance_card_front,insurance_card_back,face_sheet,clinical_notes,wound_photo',
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,gif|max:10240', // 10MB max
            'product_request_id' => 'required|integer|exists:product_requests,id',
            'episode_id' => 'required|string|exists:patient_manufacturer_ivr_episodes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productRequest = ProductRequest::findOrFail($request->product_request_id);
            $episode = PatientManufacturerIVREpisode::findOrFail($request->episode_id);

            // Validate user has access to this product request
            if ($productRequest->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to product request'
                ], 403);
            }

            $result = $this->fileService->handleMultipleFileUploads(
                $request,
                $productRequest,
                $episode,
                $request->document_type
            );

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

            // Update product request with new files
            $this->fileService->updateProductRequestWithDocuments($productRequest, $result);

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
                'data' => [
                    'uploaded_files' => $result['files'] ?? [],
                    'errors' => $result['errors'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Multiple file upload failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'product_request_id' => $request->product_request_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a specific file
     */
    public function removeFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|string',
            'product_request_id' => 'required|integer|exists:product_requests,id',
            'episode_id' => 'required|string|exists:patient_manufacturer_ivr_episodes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productRequest = ProductRequest::findOrFail($request->product_request_id);
            $episode = PatientManufacturerIVREpisode::findOrFail($request->episode_id);

            // Validate user has access to this product request
            if ($productRequest->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to product request'
                ], 403);
            }

            $success = $this->fileService->removeFile(
                $productRequest,
                $episode,
                $request->file_id
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'File removed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found or could not be removed'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('File removal failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'product_request_id' => $request->product_request_id,
                'file_id' => $request->file_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all uploaded files for a product request
     */
    public function getUploadedFiles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_request_id' => 'required|integer|exists:product_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productRequest = ProductRequest::findOrFail($request->product_request_id);

            // Validate user has access to this product request
            if ($productRequest->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to product request'
                ], 403);
            }

            $files = $this->fileService->getUploadedFiles($productRequest);

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get uploaded files', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'product_request_id' => $request->product_request_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate file upload (pre-upload validation)
     */
    public function validateFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'document_type' => 'required|string|in:clinical_documents,demographics,supporting_docs,insurance_card_front,insurance_card_back,face_sheet,clinical_notes,wound_photo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $errors = $this->fileService->validateFileUpload(
                $request->file('file'),
                $request->document_type
            );

            return response()->json([
                'success' => empty($errors),
                'data' => [
                    'is_valid' => empty($errors),
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('File validation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate file: ' . $e->getMessage()
            ], 500);
        }
    }
}
