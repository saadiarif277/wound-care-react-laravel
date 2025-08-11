<?php

namespace Tests\Unit\Services;

use App\Models\Order\OrderEmailNotification;
use App\Models\Order\ProductRequest;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\Concerns\InteractsWithContainer;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\InteractsWithTime;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithConsole;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;

abstract class LiteTestCase extends \PHPUnit\Framework\TestCase
{
    use InteractsWithContainer,
        InteractsWithExceptionHandling,
        InteractsWithSession,
        InteractsWithTime,
        InteractsWithAuthentication,
        InteractsWithConsole,
        InteractsWithViews;

    protected $app;

    protected function setUp(): void
    {
        parent::setUp();
        // Boot the Laravel application via bootstrap/app.php like base TestCase would
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(ConsoleKernelContract::class)->bootstrap();
        $this->app = $app;
    }

    protected function tearDown(): void
    {
        // Restore any global handlers Laravel may have registered during bootstrap
        // to avoid PHPUnit marking tests as risky.
        while (@restore_error_handler()) {}
        while (@restore_exception_handler()) {}
        parent::tearDown();
    }
}

class EmailNotificationServiceTest extends LiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Use array mailer so nothing is actually sent
        config(['mail.default' => 'array']);
    // Ensure encryption + session/auth work
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['session.driver' => 'array']);
        // Force sqlite memory DB and bootstrap minimal schema only once
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        $this->setUpMinimalSchema();
        // Register minimal route used by getEmailContent()
        Route::get('/admin/orders/{id}', function ($id) {
            return 'ok';
        })->name('admin.orders.show');
    // Register tracking pixel and order tracking routes used by service
    Route::get('/email/track', function () { return '1x1'; })->name('email.track');
    Route::get('/orders/{order}/track', function ($order) { return 'ok'; })->name('order.track');
    }

    private function setUpMinimalSchema(): void
    {
        // Users
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // Product Requests (orders)
        if (!Schema::hasTable('product_requests')) {
            Schema::create('product_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('patient_display_id')->nullable();
                $table->boolean('email_notifications_enabled')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // Email notifications
        if (!Schema::hasTable('order_email_notifications')) {
            Schema::create('order_email_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('notification_type');
                $table->string('recipient_email');
                $table->string('recipient_name')->nullable();
                $table->string('subject')->nullable();
                $table->text('content')->nullable();
                $table->string('status');
                $table->string('message_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_status_change_creates_notification_and_marks_sent()
    {

    $userId = DB::table('users')->insertGetId([
            'email' => 'requestor@example.com',
            'name' => 'Requestor Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('product_requests')->insertGetId([
            'request_number' => 'REQ-123',
            'created_by' => $userId,
            'patient_display_id' => 'Patient A',
            'email_notifications_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->find($userId);
    $order = ProductRequest::query()->find($orderId);
    // Provide a manufacturer name to satisfy template payloads
    $order->setRelation('manufacturer', (object)['name' => 'Acme']);
    // Provide provider relation so getOrderRequestor() resolves
    $order->setRelation('provider', (object)['email' => 'requestor@example.com', 'name' => 'Requestor Name']);

    // authenticate as an admin/tester
    $this->actingAs(User::query()->find($userId));
    $svc = app(EmailNotificationService::class);

        $ok = $svc->sendStatusChangeNotification(
            $order,
            'sent',
            'verified',
            'Admin Tester',
            'All good',
            null
        );

        $this->assertTrue($ok);

        $notif = OrderEmailNotification::first();
        $this->assertNotNull($notif);
        $this->assertEquals($order->id, $notif->order_id);
        $this->assertEquals('status_change', $notif->notification_type);
        $this->assertEquals('sent', $notif->status);
        $this->assertNotNull($notif->sent_at);
    }

    public function test_status_change_with_attachments_succeeds()
    {

    $userId = DB::table('users')->insertGetId([
            'email' => 'requestor@example.com',
            'name' => 'Requestor Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('product_requests')->insertGetId([
            'request_number' => 'REQ-456',
            'created_by' => $userId,
            'patient_display_id' => 'Patient B',
            'email_notifications_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $order = ProductRequest::query()->find($orderId);
    $order->setRelation('manufacturer', (object)['name' => 'Acme']);
    $order->setRelation('provider', (object)['email' => 'requestor@example.com', 'name' => 'Requestor Name']);

    $this->actingAs(User::query()->find($userId));
    $svc = app(EmailNotificationService::class);

        $attachments = [
            ['path' => base_path('README.md'), 'name' => 'readme.txt', 'mime' => 'text/plain']
        ];

        $ok = $svc->sendStatusChangeNotification(
            $order,
            'submitted_to_manufacturer',
            'confirmed_by_manufacturer',
            'Admin Tester',
            'Confirmed by MFG',
            $attachments
        );

        $this->assertTrue($ok);

        $notif = OrderEmailNotification::where('order_id', $order->id)->latest()->first();
        $this->assertNotNull($notif);
        $this->assertEquals('sent', $notif->status);
    }
}
