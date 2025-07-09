<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenceData extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'key',
        'label',
        'metadata',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];
}
