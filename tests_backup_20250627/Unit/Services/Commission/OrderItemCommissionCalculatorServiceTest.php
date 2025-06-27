<?php

namespace Tests\Unit\Services\Commission;

use Tests\TestCase;
use App\Services\OrderItemCommissionCalculatorService;
use App\Services\CommissionRuleFinderService;
use App\Models\Order\OrderItem;
use App\Models\Order\Order;
use App\Models\Order\Product;
use App\Models\MscSalesRep;
use App\Models\Commissions\CommissionRule;
use App\Models\Commissions\CommissionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class OrderItemCommissionCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemCommissionCalculatorService $service;
    private $mockRuleFinder;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRuleFinder = Mockery::mock(CommissionRuleFinderService::class);
        $this->service = new OrderItemCommissionCalculatorService($this->mockRuleFinder);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test calculate commission for order item with valid rule.
     */
    public function test_calculates_commission_for_order_item_with_valid_rule()
    {
        // Arrange
        $product = Product::factory()->create(['msc_price' => 100.00]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
            'total_amount' => 200.00
        ]);
        
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 15.0,
            'is_active' => true
        ]);

        $this->mockRuleFinder
            ->shouldReceive('findApplicableRule')
            ->once()
            ->with(Mockery::type(Product::class), Mockery::type(MscSalesRep::class))
            ->andReturn($rule);

        $this->mockRuleFinder
            ->shouldReceive('getCommissionRate')
            ->once()
            ->with(Mockery::type(Product::class), Mockery::type(MscSalesRep::class))
            ->andReturn(15.0);

        // Act
        $result = $this->service->calculateCommission($orderItem, $rep);

        // Assert
        $this->assertInstanceOf(CommissionRecord::class, $result);
        $this->assertEquals($order->id, $result->order_id);
        $this->assertEquals($orderItem->id, $result->order_item_id);
        $this->assertEquals($rep->id, $result->rep_id);
        $this->assertEquals('product', $result->commission_type);
        $this->assertEquals(15.0, $result->commission_rate);
        $this->assertEquals(200.00, $result->base_amount);
        $this->assertEquals(30.00, $result->commission_amount); // 15% of 200
        $this->assertEquals('pending', $result->status);
    }

    /**
     * Test calculate commission with no applicable rule returns zero commission.
     */
    public function test_calculates_zero_commission_when_no_rule_found()
    {
        // Arrange
        $product = Product::factory()->create(['msc_price' => 100.00]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.00,
            'total_amount' => 100.00
        ]);
        
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);

        $this->mockRuleFinder
            ->shouldReceive('findApplicableRule')
            ->once()
            ->andReturn(null);

        $this->mockRuleFinder
            ->shouldReceive('getCommissionRate')
            ->once()
            ->andReturn(0);

        // Act
        $result = $this->service->calculateCommission($orderItem, $rep);

        // Assert
        $this->assertInstanceOf(CommissionRecord::class, $result);
        $this->assertEquals(0, $result->commission_rate);
        $this->assertEquals(0, $result->commission_amount);
        $this->assertEquals('pending', $result->status);
    }

    /**
     * Test calculate commission for parent rep when sub-rep makes sale.
     */
    public function test_calculates_parent_rep_commission_for_sub_rep_sale()
    {
        // Arrange
        $product = Product::factory()->create(['msc_price' => 100.00]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.00,
            'total_amount' => 100.00
        ]);
        
        $parentRep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        $subRep = MscSalesRep::factory()->create([
            'rep_type' => 'msc_sub_rep',
            'parent_rep_id' => $parentRep->id
        ]);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 15.0,
            'msc_sub_rep_rate' => 8.0,
            'is_active' => true
        ]);

        // When calculating for sub-rep
        $this->mockRuleFinder
            ->shouldReceive('findApplicableRule')
            ->once()
            ->andReturn($rule);

        $this->mockRuleFinder
            ->shouldReceive('getCommissionRate')
            ->once()
            ->andReturn(8.0);

        // Act
        $result = $this->service->calculateCommission($orderItem, $subRep);

        // Assert
        $this->assertEquals($subRep->id, $result->rep_id);
        $this->assertEquals($parentRep->id, $result->parent_rep_id);
        $this->assertEquals(8.0, $result->commission_rate);
        $this->assertEquals(8.00, $result->commission_amount); // 8% of 100
        $this->assertEquals('sub_rep', $result->commission_type);
    }

    /**
     * Test commission calculation with different order item quantities.
     */
    public function test_calculates_commission_based_on_total_amount()
    {
        // Arrange
        $product = Product::factory()->create(['msc_price' => 50.00]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 50.00,
            'total_amount' => 250.00
        ]);
        
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 10.0,
            'is_active' => true
        ]);

        $this->mockRuleFinder
            ->shouldReceive('findApplicableRule')
            ->once()
            ->andReturn($rule);

        $this->mockRuleFinder
            ->shouldReceive('getCommissionRate')
            ->once()
            ->andReturn(10.0);

        // Act
        $result = $this->service->calculateCommission($orderItem, $rep);

        // Assert
        $this->assertEquals(250.00, $result->base_amount);
        $this->assertEquals(25.00, $result->commission_amount); // 10% of 250
    }

    /**
     * Test commission calculation stores rule ID for audit trail.
     */
    public function test_stores_commission_rule_id_for_audit_trail()
    {
        // Arrange
        $product = Product::factory()->create(['msc_price' => 100.00]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.00,
            'total_amount' => 100.00
        ]);
        
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $rule = CommissionRule::factory()->create([
            'id' => 999,
            'target_type' => 'product',
            'target_id' => $product->id,
            'msc_rep_rate' => 12.0,
            'is_active' => true
        ]);

        $this->mockRuleFinder
            ->shouldReceive('findApplicableRule')
            ->once()
            ->andReturn($rule);

        $this->mockRuleFinder
            ->shouldReceive('getCommissionRate')
            ->once()
            ->andReturn(12.0);

        // Act
        $result = $this->service->calculateCommission($orderItem, $rep);

        // Assert
        $this->assertEquals(999, $result->commission_rule_id);
    }

    /**
     * Test commission calculation for category-based rule.
     */
    public function test_calculates_commission_with_category_rule()
    {
        // Arrange
        $product = Product::factory()->create([
            'msc_price' => 100.00,
            'category' => 'wound_care'
        ]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.00,
            'total_amount' => 100.00
        ]);
        
        $rep = MscSalesRep::factory()->create(['rep_type' => 'msc_rep']);
        
        $rule = CommissionRule::factory()->create([
            'target_type' => 'category',
            'target_id' => 'wound_care',
            'msc_rep_rate' => 10.0,
            'is_active' => true
        ]);

        $this->mockRuleFinder
            ->shouldReceive('findApplicableRule')
            ->once()
            ->andReturn($rule);

        $this->mockRuleFinder
            ->shouldReceive('getCommissionRate')
            ->once()
            ->andReturn(10.0);

        // Act
        $result = $this->service->calculateCommission($orderItem, $rep);

        // Assert
        $this->assertEquals('category', $result->commission_type);
        $this->assertEquals(10.0, $result->commission_rate);
        $this->assertEquals(10.00, $result->commission_amount);
    }
}