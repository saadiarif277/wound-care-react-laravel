<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update</title>
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
            background-color: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        .status-submitted { background-color: #dbeafe; color: #1e40af; }
        .status-confirmed { background-color: #dcfce7; color: #166534; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
        .status-canceled { background-color: #fef3c7; color: #92400e; }
        .btn {
            display: inline-block;
            background-color: #1e40af;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #64748b;
        }
        .comments {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #1e40af;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MSC Platform</h1>
        <p>Order Status Update</p>
    </div>

    <div class="content">
        <p>Dear {{ $recipientName }},</p>

        <p>Your order <strong>{{ $orderId }}</strong> has been updated.</p>

        <div class="status-badge status-{{ strtolower(str_replace(' ', '-', $newStatus)) }}">
            New Order Status: {{ $newStatus }}
        </div>

        @if($comments)
        <div class="comments">
            <strong>Comments:</strong><br>
            {{ $comments }}
        </div>
        @endif

        <a href="{{ $orderDetailsUrl }}" class="btn">View Order</a>

        <div class="footer">
            <p>Thank you,<br>
            MSC Platform Team</p>

            <p>Questions? <a href="mailto:support@mscplatform.com">support@mscplatform.com</a></p>
        </div>
    </div>
</body>
</html>
