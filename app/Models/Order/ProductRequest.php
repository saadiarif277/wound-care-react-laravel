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
use App\Constants\DocusealFields;
use Illuminate\Support\Arr;
use Illuminate\Http\UploadedFile;
use App\Services\OrderDetailService;
use App\Services\FileService;

class ProductRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'provider_id',
        'patient_fhir_id',
        'patient_display_id',
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
        'ivr_status',
        'step',
        'submitted_at',
        'approved_at',
        'total_order_value',
        'acquiring_rep_id',
        'ivr_episode_id',
        'docuseal_submission_id',
        'docuseal_template_id',
        'notes',
        'rejection_reason',
        'cancellation_reason',
        'carrier',
        'tracking_number',
        'shipping_info',
        'altered_ivr_file_path',
        'altered_ivr_uploaded_at',
        'altered_ivr_uploaded_by',
        'altered_order_form_file_path',
        'altered_order_form_uploaded_at',
        'altered_order_form_uploaded_by',
    ];

    protected $casts = [
        'expected_service_date' => 'date',
        'clinical_summary' => 'array',
        'mac_validation_results' => 'array',
        'eligibility_results' => 'array',
        'clinical_opportunities' => 'array',
        'shipping_info' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'pre_auth_submitted_at' => 'datetime',
        'pre_auth_approved_at' => 'datetime',
        'pre_auth_denied_at' => 'datetime',
        'total_order_value' => 'decimal:2',
        'medicare_part_b_authorized' => 'boolean',
    ];

    /**
     * Order Status constants - matches PRD requirements
     */
    const ORDER_STATUS_PENDING = 'pending';
    const ORDER_STATUS_SUBMITTED_TO_MANUFACTURER = 'submitted_to_manufacturer';
    const ORDER_STATUS_CONFIRMED_BY_MANUFACTURER = 'confirmed_by_manufacturer';
    const ORDER_STATUS_REJECTED = 'rejected';
    const ORDER_STATUS_CANCELED = 'canceled';

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
        // Since ivr_bypassed_by column doesn't exist,
        // we can't establish a direct relationship
        throw new \Exception('ivr_bypassed_by column does not exist in product_requests table');
    }

    /**
     * Get the user who sent the IVR to manufacturer.
     */
    public function manufacturerSentBy(): BelongsTo
    {
        // Since manufacturer_sent_by column doesn't exist,
        // we can't establish a direct relationship
        throw new \Exception('manufacturer_sent_by column does not exist in product_requests table');
    }

    /**
     * Get the status documents for this product request.
     */
    public function statusDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\OrderStatusDocument::class, 'product_request_id');
    }

    /**
     * Get the Docuseal submissions for this product request.
     */
    public function docusealSubmissions(): HasMany
    {
        return $this->hasMany(DocusealSubmission::class, 'order_id');
    }

    /**
     * Get the user who uploaded the altered IVR file.
     */
    public function alteredIvrUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'altered_ivr_uploaded_by');
    }

    /**
     * Get the user who uploaded the altered order form file.
     */
    public function alteredOrderFormUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'altered_order_form_uploaded_by');
    }

    /**
     * Get the URL for the altered IVR file.
     */
    public function getAlteredIvrFileUrlAttribute(): ?string
    {
        if (!$this->altered_ivr_file_path) {
            return null;
        }
        return asset('storage/' . $this->altered_ivr_file_path);
    }

    /**
     * Get the URL for the altered order form file.
     */
    public function getAlteredOrderFormFileUrlAttribute(): ?string
    {
        if (!$this->altered_order_form_file_path) {
            return null;
        }
        return asset('storage/' . $this->altered_order_form_file_path);
    }

    /**
     * Get the filename for the altered IVR file.
     */
    public function getAlteredIvrFileNameAttribute(): ?string
    {
        if (!$this->altered_ivr_file_path) {
            return null;
        }
        return basename($this->altered_ivr_file_path);
    }

    /**
     * Get the filename for the altered order form file.
     */
    public function getAlteredOrderFormFileNameAttribute(): ?string
    {
        if (!$this->altered_order_form_file_path) {
            return null;
        }
        return basename($this->altered_order_form_file_path);
    }

    /**
     * Get the IVR episode associated with this product request.
     */
    public function episode(): BelongsTo
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
        // Check if IVR is explicitly marked as not required in clinical_summary
        if (isset($this->clinical_summary['ivr_required']) && $this->clinical_summary['ivr_required'] === false) {
            return false;
        }

        // Check if IVR was bypassed with a reason
        if (isset($this->clinical_summary['ivr_bypass_reason'])) {
            return false;
        }

        // Default: IVR is required for all requests
        return true;
    }

    /**
     * Check if IVR was bypassed and get the reason
     */
    public function getIvrBypassReason(): ?string
    {
        return $this->clinical_summary['ivr_bypass_reason'] ?? null;
    }

    /**
     * Set IVR as not required with optional reason
     */
    public function setIvrNotRequired(?string $reason = null): void
    {
        $clinicalSummary = $this->clinical_summary ?? [];
        $clinicalSummary['ivr_required'] = false;

        if ($reason) {
            $clinicalSummary['ivr_bypass_reason'] = $reason;
        }

        $this->update(['clinical_summary' => $clinicalSummary]);
    }

    /**
     * Check if IVR has been generated.
     */
    public function isIvrGenerated(): bool
    {
        // Since ivr_sent_at and ivr_document_url columns don't exist,
        // check if we have any Docuseal submissions
        return $this->docusealSubmissions()->exists();
    }

    /**
     * Check if IVR has been sent to manufacturer.
     */
    public function isIvrSentToManufacturer(): bool
    {
        // Since manufacturer_sent_at column doesn't exist,
        // check if order_status indicates it was sent
        return in_array($this->order_status, ['submitted_to_manufacturer', 'approved', 'shipped', 'delivered']);
    }

    /**
     * Check if manufacturer has approved.
     */
    public function isManufacturerApproved(): bool
    {
        // Since manufacturer_approved column doesn't exist,
        // check if order_status indicates approval
        return in_array($this->order_status, ['approved', 'shipped', 'delivered']);
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
     * Check if IVR is verified (for Scenario 1)
     */
    public function isIvrVerified(): bool
    {
        if (!$this->isIvrRequired()) {
            return true; // If IVR not required, consider it "verified"
        }

        // Check if IVR has been verified by manufacturer
        return in_array($this->order_status, ['ivr_confirmed', 'approved', 'submitted_to_manufacturer', 'shipped', 'delivered']);
    }

    /**
     * Check if order form can be completed (for Scenario 1)
     */
    public function canCompleteOrderForm(): bool
    {
        // If IVR not required, order form can always be completed
        if (!$this->isIvrRequired()) {
            return true;
        }

        // If IVR required, order form can be completed but not submitted until IVR verified
        return true;
    }

    /**
     * Check if order form can be submitted (for Scenario 1)
     */
    public function canSubmitOrderForm(): bool
    {
        // If IVR not required, order form can be submitted immediately
        if (!$this->isIvrRequired()) {
            return true;
        }

        // If IVR required, order form can only be submitted after IVR verification
        return $this->isIvrVerified();
    }

    /**
     * Determine the actual status based on IVR workflow state.
     * This helps map our detailed tracking to the simplified statuses.
     */
    public function determineIvrStatus(): string
    {
        // Since manufacturer_sent_at column doesn't exist, use order_status directly
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
        // Since order_number column doesn't exist, generate a new one
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
     * Get a normalized field value using Docuseal canonical keys.
     * This provides a single interface for accessing form data regardless of storage method.
     *
     * @param string $key Canonical Docuseal field key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getValue(string $key, $default = null)
    {
        // First check if we have Docuseal submission data
        if ($submission = $this->getDocusealSubmissionData()) {
            $value = Arr::get($submission, $key, null);
            if ($value !== null) {
                return $value;
            }
        }

        // Fall back to direct model attributes with field mapping
        return $this->getValueFromModel($key, $default);
    }

    /**
     * Get Docuseal submission form data if available.
     */
    protected function getDocusealSubmissionData(): ?array
    {
        // Since docuseal_submission_id column doesn't exist,
        // try to get the first Docuseal submission
        try {
            $submission = $this->docusealSubmissions()->first();
            if ($submission && isset($submission->response_data)) {
                return is_array($submission->response_data)
                    ? $submission->response_data
                    : json_decode($submission->response_data, true);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Docuseal submission data', [
                'product_request_id' => $this->id,
                'submission_id' => $this->docuseal_submission_id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Map canonical Docuseal field keys to ProductRequest model attributes.
     */
    protected function getValueFromModel(string $key, $default = null)
    {
        $fieldMapping = [
            // Patient Information (from FHIR when available)
            DocusealFields::PATIENT_NAME => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['name'] ?? null) : $this->patient_display_id;
            },
            DocusealFields::PATIENT_FIRST_NAME => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['name'][0]['given'][0] ?? null) : null;
            },
            DocusealFields::PATIENT_LAST_NAME => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['name'][0]['family'] ?? null) : null;
            },
            DocusealFields::PATIENT_DOB => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['birthDate'] ?? null) : null;
            },
            DocusealFields::PATIENT_GENDER => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['gender'] ?? null) : null;
            },
            DocusealFields::PATIENT_ADDRESS => function() {
                $patient = $this->getPatientAttribute();
                if ($patient && isset($patient['address'][0])) {
                    $addr = $patient['address'][0];
                    return ($addr['line'] ?? []) ? implode(', ', $addr['line']) : null;
                }
                return null;
            },
            DocusealFields::PATIENT_CITY => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['address'][0]['city'] ?? null) : null;
            },
            DocusealFields::PATIENT_STATE => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['address'][0]['state'] ?? null) : null;
            },
            DocusealFields::PATIENT_ZIP => function() {
                $patient = $this->getPatientAttribute();
                return $patient ? ($patient['address'][0]['postalCode'] ?? null) : null;
            },

            // Provider Information
            DocusealFields::PROVIDER_NAME => function() {
                return $this->provider ? $this->provider->first_name . ' ' . $this->provider->last_name : null;
            },
            DocusealFields::PROVIDER_NPI => function() {
                return $this->provider ? $this->provider->npi : null;
            },
            DocusealFields::PROVIDER_PHONE => function() {
                return $this->provider ? $this->provider->phone : null;
            },
            DocusealFields::PROVIDER_EMAIL => function() {
                return $this->provider ? $this->provider->email : null;
            },

            // Facility Information
            DocusealFields::FACILITY_NAME => function() {
                return $this->facility ? $this->facility->name : null;
            },
            DocusealFields::FACILITY_NPI => function() {
                return $this->facility ? $this->facility->npi : null;
            },
            DocusealFields::FACILITY_CONTACT_PHONE => function() {
                return $this->facility ? $this->facility->phone : null;
            },
            DocusealFields::FACILITY_CONTACT_EMAIL => function() {
                return $this->facility ? $this->facility->email : null;
            },
            DocusealFields::FACILITY_ADDRESS => function() {
                if (!$this->facility) return null;
                $facility = $this->facility;
                $parts = array_filter([
                    $facility->address_line_1,
                    $facility->address_line_2,
                    $facility->city . ', ' . $facility->state . ' ' . $facility->zip_code
                ]);
                return $parts ? implode("\n", $parts) : null;
            },

            // Insurance Information
            DocusealFields::PRIMARY_INS_NAME => 'payer_name_submitted',
            DocusealFields::PRIMARY_INS_MEMBER_ID => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['insurance']['primary_member_id'] ?? null) : null;
            },
            DocusealFields::SECONDARY_INS_NAME => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['insurance']['secondary_name'] ?? null) : null;
            },
            DocusealFields::SECONDARY_INS_MEMBER_ID => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['insurance']['secondary_member_id'] ?? null) : null;
            },

            // Service Information
            DocusealFields::PLACE_OF_SERVICE => 'place_of_service',
            DocusealFields::ANTICIPATED_APPLICATION_DATE => function() {
                return $this->expected_service_date ? $this->expected_service_date->format('m/d/Y') : null;
            },
            DocusealFields::SERVICE_DATE => function() {
                return $this->expected_service_date ? $this->expected_service_date->format('m/d/Y') : null;
            },

            // Product Information
            DocusealFields::PRODUCT_CODE => function() {
                $product = $this->products()->first();
                return $product ? $product->q_code : null;
            },
            DocusealFields::PRODUCT_SIZE => function() {
                $product = $this->products()->first();
                return $product ? $product->pivot->size ?? null : null;
            },
            DocusealFields::PRODUCT_NAME => function() {
                $product = $this->products()->first();
                return $product ? $product->name : null;
            },
            DocusealFields::PRODUCT_QUANTITY => function() {
                $product = $this->products()->first();
                return $product ? $product->pivot->quantity ?? 1 : 1;
            },
            DocusealFields::PRODUCT_CATEGORY => function() {
                $product = $this->products()->first();
                return $product ? $product->category : null;
            },

            // Clinical Information
            DocusealFields::WOUND_TYPE => 'wound_type',
            DocusealFields::WOUND_LOCATION => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['clinical']['wound_location'] ?? null) : null;
            },
            DocusealFields::WOUND_SIZE => function() {
                $clinical = $this->clinical_summary;
                if (!is_array($clinical) || !isset($clinical['clinical']['wound_size'])) {
                    return null;
                }
                $size = $clinical['clinical']['wound_size'];
                if (is_array($size)) {
                    $parts = [];
                    if (isset($size['length']) && isset($size['width'])) {
                        $parts[] = $size['length'] . 'cm x ' . $size['width'] . 'cm';
                    }
                    if (isset($size['depth'])) {
                        $parts[] = 'Depth: ' . $size['depth'] . 'cm';
                    }
                    return $parts ? implode(', ', $parts) : null;
                }
                return is_string($size) ? $size : null;
            },
            DocusealFields::ICD10_PRIMARY => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['clinical']['primary_diagnosis_code'] ?? null) : null;
            },
            DocusealFields::ICD10_SECONDARY => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['clinical']['secondary_diagnosis_code'] ?? null) : null;
            },
            DocusealFields::CLINICAL_NOTES => function() {
                $clinical = $this->clinical_summary;
                return is_array($clinical) ? ($clinical['clinical']['clinical_notes'] ?? null) : null;
            },
        ];

        if (isset($fieldMapping[$key])) {
            $mapping = $fieldMapping[$key];

            if (is_callable($mapping)) {
                return $mapping() ?? $default;
            } else {
                return $this->getAttribute($mapping) ?? $default;
            }
        }

        return $default;
    }

    /**
     * Get form values formatted for Docuseal templates.
     * This builds the payload that gets sent to Docuseal for form population.
     */
    public function getDocusealFormValues(): array
    {
        $values = [];

        foreach (DocusealFields::getAllFields() as $field) {
            $value = $this->getValue($field);
            if ($value !== null) {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * Get form values grouped by category for display.
     */
    public function getFormValuesByCategory(): array
    {
        $categorized = [];
        $categories = DocusealFields::getFieldsByCategory();

        foreach ($categories as $category => $fields) {
            $categoryData = [];
            foreach ($fields as $field) {
                $value = $this->getValue($field);
                if ($value !== null) {
                    $categoryData[$field] = [
                        'label' => DocusealFields::getFieldLabel($field),
                        'value' => $value,
                        'type' => DocusealFields::getFieldType($field)
                    ];
                }
            }
            if (!empty($categoryData)) {
                $categorized[$category] = $categoryData;
            }
        }

        return $categorized;
    }

    /**
     * Get route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Get comprehensive order data using the OrderDataService
     */
    public function getComprehensiveOrderData(): array
    {
        $orderDataService = app(OrderDataService::class);
        return $orderDataService->getOrderData($this);
    }

    /**
     * Get active file URLs (prioritizing uploaded files over originals)
     */
    public function getActiveFileUrls(): array
    {
        return [
            'ivr' => $this->altered_ivr_file_url ?: $this->ivr_document_url,
            'order_form' => $this->altered_order_form_file_url ?: $this->episode?->order_form_url,
        ];
    }

    /**
     * Get active file names (prioritizing uploaded files over originals)
     */
    public function getActiveFileNames(): array
    {
        return [
            'ivr' => $this->altered_ivr_file_name ?: $this->extractFileName($this->ivr_document_url),
            'order_form' => $this->altered_order_form_file_name ?: $this->extractFileName($this->episode?->order_form_url),
        ];
    }

    /**
     * Check if order has uploaded files
     */
    public function hasUploadedFiles(): bool
    {
        return !empty($this->altered_ivr_file_path) || !empty($this->altered_order_form_file_path);
    }

    /**
     * Get file upload information
     */
    public function getFileUploadInfo(): array
    {
        return [
            'ivr' => [
                'has_upload' => !empty($this->altered_ivr_file_path),
                'uploaded_at' => $this->altered_ivr_uploaded_at,
                'uploaded_by' => $this->alteredIvrUploadedBy?->name,
                'file_name' => $this->altered_ivr_file_name,
                'file_url' => $this->altered_ivr_file_url,
            ],
            'order_form' => [
                'has_upload' => !empty($this->altered_order_form_file_path),
                'uploaded_at' => $this->altered_order_form_uploaded_at,
                'uploaded_by' => $this->alteredOrderFormUploadedBy?->name,
                'file_name' => $this->altered_order_form_file_name,
                'file_url' => $this->altered_order_form_file_url,
            ],
        ];
    }

    /**
     * Extract filename from URL
     */
    private function extractFileName(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }

    /**
     * Get order documents using FileService
     */
    public function getOrderDocuments(): array
    {
        $fileService = app(FileService::class);
        return $fileService->getOrderDocuments($this);
    }

    /**
     * Get file statistics for the order
     */
    public function getFileStats(): array
    {
        $fileService = app(FileService::class);
        return $fileService->getOrderFileStats($this);
    }

    /**
     * Upload a file for this order
     */
    public function uploadFile(UploadedFile $file, string $fileType, ?string $notes = null, ?int $uploadedBy = null): array
    {
        $fileService = app(FileService::class);
        return $fileService->uploadOrderFile($this, $file, $fileType, $notes, $uploadedBy);
    }

    /**
     * Update an existing file for this order
     */
    public function updateFile(UploadedFile $file, string $fileType, ?string $notes = null, ?int $uploadedBy = null): array
    {
        $fileService = app(FileService::class);
        return $fileService->updateOrderFile($this, $file, $fileType, $notes, $uploadedBy);
    }

    /**
     * Delete a file from this order
     */
    public function deleteFile(int $documentId): bool
    {
        $fileService = app(FileService::class);
        return $fileService->deleteOrderFile($this, $documentId);
    }

    /**
     * Get formatted patient name from various sources
     */
    public function getFormattedPatientName(): string
    {
        // Try from Docuseal fields first
        $name = $this->getValue(DocusealFields::PATIENT_NAME);
        if ($name) {
            return $name;
        }

        // Try from clinical summary
        $clinical = $this->clinical_summary;
        if (is_array($clinical) && isset($clinical['patient'])) {
            $firstName = $clinical['patient']['first_name'] ?? '';
            $lastName = $clinical['patient']['last_name'] ?? '';
            if ($firstName || $lastName) {
                return trim($firstName . ' ' . $lastName);
            }
        }

        // Fallback to display ID
        return $this->patient_display_id ?? 'Unknown Patient';
    }

    /**
     * Get formatted provider name
     */
    public function getFormattedProviderName(): ?string
    {
        $name = $this->getValue(DocusealFields::PROVIDER_NAME);
        return $name ?: ($this->provider ? $this->provider->first_name . ' ' . $this->provider->last_name : null);
    }

    /**
     * Get formatted facility name
     */
    public function getFormattedFacilityName(): ?string
    {
        return $this->getValue(DocusealFields::FACILITY_NAME) ?: $this->facility?->name;
    }

    /**
     * Get order data formatted for frontend consumption
     */
    public function getFrontendOrderData(): array
    {
        $orderDetailService = app(OrderDetailService::class);
        return $orderDetailService->getFrontendOrderData($this);
    }
}
