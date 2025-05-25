<?php

namespace App\Services\EligibilityEngine;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EligibilityRequestValidator
{
    /**
     * Validate an eligibility request payload
     */
    public function validate(array $payload): void
    {
        $validator = Validator::make($payload, $this->getRules());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Get validation rules for eligibility request based on official API spec
     */
    private function getRules(): array
    {
        return [
            // Required top-level fields
            'controlNumber' => 'required|string|size:9|regex:/^[0-9]+$/',
            'subscriber' => 'required|array',

            // Optional top-level fields
            'submitterTransactionIdentifier' => 'sometimes|string|max:50',
            'tradingPartnerServiceId' => 'sometimes|string|max:80',
            'tradingPartnerName' => 'sometimes|string|max:80',
            'provider' => 'sometimes|array',
            'encounter' => 'sometimes|array',

            // Provider validation (if present)
            'provider.organizationName' => 'sometimes|string|max:60',
            'provider.npi' => 'sometimes|string|size:10|regex:/^[0-9]+$/',
            'provider.serviceProviderNumber' => 'sometimes|string|max:80',
            'provider.providerCode' => 'sometimes|string|in:AD,AT,BI,CO,CV,H,HH,LA,OT,P1,P2,PC,PE,R,RF,SB,SK,SU',
            'provider.taxId' => 'sometimes|string|max:80',

            // Subscriber validation (required)
            'subscriber.memberId' => 'required|string|min:2|max:80',
            'subscriber.firstName' => 'required|string|min:1|max:35',
            'subscriber.lastName' => 'required|string|min:1|max:60',
            'subscriber.dateOfBirth' => 'required|string|size:8|regex:/^[0-9]+$/',
            'subscriber.gender' => 'required|string|in:M,F',
            'subscriber.ssn' => 'sometimes|string|max:50',
            'subscriber.groupNumber' => 'sometimes|string|max:50',
            'subscriber.address' => 'sometimes|array',
            'subscriber.address.address1' => 'sometimes|string|max:55',
            'subscriber.address.city' => 'sometimes|string|max:30',
            'subscriber.address.state' => 'sometimes|string|max:2',
            'subscriber.address.postalCode' => 'sometimes|string|max:15',

            // Encounter validation (if present)
            'encounter.dateOfService' => 'sometimes|string|size:8|regex:/^[0-9]+$/',
            'encounter.serviceTypeCodes' => 'sometimes|array|min:1',
            'encounter.serviceTypeCodes.*' => 'string|max:3',
        ];
    }

    /**
     * Validate basic structure requirements
     */
    public function validateBasicStructure(array $payload): void
    {
        $required = ['transactionSet', 'submitter', 'receiver'];

        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        if (!isset($payload['transactionSet']['transactions']) || empty($payload['transactionSet']['transactions'])) {
            throw new \InvalidArgumentException("At least one transaction is required");
        }
    }

    /**
     * Validate control numbers format
     */
    public function validateControlNumbers(array $payload): void
    {
        $transactionSetControlNumber = $payload['transactionSet']['transactionSetControlNumber'] ?? '';

        if (!preg_match('/^\d{1,9}$/', $transactionSetControlNumber)) {
            throw new \InvalidArgumentException("Transaction set control number must be 1-9 digits");
        }

        foreach ($payload['transactionSet']['transactions'] as $index => $transaction) {
            $transactionControlNumber = $transaction['transactionControlNumber'] ?? '';

            if (!preg_match('/^\d{1,9}$/', $transactionControlNumber)) {
                throw new \InvalidArgumentException("Transaction control number for transaction {$index} must be 1-9 digits");
            }
        }
    }

    /**
     * Validate date formats
     */
    public function validateDates(array $payload): void
    {
        foreach ($payload['transactionSet']['transactions'] as $index => $transaction) {
            if (isset($transaction['requestValidation']['requestDate'])) {
                $requestDate = $transaction['requestValidation']['requestDate'];

                if (!preg_match('/^\d{8}$/', $requestDate)) {
                    throw new \InvalidArgumentException("Request date for transaction {$index} must be in YYYYMMDD format");
                }
            }

            if (isset($transaction['serviceTypeCode'])) {
                foreach ($transaction['serviceTypeCode'] as $serviceIndex => $service) {
                    if (isset($service['serviceDate'])) {
                        $serviceDate = $service['serviceDate'];

                        if (!preg_match('/^\d{8}$/', $serviceDate)) {
                            throw new \InvalidArgumentException("Service date for transaction {$index}, service {$serviceIndex} must be in YYYYMMDD format");
                        }
                    }
                }
            }
        }
    }

    /**
     * Validate NPI numbers
     */
    public function validateNPIs(array $payload): void
    {
        foreach ($payload['transactionSet']['transactions'] as $index => $transaction) {
            if (isset($transaction['serviceProvider']['npi'])) {
                $npi = $transaction['serviceProvider']['npi'];

                if (!preg_match('/^\d{10}$/', $npi)) {
                    throw new \InvalidArgumentException("NPI for transaction {$index} must be exactly 10 digits");
                }
            }
        }
    }
}
