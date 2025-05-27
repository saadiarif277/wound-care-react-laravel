<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Facility;
use App\Models\MedicareMacValidation;
use App\Services\MedicareMacValidationService;
use App\Services\CmsCoverageApiService;
use App\Services\ValidationBuilderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class MacZipValidationTest extends TestCase
{
    use RefreshDatabase;

    private $macValidationService;
    private $mockCmsService;
    private $mockValidationEngine;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the dependencies
        $this->mockValidationEngine = Mockery::mock(ValidationBuilderEngine::class);
        $this->mockCmsService = Mockery::mock(CmsCoverageApiService::class);

        $this->macValidationService = new MedicareMacValidationService(
            $this->mockValidationEngine,
            $this->mockCmsService
        );
    }

    /** @test */
    public function it_uses_patient_zip_code_for_mac_determination_instead_of_facility()
    {
        // Create a patient in California
        $patient = Patient::factory()->create([
            'name' => 'John Doe',
            'date_of_birth' => '1950-01-01',
            'state' => 'CA',
            'zip_code' => '90210'
        ]);

        // Create a facility in Texas (different state)
        $facility = Facility::factory()->create([
            'name' => 'Texas Wound Center',
            'state' => 'TX',
            'zip_code' => '75001',
            'facility_type' => 'clinic'
        ]);

        // Create an order
        $order = Order::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'date_of_service' => now()->addDays(7)
        ]);

        // Mock CMS service to return appropriate data
        $this->mockCmsService->shouldReceive('getLCDsBySpecialty')
            ->andReturn([]);
        $this->mockCmsService->shouldReceive('getNCDsBySpecialty')
            ->andReturn([]);

        // Run validation
        $validation = $this->macValidationService->validateOrder($order, 'wound_care_only');

        // Assertions
        $this->assertNotNull($validation);
        $this->assertEquals('90210', $validation->patient_zip_code);
        $this->assertEquals('CA', $validation->mac_region);

        // Should use California MAC (Noridian Healthcare Solutions - JF) based on patient address
        $this->assertEquals('Noridian Healthcare Solutions', $validation->mac_contractor);
        $this->assertEquals('JF', $validation->mac_jurisdiction);
        $this->assertEquals('patient_address', $validation->addressing_method);
    }

    /** @test */
    public function it_handles_special_zip_code_jurisdictions()
    {
        // Create a patient in a special ZIP code area (Greenwich, CT)
        $patient = Patient::factory()->create([
            'name' => 'Jane Smith',
            'date_of_birth' => '1945-05-15',
            'state' => 'CT',
            'zip_code' => '06830' // Greenwich, CT - special jurisdiction
        ]);

        $facility = Facility::factory()->create([
            'state' => 'NY',
            'zip_code' => '10001'
        ]);

        $order = Order::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id
        ]);

        // Mock CMS service
        $this->mockCmsService->shouldReceive('getLCDsBySpecialty')->andReturn([]);
        $this->mockCmsService->shouldReceive('getNCDsBySpecialty')->andReturn([]);

        $validation = $this->macValidationService->validateOrder($order, 'wound_care_only');

        // Should use the special ZIP-based jurisdiction
        $this->assertEquals('06830', $validation->patient_zip_code);
        $this->assertEquals('zip_code_specific', $validation->addressing_method);
        $this->assertEquals('National Government Services', $validation->mac_contractor);
        $this->assertEquals('J6', $validation->mac_jurisdiction);
    }

    /** @test */
    public function it_falls_back_to_facility_when_patient_address_missing()
    {
        // Create a patient without state/ZIP
        $patient = Patient::factory()->create([
            'name' => 'Incomplete Patient',
            'date_of_birth' => '1960-01-01',
            'state' => null,
            'zip_code' => null
        ]);

        $facility = Facility::factory()->create([
            'state' => 'FL',
            'zip_code' => '33101'
        ]);

        $order = Order::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id
        ]);

        // Mock CMS service
        $this->mockCmsService->shouldReceive('getLCDsBySpecialty')->andReturn([]);
        $this->mockCmsService->shouldReceive('getNCDsBySpecialty')->andReturn([]);

        $validation = $this->macValidationService->validateOrder($order, 'wound_care_only');

        // Should fall back to facility state
        $this->assertNull($validation->patient_zip_code);
        $this->assertEquals('FL', $validation->mac_region);
        $this->assertEquals('First Coast Service Options', $validation->mac_contractor);
        $this->assertEquals('JL', $validation->mac_jurisdiction);
    }

    /** @test */
    public function it_stores_addressing_method_correctly()
    {
        $patient = Patient::factory()->create([
            'state' => 'NY',
            'zip_code' => '10001'
        ]);

        $facility = Facility::factory()->create([
            'state' => 'NJ'
        ]);

        $order = Order::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id
        ]);

        // Mock CMS service
        $this->mockCmsService->shouldReceive('getLCDsBySpecialty')->andReturn([]);
        $this->mockCmsService->shouldReceive('getNCDsBySpecialty')->andReturn([]);

        $validation = $this->macValidationService->validateOrder($order, 'wound_care_only');

        // Verify the addressing method is tracked
        $this->assertNotNull($validation->addressing_method);
        $this->assertContains($validation->addressing_method, [
            'patient_address',
            'zip_code_specific',
            'state_based',
            'state_based_no_zip'
        ]);
    }

    /** @test */
    public function it_handles_kansas_city_metro_area_properly()
    {
        // Test Kansas City, MO ZIP that crosses state boundaries
        $patient = Patient::factory()->create([
            'state' => 'MO',
            'zip_code' => '64108'
        ]);

        $facility = Facility::factory()->create([
            'state' => 'KS'
        ]);

        $order = Order::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id
        ]);

        // Mock CMS service
        $this->mockCmsService->shouldReceive('getLCDsBySpecialty')->andReturn([]);
        $this->mockCmsService->shouldReceive('getNCDsBySpecialty')->andReturn([]);

        $validation = $this->macValidationService->validateOrder($order, 'wound_care_only');

        // Should use the ZIP-specific jurisdiction for Kansas City metro
        $this->assertEquals('64108', $validation->patient_zip_code);
        $this->assertEquals('zip_code_specific', $validation->addressing_method);
        $this->assertEquals('WPS Health Solutions', $validation->mac_contractor);
        $this->assertEquals('JM', $validation->mac_jurisdiction);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
