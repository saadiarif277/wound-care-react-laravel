<?php

namespace App\Models\Order;

use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\MscSalesRep;
use Illuminate\Support\Facades\Log;
use App\Traits\BelongsToOrganizationThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Docuseal\DocusealSubmission;

class ProductRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'provider_id',
        'patient_fhir_id',
        'patient_display_id',
        'ivr_episode_id',
        'facility_id',
        'place_of_service',
        'medicare_part_b_authorized',
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
        // IVR fields
        'ivr_required',
        'ivr_bypass_reason',
        'ivr_bypassed_at',
        'ivr_bypassed_by',
        'docuseal_submission_id',
        'docuseal_template_id',
        'ivr_sent_at',
        'ivr_signed_at',
        'ivr_document_url',
        // Manufacturer approval fields
        'manufacturer_sent_at',
        'manufacturer_sent_by',
        'manufacturer_approved',
        'manufacturer_approved_at',
        'manufacturer_approval_reference',
        'manufacturer_notes',
        // Order fulfillment fields
        'order_number',
        'order_submitted_at',
        'manufacturer_order_id',
        'tracking_number',
        'shipped_at',
        'delivered_at',
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
        'medicare_part_b_authorized' => 'boolean',
        // IVR casts
        'ivr_required' => 'boolean',
        'ivr_bypassed_at' => 'datetime',
        'ivr_sent_at' => 'datetime',
        'ivr_signed_at' => 'datetime',
        'manufacturer_sent_at' => 'datetime',
        'manufacturer_approved' => 'boolean',
        'manufacturer_approved_at' => 'datetime',
        'order_submitted_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Place of service codes and descriptions.
     */
    const PLACE_OF_SERVICE_OPTIONS = [
        '11' => 'Office',
        '12' => 'Home',
        '32' => 'Nursing Home',
        '31' => 'Skilled Nursing',
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
     * Get the patient data from Azure FHIR.
     * This is not a relationship - patient data lives in Azure FHIR, not local DB.
     */
    public function getPatientAttribute()
    {
        if (!$this->patient_fhir_id) {
            return null;
        }
        
        // Extract just the ID part from "Patient/uuid" format if needed
        $fhirId = $this->patient_fhir_id;
        if (str_starts_with($fhirId, 'Patient/')) {
            $fhirId = substr($fhirId, 8);
        }
        
        try {
            $fhirService = app(\App\Services\FhirService::class);
            return $fhirService->getPatientById($fhirId);
        } catch (\Exception $e) {
            Log::error('Failed to fetch patient from FHIR', [
                'fhir_id' => $fhirId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
     * Get the user who bypassed the IVR requirement.
     */
    public function ivrBypassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ivr_bypassed_by');
    }

    /**
     * Get the user who sent the IVR to manufacturer.
     */
    public function manufacturerSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manufacturer_sent_by');
    }

    /**
     * Get the DocuSeal submissions for this product request.
     */
    public function docusealSubmissions(): HasMany
    {
        return $this->hasMany(DocusealSubmission::class, 'order_id');
    }

    /**
     * Get the IVR episode associated with this product request.
     */
    public function ivrEpisode(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PatientManufacturerIVREpisode::class, 'ivr_episode_id');
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

    /**
     * Get place of service description.
     */
    public function getPlaceOfServiceDescriptionAttribute(): ?string
    {
        if (!$this->place_of_service) {
            return null;
        }

        return self::PLACE_OF_SERVICE_OPTIONS[$this->place_of_service] ?? 'Unknown';
    }

    /**
     * Get the full place of service display.
     */
    public function getPlaceOfServiceDisplayAttribute(): ?string
    {
        if (!$this->place_of_service) {
            return null;
        }

        $description = self::PLACE_OF_SERVICE_OPTIONS[$this->place_of_service] ?? 'Unknown';
        $display = "({$this->place_of_service}) {$description}";

        // Add Medicare Part B note for skilled nursing
        if ($this->place_of_service === '31' && $this->medicare_part_b_authorized) {
            $display .= ' - Medicare Part B Authorized';
        }

        return $display;
    }

    /**
     * Check if IVR is required for this request.
     */
    public function isIvrRequired(): bool
    {
        return $this->ivr_required && !$this->ivr_bypass_reason;
    }

    /**
     * Check if IVR has been generated.
     */
    public function isIvrGenerated(): bool
    {
        return !is_null($this->ivr_sent_at) && !is_null($this->ivr_document_url);
    }

    /**
     * Check if IVR has been sent to manufacturer.
     */
    public function isIvrSentToManufacturer(): bool
    {
        return !is_null($this->manufacturer_sent_at);
    }

    /**
     * Check if manufacturer has approved.
     */
    public function isManufacturerApproved(): bool
    {
        return $this->manufacturer_approved;
    }

    /**
     * Check if request is ready for final approval.
     */
    public function isReadyForApproval(): bool
    {
        // Must have either IVR generated and sent to manufacturer or IVR bypassed
        $ivrComplete = ($this->isIvrGenerated() && $this->isIvrSentToManufacturer()) || !$this->isIvrRequired();

        // Must have manufacturer approval
        return $ivrComplete && $this->isManufacturerApproved();
    }

    /**
     * Determine the actual status based on IVR workflow state.
     * This helps map our detailed tracking to the simplified statuses.
     */
    public function determineIvrStatus(): string
    {
        // If IVR is generated but not sent to manufacturer yet, we're still in "IVR Sent" phase
        if ($this->order_status === 'ivr_sent' && !$this->isIvrSentToManufacturer()) {
            return 'ivr_sent';
        }

        // If sent to manufacturer but not approved, still "IVR Sent"
        if ($this->manufacturer_sent_at && !$this->isManufacturerApproved()) {
            return 'ivr_sent';
        }

        // If manufacturer approved, move to "IVR Confirmed"
        if ($this->isManufacturerApproved() && $this->order_status !== 'approved') {
            return 'ivr_confirmed';
        }

        return $this->order_status;
    }

    /**
     * Check if this is considered an approved order.
     */
    public function isApprovedOrder(): bool
    {
        return in_array($this->order_status, [
            'approved',
            'submitted_to_manufacturer',
            'shipped',
            'delivered'
        ]);
    }

    /**
     * Generate order number when transitioning to order.
     */
    public function generateOrderNumber(): string
    {
        if ($this->order_number) {
            return $this->order_number;
        }

        // Format: ORD-YYYYMMDD-XXXX
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;

        return sprintf('ORD-%s-%04d', $date, $count);
    }

    /**
     * Get the manufacturer for the primary product.
     */
    public function getManufacturer(): ?string
    {
        $product = $this->products()->first();
        return $product ? $product->manufacturer : null;
    }

    /**
     * Update the status color to include new IVR statuses.
     * Matches the colors from ADMIN_ORDER_CENTER.md
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->order_status) {
            'draft' => 'gray',
            'submitted' => 'gray',
            'processing' => 'gray',
            'pending_ivr' => 'gray',        // Pending IVR - Gray
            'ivr_sent' => 'blue',           // IVR Sent - Blue
            'ivr_confirmed' => 'purple',    // IVR Confirmed - Purple
            'approved' => 'green',          // Approved - Green
            'sent_back' => 'orange',        // Sent Back - Orange
            'denied' => 'red',              // Denied - Red
            'submitted_to_manufacturer' => 'green', // Dark Green (using green variant)
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get simplified status for UI display (matches ADMIN_ORDER_CENTER.md).
     */
    public function getSimplifiedStatusAttribute(): string
    {
        // Map our internal statuses to the document's simplified statuses
        return match ($this->order_status) {
            'draft', 'submitted', 'processing' => 'Processing',
            'pending_ivr' => 'Pending IVR',
            'ivr_sent' => 'IVR Sent',
            'ivr_confirmed' => 'IVR Confirmed',
            'approved' => 'Approved',
            'sent_back' => 'Sent Back',
            'denied' => 'Denied',
            'submitted_to_manufacturer' => 'Submitted to Manufacturer',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => $this->order_status,
        };
    }

    /**
     * Check if this status means action is required by admin.
     */
    public function requiresAdminAction(): bool
    {
        return in_array($this->order_status, ['submitted', 'pending_ivr', 'ivr_confirmed']);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }
}
