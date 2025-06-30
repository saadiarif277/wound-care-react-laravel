<?php

namespace App\Models\Docuseal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'extraction_metadata',
        'last_extracted_at',
        'field_discovery_status',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'extraction_metadata' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_extracted_at' => 'datetime',
    ];

    /**
     * Get the manufacturer that owns this template
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Manufacturer::class, 'manufacturer_id');
    }

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
     * Scope to get templates by manufacturer
     */
    public function scopeByManufacturer($query, $manufacturerId)
    {
        return $query->where('manufacturer_id', $manufacturerId);
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

    /**
     * Get the default template for a specific manufacturer and document type
     */
    public static function getDefaultTemplateForManufacturer(int $manufacturerId, string $documentType): ?self
    {
        return static::active()
            ->byManufacturer($manufacturerId)
            ->byDocumentType($documentType)
            ->default()
            ->first();
    }

    /**
     * Get the field mappings for this template
     */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(\App\Models\TemplateFieldMapping::class, 'template_id');
    }

    /**
     * Get the mapping audit logs for this template
     */
    public function mappingAuditLogs(): HasMany
    {
        return $this->hasMany(\App\Models\MappingAuditLog::class, 'template_id');
    }

    /**
     * Get the user who last mapped this template
     */
    public function lastMappedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'last_mapped_by');
    }

    /**
     * Get active field mappings
     */
    public function activeFieldMappings(): HasMany
    {
        return $this->fieldMappings()->active();
    }

    /**
     * Get field mappings with errors
     */
    public function fieldMappingsWithErrors(): HasMany
    {
        return $this->fieldMappings()->withErrors();
    }

    /**
     * Get field mappings with warnings
     */
    public function fieldMappingsWithWarnings(): HasMany
    {
        return $this->fieldMappings()->withWarnings();
    }

    /**
     * Get mapping coverage percentage
     */
    public function getMappingCoverageAttribute(): float
    {
        return $this->mapping_coverage ?? 0;
    }

    /**
     * Check if template has complete required field mappings
     */
    public function hasCompleteRequiredMappings(): bool
    {
        $requiredCanonicalFields = \App\Models\CanonicalField::required()->count();
        return $this->required_fields_mapped >= $requiredCanonicalFields;
    }

    /**
     * Update mapping statistics
     */
    public function updateMappingStatistics(): void
    {
        $totalFields = $this->fieldMappings()->count();
        $mappedFields = $this->fieldMappings()->whereNotNull('canonical_field_id')->count();
        $requiredFieldsMapped = $this->fieldMappings()
            ->whereHas('canonicalField', function ($query) {
                $query->where('is_required', true);
            })
            ->count();
        $validationErrors = $this->fieldMappings()->withErrors()->count();

        $this->update([
            'total_mapped_fields' => $mappedFields,
            'mapping_coverage' => $totalFields > 0 ? ($mappedFields / $totalFields) * 100 : 0,
            'required_fields_mapped' => $requiredFieldsMapped,
            'validation_errors_count' => $validationErrors,
            'last_mapping_update' => now(),
            'last_mapped_by' => auth()->id(),
        ]);
    }
}
