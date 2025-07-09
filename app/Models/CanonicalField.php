<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CanonicalField extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'field_name',
        'description',
        'data_type',
        'validation_rules',
        'is_required',
        'is_phi',
        'fhir_path',
        'example_value',
        'source_system',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_phi' => 'boolean',
    ];
} 