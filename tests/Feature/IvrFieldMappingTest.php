<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\IvrFieldMappingService;
use App\Services\DocusealFieldFormatterService;
use App\Services\FhirService;
use App\Models\Order\ProductRequest;
use App\Models\Order\Product;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class IvrFieldMappingTest extends TestCase
{
    use RefreshDatabase;

    protected IvrFieldMappingService $fieldMappingService;
    protected array $mockPatientData;
    protected ProductRequest $productRequest;
    protected User $provider;
    protected Facility $facility;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize services
        $this->fieldMappingService = new IvrFieldMappingService(
            $this->createMock(FhirService::class),
            new DocusealFieldFormatterService()
        );

        // Create test data
        $this->provider = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'npi_number' => '1234567890'
        ]);

        $this->facility = Facility::create([
            'name' => 'Test Facility',
            'address' => '123 Main St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip_code' => '12345',
            'phone' => '555-123-4567'
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'q_code' => 'Q1234',
            'manufacturer' => 'ACZ Distribution',
            'is_active' => true
        ]);

        // Mock patient data structure from FHIR
        $this->mockPatientData = [
            'given' => ['Jane'],
            'family' => 'Doe',
            'birthDate' => '1980-05-15',
            'gender' => 'female',
            'address' => [[
                'line' => ['456 Patient St', 'Apt 2'],
                'city' => 'Patient City',
                'state' => 'PC',
                'postalCode' => '54321'
            ]],
            'telecom' => [[
                'system' => 'phone',
                'value' => '555-987-6543'
            ]]
        ];

        $this->productRequest = ProductRequest::create([
            'request_number' => 'REQ-20241217-001',
            'provider_id' => $this->provider->id,
            'patient_fhir_id' => 'patient-123',
            'patient_display_id' => 'JADO456',
            'facility_id' => $this->facility->id,
            'payer_name_submitted' => 'Blue Cross Blue Shield',
            'payer_id' => 'BCBS987654',
            'insurance_type' => 'PPO',
            'expected_service_date' => Carbon::now()->addDays(5),
            'wound_type' => 'surgical',
            'place_of_service' => '11',
            'clinical_summary' => json_encode([
                'woundDetails' => [
                    'location' => 'lower_extremity',
                    'size' => '3.0cm x 4.0cm',
                    'duration' => '4 weeks'
                ]
            ]),
            'failed_conservative_treatment' => true,
            'information_accurate' => true,
            'medical_necessity_established' => true,
            'maintain_documentation' => true,
            'authorize_prior_auth' => false,
            'order_status' => 'pending_ivr'
        ]);

        $this->productRequest->products()->attach($this->product->id, [
            'quantity' => 3,
            'size' => '4x4cm',
            'unit_price' => 150.00,
            'total_price' => 450.00
        ]);
    }

    /**
     * Test standard Docuseal field mapping
     */
    public function test_maps_standard_docuseal_fields_correctly()
    {
        $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
            $this->productRequest,
            'ACZ_Distribution',
            $this->mockPatientData
        );

        // Patient Information Fields
        $this->assertEquals('Jane', $mappedFields['patient_first_name']);
        $this->assertEquals('Doe', $mappedFields['patient_last_name']);
        $this->assertEquals('1980-05-15', $mappedFields['patient_dob']);
        $this->assertEquals('JADO456', $mappedFields['patient_display_id']);
        $this->assertEquals('female', $mappedFields['patient_gender']);
        $this->assertEquals('456 Patient St', $mappedFields['patient_address_line1']);
        $this->assertEquals('Apt 2', $mappedFields['patient_address_line2']);
        $this->assertEquals('Patient City', $mappedFields['patient_city']);
        $this->assertEquals('PC', $mappedFields['patient_state']);
        $this->assertEquals('54321', $mappedFields['patient_zip']);
        $this->assertEquals('555-987-6543', $mappedFields['patient_phone']);

        // Insurance Information Fields
        $this->assertEquals('Blue Cross Blue Shield', $mappedFields['payer_name']);
        $this->assertEquals('BCBS987654', $mappedFields['payer_id']);
        $this->assertEquals('PPO', $mappedFields['insurance_type']);

        // Product Information Fields
        $this->assertEquals('Test Product', $mappedFields['product_name']);
        $this->assertEquals('Q1234', $mappedFields['product_code']);
        $this->assertEquals('ACZ Distribution', $mappedFields['manufacturer']);
        $this->assertEquals('4x4cm', $mappedFields['size']);
        $this->assertEquals(3, $mappedFields['quantity']);

        // Service Information Fields
        $this->assertEquals(Carbon::parse($this->productRequest->expected_service_date)->format('Y-m-d'), $mappedFields['expected_service_date']);
        $this->assertEquals('surgical', $mappedFields['wound_type']);
        $this->assertEquals('Physician Office', $mappedFields['place_of_service']);

        // Provider Information Fields
        $this->assertEquals('John Smith', $mappedFields['provider_name']);
        $this->assertEquals('1234567890', $mappedFields['provider_npi']);

        // Facility Information
        $this->assertEquals('Test Facility', $mappedFields['facility_name']);
        $this->assertEquals('123 Main St', $mappedFields['facility_address']);

        // Clinical Attestations
        $this->assertEquals('Yes', $mappedFields['failed_conservative_treatment']);
        $this->assertEquals('Yes', $mappedFields['information_accurate']);
        $this->assertEquals('Yes', $mappedFields['medical_necessity_established']);
        $this->assertEquals('Yes', $mappedFields['maintain_documentation']);
        $this->assertEquals('No', $mappedFields['authorize_prior_auth']);

        // Auto-Generated Fields
        $this->assertEquals(Carbon::now()->format('m/d/Y'), $mappedFields['todays_date']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2} [AP]M$/', $mappedFields['current_time']);

        // Clinical Summary Fields
        $this->assertEquals('lower_extremity', $mappedFields['wound_location']);
        $this->assertEquals('3.0cm x 4.0cm', $mappedFields['wound_size']);
        $this->assertEquals('4 weeks', $mappedFields['wound_duration']);
    }

    /**
     * Test manufacturer-specific field mappings
     */
    public function test_applies_manufacturer_specific_fields()
    {
        $testCases = [
            'ACZ_Distribution' => [
                'fields' => ['physician_attestation', 'not_used_previously'],
                'expected' => ['Yes', 'Yes']
            ],
            'Advanced_Health' => [
                'fields' => ['multiple_products', 'previous_use'],
                'expected' => ['No', 'No']
            ],
            'Centurion' => [
                'fields' => ['stat_order', 'previous_amnion_use'],
                'expected' => ['No', 'No']
            ],
            'BioWerX' => [
                'fields' => ['first_application', 'reapplication'],
                'expected' => ['Yes', 'No']
            ]
        ];

        foreach ($testCases as $manufacturer => $test) {
            $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
                $this->productRequest,
                $manufacturer,
                $this->mockPatientData
            );

            foreach ($test['fields'] as $index => $field) {
                $this->assertArrayHasKey($field, $mappedFields, "Field {$field} missing for {$manufacturer}");
                $this->assertEquals($test['expected'][$index], $mappedFields[$field], 
                    "Field {$field} has incorrect value for {$manufacturer}");
            }
        }
    }

    /**
     * Test field type definitions
     */
    public function test_provides_correct_field_types()
    {
        $fieldTypes = $this->fieldMappingService->getFieldTypes('ACZ_Distribution');

        // Common fields
        $this->assertEquals('text', $fieldTypes['patient_first_name']);
        $this->assertEquals('text', $fieldTypes['patient_last_name']);
        $this->assertEquals('date', $fieldTypes['patient_dob']);
        $this->assertEquals('phone', $fieldTypes['patient_phone']);
        $this->assertEquals('checkbox', $fieldTypes['failed_conservative_treatment']);
        $this->assertEquals('signature', $fieldTypes['provider_signature']);
        $this->assertEquals('datenow', $fieldTypes['todays_date']);

        // Manufacturer-specific fields
        $this->assertEquals('checkbox', $fieldTypes['physician_attestation']);
        $this->assertEquals('checkbox', $fieldTypes['not_used_previously']);
    }

    /**
     * Test required field validation
     */
    public function test_validates_required_fields()
    {
        // Create request with missing required fields
        $incompleteRequest = ProductRequest::create([
            'request_number' => 'REQ-INCOMPLETE',
            'provider_id' => $this->provider->id,
            'facility_id' => $this->facility->id,
            'order_status' => 'pending_ivr',
            // Missing: payer_name_submitted, patient_display_id
        ]);

        $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
            $incompleteRequest,
            'ACZ_Distribution',
            []
        );

        $errors = $this->fieldMappingService->validateMapping('ACZ_Distribution', $mappedFields);

        $this->assertContains('Missing required field: payer_name', $errors);
        $this->assertContains('Missing required field: patient_display_id', $errors);
    }

    /**
     * Test handling of empty patient data
     */
    public function test_handles_empty_patient_data_gracefully()
    {
        $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
            $this->productRequest,
            'ACZ_Distribution',
            [] // Empty patient data
        );

        // Should still map non-patient fields
        $this->assertEquals('Blue Cross Blue Shield', $mappedFields['payer_name']);
        $this->assertEquals('Test Product', $mappedFields['product_name']);
        $this->assertEquals('John Smith', $mappedFields['provider_name']);

        // Patient fields should be empty but present
        $this->assertArrayHasKey('patient_first_name', $mappedFields);
        $this->assertEquals('', $mappedFields['patient_first_name']);
    }

    /**
     * Test place of service mapping
     */
    public function test_maps_place_of_service_codes()
    {
        $testCases = [
            '11' => 'Physician Office',
            '22' => 'Hospital Outpatient',
            '24' => 'Ambulatory Surgical Center',
            '12' => 'Home',
            '31' => 'Skilled Nursing Facility',
            '99' => '99' // Unknown code returns original
        ];

        foreach ($testCases as $code => $expected) {
            $this->productRequest->place_of_service = $code;
            $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
                $this->productRequest,
                'ACZ_Distribution',
                $this->mockPatientData
            );

            $this->assertEquals($expected, $mappedFields['place_of_service']);
        }
    }

    /**
     * Test date formatting consistency
     */
    public function test_formats_dates_consistently()
    {
        $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
            $this->productRequest,
            'ACZ_Distribution',
            $this->mockPatientData
        );

        // Patient DOB should be Y-m-d format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $mappedFields['patient_dob']);
        
        // Service date should be Y-m-d format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $mappedFields['expected_service_date']);
        
        // Today's date should be m/d/Y format
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $mappedFields['todays_date']);
    }

    /**
     * Test multiple product sizes handling
     */
    public function test_handles_multiple_product_sizes()
    {
        // Add more product sizes
        $this->productRequest->products()->attach($this->product->id, [
            'quantity' => 2,
            'size' => '2x2cm',
            'unit_price' => 75.00,
            'total_price' => 150.00
        ]);

        $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
            $this->productRequest,
            'ACZ_Distribution',
            $this->mockPatientData
        );

        // Should get the first product's size
        $this->assertEquals('4x4cm', $mappedFields['size']);
        
        // Product name should remain the same
        $this->assertEquals('Test Product', $mappedFields['product_name']);
    }

    /**
     * Test Docuseal configuration retrieval
     */
    public function test_retrieves_docuseal_configuration()
    {
        // This would need actual config data
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown manufacturer: Invalid_Manufacturer');
        
        $this->fieldMappingService->getDocusealConfig('Invalid_Manufacturer');
    }

    /**
     * Test clinical summary extraction
     */
    public function test_extracts_clinical_summary_data()
    {
        $this->productRequest->clinical_summary = json_encode([
            'woundDetails' => [
                'location' => 'right_foot_dorsal',
                'size' => '5.0cm x 6.0cm x 0.5cm',
                'duration' => '12 weeks',
                'previousTreatments' => ['compression', 'debridement']
            ]
        ]);
        $this->productRequest->save();

        $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
            $this->productRequest,
            'ACZ_Distribution',
            $this->mockPatientData
        );

        $this->assertEquals('right_foot_dorsal', $mappedFields['wound_location']);
        $this->assertEquals('5.0cm x 6.0cm x 0.5cm', $mappedFields['wound_size']);
        $this->assertEquals('12 weeks', $mappedFields['wound_duration']);
    }
}