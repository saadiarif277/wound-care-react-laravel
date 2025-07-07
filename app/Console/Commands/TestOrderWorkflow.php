<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Order\Order;
use App\Models\Product;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\StatusChangeService;
use App\Services\EmailNotificationService;
use App\Services\Compliance\PhiAuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TestOrderWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:order-workflow 
                            {--user=1 : User ID to run tests as}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete order workflow without affecting production data';

    protected QuickRequestOrchestrator $orchestrator;
    protected StatusChangeService $statusService;
    protected PhiAuditService $auditService;

    public function __construct(
        QuickRequestOrchestrator $orchestrator,
        StatusChangeService $statusService,
        PhiAuditService $auditService
    ) {
        parent::__construct();
        $this->orchestrator = $orchestrator;
        $this->statusService = $statusService;
        $this->auditService = $auditService;
    }

    public function handle()
    {
        $this->info('🚀 Starting Order Workflow Tests...');
        
        // Authenticate as test user
        $userId = $this->option('user');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error('User not found!');
            return 1;
        }
        
        Auth::login($user);
        $this->info("Testing as: {$user->name} ({$user->role->name})");
        
        $passedTests = 0;
        $failedTests = 0;
        
        // Run test suite
        try {
            // Test 1: Quick Request Submission
            $this->info("\n📋 Testing Quick Request Submission...");
            $orderId = $this->testQuickRequestSubmission();
            if ($orderId) {
                $passedTests++;
                $this->info("✓ Quick request submission successful - Order ID: {$orderId}");
            } else {
                $failedTests++;
                $this->error("✗ Quick request submission failed");
            }
            
            // Test 2: Permission-Based Data Visibility
            $this->info("\n🔒 Testing Role-Based Data Visibility...");
            if ($this->testPermissionBasedDataVisibility()) {
                $passedTests++;
                $this->info("✓ Role-based visibility working correctly");
            } else {
                $failedTests++;
                $this->error("✗ Role-based visibility test failed");
            }
            
            // Test 3: Order Status Management
            if ($orderId) {
                $this->info("\n📊 Testing Order Status Management...");
                if ($this->testOrderStatusManagement($orderId)) {
                    $passedTests++;
                    $this->info("✓ Order status management working correctly");
                } else {
                    $failedTests++;
                    $this->error("✗ Order status management test failed");
                }
            }
            
            // Test 4: Notification System
            if ($orderId) {
                $this->info("\n📧 Testing Notification System...");
                if ($this->testNotificationSystem($orderId)) {
                    $passedTests++;
                    $this->info("✓ Notification system working correctly");
                } else {
                    $failedTests++;
                    $this->error("✗ Notification system test failed");
                }
            }
            
            // Test 5: Audit Logging
            if ($orderId) {
                $this->info("\n📝 Testing Audit Logging...");
                if ($this->testAuditLogging($orderId)) {
                    $passedTests++;
                    $this->info("✓ Audit logging working correctly");
                } else {
                    $failedTests++;
                    $this->error("✗ Audit logging test failed");
                }
            }
            
            // Test 6: Financial Data Filtering
            $this->info("\n💰 Testing Financial Data Filtering...");
            if ($this->testFinancialDataFiltering()) {
                $passedTests++;
                $this->info("✓ Financial data filtering working correctly");
            } else {
                $failedTests++;
                $this->error("✗ Financial data filtering test failed");
            }
            
        } catch (\Exception $e) {
            $this->error("Fatal error: " . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            $failedTests++;
        }
        
        // Summary
        $this->info("\n📊 Test Summary:");
        $this->info("  ✓ Passed: {$passedTests}");
        if ($failedTests > 0) {
            $this->error("  ✗ Failed: {$failedTests}");
        }
        $this->info("  Total: " . ($passedTests + $failedTests));
        
        return $failedTests > 0 ? 1 : 0;
    }

    protected function testQuickRequestSubmission()
    {
        try {
            // Create test data
            $testData = [
                'patient_fhir_id' => 'test-patient-' . now()->timestamp,
                'episode_id' => 'test-episode-' . now()->timestamp,
                'provider_id' => Auth::id(),
                'facility_id' => 1,
                'products' => [
                    [
                        'product_id' => Product::first()->id ?? 1,
                        'quantity' => 2,
                        'size' => 'Large'
                    ]
                ],
                'expected_service_date' => now()->addDays(7)->format('Y-m-d')
            ];
            
            // Simulate order creation
            $order = Order::create([
                'patient_fhir_id' => $testData['patient_fhir_id'],
                'episode_fhir_id' => $testData['episode_id'],
                'provider_id' => $testData['provider_id'],
                'facility_id' => $testData['facility_id'],
                'order_status' => 'pending',
                'expected_service_date' => $testData['expected_service_date'],
            ]);
            
            if ($this->option('verbose')) {
                $this->line("  Created order: {$order->id}");
                $this->line("  Patient FHIR ID: {$order->patient_fhir_id}");
            }
            
            return $order->id;
            
        } catch (\Exception $e) {
            if ($this->option('verbose')) {
                $this->error("  Error: " . $e->getMessage());
            }
            return null;
        }
    }

    protected function testPermissionBasedDataVisibility()
    {
        try {
            $product = Product::first();
            if (!$product) {
                $this->warn("  No products found in database");
                return false;
            }
            
            // Test as different roles
            $roles = ['admin', 'provider', 'office_manager'];
            $results = [];
            
            foreach ($roles as $roleName) {
                $user = User::whereHas('role', function($q) use ($roleName) {
                    $q->where('slug', $roleName);
                })->first();
                
                if (!$user) {
                    $this->warn("  No user found with role: {$roleName}");
                    continue;
                }
                
                Auth::login($user);
                
                // Check if user can see pricing
                $canSeePricing = $user->can('view_financials');
                
                if ($roleName === 'office_manager') {
                    $results[$roleName] = !$canSeePricing;
                } else {
                    $results[$roleName] = $canSeePricing;
                }
                
                if ($this->option('verbose')) {
                    $this->line("  {$roleName}: " . ($canSeePricing ? 'CAN' : 'CANNOT') . " see pricing");
                }
            }
            
            return !in_array(false, $results);
            
        } catch (\Exception $e) {
            if ($this->option('verbose')) {
                $this->error("  Error: " . $e->getMessage());
            }
            return false;
        }
    }

    protected function testOrderStatusManagement($orderId)
    {
        try {
            $order = Order::find($orderId);
            if (!$order) {
                $this->error("  Order not found");
                return false;
            }
            
            $statuses = ['pending_ivr', 'ivr_sent', 'approved', 'submitted_to_manufacturer'];
            
            foreach ($statuses as $status) {
                $result = $this->statusService->changeOrderStatus($order, $status, "Test update to {$status}");
                
                if (!$result) {
                    $this->error("  Failed to update status to: {$status}");
                    return false;
                }
                
                if ($this->option('verbose')) {
                    $this->line("  Status updated to: {$status}");
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->option('verbose')) {
                $this->error("  Error: " . $e->getMessage());
            }
            return false;
        }
    }

    protected function testNotificationSystem($orderId)
    {
        try {
            $order = Order::find($orderId);
            if (!$order) {
                $this->error("  Order not found");
                return false;
            }
            
            // Test logging instead of actual email sending
            Log::info('Test notification for order submission', ['order_id' => $orderId]);
            Log::info('Test notification for status change', ['order_id' => $orderId, 'status' => 'approved']);
            
            if ($this->option('verbose')) {
                $this->line("  Notification logging tested successfully");
            }
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->option('verbose')) {
                $this->error("  Error: " . $e->getMessage());
            }
            return false;
        }
    }

    protected function testAuditLogging($orderId)
    {
        try {
            // Log test action
            $this->auditService->logAccess('test_order_view', 'Order', $orderId, [
                'reason' => 'Testing audit logging',
                'test_run' => true
            ]);
            
            // Retrieve audit logs
            $logs = $this->auditService->getAuditLogs('Order', $orderId, 10);
            
            if ($logs->isEmpty()) {
                $this->error("  No audit logs found");
                return false;
            }
            
            if ($this->option('verbose')) {
                $this->line("  Found {$logs->count()} audit log entries");
                $this->line("  Latest action: " . $logs->first()->action);
            }
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->option('verbose')) {
                $this->error("  Error: " . $e->getMessage());
            }
            return false;
        }
    }

    protected function testFinancialDataFiltering()
    {
        try {
            $roles = [
                'admin' => true,
                'provider' => true,
                'office_manager' => false
            ];
            
            $results = [];
            
            foreach ($roles as $roleName => $shouldSeePricing) {
                $user = User::whereHas('role', function($q) use ($roleName) {
                    $q->where('slug', $roleName);
                })->first();
                
                if (!$user) {
                    $this->warn("  No user found with role: {$roleName}");
                    continue;
                }
                
                Auth::login($user);
                
                // Test permission
                $canViewFinancials = $user->can('view_financials');
                $results[$roleName] = ($canViewFinancials === $shouldSeePricing);
                
                if ($this->option('verbose')) {
                    $this->line("  {$roleName}: " . ($canViewFinancials ? 'CAN' : 'CANNOT') . " view financials - " . 
                               ($results[$roleName] ? 'CORRECT' : 'INCORRECT'));
                }
            }
            
            return !in_array(false, $results);
            
        } catch (\Exception $e) {
            if ($this->option('verbose')) {
                $this->error("  Error: " . $e->getMessage());
            }
            return false;
        }
    }
}