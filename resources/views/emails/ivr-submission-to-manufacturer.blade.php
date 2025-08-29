<x-mail::message>
# Insurance Verification Request - Order #{{ $order->request_number }}

Dear {{ $order->manufacturer->name ?? 'Valued Partner' }},

A new wound care order has been processed and requires insurance verification. Please find the Insurance Verification Request (IVR) attached to this email.

## Order Details

**Order Number:** {{ $order->request_number }}
**Provider:** {{ $provider->first_name ?? '' }} {{ $provider->last_name ?? '' }}
**Facility:** {{ $facility->name ?? 'N/A' }}
**Patient ID:** {{ $patient }}
**Expected Service Date:** {{ $order->expected_service_date ? \Carbon\Carbon::parse($order->expected_service_date)->format('M j, Y') : 'N/A' }}

## Products Ordered

@foreach($products as $product)
- **{{ $product['name'] ?? 'Product' }}** - Quantity: {{ $product['quantity'] ?? 1 }} - ${{ number_format($product['price'] ?? 0, 2) }}
@endforeach

**Total Order Value:** ${{ number_format($order->total_order_value ?? 0, 2) }}

## Next Steps

1. Review the attached IVR document
2. Process the insurance verification
3. Contact the provider if additional information is needed
4. Update the order status in the MSC Wound Care Portal

If you have any questions or need additional information, please contact our support team.

<x-mail::button :url="route('admin.orders.show', $order->id)" color="primary">
    View Order Details
</x-mail::button>

Thank you for your partnership in providing quality wound care.

Best regards,<br>
MSC Wound Care Portal Team
{{ config('app.name') }}

---
*This is an automated message. Please do not reply to this email.*
</x-mail::message>
