<?php

namespace Tests\Unit\Services;

use App\Services\FhirToIvrFieldMapper;
use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Models\Fhir\Patient;
use App\Models\Fhir\Practitioner;
use App\Models\Fhir\Organization;
use App\Models\Fhir\Coverage;
use App\Models\Fhir\Condition;
use Tests\TestCase;
use Mockery;

class FhirToIvrFieldMapperTest extends TestCase
{
    protected FhirToIvrFieldMapper $mapper;
    protected $fhirService;
    protected $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fhirService = Mockery::mock(FhirService::class);
        $this->logger = Mockery::mock(PhiSafeLogger::class);
        
        $this->mapper = new FhirToIvrFieldMapper($this->fhirService, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_extract_comprehensive_data_from_fhir_resources()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123',
            'practitioner_id' => 'practitioner-456',
            'organization_id' => 'organization-789',
            'coverage_id' => 'coverage-101',
            'condition_id' => 'condition-112'
        ];

        $metadata = [
            'episode_id' => 'episode-999',
            'manufacturer' => 'BioWound Solutions'
        ];

        $mockPatient = [
            'id' => 'patient-123',
            'name' => [
                ['given' => ['John'], 'family' => 'Doe']
            ],
            'birthDate' => '1980-01-01',
            'gender' => 'male',
            'telecom' => [
                ['system' => 'phone', 'value' => '555-123-4567']
            ],
            'address' => [
                [
                    'line' => ['123 Main St'],
                    'city' => 'Anytown',
                    'state' => 'NY',
                    'postalCode' => '12345'
                ]
            ]
        ];

        $mockPractitioner = [
            'id' => 'practitioner-456',
            'name' => [
                ['given' => ['Dr. Jane'], 'family' => 'Smith']
            ],
            'identifier' => [
                ['system' => 'NPI', 'value' => '1234567890']
            ],
            'telecom' => [
                ['system' => 'phone', 'value' => '555-987-6543']
            ]
        ];

        $mockOrganization = [
            'id' => 'organization-789',
            'name' => 'General Hospital',
            'address' => [
                [
                    'line' => ['456 Hospital Blvd'],
                    'city' => 'Anytown',
                    'state' => 'NY',
                    'postalCode' => '12345'
                ]
            ],
            'telecom' => [
                ['system' => 'phone', 'value' => '555-555-5555']
            ]
        ];

        $mockCoverage = [
            'id' => 'coverage-101',
            'subscriberId' => 'MEMBER123',
            'payor' => [
                ['display' => 'Blue Cross Blue Shield']
            ],
            'type' => [
                'coding' => [
                    ['code' => 'primary', 'display' => 'Primary Coverage']
                ]
            ]
        ];

        $mockCondition = [
            'id' => 'condition-112',
            'code' => [
                'coding' => [
                    ['system' => 'ICD-10', 'code' => 'E11.621', 'display' => 'Type 2 diabetes with foot ulcer']
                ]
            ],
            'bodySite' => [
                [
                    'coding' => [
                        ['code' => 'foot', 'display' => 'Foot']
                    ]
                ]
            ]
        ];

        // Set up expectations
        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->fhirService->shouldReceive('read')
            ->with('Practitioner', 'practitioner-456')
            ->andReturn($mockPractitioner);

        $this->fhirService->shouldReceive('read')
            ->with('Organization', 'organization-789')
            ->andReturn($mockOrganization);

        $this->fhirService->shouldReceive('read')
            ->with('Coverage', 'coverage-101')
            ->andReturn($mockCoverage);

        $this->fhirService->shouldReceive('read')
            ->with('Condition', 'condition-112')
            ->andReturn($mockCondition);

        $this->logger->shouldReceive('info')->atLeast()->once();

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('patient_name', $result);
        $this->assertArrayHasKey('patient_dob', $result);
        $this->assertArrayHasKey('patient_gender', $result);
        $this->assertArrayHasKey('patient_phone', $result);
        $this->assertArrayHasKey('patient_address', $result);
        $this->assertArrayHasKey('provider_name', $result);
        $this->assertArrayHasKey('provider_npi', $result);
        $this->assertArrayHasKey('facility_name', $result);
        $this->assertArrayHasKey('primary_insurance_name', $result);
        $this->assertArrayHasKey('primary_member_id', $result);
        $this->assertArrayHasKey('diagnosis_code', $result);
        $this->assertArrayHasKey('wound_location', $result);

        $this->assertEquals('John Doe', $result['patient_name']);
        $this->assertEquals('1980-01-01', $result['patient_dob']);
        $this->assertEquals('male', $result['patient_gender']);
        $this->assertEquals('555-123-4567', $result['patient_phone']);
        $this->assertEquals('Dr. Jane Smith', $result['provider_name']);
        $this->assertEquals('1234567890', $result['provider_npi']);
        $this->assertEquals('General Hospital', $result['facility_name']);
        $this->assertEquals('Blue Cross Blue Shield', $result['primary_insurance_name']);
        $this->assertEquals('MEMBER123', $result['primary_member_id']);
        $this->assertEquals('E11.621', $result['diagnosis_code']);
        $this->assertEquals('foot', $result['wound_location']);
    }

    /** @test */
    public function it_handles_missing_fhir_resources_gracefully()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
        ];

        $metadata = [
            'episode_id' => 'episode-999'
        ];

        $mockPatient = [
            'id' => 'patient-123',
            'name' => [
                ['given' => ['John'], 'family' => 'Doe']
            ],
            'birthDate' => '1980-01-01',
            'gender' => 'male'
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->logger->shouldReceive('info')->atLeast()->once();

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('patient_name', $result);
        $this->assertArrayHasKey('patient_dob', $result);
        $this->assertArrayHasKey('patient_gender', $result);
        $this->assertEquals('John Doe', $result['patient_name']);
        $this->assertEquals('1980-01-01', $result['patient_dob']);
        $this->assertEquals('male', $result['patient_gender']);
    }

    /** @test */
    public function it_applies_manufacturer_specific_field_mappings()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123',
            'condition_id' => 'condition-112'
        ];

        $metadata = [
            'episode_id' => 'episode-999',
            'manufacturer' => 'BioWound Solutions'
        ];

        $mockPatient = [
            'id' => 'patient-123',
            'name' => [
                ['given' => ['John'], 'family' => 'Doe']
            ],
            'birthDate' => '1980-01-01',
            'gender' => 'male'
        ];

        $mockCondition = [
            'id' => 'condition-112',
            'code' => [
                'coding' => [
                    ['system' => 'ICD-10', 'code' => 'E11.621', 'display' => 'Type 2 diabetes with foot ulcer']
                ]
            ],
            'bodySite' => [
                [
                    'coding' => [
                        ['code' => 'foot', 'display' => 'Foot']
                    ]
                ]
            ]
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->fhirService->shouldReceive('read')
            ->with('Condition', 'condition-112')
            ->andReturn($mockCondition);

        $this->logger->shouldReceive('info')->atLeast()->once();

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        
        // Check for BioWound Solutions specific fields
        $this->assertArrayHasKey('wound_dfu', $result);
        $this->assertArrayHasKey('wound_vlu', $result);
        $this->assertArrayHasKey('wound_chronic_ulcer', $result);
        $this->assertArrayHasKey('new_request', $result);
        $this->assertArrayHasKey('patient_snf_yes', $result);
        $this->assertArrayHasKey('patient_snf_no', $result);
        
        // Verify wound type classification
        $this->assertTrue($result['wound_dfu']); // Should be true for diabetes with foot ulcer
        $this->assertEquals('yes', $result['new_request']);
        $this->assertEquals('no', $result['patient_snf_yes']);
        $this->assertEquals('yes', $result['patient_snf_no']);
    }

    /** @test */
    public function it_handles_fhir_service_exceptions()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
        ];

        $metadata = [
            'episode_id' => 'episode-999'
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andThrow(new \Exception('FHIR service unavailable'));

        $this->logger->shouldReceive('error')->once();

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_extracts_patient_data_correctly()
    {
        // Arrange
        $mockPatient = [
            'id' => 'patient-123',
            'name' => [
                ['given' => ['John', 'Michael'], 'family' => 'Doe']
            ],
            'birthDate' => '1980-01-01',
            'gender' => 'male',
            'telecom' => [
                ['system' => 'phone', 'value' => '555-123-4567'],
                ['system' => 'email', 'value' => 'john.doe@example.com']
            ],
            'address' => [
                [
                    'line' => ['123 Main St', 'Apt 4B'],
                    'city' => 'Anytown',
                    'state' => 'NY',
                    'postalCode' => '12345'
                ]
            ]
        ];

        // Act
        $result = $this->mapper->extractPatientData($mockPatient);

        // Assert
        $this->assertEquals('John Michael Doe', $result['patient_name']);
        $this->assertEquals('John', $result['patient_first_name']);
        $this->assertEquals('Doe', $result['patient_last_name']);
        $this->assertEquals('1980-01-01', $result['patient_dob']);
        $this->assertEquals('male', $result['patient_gender']);
        $this->assertEquals('555-123-4567', $result['patient_phone']);
        $this->assertEquals('john.doe@example.com', $result['patient_email']);
        $this->assertEquals('123 Main St, Apt 4B', $result['patient_address']);
        $this->assertEquals('Anytown', $result['patient_city']);
        $this->assertEquals('NY', $result['patient_state']);
        $this->assertEquals('12345', $result['patient_zip']);
    }

    /** @test */
    public function it_extracts_practitioner_data_correctly()
    {
        // Arrange
        $mockPractitioner = [
            'id' => 'practitioner-456',
            'name' => [
                ['given' => ['Dr. Jane'], 'family' => 'Smith', 'prefix' => ['Dr.']]
            ],
            'identifier' => [
                ['system' => 'NPI', 'value' => '1234567890'],
                ['system' => 'DEA', 'value' => 'AS1234567']
            ],
            'telecom' => [
                ['system' => 'phone', 'value' => '555-987-6543'],
                ['system' => 'email', 'value' => 'dr.smith@hospital.com']
            ]
        ];

        // Act
        $result = $this->mapper->extractPractitionerData($mockPractitioner);

        // Assert
        $this->assertEquals('Dr. Jane Smith', $result['provider_name']);
        $this->assertEquals('Jane', $result['provider_first_name']);
        $this->assertEquals('Smith', $result['provider_last_name']);
        $this->assertEquals('1234567890', $result['provider_npi']);
        $this->assertEquals('555-987-6543', $result['provider_phone']);
        $this->assertEquals('dr.smith@hospital.com', $result['provider_email']);
        $this->assertEquals('AS1234567', $result['provider_dea']);
    }

    /** @test */
    public function it_extracts_organization_data_correctly()
    {
        // Arrange
        $mockOrganization = [
            'id' => 'organization-789',
            'name' => 'General Hospital',
            'identifier' => [
                ['system' => 'NPI', 'value' => '9876543210'],
                ['system' => 'TAX', 'value' => '12-3456789']
            ],
            'address' => [
                [
                    'line' => ['456 Hospital Blvd'],
                    'city' => 'Anytown',
                    'state' => 'NY',
                    'postalCode' => '12345'
                ]
            ],
            'telecom' => [
                ['system' => 'phone', 'value' => '555-555-5555'],
                ['system' => 'fax', 'value' => '555-555-5556']
            ]
        ];

        // Act
        $result = $this->mapper->extractOrganizationData($mockOrganization);

        // Assert
        $this->assertEquals('General Hospital', $result['facility_name']);
        $this->assertEquals('456 Hospital Blvd', $result['facility_address']);
        $this->assertEquals('Anytown', $result['facility_city']);
        $this->assertEquals('NY', $result['facility_state']);
        $this->assertEquals('12345', $result['facility_zip']);
        $this->assertEquals('555-555-5555', $result['facility_phone']);
        $this->assertEquals('555-555-5556', $result['facility_fax']);
        $this->assertEquals('9876543210', $result['facility_npi']);
        $this->assertEquals('12-3456789', $result['facility_tax_id']);
    }

    /** @test */
    public function it_extracts_coverage_data_correctly()
    {
        // Arrange
        $mockCoverage = [
            'id' => 'coverage-101',
            'subscriberId' => 'MEMBER123',
            'payor' => [
                ['display' => 'Blue Cross Blue Shield']
            ],
            'type' => [
                'coding' => [
                    ['code' => 'primary', 'display' => 'Primary Coverage']
                ]
            ],
            'period' => [
                'start' => '2023-01-01',
                'end' => '2023-12-31'
            ]
        ];

        // Act
        $result = $this->mapper->extractCoverageData($mockCoverage);

        // Assert
        $this->assertEquals('Blue Cross Blue Shield', $result['primary_insurance_name']);
        $this->assertEquals('MEMBER123', $result['primary_member_id']);
        $this->assertEquals('primary', $result['primary_plan_type']);
        $this->assertEquals('2023-01-01', $result['coverage_start_date']);
        $this->assertEquals('2023-12-31', $result['coverage_end_date']);
    }

    /** @test */
    public function it_extracts_condition_data_correctly()
    {
        // Arrange
        $mockCondition = [
            'id' => 'condition-112',
            'code' => [
                'coding' => [
                    ['system' => 'ICD-10', 'code' => 'E11.621', 'display' => 'Type 2 diabetes with foot ulcer']
                ]
            ],
            'bodySite' => [
                [
                    'coding' => [
                        ['code' => 'foot', 'display' => 'Foot']
                    ]
                ]
            ],
            'severity' => [
                'coding' => [
                    ['code' => 'moderate', 'display' => 'Moderate']
                ]
            ],
            'onsetDateTime' => '2023-01-15'
        ];

        // Act
        $result = $this->mapper->extractConditionData($mockCondition);

        // Assert
        $this->assertEquals('E11.621', $result['diagnosis_code']);
        $this->assertEquals('Type 2 diabetes with foot ulcer', $result['diagnosis_description']);
        $this->assertEquals('foot', $result['wound_location']);
        $this->assertEquals('moderate', $result['wound_severity']);
        $this->assertEquals('2023-01-15', $result['wound_onset_date']);
    }

    /** @test */
    public function it_handles_empty_fhir_resources()
    {
        // Arrange
        $fhirIds = [];
        $metadata = [];

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_creates_default_values_for_missing_data()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
        ];

        $metadata = [
            'episode_id' => 'episode-999'
        ];

        $mockPatient = [
            'id' => 'patient-123',
            'name' => [
                ['given' => ['John'], 'family' => 'Doe']
            ]
            // Missing birthDate, gender, etc.
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->logger->shouldReceive('info')->atLeast()->once();

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('John Doe', $result['patient_name']);
        $this->assertEquals('', $result['patient_dob']);
        $this->assertEquals('unknown', $result['patient_gender']);
        $this->assertEquals('', $result['patient_phone']);
        $this->assertEquals('', $result['patient_email']);
    }

    /** @test */
    public function it_applies_field_aliases_correctly()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
        ];

        $metadata = [
            'episode_id' => 'episode-999',
            'manufacturer' => 'MedLife Solutions'
        ];

        $mockPatient = [
            'id' => 'patient-123',
            'name' => [
                ['given' => ['John'], 'family' => 'Doe']
            ],
            'birthDate' => '1980-01-01',
            'telecom' => [
                ['system' => 'phone', 'value' => '555-123-4567'],
                ['system' => 'email', 'value' => 'john.doe@example.com']
            ]
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->logger->shouldReceive('info')->atLeast()->once();

        // Act
        $result = $this->mapper->extractDataFromFhir($fhirIds, $metadata);

        // Assert
        $this->assertIsArray($result);
        
        // Check that aliases are applied (for MedLife Solutions)
        $this->assertArrayHasKey('name', $result); // Alias for patient_name
        $this->assertArrayHasKey('email', $result); // Alias for patient_email
        $this->assertArrayHasKey('phone', $result); // Alias for patient_phone
        
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('555-123-4567', $result['phone']);
    }
} 