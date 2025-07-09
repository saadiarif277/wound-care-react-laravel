<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IVRFieldMapping extends Model
{
    use HasFactory;

    protected $table = 'ivr_field_mappings';

    protected $fillable = [
        'manufacturer_id',
        'template_id',
        'source_field',
        'target_field',
        'confidence',
        'match_type',
        'usage_count',
        'success_rate',
        'last_used_at',
        'created_by',
        'approved_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'success_rate' => 'float',
        'confidence' => 'float',
    ];

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
