# Order Notification Templates

This document contains all email templates related to order management in the MSC Wound Care Portal.

## Template Overview

### Available Order Notification Templates

1. **Order Submitted (Admin)** - Notify admins of new order submissions
2. **Order Approved** - Confirm approval to providers
3. **Order Denied** - Inform providers of denials with reasons
4. **Order Sent Back** - Request corrections from providers
5. **IVR Submission** - Send IVR documents to manufacturers
6. **Order Fulfilled** - Notify admins of completion

## 1. Order Submitted to Admin Template

**File**: `resources/views/emails/order-submitted-admin.blade.php`

```blade
@extends('emails.layout')

@section('title', 'New Order Submitted - MSC Wound Care')
@section('aria-label', 'New order submission notification')

@section('content')
    <h1>📝 New Order Request Submitted</h1>
    
    <p>Hello {{ $admin->first_name ?? 'Admin' }},</p>
    
    <p>A new order request has been submitted and requires your review.</p>
    
    <div class="info-box">
        <table class="data-table" style="margin: 0;">
            <tr>
                <td style="width: 40%; font-weight: 600; color: var(--msc-blue);">Order ID:</td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">Provider:</td>
                <td>{{ $provider->name }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">Facility:</td>
                <td>{{ $order->facility->name }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">Service Date:</td>
                <td>{{ $order->expected_service_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">Manufacturer:</td>
                <td>{{ $order->manufacturer->name }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">Total Value:</td>
                <td style="font-size: 18px; font-weight: 700; color: var(--msc-blue);">
                    ${{ number_format($order->total_order_value, 2) }}
                </td>
            </tr>
        </table>
    </div>
    
    @if($order->is_urgent)
        <div class="info-box warning">
            <p style="margin: 0; font-weight: 600;">
                ⚡ This is marked as an URGENT order requiring immediate attention.
            </p>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            Review Order Details
        </a>
    </div>
    
    <p style="text-align: center; font-size: 14px; color: var(--text-muted); margin-top: 20px;">
        This order requires your immediate attention.
    </p>
@endsection
```

## 2. Order Approved Template

**File**: `resources/views/emails/order-approved.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Order Approved - MSC Wound Care')
@section('aria-label', 'Order approval notification')

@section('content')
    <h1 style="color: #10b981;">✅ Order Request Approved</h1>
    
    <p>Hello {{ $provider->name }},</p>
    
    <p>Great news! Your order request has been approved and is ready to proceed.</p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Order Information</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 40%;"><strong>Order ID:</strong></td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td><strong>Facility:</strong></td>
                <td>{{ $order->facility->name }}</td>
            </tr>
            <tr>
                <td><strong>Service Date:</strong></td>
                <td>{{ $order->expected_service_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Manufacturer:</strong></td>
                <td>{{ $order->manufacturer->name }}</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><span class="badge badge-approved">Approved</span></td>
            </tr>
            <tr>
                <td><strong>Total Value:</strong></td>
                <td style="font-size: 18px; font-weight: 700; color: var(--msc-blue);">
                    ${{ number_format($order->total_order_value, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
    
    <div class="info-box success">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">Next Steps:</h3>
        <ol style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>IVR will be sent to the manufacturer</li>
            <li>Insurance verification will be processed</li>
            <li>You'll receive updates on fulfillment status</li>
            <li>Products will be delivered as scheduled</li>
        </ol>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            View Order Status
        </a>
    </div>
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Questions?</strong> Contact our support team at<br>
            <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 3. Order Denied Template

**File**: `resources/views/emails/order-denied.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Order Denied - MSC Wound Care')
@section('aria-label', 'Order denial notification')

@section('content')
    <h1 style="color: var(--msc-red);">❌ Order Request Denied</h1>
    
    <p>Hello {{ $provider->name }},</p>
    
    <p>Unfortunately, your order request has been denied. Please see the details below:</p>
    
    <div class="info-box error">
        <h2 style="margin: 0 0 10px 0; font-size: 16px;">Denial Reason:</h2>
        <p style="margin: 0; color: var(--text-primary); font-weight: 500;">
            {{ $reason }}
        </p>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Order Information</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 40%;"><strong>Order ID:</strong></td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td><strong>Facility:</strong></td>
                <td>{{ $order->facility->name }}</td>
            </tr>
            <tr>
                <td><strong>Service Date:</strong></td>
                <td>{{ $order->expected_service_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Manufacturer:</strong></td>
                <td>{{ $order->manufacturer->name }}</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><span class="badge badge-denied">Denied</span></td>
            </tr>
            <tr>
                <td><strong>Denied By:</strong></td>
                <td>{{ $order->deniedByUser->first_name ?? 'Admin' }} {{ $order->deniedByUser->last_name ?? '' }}</td>
            </tr>
            <tr>
                <td><strong>Denied At:</strong></td>
                <td>{{ $order->denied_at->format('F j, Y g:i A') }}</td>
            </tr>
        </tbody>
    </table>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-secondary">
            View Order Details
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px;">
            You may submit a new order with the required corrections.
        </p>
    </div>
    
    <div class="info-box">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">How to Resubmit:</h3>
        <ol style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>Address the denial reason above</li>
            <li>Gather any missing documentation</li>
            <li>Create a new order request in the portal</li>
            <li>Include all required information</li>
        </ol>
    </div>
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Need help?</strong> Contact our support team at<br>
            <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 4. Order Sent Back Template

**File**: `resources/views/emails/order-sent-back.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Order Sent Back - MSC Wound Care')
@section('aria-label', 'Order correction request notification')

@section('content')
    <h1 style="color: #f59e0b;">🔄 Order Sent Back for Edits</h1>
    
    <p>Hello {{ $provider->name }},</p>
    
    <p>Your order request requires corrections before it can be approved. Please review the feedback below and make the necessary changes.</p>
    
    <div class="info-box warning">
        <h2 style="margin: 0 0 10px 0; font-size: 16px;">Required Changes:</h2>
        <p style="margin: 0; color: var(--text-primary); font-weight: 500;">
            {{ $reason }}
        </p>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Order Information</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 40%;"><strong>Order ID:</strong></td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td><strong>Facility:</strong></td>
                <td>{{ $order->facility->name }}</td>
            </tr>
            <tr>
                <td><strong>Service Date:</strong></td>
                <td>{{ $order->expected_service_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><span style="background-color: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">CORRECTIONS NEEDED</span></td>
            </tr>
            <tr>
                <td><strong>Feedback From:</strong></td>
                <td>{{ $order->sentBackByUser->first_name ?? 'Admin' }} {{ $order->sentBackByUser->last_name ?? '' }}</td>
            </tr>
            <tr>
                <td><strong>Sent Back At:</strong></td>
                <td>{{ $order->sent_back_at->format('F j, Y g:i A') }}</td>
            </tr>
        </tbody>
    </table>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            Edit Order Now
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px;">
            Make the required changes and resubmit for approval.
        </p>
    </div>
    
    <div class="info-box">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">Common Issues & Solutions:</h3>
        <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li><strong>Missing Documentation:</strong> Upload all required patient and insurance documents</li>
            <li><strong>Incorrect Product Selection:</strong> Verify product codes and quantities match patient needs</li>
            <li><strong>Insurance Information:</strong> Ensure all insurance details are complete and accurate</li>
            <li><strong>Service Date:</strong> Confirm the date aligns with medical necessity and availability</li>
        </ul>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Priority Support:</strong> This order has been flagged for review.<br>
            Contact us at <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a> for immediate assistance.
        </p>
    </div>
@endsection
```

## 5. IVR Submission Template

**File**: `resources/views/emails/ivr-submission.blade.php`

```blade
@extends('emails.layout')

@section('title', 'IVR Submission - Order #' . $order->id)
@section('aria-label', 'IVR submission notification')

@section('content')
    <h1>📄 IVR Request for Order #{{ $order->id }}</h1>
    
    <p>Dear {{ $manufacturer->name }} Team,</p>
    
    <p>Please find attached the Insurance Verification Request (IVR) for the following order:</p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Order Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 40%;"><strong>Order ID:</strong></td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td><strong>Provider:</strong></td>
                <td>{{ $order->provider->name }}</td>
            </tr>
            <tr>
                <td><strong>Facility:</strong></td>
                <td>{{ $order->facility->name }}</td>
            </tr>
            <tr>
                <td><strong>Patient:</strong></td>
                <td>{{ $order->patient_first_name }} {{ $order->patient_last_name }}</td>
            </tr>
            <tr>
                <td><strong>Service Date:</strong></td>
                <td>{{ $order->expected_service_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Priority:</strong></td>
                <td>
                    @if($order->is_urgent)
                        <span class="badge badge-denied">URGENT</span>
                    @else
                        <span class="badge badge-pending">Standard</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>Total Value:</strong></td>
                <td style="font-size: 18px; font-weight: 700; color: var(--msc-blue);">
                    ${{ number_format($order->total_order_value, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
    
    <div class="info-box">
        <p style="margin: 0; font-weight: 600;">
            📎 The IVR document is attached to this email as a PDF file.
        </p>
    </div>
    
    @if($order->products->count() > 0)
        <div style="margin: 30px 0;">
            <h2>Ordered Products</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Code</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->product_code }}</td>
                        <td>{{ $product->pivot->quantity }}</td>
                        <td>${{ number_format($product->unit_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            View Full Order Details
        </a>
    </div>
    
    <div class="info-box">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">Next Steps:</h3>
        <ol style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>Review the attached IVR document carefully</li>
            <li>Verify insurance coverage and authorization requirements</li>
            <li>Process insurance verification through your systems</li>
            <li>Update order status in the MSC portal</li>
            <li>Proceed with fulfillment upon successful verification</li>
        </ol>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Questions about this IVR?</strong><br>
            Contact MSC at <a href="mailto:ivr@mscwoundcare.com" style="color: var(--msc-blue);">ivr@mscwoundcare.com</a><br>
            Or use the portal messaging system for order-specific inquiries.
        </p>
    </div>
    
    <p style="margin-top: 20px; font-size: 14px; color: var(--text-muted);">
        <strong>Important:</strong> This IVR contains protected health information (PHI). 
        Please handle according to HIPAA requirements and your organization's privacy policies.
    </p>
@endsection
```

## 6. Order Fulfilled Template

**File**: `resources/views/emails/order-fulfilled.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Order Fulfilled - MSC Wound Care')
@section('aria-label', 'Order fulfillment notification')

@section('content')
    <h1 style="color: #10b981;">📦 Order Fulfilled Successfully</h1>
    
    <p>Hello Admin Team,</p>
    
    <p>The following order has been successfully fulfilled by {{ $manufacturer->name }}:</p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Order Information</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 40%;"><strong>Order ID:</strong></td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td><strong>Provider:</strong></td>
                <td>{{ $order->provider->name }}</td>
            </tr>
            <tr>
                <td><strong>Facility:</strong></td>
                <td>{{ $order->facility->name }}</td>
            </tr>
            <tr>
                <td><strong>Manufacturer:</strong></td>
                <td>{{ $manufacturer->name }}</td>
            </tr>
            <tr>
                <td><strong>Service Date:</strong></td>
                <td>{{ $order->expected_service_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Fulfilled Date:</strong></td>
                <td>{{ now()->format('F j, Y g:i A') }}</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><span class="badge badge-delivered">Fulfilled</span></td>
            </tr>
        </tbody>
    </table>
    
    @if(isset($fulfillmentData))
        <div style="margin: 30px 0;">
            <h2>Fulfillment Details</h2>
            <div class="info-box success">
                @if(isset($fulfillmentData['tracking_number']))
                    <p style="margin: 0 0 10px 0;">
                        <strong>Tracking Number:</strong> {{ $fulfillmentData['tracking_number'] }}
                    </p>
                @endif
                @if(isset($fulfillmentData['carrier']))
                    <p style="margin: 0 0 10px 0;">
                        <strong>Carrier:</strong> {{ $fulfillmentData['carrier'] }}
                    </p>
                @endif
                @if(isset($fulfillmentData['estimated_delivery']))
                    <p style="margin: 0 0 10px 0;">
                        <strong>Estimated Delivery:</strong> {{ $fulfillmentData['estimated_delivery'] }}
                    </p>
                @endif
                @if(isset($fulfillmentData['notes']))
                    <p style="margin: 0;">
                        <strong>Notes:</strong> {{ $fulfillmentData['notes'] }}
                    </p>
                @endif
            </div>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            View Complete Order
        </a>
    </div>
    
    <div class="info-box">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">Recommended Actions:</h3>
        <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>Update order status in the system</li>
            <li>Notify the provider if required</li>
            <li>Schedule any follow-up appointments</li>
            <li>Update patient records as needed</li>
            <li>Process any pending invoicing</li>
        </ul>
    </div>
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Order Complete!</strong> This fulfillment marks the successful completion of the order workflow.<br>
            Total processing time: {{ $order->created_at->diffForHumans($order->updated_at, true) }}
        </p>
    </div>
@endsection
```

## Usage Instructions

### 1. Implementing Templates

Place each template in the appropriate location:
```bash
resources/views/emails/
├── order-submitted-admin.blade.php
├── order-approved.blade.php
├── order-denied.blade.php
├── order-sent-back.blade.php
├── ivr-submission.blade.php
└── order-fulfilled.blade.php
```

### 2. Controller Integration

Use these templates in your notification service:

```php
// Send to admins when order submitted
Mail::send('emails.order-submitted-admin', [
    'order' => $order,
    'provider' => $order->provider,
    'deepLink' => $this->generateDeepLink('admin-order', $order->id),
], function ($message) use ($admin, $order) {
    $message->to($admin->email)
            ->subject("📝 New Order Request Submitted by {$order->provider->name}");
});
```

### 3. Customization

Each template can be customized by:
- Modifying the content sections
- Adding conditional content based on order properties
- Including additional data tables or information blocks
- Changing button styles and actions

### 4. Testing

Test templates using:
```bash
php artisan tinker
>>> Mail::send('emails.order-approved', [...], function($m) { $m->to('test@example.com'); });
```

---

**Last Updated**: August 4, 2025  
**Next**: [User Management Templates](user-management.md)
