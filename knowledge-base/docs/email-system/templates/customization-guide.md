# Template Customization Guide

This comprehensive guide covers how to customize, extend, and maintain the MSC Wound Care Portal email templates to meet specific business requirements and branding needs.

## Table of Contents

1. [Customization Overview](#customization-overview)
2. [Brand Customization](#brand-customization)
3. [Content Customization](#content-customization)
4. [Layout Modifications](#layout-modifications)
5. [Advanced Features](#advanced-features)
6. [Testing & Validation](#testing--validation)
7. [Best Practices](#best-practices)

## Customization Overview

### Template Architecture

Our email system follows a modular architecture:

```
resources/views/emails/
├── layout.blade.php           # Master template
├── components/                # Reusable components
│   ├── header.blade.php
│   ├── footer.blade.php
│   ├── button.blade.php
│   └── info-box.blade.php
├── order-*.blade.php          # Order-specific templates
├── user-*.blade.php           # User management templates
└── system-*.blade.php         # System notifications
```

### Customization Levels

1. **Basic**: Colors, fonts, logo
2. **Intermediate**: Layout modifications, new components
3. **Advanced**: Dynamic content, conditional logic, integrations

## Brand Customization

### 1. Color Scheme Modification

**File**: `resources/views/emails/layout.blade.php`

Update the CSS variables in the `<style>` section:

```css
:root {
    /* Primary Brand Colors */
    --msc-blue: #1e40af;           /* Main brand blue */
    --msc-light-blue: #dbeafe;     /* Light blue backgrounds */
    --msc-red: #dc2626;            /* Error/alert red */
    --msc-orange: #ea580c;         /* Warning orange */
    --msc-green: #16a34a;          /* Success green */
    
    /* Custom Brand Extensions */
    --secondary-color: #6366f1;    /* Your secondary brand color */
    --accent-color: #f59e0b;       /* Your accent color */
    --neutral-gray: #6b7280;       /* Neutral text color */
    
    /* Background Colors */
    --bg-primary: #ffffff;         /* Main background */
    --bg-secondary: #f8fafc;       /* Secondary background */
    --bg-light: #f1f5f9;           /* Light background */
    
    /* Text Colors */
    --text-primary: #111827;       /* Primary text */
    --text-secondary: #4b5563;     /* Secondary text */
    --text-muted: #9ca3af;         /* Muted text */
}
```

### 2. Logo Customization

Replace the logo in the header section:

```blade
<!-- In the header section -->
<div style="text-align: center; margin-bottom: 30px;">
    <img src="{{ asset('images/your-custom-logo.png') }}" 
         alt="Your Company Name" 
         style="max-width: 200px; height: auto;" />
</div>
```

### 3. Typography Changes

Modify font families and sizes:

```css
/* Add to the CSS section */
body {
    font-family: 'Your Custom Font', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 16px;
    line-height: 1.6;
}

h1 {
    font-family: 'Your Heading Font', Georgia, serif;
    font-size: 28px;
    font-weight: 700;
}
```

### 4. Custom Branding Components

Create a custom header component:

**File**: `resources/views/emails/components/custom-header.blade.php`

```blade
<div style="background: linear-gradient(135deg, var(--msc-blue) 0%, var(--secondary-color) 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
    <img src="{{ asset('images/logo-white.png') }}" 
         alt="{{ config('app.name') }}" 
         style="max-width: 180px; height: auto; margin-bottom: 15px;" />
    
    <h2 style="color: white; margin: 0; font-size: 18px; font-weight: 300;">
        {{ $headerSubtitle ?? 'Advanced Wound Care Solutions' }}
    </h2>
    
    @if(isset($headerBadge))
        <div style="margin-top: 15px;">
            <span style="background-color: rgba(255,255,255,0.2); color: white; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                {{ $headerBadge }}
            </span>
        </div>
    @endif
</div>
```

## Content Customization

### 1. Dynamic Content Blocks

Create reusable content blocks:

**File**: `resources/views/emails/components/dynamic-content.blade.php`

```blade
@if($contentType === 'order-summary')
    <div class="info-box">
        <h3>Order Summary</h3>
        <table class="data-table">
            <tr>
                <td><strong>Order ID:</strong></td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td><strong>Total Value:</strong></td>
                <td>${{ number_format($order->total_value, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>{{ ucfirst($order->status) }}</td>
            </tr>
        </table>
    </div>
@elseif($contentType === 'user-info')
    <div class="info-box">
        <h3>Account Information</h3>
        <p><strong>Name:</strong> {{ $user->full_name }}</p>
        <p><strong>Role:</strong> {{ ucfirst($user->role) }}</p>
        <p><strong>Facility:</strong> {{ $user->facility->name ?? 'N/A' }}</p>
    </div>
@endif
```

### 2. Conditional Content

Add role-based content:

```blade
@if($user->role === 'provider')
    <div class="info-box success">
        <h3>Provider Resources</h3>
        <ul>
            <li><a href="{{ route('provider.dashboard') }}">Provider Dashboard</a></li>
            <li><a href="{{ route('orders.create') }}">Submit New Order</a></li>
            <li><a href="{{ route('training.materials') }}">Training Materials</a></li>
        </ul>
    </div>
@elseif($user->role === 'admin')
    <div class="info-box">
        <h3>Admin Tools</h3>
        <ul>
            <li><a href="{{ route('admin.orders') }}">Manage Orders</a></li>
            <li><a href="{{ route('admin.users') }}">User Management</a></li>
            <li><a href="{{ route('admin.reports') }}">Analytics & Reports</a></li>
        </ul>
    </div>
@endif
```

### 3. Multilingual Support

Create language-specific templates:

**File**: `resources/views/emails/order-approved-es.blade.php` (Spanish)

```blade
@extends('emails.layout')

@section('title', 'Orden Aprobada - MSC Cuidado de Heridas')
@section('aria-label', 'Notificación de aprobación de orden')

@section('content')
    <h1 style="color: #10b981;">✅ Solicitud de Orden Aprobada</h1>
    
    <p>Hola {{ $provider->name }},</p>
    
    <p>¡Excelentes noticias! Su solicitud de orden ha sido aprobada y está lista para proceder.</p>
    
    <!-- Rest of Spanish content -->
@endsection
```

## Layout Modifications

### 1. Responsive Design Enhancements

Add mobile-specific styles:

```css
/* Add to CSS section */
@media only screen and (max-width: 600px) {
    .email-container {
        width: 100% !important;
        padding: 10px !important;
    }
    
    .data-table {
        font-size: 12px !important;
    }
    
    .button {
        display: block !important;
        width: 100% !important;
        margin: 10px 0 !important;
    }
    
    h1 {
        font-size: 22px !important;
    }
    
    .info-box {
        padding: 15px !important;
        margin: 15px 0 !important;
    }
}
```

### 2. Custom Layout Components

Create a two-column layout component:

**File**: `resources/views/emails/components/two-column.blade.php`

```blade
<div style="display: table; width: 100%; margin: 20px 0;">
    <div style="display: table-cell; width: 48%; vertical-align: top; padding-right: 2%;">
        {{ $leftColumn }}
    </div>
    <div style="display: table-cell; width: 48%; vertical-align: top; padding-left: 2%;">
        {{ $rightColumn }}
    </div>
</div>

<!-- Mobile fallback -->
<style>
    @media only screen and (max-width: 600px) {
        .two-column-table {
            display: block !important;
        }
        .two-column-cell {
            display: block !important;
            width: 100% !important;
            padding: 0 !important;
            margin-bottom: 20px !important;
        }
    }
</style>
```

### 3. Advanced Button Styles

Create custom button variants:

```blade
{{-- Primary CTA Button --}}
<a href="{{ $link }}" class="button button-primary button-large" style="
    background: linear-gradient(135deg, var(--msc-blue) 0%, var(--secondary-color) 100%);
    border: none;
    color: white;
    padding: 18px 36px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 18px;
    display: inline-block;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
">
    {{ $buttonText }}
</a>

{{-- Secondary Button --}}
<a href="{{ $link }}" class="button button-secondary" style="
    background: transparent;
    border: 2px solid var(--msc-blue);
    color: var(--msc-blue);
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    display: inline-block;
    text-align: center;
">
    {{ $buttonText }}
</a>
```

## Advanced Features

### 1. Dynamic Deep Links

Create context-aware deep links:

```php
// In your service class
public function generateContextualDeepLink(string $action, $resource, array $context = []): string
{
    $baseUrl = config('app.url');
    $token = $this->generateSecureToken($resource, $context);
    
    $routes = [
        'order-view' => "/orders/{$resource->id}?token={$token}",
        'order-edit' => "/orders/{$resource->id}/edit?token={$token}",
        'profile-setup' => "/profile/setup?token={$token}",
        'security-settings' => "/profile/security?token={$token}",
        'dashboard' => "/dashboard?token={$token}&highlight={$context['highlight'] ?? ''}",
    ];
    
    return $baseUrl . ($routes[$action] ?? '/');
}
```

### 2. Tracking and Analytics

Add email tracking capabilities:

```blade
{{-- Tracking pixel for email opens --}}
<img src="{{ route('email.track.open', ['id' => $emailId, 'token' => $trackingToken]) }}" 
     width="1" height="1" style="display: block;" alt="" />

{{-- UTM tracking for links --}}
@php
$utmParams = http_build_query([
    'utm_source' => 'email',
    'utm_medium' => 'notification',
    'utm_campaign' => $emailType,
    'utm_content' => $buttonType ?? 'primary-cta'
]);
@endphp

<a href="{{ $baseLink }}?{{ $utmParams }}" class="button button-primary">
    {{ $buttonText }}
</a>
```

### 3. A/B Testing Support

Create variant templates:

```blade
{{-- Template A: Standard layout --}}
@if($variant === 'A')
    <div class="standard-layout">
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ $link }}" class="button button-primary">{{ $cta }}</a>
    </div>
@else
    {{-- Template B: Enhanced layout --}}
    <div class="enhanced-layout">
        <div style="background: var(--msc-light-blue); padding: 20px; border-radius: 8px;">
            <h1 style="color: var(--msc-blue);">{{ $title }}</h1>
            <p style="font-size: 18px;">{{ $message }}</p>
            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ $link }}" class="button button-primary button-large">{{ $cta }}</a>
            </div>
        </div>
    </div>
@endif
```

## Testing & Validation

### 1. Email Testing Framework

**File**: `tests/Feature/EmailTemplateTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Services\Notifications\MailgunNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected MailgunNotificationService $notificationService;

    public function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(MailgunNotificationService::class);
    }

    public function test_order_approved_email_template()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        
        $view = view('emails.order-approved', [
            'provider' => $user,
            'order' => $order,
            'deepLink' => 'https://example.com/orders/1'
        ]);
        
        $html = $view->render();
        
        $this->assertStringContainsString('Order Request Approved', $html);
        $this->assertStringContainsString($order->id, $html);
        $this->assertStringContainsString($user->name, $html);
    }

    public function test_email_accessibility_compliance()
    {
        $html = view('emails.layout')->render();
        
        // Check for alt text on images
        $this->assertStringContainsString('alt=', $html);
        
        // Check for semantic structure
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('role=', $html);
        
        // Check for aria labels
        $this->assertStringContainsString('aria-label=', $html);
    }
}
```

### 2. Cross-Client Testing

Create a testing checklist:

```bash
# Email Client Testing Checklist

## Desktop Clients
- [ ] Outlook 2016/2019/365
- [ ] Apple Mail
- [ ] Thunderbird
- [ ] Gmail (Web)

## Mobile Clients  
- [ ] iOS Mail
- [ ] Android Gmail
- [ ] Outlook Mobile

## Web Clients
- [ ] Gmail
- [ ] Yahoo Mail
- [ ] Outlook.com
- [ ] AOL Mail

## Testing Tools
- [ ] Litmus
- [ ] Email on Acid
- [ ] Mailtrap
- [ ] Can I Email
```

### 3. Performance Testing

Monitor email rendering performance:

```php
// Add to your email service
public function validateEmailPerformance(string $template, array $data): array
{
    $startTime = microtime(true);
    
    $view = view($template, $data);
    $html = $view->render();
    
    $renderTime = microtime(true) - $startTime;
    $htmlSize = strlen($html);
    $imageCount = substr_count($html, '<img');
    $linkCount = substr_count($html, '<a');
    
    return [
        'render_time_ms' => round($renderTime * 1000, 2),
        'html_size_kb' => round($htmlSize / 1024, 2),
        'image_count' => $imageCount,
        'link_count' => $linkCount,
        'performance_score' => $this->calculatePerformanceScore($renderTime, $htmlSize)
    ];
}
```

## Best Practices

### 1. Design Principles

- **Mobile-First**: Design for mobile, enhance for desktop
- **Accessibility**: Include alt text, semantic HTML, high contrast
- **Performance**: Optimize images, minimize CSS, limit external resources
- **Consistency**: Maintain brand consistency across all templates

### 2. Content Guidelines

```blade
{{-- Use clear, action-oriented subject lines --}}
@section('title', 'Action Required: Order #' . $order->id . ' Needs Review')

{{-- Keep content scannable with headers and bullet points --}}
<h2>What You Need to Do:</h2>
<ol>
    <li>Review the order details below</li>
    <li>Check for any missing information</li>
    <li>Submit your approval or request changes</li>
</ol>

{{-- Use clear, prominent call-to-action buttons --}}
<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $actionLink }}" class="button button-primary">
        Review Order Now
    </a>
</div>
```

### 3. Security Considerations

```php
// Always validate and sanitize data
public function sanitizeEmailData(array $data): array
{
    return array_map(function ($value) {
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }, $data);
}

// Use secure tokens for sensitive links
public function generateSecureEmailToken(string $action, $resource): string
{
    return encrypt([
        'action' => $action,
        'resource_id' => $resource->id,
        'resource_type' => get_class($resource),
        'expires_at' => now()->addHours(24),
        'signature' => hash_hmac('sha256', $action . $resource->id, config('app.key'))
    ]);
}
```

### 4. Maintenance and Updates

**Scheduled Review Checklist:**

1. **Monthly**: Review email performance metrics and user feedback
2. **Quarterly**: Update brand elements and test across email clients
3. **Annually**: Conduct accessibility audit and security review
4. **As Needed**: Update templates for new features and regulations

**Version Control for Templates:**

```bash
# Email template versioning strategy
resources/views/emails/
├── v1/                    # Legacy templates
├── current/               # Active templates
└── experimental/          # A/B test variants
```

---

## Quick Reference

### Common Customization Tasks

| Task | File to Edit | Key Considerations |
|------|-------------|-------------------|
| Change Colors | `layout.blade.php` | Update CSS variables, test contrast |
| Add Logo | `layout.blade.php` | Optimize image size, include alt text |
| Modify Footer | `layout.blade.php` | Include unsubscribe, legal text |
| Create New Template | New `.blade.php` file | Extend layout, include tracking |
| Add Components | `components/` folder | Make reusable, document usage |

### Testing Commands

```bash
# Test email rendering
php artisan tinker
>>> view('emails.order-approved', $testData)->render();

# Send test email
php artisan email:test order-approved user@example.com

# Validate all templates
php artisan test --filter=EmailTemplate
```

---

**Last Updated**: August 4, 2025  
**Next**: [Troubleshooting Guide](troubleshooting.md)
