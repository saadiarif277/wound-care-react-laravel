<?php

namespace App\Models\Order;

use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\MscSalesRep;
use App\Traits\BelongsToOrganizationThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganizationThrough;

    protected $fillable = [
        'request_number',
        'provider_id',
        'patient_fhir_id',
        'patient_display_id',
        'facility_id',
        'payer_name_submitted',
        'payer_id',
        'expected_service_date',
        'wound_type',
        'azure_order_checklist_fhir_id',
        'clinical_summary',
        'mac_validation_results',
        'mac_validation_status',
        'eligibility_results',
        'eligibility_status',
        'pre_auth_required_determination',
        'pre_auth_status',
        'pre_auth_submitted_at',
        'pre_auth_approved_at',
        'pre_auth_denied_at',
        'clinical_opportunities',
        'order_status',
        'step',
        'submitted_at',
        'approved_at',
        'total_order_value',
        'acquiring_rep_id',
    ];

    protected $casts = [
        'expected_service_date' => 'date',
        'clinical_summary' => 'array',
        'mac_validation_results' => 'array',
        'eligibility_results' => 'array',
        'clinical_opportunities' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'pre_auth_submitted_at' => 'datetime',
        'pre_auth_approved_at' => 'datetime',
        'pre_auth_denied_at' => 'datetime',
        'total_order_value' => 'decimal:2',
    ];

    /**
     * Get the name of the parent relationship that contains organization_id
     */
    protected static function getOrganizationParentRelationName(): string
    {
        return 'facility';
    }

    /**
     * Get the name of the organization relationship on the parent
     */
    public function getOrganizationRelationName(): string
    {
        return 'organization';
    }

    /**
     * Get the provider that created this request.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Get the facility associated with this request.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the acquiring sales rep.
     */
    public function acquiringRep(): BelongsTo
    {
        return $this->belongsTo(MscSalesRep::class, 'acquiring_rep_id', 'rep_id');
    }

    /**
     * Get the pre-authorizations for this product request.
     */
    public function preAuthorizations(): HasMany
    {
        return $this->hasMany(\App\Models\Insurance\PreAuthorization::class, 'product_request_id');
    }

    /**
     * Get patient data from FHIR server (via service).
     * Note: Patient data is stored in Azure FHIR, not locally.
     */
    public function getPatientData(): ?array
    {
        // This would integrate with FHIR service to fetch patient data
        // For now, return null as we don't store PHI locally
        return null;
    }

    /**
     * Get patient display information for UI (non-PHI).
     */
    public function getPatientDisplayInfo(): array
    {
        return [
            'patient_fhir_id' => $this->patient_fhir_id,
            'patient_display_id' => $this->patient_display_id,
            'display_name' => $this->formatPatientDisplay(),
        ];
    }

    /**
     * Format patient display for UI using sequential display ID.
     */
    public function formatPatientDisplay(): string
    {
        if (!$this->patient_display_id) {
            return 'Patient ' . substr($this->patient_fhir_id, -4);
        }

        return $this->patient_display_id; // "JoSm001" format - no age for better privacy
    }

    /**
     * Get the products associated with this request.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_request_products')
            ->withPivot(['quantity', 'size', 'unit_price', 'total_price'])
            ->withTimestamps();
    }

    /**
     * Calculate the total amount for this request.
     */
    public function calculateTotalAmount(): float
    {
        return $this->products->sum('pivot.total_price');
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->order_status) {
            'draft' => 'gray',
            'submitted' => 'blue',
            'processing' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the step description for the 6-step MSC-MVP workflow.
     */
    public function getStepDescriptionAttribute(): string
    {
        return match ($this->step) {
            1 => 'Patient Information Entry',
            2 => 'Clinical Assessment Documentation',
            3 => 'Product Selection with AI Recommendations',
            4 => 'Validation & Eligibility (Automated)',
            5 => 'Clinical Opportunities Review (Optional)',
            6 => 'Review & Submit',
            default => 'Unknown Step',
        };
    }

    /**
     * Get wound type descriptions.
     */
    public static function getWoundTypeDescriptions(): array
    {
        return [
            'DFU' => 'Diabetic Foot Ulcer',
            'VLU' => 'Venous Leg Ulcer',
            'PU' => 'Pressure Ulcer',
            'TW' => 'Traumatic Wound',
            'AU' => 'Arterial Ulcer',
            'OTHER' => 'Other',
        ];
    }

    /**
     * Check if the request can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->order_status, ['draft', 'processing']);
    }

    /**
     * Check if the request can be submitted.
     */
    public function canBeSubmitted(): bool
    {
        return $this->order_status === 'draft' && $this->step >= 6;
    }

    /**
     * Check if prior authorization is required for this request.
     */
    public function isPriorAuthRequired(): bool
    {
        return $this->pre_auth_required_determination === 'required';
    }

    /**
     * Check if we should skip the prior auth step.
     */
    public function shouldSkipPriorAuthStep(): bool
    {
        return $this->pre_auth_required_determination !== 'required';
    }

    /**
     * Search patients by display ID within facility.
     */
    public static function searchPatientsByDisplayId(string $searchTerm, int $facilityId): array
    {
        return static::query()
            ->select('patient_display_id', 'patient_fhir_id')
            ->where('facility_id', $facilityId)
            ->where('patient_display_id', 'LIKE', $searchTerm . '%')
            ->distinct()
            ->get()
            ->map(function ($request) {
                return [
                    'patient_display_id' => $request->patient_display_id,
                    'patient_fhir_id' => $request->patient_fhir_id,
                    'display_name' => $request->patient_display_id,
                ];
            })
            ->toArray();
    }
}
