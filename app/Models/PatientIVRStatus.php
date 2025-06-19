<?php

namespace App\Models;

use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatientIVRStatus extends Model
{
    use HasFactory;

    protected $table = 'patient_manufacturer_ivr_episodes';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'patient_id',
        'manufacturer_id',
        'status',
        'ivr_status',
        'verification_date',
        'expiration_date',
        'frequency_days',
        'created_by',
        'completed_at',
        'docuseal_submission_id',
        'docuseal_status',
        'docuseal_completed_at',
        'docuseal_audit_log_url',
        'docuseal_signed_document_url',
        'docuseal_template_id',
        'docuseal_last_synced_at',
    ];

    protected $casts = [
        'verification_date' => 'date',
        'expiration_date' => 'date',
        'completed_at' => 'datetime',
        'docuseal_completed_at' => 'datetime',
        'docuseal_last_synced_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the patient (FHIR Patient - this is a reference ID, not a direct relationship)
     * Note: patient_id is a FHIR Patient ID, not a local database relationship
     */
    public function patient(): BelongsTo
    {
        // This would typically be a FHIR service call, not a direct relationship
        // For now, we'll comment this out since patient_id is a FHIR reference
        // return $this->belongsTo(Patient::class, 'patient_id');

        // Temporary placeholder - in reality, patient data comes from Azure FHIR
        return $this->belongsTo(\App\Models\User::class, 'patient_id');
    }

    /**
     * Get the manufacturer
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    /**
     * Get product requests for the IVR episode
     */
    public function orders()
    {
        return $this->hasMany(\App\Models\Order\ProductRequest::class, 'ivr_episode_id');
    }

    /**
     * Get product requests for the IVR episode (alias for clarity)
     */
    public function productRequests()
    {
        return $this->hasMany(\App\Models\Order\ProductRequest::class, 'ivr_episode_id');
    }

    /**
     * Check if IVR is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiration_date) {
            return true;
        }

        return $this->expiration_date->isPast();
    }

    /**
     * Check if IVR is expiring soon (within 30 days)
     */
    public function isExpiringSoon(): bool
    {
        if (!$this->expiration_date) {
            return false;
        }

        return $this->expiration_date->isBetween(now(), now()->addDays(30));
    }

    /**
     * Calculate next expiration date based on frequency
     */
    public function calculateNextExpirationDate($fromDate = null): \Carbon\Carbon
    {
        $baseDate = $fromDate ? \Carbon\Carbon::parse($fromDate) : now();

        switch ($this->frequency) {
            case 'weekly':
                return $baseDate->addWeek();
            case 'monthly':
                return $baseDate->addMonth();
            case 'quarterly':
                return $baseDate->addMonths(3);
            case 'yearly':
                return $baseDate->addYear();
            default:
                return $baseDate->addMonths(3); // Default to quarterly
        }
    }

    /**
     * Update IVR verification
     */
    public function markAsVerified($docusealSubmissionId = null): void
    {
        $this->last_verified_date = now();
        $this->expiration_date = $this->calculateNextExpirationDate();
        $this->status = 'active';

        if ($docusealSubmissionId) {
            $this->latest_docuseal_submission_id = $docusealSubmissionId;
        }

        $this->save();
    }

    /**
     * Get IVR status for a patient across all manufacturers
     */
    public static function getPatientStatus($patientFhirId)
    {
        return self::where('patient_fhir_id', $patientFhirId)
            ->with('manufacturer')
            ->orderBy('expiration_date')
            ->get();
    }

    /**
     * Get expiring IVRs (within next 30 days)
     */
    public static function getExpiringIVRs($days = 30)
    {
        return self::where('status', 'active')
            ->whereBetween('expiration_date', [now(), now()->addDays($days)])
            ->with('manufacturer')
            ->orderBy('expiration_date')
            ->get();
    }

    /**
     * Get the DocuSeal documents for this IVR episode.
     * TODO: Implement when Document model is available
     */
    // public function docusealDocuments()
    // {
    //     return $this->hasMany(Document::class, 'ivr_episode_id')
    //         ->whereIn('type', ['ivr', 'confirmation']);
    // }
}
