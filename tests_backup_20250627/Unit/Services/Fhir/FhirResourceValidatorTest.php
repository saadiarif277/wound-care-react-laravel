<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fhir;

use App\Services\Fhir\FhirResourceValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FhirResourceValidatorTest extends TestCase
{
    private FhirResourceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FhirResourceValidator();
    }

    public function test_validates_patient_resource_successfully(): void
    {
        $patient = [
            'resourceType' => 'Patient',
            'name' => [
                [
                    'use' => 'official',
                    'family' => 'Doe',
                    'given' => ['John'],
                ],
            ],
            'gender' => 'male',
            'birthDate' => '1990-01-01',
        ];

        $errors = $this->validator->validate($patient);
        $this->assertEmpty($errors);
    }

    public function test_validates_patient_missing_required_fields(): void
    {
        $patient = [
            'resourceType' => 'Patient',
            'gender' => 'male',
            // Missing name and birthDate
        ];

        $errors = $this->validator->validate($patient);
        $this->assertNotEmpty($errors);
    }

    public function test_validates_practitioner_resource(): void
    {
        $practitioner = [
            'resourceType' => 'Practitioner',
            'name' => [
                [
                    'family' => 'Smith',
                    'given' => ['Jane'],
                ],
            ],
            'identifier' => [
                [
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => '1234567890',
                ],
            ],
        ];

        $errors = $this->validator->validate($practitioner);
        $this->assertEmpty($errors);
    }

    public function test_validates_coverage_resource(): void
    {
        $coverage = [
            'resourceType' => 'Coverage',
            'status' => 'active',
            'beneficiary' => [
                'reference' => 'Patient/123',
            ],
            'payor' => [
                [
                    'reference' => 'Organization/456',
                ],
            ],
        ];

        $errors = $this->validator->validate($coverage);
        $this->assertEmpty($errors);
    }

    public function test_validates_device_request_resource(): void
    {
        $deviceRequest = [
            'resourceType' => 'DeviceRequest',
            'status' => 'active',
            'intent' => 'order',
            'codeCodeableConcept' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '123456',
                        'display' => 'Wound dressing',
                    ],
                ],
            ],
            'subject' => [
                'reference' => 'Patient/123',
            ],
        ];

        $errors = $this->validator->validate($deviceRequest);
        $this->assertEmpty($errors);
    }

    public function test_validates_npi_format(): void
    {
        // Valid NPI
        $practitioner = [
            'resourceType' => 'Practitioner',
            'name' => [['family' => 'Test', 'given' => ['Test']]],
            'identifier' => [
                [
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => '1234567890',
                ],
            ],
        ];

        $errors = $this->validator->validate($practitioner);
        $this->assertEmpty($errors);

        // Invalid NPI (too short)
        $practitioner['identifier'][0]['value'] = '123456789';
        $errors = $this->validator->validate($practitioner);
        $this->assertNotEmpty($errors);
    }

    public function test_validates_date_formats(): void
    {
        $patient = [
            'resourceType' => 'Patient',
            'name' => [['family' => 'Test', 'given' => ['Test']]],
            'gender' => 'male',
            'birthDate' => '1990-01-01',
        ];

        $errors = $this->validator->validate($patient);
        $this->assertEmpty($errors);

        // Invalid date format
        $patient['birthDate'] = '01/01/1990';
        $errors = $this->validator->validate($patient);
        $this->assertNotEmpty($errors);
    }

    public function test_validates_reference_format(): void
    {
        $coverage = [
            'resourceType' => 'Coverage',
            'status' => 'active',
            'beneficiary' => [
                'reference' => 'Patient/123',
            ],
            'payor' => [
                [
                    'reference' => 'Organization/456',
                ],
            ],
        ];

        $errors = $this->validator->validate($coverage);
        $this->assertEmpty($errors);

        // Invalid reference format
        $coverage['beneficiary']['reference'] = 'invalid-reference';
        $errors = $this->validator->validate($coverage);
        $this->assertNotEmpty($errors);
    }

    public function test_validate_with_profiles(): void
    {
        $validator = new FhirResourceValidator(['us-core-patient'], true);

        $patient = [
            'resourceType' => 'Patient',
            'name' => [
                [
                    'family' => 'Doe',
                    'given' => ['John'],
                ],
            ],
            'gender' => 'male',
            'birthDate' => '1990-01-01',
            // US Core requires race and ethnicity extensions
        ];

        $errors = $validator->validate($patient);
        
        // In strict mode with US Core profile, should have errors for missing extensions
        if ($validator->isStrictMode()) {
            $this->assertNotEmpty($errors);
        }
    }

    public function test_throws_validation_exception_when_configured(): void
    {
        $patient = [
            'resourceType' => 'Patient',
            // Missing required fields
        ];

        $this->expectException(ValidationException::class);
        
        $this->validator->validateOrThrow($patient);
    }
}