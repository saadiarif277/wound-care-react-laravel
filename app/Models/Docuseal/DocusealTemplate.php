<?php

namespace App\Models\Docuseal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DocusealTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'template_name',
        'docuseal_template_id',
        'manufacturer_id',
        'document_type',
        'is_default',
        'field_mappings',
        'is_active',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the submissions for this template
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(DocusealSubmission::class, 'docuseal_template_id', 'docuseal_template_id');
    }

    /**
     * Scope to get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get templates by document type
     */
    public function scopeByDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Scope to get default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default template for a specific document type
     */
    public static function getDefaultTemplate(string $documentType): ?self
    {
        return static::active()
            ->byDocumentType($documentType)
            ->default()
            ->first();
    }
} 