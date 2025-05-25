<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Aws\S3\S3Client;
use Exception;

/**
 * File Storage Service for Non-PHI Documents
 *
 * This service handles file uploads to Supabase Storage.
 * IMPORTANT: Only use for non-PHI documents. All PHI files must go to Azure Health Data Services.
 */
class FileStorageService
{
    /**
     * Allowed file types for non-PHI documents
     */
    const ALLOWED_TYPES = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'svg'
    ];

    /**
     * Maximum file size in bytes (10MB)
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Upload a non-PHI document to Supabase Storage
     *
     * @param UploadedFile $file
     * @param string $category Category/folder (documents, reports, exports)
     * @param string|null $customName Custom filename (optional)
     * @return array File information including URL and metadata
     * @throws Exception
     */
    public function uploadDocument(UploadedFile $file, string $category = 'documents', ?string $customName = null): array
    {
        $this->validateFile($file);

        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();
        $fileName = $customName ? $customName . '.' . $extension : Str::uuid() . '.' . $extension;

        // Organize files by category and date
        $path = "{$category}/" . date('Y/m/d') . "/{$fileName}";

        try {
            // Upload to Supabase Storage
            $uploaded = Storage::disk('supabase')->putFileAs(
                $category . '/' . date('Y/m/d'),
                $file,
                $fileName
            );

            if (!$uploaded) {
                throw new Exception('Failed to upload file to storage');
            }

            $url = $this->getFileUrl($path);

            return [
                'success' => true,
                'path' => $path,
                'url' => $url,
                'original_name' => $originalName,
                'file_name' => $fileName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'category' => $category,
                'uploaded_at' => now()->toISOString(),
            ];

        } catch (Exception $e) {
            throw new Exception("File upload failed: " . $e->getMessage());
        }
    }

    /**
     * Delete a file from Supabase Storage
     *
     * @param string $path File path or URL
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            // If it's a URL, extract the path
            if (str_contains($path, 'http')) {
                $path = $this->extractPathFromUrl($path);
            }

            return Storage::disk('supabase')->delete($path);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if a file exists in storage
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            return Storage::disk('supabase')->exists($path);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get file contents
     *
     * @param string $path
     * @return string|null
     */
    public function getFileContents(string $path): ?string
    {
        try {
            return Storage::disk('supabase')->get($path);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate a signed URL for temporary access
     *
     * @param string $path
     * @param int $expiresInMinutes
     * @return string|null
     */
    public function getSignedUrl(string $path, int $expiresInMinutes = 60): ?string
    {
        try {
            $config = config('filesystems.disks.supabase');

            // Create S3Client directly for presigned URLs
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => $config['use_path_style_endpoint'],
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
            ]);

            $command = $s3Client->getCommand('GetObject', [
                'Bucket' => $config['bucket'],
                'Key' => $path,
            ]);

            $request = $s3Client->createPresignedRequest($command, "+{$expiresInMinutes} minutes");

            return (string) $request->getUri();
        } catch (Exception $e) {
            // Fallback to regular URL if signed URL generation fails
            return $this->getFileUrl($path);
        }
    }

    /**
     * List files in a directory
     *
     * @param string $directory
     * @return array
     */
    public function listFiles(string $directory = ''): array
    {
        try {
            return Storage::disk('supabase')->files($directory);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get file URL
     *
     * @param string $path
     * @return string
     */
    private function getFileUrl(string $path): string
    {
        try {
            // Construct URL manually for Supabase
            $baseUrl = config('filesystems.disks.supabase.url', env('SUPABASE_S3_URL'));
            $bucket = config('filesystems.disks.supabase.bucket', env('SUPABASE_S3_BUCKET'));

            if ($baseUrl) {
                return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
            }

            // Final fallback: construct from endpoint
            $endpoint = config('filesystems.disks.supabase.endpoint', env('SUPABASE_S3_ENDPOINT'));
            if ($endpoint) {
                $publicEndpoint = str_replace('/storage/v1/s3', '/storage/v1/object/public', $endpoint);
                return $publicEndpoint . '/' . $bucket . '/' . ltrim($path, '/');
            }

            return $path; // Return path as fallback
        } catch (Exception $e) {
            return $path;
        }
    }

    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @throws Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new Exception('Invalid file upload');
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new Exception('File size exceeds limit (' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB)');
        }

        // Check file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_TYPES)) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', self::ALLOWED_TYPES));
        }

        // Basic security check - ensure no executable extensions
        $dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'sh', 'py', 'rb', 'js', 'html', 'htm'];
        if (in_array($extension, $dangerousExtensions)) {
            throw new Exception('File type not allowed for security reasons');
        }
    }

    /**
     * Extract file path from Supabase Storage URL
     *
     * @param string $url
     * @return string
     */
    private function extractPathFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        // Remove the Supabase storage URL prefix
        $path = preg_replace('/^\/storage\/v1\/object\/(public\/)?/', '', $path);

        // Remove bucket name if present
        $bucket = config('filesystems.disks.supabase.bucket', env('SUPABASE_S3_BUCKET'));
        if ($bucket && str_starts_with($path, $bucket . '/')) {
            $path = substr($path, strlen($bucket) + 1);
        }

        return $path;
    }

    /**
     * Get file metadata
     *
     * @param string $path
     * @return array|null
     */
    public function getFileMetadata(string $path): ?array
    {
        try {
            $disk = Storage::disk('supabase');

            $size = $disk->size($path);
            $lastModified = $disk->lastModified($path);

            // MIME type detection not available for S3 storage, use fallback
            $mimeType = 'application/octet-stream';

            return [
                'path' => $path,
                'size' => $size,
                'last_modified' => date('Y-m-d H:i:s', $lastModified),
                'mime_type' => $mimeType,
                'url' => $this->getFileUrl($path),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate that file content doesn't contain PHI
     * Basic check - you should implement more sophisticated PHI detection
     *
     * @param UploadedFile $file
     * @return bool
     * @throws Exception
     */
    public function validateNonPHI(UploadedFile $file): bool
    {
        // For text files, do basic PHI pattern detection
        if (in_array($file->getMimeType(), ['text/plain', 'text/csv'])) {
            $content = file_get_contents($file->getPathname());

            // Basic PHI patterns (you should expand this)
            $phiPatterns = [
                '/\b\d{3}-\d{2}-\d{4}\b/', // SSN
                '/\b\d{2}\/\d{2}\/\d{4}\b/', // Dates that might be DOB
                '/\bDOB\s*:?\s*\d/', // DOB labels
                '/\bSSN\s*:?\s*\d/', // SSN labels
                '/\bmedical record\b/i',
                '/\bpatient id\b/i',
            ];

            foreach ($phiPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    throw new Exception('File appears to contain PHI data. PHI files must be stored in Azure Health Data Services.');
                }
            }
        }

        return true;
    }
}
