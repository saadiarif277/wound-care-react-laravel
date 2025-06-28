<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalField extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'field_name',
        'field_path',
        'data_type',
        'is_required',
        'description',
        'validation_rules',
        'hipaa_flag',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'hipaa_flag' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * Get all template field mappings for this canonical field
     */
    public function templateFieldMappings(): HasMany
    {
        return $this->hasMany(TemplateFieldMapping::class);
    }

    /**
     * Scope to get required fields only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to get fields by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get formatted field display name
     */
    public function getDisplayNameAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->field_name));
    }

    /**
     * Check if field contains PHI
     */
    public function containsPHI(): bool
    {
        return $this->hipaa_flag;
    }

    /**
     * Get validation rules as array
     */
    public function getValidationRulesArray(): array
    {
        return $this->validation_rules ?? [];
    }
}