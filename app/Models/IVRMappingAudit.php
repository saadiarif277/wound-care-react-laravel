<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IVRMappingAudit extends Model
{
    use HasUuids;

    protected $table = 'ivr_mapping_audit';

    protected $fillable = [
        'mapping_id',
        'manufacturer_id',
        'template_name',
        'fhir_path',
        'ivr_field_name',
        'mapped_value',
        'mapping_strategy',
        'confidence_score',
        'was_successful',
        'error_details',
        'user_id',
        'session_id',
    ];

    protected $casts = [
        'error_details' => 'array',
        'confidence_score' => 'float',
        'was_successful' => 'boolean',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Manufacturer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(IVRFieldMapping::class, 'mapping_id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('was_successful', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('was_successful', false);
    }

    public function scopeForStrategy($query, string $strategy)
    {
        return $query->where('mapping_strategy', $strategy);
    }

    public function scopeRecentForManufacturer($query, int $manufacturerId, int $days = 30)
    {
        return $query->where('manufacturer_id', $manufacturerId)
                     ->where('created_at', '>=', now()->subDays($days));
    }
}