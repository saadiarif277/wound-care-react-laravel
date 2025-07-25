<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Order Submission</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #1e40af;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f8fafc;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-top: none;
        }
        .order-details {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: #1e40af;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        .note {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>New Order Submission</h1>
        <p>A new order has been submitted and requires your review.</p>
    </div>

    <div class="content">
        <h2>Order Details</h2>

        <div class="order-details">
            <p><strong>Order Number:</strong> {{ $order->request_number }}</p>
            <p><strong>Submitted By:</strong> {{ $submitter->name }} ({{ $submitter->email }})</p>
            <p><strong>Patient:</strong> {{ $order->patient_first_name }} {{ $order->patient_last_name }}</p>
            <p><strong>Product:</strong> {{ $order->products->first()?->name ?? 'N/A' }}</p>
            <p><strong>Submitted At:</strong> {{ $order->submitted_at?->format('M j, Y g:i A') ?? 'N/A' }}</p>

            @if($order->isIvrRequired())
                <p><strong>IVR Status:</strong> {{ $order->ivr_status ?? 'Pending' }}</p>
            @else
                <p><strong>IVR Required:</strong> No ({{ $order->getIvrBypassReason() ?? 'Not specified' }})</p>
            @endif
        </div>

        @if($adminNote)
        <div class="note">
            <h3>Admin Note from {{ $submitter->name }}:</h3>
            <p>{{ $adminNote }}</p>
        </div>
        @endif

        <p>
            <a href="{{ $orderUrl }}" class="button">Review Order</a>
        </p>

        <div class="footer">
            <p>This is an automated notification from the MSC Wound Portal.</p>
            <p>If you have any questions, please contact the support team.</p>
        </div>
    </div>
</body>
</html>
