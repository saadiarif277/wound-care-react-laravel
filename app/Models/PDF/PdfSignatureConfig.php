<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfSignatureConfig extends Model
{
    protected $fillable = [
        'template_id',
        'signature_type',
        'page_number',
        'x_position',
        'y_position',
        'width',
        'height',
        'label',
        'is_required',
        'styling'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'styling' => 'array',
        'x_position' => 'float',
        'y_position' => 'float',
        'width' => 'float',
        'height' => 'float'
    ];

    /**
     * Get the template this signature config belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ManufacturerPdfTemplate::class, 'template_id');
    }

    /**
     * Get coordinates as array for PDF rendering
     */
    public function getCoordinates(): array
    {
        return [
            'page' => $this->page_number,
            'x' => $this->x_position,
            'y' => $this->y_position,
            'width' => $this->width,
            'height' => $this->height
        ];
    }

    /**
     * Get absolute pixel coordinates based on page dimensions
     */
    public function getAbsoluteCoordinates(float $pageWidth, float $pageHeight): array
    {
        return [
            'page' => $this->page_number,
            'x' => ($this->x_position / 100) * $pageWidth,
            'y' => ($this->y_position / 100) * $pageHeight,
            'width' => ($this->width / 100) * $pageWidth,
            'height' => ($this->height / 100) * $pageHeight
        ];
    }

    /**
     * Get default styling merged with custom styling
     */
    public function getMergedStyling(): array
    {
        $defaults = [
            'border' => '1px solid #000',
            'background' => 'transparent',
            'padding' => '5px',
            'font_size' => '10px',
            'font_color' => '#000'
        ];

        return array_merge($defaults, $this->styling ?? []);
    }

    /**
     * Scope to get required signatures
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to get signatures by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('signature_type', $type);
    }

    /**
     * Check if this signature type can be signed by user role
     */
    public function canBeSignedByRole(string $role): bool
    {
        $roleSignatureMap = [
            'admin' => ['admin', 'sales_rep'],
            'sales_rep' => ['sales_rep'],
            'provider' => ['provider'],
            'order_manager' => ['provider'],
            'patient' => ['patient']
        ];

        return in_array($this->signature_type, $roleSignatureMap[$role] ?? []);
    }
}