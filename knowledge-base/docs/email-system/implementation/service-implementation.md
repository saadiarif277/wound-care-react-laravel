# Email Notification Service Implementation

## Overview

The `EmailNotificationService` is a comprehensive Laravel service that handles all email notifications for the MSC Wound Care Portal. It provides a unified interface for sending notifications with consistent formatting, logging, and error handling.

## Service Location

- **File**: `app/Services/EmailNotificationService.php`
- **Namespace**: `App\Services`
- **Dependencies**: Mail facade, Laravel Log, User models

## Core Features

### 🔧 Service Infrastructure
- **Queue Integration**: All emails can be queued for asynchronous processing
- **Error Handling**: Comprehensive try-catch blocks with detailed logging
- **Template Engine**: Uses Blade templates with consistent variable passing
- **Deep Link Generation**: JWT-based secure links to order details
- **Status Tracking**: Integration with email delivery status monitoring

### 📧 Email Configuration
- **Provider**: Configured for Mailgun integration
- **Templates**: Responsive, accessible HTML templates
- **Personalization**: Dynamic content based on recipient and context
- **Branding**: Consistent MSC visual identity across all templates

## Available Notification Methods

### 1. User Onboarding
#### `sendUserInvitation(User $user, string $role): bool`
**Purpose**: Welcome new users to the platform  
**Triggers**: New user account creation, role assignment  
**Recipients**: New user  
**Template**: `emails.provider-invitation`  
**Variables**: `user`, `first_name`, `role`, `login_url`  
**Subject**: "🚀 You're Invited to Join the MSC Wound Care Platform 🚀"

### 2. Order Submission Notifications
#### `sendOrderSubmittedToAdmin(ProductRequest $order, ?string $comments = null): bool`
**Purpose**: Notify admins of new order submissions  
**Triggers**: Provider submits new order request  
**Recipients**: All admin users  
**Template**: `emails.order.new-order-admin`  
**Variables**: `order`, `order_id`, `provider_name`, `date`, `comment`, `order_link`, `submission_type`  
**Subject**: "📝 MSC: New Order Request Submitted by {provider_name}"

#### `sendOrderFormSubmittedToAdmin(ProductRequest $order, ?string $comments = null): bool`
**Purpose**: Notify admins of order form submissions  
**Triggers**: Provider completes and submits order form  
**Recipients**: All admin users  
**Template**: `emails.order.new-order-admin`  
**Variables**: `order`, `order_id`, `provider_name`, `date`, `comment`, `order_link`, `submission_type`  
**Subject**: "📝 MSC: New Order Form Submitted by {provider_name}"

### 3. IVR Status Notifications
#### `sendIvrVerifiedToProvider(ProductRequest $order, ?string $comments = null): bool`
**Purpose**: Notify provider that IVR verification is complete  
**Triggers**: Admin marks IVR as verified in system  
**Recipients**: Requesting provider  
**Template**: `emails.order.status-update-provider`  
**Variables**: `order`, `order_id`, `manufacturer_name`, `admin_name`, `comments`, `order_link`, `status_type`, `status_emoji`  
**Subject**: "✅ MSC: IVR Verification Complete for Order #{order_id}"

#### `sendIvrSentBackToProvider(ProductRequest $order, string $reason, ?string $comments = null): bool`
**Purpose**: Notify provider that IVR was sent back for revision  
**Triggers**: Admin marks IVR as requiring revision  
**Recipients**: Requesting provider  
**Template**: `emails.order.status-update-provider`  
**Variables**: `order`, `order_id`, `provider_name`, `manufacturer_name`, `denial_reason`, `comments`, `order_link`, `status_type`, `status_emoji`  
**Subject**: "❌ MSC: IVR Sent Back - #{order_id}"

### 4. Order Processing Notifications
#### `sendOrderSubmittedToManufacturerNotification(ProductRequest $order, ?string $comments = null): bool`
**Purpose**: Notify provider that order was submitted to manufacturer  
**Triggers**: Admin submits order to manufacturer  
**Recipients**: Requesting provider  
**Template**: `emails.order.status-update-provider`  
**Variables**: `order`, `order_id`, `manufacturer_name`, `admin_name`, `comments`, `order_link`, `status_type`, `status_emoji`  
**Subject**: "✅ MSC: Order Submitted to Manufacturer - Order #{order_id}"

#### `sendManufacturerConfirmationToProvider(ProductRequest $order, ?string $comments = null): bool`
**Purpose**: Notify provider that manufacturer confirmed the order  
**Triggers**: Manufacturer confirms order receipt and processing  
**Recipients**: Requesting provider  
**Template**: `emails.order.status-update-provider`  
**Variables**: `order`, `order_id`, `provider_name`, `manufacturer_name`, `admin_name`, `comments`, `order_link`, `status_type`, `status_emoji`  
**Subject**: "📦 MSC: Order Confirmed by Manufacturer - Order #{order_id}"

#### `sendOrderDeniedToProvider(ProductRequest $order, string $reason): bool`
**Purpose**: Notify provider that order was denied  
**Triggers**: Admin or system denies order request  
**Recipients**: Requesting provider  
**Template**: `emails.order.status-update-provider`  
**Variables**: `order`, `order_id`, `denial_reason`, `order_link`, `status_type`, `status_emoji`  
**Subject**: "❌ MSC Order Denied - #{order_id}"

### 5. Support & Help
#### `sendHelpRequest(Provider $provider, string $comment): bool`
**Purpose**: Notify admins of provider support requests  
**Triggers**: Provider submits help/support request through portal  
**Recipients**: All admin users  
**Template**: `emails.admin.help-request`  
**Variables**: `provider`, `provider_name`, `provider_email`, `comment`  
**Subject**: "MSC Support Requested by {provider_name}"  
**Special**: Configured with reply-to provider email

## Helper Methods

### `getOrderRequestor(ProductRequest $order): ?User`
**Purpose**: Safely retrieve the user who submitted an order  
**Returns**: User object or null if not found  
**Usage**: Internal helper for notification methods

### `getNotificationStats(ProductRequest $order): array`
**Purpose**: Generate notification statistics for an order  
**Returns**: Array with counts of total, pending, sent, delivered, failed, and recent notifications  
**Usage**: Dashboard analytics and reporting

## Error Handling & Logging

### Logging Strategy
```php
// Success logging
Log::info('Notification sent successfully', [
    'type' => 'notification_type',
    'order_id' => $order->id,
    'recipient' => $recipient->email
]);

// Error logging
Log::error('Failed to send notification', [
    'type' => 'notification_type',
    'order_id' => $order->id,
    'error' => $exception->getMessage()
]);
```

### Exception Handling
- All notification methods use try-catch blocks
- Graceful degradation when recipients are not found
- Boolean return values for easy status checking
- Detailed error logging for debugging

## Template Variables Reference

### Common Variables (Available in Most Templates)
- `order_id`: Unique order identifier
- `order_link`: Deep link to order details page
- `provider_name`: Name of the requesting provider
- `manufacturer_name`: Name of the order's manufacturer
- `admin_name`: Name of the admin performing actions
- `comments`: Additional notes or instructions
- `date`: Formatted submission/update date

### Status-Specific Variables
- `status_type`: Human-readable status description
- `status_emoji`: Visual indicator for status
- `denial_reason`: Reason for order/IVR denial
- `submission_type`: Type of submission (Order Request vs Order Form)

### User-Specific Variables
- `user`: Full user object
- `first_name`: User's first name
- `role`: User's role in the system
- `login_url`: Link to login page

## Usage Examples

### Basic Notification
```php
use App\Services\EmailNotificationService;

$emailService = new EmailNotificationService();
$success = $emailService->sendOrderSubmittedToAdmin($order, $comments);

if ($success) {
    flash('Notification sent successfully');
} else {
    flash('Failed to send notification', 'error');
}
```

### With Error Handling
```php
try {
    $emailService = new EmailNotificationService();
    
    if ($emailService->sendIvrVerifiedToProvider($order, $comments)) {
        $order->update(['notification_sent' => true]);
        return response()->json(['success' => true]);
    } else {
        return response()->json(['error' => 'Failed to send notification'], 500);
    }
} catch (Exception $e) {
    Log::error('Notification error', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'System error'], 500);
}
```

### Bulk Notifications
```php
$emailService = new EmailNotificationService();
$results = [];

foreach ($orders as $order) {
    $results[] = [
        'order_id' => $order->id,
        'success' => $emailService->sendOrderSubmittedToManufacturerNotification($order)
    ];
}

$successCount = count(array_filter($results, fn($r) => $r['success']));
Log::info("Sent {$successCount} notifications successfully");
```

## Integration Points

### Controller Integration
```php
// In OrderController
public function submitToManufacturer(ProductRequest $order, Request $request)
{
    $order->update(['status' => 'submitted_to_manufacturer']);
    
    $emailService = new EmailNotificationService();
    $emailService->sendOrderSubmittedToManufacturerNotification(
        $order, 
        $request->input('comments')
    );
    
    return redirect()->back()->with('success', 'Order submitted and provider notified');
}
```

### Queue Integration
```php
// For high-volume notifications
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, SerializesModels;
    
    public function handle()
    {
        $emailService = new EmailNotificationService();
        $emailService->sendOrderSubmittedToAdmin($this->order, $this->comments);
    }
}
```

### Event Integration
```php
// In EventServiceProvider
protected $listen = [
    'App\Events\OrderStatusChanged' => [
        'App\Listeners\SendOrderStatusNotification',
    ],
];

// In SendOrderStatusNotification listener
public function handle(OrderStatusChanged $event)
{
    $emailService = new EmailNotificationService();
    
    match($event->newStatus) {
        'ivr_verified' => $emailService->sendIvrVerifiedToProvider($event->order),
        'manufacturer_confirmed' => $emailService->sendManufacturerConfirmationToProvider($event->order),
        'denied' => $emailService->sendOrderDeniedToProvider($event->order, $event->reason),
        default => null
    };
}
```

## Best Practices

### 1. **Always Check Return Values**
```php
if (!$emailService->sendNotification($order)) {
    // Handle failure case
    Log::warning('Notification failed, manual follow-up required');
}
```

### 2. **Use Descriptive Comments**
```php
$comments = "Order expedited due to urgent patient need. Priority processing requested.";
$emailService->sendOrderSubmittedToAdmin($order, $comments);
```

### 3. **Validate Recipients**
```php
if (!$provider || !$provider->email) {
    Log::warning('Cannot send notification - invalid recipient');
    return false;
}
```

### 4. **Consistent Error Handling**
```php
try {
    return $emailService->sendNotification($data);
} catch (Exception $e) {
    Log::error('Notification system error', [
        'method' => __METHOD__,
        'error' => $e->getMessage(),
        'context' => $data
    ]);
    return false;
}
```

## Testing

### Unit Tests
```php
// Test successful notification
public function test_sends_ivr_verified_notification()
{
    Mail::fake();
    
    $service = new EmailNotificationService();
    $result = $service->sendIvrVerifiedToProvider($this->order);
    
    $this->assertTrue($result);
    Mail::assertSent(function ($mail) {
        return $mail->hasTo($this->provider->email) &&
               str_contains($mail->subject, 'IVR Verification Complete');
    });
}

// Test error handling
public function test_handles_missing_recipient_gracefully()
{
    $service = new EmailNotificationService();
    $orderWithoutProvider = ProductRequest::factory()->create(['provider_id' => null]);
    
    $result = $service->sendIvrVerifiedToProvider($orderWithoutProvider);
    
    $this->assertFalse($result);
}
```

### Manual Testing
1. **Use the email preview system** in the knowledge base
2. **Test with real email addresses** in development
3. **Verify deep links** work correctly
4. **Check mobile responsiveness** of templates
5. **Test with various data scenarios** (missing fields, long text, etc.)

## Maintenance

### Adding New Notifications
1. **Add method to EmailNotificationService**
2. **Create or update Blade template**
3. **Add to this documentation**
4. **Create unit tests**
5. **Update preview system**

### Template Updates
1. **Maintain accessibility standards**
2. **Test mobile responsiveness**
3. **Preserve existing variable contracts**
4. **Update preview files**

### Performance Monitoring
- Monitor email delivery rates
- Track notification success/failure rates
- Log timing for performance optimization
- Review queue processing times
