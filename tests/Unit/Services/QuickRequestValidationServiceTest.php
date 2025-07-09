<?php

namespace Tests\Unit\Services;

use App\Services\QuickRequestValidationService;
use App\Services\FhirService;
use App\Logging\PhiSafeLogger;
use App\Models\PatientManufacturerIVREpisode;
use Tests\TestCase;
use Mockery;

class QuickRequestValidationServiceTest extends TestCase
{
    protected QuickRequestValidationService $validator;
    protected $fhirService;
    protected $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fhirService = Mockery::mock(FhirService::class);
        $this->logger = Mockery::mock(PhiSafeLogger::class);
        
        $this->validator = new QuickRequestValidationService($this->fhirService, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_validates_fhir_compliance_successfully()
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
            'episode_id' => 'episode-999'
        ];

        $mockPatient = [
            'name' => [['given' => ['John'], 'family' => 'Doe']],
            'birthDate' => '1980-01-01',
            'gender' => 'male',
            'identifier' => [['system' => 'MRN', 'value' => '123456']]
        ];

        $mockPractitioner = [
            'name' => [['given' => ['Dr. Jane'], 'family' => 'Smith']],
            'identifier' => [['system' => 'NPI', 'value' => '1234567890']]
        ];

        $mockOrganization = [
            'name' => 'General Hospital',
            'identifier' => [['system' => 'NPI', 'value' => '9876543210']]
        ];

        $mockCoverage = [
            'beneficiary' => ['reference' => 'Patient/patient-123'],
            'payor' => [['display' => 'Blue Cross']],
            'subscriberId' => 'MEMBER123'
        ];

        $mockCondition = [
            'code' => [
                'coding' => [
                    ['system' => 'ICD-10', 'code' => 'E11.621', 'display' => 'Type 2 diabetes with foot ulcer']
                ]
            ],
            'subject' => ['reference' => 'Patient/patient-123']
        ];

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

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateFhirCompliance($fhirIds, $metadata);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertGreaterThan(80, $result['compliance_score']);
    }

    /** @test */
    public function it_identifies_missing_required_fhir_resources()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
            // Missing other required resources
        ];

        $metadata = [
            'episode_id' => 'episode-999'
        ];

        $mockPatient = [
            'name' => [['given' => ['John'], 'family' => 'Doe']],
            'birthDate' => '1980-01-01',
            'gender' => 'male',
            'identifier' => [['system' => 'MRN', 'value' => '123456']]
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateFhirCompliance($fhirIds, $metadata);

        // Assert
        $this->assertTrue($result['valid']); // Still valid because warnings don't make it invalid
        $this->assertEmpty($result['errors']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertArrayHasKey('practitioner', $result['warnings']);
        $this->assertArrayHasKey('organization', $result['warnings']);
        $this->assertArrayHasKey('coverage', $result['warnings']);
        $this->assertArrayHasKey('condition', $result['warnings']);
    }

    /** @test */
    public function it_validates_patient_resource_correctly()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
        ];

        $metadata = [];

        $mockPatient = [
            // Missing required fields
            'id' => 'patient-123'
        ];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andReturn($mockPatient);

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateFhirCompliance($fhirIds, $metadata);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('patient', $result['errors']);
        $this->assertContains('Patient name is required', $result['errors']['patient']);
        $this->assertContains('Patient birth date is required', $result['errors']['patient']);
        $this->assertContains('Patient gender is required', $result['errors']['patient']);
        $this->assertContains('Patient identifier is required', $result['errors']['patient']);
    }

    /** @test */
    public function it_validates_ivr_form_completeness_for_biowound_solutions()
    {
        // Arrange
        $data = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => '555-123-4567',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'BioWound Matrix',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '11',
            // BioWound Solutions specific fields
            'new_request' => 'yes',
            'patient_snf_yes' => 'no',
            'patient_snf_no' => 'yes',
            'pos_11' => 'yes',
            'pos_21' => 'no',
            'pos_24' => 'no',
            'pos_22' => 'no',
            'pos_32' => 'no',
            'wound_dfu' => 'yes',
            'wound_vlu' => 'no',
            'wound_chronic_ulcer' => 'no',
            'q4161' => 'yes',
            'q4205' => 'no',
            'q4290' => 'no',
            'q4238' => 'no',
            'q4239' => 'no',
            'prior_auth_yes' => 'no',
            'prior_auth_no' => 'yes'
        ];

        $manufacturerName = 'BioWound Solutions';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($data, $manufacturerName);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('BioWound Solutions', $result['manufacturer']);
        $this->assertEquals(100, $result['completeness_score']);
        $this->assertGreaterThan(40, $result['total_fields']);
        $this->assertGreaterThan(40, $result['completed_fields']);
    }

    /** @test */
    public function it_identifies_missing_manufacturer_specific_fields()
    {
        // Arrange
        $data = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => '555-123-4567',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'BioWound Matrix',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '11'
            // Missing BioWound Solutions specific fields
        ];

        $manufacturerName = 'BioWound Solutions';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($data, $manufacturerName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('new_request', $result['errors']);
        $this->assertArrayHasKey('patient_snf_yes', $result['errors']);
        $this->assertArrayHasKey('wound_dfu', $result['errors']);
        $this->assertArrayHasKey('q4161', $result['errors']);
        $this->assertArrayHasKey('prior_auth_yes', $result['errors']);
    }

    /** @test */
    public function it_validates_data_formats_correctly()
    {
        // Arrange
        $data = [
            'patient_name' => 'John Doe',
            'patient_dob' => 'invalid-date',
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => 'invalid-phone',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '123', // Invalid NPI
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'BioWound Matrix',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '11',
            'patient_email' => 'invalid-email'
        ];

        $manufacturerName = 'Standard';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($data, $manufacturerName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('patient_dob', $result['errors']);
        $this->assertArrayHasKey('patient_phone', $result['errors']);
        $this->assertArrayHasKey('provider_npi', $result['errors']);
        $this->assertArrayHasKey('patient_email', $result['errors']);
    }

    /** @test */
    public function it_validates_business_rules()
    {
        // Arrange
        $data = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1800-01-01', // Age > 120 years
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => '555-123-4567',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'BioWound Matrix',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '99' // Invalid place of service
        ];

        $manufacturerName = 'Standard';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($data, $manufacturerName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('patient_age', $result['errors']);
        $this->assertArrayHasKey('place_of_service', $result['errors']);
    }

    /** @test */
    public function it_validates_episode_consistency()
    {
        // Arrange
        $episode = Mockery::mock(PatientManufacturerIVREpisode::class);
        $episode->id = 'episode-123';
        $episode->metadata = [
            'patient_data' => [
                'display_id' => 'PAT001',
                'first_name' => 'John',
                'last_name' => 'Doe'
            ],
            'provider_data' => [
                'npi' => '1234567890',
                'name' => 'Dr. Jane Smith'
            ],
            'clinical_data' => [
                'wound_length' => '2.5',
                'wound_width' => '1.8',
                'wound_type' => 'diabetic_ulcer'
            ],
            'insurance_data' => [
                'primary_member_id' => 'MEMBER123456',
                'primary_insurance_name' => 'Blue Cross Blue Shield'
            ]
        ];

        $episode->patient_display_id = 'PAT001';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateEpisodeConsistency($episode);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertGreaterThan(80, $result['consistency_score']);
    }

    /** @test */
    public function it_detects_patient_data_inconsistencies()
    {
        // Arrange
        $episode = Mockery::mock(PatientManufacturerIVREpisode::class);
        $episode->id = 'episode-123';
        $episode->metadata = [
            'patient_data' => [
                'display_id' => 'PAT001',
                'first_name' => 'John',
                'last_name' => 'Doe'
            ]
        ];

        $episode->patient_display_id = 'PAT002'; // Different from metadata

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateEpisodeConsistency($episode);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('patient_consistency', $result['errors']);
        $this->assertContains('Patient display ID mismatch between form data and episode', $result['errors']['patient_consistency']);
    }

    /** @test */
    public function it_handles_validation_exceptions_gracefully()
    {
        // Arrange
        $fhirIds = [
            'patient_id' => 'patient-123'
        ];

        $metadata = [];

        $this->fhirService->shouldReceive('read')
            ->with('Patient', 'patient-123')
            ->andThrow(new \Exception('FHIR service error'));

        $this->logger->shouldReceive('error')->once();

        // Act
        $result = $this->validator->validateFhirCompliance($fhirIds, $metadata);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('validation', $result['errors']);
        $this->assertEquals(0, $result['compliance_score']);
    }

    /** @test */
    public function it_calculates_completeness_score_correctly()
    {
        // Arrange
        $data = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => '555-123-4567',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'BioWound Matrix',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '11'
            // Missing some required fields
        ];

        $manufacturerName = 'Standard';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($data, $manufacturerName);

        // Assert
        $this->assertIsInt($result['completeness_score']);
        $this->assertGreaterThan(0, $result['completeness_score']);
        $this->assertLessThan(100, $result['completeness_score']);
    }

    /** @test */
    public function it_identifies_data_quality_issues()
    {
        // Arrange
        $data = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => '555-123-4567',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'provider_phone' => '555-123-4567', // Same as patient phone
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'BioWound Matrix',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '11'
            // Missing patient_email, provider_email, facility_fax, secondary_insurance_name
        ];

        $manufacturerName = 'Standard';

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($data, $manufacturerName);

        // Assert
        $this->assertNotEmpty($result['warnings']);
        $this->assertContains('Patient and provider have the same phone number', $result['warnings']);
        $this->assertContains("Optional field 'patient_email' is missing but recommended", $result['warnings']);
        $this->assertContains("Optional field 'provider_email' is missing but recommended", $result['warnings']);
        $this->assertContains("Optional field 'facility_fax' is missing but recommended", $result['warnings']);
        $this->assertContains("Optional field 'secondary_insurance_name' is missing but recommended", $result['warnings']);
    }

    /** @test */
    public function it_validates_different_manufacturers_correctly()
    {
        // Test MedLife Solutions
        $medlifeData = [
            'patient_name' => 'John Doe',
            'patient_dob' => '1980-01-01',
            'patient_gender' => 'male',
            'patient_address' => '123 Main St',
            'patient_phone' => '555-123-4567',
            'provider_name' => 'Dr. Jane Smith',
            'provider_npi' => '1234567890',
            'facility_name' => 'General Hospital',
            'facility_address' => '456 Hospital Blvd',
            'primary_insurance_name' => 'Blue Cross',
            'primary_member_id' => 'MEMBER123',
            'wound_type' => 'diabetic_ulcer',
            'wound_location' => 'foot',
            'diagnosis_code' => 'E11.621',
            'product_name' => 'MedLife Product',
            'product_hcpcs' => 'Q4161',
            'date_of_service' => '2023-12-01',
            'place_of_service' => '11',
            // MedLife Solutions specific fields
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '555-123-4567',
            'distributor_company' => 'MedLife Distributors',
            'tax_id' => '12-3456789',
            'practice_ptan' => 'PTAN123',
            'practice_npi' => '1234567890',
            'icd10_code_1' => 'E11.621',
            'cpt_code_1' => '11042',
            'hcpcs_code_1' => 'Q4161',
            'patient_global_yes' => 'no',
            'patient_global_no' => 'yes'
        ];

        $this->logger->shouldReceive('info')->once();

        // Act
        $result = $this->validator->validateIvrFormCompleteness($medlifeData, 'MedLife Solutions');

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('MedLife Solutions', $result['manufacturer']);
    }
} 