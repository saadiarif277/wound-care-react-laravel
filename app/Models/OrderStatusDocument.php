<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_request_id',
        'document_type',
        'file_name',
        'file_path',
        'file_url',
        'file_size',
        'mime_type',
        'uploaded_by',
        'notes',
        'status_type',
        'status_value',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the product request that owns the document.
     */
    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the document type label for display.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return match($this->document_type) {
            'ivr_doc' => 'IVR Document',
            'order_related_doc' => 'Order Related Document',
            default => 'Document'
        };
    }

    /**
     * Get the file URL for display.
     */
    public function getDisplayUrlAttribute(): string
    {
        return $this->file_url ?: asset('storage/' . $this->file_path);
    }

    /**
     * Get the file size in human readable format.
     */
    public function getHumanFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope to filter by document type.
     */
    public function scopeByDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Scope to filter by status type.
     */
    public function scopeByStatusType($query, string $statusType)
    {
        return $query->where('status_type', $statusType);
    }
}
