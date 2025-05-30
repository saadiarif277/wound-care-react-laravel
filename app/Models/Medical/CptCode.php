<?php

namespace App\Models\Medical;

use App\Models\Insurance\PreAuthorization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CptCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'category',
        'subcategory',
        'relative_value_units',
        'is_active',
        'version',
    ];

    protected $casts = [
        'relative_value_units' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Get the pre-authorizations that use this CPT code.
     */
    public function preAuthorizations(): BelongsToMany
    {
        return $this->belongsToMany(
            PreAuthorization::class,
            'pre_authorization_procedure_codes',
            'cpt_code_id',
            'pre_authorization_id'
        )->withPivot(['quantity', 'modifier', 'sequence'])
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
     * Scope for codes by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for codes by subcategory.
     */
    public function scopeBySubcategory($query, $subcategory)
    {
        return $query->where('subcategory', $subcategory);
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

    /**
     * Get formatted display name with RVUs.
     */
    public function getDisplayNameWithRvusAttribute(): string
    {
        $rvus = $this->relative_value_units ? " ({$this->relative_value_units} RVUs)" : '';
        return "{$this->code} - {$this->description}{$rvus}";
    }
}
