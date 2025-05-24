<?php

namespace App\Services\EligibilityEngine;

use App\Models\Order;
use Carbon\Carbon;

class EligibilityRequestMapper
{
    /**
     * Map an Order to Optum Enhanced Eligibility API request format
     * Based on official API specification v0.2.0
     */
    public function mapOrderToEligibilityRequest(Order $order): array
    {
        $controlNumber = $this->generateControlNumber();

        return [
            'controlNumber' => $controlNumber,
            'submitterTransactionIdentifier' => config('eligibility.defaults.submitter_transaction_identifier', 'MSC') . '_' . $order->id,
            'tradingPartnerServiceId' => config('eligibility.trading_partner.service_id'),
            'tradingPartnerName' => config('eligibility.trading_partner.name'),
            'provider' => $this->buildProvider($order),
            'subscriber' => $this->buildSubscriber($order),
            'encounter' => $this->buildEncounter($order),
        ];
    }

    /**
     * Build provider information
     */
    private function buildProvider(Order $order): array
    {
        $facility = $order->facility;

        return [
            'organizationName' => config('eligibility.provider.organization_name', $facility->name ?? 'MSC Wound Care'),
            'npi' => config('eligibility.provider.npi', $facility->npi ?? null),
            'serviceProviderNumber' => config('eligibility.provider.service_provider_number'),
            'providerCode' => config('eligibility.provider.provider_code', 'AT'), // Attending
            'taxId' => config('eligibility.provider.tax_id'),
        ];
    }

    /**
     * Build subscriber information from patient data
     */
    private function buildSubscriber(Order $order): array
    {
        // TODO: This should fetch actual patient data from FHIR or patient records
        // For now using placeholder structure that matches API spec
        return [
            'memberId' => 'PLACEHOLDER_MEMBER_ID', // Required - will need patient insurance info
            'firstName' => 'PLACEHOLDER_FIRST', // Required - will need patient first name
            'lastName' => 'PLACEHOLDER_LAST', // Required - will need patient last name
            'dateOfBirth' => '19800101', // Required - YYYYMMDD format
            'gender' => 'M', // Required - M or F
            'ssn' => '555443333', // Optional - Social Security Number
            'groupNumber' => 'PLACEHOLDER_GROUP', // Optional - Insurance group number
            'address' => [
                'address1' => 'PLACEHOLDER_ADDRESS',
                'city' => 'PLACEHOLDER_CITY',
                'state' => 'PLACEHOLDER_STATE',
                'postalCode' => 'PLACEHOLDER_ZIP'
            ]
        ];
    }

    /**
     * Build encounter information based on order
     */
    private function buildEncounter(Order $order): array
    {
        $serviceDate = Carbon::parse($order->date_of_service ?? now())->format('Ymd');

        return [
            'dateOfService' => $serviceDate,
            'serviceTypeCodes' => $this->buildServiceTypeCodes($order),
        ];
    }

    /**
     * Build service type codes based on order items
     */
    private function buildServiceTypeCodes(Order $order): array
    {
        $serviceCodes = ['30']; // Default: Health benefit plan coverage

        // Add specific service codes based on order items/products
        foreach ($order->orderItems as $item) {
            $product = $item->product;

            if ($product && $product->category) {
                $mappedCode = config('eligibility.service_type_mappings.' . strtolower($product->category));
                if ($mappedCode && !in_array($mappedCode, $serviceCodes)) {
                    $serviceCodes[] = $mappedCode;
                }
            }
        }

        return $serviceCodes;
    }

    /**
     * Generate a unique control number
     */
    private function generateControlNumber(): string
    {
        return str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
    }
}
