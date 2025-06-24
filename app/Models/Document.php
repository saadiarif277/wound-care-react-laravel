<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'type',
        'name',
        'path',
        'mime_type',
        'size',
        'metadata',
        'fhir_id',
        'status',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'reviewed_at',
    ];

    /**
     * Get the documentable model (Episode, Order, etc.)
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who reviewed this document
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Check if document is reviewed
     */
    public function isReviewed(): bool
    {
        return !is_null($this->reviewed_at) && !is_null($this->reviewed_by);
    }

    /**
     * Get the full storage path for the document
     */
    public function getFullPathAttribute(): string
    {
        return storage_path('app/documents/' . $this->path);
    }

    /**
     * Get the download URL for the document
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('documents.download', $this->id);
    }

    /**
     * Scope for filtering by document type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for reviewed documents
     */
    public function scopeReviewed($query)
    {
        return $query->whereNotNull('reviewed_at')->whereNotNull('reviewed_by');
    }

    /**
     * Scope for pending review documents
     */
    public function scopePendingReview($query)
    {
        return $query->whereNull('reviewed_at');
    }

    /**
     * Get human-readable file size
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension from name
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    /**
     * Check if document is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if document is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Mark document as reviewed
     */
    public function markAsReviewed(User $reviewer): void
    {
        $this->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'status' => 'reviewed',
        ]);
    }

    /**
     * Get display name for document type
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'insurance_verification' => 'Insurance Verification',
            'prescription' => 'Prescription',
            'order_form' => 'Order Form',
            'consent' => 'Consent Form',
            'clinical_note' => 'Clinical Note',
            'lab_result' => 'Lab Result',
            'imaging' => 'Medical Imaging',
            'discharge_summary' => 'Discharge Summary',
            default => ucwords(str_replace('_', ' ', $this->type)),
        };
    }
}
