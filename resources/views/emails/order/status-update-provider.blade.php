<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Update</title>
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
        .status-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .status-box h3 {
            margin: 0;
            color: #007bff;
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
        .status-sent { color: #17a2b8; }
        .status-verified { color: #28a745; }
        .status-rejected { color: #dc3545; }
        .status-submitted { color: #007bff; }
        .status-confirmed { color: #28a745; }
        .status-canceled { color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Order Update – {{ $order['order_number'] }}</h2>
    </div>
    
    <div class="content">
        @if($updateType === 'ivr')
            <p>The IVR for your order {{ $order['order_number'] }} has been updated.</p>
            
            <div class="status-box">
                <h3 class="status-{{ strtolower($newStatus) }}">New IVR Status: {{ $newStatus }}</h3>
            </div>
        @else
            <p>Your order {{ $order['order_number'] }} has been updated.</p>
            
            <div class="status-box">
                <h3 class="status-{{ strtolower(str_replace(' ', '-', $newStatus)) }}">New Order Status: {{ $newStatus }}</h3>
            </div>
        @endif
        
        @if(!empty($comments))
        <p><strong>Comments:</strong> {{ $comments }}</p>
        @endif
        
        <div style="text-align: center;">
            <a href="{{ $trackingUrl }}" class="button">View Order</a>
        </div>
        
        <div class="footer">
            <p>Thank you,<br>MSC Platform Team</p>
            @if($updateType === 'ivr')
                <p>Need help? <a href="mailto:support@mscplatform.com">support@mscplatform.com</a></p>
            @else
                <p>Questions? <a href="mailto:support@mscplatform.com">support@mscplatform.com</a></p>
            @endif
        </div>
    </div>
</body>
</html>