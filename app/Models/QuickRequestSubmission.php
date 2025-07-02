<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuickRequestSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Patient Info
        'patient_first_name',
        'patient_last_name',
        'patient_dob',
        'patient_gender',
        'patient_phone',
        'patient_email',
        'patient_address',
        'patient_city',
        'patient_state',
        'patient_zip',
        // Insurance Info
        'primary_insurance_name',
        'primary_plan_type',
        'primary_member_id',
        'has_secondary_insurance',
        'secondary_insurance_name',
        'secondary_plan_type',
        'secondary_member_id',
        'insurance_card_uploaded',
        // Provider/Facility Info
        'provider_name',
        'provider_npi',
        'facility_name',
        'facility_address',
        'organization_name',
        // Clinical Info
        'wound_type',
        'wound_location',
        'wound_size_length',
        'wound_size_width',
        'wound_size_depth',
        'diagnosis_codes',
        'icd10_codes',
        'procedure_info',
        'prior_applications',
        'anticipated_applications',
        'clinical_facility_info',
        // Product Info
        'product_name',
        'product_sizes',
        'product_quantity',
        'asp_price',
        'discounted_price',
        'coverage_warnings',
        // IVR & Order Form Status
        'ivr_status',
        'ivr_submission_date',
        'ivr_document_link',
        'order_form_status',
        'order_form_submission_date',
        'order_form_document_link',
        // Order Meta
        'order_number',
        'order_status',
        'created_by',
        'total_bill',
    ];

    protected $casts = [
        'patient_dob' => 'date',
        'has_secondary_insurance' => 'boolean',
        'insurance_card_uploaded' => 'boolean',
        'diagnosis_codes' => 'array',
        'icd10_codes' => 'array',
        'product_sizes' => 'array',
        'coverage_warnings' => 'array',
        'ivr_submission_date' => 'datetime',
        'order_form_submission_date' => 'datetime',
        'asp_price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'total_bill' => 'decimal:2',
    ];

    /**
     * Get the patient's full name
     */
    public function getPatientFullNameAttribute(): string
    {
        return trim($this->patient_first_name . ' ' . $this->patient_last_name);
    }

    /**
     * Get the patient's full address
     */
    public function getPatientFullAddressAttribute(): string
    {
        $address = $this->patient_address;
        if ($this->patient_city && $this->patient_state) {
            $address .= ', ' . $this->patient_city . ', ' . $this->patient_state;
        }
        if ($this->patient_zip) {
            $address .= ' ' . $this->patient_zip;
        }
        return $address;
    }

    /**
     * Get the wound size as a formatted string
     */
    public function getWoundSizeFormattedAttribute(): string
    {
        if ($this->wound_size_length && $this->wound_size_width) {
            $size = $this->wound_size_length . ' x ' . $this->wound_size_width;
            if ($this->wound_size_depth) {
                $size .= ' x ' . $this->wound_size_depth;
            }
            return $size . 'cm';
        }
        return 'N/A';
    }

    /**
     * Check if the submission is complete
     */
    public function getIsCompleteAttribute(): bool
    {
        return !empty($this->patient_first_name) &&
               !empty($this->patient_last_name) &&
               !empty($this->patient_dob) &&
               !empty($this->primary_insurance_name) &&
               !empty($this->wound_type) &&
               !empty($this->product_name);
    }

    /**
     * Scope for completed submissions
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull('patient_first_name')
                    ->whereNotNull('patient_last_name')
                    ->whereNotNull('patient_dob')
                    ->whereNotNull('primary_insurance_name')
                    ->whereNotNull('wound_type')
                    ->whereNotNull('product_name');
    }

    /**
     * Scope for submissions with IVR completed
     */
    public function scopeWithIvrCompleted($query)
    {
        return $query->whereNotNull('ivr_submission_date');
    }

    /**
     * Scope for submissions with order form completed
     */
    public function scopeWithOrderFormCompleted($query)
    {
        return $query->whereNotNull('order_form_submission_date');
    }
}
