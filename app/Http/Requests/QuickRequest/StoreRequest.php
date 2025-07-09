<?php

declare(strict_types=1);

namespace App\Http\Requests\QuickRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return \Illuminate\Support\Facades\Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Context & Request Type
            'request_type' => 'required|in:new_request,reverification,additional_applications',
            'provider_id' => 'required|exists:users,id',
            'facility_id' => 'required|exists:facilities,id',
            'sales_rep_id' => 'nullable|string',

            // Patient Information
            'patient_first_name' => 'required|string|max:255',
            'patient_last_name' => 'required|string|max:255',
            'patient_dob' => 'required|date|before:today',
            'patient_gender' => 'nullable|in:male,female,other,unknown',
            'patient_member_id' => 'nullable|string|max:255',
            
            // FHIR-compliant address structure (new format)
            'patient.address.text' => 'nullable|string|max:500',
            'patient.address.line' => 'nullable|array|max:5',
            'patient.address.line.*' => 'nullable|string|max:255',
            'patient.address.city' => 'nullable|string|max:255',
            'patient.address.state' => 'nullable|string|max:50',
            'patient.address.postalCode' => 'nullable|string|max:10',
            'patient.address.country' => 'nullable|string|max:50',
            
            // Legacy address fields (backwards compatibility)
            'patient_address_line1' => 'nullable|string|max:255',
            'patient_address_line2' => 'nullable|string|max:255',
            'patient_city' => 'nullable|string|max:255',
            'patient_state' => 'nullable|string|max:2',
            'patient_zip' => 'nullable|string|max:10',
            
            'patient_phone' => 'nullable|string|max:20',
            'patient_email' => 'nullable|email|max:255',
            'patient_is_subscriber' => 'required|boolean',

            // Caregiver (if not subscriber)
            'caregiver_name' => 'nullable|string|max:255',
            'caregiver_relationship' => 'nullable|string|max:255',
            'caregiver_phone' => 'nullable|string|max:20',

            // Service & Shipping
            'expected_service_date' => 'required|date|after:today',
            'shipping_speed' => 'required|string|max:50',
            'delivery_date' => 'nullable|date',

            // Primary Insurance
            'primary_insurance_name' => 'required|string|max:255',
            'primary_member_id' => 'required|string|max:255',
            'primary_payer_phone' => 'nullable|string|max:20',
            'primary_plan_type' => 'required|string|max:50',

            // Secondary Insurance
            'has_secondary_insurance' => 'required|boolean',
            'secondary_insurance_name' => 'nullable|string|max:255',
            'secondary_member_id' => 'nullable|string|max:255',
            'secondary_subscriber_name' => 'nullable|string|max:255',
            'secondary_subscriber_dob' => 'nullable|date',
            'secondary_payer_phone' => 'nullable|string|max:20',
            'secondary_plan_type' => 'nullable|string|max:50',

            // Prior Authorization
            'prior_auth_permission' => 'required|boolean',

            // Clinical Information
            'wound_type' => ['required', 'string', new \App\Rules\WoundTypeRule()],
            'wound_types' => 'nullable|array|min:1',
            'wound_other_specify' => 'nullable|string|max:255',
            'wound_location' => 'required|string|max:255',
            'wound_location_details' => 'nullable|string|max:255',

            // Diagnosis codes
            'yellow_diagnosis_code' => 'nullable|string|max:20',
            'orange_diagnosis_code' => 'nullable|string|max:20',
            'primary_diagnosis_code' => 'nullable|string|max:20',
            'secondary_diagnosis_code' => 'nullable|string|max:20',
            'diagnosis_code' => 'nullable|string|max:20',

            // Wound measurements
            'wound_size_length' => 'required|numeric|min:0.1|max:100',
            'wound_size_width' => 'required|numeric|min:0.1|max:100',
            'wound_size_depth' => 'nullable|numeric|min:0|max:100',

            // Wound duration
            'wound_duration' => 'nullable|string|max:255',
            'wound_duration_days' => 'nullable|numeric|min:0|max:30',
            'wound_duration_weeks' => 'nullable|numeric|min:0|max:52',
            'wound_duration_months' => 'nullable|numeric|min:0|max:12',
            'wound_duration_years' => 'nullable|numeric|min:0|max:10',

            'previous_treatments' => 'nullable|string|max:1000',

            // Procedure Information
            'application_cpt_codes' => 'required|array|min:1',
            'prior_applications' => 'nullable|string|max:20',
            'prior_application_product' => 'nullable|string|max:255',
            'prior_application_within_12_months' => 'nullable|boolean',
            'anticipated_applications' => 'nullable|string|max:20',

            // Billing Status
            'place_of_service' => 'required|string|max:10',
            'medicare_part_b_authorized' => 'nullable|boolean',
            'snf_days' => 'nullable|string|max:10',
            'hospice_status' => 'nullable|boolean',
            'hospice_family_consent' => 'nullable|boolean',
            'hospice_clinically_necessary' => 'nullable|boolean',
            'part_a_status' => 'nullable|boolean',
            'global_period_status' => 'nullable|boolean',
            'global_period_cpt' => 'nullable|string|max:10',
            'global_period_surgery_date' => 'nullable|date',

            // Product Selection
            'selected_products' => 'required|array|min:1',
            'selected_products.*.product_id' => 'required|exists:msc_products,id',
            'selected_products.*.quantity' => 'required|integer|min:1|max:100',
            'selected_products.*.size' => 'nullable|string|max:50',

            // Manufacturer Fields
            'manufacturer_fields' => 'nullable|array',

            // Clinical Attestations
            'failed_conservative_treatment' => 'required|boolean',
            'information_accurate' => 'required|boolean',
            'medical_necessity_established' => 'required|boolean',
            'maintain_documentation' => 'required|boolean',
            'authorize_prior_auth' => 'nullable|boolean',

            // Provider Authorization
            'provider_name' => 'nullable|string|max:255',
            'provider_npi' => 'nullable|string|max:20',
            'signature_date' => 'nullable|date',
            'verbal_order' => 'nullable|array',

            // Docuseal Integration
            'docuseal_submission_id' => 'nullable|string',
            'episode_id' => 'nullable|uuid',

            // File uploads
            'insurance_card_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'face_sheet' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'clinical_notes' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'wound_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'expected_service_date.after' => 'Service date must be in the future.',
            'selected_products.required' => 'Please select at least one product.',
            'facility_id.required' => 'Please select a facility.',
            'wound_type.required' => 'Please select a wound type.',
            'patient_dob.before' => 'Patient date of birth must be in the past.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateWoundDurationFields($validator);
            $this->validateDiagnosisCodes($validator);
        });
    }

    /**
     * Validate wound duration fields
     */
    private function validateWoundDurationFields($validator): void
    {
        $data = $validator->getData();
        $hasAnyDuration =
            !empty($data['wound_duration_days']) ||
            !empty($data['wound_duration_weeks']) ||
            !empty($data['wound_duration_months']) ||
            !empty($data['wound_duration_years']);

        if (!$hasAnyDuration) {
            $validator->errors()->add('wound_duration', 'At least one duration field (days, weeks, months, or years) is required.');
        }
    }

    /**
     * Validate diagnosis codes based on wound type
     */
    private function validateDiagnosisCodes($validator): void
    {
        $data = $validator->getData();
        $woundType = \App\Rules\WoundTypeRule::normalize($data['wound_type'] ?? '');

        if (in_array($woundType, ['DFU', 'VLU'])) {
            if (empty($data['primary_diagnosis_code']) || empty($data['secondary_diagnosis_code'])) {
                $validator->errors()->add('diagnosis_code', 'This wound type requires both a primary and secondary diagnosis code.');
            }
        } elseif (empty($data['diagnosis_code']) && empty($data['primary_diagnosis_code'])) {
            $validator->errors()->add('diagnosis_code', 'A diagnosis code is required.');
        }
    }
}
