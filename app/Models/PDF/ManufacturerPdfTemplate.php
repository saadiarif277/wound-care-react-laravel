<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Order\Manufacturer;

class ManufacturerPdfTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'manufacturer_id',
        'template_name',
        'document_type',
        'file_path',
        'azure_container',
        'version',
        'is_active',
        'template_fields',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'template_fields' => 'array',
        'metadata' => 'array'
    ];

    /**
     * Get the manufacturer that owns this template
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Get the field mappings for this template
     */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(PdfFieldMapping::class, 'template_id')
            ->orderBy('display_order');
    }

    /**
     * Get the signature configurations for this template
     */
    public function signatureConfigs(): HasMany
    {
        return $this->hasMany(PdfSignatureConfig::class, 'template_id');
    }

    /**
     * Get all documents generated from this template
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PdfDocument::class, 'template_id');
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
    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Get the latest version of this template type for a manufacturer
     */
    public static function getLatestForManufacturer(int $manufacturerId, string $documentType)
    {
        return static::where('manufacturer_id', $manufacturerId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get the full Azure URL for this template
     */
    public function getAzureUrl(): string
    {
        return sprintf(
            'https://%s.blob.core.windows.net/%s/%s',
            config('azure.storage.account_name'),
            $this->azure_container,
            $this->file_path
        );
    }

    /**
     * Check if this template has all required mappings
     */
    public function hasCompleteMappings(): bool
    {
        if (empty($this->template_fields)) {
            return true; // No fields to map
        }

        $mappedFields = $this->fieldMappings->pluck('pdf_field_name')->toArray();
        $missingFields = array_diff($this->template_fields, $mappedFields);

        return empty($missingFields);
    }
}