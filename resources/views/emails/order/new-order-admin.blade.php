<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order Submitted</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #e9ecef;
            border-radius: 0 0 8px 8px;
        }
        .order-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .order-details p {
            margin: 5px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>New Order Submitted</h2>
    </div>
    
    <div class="content">
        <p>A new order has been submitted by {{ $submitter->name }}.</p>
        
        <div class="order-details">
            <p><strong>Order ID:</strong> {{ $order['order_number'] }}</p>
            <p><strong>Product:</strong> {{ implode(', ', array_column($order['products'], 'name')) }}</p>
            <p><strong>Manufacturer:</strong> {{ $order['manufacturer']['name'] }}</p>
            <p><strong>Request Date:</strong> {{ date('m/d/Y', strtotime($order['submitted_at'])) }}</p>
            @if(!empty($comments))
            <p><strong>Comments:</strong> {{ $comments }}</p>
            @endif
        </div>
        
        <div style="text-align: center;">
            <a href="{{ $reviewUrl }}" class="button">View Order</a>
        </div>
        
        <div class="footer">
            <p>Thank you,<br>MSC Platform Team</p>
            <p>For support, contact us at <a href="mailto:support@mscwoundcare.com">support@mscwoundcare.com</a></p>
        </div>
    </div>
</body>
</html>