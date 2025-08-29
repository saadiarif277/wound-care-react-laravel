<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use App\Models\Order\OrderStatusDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * File Service for Order Documents
 *
 * Handles all file operations for orders including IVR documents, order forms,
 * and additional status documents. Provides unified file management with
 * proper categorization and metadata tracking.
 */
class FileService
{
    protected FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Get all files associated with an order
     */
    public function getOrderFiles(ProductRequest $order): array
    {
        return [
            'ivr' => [
                'original_url' => $order->ivr_document_url,
                'original_name' => $this->extractFileName($order->ivr_document_url),
                'uploaded_url' => $order->altered_ivr_file_url,
                'uploaded_name' => $order->altered_ivr_file_name,
                'uploaded_at' => $order->altered_ivr_uploaded_at?->toISOString(),
                'uploaded_by' => $order->alteredIvrUploadedBy?->name,
                'active_url' => $order->altered_ivr_file_url ?: $order->ivr_document_url,
                'active_name' => $order->altered_ivr_file_name ?: $this->extractFileName($order->ivr_document_url),
                'has_upload' => !empty($order->altered_ivr_file_path),
                'file_type' => $this->getFileType($order->altered_ivr_file_url ?: $order->ivr_document_url),
                'category' => 'ivr',
            ],
            'order_form' => [
                'original_url' => $order->episode?->order_form_url,
                'original_name' => $this->extractFileName($order->episode?->order_form_url),
                'uploaded_url' => $order->altered_order_form_file_url,
                'uploaded_name' => $order->altered_order_form_file_name,
                'uploaded_at' => $order->altered_order_form_uploaded_at?->toISOString(),
                'uploaded_by' => $order->alteredOrderFormUploadedBy?->name,
                'active_url' => $order->altered_order_form_file_url ?: $order->episode?->order_form_url,
                'active_name' => $order->altered_order_form_file_name ?: $this->extractFileName($order->episode?->order_form_url),
                'has_upload' => !empty($order->altered_order_form_file_path),
                'file_type' => $this->getFileType($order->altered_order_form_file_url ?: $order->episode?->order_form_url),
                'category' => 'order_form',
            ],
        ];
    }

    /**
     * Get all documents associated with an order
     */
    public function getOrderDocuments(ProductRequest $order): array
    {
        $documents = [];

        // Add episode documents if available
        if ($order->episode && isset($order->episode->metadata['documents'])) {
            foreach (($order->episode->metadata['documents'] ?? []) as $doc) {
                $documents[] = [
                    'id' => 'episode_doc_' . ($doc['id'] ?? Str::random(8)),
                    'name' => $doc['name'] ?? 'Unnamed Document',
                    'type' => $this->categorizeDocumentType($doc),
                    'file_type' => $this->getFileType($doc['url'] ?? null),
                    'uploaded_at' => $doc['uploaded_at'] ?? $order->created_at->toISOString(),
                    'uploaded_by' => $doc['uploaded_by'] ?? 'System',
                    'file_size' => $doc['file_size'] ?? null,
                    'url' => $doc['url'] ?? null,
                    'notes' => $doc['notes'] ?? null,
                    'category' => 'episode',
                    'source' => 'episode_metadata',
                ];
            }
        }

        // Add status documents
        $statusDocuments = $order->statusDocuments()->with('uploadedByUser')->get();
        foreach ($statusDocuments as $statusDoc) {
            $documents[] = [
                'id' => 'status_doc_' . $statusDoc->id,
                'name' => $statusDoc->file_name,
                'type' => $this->mapDocumentType($statusDoc->document_type),
                'file_type' => $this->getFileTypeFromMime($statusDoc->mime_type),
                'uploaded_at' => $statusDoc->created_at->toISOString(),
                'uploaded_by' => $statusDoc->uploadedByUser->name ?? 'Admin',
                'file_size' => $statusDoc->human_file_size,
                'url' => $statusDoc->display_url,
                'notes' => $statusDoc->notes,
                'status_type' => $statusDoc->status_type,
                'status_value' => $statusDoc->status_value,
                'category' => 'status',
                'source' => 'status_document',
            ];
        }

        // Add clinical summary documents if available
        $clinicalSummary = $order->clinical_summary ?? [];
        if (isset($clinicalSummary['documents'])) {
            foreach ($clinicalSummary['documents'] as $doc) {
                $documents[] = [
                    'id' => 'clinical_doc_' . ($doc['id'] ?? Str::random(8)),
                    'name' => $doc['name'] ?? 'Clinical Document',
                    'type' => 'clinical_supporting',
                    'file_type' => $this->getFileType($doc['url'] ?? null),
                    'uploaded_at' => $doc['uploaded_at'] ?? $order->created_at->toISOString(),
                    'uploaded_by' => $doc['uploaded_by'] ?? 'System',
                    'file_size' => $doc['file_size'] ?? null,
                    'url' => $doc['url'] ?? null,
                    'notes' => $doc['notes'] ?? null,
                    'category' => 'clinical',
                    'source' => 'clinical_summary',
                ];
            }
        }

        return $documents;
    }

    /**
     * Upload a file for an order
     */
    public function uploadOrderFile(
        ProductRequest $order,
        UploadedFile $file,
        string $fileType,
        ?string $notes = null,
        ?int $uploadedBy = null
    ): array {
        try {
            // Validate file type
            $this->validateFileForOrder($file, $fileType);

            // Upload file using storage service
            $uploadResult = $this->fileStorageService->uploadDocument(
                $file,
                'order_documents',
                null
            );

            // Create status document record
            $statusDocument = OrderStatusDocument::create([
                'order_id' => $order->id,
                'file_name' => $uploadResult['original_name'],
                'file_path' => $uploadResult['path'],
                'display_url' => $uploadResult['url'],
                'mime_type' => $uploadResult['mime_type'],
                'file_size' => $uploadResult['size'],
                'human_file_size' => $this->formatFileSize($uploadResult['size']),
                'document_type' => $this->mapFileTypeToDocumentType($fileType),
                'status_type' => $order->order_status,
                'status_value' => $order->ivr_status,
                'notes' => $notes,
                'uploaded_by' => $uploadedBy,
            ]);

            Log::info('Order file uploaded successfully', [
                'order_id' => $order->id,
                'file_type' => $fileType,
                'file_name' => $uploadResult['original_name'],
                'uploaded_by' => $uploadedBy,
            ]);

            return [
                'success' => true,
                'document' => $statusDocument,
                'file_info' => $uploadResult,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to upload order file', [
                'order_id' => $order->id,
                'file_type' => $fileType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update order file (replace existing file)
     */
    public function updateOrderFile(
        ProductRequest $order,
        UploadedFile $file,
        string $fileType,
        ?string $notes = null,
        ?int $uploadedBy = null
    ): array {
        try {
            // Delete existing file if it exists
            $this->deleteExistingFile($order, $fileType);

            // Upload new file
            return $this->uploadOrderFile($order, $file, $fileType, $notes, $uploadedBy);

        } catch (\Exception $e) {
            Log::error('Failed to update order file', [
                'order_id' => $order->id,
                'file_type' => $fileType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a file from an order
     */
    public function deleteOrderFile(ProductRequest $order, int $documentId): bool
    {
        try {
            $document = OrderStatusDocument::where('order_id', $order->id)
                ->where('id', $documentId)
                ->first();

            if (!$document) {
                return false;
            }

            // Delete from storage
            $this->fileStorageService->deleteFile($document->file_path);

            // Delete from database
            $document->delete();

            Log::info('Order file deleted successfully', [
                'order_id' => $order->id,
                'document_id' => $documentId,
                'file_name' => $document->file_name,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete order file', [
                'order_id' => $order->id,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get file statistics for an order
     */
    public function getOrderFileStats(ProductRequest $order): array
    {
        $documents = $this->getOrderDocuments($order);
        $files = $this->getOrderFiles($order);

        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'file_types' => [],
            'categories' => [],
            'has_ivr_upload' => false,
            'has_order_form_upload' => false,
        ];

        // Count files
        foreach ($files as $file) {
            if ($file['active_url']) {
                $stats['total_files']++;
                $stats['file_types'][$file['file_type']] = ($stats['file_types'][$file['file_type']] ?? 0) + 1;
                $stats['categories'][$file['category']] = ($stats['categories'][$file['category']] ?? 0) + 1;

                if ($file['category'] === 'ivr' && $file['has_upload']) {
                    $stats['has_ivr_upload'] = true;
                }
                if ($file['category'] === 'order_form' && $file['has_upload']) {
                    $stats['has_order_form_upload'] = true;
                }
            }
        }

        // Count documents
        foreach ($documents as $doc) {
            $stats['total_files']++;
            $stats['file_types'][$doc['file_type']] = ($stats['file_types'][$doc['file_type']] ?? 0) + 1;
            $stats['categories'][$doc['category']] = ($stats['categories'][$doc['category']] ?? 0) + 1;

            if (isset($doc['file_size']) && is_numeric($doc['file_size'])) {
                $stats['total_size'] += $doc['file_size'];
            }
        }

        $stats['total_size_formatted'] = $this->formatFileSize($stats['total_size']);

        return $stats;
    }

    /**
     * Validate file for order upload
     */
    private function validateFileForOrder(UploadedFile $file, string $fileType): void
    {
        // Check file size
        if ($file->getSize() > FileStorageService::MAX_FILE_SIZE) {
            throw new \Exception('File size exceeds maximum allowed size of 10MB');
        }

        // Check file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, FileStorageService::ALLOWED_TYPES)) {
            throw new \Exception('File type not allowed. Allowed types: ' . implode(', ', FileStorageService::ALLOWED_TYPES));
        }

        // Validate file type matches expected category
        $this->validateFileTypeCategory($fileType, $extension);
    }

    /**
     * Validate file type matches expected category
     */
    private function validateFileTypeCategory(string $fileType, string $extension): void
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];

        if ($fileType === 'image' && !in_array($extension, $imageTypes)) {
            throw new \Exception('Expected image file but received document');
        }

        if ($fileType === 'document' && !in_array($extension, $documentTypes)) {
            throw new \Exception('Expected document file but received image');
        }
    }

    /**
     * Delete existing file for the given type
     */
    private function deleteExistingFile(ProductRequest $order, string $fileType): void
    {
        $documentType = $this->mapFileTypeToDocumentType($fileType);

        $existingDoc = OrderStatusDocument::where('order_id', $order->id)
            ->where('document_type', $documentType)
            ->latest()
            ->first();

        if ($existingDoc) {
            $this->fileStorageService->deleteFile($existingDoc->file_path);
            $existingDoc->delete();
        }
    }

    /**
     * Map file type to document type
     */
    private function mapFileTypeToDocumentType(string $fileType): string
    {
        return match ($fileType) {
            'ivr' => 'ivr_doc',
            'order_form' => 'order_related_doc',
            'clinical' => 'clinical_supporting',
            default => 'other',
        };
    }

    /**
     * Map document type for display
     */
    private function mapDocumentType(string $documentType): string
    {
        return match ($documentType) {
            'ivr_doc' => 'IVR Document',
            'order_related_doc' => 'Order Form',
            'clinical_supporting' => 'Clinical Supporting',
            default => 'Other Document',
        };
    }

    /**
     * Categorize document type from episode metadata
     */
    private function categorizeDocumentType(array $doc): string
    {
        $type = $doc['type'] ?? 'other';

        return match ($type) {
            'ivr', 'ivr_doc' => 'IVR Document',
            'order_form', 'order_related_doc' => 'Order Form',
            'clinical', 'clinical_supporting' => 'Clinical Supporting',
            default => 'Other Document',
        };
    }

    /**
     * Get file type from URL
     */
    private function getFileType(?string $url): string
    {
        if (!$url) {
            return 'unknown';
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
            return 'image';
        }

        if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Get file type from MIME type
     */
    private function getFileTypeFromMime(?string $mimeType): string
    {
        if (!$mimeType) {
            return 'unknown';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        if (in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Extract filename from URL
     */
    private function extractFileName(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
