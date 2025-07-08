<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PatientReference extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'patient_references';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patient_fhir_id',
        'patient_display_id',
        'display_metadata',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'display_metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
            
            $model->created_at = now();
        });
    }

    /**
     * Get the tenant that owns the patient reference.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the episodes for the patient.
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class, 'patient_fhir_id', 'patient_fhir_id');
    }

    /**
     * Generate a display ID for a patient.
     */
    public static function generateDisplayId(string $firstName, string $lastName): array
    {
        $firstInit = strtoupper(substr($firstName, 0, 2));
        $lastInit = strtoupper(substr($lastName, 0, 2));
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        return [
            'display_id' => $firstInit . $lastInit . $random,
            'first_init' => $firstInit,
            'last_init' => $lastInit,
            'random' => $random,
        ];
    }
}