<?php

namespace Tests\Unit\Services\Commission;

use Tests\TestCase;
use App\Services\CommissionRuleFinderService;
use App\Models\Order\Product;
use App\Models\MscSalesRep;
use App\Models\Commissions\CommissionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

class CommissionRuleFinderServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionRuleFinderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionRuleFinderService();
    }

    /**
     * Test finding rule with direct product match for MSC rep.
     */
    public function test_finds_rule_with_direct_product_match_for_msc_rep()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 15.5,
            'is_active' => true
        ]);

        // Act
        $result = $this->service->findApplicableRule($product, $rep);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($rule->id, $result->id);
        $this->assertEquals(15.5, $result->msc_rep_rate);
    }

    /**
     * Test finding rule with category match when no product match exists.
     */
    public function test_finds_rule_with_category_match_when_no_product_match()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $categoryRule = CommissionRule::factory()->create([
            'target_type' => 'category',
            'target_id' => 'wound_care',
            'msc_rep_rate' => 12.0,
            'is_active' => true
        ]);

        // Act
        $result = $this->service->findApplicableRule($product, $rep);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($categoryRule->id, $result->id);
        $this->assertEquals(12.0, $result->msc_rep_rate);
    }

    /**
     * Test prioritizes product rule over category rule.
     */
    public function test_prioritizes_product_rule_over_category_rule()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $productRule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 15.0,
            'is_active' => true
        ]);

        $categoryRule = CommissionRule::factory()->create([
            'target_type' => 'category',
            'target_id' => 'wound_care',
            'msc_rep_rate' => 10.0,
            'is_active' => true
        ]);

        // Act
        $result = $this->service->findApplicableRule($product, $rep);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($productRule->id, $result->id);
        $this->assertEquals(15.0, $result->msc_rep_rate);
    }

    /**
     * Test returns null when no rules exist.
     */
    public function test_returns_null_when_no_rules_exist()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);

        // Act
        $result = $this->service->findApplicableRule($product, $rep);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test ignores inactive rules.
     */
    public function test_ignores_inactive_rules()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $inactiveRule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 20.0,
            'is_active' => false
        ]);

        // Act
        $result = $this->service->findApplicableRule($product, $rep);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test gets commission rate for MSC rep.
     */
    public function test_gets_commission_rate_for_msc_rep()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 15.5,
            'msc_sub_rep_rate' => 8.0,
            'is_active' => true
        ]);

        // Act
        $rate = $this->service->getCommissionRate($product, $rep);

        // Assert
        $this->assertEquals(15.5, $rate);
    }

    /**
     * Test gets commission rate for MSC sub-rep.
     */
    public function test_gets_commission_rate_for_msc_sub_rep()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_sub_rep']);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 15.5,
            'msc_sub_rep_rate' => 8.0,
            'is_active' => true
        ]);

        // Act
        $rate = $this->service->getCommissionRate($product, $rep);

        // Assert
        $this->assertEquals(8.0, $rate);
    }

    /**
     * Test returns zero rate when no rule found.
     */
    public function test_returns_zero_rate_when_no_rule_found()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);

        // Act
        $rate = $this->service->getCommissionRate($product, $rep);

        // Assert
        $this->assertEquals(0, $rate);
    }

    /**
     * Test handles organization-specific rules.
     */
    public function test_handles_organization_specific_rules()
    {
        // Arrange
        $product = Product::factory()->create(['id' => 1, 'category' => 'wound_care']);
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep', 'organization_id' => 100]);
        
        $orgRule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'organization_id' => 100,
            'msc_rep_rate' => 18.0,
            'is_active' => true
        ]);

        $globalRule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'organization_id' => null,
            'msc_rep_rate' => 12.0,
            'is_active' => true
        ]);

        // Act
        $result = $this->service->findApplicableRule($product, $rep);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($orgRule->id, $result->id);
        $this->assertEquals(18.0, $result->msc_rep_rate);
    }
}