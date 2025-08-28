<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use App\Models\Order\Order;
use App\Models\Fhir\Facility;
use App\Models\Users\Organization\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OrderFormPrefillService
{
        /**
     * Get pre-fill data for ACZ order form
     */
    public function getACZOrderFormPrefillData(ProductRequest $productRequest): array
    {
        $data = [];

        // Get basic order information
        $data = array_merge($data, $this->getOrderInformation($productRequest));

        // Get contact information
        $data = array_merge($data, $this->getContactInformation($productRequest));

        // Get product line items
        $data = array_merge($data, $this->getProductLineItems($productRequest));

        // Get totals
        $data = array_merge($data, $this->getTotals($productRequest));

        // Get shipping information
        $data = array_merge($data, $this->getShippingInformation($productRequest));

        // Get patient information
        $data = array_merge($data, $this->getPatientInformation($productRequest));

        // Get additional computed fields
        $data = array_merge($data, $this->getAdditionalComputedFields($productRequest));

        // Add dummy data for testing if no real data is available
        $data = array_merge($data, $this->getDummyData($productRequest));

        return $data;
    }

    /**
     * Get order information fields
     */
    private function getOrderInformation(ProductRequest $productRequest): array
    {
        return [
            'order_date' => $productRequest->created_at ? $productRequest->created_at->format('m/d/Y') : Carbon::now()->format('m/d/Y'),
            'anticipated_application_date' => $productRequest->expected_service_date ? Carbon::parse($productRequest->expected_service_date)->format('m/d/Y') : '',
        ];
    }

        /**
     * Get contact information fields
     */
    private function getContactInformation(ProductRequest $productRequest): array
    {
        $user = Auth::user();
        $facility = $this->getFacility($productRequest);
        $organization = $facility?->organization;

        // Try to get physician name from multiple sources
        $physicianName = $productRequest->physician_name ??
                        $productRequest->provider_name ??
                        $user->name ??
                        $facility?->contact_name ??
                        $organization?->ap_contact_name ?? '';

        return [
            'physician_name' => $physicianName,
            'account_contact_email' => $facility?->contact_email ?? $organization?->contact_email ?? $user->email ?? '',
            'account_contact_name' => $facility?->contact_name ?? $organization?->ap_contact_name ?? $user->name ?? '',
            'account_contact_phone' => $facility?->contact_phone ?? $organization?->ap_contact_phone ?? $user->phone ?? '',
        ];
    }

    /**
     * Get product line items (up to 5)
     */
    private function getProductLineItems(ProductRequest $productRequest): array
    {
        $data = [];
        $selectedProducts = $productRequest->selected_products ?? [];

        // Process up to 5 product lines
        for ($i = 1; $i <= 5; $i++) {
            $productIndex = $i - 1;
            $product = $selectedProducts[$productIndex] ?? null;

            if ($product) {
                $quantity = $product['quantity'] ?? 1;
                $unitPrice = $product['product']['msc_price'] ?? 0;
                $amount = $quantity * $unitPrice;

                $data["quantity_line_{$i}"] = $quantity;
                $data["description_line_{$i}"] = $product['product']['name'] ?? '';
                $data["size_line_{$i}"] = $product['size'] ?? '';
                $data["unit_price_line_{$i}"] = $unitPrice > 0 ? number_format($unitPrice, 2) : '';
                $data["amount_line_{$i}"] = $amount > 0 ? number_format($amount, 2) : '';
            } else {
                // Empty line items
                $data["quantity_line_{$i}"] = '';
                $data["description_line_{$i}"] = '';
                $data["size_line_{$i}"] = '';
                $data["unit_price_line_{$i}"] = '';
                $data["amount_line_{$i}"] = '';
            }
        }

        return $data;
    }

    /**
     * Get totals
     */
    private function getTotals(ProductRequest $productRequest): array
    {
        $selectedProducts = $productRequest->selected_products ?? [];
        $subTotal = 0;

        foreach ($selectedProducts as $product) {
            $quantity = $product['quantity'] ?? 1;
            $unitPrice = $product['product']['msc_price'] ?? 0;
            $subTotal += $quantity * $unitPrice;
        }

        $discount = $productRequest->discount_amount ?? 0;
        $total = $subTotal - $discount;

        return [
            'sub_total' => $subTotal > 0 ? number_format($subTotal, 2) : '',
            'discount' => $discount > 0 ? number_format($discount, 2) : '',
            'total' => $total > 0 ? number_format($total, 2) : '',
        ];
    }

        /**
     * Get shipping information
     */
    private function getShippingInformation(ProductRequest $productRequest): array
    {
        $facility = $this->getFacility($productRequest);
        $organization = $facility?->organization;

        // Get comprehensive shipping address information
        $shippingAddress = $facility?->address ?? $organization?->address ?? '';
        $shippingAddress2 = $facility?->address_line2 ?? $organization?->address_line2 ?? '';
        $shippingCity = $facility?->city ?? $organization?->city ?? '';
        $shippingState = $facility?->state ?? $organization?->region ?? '';
        $shippingZip = $facility?->zip_code ?? $organization?->postal_code ?? '';

        // Determine shipping method preference
        $shippingMethod = $productRequest->shipping_method ?? 'standard';
        $checkFedex = in_array(strtolower($shippingMethod), ['fedex', 'fed-ex', 'fed_ex']) ? 'true' : 'false';

        // Get delivery date from multiple sources
        $deliveryDate = $productRequest->expected_delivery_date ??
                       $productRequest->delivery_date ??
                       $productRequest->anticipated_delivery_date ?? '';

        $formattedDeliveryDate = $deliveryDate ? Carbon::parse($deliveryDate)->format('m/d/Y') : '';

        return [
            'check_fedex' => $checkFedex,
            'date_to_receive' => $formattedDeliveryDate,
            'facility_name' => $facility?->name ?? $organization?->name ?? '',
            'ship_to_address' => $shippingAddress,
            'ship_to_address_2' => $shippingAddress2,
            'ship_to_city' => $shippingCity,
            'ship_to_state' => $shippingState,
            'ship_to_zip' => $shippingZip,
            'notes' => $productRequest->notes ?? $productRequest->special_instructions ?? $productRequest->clinical_notes ?? '',
        ];
    }

    /**
     * Get patient information
     */
    private function getPatientInformation(ProductRequest $productRequest): array
    {
        // Try to get patient ID from multiple sources
        $patientId = $productRequest->patient_id ??
                    $productRequest->fhir_patient_id ??
                    $productRequest->patient_fhir_id ??
                    $productRequest->episode_id ?? '';

        // If we have a patient ID, try to get additional patient info
        $patientInfo = '';
        if ($patientId) {
            // Try to get patient name if available
            if ($productRequest->patient_name) {
                $patientInfo = "Patient ID: {$patientId} - {$productRequest->patient_name}";
            } else {
                $patientInfo = "Patient ID: {$patientId}";
            }
        }

        return [
            'patient_id' => $patientId,
        ];
    }

        /**
     * Get facility for the product request
     */
    private function getFacility(ProductRequest $productRequest): ?Facility
    {
        // Try to get facility from the product request
        if ($productRequest->facility_id) {
            $facility = Facility::with(['organization'])->find($productRequest->facility_id);
            if ($facility) {
                return $facility;
            }
        }

        // Try to get facility from the user's primary facility
        $user = Auth::user();
        if ($user) {
            $facility = $user->facilities()->with(['organization'])->where('is_primary', true)->first();
            if ($facility) {
                return $facility;
            }
        }

        // Try to get facility from the episode if available
        if ($productRequest->episode_id) {
            // You might need to implement episode-facility relationship
            // For now, return null if no facility found
        }

        return null;
    }

    /**
     * Get additional computed fields that might be useful
     */
    private function getAdditionalComputedFields(ProductRequest $productRequest): array
    {
        $facility = $this->getFacility($productRequest);
        $organization = $facility?->organization;

        $additionalFields = [];

        // Add facility/organization identifiers if available
        if ($facility) {
            $additionalFields['facility_npi'] = $facility->npi ?? '';
            $additionalFields['facility_tax_id'] = $facility->tax_id ?? '';
            $additionalFields['facility_ptan'] = $facility->ptan ?? '';
        }

        if ($organization) {
            $additionalFields['organization_tax_id'] = $organization->tax_id ?? '';
            $additionalFields['organization_type'] = $organization->type ?? '';
        }

        // Add user/physician identifiers if available
        $user = Auth::user();
        if ($user) {
            $additionalFields['user_npi'] = $user->npi ?? '';
            $additionalFields['user_tax_id'] = $user->tax_id ?? '';
        }

        return $additionalFields;
    }

    /**
     * Get dummy data for testing when real data is not available
     */
    private function getDummyData(ProductRequest $productRequest): array
    {
        // Only add dummy data if we don't have real data
        if (!empty($productRequest->selected_products)) {
            return [];
        }

        return [
            'quantity_line_1' => '1',
            'description_line_1' => 'Test Product - Wound Care Dressing',
            'size_line_1' => '4x4 inches',
            'unit_price_line_1' => '125.00',
            'amount_line_1' => '125.00',

            'quantity_line_2' => '2',
            'description_line_2' => 'Test Product - Advanced Wound Care',
            'size_line_2' => '6x6 inches',
            'unit_price_line_2' => '200.00',
            'amount_line_2' => '400.00',

            'sub_total' => '525.00',
            'discount' => '50.00',
            'total' => '475.00',

            'check_fedex' => 'true',
            'date_to_receive' => Carbon::now()->addDays(7)->format('m/d/Y'),
            'facility_name' => 'Test Medical Center',
            'ship_to_address' => '123 Medical Plaza',
            'ship_to_city' => 'Test City',
            'ship_to_state' => 'TX',
            'ship_to_zip' => '12345',
            'notes' => 'Test order - please expedite shipping',
        ];
    }

    /**
     * Map pre-fill data to DocuSeal field format
     */
    public function mapToDocusealFields(array $prefillData, array $fieldMappings): array
    {
        $docuSealFields = [];

        // Debug logging
        \Log::info('OrderFormPrefillService: Mapping fields to DocuSeal format', [
            'prefill_data_keys' => array_keys($prefillData),
            'prefill_data_count' => count($prefillData),
            'field_mappings_keys' => array_keys($fieldMappings),
            'field_mappings_count' => count($fieldMappings),
            'sample_prefill_data' => array_slice($prefillData, 0, 5),
            'sample_field_mappings' => array_slice($fieldMappings, 0, 5)
        ]);

        foreach ($fieldMappings as $canonicalField => $docuSealFieldName) {
            if (isset($prefillData[$canonicalField])) {
                $value = $prefillData[$canonicalField];

                // Skip empty values to avoid cluttering the form
                if ($value !== '' && $value !== null && $value !== 'false') {
                    $docuSealFields[] = [
                        'name' => $docuSealFieldName,
                        'default_value' => $value,
                        'readonly' => false
                    ];

                    // Debug logging for successful mappings
                    \Log::info('OrderFormPrefillService: Field mapped successfully', [
                        'canonical_field' => $canonicalField,
                        'docuseal_field_name' => $docuSealFieldName,
                        'value' => $value
                    ]);
                } else {
                    // Debug logging for skipped fields
                    \Log::info('OrderFormPrefillService: Field skipped (empty value)', [
                        'canonical_field' => $canonicalField,
                        'docuseal_field_name' => $docuSealFieldName,
                        'value' => $value
                    ]);
                }
            } else {
                // Debug logging for missing fields
                \Log::info('OrderFormPrefillService: Field not found in prefill data', [
                    'canonical_field' => $canonicalField,
                    'docuseal_field_name' => $docuSealFieldName
                ]);
            }
        }

        // Final debug logging
        \Log::info('OrderFormPrefillService: Mapping complete', [
            'total_fields_mapped' => count($docuSealFields),
            'mapped_fields' => $docuSealFields
        ]);

        return $docuSealFields;
    }
}
