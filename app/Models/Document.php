<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'path',
        'url',
        'size',
        'mime_type',
        'extension',
        'documentable_type',
        'documentable_id',
        'document_type',
        'uploaded_by_user_id',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    /**
     * Get the parent documentable model (ProductRequest, Episode, etc.)
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded the document
     */
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Get the file size in human readable format
     */
    public function getFormattedSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get the file type icon/class
     */
    public function getFileTypeAttribute(): string
    {
        if (str_starts_with($this->mime_type, 'image/')) {
            return 'image';
        } elseif ($this->mime_type === 'application/pdf') {
            return 'pdf';
        } elseif (str_starts_with($this->mime_type, 'text/')) {
            return 'document';
        } else {
            return 'document';
        }
    }

    /**
     * Check if the document is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the document is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get a thumbnail URL for images
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->isImage()) {
            // For now, return the same URL. You can implement thumbnail generation later
            return $this->url;
        }
        
        return null;
    }
}
