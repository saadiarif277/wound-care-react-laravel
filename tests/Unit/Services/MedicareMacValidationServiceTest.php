<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MedicareMacValidationService;
use App\Services\ValidationBuilderEngine;
use App\Services\CmsCoverageApiService;
use App\Models\Order\Order;
use App\Models\Order\Product;
use App\Models\Order\OrderItem;
use App\Models\Insurance\MedicareMacValidation;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;

class MedicareMacValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MedicareMacValidationService $service;
    private $mockValidationEngine;
    private $mockCmsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockValidationEngine = Mockery::mock(ValidationBuilderEngine::class);
        $this->mockCmsService = Mockery::mock(CmsCoverageApiService::class);
        
        $this->service = new MedicareMacValidationService(
            $this->mockValidationEngine,
            $this->mockCmsService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful validation of an order.
     */
    public function test_validates_order_successfully()
    {
        // Arrange
        $facility = Facility::factory()->create([
            'state' => 'CA',
            'zip' => '90210'
        ]);
        
        $provider = User::factory()->create([
            'npi_number' => '1234567890'
        ]);
        
        $order = Order::factory()->create([
            'facility_id' => $facility->id,
            'provider_id' => $provider->id,
            'customer_zip' => '90210',
            'order_status' => 'submitted'
        ]);
        
        $product = Product::factory()->create([
            'hcpcs_code' => 'Q4151',
            'requires_prior_auth' => false
        ]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1
        ]);

        // Mock CMS service responses
        $this->mockCmsService
            ->shouldReceive('getMACJurisdiction')
            ->once()
            ->with('CA', '90210')
            ->andReturn([
                'contractor' => 'Noridian Healthcare Solutions',
                'jurisdiction' => 'J-E',
                'region' => 'West'
            ]);

        $this->mockCmsService
            ->shouldReceive('getLCDsBySpecialty')
            ->once()
            ->andReturn([
                [
                    'documentId' => 'L12345',
                    'documentTitle' => 'Wound Care LCDs',
                    'contractorName' => 'Noridian'
                ]
            ]);

        $this->mockCmsService
            ->shouldReceive('getNCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockValidationEngine
            ->shouldReceive('validateOrder')
            ->once()
            ->andReturn([
                'overall_status' => 'passed',
                'validations' => []
            ]);

        // Act
        $result = $this->service->validateOrder($order, 'wound_care_only');

        // Assert
        $this->assertInstanceOf(MedicareMacValidation::class, $result);
        $this->assertEquals('validated', $result->validation_status);
        $this->assertEquals('Noridian Healthcare Solutions', $result->mac_contractor);
        $this->assertEquals('J-E', $result->mac_jurisdiction);
        $this->assertEquals('wound_care_only', $result->validation_type);
    }

    /**
     * Test validation with failed MAC requirements.
     */
    public function test_validates_order_with_failed_requirements()
    {
        // Arrange
        $facility = Facility::factory()->create([
            'state' => 'CA',
            'zip' => '90210'
        ]);
        
        $order = Order::factory()->create([
            'facility_id' => $facility->id,
            'customer_zip' => '90210'
        ]);

        $this->mockCmsService
            ->shouldReceive('getMACJurisdiction')
            ->once()
            ->andReturn([
                'contractor' => 'Noridian Healthcare Solutions',
                'jurisdiction' => 'J-E'
            ]);

        $this->mockCmsService
            ->shouldReceive('getLCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockCmsService
            ->shouldReceive('getNCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockValidationEngine
            ->shouldReceive('validateOrder')
            ->once()
            ->andReturn([
                'overall_status' => 'failed',
                'validations' => [
                    [
                        'rule' => 'coverage_verification',
                        'status' => 'failed',
                        'message' => 'Product not covered by Medicare'
                    ]
                ]
            ]);

        // Act
        $result = $this->service->validateOrder($order);

        // Assert
        $this->assertEquals('failed', $result->validation_status);
        $this->assertNotEmpty($result->validation_errors);
        $this->assertArrayHasKey('coverage_issues', $result->validation_errors);
    }

    /**
     * Test validation with missing MAC contractor information.
     */
    public function test_handles_missing_mac_contractor()
    {
        // Arrange
        $facility = Facility::factory()->create([
            'state' => 'XX',
            'zip' => '99999'
        ]);
        
        $order = Order::factory()->create([
            'facility_id' => $facility->id,
            'customer_zip' => '99999'
        ]);

        $this->mockCmsService
            ->shouldReceive('getMACJurisdiction')
            ->once()
            ->andReturn(null);

        $this->mockCmsService
            ->shouldReceive('getLCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockCmsService
            ->shouldReceive('getNCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockValidationEngine
            ->shouldReceive('validateOrder')
            ->once()
            ->andReturn([
                'overall_status' => 'passed',
                'validations' => []
            ]);

        // Act
        $result = $this->service->validateOrder($order);

        // Assert
        $this->assertEquals('Unknown', $result->mac_contractor);
        $this->assertEquals('Unknown', $result->mac_jurisdiction);
    }

    /**
     * Test validation with prior authorization requirements.
     */
    public function test_validates_prior_authorization_requirements()
    {
        // Arrange
        $facility = Facility::factory()->create([
            'state' => 'CA',
            'zip' => '90210'
        ]);
        
        $order = Order::factory()->create([
            'facility_id' => $facility->id,
            'pre_auth_status' => 'not_started'
        ]);
        
        $product = Product::factory()->create([
            'hcpcs_code' => 'Q4152',
            'requires_prior_auth' => true
        ]);
        
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id
        ]);

        $this->mockCmsService
            ->shouldReceive('getMACJurisdiction')
            ->once()
            ->andReturn([
                'contractor' => 'Noridian Healthcare Solutions',
                'jurisdiction' => 'J-E'
            ]);

        $this->mockCmsService
            ->shouldReceive('getLCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockCmsService
            ->shouldReceive('getNCDsBySpecialty')
            ->once()
            ->andReturn([]);

        $this->mockValidationEngine
            ->shouldReceive('validateOrder')
            ->once()
            ->andReturn([
                'overall_status' => 'passed_with_warnings',
                'validations' => []
            ]);

        // Act
        $result = $this->service->validateOrder($order);

        // Assert
        $this->assertTrue($result->requires_prior_auth);
        $this->assertEquals('requires_review', $result->validation_status);
        $this->assertArrayHasKey('prior_auth_issues', $result->validation_errors);
    }

    /**
     * Test validation using patient zip code instead of facility.
     */
    public function test_validates_using_patient_zip_code()
    {
        // Arrange
        $orderData = [
            'order_id' => 123,
            'service_codes' => ['Q4151', '97597'],
            'wound_type' => 'diabetic_foot_ulcer'
        ];
        
        $patientData = [
            'zip_code' => '33101',
            'state' => 'FL'
        ];
        
        $facilityData = [
            'zip_code' => '33139',
            'state' => 'FL'
        ];

        $this->mockCmsService
            ->shouldReceive('getMACJurisdiction')
            ->once()
            ->with('FL', '33101') // Should use patient zip
            ->andReturn([
                'contractor' => 'First Coast Service Options',
                'jurisdiction' => 'J-N',
                'addressing_method' => 'patient_zip'
            ]);

        $this->mockCmsService
            ->shouldReceive('checkCoverageWithAddressing')
            ->once()
            ->andReturn([
                'coverage_results' => [
                    [
                        'procedure_code' => 'Q4151',
                        'covered' => true
                    ],
                    [
                        'procedure_code' => '97597',
                        'covered' => true
                    ]
                ]
            ]);

        // Act
        $result = $this->service->validateOrderWithCorrectAddressing(
            $orderData,
            $patientData,
            $facilityData
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('First Coast Service Options', $result['mac_contractor']);
        $this->assertEquals('patient_zip', $result['addressing_method']);
    }

    /**
     * Test daily monitoring functionality.
     */
    public function test_runs_daily_monitoring()
    {
        // Arrange
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();
        
        $validation1 = MedicareMacValidation::create([
            'validation_id' => 'VAL001',
            'order_id' => $order1->id,
            'facility_id' => $order1->facility_id,
            'validation_type' => 'wound_care_only',
            'validation_status' => 'validated',
            'mac_contractor' => 'Noridian',
            'mac_jurisdiction' => 'J-E',
            'compliance_score' => 85,
            'last_monitored_at' => now()->subDays(2)
        ]);
        
        $validation2 = MedicareMacValidation::create([
            'validation_id' => 'VAL002',
            'order_id' => $order2->id,
            'facility_id' => $order2->facility_id,
            'validation_type' => 'wound_care_only',
            'validation_status' => 'validated',
            'mac_contractor' => 'Noridian',
            'mac_jurisdiction' => 'J-E',
            'compliance_score' => 90,
            'last_monitored_at' => null
        ]);

        $this->mockCmsService
            ->shouldReceive('getMACJurisdiction')
            ->times(2)
            ->andReturn([
                'contractor' => 'Noridian Healthcare Solutions',
                'jurisdiction' => 'J-E'
            ]);

        $this->mockCmsService
            ->shouldReceive('getLCDsBySpecialty')
            ->times(2)
            ->andReturn([]);

        $this->mockCmsService
            ->shouldReceive('getNCDsBySpecialty')
            ->times(2)
            ->andReturn([]);

        $this->mockValidationEngine
            ->shouldReceive('validateOrder')
            ->times(2)
            ->andReturn([
                'overall_status' => 'passed',
                'validations' => []
            ]);

        // Act
        $result = $this->service->runDailyMonitoring();

        // Assert
        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(0, $result['errors']);
        
        // Check that last_monitored_at was updated
        $validation1->refresh();
        $validation2->refresh();
        $this->assertNotNull($validation1->last_monitored_at);
        $this->assertNotNull($validation2->last_monitored_at);
        $this->assertTrue($validation1->last_monitored_at->isToday());
        $this->assertTrue($validation2->last_monitored_at->isToday());
    }

    /**
     * Test compliance score calculation.
     */
    public function test_calculates_compliance_score()
    {
        // Arrange
        $order = Order::factory()->create();
        
        $validation = MedicareMacValidation::create([
            'validation_id' => 'VAL001',
            'order_id' => $order->id,
            'facility_id' => $order->facility_id,
            'validation_type' => 'wound_care_only',
            'validation_status' => 'validated',
            'mac_contractor' => 'Noridian',
            'mac_jurisdiction' => 'J-E',
            'documentation_complete' => true,
            'frequency_appropriate' => true,
            'medical_necessity_met' => true,
            'billing_compliant' => false,
            'cms_compliant' => true
        ]);

        // Act
        $score = $validation->getComplianceScore();

        // Assert
        $this->assertEquals(80, $score); // 4 out of 5 requirements met
    }

    /**
     * Test identifying missing compliance items.
     */
    public function test_identifies_missing_compliance_items()
    {
        // Arrange
        $order = Order::factory()->create();
        
        $validation = MedicareMacValidation::create([
            'validation_id' => 'VAL001',
            'order_id' => $order->id,
            'facility_id' => $order->facility_id,
            'validation_type' => 'wound_care_only',
            'validation_status' => 'requires_review',
            'mac_contractor' => 'Noridian',
            'mac_jurisdiction' => 'J-E',
            'documentation_complete' => false,
            'frequency_appropriate' => true,
            'medical_necessity_met' => false,
            'billing_compliant' => true,
            'cms_compliant' => true
        ]);

        // Act
        $missingItems = $validation->getMissingComplianceItems();

        // Assert
        $this->assertCount(2, $missingItems);
        $this->assertContains('Complete documentation', $missingItems);
        $this->assertContains('Medical necessity requirements', $missingItems);
    }
}