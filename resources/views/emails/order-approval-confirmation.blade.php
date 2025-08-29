<x-mail::message>
# Order Approved - #{{ $order->request_number }}

Dear Dr. {{ $provider->last_name ?? 'Provider' }},

Great news! Your wound care order has been approved and is now moving forward in the process.

## Order Details

**Order Number:** {{ $order->request_number }}
**Facility:** {{ $facility->name ?? 'N/A' }}
**Expected Service Date:** {{ $order->expected_service_date ? \Carbon\Carbon::parse($order->expected_service_date)->format('M j, Y') : 'N/A' }}

## What's Next

1. **Document Preparation**: We'll prepare the necessary paperwork for manufacturer submission
2. **Insurance Verification**: The manufacturer will handle insurance verification
3. **Product Delivery**: Once approved, products will be delivered to your facility
4. **Status Updates**: You'll receive notifications at each step of the process

## Products Approved

@foreach($products as $product)
- **{{ $product['name'] ?? 'Product' }}** - Quantity: {{ $product['quantity'] ?? 1 }}
@endforeach

**Total Order Value:** ${{ number_format($order->total_order_value ?? 0, 2) }}

<x-mail::button :url="route('provider.orders.show', $order->id)" color="success">
    Track Your Order
</x-mail::button>

Thank you for choosing MSC Wound Care. We're committed to providing you with the best possible service.

Best regards,<br>
MSC Wound Care Portal Team
{{ config('app.name') }}

---
*This is an automated message. Please do not reply to this email.*
</x-mail::message>
