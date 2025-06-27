<?php

namespace Tests\Feature;

use App\Models\Order\Product;
use App\Models\User;
use App\Services\CmsEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $providerUser;
    private Product $testProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with appropriate permissions
        $this->adminUser = User::factory()->create();
        $this->providerUser = User::factory()->create();

        // Create test product with Q-code
        $this->testProduct = Product::factory()->create([
            'name' => 'Test Wound Dressing',
            'q_code' => '4154',
            'national_asp' => null,
            'mue' => null,
            'cms_last_updated' => null,
        ]);
    }

    /** @test */
    public function cms_enrichment_service_can_get_reimbursement_data()
    {
        $service = new CmsEnrichmentService();

        // Test valid Q-code
        $result = $service->getCmsReimbursement('Q4154');
        $this->assertEquals(550.64, $result['asp']);
        $this->assertEquals(36, $result['mue']);

        // Test invalid Q-code
        $result = $service->getCmsReimbursement('Q9999');
        $this->assertNull($result['asp']);
        $this->assertNull($result['mue']);
    }

    /** @test */
    public function cms_enrichment_service_normalizes_qcodes()
    {
        $service = new CmsEnrichmentService();

        // Test various Q-code formats
        $this->assertEquals('Q4154', $service->normalizeQCode('4154'));
        $this->assertEquals('Q4154', $service->normalizeQCode('q4154'));
        $this->assertEquals('Q4154', $service->normalizeQCode(' Q4154 '));
    }

    /** @test */
    public function cms_enrichment_service_can_enrich_single_product()
    {
        $service = new CmsEnrichmentService();

        $result = $service->enrichProduct($this->testProduct);

        $this->assertTrue($result);
        $this->testProduct->refresh();
        $this->assertEquals(550.64, $this->testProduct->national_asp);
        $this->assertEquals(36, $this->testProduct->mue);
        $this->assertNotNull($this->testProduct->cms_last_updated);
    }

    /** @test */
    public function product_model_validates_mue_limits()
    {
        $this->testProduct->update(['mue' => 36]);

        // Test valid quantity
        $this->assertFalse($this->testProduct->exceedsMueLimit(30));

        // Test excessive quantity
        $this->assertTrue($this->testProduct->exceedsMueLimit(50));

        // Test exact limit
        $this->assertFalse($this->testProduct->exceedsMueLimit(36));
    }

    /** @test */
    public function product_model_validates_order_quantities()
    {
        $this->testProduct->update(['mue' => 36]);

        $validation = $this->testProduct->validateOrderQuantity(30);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);

        $validation = $this->testProduct->validateOrderQuantity(50);
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /** @test */
    public function artisan_command_can_sync_cms_pricing()
    {
        $this->artisan('cms:sync-pricing', ['--force' => true])
            ->expectsOutput('🔄 Starting CMS ASP/MUE pricing sync...')
            ->assertExitCode(0);

        $this->testProduct->refresh();
        $this->assertEquals(550.64, $this->testProduct->national_asp);
        $this->assertEquals(36, $this->testProduct->mue);
        $this->assertNotNull($this->testProduct->cms_last_updated);
    }

    /** @test */
    public function artisan_command_dry_run_shows_changes_without_updating()
    {
        $originalAsp = $this->testProduct->national_asp;
        $originalMue = $this->testProduct->mue;

        $this->artisan('cms:sync-pricing', ['--dry-run' => true])
            ->expectsOutput('🧪 Dry run completed - no changes were made')
            ->assertExitCode(0);

        $this->testProduct->refresh();
        $this->assertEquals($originalAsp, $this->testProduct->national_asp);
        $this->assertEquals($originalMue, $this->testProduct->mue);
    }

    /** @test */
    public function admin_can_trigger_cms_sync_via_api()
    {
        // Mock admin permissions
        $this->adminUser->givePermissionTo('manage-products');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/products/cms/sync');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->testProduct->refresh();
        $this->assertEquals(550.64, $this->testProduct->national_asp);
        $this->assertEquals(36, $this->testProduct->mue);
    }

    /** @test */
    public function non_admin_cannot_trigger_cms_sync()
    {
        $response = $this->actingAs($this->providerUser)
            ->postJson('/api/products/cms/sync');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_get_cms_sync_status()
    {
        // Mock admin permissions
        $this->adminUser->givePermissionTo('manage-products');

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/products/cms/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_products_with_qcodes',
                'synced_products',
                'stale_products',
                'needs_update_products',
                'last_sync',
                'sync_coverage_percentage'
            ]);
    }

    /** @test */
    public function quantity_validation_endpoint_works()
    {
        $this->testProduct->update(['mue' => 36]);

        // Mock admin permissions
        $this->adminUser->givePermissionTo('manage-products');

        // Test valid quantity
        $response = $this->actingAs($this->adminUser)
            ->postJson("/products/{$this->testProduct->id}/validate-quantity", [
                'quantity' => 30
            ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);

        // Test invalid quantity
        $response = $this->actingAs($this->adminUser)
            ->postJson("/products/{$this->testProduct->id}/validate-quantity", [
                'quantity' => 50
            ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => false]);
    }

        /** @test */
    public function product_filtering_shows_asp_to_providers_hides_from_office_managers()
    {
        $this->testProduct->update([
            'national_asp' => 550.64,
            'mue' => 36,
            'cms_last_updated' => now()
        ]);

        // Test provider can see ASP pricing
        $this->providerUser->givePermissionTo(['view-products', 'view-providers']);

        $response = $this->actingAs($this->providerUser)
            ->getJson("/api/products/{$this->testProduct->id}");

        $response->assertStatus(200);
        $data = $response->json();

        // Provider should see ASP but not sensitive CMS data
        $this->assertArrayHasKey('national_asp', $data);
        $this->assertEquals(550.64, $data['national_asp']);
        $this->assertArrayNotHasKey('mue', $data);
        $this->assertArrayNotHasKey('cms_last_updated', $data);

        // Should see processed MUE information
        $this->assertArrayHasKey('has_quantity_limits', $data);
        $this->assertArrayHasKey('max_allowed_quantity', $data);
    }

    /** @test */
    public function product_filtering_hides_asp_from_office_managers()
    {
        $this->testProduct->update([
            'national_asp' => 550.64,
            'mue' => 36,
            'cms_last_updated' => now()
        ]);

        // Create office manager user with only basic permissions
        /** @var \App\Models\User $officeManager */
        $officeManager = User::factory()->create();
        $officeManager->givePermissionTo('view-products');

        $response = $this->actingAs($officeManager)
            ->getJson("/api/products/{$this->testProduct->id}");

        $response->assertStatus(200);
        $data = $response->json();

        // Office manager should NOT see ASP pricing
        $this->assertArrayNotHasKey('national_asp', $data);
        $this->assertArrayNotHasKey('mue', $data);
        $this->assertArrayNotHasKey('cms_last_updated', $data);

        // Should still see processed MUE information for order limits
        $this->assertArrayHasKey('has_quantity_limits', $data);
        $this->assertArrayHasKey('max_allowed_quantity', $data);
    }

    /** @test */
    public function admin_can_see_cms_data_and_status()
    {
        $this->testProduct->update([
            'national_asp' => 550.64,
            'mue' => 36,
            'cms_last_updated' => now()
        ]);

        // Mock admin permissions
        $this->adminUser->givePermissionTo(['manage-products', 'view-financials']);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/products/{$this->testProduct->id}");

        $response->assertStatus(200);
        $data = $response->json();

        // Should see all data including CMS status
        $this->assertArrayHasKey('national_asp', $data);
        $this->assertEquals(550.64, $data['national_asp']);
        $this->assertArrayHasKey('cms_status', $data);
        $this->assertArrayHasKey('cms_last_updated', $data);
    }

    /** @test */
    public function cms_status_attribute_works_correctly()
    {
        // Test not synced
        $this->assertEquals('not_synced', $this->testProduct->cms_status);

        // Test current
        $this->testProduct->update(['cms_last_updated' => now()]);
        $this->assertEquals('current', $this->testProduct->cms_status);

        // Test needs update
        $this->testProduct->update(['cms_last_updated' => now()->subDays(45)]);
        $this->assertEquals('needs_update', $this->testProduct->cms_status);

        // Test stale
        $this->testProduct->update(['cms_last_updated' => now()->subDays(120)]);
        $this->assertEquals('stale', $this->testProduct->cms_status);
    }

    /** @test */
    public function cms_enrichment_service_provides_sync_statistics()
    {
        // Create additional test products
        Product::factory()->create(['q_code' => '4262']); // Has CMS data
        Product::factory()->create(['q_code' => '9999']); // No CMS data
        Product::factory()->create(['q_code' => null]); // No Q-code

        $service = new CmsEnrichmentService();
        $stats = $service->getSyncStatistics();

        $this->assertArrayHasKey('total_products_with_qcodes', $stats);
        $this->assertArrayHasKey('available_in_cms', $stats);
        $this->assertArrayHasKey('sync_coverage', $stats);
        $this->assertArrayHasKey('cms_coverage', $stats);
    }
}
