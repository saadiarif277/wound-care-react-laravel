<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OnboardingDocument extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'entity_id',
        'entity_type',
        'document_type',
        'document_name',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'expiration_date',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'expiration_date' => 'date',
        'file_size' => 'integer', // Or string, depending on how it's stored
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

    public function uploadedByUser()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
