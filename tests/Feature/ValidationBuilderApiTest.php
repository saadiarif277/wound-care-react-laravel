<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\ProductRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class ValidationBuilderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'credentials' => ['specialty' => 'wound_care_specialty']
        ]);
    }

    /** @test */
    public function it_can_get_validation_rules_for_specialty()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([
                'data' => [['id' => 'L38295', 'title' => 'Wound Care', 'state' => 'CA']],
                'total' => 1
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/rules?specialty=wound_care_specialty&state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'specialty',
                    'state',
                    'rules' => [
                        'pre_purchase_qualification',
                        'wound_type_classification',
                        'comprehensive_wound_assessment',
                        'conservative_care_documentation',
                        'clinical_assessments',
                        'mac_coverage_verification'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_get_user_specific_validation_rules()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/user-rules?state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'specialty',
                    'state',
                    'rules'
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_validation_endpoints()
    {
        $response = $this->getJson('/api/v1/validation-builder/rules?specialty=wound_care_specialty');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_specialty_parameter()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/rules?specialty=invalid_specialty');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['specialty']);
    }

    /** @test */
    public function it_can_validate_orders()
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/validation-builder/validate-order', [
                'order_id' => $order->id,
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order_id',
                    'specialty',
                    'validation_results' => [
                        'overall_status',
                        'validations'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_validate_product_requests()
    {
        $productRequest = ProductRequest::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/validation-builder/validate-product-request', [
                'product_request_id' => $productRequest->id,
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'product_request_id',
                    'specialty',
                    'validation_results'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_cms_lcd_data()
    {
        Http::fake([
            'api.coverage.cms.gov/v1/coverage_determinations/local*' => Http::response([
                'data' => [
                    ['id' => 'L38295', 'title' => 'Wound Care', 'state' => 'CA'],
                    ['id' => 'L38296', 'title' => 'Skin Substitutes', 'state' => 'CA']
                ],
                'total' => 2
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty&state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'specialty',
                    'state',
                    'lcds' => [
                        '*' => ['id', 'title']
                    ],
                    'total_count'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_cms_ncd_data()
    {
        Http::fake([
            'api.coverage.cms.gov/v1/coverage_determinations/national*' => Http::response([
                'data' => [
                    ['id' => '270.1', 'title' => 'Durable Medical Equipment', 'version' => '1.1']
                ],
                'total' => 1
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/cms-ncds?specialty=wound_care_specialty');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'specialty',
                    'ncds',
                    'total_count'
                ]
            ]);
    }

    /** @test */
    public function it_can_search_cms_coverage_documents()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([
                'data' => [
                    ['id' => 'L38295', 'title' => 'Wound Dressing Coverage', 'type' => 'lcd']
                ],
                'total' => 1
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/search-cms?keyword=wound+dressing&state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'keyword',
                    'state',
                    'results' => [
                        'lcds',
                        'ncds',
                        'articles'
                    ],
                    'total_results'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_available_specialties()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/specialties');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'specialties' => [
                        '*' => ['key', 'name', 'description']
                    ]
                ]
            ])
            ->assertJsonFragment([
                'key' => 'wound_care_specialty'
            ])
            ->assertJsonFragment([
                'key' => 'pulmonology_wound_care'
            ]);
    }

    /** @test */
    public function it_can_get_mac_jurisdiction_info()
    {
        Http::fake([
            'api.coverage.cms.gov/v1/coverage_jurisdictions*' => Http::response([
                'data' => [
                    [
                        'state' => 'CA',
                        'mac_contractor' => 'Noridian Healthcare Solutions',
                        'jurisdiction' => 'J-L'
                    ]
                ]
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/mac-jurisdiction?state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'state',
                    'mac_contractor',
                    'jurisdiction'
                ]
            ]);
    }

    /** @test */
    public function it_can_clear_cache()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/validation-builder/clear-cache', [
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'specialty',
                    'cache_keys_cleared'
                ]
            ]);
    }

    /** @test */
    public function it_handles_cms_api_failures_gracefully()
    {
        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([], 500)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty&state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'specialty',
                    'state',
                    'lcds',
                    'error_message'
                ]
            ]);
    }

    /** @test */
    public function it_validates_order_ownership()
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/validation-builder/validate-order', [
                'order_id' => $order->id,
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_supports_pulmonology_wound_care_specialty()
    {
        /** @var User $pulmonaryUser */
        $pulmonaryUser = User::factory()->create([
            'credentials' => ['specialty' => 'pulmonology_wound_care']
        ]);

        $response = $this->actingAs($pulmonaryUser)
            ->getJson('/api/v1/validation-builder/rules?specialty=pulmonology_wound_care&state=CA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rules' => [
                        'pre_treatment_qualification',
                        'pulmonary_history_assessment',
                        'wound_assessment_with_pulmonary_considerations',
                        'pulmonary_function_assessment',
                        'tissue_oxygenation_assessment',
                        'conservative_care_pulmonary_specific',
                        'coordinated_care_planning',
                        'mac_coverage_verification'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_appropriate_error_for_missing_order()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/validation-builder/validate-order', [
                'order_id' => 999999,
                'specialty' => 'wound_care_specialty'
            ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_large_cms_responses()
    {
        // Mock a large response to test pagination/handling
        $largeLcdData = collect(range(1, 100))->map(function ($i) {
            return ['id' => "L{$i}", 'title' => "LCD {$i}", 'state' => 'CA'];
        })->toArray();

        Http::fake([
            'api.coverage.cms.gov/*' => Http::response([
                'data' => $largeLcdData,
                'total' => 100
            ])
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/validation-builder/cms-lcds?specialty=wound_care_specialty&state=CA');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_count' => 100
                ]
            ]);
    }
}