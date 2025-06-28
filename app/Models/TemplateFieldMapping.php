<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Docuseal\DocusealTemplate;

class TemplateFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'field_name',
        'canonical_field_id',
        'transformation_rules',
        'confidence_score',
        'validation_status',
        'validation_messages',
        'is_active',
        'created_by',
        'updated_by',
        'version',
    ];

    protected $casts = [
        'transformation_rules' => 'array',
        'validation_messages' => 'array',
        'is_active' => 'boolean',
        'confidence_score' => 'decimal:2',
        'version' => 'integer',
    ];

    /**
     * Get the template this mapping belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocusealTemplate::class, 'template_id');
    }

    /**
     * Get the canonical field this maps to
     */
    public function canonicalField(): BelongsTo
    {
        return $this->belongsTo(CanonicalField::class);
    }

    /**
     * Get the user who created this mapping
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this mapping
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get active mappings only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get mappings with validation errors
     */
    public function scopeWithErrors($query)
    {
        return $query->where('validation_status', 'error');
    }

    /**
     * Scope to get mappings with warnings
     */
    public function scopeWithWarnings($query)
    {
        return $query->where('validation_status', 'warning');
    }

    /**
     * Scope to get high confidence mappings
     */
    public function scopeHighConfidence($query, $threshold = 80)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Check if mapping has transformation rules
     */
    public function hasTransformationRules(): bool
    {
        return !empty($this->transformation_rules);
    }

    /**
     * Get transformation rules as array
     */
    public function getTransformationRulesArray(): array
    {
        return $this->transformation_rules ?? [];
    }

    /**
     * Check if mapping is valid
     */
    public function isValid(): bool
    {
        return $this->validation_status === 'valid';
    }

    /**
     * Check if mapping has warnings
     */
    public function hasWarnings(): bool
    {
        return $this->validation_status === 'warning';
    }

    /**
     * Check if mapping has errors
     */
    public function hasErrors(): bool
    {
        return $this->validation_status === 'error';
    }

    /**
     * Increment version on update
     */
    protected static function booted()
    {
        static::updating(function ($mapping) {
            $mapping->version++;
        });
    }
}