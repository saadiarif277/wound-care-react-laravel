<x-mail::message>
# Order Submitted - #{{ $order->request_number }}

Dear {{ $order->manufacturer->name ?? 'Valued Partner' }},

An approved wound care order has been submitted to you for processing. All necessary documentation has been prepared and attached.

## Order Details

**Order Number:** {{ $order->request_number }}
**Provider:** Dr. {{ $provider->last_name ?? 'Provider' }}
**Facility:** {{ $facility->name ?? 'N/A' }}
**Expected Service Date:** {{ $order->expected_service_date ? \Carbon\Carbon::parse($order->expected_service_date)->format('M j, Y') : 'N/A' }}

## Products Requested

@foreach($products as $product)
- **{{ $product['name'] ?? 'Product' }}** - Quantity: {{ $product['quantity'] ?? 1 }} - ${{ number_format($product['price'] ?? 0, 2) }}
@endforeach

**Total Order Value:** ${{ number_format($order->total_order_value ?? 0, 2) }}

## Required Actions

1. **Insurance Verification**: Process the attached IVR document
2. **Product Preparation**: Prepare the requested products for delivery
3. **Delivery Coordination**: Coordinate delivery with the facility
4. **Status Updates**: Update order status as processing progresses

<x-mail::button :url="route('manufacturer.orders.show', $order->id)" color="primary">
    View Order Details
</x-mail::button>

If you need any additional information or have questions about this order, please contact our support team.

Thank you for your partnership!

Best regards,<br>
MSC Wound Care Portal Team
{{ config('app.name') }}

---
*This is an automated message. Please do not reply to this email.*
</x-mail::message>
