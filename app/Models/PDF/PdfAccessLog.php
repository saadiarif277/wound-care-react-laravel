<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PdfAccessLog extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'context',
        'accessed_at'
    ];

    protected $casts = [
        'context' => 'array',
        'accessed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->accessed_at = $model->accessed_at ?? now();
        });
    }

    /**
     * Get the document this access log belongs to
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PdfDocument::class, 'document_id');
    }

    /**
     * Get the user who accessed the document
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new access log entry
     */
    public static function logAccess(
        int $documentId,
        string $action,
        ?User $user = null,
        array $context = []
    ): self {
        return static::create([
            'document_id' => $documentId,
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'context' => $context
        ]);
    }

    /**
     * Scope to get logs by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get logs for a specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get logs within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('accessed_at', [$startDate, $endDate]);
    }

    /**
     * Get human-readable action description
     */
    public function getActionDescription(): string
    {
        $descriptions = [
            'view' => 'Viewed document',
            'download' => 'Downloaded document',
            'print' => 'Printed document',
            'email' => 'Emailed document',
            'forward' => 'Forwarded document'
        ];

        return $descriptions[$this->action] ?? $this->action;
    }
}