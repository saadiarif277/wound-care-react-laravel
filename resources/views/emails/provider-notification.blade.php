<x-mail::message>
# Order Status Update - #{{ $order->request_number }}

Dear Dr. {{ $provider->last_name ?? 'Provider' }},

We wanted to inform you of an important update regarding your wound care order.

## Order Details

**Order Number:** {{ $order->request_number }}
**Facility:** {{ $facility->name ?? 'N/A' }}
**Expected Service Date:** {{ $order->expected_service_date ? \Carbon\Carbon::parse($order->expected_service_date)->format('M j, Y') : 'N/A' }}

## Status Update

@if($status === 'send_back')
:x: **Action Required: Order Sent Back**

Your order has been sent back for review. Please check the following:

{{ $message ?? 'Please review and resubmit your order with the requested corrections.' }}

<x-mail::button :url="route('provider.orders.edit', $order->id)" color="warning">
    Review & Resubmit Order
</x-mail::button>

@elseif($status === 'denied')
:x: **Order Denied**

Unfortunately, your order has been denied at this time.

{{ $message ?? 'Please contact our support team for more information.' }}

<x-mail::button :url="route('provider.support.contact')" color="danger">
    Contact Support
</x-mail::button>

@elseif($status === 'approved')
:white_check_mark: **Order Approved**

Congratulations! Your order has been approved and is now being processed.

@else
:information_source: **Status Update**

Your order status has been updated to: **{{ ucfirst($status) }}**

@endif

<x-mail::button :url="route('provider.orders.show', $order->id)" color="primary">
    View Order Details
</x-mail::button>

If you have any questions about this update, please don't hesitate to contact our support team.

Best regards,<br>
MSC Wound Care Portal Team
{{ config('app.name') }}

---
*This is an automated message. Please do not reply to this email.*
</x-mail::message>
