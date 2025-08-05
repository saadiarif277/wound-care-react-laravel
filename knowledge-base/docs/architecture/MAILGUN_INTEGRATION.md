Collecting workspace information# Mailgun Notification System Implementation Plan

Based on your codebase analysis, I'll create a comprehensive plan to implement Mailgun as your notification provider with deep links and 2025 best practices.

## 📧 Mailgun Integration Architecture

### 1. Environment Configuration

```bash
# .env configuration
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.mscwoundcare.com
MAILGUN_SECRET=key-xxx
MAILGUN_ENDPOINT=api.mailgun.net
MAILGUN_WEBHOOK_SIGNING_SECRET=xxx

# Optional for EU region
# MAILGUN_ENDPOINT=api.eu.mailgun.net

# Email settings
MAIL_FROM_ADDRESS=notifications@mscwoundcare.com
MAIL_FROM_NAME="MSC Wound Care Portal"
```

### 2. Mailgun Service Implementation

```php
// app/Services/Notifications/MailgunNotificationService.php
<?php

namespace App\Services\Notifications;

use App\Models\EmailLog;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MailgunNotificationService
{
    protected array $config;
    
    public function __construct()
    {
        $this->config = [
            'tracking_domain' => config('services.mailgun.tracking_domain', 'track.mscwoundcare.com'),
            'enable_tracking' => true,
            'enable_analytics' => true,
        ];
    }

    /**
     * Send IVR to manufacturer with deep link
     */
    public function sendIvrToManufacturer(ProductRequest $order, string $pdfPath): void
    {
        $manufacturer = $order->manufacturer;
        $deepLink = $this->generateDeepLink('admin-order', $order->id);
        
        Mail::send('emails.ivr-submission', [
            'order' => $order,
            'manufacturer' => $manufacturer,
            'deepLink' => $deepLink,
            'trackingPixel' => $this->generateTrackingPixel($order->id, 'ivr-sent'),
        ], function ($message) use ($manufacturer, $order, $pdfPath) {
            $message->to($manufacturer->ivr_email)
                    ->cc($manufacturer->ivr_cc_emails ?? [])
                    ->subject("IVR Submission - Order #{$order->id}")
                    ->attach($pdfPath, [
                        'as' => "IVR_Order_{$order->id}.pdf",
                        'mime' => 'application/pdf',
                    ])
                    ->getHeaders()
                    ->addTextHeader('X-Mailgun-Tag', 'ivr-submission')
                    ->addTextHeader('X-Mailgun-Variables', json_encode([
                        'order_id' => $order->id,
                        'manufacturer_id' => $manufacturer->id,
                    ]));
        });
        
        $this->logEmail($order, 'ivr-sent', $manufacturer->ivr_email);
    }

    /**
     * Send status update to provider with deep link
     */
    public function sendProviderStatusUpdate(ProductRequest $order, string $status, ?string $reason = null): void
    {
        $provider = $order->provider;
        $deepLink = $this->generateDeepLink('provider-order', $order->id);
        
        $template = match($status) {
            'sent_back' => 'emails.order-sent-back',
            'denied' => 'emails.order-denied',
            'approved' => 'emails.order-approved',
            'manufacturer_approved' => 'emails.manufacturer-approved',
            'delivered' => 'emails.order-delivered',
            default => 'emails.order-status-update',
        };
        
        Mail::send($template, [
            'order' => $order,
            'provider' => $provider,
            'status' => $status,
            'reason' => $reason,
            'deepLink' => $deepLink,
            'actionButton' => $this->generateActionButton($status, $deepLink),
            'trackingPixel' => $this->generateTrackingPixel($order->id, $status),
        ], function ($message) use ($provider, $order, $status) {
            $message->to($provider->email)
                    ->subject($this->getSubjectForStatus($status, $order->id))
                    ->getHeaders()
                    ->addTextHeader('X-Mailgun-Tag', "status-{$status}")
                    ->addTextHeader('X-Mailgun-Variables', json_encode([
                        'order_id' => $order->id,
                        'provider_id' => $provider->id,
                        'status' => $status,
                    ]));
        });
        
        $this->logEmail($order, "status-{$status}", $provider->email);
    }

    /**
     * Generate secure deep link with JWT token
     */
    protected function generateDeepLink(string $type, string $orderId): string
    {
        $token = $this->generateSecureToken([
            'type' => $type,
            'order_id' => $orderId,
            'exp' => now()->addDays(7)->timestamp,
        ]);
        
        $baseUrl = config('app.url');
        
        return match($type) {
            'admin-order' => "{$baseUrl}/admin/orders/{$orderId}?token={$token}",
            'provider-order' => "{$baseUrl}/provider/orders/{$orderId}?token={$token}",
            default => "{$baseUrl}/orders/{$orderId}?token={$token}",
        };
    }

    /**
     * Generate tracking pixel for email analytics
     */
    protected function generateTrackingPixel(string $orderId, string $event): string
    {
        $trackingId = base64_encode(json_encode([
            'order_id' => $orderId,
            'event' => $event,
            'timestamp' => now()->timestamp,
        ]));
        
        return "https://{$this->config['tracking_domain']}/pixel/{$trackingId}.gif";
    }

    /**
     * Log email for audit trail
     */
    protected function logEmail(ProductRequest $order, string $type, string $recipient): void
    {
        EmailLog::create([
            'order_id' => $order->id,
            'type' => $type,
            'recipient' => $recipient,
            'subject' => $this->getSubjectForStatus($type, $order->id),
            'sent_at' => now(),
            'provider' => 'mailgun',
            'metadata' => [
                'manufacturer_id' => $order->manufacturer_id,
                'provider_id' => $order->provider_id,
            ],
        ]);
    }
}
```

### 3. Email Templates with 2025 Best Practices

```blade
{{-- resources/views/emails/ivr-submission.blade.php --}}
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>IVR Submission - Order #{{ $order->id }}</title>
    
    <style>
        /* 2025 Email Best Practices */
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }
        
        @media (prefers-color-scheme: dark) {
            .email-body {
                background-color: #1a1a1a !important;
                color: #ffffff !important;
            }
            .card {
                background-color: #2a2a2a !important;
                border-color: #3a3a3a !important;
            }
        }
        
        /* Mobile-first responsive design */
        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                padding: 10px !important;
            }
            .button {
                width: 100% !important;
                text-align: center !important;
            }
        }
        
        /* Interactive elements */
        .button {
            background-color: #4F46E5;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        
        .button:hover {
            background-color: #4338CA;
        }
        
        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>
</head>
<body class="email-body" style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div role="article" aria-roledescription="email" aria-label="IVR Submission">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <table class="container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin: 0 auto;">
                        {{-- Header --}}
                        <tr>
                            <td style="padding: 20px; text-align: center; background-color: #4F46E5;">
                                <img src="{{ config('app.url') }}/images/logo-white.png" alt="MSC Wound Care" width="200" style="display: block; margin: 0 auto;">
                            </td>
                        </tr>
                        
                        {{-- Content --}}
                        <tr>
                            <td class="card" style="padding: 30px; background-color: #ffffff; border: 1px solid #e5e7eb;">
                                <h1 style="margin: 0 0 20px 0; font-size: 24px; color: #111827;">IVR Submission</h1>
                                
                                <p style="margin: 0 0 20px 0; color: #6b7280;">
                                    Please find attached the IVR for Order #{{ $order->id }}.
                                </p>
                                
                                {{-- Order Details --}}
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 30px 0;">
                                    <tr>
                                        <td style="padding: 10px; background-color: #f9fafb; border-radius: 6px;">
                                            <h2 style="margin: 0 0 10px 0; font-size: 18px; color: #111827;">Order Details</h2>
                                            <p style="margin: 0; color: #6b7280;">
                                                <strong>Provider:</strong> {{ $order->provider->name }}<br>
                                                <strong>Facility:</strong> {{ $order->facility->name }}<br>
                                                <strong>Service Date:</strong> {{ $order->expected_service_date->format('M d, Y') }}<br>
                                                <strong>Total Value:</strong> ${{ number_format($order->total_order_value, 2) }}
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                
                                {{-- CTA Button --}}
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td align="center" style="padding: 20px 0;">
                                            <a href="{{ $deepLink }}" class="button" style="background-color: #4F46E5; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600;">
                                                View Order Details
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                
                                {{-- Security Notice --}}
                                <p style="margin: 20px 0 0 0; font-size: 14px; color: #9ca3af; text-align: center;">
                                    This link will expire in 7 days for security purposes.
                                </p>
                            </td>
                        </tr>
                        
                        {{-- Footer --}}
                        <tr>
                            <td style="padding: 20px; text-align: center; color: #6b7280; font-size: 14px;">
                                <p style="margin: 0 0 10px 0;">
                                    MSC Wound Care Portal<br>
                                    <a href="{{ config('app.url') }}" style="color: #4F46E5; text-decoration: none;">{{ config('app.url') }}</a>
                                </p>
                                <p style="margin: 0; font-size: 12px;">
                                    <a href="{{ config('app.url') }}/unsubscribe?token={{ $unsubscribeToken }}" style="color: #9ca3af; text-decoration: underline;">Unsubscribe</a> |
                                    <a href="{{ config('app.url') }}/preferences?token={{ $preferencesToken }}" style="color: #9ca3af; text-decoration: underline;">Email Preferences</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    
    {{-- Tracking Pixel --}}
    <img src="{{ $trackingPixel }}" alt="" width="1" height="1" border="0" style="display: block;">
</body>
</html>
```

### 4. Webhook Handler for Email Events

```php
// app/Http/Controllers/Webhooks/MailgunWebhookController.php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\EmailEvent;
use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailgunWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }
        
        $eventData = $request->input('event-data');
        
        // Process event
        EmailEvent::create([
            'message_id' => $eventData['message']['headers']['message-id'],
            'event' => $eventData['event'],
            'recipient' => $eventData['recipient'],
            'timestamp' => $eventData['timestamp'],
            'metadata' => $eventData['user-variables'] ?? [],
            'raw_data' => $eventData,
        ]);
        
        // Handle specific events
        switch ($eventData['event']) {
            case 'delivered':
                $this->handleDelivered($eventData);
                break;
            case 'opened':
                $this->handleOpened($eventData);
                break;
            case 'clicked':
                $this->handleClicked($eventData);
                break;
            case 'failed':
                $this->handleFailed($eventData);
                break;
        }
        
        return response()->json(['status' => 'success']);
    }
    
    protected function verifyWebhookSignature(Request $request): bool
    {
        $token = $request->input('signature.token');
        $timestamp = $request->input('signature.timestamp');
        $signature = $request->input('signature.signature');
        
        $signingKey = config('services.mailgun.webhook_signing_secret');
        $encoded = hash_hmac('sha256', "{$timestamp}{$token}", $signingKey);
        
        return hash_equals($encoded, $signature);
    }
    
    protected function handleDelivered(array $eventData): void
    {
        if (isset($eventData['user-variables']['order_id'])) {
            $order = ProductRequest::find($eventData['user-variables']['order_id']);
            if ($order) {
                $order->update([
                    'last_email_delivered_at' => now(),
                ]);
            }
        }
    }
    
    protected function handleClicked(array $eventData): void
    {
        // Track deep link clicks
        Log::info('Email link clicked', [
            'recipient' => $eventData['recipient'],
            'url' => $eventData['url'],
            'order_id' => $eventData['user-variables']['order_id'] ?? null,
        ]);
    }
}
```

### 5. Queue Configuration for Reliability

```php
// app/Jobs/SendOrderNotification.php
<?php

namespace App\Jobs;

use App\Models\ProductRequest;
use App\Services\Notifications\MailgunNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min
    
    public function __construct(
        protected ProductRequest $order,
        protected string $type,
        protected array $data = []
    ) {}
    
    public function handle(MailgunNotificationService $service): void
    {
        match($this->type) {
            'ivr-sent' => $service->sendIvrToManufacturer($this->order, $this->data['pdf_path']),
            'status-update' => $service->sendProviderStatusUpdate($this->order, $this->data['status'], $this->data['reason'] ?? null),
            default => throw new \InvalidArgumentException("Unknown notification type: {$this->type}"),
        };
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send notification', [
            'order_id' => $this->order->id,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
        
        // Notify admin of failure
        // Could implement fallback notification method here
    }
}
```

### 6. Database Migrations

```php
// database/migrations/2024_XX_XX_create_email_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_requests');
            $table->string('type'); // ivr-sent, status-update, etc.
            $table->string('recipient');
            $table->string('subject');
            $table->string('provider')->default('mailgun');
            $table->timestamp('sent_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'type']);
            $table->index('recipient');
        });
        
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->string('message_id');
            $table->string('event'); // delivered, opened, clicked, failed
            $table->string('recipient');
            $table->integer('timestamp');
            $table->json('metadata')->nullable();
            $table->json('raw_data');
            $table->timestamps();
            
            $table->index('message_id');
            $table->index(['recipient', 'event']);
        });
    }
};
```

### 7. Integration with Order Workflow

```php
// app/Http/Controllers/Admin/OrderController.php (update existing)
public function sendIvrToManufacturer(ProductRequest $productRequest)
{
    // Generate IVR PDF
    $pdfPath = $this->ivrService->generateIvr($productRequest);
    
    // Queue email notification
    SendOrderNotification::dispatch($productRequest, 'ivr-sent', [
        'pdf_path' => $pdfPath,
    ]);
    
    // Update order status
    $productRequest->update([
        'order_status' => 'ivr_sent',
        'ivr_sent_at' => now(),
    ]);
    
    return response()->json([
        'message' => 'IVR sent to manufacturer successfully',
    ]);
}
```

### 8. Testing Suite

```php
// tests/Feature/MailgunNotificationTest.php
<?php

namespace Tests\Feature;

use App\Jobs\SendOrderNotification;
use App\Models\ProductRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailgunNotificationTest extends TestCase
{
    public function test_ivr_email_sent_to_manufacturer()
    {
        Queue::fake();
        
        $order = ProductRequest::factory()->create([
            'order_status' => 'approved',
        ]);
        
        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/send-ivr");
        
        $response->assertOk();
        
        Queue::assertPushed(SendOrderNotification::class, function ($job) use ($order) {
            return $job->order->id === $order->id && $job->type === 'ivr-sent';
        });
    }
    
    public function test_deep_links_are_generated_correctly()
    {
        $service = new MailgunNotificationService();
        $order = ProductRequest::factory()->create();
        
        $deepLink = $service->generateDeepLink('admin-order', $order->id);
        
        $this->assertStringContainsString("/admin/orders/{$order->id}", $deepLink);
        $this->assertStringContainsString("token=", $deepLink);
    }
}
```

## 📋 Implementation Checklist

### Phase 1: Core Setup (Day 1)
- [ ] Configure Mailgun account and domain
- [ ] Update .env with Mailgun credentials
- [ ] Create email log tables migration
- [ ] Implement MailgunNotificationService
- [ ] Set up webhook endpoint

### Phase 2: Email Templates (Day 2)
- [ ] Create responsive email templates
- [ ] Implement dark mode support
- [ ] Add accessibility features
- [ ] Create unsubscribe/preferences system
- [ ] Test across email clients

### Phase 3: Integration (Day 3)
- [ ] Update order workflow controllers
- [ ] Implement queue jobs
- [ ] Add deep link generation
- [ ] Set up tracking pixels
- [ ] Configure webhook processing

### Phase 4: Testing & Monitoring (Day 4)
- [ ] Write comprehensive tests
- [ ] Set up email analytics dashboard
- [ ] Configure bounce/complaint handling
- [ ] Implement retry logic
- [ ] Add monitoring alerts

## 🎯 2025 Best Practices Implemented

1. **Dark Mode Support**: Automatic adaptation to user preferences
2. **Mobile-First Design**: Optimized for mobile email clients
3. **Accessibility**: ARIA labels, semantic HTML, proper contrast
4. **Security**: JWT tokens for deep links with expiration
5. **Privacy**: GDPR-compliant unsubscribe/preferences
6. **Analytics**: Comprehensive tracking without compromising privacy
7. **Reliability**: Queue-based sending with retry logic
8. **Performance**: Optimized images, minimal CSS
9. **Deliverability**: SPF, DKIM, DMARC configuration
10. **User Experience**: One-click actions, clear CTAs

This implementation provides a robust, modern notification system that aligns with your streamlined workflow while maintaining security and compliance standards.

Similar code found with 4 license types