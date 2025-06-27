<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FhirAuditLog extends Model
{
    protected $fillable = [
        'event_type',
        'event_subtype',
        'user_id',
        'fhir_resource',
        'entities',
        'details',
        'recorded_at',
        'azure_fhir_id',
    ];

    protected $casts = [
        'fhir_resource' => 'array',
        'entities' => 'array',
        'details' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for specific event types
     */
    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for specific event subtypes
     */
    public function scopeEventSubtype($query, string $subtype)
    {
        return $query->where('event_subtype', $subtype);
    }

    /**
     * Scope for date range
     */
    public function scopeRecordedBetween($query, $start, $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Get events for a specific entity
     */
    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->whereJsonContains('entities', [
            ['type' => $entityType, 'reference' => $entityId]
        ]);
    }
}
