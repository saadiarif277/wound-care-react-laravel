<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

class QuickRequestData
{
    public function __construct(
        public readonly PatientData $patient,
        public readonly ProviderData $provider,
        public readonly FacilityData $facility,
        public readonly ClinicalData $clinical,
        public readonly InsuranceData $insurance,
        public readonly ProductSelectionData $productSelection,
        public readonly OrderPreferencesData $orderPreferences,
        public readonly array $manufacturerFields = [],
        public readonly ?string $docusealSubmissionId = null,
        public readonly ?string $ivrDocumentUrl = null,
        public readonly array $attestations = [],
        public readonly ?string $adminNote = null,
    ) {}

    public function toArray(): array
    {
        $now = now()->toISOString();

        return [
            'patient' => array_merge($this->patient->toArray(), ['saved_at' => $now]),
            'provider' => array_merge($this->provider->toArray(), ['saved_at' => $now]),
            'facility' => array_merge($this->facility->toArray(), ['saved_at' => $now]),
            'clinical' => array_merge($this->clinical->toArray(), ['saved_at' => $now]),
            'insurance' => array_merge($this->insurance->toArray(), ['saved_at' => $now]),
            'product_selection' => array_merge($this->productSelection->toArray(), ['saved_at' => $now]),
            'order_preferences' => array_merge($this->orderPreferences->toArray(), ['saved_at' => $now]),
            'manufacturer_fields' => $this->manufacturerFields,
            'docuseal_submission_id' => $this->docusealSubmissionId,
            'attestations' => array_merge($this->attestations, ['saved_at' => $now]),
            'admin_note' => $this->adminNote,
            'admin_note_added_at' => $this->adminNote ? now()->toISOString() : null,
            'saved_at' => $now,
        ];
    }

    public static function fromFormData(array $formData): self
    {
        return new self(
            patient: PatientData::fromArray([
                'first_name' => $formData['patient_first_name'] ?? '',
                'last_name' => $formData['patient_last_name'] ?? '',
                'date_of_birth' => $formData['patient_dob'] ?? '',
                'gender' => $formData['patient_gender'] ?? 'unknown',
                'member_id' => $formData['patient_member_id'] ?? null,
                'display_id' => $formData['patient_display_id'] ?? null,
                'address_line1' => $formData['patient_address_line1'] ?? null,
                'address_line2' => $formData['patient_address_line2'] ?? null,
                'city' => $formData['patient_city'] ?? null,
                'state' => $formData['patient_state'] ?? null,
                'zip' => $formData['patient_zip'] ?? null,
                'phone' => $formData['patient_phone'] ?? null,
                'email' => $formData['patient_email'] ?? null,
                'is_subscriber' => $formData['patient_is_subscriber'] ?? true,
            ]),
            provider: ProviderData::fromArray([
                'id' => $formData['provider_id'] ?? null,
                'name' => $formData['provider_name'] ?? '',
                'npi' => $formData['provider_npi'] ?? null,
                'email' => $formData['provider_email'] ?? null,
                'phone' => $formData['provider_phone'] ?? null,
                'specialty' => $formData['provider_specialty'] ?? null,
            ]),
            facility: FacilityData::fromArray([
                'id' => $formData['facility_id'] ?? null,
                'name' => $formData['facility_name'] ?? '',
                'address' => $formData['facility_address'] ?? null,
                'address_line1' => $formData['facility_address_line1'] ?? null,
                'address_line2' => $formData['facility_address_line2'] ?? null,
                'city' => $formData['facility_city'] ?? null,
                'state' => $formData['facility_state'] ?? null,
                'zip' => $formData['facility_zip'] ?? null,
                'phone' => $formData['facility_phone'] ?? null,
                'fax' => $formData['facility_fax'] ?? null,
                'email' => $formData['facility_email'] ?? null,
                'npi' => $formData['facility_npi'] ?? null,
                'tax_id' => $formData['facility_tax_id'] ?? null,
            ]),
            clinical: ClinicalData::fromArray([
                'wound_type' => $formData['wound_type'] ?? '',
                'wound_location' => $formData['wound_location'] ?? '',
                'wound_size_length' => $formData['wound_size_length'] ?? 0,
                'wound_size_width' => $formData['wound_size_width'] ?? 0,
                'wound_size_depth' => $formData['wound_size_depth'] ?? null,
                'wound_duration_weeks' => $formData['wound_duration_weeks'] ?? null,
                'diagnosis_codes' => self::extractDiagnosisCodes($formData),
                'primary_diagnosis_code' => $formData['primary_diagnosis_code'] ?? '',
                'secondary_diagnosis_code' => $formData['secondary_diagnosis_code'] ?? '',
                'application_cpt_codes' => $formData['application_cpt_codes'] ?? [],
                'clinical_notes' => $formData['clinical_notes'] ?? null,
                'failed_conservative_treatment' => $formData['failed_conservative_treatment'] ?? false,
            ]),
            insurance: InsuranceData::fromArray([
                'primary_name' => $formData['primary_insurance_name'] ?? '',
                'primary_member_id' => $formData['primary_member_id'] ?? '',
                'primary_plan_type' => $formData['primary_plan_type'] ?? '',
                'has_secondary' => $formData['has_secondary_insurance'] ?? false,
                'secondary_name' => $formData['secondary_insurance_name'] ?? null,
                'secondary_member_id' => $formData['secondary_member_id'] ?? null,
                'secondary_plan_type' => $formData['secondary_plan_type'] ?? null,
            ]),
            productSelection: ProductSelectionData::fromArray([
                'selected_products' => $formData['selected_products'] ?? [],
                'manufacturer_id' => $formData['manufacturer_id'] ?? null,
                'manufacturer_name' => $formData['manufacturer_name'] ?? null,
            ]),
            orderPreferences: OrderPreferencesData::fromArray([
                'expected_service_date' => $formData['expected_service_date'] ?? '',
                'shipping_speed' => $formData['shipping_speed'] ?? 'standard',
                'place_of_service' => $formData['place_of_service'] ?? '',
                'delivery_instructions' => $formData['delivery_instructions'] ?? null,
            ]),
            manufacturerFields: $formData['manufacturer_fields'] ?? [],
            docusealSubmissionId: $formData['docuseal_submission_id'] ?? null,
            ivrDocumentUrl: $formData['ivr_document_url'] ?? null,
            attestations: [
                'failed_conservative_treatment' => $formData['failed_conservative_treatment'] ?? false,
                'information_accurate' => $formData['information_accurate'] ?? false,
                'medical_necessity_established' => $formData['medical_necessity_established'] ?? false,
                'maintain_documentation' => $formData['maintain_documentation'] ?? false,
                'authorize_prior_auth' => $formData['authorize_prior_auth'] ?? false,
            ],
        );
    }

    private static function extractDiagnosisCodes(array $formData): array
    {
        $codes = [];

        // Add primary diagnosis code
        if (!empty($formData['primary_diagnosis_code'])) {
            $codes[] = $formData['primary_diagnosis_code'];
        }

        // Add secondary diagnosis code
        if (!empty($formData['secondary_diagnosis_code'])) {
            $codes[] = $formData['secondary_diagnosis_code'];
        }

        // Add any additional diagnosis codes
        if (!empty($formData['diagnosis_codes']) && is_array($formData['diagnosis_codes'])) {
            $codes = array_merge($codes, $formData['diagnosis_codes']);
        }

        return array_unique(array_filter($codes));
    }
}
