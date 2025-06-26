<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IVRFieldMapping extends Model
{
    use HasUuids;

    protected $table = 'ivr_field_mappings';

    protected $fillable = [
        'manufacturer_id',
        'template_name',
        'template_version',
        'fhir_path',
        'ivr_field_name',
        'mapping_type',
        'confidence_score',
        'transformation_rules',
        'validation_rules',
        'default_value',
        'is_required',
        'is_active',
        'is_learned',
        'usage_count',
        'success_count',
    ];

    protected $casts = [
        'transformation_rules' => 'array',
        'validation_rules' => 'array',
        'confidence_score' => 'float',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'is_learned' => 'boolean',
        'usage_count' => 'integer',
        'success_count' => 'integer',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Manufacturer::class);
    }

    public function incrementUsage(bool $wasSuccessful = true): void
    {
        $this->increment('usage_count');
        
        if ($wasSuccessful) {
            $this->increment('success_count');
        }
        
        // Update confidence score based on success rate
        if ($this->usage_count >= 10) {
            $this->confidence_score = round($this->success_count / $this->usage_count, 2);
            $this->save();
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForManufacturerTemplate($query, int $manufacturerId, string $templateName)
    {
        return $query->where('manufacturer_id', $manufacturerId)
                     ->where('template_name', $templateName)
                     ->active();
    }

    public function scopeHighConfidence($query, float $minConfidence = 0.8)
    {
        return $query->where('confidence_score', '>=', $minConfidence);
    }
}