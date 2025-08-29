<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class OrderDataService
{
    /**
     * Get comprehensive order data organized by category
     */
    public function getOrderData(ProductRequest $order): array
    {
        return [
            'basic' => $this->getBasicOrderData($order),
            'patient' => $this->getPatientData($order),
            'provider' => $this->getProviderData($order),
            'facility' => $this->getFacilityData($order),
            'insurance' => $this->getInsuranceData($order),
            'product' => $this->getProductData($order),
            'clinical' => $this->getClinicalData($order),
            'files' => $this->getFileData($order),
            'metadata' => $this->getMetadata($order),
        ];
    }

    /**
     * Get basic order information
     */
    private function getBasicOrderData(ProductRequest $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->request_number,
            'status' => $order->order_status,
            'ivr_status' => $order->ivr_status,
            'created_at' => $order->created_at?->toISOString(),
            'submitted_at' => $order->submitted_at?->toISOString(),
            'expected_service_date' => $order->expected_service_date?->toISOString(),
            'place_of_service' => $order->place_of_service,
            'place_of_service_display' => $order->place_of_service_description,
            'total_order_value' => (float) $order->total_order_value,
            'episode_id' => $order->ivr_episode_id,
            'docuseal_submission_id' => $order->docuseal_submission_id,
        ];
    }

    /**
     * Get patient information from multiple sources
     */
    private function getPatientData(ProductRequest $order): array
    {
        $clinicalSummary = $order->clinical_summary ?? [];
        $fhirData = $order->getPatientAttribute();

        return [
            'name' => $order->getValue(DocusealFields::PATIENT_NAME) ?: $order->patient_display_id,
            'first_name' => $clinicalSummary['patient']['first_name'] ?? null,
            'last_name' => $clinicalSummary['patient']['last_name'] ?? null,
            'display_id' => $order->patient_display_id,
            'dob' => $order->getValue(DocusealFields::PATIENT_DOB),
            'gender' => $order->getValue(DocusealFields::PATIENT_GENDER),
            'phone' => $order->getValue(DocusealFields::PATIENT_PHONE),
            'email' => $order->getValue(DocusealFields::PATIENT_EMAIL),
            'address' => $this->formatAddress([
                'street' => $order->getValue(DocusealFields::PATIENT_ADDRESS),
                'city' => $order->getValue(DocusealFields::PATIENT_CITY),
                'state' => $order->getValue(DocusealFields::PATIENT_STATE),
                'zip' => $order->getValue(DocusealFields::PATIENT_ZIP),
            ]),
            'fhir_data' => $fhirData,
            'is_subscriber' => $clinicalSummary['patient']['is_subscriber'] ?? true,
        ];
    }

    /**
     * Get provider information
     */
    private function getProviderData(ProductRequest $order): array
    {
        return [
            'id' => $order->provider?->id,
            'name' => $order->getValue(DocusealFields::PROVIDER_NAME),
            'npi' => $order->getValue(DocusealFields::PROVIDER_NPI),
            'phone' => $order->getValue(DocusealFields::PROVIDER_PHONE),
            'email' => $order->getValue(DocusealFields::PROVIDER_EMAIL),
            'address' => $order->getValue(DocusealFields::PROVIDER_ADDRESS),
            'specialty' => $order->provider?->specialty,
        ];
    }

    /**
     * Get facility information
     */
    private function getFacilityData(ProductRequest $order): array
    {
        return [
            'id' => $order->facility?->id,
            'name' => $order->getValue(DocusealFields::FACILITY_NAME),
            'npi' => $order->getValue(DocusealFields::FACILITY_NPI),
            'phone' => $order->getValue(DocusealFields::FACILITY_CONTACT_PHONE),
            'email' => $order->getValue(DocusealFields::FACILITY_CONTACT_EMAIL),
            'address' => $this->formatFacilityAddress($order),
            'tax_id' => $order->facility?->tax_id,
        ];
    }

    /**
     * Get insurance information
     */
    private function getInsuranceData(ProductRequest $order): array
    {
        $clinicalSummary = $order->clinical_summary ?? [];

        return [
            'primary' => [
                'name' => $order->getValue(DocusealFields::PRIMARY_INS_NAME) ?: $order->payer_name_submitted,
                'member_id' => $order->getValue(DocusealFields::PRIMARY_INS_MEMBER_ID),
                'plan_type' => $clinicalSummary['insurance']['primary_plan_type'] ?? null,
            ],
            'secondary' => [
                'name' => $order->getValue(DocusealFields::SECONDARY_INS_NAME),
                'member_id' => $order->getValue(DocusealFields::SECONDARY_INS_MEMBER_ID),
                'plan_type' => $clinicalSummary['insurance']['secondary_plan_type'] ?? null,
            ],
            'has_secondary' => $clinicalSummary['insurance']['has_secondary'] ?? false,
        ];
    }

    /**
     * Get product information
     */
    private function getProductData(ProductRequest $order): array
    {
        $clinicalSummary = $order->clinical_summary ?? [];
        $product = $order->products()->first();

        return [
            'name' => $order->getValue(DocusealFields::PRODUCT_NAME) ?: $product?->name,
            'code' => $order->getValue(DocusealFields::PRODUCT_CODE) ?: $product?->q_code,
            'size' => $order->getValue(DocusealFields::PRODUCT_SIZE),
            'quantity' => $order->getValue(DocusealFields::PRODUCT_QUANTITY) ?: 1,
            'category' => $order->getValue(DocusealFields::PRODUCT_CATEGORY),
            'manufacturer' => $product?->manufacturer?->name ?? $order->getManufacturer(),
            'manufacturer_id' => $clinicalSummary['product_selection']['manufacturer_id'] ?? null,
            'selected_products' => $clinicalSummary['product_selection']['selected_products'] ?? [],
            'shipping_info' => [
                'speed' => $clinicalSummary['order_preferences']['shipping_speed'] ?? 'Standard',
                'instructions' => $clinicalSummary['order_preferences']['delivery_instructions'] ?? null,
            ],
        ];
    }

    /**
     * Get clinical information
     */
    private function getClinicalData(ProductRequest $order): array
    {
        $clinicalSummary = $order->clinical_summary ?? [];

        return [
            'wound_type' => $order->getValue(DocusealFields::WOUND_TYPE) ?: $order->wound_type,
            'wound_location' => $order->getValue(DocusealFields::WOUND_LOCATION),
            'wound_size' => $this->formatWoundSize($clinicalSummary),
            'wound_depth' => $clinicalSummary['clinical']['wound_size_depth'] ?? null,
            'diagnosis_codes' => $clinicalSummary['clinical']['diagnosis_codes'] ?? [],
            'primary_diagnosis' => $order->getValue(DocusealFields::ICD10_PRIMARY),
            'secondary_diagnosis' => $order->getValue(DocusealFields::ICD10_SECONDARY),
            'cpt_codes' => $clinicalSummary['clinical']['application_cpt_codes'] ?? [],
            'clinical_notes' => $order->getValue(DocusealFields::CLINICAL_NOTES),
            'wound_duration_weeks' => $clinicalSummary['clinical']['wound_duration_weeks'] ?? null,
            'failed_conservative_treatment' => $clinicalSummary['attestations']['failed_conservative_treatment'] ?? false,
        ];
    }

    /**
     * Get file information with priority handling
     */
    private function getFileData(ProductRequest $order): array
    {
        return [
            'ivr' => [
                'original_url' => $order->ivr_document_url,
                'original_name' => $this->extractFileName($order->ivr_document_url),
                'uploaded_url' => $order->altered_ivr_file_url,
                'uploaded_name' => $order->altered_ivr_file_name,
                'uploaded_at' => $order->altered_ivr_uploaded_at?->toISOString(),
                'uploaded_by' => $order->alteredIvrUploadedBy?->name,
                'active_url' => $order->altered_ivr_file_url ?: $order->ivr_document_url,
                'active_name' => $order->altered_ivr_file_name ?: $this->extractFileName($order->ivr_document_url),
                'has_upload' => !empty($order->altered_ivr_file_path),
            ],
            'order_form' => [
                'original_url' => $order->episode?->order_form_url,
                'original_name' => $this->extractFileName($order->episode?->order_form_url),
                'uploaded_url' => $order->altered_order_form_file_url,
                'uploaded_name' => $order->altered_order_form_file_name,
                'uploaded_at' => $order->altered_order_form_uploaded_at?->toISOString(),
                'uploaded_by' => $order->alteredOrderFormUploadedBy?->name,
                'active_url' => $order->altered_order_form_file_url ?: $order->episode?->order_form_url,
                'active_name' => $order->altered_order_form_file_name ?: $this->extractFileName($order->episode?->order_form_url),
                'has_upload' => !empty($order->altered_order_form_file_path),
            ],
            'additional_documents' => $this->getAdditionalDocuments($order),
        ];
    }

    /**
     * Get metadata and timestamps
     */
    private function getMetadata(ProductRequest $order): array
    {
        $clinicalSummary = $order->clinical_summary ?? [];

        return [
            'timestamps' => [
                'patient_saved_at' => $clinicalSummary['patient']['saved_at'] ?? null,
                'provider_saved_at' => $clinicalSummary['provider']['saved_at'] ?? null,
                'facility_saved_at' => $clinicalSummary['facility']['saved_at'] ?? null,
                'clinical_saved_at' => $clinicalSummary['clinical']['saved_at'] ?? null,
                'insurance_saved_at' => $clinicalSummary['insurance']['saved_at'] ?? null,
                'attestations_saved_at' => $clinicalSummary['attestations']['saved_at'] ?? null,
            ],
            'attestations' => [
                'failed_conservative_treatment' => $clinicalSummary['attestations']['failed_conservative_treatment'] ?? false,
                'information_accurate' => $clinicalSummary['attestations']['information_accurate'] ?? false,
                'medical_necessity_established' => $clinicalSummary['attestations']['medical_necessity_established'] ?? false,
                'maintain_documentation' => $clinicalSummary['attestations']['maintain_documentation'] ?? false,
                'authorize_prior_auth' => $clinicalSummary['attestations']['authorize_prior_auth'] ?? false,
            ],
            'fhir_data' => $order->getFhirData(),
            'carrier' => $order->carrier,
            'tracking_number' => $order->tracking_number,
            'shipping_info' => $order->shipping_info,
        ];
    }

    /**
     * Get additional documents from various sources
     */
    private function getAdditionalDocuments(ProductRequest $order): array
    {
        $documents = [];

        // Add any documents from clinical summary
        $clinicalSummary = $order->clinical_summary ?? [];
        if (isset($clinicalSummary['documents'])) {
            foreach ($clinicalSummary['documents'] as $doc) {
                $documents[] = [
                    'type' => $doc['type'] ?? 'unknown',
                    'name' => $doc['name'] ?? 'Unnamed Document',
                    'url' => $doc['url'] ?? null,
                    'uploaded_at' => $doc['uploaded_at'] ?? null,
                ];
            }
        }

        return $documents;
    }

    /**
     * Format address from components
     */
    private function formatAddress(array $address): ?string
    {
        $parts = array_filter([
            $address['street'],
            $address['city'] ? $address['city'] . ', ' . $address['state'] . ' ' . $address['zip'] : null,
        ]);

        return $parts ? implode("\n", $parts) : null;
    }

    /**
     * Format facility address
     */
    private function formatFacilityAddress(ProductRequest $order): ?string
    {
        if (!$order->facility) {
            return null;
        }

        $facility = $order->facility;
        $parts = array_filter([
            $facility->address_line_1,
            $facility->address_line_2,
            $facility->city ? $facility->city . ', ' . $facility->state . ' ' . $facility->zip_code : null,
        ]);

        return $parts ? implode("\n", $parts) : null;
    }

    /**
     * Format wound size information
     */
    private function formatWoundSize(array $clinicalSummary): ?string
    {
        $size = $clinicalSummary['clinical']['wound_size'] ?? [];

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
}
