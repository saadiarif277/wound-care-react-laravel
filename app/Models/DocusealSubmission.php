<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DocusealSubmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'docuseal_submission_id',
        'docuseal_template_id',
        'document_type',
        'status',
        'folder_id',
        'document_url',
        'signing_url',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the order that owns the submission
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the template for this submission
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocusealTemplate::class, 'docuseal_template_id', 'docuseal_template_id');
    }

    /**
     * Scope to get submissions by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending submissions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get completed submissions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get submissions by document type
     */
    public function scopeByDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Check if the submission is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the submission is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Mark the submission as completed
     */
    public function markAsCompleted(string $documentUrl = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'document_url' => $documentUrl ?? $this->document_url,
        ]);
    }
} 