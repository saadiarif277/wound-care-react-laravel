<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MedicareMacValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'validation_id',
        'order_id',
        'patient_fhir_id',
        'facility_id',
        'mac_contractor',
        'mac_jurisdiction',
        'mac_region',
        'validation_type',
        'validation_status',
        'validation_results',
        'coverage_policies',
        'coverage_met',
        'coverage_notes',
        'coverage_requirements',
        'procedures_validated',
        'cpt_codes_validated',
        'hcpcs_codes_validated',
        'icd10_codes_validated',
        'documentation_complete',
        'required_documentation',
        'missing_documentation',
        'documentation_status',
        'frequency_compliant',
        'frequency_notes',
        'medical_necessity_met',
        'medical_necessity_notes',
        'prior_auth_required',
        'prior_auth_obtained',
        'prior_auth_number',
        'prior_auth_expiry',
        'billing_compliant',
        'billing_issues',
        'estimated_reimbursement',
        'reimbursement_risk',
        'validated_at',
        'last_revalidated_at',
        'next_validation_due',
        'validated_by',
        'validation_source',
        'validation_errors',
        'validation_warnings',
        'daily_monitoring_enabled',
        'last_monitored_at',
        'validation_count',
        'audit_trail',
        'provider_specialty',
        'provider_npi',
        'specialty_requirements',
    ];

    protected $casts = [
        'validation_results' => 'array',
        'coverage_policies' => 'array',
        'coverage_met' => 'boolean',
        'coverage_requirements' => 'array',
        'procedures_validated' => 'array',
        'cpt_codes_validated' => 'array',
        'hcpcs_codes_validated' => 'array',
        'icd10_codes_validated' => 'array',
        'documentation_complete' => 'boolean',
        'required_documentation' => 'array',
        'missing_documentation' => 'array',
        'documentation_status' => 'array',
        'frequency_compliant' => 'boolean',
        'medical_necessity_met' => 'boolean',
        'prior_auth_required' => 'boolean',
        'prior_auth_obtained' => 'boolean',
        'prior_auth_expiry' => 'date',
        'billing_compliant' => 'boolean',
        'billing_issues' => 'array',
        'estimated_reimbursement' => 'decimal:2',
        'validated_at' => 'datetime',
        'last_revalidated_at' => 'datetime',
        'next_validation_due' => 'datetime',
        'validation_errors' => 'array',
        'validation_warnings' => 'array',
        'daily_monitoring_enabled' => 'boolean',
        'last_monitored_at' => 'datetime',
        'validation_count' => 'integer',
        'audit_trail' => 'array',
        'specialty_requirements' => 'array',
    ];

    /**
     * Generate UUID for validation_id on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->validation_id)) {
                $model->validation_id = Str::uuid();
            }
        });
    }

    /**
     * The order this validation belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the patient FHIR ID for this validation
     */
    public function getPatientFhirId(): ?string
    {
        return $this->patient_fhir_id ?? $this->order->patient_fhir_id;
    }

    /**
     * The facility this validation belongs to
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Scope to get validations that are pending
     */
    public function scopePending($query)
    {
        return $query->where('validation_status', 'pending');
    }

    /**
     * Scope to get validations that passed
     */
    public function scopeValidated($query)
    {
        return $query->where('validation_status', 'validated');
    }

    /**
     * Scope to get validations that failed
     */
    public function scopeFailed($query)
    {
        return $query->where('validation_status', 'failed');
    }

    /**
     * Scope to get validations requiring review
     */
    public function scopeRequiresReview($query)
    {
        return $query->where('validation_status', 'requires_review');
    }

    /**
     * Scope to get validations for daily monitoring
     */
    public function scopeDailyMonitoring($query)
    {
        return $query->where('daily_monitoring_enabled', true);
    }

    /**
     * Scope to get validations due for revalidation
     */
    public function scopeDueForRevalidation($query)
    {
        return $query->where('next_validation_due', '<=', now())
                    ->whereNotNull('next_validation_due');
    }

    /**
     * Scope to get validations by MAC contractor
     */
    public function scopeByMacContractor($query, $contractor)
    {
        return $query->where('mac_contractor', $contractor);
    }

    /**
     * Scope to get validations by type
     */
    public function scopeByValidationType($query, $type)
    {
        return $query->where('validation_type', $type);
    }

    /**
     * Scope to get vascular + wound care validations
     */
    public function scopeVascularWoundCare($query)
    {
        return $query->where('validation_type', 'vascular_wound_care');
    }

    /**
     * Scope to get wound care only validations
     */
    public function scopeWoundCareOnly($query)
    {
        return $query->where('validation_type', 'wound_care_only');
    }

    /**
     * Scope to get validations by provider specialty
     */
    public function scopeBySpecialty($query, $specialty)
    {
        return $query->where('provider_specialty', $specialty);
    }

    /**
     * Scope to get vascular surgery validations
     */
    public function scopeVascularSurgery($query)
    {
        return $query->where('provider_specialty', 'vascular_surgery');
    }

    /**
     * Scope to get interventional radiology validations
     */
    public function scopeInterventionalRadiology($query)
    {
        return $query->where('provider_specialty', 'interventional_radiology');
    }

    /**
     * Scope to get cardiology validations
     */
    public function scopeCardiology($query)
    {
        return $query->where('provider_specialty', 'cardiology');
    }

    /**
     * Scope to get high reimbursement risk validations
     */
    public function scopeHighRisk($query)
    {
        return $query->where('reimbursement_risk', 'high');
    }

    /**
     * Check if validation is compliant overall
     */
    public function isCompliant(): bool
    {
        return $this->coverage_met &&
               $this->documentation_complete &&
               $this->frequency_compliant &&
               $this->medical_necessity_met &&
               $this->billing_compliant &&
               (!$this->prior_auth_required || $this->prior_auth_obtained);
    }

    /**
     * Check if prior authorization is expired
     */
    public function isPriorAuthExpired(): bool
    {
        return $this->prior_auth_required &&
               $this->prior_auth_expiry &&
               $this->prior_auth_expiry < now();
    }

    /**
     * Get validation compliance score (0-100)
     */
    public function getComplianceScore(): int
    {
        $score = 0;
        $maxScore = 6;

        if ($this->coverage_met) $score++;
        if ($this->documentation_complete) $score++;
        if ($this->frequency_compliant) $score++;
        if ($this->medical_necessity_met) $score++;
        if ($this->billing_compliant) $score++;
        if (!$this->prior_auth_required || $this->prior_auth_obtained) $score++;

        return round(($score / $maxScore) * 100);
    }

    /**
     * Get missing compliance items
     */
    public function getMissingComplianceItems(): array
    {
        $missing = [];

        if (!$this->coverage_met) {
            $missing[] = 'Coverage requirements not met';
        }
        if (!$this->documentation_complete) {
            $missing[] = 'Documentation incomplete';
        }
        if (!$this->frequency_compliant) {
            $missing[] = 'Frequency not compliant';
        }
        if (!$this->medical_necessity_met) {
            $missing[] = 'Medical necessity not established';
        }
        if (!$this->billing_compliant) {
            $missing[] = 'Billing compliance issues';
        }
        if ($this->prior_auth_required && !$this->prior_auth_obtained) {
            $missing[] = 'Prior authorization required but not obtained';
        }

        return $missing;
    }

    /**
     * Schedule next validation
     */
    public function scheduleNextValidation(int $daysFromNow = 30): void
    {
        $this->update([
            'next_validation_due' => now()->addDays($daysFromNow)
        ]);
    }

    /**
     * Add audit trail entry
     */
    public function addAuditEntry(string $action, array $data = [], string $user = null): void
    {
        $auditTrail = $this->audit_trail ?? [];

        $auditTrail[] = [
            'action' => $action,
            'data' => $data,
            'user' => $user,
            'timestamp' => now()->toISOString()
        ];

        $this->update(['audit_trail' => $auditTrail]);
    }

    /**
     * Get specialty-specific validation requirements
     */
    public function getSpecialtyRequirements(): array
    {
        $requirements = $this->specialty_requirements ?? [];

        // Add default requirements based on specialty
        $defaultRequirements = $this->getDefaultSpecialtyRequirements($this->provider_specialty);

        return array_merge($defaultRequirements, $requirements);
    }

    /**
     * Get default requirements for a specialty
     */
    private function getDefaultSpecialtyRequirements(?string $specialty): array
    {
        return match($specialty) {
            'vascular_surgery' => [
                'required_documentation' => [
                    'angiography',
                    'abi_measurements',
                    'vascular_assessment',
                    'physician_orders',
                    'failed_conservative_treatment'
                ],
                'procedure_categories' => ['vascular_interventions', 'wound_care'],
                'frequency_monitoring' => 'daily',
                'prior_auth_threshold' => 'medium_complexity'
            ],
            'interventional_radiology' => [
                'required_documentation' => [
                    'diagnostic_imaging',
                    'contrast_allergy_screening',
                    'renal_function_assessment',
                    'physician_orders'
                ],
                'procedure_categories' => ['imaging_guided_procedures', 'vascular_interventions'],
                'frequency_monitoring' => 'per_procedure',
                'prior_auth_threshold' => 'high_complexity'
            ],
            'cardiology' => [
                'required_documentation' => [
                    'ecg',
                    'echo_results',
                    'cardiac_catheterization',
                    'physician_orders'
                ],
                'procedure_categories' => ['cardiac_interventions'],
                'frequency_monitoring' => 'per_procedure',
                'prior_auth_threshold' => 'high_complexity'
            ],
            'wound_care_specialty' => [
                'required_documentation' => [
                    'wound_assessment',
                    'wound_measurement',
                    'photography',
                    'treatment_plan',
                    'physician_orders'
                ],
                'procedure_categories' => ['wound_care_only'],
                'frequency_monitoring' => 'weekly',
                'prior_auth_threshold' => 'low_complexity'
            ],
            default => [
                'required_documentation' => ['physician_orders', 'patient_assessment'],
                'procedure_categories' => ['general'],
                'frequency_monitoring' => 'monthly',
                'prior_auth_threshold' => 'standard'
            ]
        };
    }
}
