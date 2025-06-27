<?php

namespace App\Services\EligibilityEngine;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EligibilityRequestValidator
{
    /**
     * Validate eligibility request structure
     */
    public function validate(array $request): void
    {
        $validator = Validator::make($request, [
            'controlNumber' => 'required|string|max:10',
            'submitterTransactionIdentifier' => 'required|string|max:50',
            'tradingPartnerServiceId' => 'required|string',

            // Provider validation
            'provider.npi' => 'required|string|size:10',
            'provider.organizationName' => 'required|string|max:100',
            'provider.providerCode' => 'required|string|max:2',
            'provider.serviceLocation.address' => 'required|string|max:100',
            'provider.serviceLocation.city' => 'required|string|max:50',
            'provider.serviceLocation.state' => 'required|string|size:2',
            'provider.serviceLocation.postalCode' => 'required|string|max:10',

            // Subscriber validation
            'subscriber.memberId' => 'required|string|max:50',
            'subscriber.firstName' => 'required|string|max:50',
            'subscriber.lastName' => 'required|string|max:50',
            'subscriber.dateOfBirth' => 'required|string|size:8', // YYYYMMDD
            'subscriber.gender' => 'required|string|size:1|in:M,F',
            'subscriber.address.address1' => 'required|string|max:100',
            'subscriber.address.city' => 'required|string|max:50',
            'subscriber.address.state' => 'required|string|size:2',
            'subscriber.address.postalCode' => 'required|string|max:10',

            // Encounter validation
            'encounter.dateOfService' => 'required|string|size:8', // YYYYMMDD
            'encounter.serviceTypeCodes' => 'required|array',
            'encounter.serviceTypeCodes.*' => 'string|max:3',
            'encounter.placeOfService' => 'required|string|max:2',
            'encounter.procedureCodes' => 'nullable|array',
            'encounter.procedureCodes.*' => 'string|max:5'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Custom validation rules
        $this->validateNpi($request['provider']['npi']);
        $this->validateDateFormats($request);
        $this->validateStateCode($request['provider']['serviceLocation']['state']);
        $this->validateStateCode($request['subscriber']['address']['state']);
    }

    /**
     * Validate NPI format
     */
    private function validateNpi(string $npi): void
    {
        // NPI must be 10 digits
        if (!preg_match('/^\d{10}$/', $npi)) {
            throw new ValidationException(
                Validator::make([], [])
                    ->after(function ($validator) {
                        $validator->errors()->add('provider.npi', 'NPI must be exactly 10 digits');
                    })
            );
        }

        // Optional: Add NPI checksum validation if needed
    }

    /**
     * Validate date formats (YYYYMMDD)
     */
    private function validateDateFormats(array $request): void
    {
        $dates = [
            'subscriber.dateOfBirth' => $request['subscriber']['dateOfBirth'],
            'encounter.dateOfService' => $request['encounter']['dateOfService']
        ];

        foreach ($dates as $field => $date) {
            if (!preg_match('/^\d{8}$/', $date)) {
                throw new ValidationException(
                    Validator::make([], [])
                        ->after(function ($validator) use ($field) {
                            $validator->errors()->add($field, 'Date must be in YYYYMMDD format');
                        })
                );
            }

            // Validate that it's a real date
            $year = substr($date, 0, 4);
            $month = substr($date, 4, 2);
            $day = substr($date, 6, 2);

            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                throw new ValidationException(
                    Validator::make([], [])
                        ->after(function ($validator) use ($field) {
                            $validator->errors()->add($field, 'Invalid date');
                        })
                );
            }
        }
    }

    /**
     * Validate state codes
     */
    private function validateStateCode(string $state): void
    {
        $validStates = [
            'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
            'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
            'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
            'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
            'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
            'DC', 'PR', 'VI', 'GU', 'AS', 'MP'
        ];

        if (!in_array(strtoupper($state), $validStates)) {
            throw new ValidationException(
                Validator::make([], [])
                    ->after(function ($validator) {
                        $validator->errors()->add('state', 'Invalid state code');
                    })
            );
        }
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
