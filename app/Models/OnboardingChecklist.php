<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OnboardingChecklist extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'entity_id',
        'entity_type',
        'checklist_type',
        'items',
        'total_items',
        'completed_items',
        'completion_percentage',
        'last_activity_at',
    ];

    protected $casts = [
        'items' => 'json',
        'completion_percentage' => 'decimal:2',
        'last_activity_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the parent entity model (organization, facility, or provider).
     */
    public function entity()
    {
        return $this->morphTo();
    }
} 