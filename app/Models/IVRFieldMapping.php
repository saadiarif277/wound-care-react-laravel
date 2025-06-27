<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IVRFieldMapping extends Model
{
    protected $table = 'ivr_field_mappings';

    protected $fillable = [
        'manufacturer_id',
        'template_id',
        'source_field',
        'target_field',
        'confidence',
        'match_type',
        'usage_count',
        'success_rate',
        'last_used_at',
        'created_by',
        'approved_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'confidence' => 'float',
        'usage_count' => 'integer',
        'success_rate' => 'float',
        'last_used_at' => 'datetime',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Manufacturer::class);
    }

    public function incrementUsage(bool $wasSuccessful = true): void
    {
        $this->increment('usage_count');
        $this->last_used_at = now();
        
        // Update success rate
        if ($this->usage_count > 0) {
            $currentSuccesses = ($this->success_rate ?? 0) * ($this->usage_count - 1);
            if ($wasSuccessful) {
                $currentSuccesses++;
            }
            $this->success_rate = round($currentSuccesses / $this->usage_count, 2);
        }
        
        $this->save();
    }

    public function scopeActive($query)
    {
        // All mappings are considered active unless confidence is very low
        return $query->where('confidence', '>=', 0.3);
    }

    public function scopeForManufacturerTemplate($query, int $manufacturerId, string $templateId)
    {
        return $query->where('manufacturer_id', $manufacturerId)
                     ->where('template_id', $templateId)
                     ->active();
    }

    public function scopeHighConfidence($query, float $minConfidence = 0.8)
    {
        return $query->where('confidence', '>=', $minConfidence);
    }
}