<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IVRTemplateField extends Model
{
    use HasUuids;

    protected $table = 'ivr_template_fields';

    protected $fillable = [
        'manufacturer_id',
        'template_name',
        'field_name',
        'field_type',
        'is_required',
        'validation_rules',
        'field_metadata',
        'field_order',
        'section',
        'description',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'field_metadata' => 'array',
        'is_required' => 'boolean',
        'field_order' => 'integer',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Manufacturer::class);
    }

    public function scopeForTemplate($query, int $manufacturerId, string $templateName)
    {
        return $query->where('manufacturer_id', $manufacturerId)
                     ->where('template_name', $templateName)
                     ->orderBy('field_order');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeBySection($query, string $section)
    {
        return $query->where('section', $section);
    }
}