<?php

namespace App\Services\QuickRequest;

use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\PhiAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickRequestFileService
{
    /**
     * Document types and their storage paths
     */
    private const DOCUMENT_TYPES = [
        'insurance_card_front' => 'phi/insurance-cards/',
        'insurance_card_back' => 'phi/insurance-cards/',
        'face_sheet' => 'phi/face-sheets/',
        'clinical_notes' => 'phi/clinical-notes/',
        'wound_photo' => 'phi/wound-photos/',
        'demographics' => 'phi/demographics/',
        'supporting_docs' => 'phi/supporting-docs/',
        'clinical_documents' => 'phi/clinical-documents/',
    ];

    /**
     * Handle single file uploads (legacy support)
     */
    public function handleFileUploads(
        Request $request,
        ProductRequest $productRequest,
        PatientManufacturerIVREpisode $episode
    ): array {
        $documentMetadata = [];

        foreach (self::DOCUMENT_TYPES as $fieldName => $storagePath) {
            if ($request->hasFile($fieldName)) {
                try {
                    $path = $request->file($fieldName)->store(
                        $storagePath . date('Y/m'),
                        's3-encrypted'
                    );

                    $documentMetadata[$fieldName] = [
                        'path' => $path,
                        'uploaded_at' => now(),
                        'size' => $request->file($fieldName)->getSize(),
                        'mime_type' => $request->file($fieldName)->getMimeType(),
                        'original_name' => $request->file($fieldName)->getClientOriginalName(),
                    ];

                    $this->logFileUpload($fieldName, $path, $productRequest, $episode);

                } catch (\Exception $e) {
                    Log::error('File upload failed', [
                        'field_name' => $fieldName,
                        'error' => $e->getMessage(),
                        'product_request_id' => $productRequest->id
                    ]);

                    $documentMetadata[$fieldName] = [
                        'error' => $e->getMessage(),
                        'upload_failed' => true
                    ];
                }
            }
        }

        return $documentMetadata;
    }

    /**
     * Handle multiple file uploads for a specific document type
     */
    public function handleMultipleFileUploads(
        Request $request,
        ProductRequest $productRequest,
        PatientManufacturerIVREpisode $episode,
        string $documentType
    ): array {
        $documentMetadata = [];
        $uploadedFiles = [];

        if (!$request->hasFile('files')) {
            return ['error' => 'No files provided'];
        }

        $files = $request->file('files');
        $storagePath = self::DOCUMENT_TYPES[$documentType] ?? 'phi/other/';

        foreach ($files as $file) {
            try {
                $path = $file->store(
                    $storagePath . date('Y/m'),
                    's3-encrypted'
                );

                $fileMetadata = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'original_name' => $file->getClientOriginalName(),
                    'document_type' => $documentType,
                ];

                $uploadedFiles[] = $fileMetadata;
                $this->logFileUpload($documentType, $path, $productRequest, $episode);

            } catch (\Exception $e) {
                Log::error('Multiple file upload failed', [
                    'document_type' => $documentType,
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'product_request_id' => $productRequest->id
                ]);

                $documentMetadata['errors'][] = [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ];
            }
        }

        $documentMetadata['files'] = $uploadedFiles;
        return $documentMetadata;
    }

    /**
     * Remove a specific file
     */
    public function removeFile(
        ProductRequest $productRequest,
        PatientManufacturerIVREpisode $episode,
        string $fileId
    ): bool {
        try {
            // Get current document metadata
            $clinicalSummary = $productRequest->clinical_summary ?? [];
            $documents = $clinicalSummary['documents'] ?? [];

            // Find and remove the file
            $fileToRemove = null;
            foreach ($documents as $type => $files) {
                if (is_array($files)) {
                    foreach ($files as $index => $file) {
                        if (isset($file['id']) && $file['id'] === $fileId) {
                            $fileToRemove = $file;
                            unset($documents[$type][$index]);
                            break 2;
                        }
                    }
                }
            }

            if ($fileToRemove) {
                // Update the clinical summary
                $clinicalSummary['documents'] = $documents;
                $productRequest->update(['clinical_summary' => $clinicalSummary]);

                // Log the removal
                Log::info('File removed successfully', [
                    'file_id' => $fileId,
                    'file_name' => $fileToRemove['original_name'] ?? 'Unknown',
                    'product_request_id' => $productRequest->id
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('File removal failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'product_request_id' => $productRequest->id
            ]);
            return false;
        }
    }

    /**
     * Get all uploaded files for a product request
     */
    public function getUploadedFiles(ProductRequest $productRequest): array
    {
        $clinicalSummary = $productRequest->clinical_summary ?? [];
        return $clinicalSummary['documents'] ?? [];
    }

    /**
     * Update product request with document metadata
     */
    public function updateProductRequestWithDocuments(
        ProductRequest $productRequest,
        array $documentMetadata
    ): void {
        if (!empty($documentMetadata)) {
            $clinicalSummary = $productRequest->clinical_summary ?? [];

            // Handle both single file and multiple file uploads
            if (isset($documentMetadata['files'])) {
                // Multiple files upload
                $documentType = $documentMetadata['document_type'] ?? 'unknown';
                $clinicalSummary['documents'][$documentType] = $documentMetadata['files'];
            } else {
                // Single file uploads (legacy)
                $clinicalSummary['documents'] = array_merge(
                    $clinicalSummary['documents'] ?? [],
                    $documentMetadata
                );
            }

            $productRequest->update(['clinical_summary' => $clinicalSummary]);
        }
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload($file, string $documentType): array
    {
        $errors = [];

        // Check file size (10MB max)
        if ($file->getSize() > 10 * 1024 * 1024) {
            $errors[] = 'File size exceeds 10MB limit';
        }

        // Check file type
        $allowedTypes = [
            'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'
        ];

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'File type not supported. Allowed types: ' . implode(', ', $allowedTypes);
        }

        // Check document type
        if (!array_key_exists($documentType, self::DOCUMENT_TYPES)) {
            $errors[] = 'Invalid document type';
        }

        return $errors;
    }

    /**
     * Log file upload for audit purposes
     */
    private function logFileUpload(
        string $documentType,
        string $path,
        ProductRequest $productRequest,
        PatientManufacturerIVREpisode $episode
    ): void {
        PhiAuditService::logCreation('Document', $path, [
            'document_type' => $documentType,
            'patient_fhir_id' => $episode->patient_fhir_id,
            'product_request_id' => $productRequest->id
        ]);

        Log::info('File uploaded successfully', [
            'document_type' => $documentType,
            'file_path' => $path,
            'product_request_id' => $productRequest->id
        ]);
    }
}
