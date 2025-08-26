<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Store a file and return the storage path and URL
     */
    public function storeFile(UploadedFile $file, string $directory = 'documents'): array
    {
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file);
        
        // Store file in the specified directory
        $path = $file->storeAs($directory, $filename, 'public');
        
        // Generate public URL
        $url = Storage::disk('public')->url($path);
        
        return [
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => $url,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension()
        ];
    }

    /**
     * Store multiple files
     */
    public function storeMultipleFiles(array $files, string $directory = 'documents'): array
    {
        $storedFiles = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $storedFiles[] = $this->storeFile($file, $directory);
            }
        }
        
        return $storedFiles;
    }

    /**
     * Delete a file from storage
     */
    public function deleteFile(string $path): bool
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }
        
        return false;
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Clean the basename and limit length
        $cleanBasename = Str::slug($basename);
        $cleanBasename = Str::limit($cleanBasename, 50, '');
        
        // Add timestamp and random string for uniqueness
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return "{$cleanBasename}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get file size in human readable format
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
