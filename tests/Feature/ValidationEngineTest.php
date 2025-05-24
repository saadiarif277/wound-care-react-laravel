<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ValidationBuilderEngine;
use App\Services\WoundCareValidationEngine;
use App\Services\PulmonologyWoundCareValidationEngine;
use App\Services\CmsCoverageApiService;
use App\Models\Order;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Mockery;

class ValidationEngineTest extends TestCase
{
    use RefreshDatabase;

    private ValidationBuilderEngine $validationEngine;
    private WoundCareValidationEngine $woundCareEngine;
    private PulmonologyWoundCareValidationEngine $pulmonologyEngine;
    private CmsCoverageApiService $cmsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validationEngine = app(ValidationBuilderEngine::class);
        $this->woundCareEngine = app(WoundCareValidationEngine::class);
        $this->pulmonologyEngine = app(PulmonologyWoundCareValidationEngine::class);
        $this->cmsService = app(CmsCoverageApiService::class);
    }

    /** @test */
    public function it_can_build_wound_care_validation_rules()
    {
        // Mock CMS API response
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([
                'data' => [
                    ['id' => 'L38295', 'title' => 'Wound Care', 'state' => 'CA'],
                    ['id' => 'L38296', 'title' => 'Skin Substitutes', 'state' => 'CA']
                ],
                'total' => 2
            ])
        ]);

        $rules = $this->woundCareEngine->buildValidationRules('CA');

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('pre_purchase_qualification', $rules);
        $this->assertArrayHasKey('wound_type_classification', $rules);
        $this->assertArrayHasKey('comprehensive_wound_assessment', $rules);
        $this->assertArrayHasKey('conservative_care_documentation', $rules);
        $this->assertArrayHasKey('clinical_assessments', $rules);
        $this->assertArrayHasKey('mac_coverage_verification', $rules);
    }

    /** @test */
    public function it_can_build_pulmonology_wound_care_validation_rules()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([
                'data' => [
                    ['id' => 'L38295', 'title' => 'Pulmonary Function', 'state' => 'CA'],
                    ['id' => 'L38296', 'title' => 'Oxygen Therapy', 'state' => 'CA']
                ],
                'total' => 2
            ])
        ]);

        $rules = $this->pulmonologyEngine->buildValidationRules('CA');

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('pre_treatment_qualification', $rules);
        $this->assertArrayHasKey('pulmonary_history_assessment', $rules);
        $this->assertArrayHasKey('wound_assessment_with_pulmonary_considerations', $rules);
        $this->assertArrayHasKey('pulmonary_function_assessment', $rules);
        $this->assertArrayHasKey('tissue_oxygenation_assessment', $rules);
        $this->assertArrayHasKey('conservative_care_pulmonary_specific', $rules);
        $this->assertArrayHasKey('coordinated_care_planning', $rules);
        $this->assertArrayHasKey('mac_coverage_verification', $rules);
    }

    /** @test */
    public function it_delegates_to_correct_engine_based_on_specialty()
    {
        $woundCareRules = $this->validationEngine->buildValidationRulesForSpecialty('wound_care_specialty', 'CA');
        $pulmonologyRules = $this->validationEngine->buildValidationRulesForSpecialty('pulmonology_wound_care', 'CA');

        $this->assertArrayHasKey('wound_type_classification', $woundCareRules);
        $this->assertArrayHasKey('pulmonary_function_assessment', $pulmonologyRules);
        $this->assertArrayNotHasKey('pulmonary_function_assessment', $woundCareRules);
    }

    /** @test */
    public function it_validates_wound_care_orders()
    {
        $order = Order::factory()->create(['specialty' => 'wound_care_specialty']);

        $result = $this->woundCareEngine->validateOrder($order, 'CA');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertArrayHasKey('validations', $result);
        $this->assertContains($result['overall_status'], ['passed', 'failed', 'pending', 'requires_review']);
    }

    /** @test */
    public function it_validates_pulmonology_wound_care_orders()
    {
        $order = Order::factory()->create(['specialty' => 'pulmonology_wound_care']);

        $result = $this->pulmonologyEngine->validateOrder($order, 'CA');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertArrayHasKey('validations', $result);
        $this->assertIsArray($result['validations']);
    }

    /** @test */
    public function it_caches_validation_rules()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->with('validation_rules_wound_care_specialty_CA', 30, \Mockery::type('Closure'))
            ->andReturn(['cached' => 'rules']);

        $rules = $this->validationEngine->buildValidationRulesForSpecialty('wound_care_specialty', 'CA');

        $this->assertEquals(['cached' => 'rules'], $rules);
    }

    /** @test */
    public function it_handles_cms_api_failures_gracefully()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([], 500)
        ]);

        $rules = $this->woundCareEngine->buildValidationRules('CA');

        // Should still return base rules even if CMS API fails
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('pre_purchase_qualification', $rules);
    }

    /** @test */
    public function wound_care_rules_have_required_validation_structure()
    {
        $rules = $this->woundCareEngine->buildValidationRules('CA');

        // Test wound measurements structure
        $this->assertArrayHasKey('comprehensive_wound_assessment', $rules);
        $measurements = $rules['comprehensive_wound_assessment']['measurements'];

        $this->assertArrayHasKey('length_cm', $measurements);
        $this->assertEquals('numeric', $measurements['length_cm']['type']);
        $this->assertTrue($measurements['length_cm']['required']);

        // Test wound bed tissue percentages sum to 100
        $woundBed = $rules['comprehensive_wound_assessment']['wound_bed_tissue'];
        $this->assertTrue($woundBed['total_must_equal_100']);
    }

    /** @test */
    public function pulmonary_rules_have_required_spirometry_validation()
    {
        $rules = $this->pulmonologyEngine->buildValidationRules('CA');

        $spirometry = $rules['pulmonary_function_assessment']['spirometry_results'];

        $this->assertArrayHasKey('fev1_percent_predicted', $spirometry);
        $this->assertEquals('numeric', $spirometry['fev1_percent_predicted']['type']);
        $this->assertEquals(0, $spirometry['fev1_percent_predicted']['min']);
        $this->assertEquals(200, $spirometry['fev1_percent_predicted']['max']);

        $this->assertArrayHasKey('fev1_fvc_ratio', $spirometry);
        $this->assertEquals(1, $spirometry['fev1_fvc_ratio']['max']);
    }

    /** @test */
    public function it_validates_dual_mac_coverage_for_pulmonology_wound_care()
    {
        $rules = $this->pulmonologyEngine->buildValidationRules('CA');

        $macRules = $rules['mac_coverage_verification'];

        $this->assertArrayHasKey('lcd_wound_care', $macRules);
        $this->assertArrayHasKey('lcd_pulmonary', $macRules);
        $this->assertTrue($macRules['lcd_wound_care']['required']);
        $this->assertTrue($macRules['lcd_pulmonary']['required']);
    }

    /** @test */
    public function it_validates_coordinated_care_requirements()
    {
        $rules = $this->pulmonologyEngine->buildValidationRules('CA');

        $careTeam = $rules['coordinated_care_planning']['multidisciplinary_team'];

        $this->assertTrue($careTeam['pulmonologist']['required']);
        $this->assertTrue($careTeam['wound_care_specialist']['required']);
        $this->assertEquals('boolean', $careTeam['respiratory_therapist']['type']);
    }

    /** @test */
    public function conservative_care_has_minimum_duration_requirement()
    {
        $rules = $this->woundCareEngine->buildValidationRules('CA');

        $conservativeCare = $rules['conservative_care_documentation'];

        $this->assertEquals(4, $conservativeCare['minimum_duration_weeks']['min']);
        $this->assertTrue($conservativeCare['minimum_duration_weeks']['required']);
    }

    /** @test */
    public function tissue_oxygenation_requires_tcpo2_measurements()
    {
        $rules = $this->pulmonologyEngine->buildValidationRules('CA');

        $tcpo2 = $rules['tissue_oxygenation_assessment']['transcutaneous_oxygen_pressure'];

        $this->assertTrue($tcpo2['wound_site_mmhg']['required']);
        $this->assertTrue($tcpo2['reference_site_mmhg']['required']);
        $this->assertTrue($tcpo2['on_room_air']['required']);
        $this->assertEquals('boolean', $tcpo2['on_room_air']['type']);
    }
}
