<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class OrderDocument extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'order_id',
        'type',
        'name',
        'file_path',
        'mime_type',
        'size',
        'uploaded_by',
        'completed_at',
        'expires_at',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that owns the document.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the document URL.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get the document download URL.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('api.orders.documents.view', [$this->order_id, $this->id]);
    }

    /**
     * Check if the document is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the document is completed.
     */
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    /**
     * Scope a query to only include IVR forms.
     */
    public function scopeIvrForms($query)
    {
        return $query->where('type', 'ivr_form');
    }

    /**
     * Scope a query to only include order forms.
     */
    public function scopeOrderForms($query)
    {
        return $query->where('type', 'order_form');
    }

    /**
     * Scope a query to only include clinical documents.
     */
    public function scopeClinical($query)
    {
        return $query->whereIn('type', ['wound_photo', 'clinical_notes', 'assessment', 'prescription']);
    }

    /**
     * Scope a query to only include completed documents.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Scope a query to only include non-expired documents.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}