<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CanonicalField;

class CanonicalFieldsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $canonicalFields = $this->getCanonicalFields();
        
        foreach ($canonicalFields as $category => $fields) {
            foreach ($fields as $fieldName => $fieldData) {
                CanonicalField::updateOrCreate(
                    [
                        'category' => $category,
                        'field_name' => $fieldName,
                    ],
                    [
                        'field_path' => $category . '.' . $fieldName,
                        'display_name' => $fieldData['display_name'] ?? $this->formatFieldName($fieldName),
                        'data_type' => $fieldData['data_type'] ?? 'string',
                        'is_required' => $fieldData['required'] ?? false,
                        'is_phi' => $fieldData['is_phi'] ?? $this->isPhiField($category, $fieldName),
                        'validation_rules' => json_encode($fieldData['validation_rules'] ?? []),
                        'description' => $fieldData['description'] ?? null,
                        'example_values' => json_encode($fieldData['example_values'] ?? []),
                        'metadata' => json_encode([
                            'source' => 'canonical_structure',
                            'version' => '1.0',
                        ]),
                    ]
                );
            }
        }
        
        $this->command->info('Canonical fields seeded successfully!');
        $this->command->info('Total categories: ' . count($canonicalFields));
        $this->command->info('Total fields: ' . CanonicalField::count());
    }

    /**
     * Get canonical field structure
     */
    private function getCanonicalFields(): array
    {
        return [
            'physicianInformation' => [
                'npi' => [
                    'display_name' => 'NPI Number',
                    'data_type' => 'string',
                    'required' => true,
                    'validation_rules' => ['regex:/^\d{10}$/'],
                    'description' => 'National Provider Identifier',
                    'is_phi' => false,
                ],
                'firstName' => [
                    'display_name' => 'First Name',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => false,
                ],
                'lastName' => [
                    'display_name' => 'Last Name',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => false,
                ],
                'phone' => [
                    'display_name' => 'Phone Number',
                    'data_type' => 'string',
                    'validation_rules' => ['regex:/^\(\d{3}\) \d{3}-\d{4}$/'],
                    'is_phi' => false,
                ],
                'fax' => [
                    'display_name' => 'Fax Number',
                    'data_type' => 'string',
                    'validation_rules' => ['regex:/^\(\d{3}\) \d{3}-\d{4}$/'],
                    'is_phi' => false,
                ],
                'email' => [
                    'display_name' => 'Email Address',
                    'data_type' => 'string',
                    'validation_rules' => ['email'],
                    'is_phi' => false,
                ],
            ],
            'facilityInformation' => [
                'facilityName' => [
                    'display_name' => 'Facility Name',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => false,
                ],
                'streetAddress' => [
                    'display_name' => 'Street Address',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => false,
                ],
                'city' => [
                    'display_name' => 'City',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => false,
                ],
                'state' => [
                    'display_name' => 'State',
                    'data_type' => 'string',
                    'required' => true,
                    'validation_rules' => ['regex:/^[A-Z]{2}$/'],
                    'is_phi' => false,
                ],
                'zipCode' => [
                    'display_name' => 'ZIP Code',
                    'data_type' => 'string',
                    'required' => true,
                    'validation_rules' => ['regex:/^\d{5}(-\d{4})?$/'],
                    'is_phi' => false,
                ],
                'phone' => [
                    'display_name' => 'Phone Number',
                    'data_type' => 'string',
                    'validation_rules' => ['regex:/^\(\d{3}\) \d{3}-\d{4}$/'],
                    'is_phi' => false,
                ],
                'fax' => [
                    'display_name' => 'Fax Number',
                    'data_type' => 'string',
                    'validation_rules' => ['regex:/^\(\d{3}\) \d{3}-\d{4}$/'],
                    'is_phi' => false,
                ],
            ],
            'patientInformation' => [
                'firstName' => [
                    'display_name' => 'First Name',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'lastName' => [
                    'display_name' => 'Last Name',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'middleInitial' => [
                    'display_name' => 'Middle Initial',
                    'data_type' => 'string',
                    'validation_rules' => ['max:1'],
                    'is_phi' => true,
                ],
                'dateOfBirth' => [
                    'display_name' => 'Date of Birth',
                    'data_type' => 'date',
                    'required' => true,
                    'is_phi' => true,
                ],
                'gender' => [
                    'display_name' => 'Gender',
                    'data_type' => 'string',
                    'validation_rules' => ['in:M,F,O'],
                    'is_phi' => true,
                ],
                'homePhone' => [
                    'display_name' => 'Home Phone',
                    'data_type' => 'string',
                    'validation_rules' => ['regex:/^\(\d{3}\) \d{3}-\d{4}$/'],
                    'is_phi' => true,
                ],
                'cellPhone' => [
                    'display_name' => 'Cell Phone',
                    'data_type' => 'string',
                    'validation_rules' => ['regex:/^\(\d{3}\) \d{3}-\d{4}$/'],
                    'is_phi' => true,
                ],
                'email' => [
                    'display_name' => 'Email Address',
                    'data_type' => 'string',
                    'validation_rules' => ['email'],
                    'is_phi' => true,
                ],
                'streetAddress' => [
                    'display_name' => 'Street Address',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'city' => [
                    'display_name' => 'City',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'state' => [
                    'display_name' => 'State',
                    'data_type' => 'string',
                    'required' => true,
                    'validation_rules' => ['regex:/^[A-Z]{2}$/'],
                    'is_phi' => true,
                ],
                'zipCode' => [
                    'display_name' => 'ZIP Code',
                    'data_type' => 'string',
                    'required' => true,
                    'validation_rules' => ['regex:/^\d{5}(-\d{4})?$/'],
                    'is_phi' => true,
                ],
            ],
            'insuranceInformation' => [
                'primaryInsurance' => [
                    'display_name' => 'Primary Insurance',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'memberId' => [
                    'display_name' => 'Member ID',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'groupNumber' => [
                    'display_name' => 'Group Number',
                    'data_type' => 'string',
                    'is_phi' => true,
                ],
                'policyholderName' => [
                    'display_name' => 'Policyholder Name',
                    'data_type' => 'string',
                    'is_phi' => true,
                ],
                'relationshipToInsured' => [
                    'display_name' => 'Relationship to Insured',
                    'data_type' => 'string',
                    'validation_rules' => ['in:Self,Spouse,Child,Other'],
                    'is_phi' => true,
                ],
                'verificationDate' => [
                    'display_name' => 'Verification Date',
                    'data_type' => 'date',
                    'is_phi' => false,
                ],
                'verificationReference' => [
                    'display_name' => 'Verification Reference',
                    'data_type' => 'string',
                    'is_phi' => false,
                ],
            ],
            'clinicalInformation' => [
                'diagnosisCodes' => [
                    'display_name' => 'Diagnosis Codes',
                    'data_type' => 'array',
                    'required' => true,
                    'is_phi' => true,
                ],
                'woundLocation' => [
                    'display_name' => 'Wound Location',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => true,
                ],
                'woundType' => [
                    'display_name' => 'Wound Type',
                    'data_type' => 'string',
                    'validation_rules' => ['in:Pressure Ulcer,Diabetic Ulcer,Venous Ulcer,Arterial Ulcer,Surgical Wound,Traumatic Wound,Other'],
                    'is_phi' => true,
                ],
                'woundLength' => [
                    'display_name' => 'Wound Length (cm)',
                    'data_type' => 'number',
                    'is_phi' => true,
                ],
                'woundWidth' => [
                    'display_name' => 'Wound Width (cm)',
                    'data_type' => 'number',
                    'is_phi' => true,
                ],
                'woundDepth' => [
                    'display_name' => 'Wound Depth (cm)',
                    'data_type' => 'number',
                    'is_phi' => true,
                ],
                'woundDuration' => [
                    'display_name' => 'Wound Duration',
                    'data_type' => 'string',
                    'is_phi' => true,
                ],
                'treatmentStartDate' => [
                    'display_name' => 'Treatment Start Date',
                    'data_type' => 'date',
                    'is_phi' => true,
                ],
            ],
            'productInformation' => [
                'productName' => [
                    'display_name' => 'Product Name',
                    'data_type' => 'string',
                    'required' => true,
                    'is_phi' => false,
                ],
                'productCode' => [
                    'display_name' => 'Product Code',
                    'data_type' => 'string',
                    'is_phi' => false,
                ],
                'quantity' => [
                    'display_name' => 'Quantity',
                    'data_type' => 'integer',
                    'required' => true,
                    'is_phi' => false,
                ],
                'frequency' => [
                    'display_name' => 'Frequency',
                    'data_type' => 'string',
                    'is_phi' => false,
                ],
                'duration' => [
                    'display_name' => 'Duration',
                    'data_type' => 'string',
                    'is_phi' => false,
                ],
            ],
            'additionalInformation' => [
                'additionalNotes' => [
                    'display_name' => 'Additional Notes',
                    'data_type' => 'text',
                    'is_phi' => true,
                ],
                'specialInstructions' => [
                    'display_name' => 'Special Instructions',
                    'data_type' => 'text',
                    'is_phi' => false,
                ],
                'referenceNumber' => [
                    'display_name' => 'Reference Number',
                    'data_type' => 'string',
                    'is_phi' => false,
                ],
                'submissionDate' => [
                    'display_name' => 'Submission Date',
                    'data_type' => 'date',
                    'is_phi' => false,
                ],
                'submittedBy' => [
                    'display_name' => 'Submitted By',
                    'data_type' => 'string',
                    'is_phi' => false,
                ],
            ],
        ];
    }

    /**
     * Format field name for display
     */
    private function formatFieldName(string $fieldName): string
    {
        return ucwords(str_replace('_', ' ', preg_replace('/(?<!^)[A-Z]/', ' $0', $fieldName)));
    }

    /**
     * Determine if field contains PHI
     */
    private function isPhiField(string $category, string $fieldName): bool
    {
        $phiCategories = ['patientInformation', 'insuranceInformation', 'clinicalInformation'];
        $nonPhiFields = ['verificationDate', 'verificationReference'];
        
        if (in_array($fieldName, $nonPhiFields)) {
            return false;
        }
        
        return in_array($category, $phiCategories);
    }
}