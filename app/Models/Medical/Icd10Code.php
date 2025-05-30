<?php

namespace App\Models\Medical;

use App\Models\Insurance\PreAuthorization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Icd10Code extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'category',
        'subcategory',
        'is_billable',
        'is_active',
        'version',
    ];

    protected $casts = [
        'is_billable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the pre-authorizations that use this ICD-10 code.
     */
    public function preAuthorizations(): BelongsToMany
    {
        return $this->belongsToMany(
            PreAuthorization::class,
            'pre_authorization_diagnosis_codes',
            'icd10_code_id',
            'pre_authorization_id'
        )->withPivot(['type', 'sequence'])
          ->withTimestamps();
    }

    /**
     * Scope for active codes only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for billable codes only.
     */
    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    /**
     * Scope for codes by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Search codes by code or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Get formatted display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->description}";
    }
}
