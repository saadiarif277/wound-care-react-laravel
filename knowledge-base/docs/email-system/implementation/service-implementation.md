# Mailgun Service Implementation

## Overview

The `MailgunNotificationService` is the core service class that handles all email notifications in the MSC Wound Care Portal. It provides a unified interface for sending different types of notifications while maintaining consistent branding and tracking.

## Service Architecture

```php
<?php
// app/Services/Notifications/MailgunNotificationService.php

namespace App\Services\Notifications;

use App\Models\EmailLog;
use App\Models\ProductRequest;
use App\Models\User;
use App\Models\Provider;
use App\Models\Manufacturer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    
    // ... methods documented below
}
```

## Core Methods

### 1. User Management Notifications

#### sendUserInvitation()
Sends welcome email to new users with login credentials.

```php
/**
 * Send user invitation email
 */
public function sendUserInvitation(User $user, string $temporaryPassword): void
{
    $deepLink = $this->generateDeepLink('onboarding', $user->id);
    
    Mail::send('emails.user-invitation', [
        'user' => $user,
        'temporaryPassword' => $temporaryPassword,
        'deepLink' => $deepLink,
        'trackingPixel' => $this->generateTrackingPixel($user->id, 'invitation-sent'),
    ], function ($message) use ($user) {
        $message->to($user->email)
                ->subject("🚀 You're Invited to Join the MSC Wound Care Platform 🚀")
                ->getHeaders()
                ->addTextHeader('X-Mailgun-Tag', 'user-invitation')
                ->addTextHeader('X-Mailgun-Variables', json_encode([
                    'user_id' => $user->id,
                    'user_type' => $user->user_type,
                ]));
    });
    
    $this->logEmail(null, 'user-invitation', $user->email);
}
```

**Usage:**
```php
$service = app(MailgunNotificationService::class);
$service->sendUserInvitation($user, 'TempPass123');
```

### 2. Order Management Notifications

#### sendOrderSubmittedToAdmin()
Notifies all admin users when a new order is submitted.

```php
/**
 * Send order request submitted notification to admin
 */
public function sendOrderSubmittedToAdmin(ProductRequest $order): void
{
    $admins = User::where('user_type', 'Admin')->get();
    $deepLink = $this->generateDeepLink('admin-order', $order->id);
    
    foreach ($admins as $admin) {
        if (!$this->shouldSendNotification($admin)) continue;
        
        Mail::send('emails.order-submitted-admin', [
            'order' => $order,
            'provider' => $order->provider,
            'deepLink' => $deepLink,
            'trackingPixel' => $this->generateTrackingPixel($order->id, 'order-submitted-admin'),
        ], function ($message) use ($admin, $order) {
            $message->to($admin->email)
                    ->subject("📝 New Order Request Submitted by {$order->provider->name}")
                    ->getHeaders()
                    ->addTextHeader('X-Mailgun-Tag', 'order-submitted')
                    ->addTextHeader('X-Mailgun-Variables', json_encode([
                        'order_id' => $order->id,
                        'provider_id' => $order->provider_id,
                    ]));
        });
    }
    
    $this->logEmail($order, 'order-submitted-admin', 'admins');
}
```

#### sendIvrToManufacturer()
Sends IVR document to manufacturer with PDF attachment.

```php
/**
 * Send IVR to manufacturer with deep link
 */
public function sendIvrToManufacturer(ProductRequest $order, string $pdfPath): void
{
    $manufacturer = $order->manufacturer;
    
    if (!$manufacturer->ivr_email) {
        Log::warning("No IVR email configured for manufacturer: {$manufacturer->name}");
        return;
    }
    
    $deepLink = $this->generateDeepLink('manufacturer-order', $order->id);
    
    Mail::send('emails.ivr-submission', [
        'order' => $order,
        'manufacturer' => $manufacturer,
        'deepLink' => $deepLink,
        'trackingPixel' => $this->generateTrackingPixel($order->id, 'ivr-sent'),
    ], function ($message) use ($manufacturer, $order, $pdfPath) {
        $message->to($manufacturer->ivr_email);
        
        // Add CC recipients if configured
        if ($manufacturer->ivr_cc_emails) {
            $message->cc($manufacturer->ivr_cc_emails);
        }
        
        $message->subject("📄 IVR Request for MSC Order #{$order->id}")
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
```

#### Provider Status Notifications
Methods for notifying providers of order status changes.

```php
/**
 * Send order approval notification to provider
 */
public function sendOrderApprovedToProvider(ProductRequest $order): void
{
    $provider = $order->provider;
    
    // Check if provider wants approval notifications
    $preferences = $provider->user->notification_preferences ?? [];
    if (!($preferences['order_approved'] ?? true)) {
        return;
    }
    
    $deepLink = $this->generateDeepLink('provider-order', $order->id);
    
    Mail::send('emails.order-approved', [
        'order' => $order,
        'provider' => $provider,
        'deepLink' => $deepLink,
        'trackingPixel' => $this->generateTrackingPixel($order->id, 'order-approved'),
    ], function ($message) use ($provider, $order) {
        $message->to($provider->email)
                ->subject("✅ MSC Woundcare Order #{$order->id} Approved")
                ->getHeaders()
                ->addTextHeader('X-Mailgun-Tag', 'order-approved')
                ->addTextHeader('X-Mailgun-Variables', json_encode([
                    'order_id' => $order->id,
                    'provider_id' => $provider->id,
                ]));
    });
    
    $this->logEmail($order, 'order-approved', $provider->email);
}

/**
 * Send order denied notification to provider
 */
public function sendOrderDeniedToProvider(ProductRequest $order, string $reason): void
{
    $provider = $order->provider;
    $deepLink = $this->generateDeepLink('provider-order', $order->id);
    
    Mail::send('emails.order-denied', [
        'order' => $order,
        'provider' => $provider,
        'reason' => $reason,
        'deepLink' => $deepLink,
        'trackingPixel' => $this->generateTrackingPixel($order->id, 'order-denied'),
    ], function ($message) use ($provider, $order) {
        $message->to($provider->email)
                ->subject("❌ MSC Order #{$order->id} Denied")
                ->priority(1) // High priority
                ->getHeaders()
                ->addTextHeader('X-Mailgun-Tag', 'order-denied')
                ->addTextHeader('X-Mailgun-Variables', json_encode([
                    'order_id' => $order->id,
                    'provider_id' => $provider->id,
                ]));
    });
    
    $this->logEmail($order, 'order-denied', $provider->email);
}

/**
 * Send order sent back notification to provider
 */
public function sendOrderSentBackToProvider(ProductRequest $order, string $reason): void
{
    $provider = $order->provider;
    $deepLink = $this->generateDeepLink('provider-order-edit', $order->id);
    
    Mail::send('emails.order-sent-back', [
        'order' => $order,
        'provider' => $provider,
        'reason' => $reason,
        'deepLink' => $deepLink,
        'actionButton' => [
            'text' => 'Edit Order',
            'url' => $deepLink,
        ],
        'trackingPixel' => $this->generateTrackingPixel($order->id, 'order-sent-back'),
    ], function ($message) use ($provider, $order) {
        $message->to($provider->email)
                ->subject("🔄 MSC Order #{$order->id} Sent Back for Edits")
                ->priority(1) // High priority
                ->getHeaders()
                ->addTextHeader('X-Mailgun-Tag', 'order-sent-back')
                ->addTextHeader('X-Mailgun-Variables', json_encode([
                    'order_id' => $order->id,
                    'provider_id' => $provider->id,
                ]));
    });
    
    $this->logEmail($order, 'order-sent-back', $provider->email);
}
```

### 3. Support System Notifications

#### sendHelpRequest()
Forwards help requests from providers to admin team.

```php
/**
 * Send help request notification
 */
public function sendHelpRequest(Provider $provider, string $subject, string $message): void
{
    $admins = User::where('user_type', 'Admin')->get();
    
    foreach ($admins as $admin) {
        if (!$this->shouldSendNotification($admin)) continue;
        
        Mail::send('emails.help-request', [
            'provider' => $provider,
            'helpSubject' => $subject,
            'helpMessage' => $message,
            'trackingPixel' => $this->generateTrackingPixel($provider->id, 'help-request'),
        ], function ($mailMessage) use ($admin, $provider) {
            $mailMessage->to($admin->email)
                        ->subject("MSC Support Requested by {$provider->name}")
                        ->replyTo($provider->email, $provider->name)
                        ->priority(1) // High priority
                        ->getHeaders()
                        ->addTextHeader('X-Mailgun-Tag', 'help-request')
                        ->addTextHeader('X-Mailgun-Variables', json_encode([
                            'provider_id' => $provider->id,
                        ]));
        });
    }
    
    $this->logEmail(null, 'help-request', 'admins');
}
```

## Utility Methods

### Deep Link Generation
Generates secure, expiring deep links for email actions.

```php
/**
 * Generate secure deep link with JWT token
 */
protected function generateDeepLink(string $type, string $id): string
{
    $token = $this->generateSecureToken([
        'type' => $type,
        'id' => $id,
        'exp' => now()->addDays(7)->timestamp,
    ]);
    
    $baseUrl = config('app.url');
    
    return match($type) {
        'onboarding' => "{$baseUrl}/onboarding?token={$token}",
        'admin-order' => "{$baseUrl}/admin/orders/{$id}?token={$token}",
        'provider-order' => "{$baseUrl}/provider/orders/{$id}?token={$token}",
        'provider-order-edit' => "{$baseUrl}/provider/orders/{$id}/edit?token={$token}",
        'manufacturer-order' => "{$baseUrl}/manufacturer/orders/{$id}?token={$token}",
        default => "{$baseUrl}/orders/{$id}?token={$token}",
    };
}

/**
 * Generate JWT token for deep links
 */
protected function generateSecureToken(array $payload): string
{
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, config('app.key'), true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}
```

### Analytics and Tracking

```php
/**
 * Generate tracking pixel for email analytics
 */
protected function generateTrackingPixel(string $id, string $event): string
{
    $trackingId = base64_encode(json_encode([
        'id' => $id,
        'event' => $event,
        'timestamp' => now()->timestamp,
    ]));
    
    return "https://{$this->config['tracking_domain']}/pixel/{$trackingId}.gif";
}

/**
 * Log email for audit trail
 */
protected function logEmail(?ProductRequest $order, string $type, string $recipient): void
{
    EmailLog::create([
        'order_id' => $order?->id,
        'type' => $type,
        'recipient' => $recipient,
        'subject' => $this->getSubjectForType($type, $order?->id),
        'sent_at' => now(),
        'provider' => 'mailgun',
        'metadata' => [
            'manufacturer_id' => $order?->manufacturer_id,
            'provider_id' => $order?->provider_id,
        ],
    ]);
}
```

### Notification Preferences

```php
/**
 * Check if notification should be sent to user
 */
protected function shouldSendNotification(User $user): bool
{
    return $user->email_notifications_enabled ?? true;
}

/**
 * Get email subject based on type
 */
protected function getSubjectForType(string $type, ?string $orderId = null): string
{
    return match($type) {
        'user-invitation' => "🚀 You're Invited to Join the MSC Wound Care Platform 🚀",
        'order-submitted-admin' => "📝 New Order Request Submitted",
        'ivr-sent' => "📄 IVR Request for MSC Order #{$orderId}",
        'order-approved' => "✅ MSC Woundcare Order #{$orderId} Approved",
        'order-denied' => "❌ MSC Order #{$orderId} Denied",
        'order-sent-back' => "🔄 MSC Order #{$orderId} Sent Back for Edits",
        'order-fulfilled' => "📦 Order #{$orderId} Fulfilled",
        'help-request' => "MSC Support Requested",
        default => "MSC Wound Care Portal Notification",
    };
}
```

## Service Registration

### Laravel Service Provider
Register the service in your application:

```php
// app/Providers/AppServiceProvider.php
public function register()
{
    $this->app->singleton(MailgunNotificationService::class, function ($app) {
        return new MailgunNotificationService();
    });
}
```

### Dependency Injection
Use the service in controllers:

```php
// app/Http/Controllers/Admin/ProductRequestController.php
public function __construct(
    protected MailgunNotificationService $notificationService
) {}
```

## Error Handling

### Exception Handling
```php
try {
    $this->notificationService->sendOrderApprovedToProvider($order);
} catch (\Exception $e) {
    Log::error('Failed to send approval notification', [
        'order_id' => $order->id,
        'error' => $e->getMessage(),
    ]);
    
    // Optional: Send fallback notification or alert admin
}
```

### Graceful Degradation
```php
public function sendIvrToManufacturer(ProductRequest $order, string $pdfPath): void
{
    $manufacturer = $order->manufacturer;
    
    // Gracefully handle missing email configuration
    if (!$manufacturer->ivr_email) {
        Log::warning("No IVR email configured for manufacturer: {$manufacturer->name}");
        
        // Could notify admin or use fallback method
        $this->notifyAdminOfMissingManufacturerEmail($manufacturer);
        return;
    }
    
    // Continue with email sending...
}
```

## Testing the Service

### Unit Testing
```php
// tests/Unit/MailgunNotificationServiceTest.php
public function test_generates_secure_deep_links()
{
    $service = new MailgunNotificationService();
    $deepLink = $service->generateDeepLink('admin-order', '123');
    
    $this->assertStringContainsString('/admin/orders/123', $deepLink);
    $this->assertStringContainsString('token=', $deepLink);
}

public function test_respects_user_notification_preferences()
{
    $user = User::factory()->create([
        'email_notifications_enabled' => false,
    ]);
    
    Mail::fake();
    
    $service = new MailgunNotificationService();
    // Should not send email due to user preference
    
    Mail::assertNothingSent();
}
```

### Integration Testing
```php
// tests/Feature/EmailNotificationTest.php
public function test_order_approval_sends_email_to_provider()
{
    Queue::fake();
    
    $order = ProductRequest::factory()->create();
    
    $response = $this->actingAs($this->admin)
        ->post("/api/admin/orders/{$order->id}/approve");
    
    Queue::assertPushed(SendOrderNotification::class);
}
```

## Performance Considerations

### Queue Usage
Always use queues for email sending to avoid blocking requests:

```php
// Use via queue job
SendOrderNotification::dispatch($order, 'order-approved');

// Direct usage (not recommended for web requests)
$this->notificationService->sendOrderApprovedToProvider($order);
```

### Batch Processing
For bulk notifications:

```php
public function sendBulkNotifications(Collection $users, string $template, array $data): void
{
    $users->chunk(50)->each(function ($chunk) use ($template, $data) {
        dispatch(new SendBulkEmailNotification($chunk, $template, $data))
            ->onQueue('bulk-emails');
    });
}
```

---

**Last Updated**: August 4, 2025  
**Next**: [Database Schema](database-schema.md)
