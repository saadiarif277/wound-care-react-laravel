<?php

namespace App\DataTransferObjects;

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
        public readonly ?string $pdfDocumentId = null,
        public readonly array $attestations = [],
        public readonly ?string $adminNote = null,
    ) {}

    public function toArray(): array
    {
        return [
            'patient' => $this->patient->toArray(),
            'provider' => $this->provider->toArray(),
            'facility' => $this->facility->toArray(),
            'clinical' => $this->clinical->toArray(),
            'insurance' => $this->insurance->toArray(),
            'product_selection' => $this->productSelection->toArray(),
            'order_preferences' => $this->orderPreferences->toArray(),
            'manufacturer_fields' => $this->manufacturerFields,
            'pdf_document_id' => $this->pdfDocumentId,
            'attestations' => $this->attestations,
            'admin_note' => $this->adminNote,
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
            ]),
            facility: FacilityData::fromArray([
                'id' => $formData['facility_id'] ?? null,
                'name' => $formData['facility_name'] ?? '',
            ]),
            clinical: ClinicalData::fromArray([
                'wound_type' => $formData['wound_type'] ?? '',
                'wound_location' => $formData['wound_location'] ?? '',
                'wound_size_length' => $formData['wound_size_length'] ?? 0,
                'wound_size_width' => $formData['wound_size_width'] ?? 0,
                'wound_size_depth' => $formData['wound_size_depth'] ?? null,
                'diagnosis_codes' => self::extractDiagnosisCodes($formData),
            ]),
            insurance: InsuranceData::fromArray([
                'primary_name' => $formData['primary_insurance_name'] ?? '',
                'primary_member_id' => $formData['primary_member_id'] ?? '',
                'primary_plan_type' => $formData['primary_plan_type'] ?? '',
                'has_secondary' => $formData['has_secondary_insurance'] ?? false,
                'secondary_name' => $formData['secondary_insurance_name'] ?? null,
                'secondary_member_id' => $formData['secondary_member_id'] ?? null,
            ]),
            productSelection: ProductSelectionData::fromArray([
                'selected_products' => $formData['selected_products'] ?? [],
                'manufacturer_id' => $formData['manufacturer_id'] ?? null,
            ]),
            orderPreferences: OrderPreferencesData::fromArray([
                'expected_service_date' => $formData['expected_service_date'] ?? '',
                'shipping_speed' => $formData['shipping_speed'] ?? 'standard',
                'place_of_service' => $formData['place_of_service'] ?? '',
            ]),
            manufacturerFields: $formData['manufacturer_fields'] ?? [],
            pdfDocumentId: $formData['pdf_document_id'] ?? null,
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
        
        if (!empty($formData['primary_diagnosis_code'])) {
            $codes[] = $formData['primary_diagnosis_code'];
        }
        if (!empty($formData['secondary_diagnosis_code'])) {
            $codes[] = $formData['secondary_diagnosis_code'];
        }
        if (!empty($formData['diagnosis_code'])) {
            $codes[] = $formData['diagnosis_code'];
        }
        
        return array_unique(array_filter($codes));
    }
} 