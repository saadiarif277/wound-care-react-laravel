<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $status_type ?? 'Order Update' }} - Order #{{ $order_id }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .status-banner {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .status-banner.success {
            background: #f0fdf4;
            border-color: #22c55e;
        }
        .status-banner.error {
            background: #fef2f2;
            border-color: #ef4444;
        }
        .status-banner.warning {
            background: #fffbeb;
            border-color: #f59e0b;
        }
        .status-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #1e40af;
        }
        .status-title.success { color: #16a34a; }
        .status-title.error { color: #dc2626; }
        .status-title.warning { color: #d97706; }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #4a5568;
            margin-right: 10px;
        }
        .info-value {
            color: #2d3748;
        }
        .comments-box {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            font-style: italic;
            line-height: 1.7;
        }
        .comments-header {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            font-style: normal;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background: #1e40af;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
            transition: background-color 0.2s;
        }
        .button:hover {
            background: #1e3a8a;
        }
        .button-container {
            text-align: center;
        }
        .footer {
            background: #f8fafc;
            color: #64748b;
            padding: 20px;
            text-align: center;
            font-size: 0.875rem;
        }
        .reason-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            color: #991b1b;
        }
        .reason-header {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            .content {
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .info-label {
                width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $status_emoji ?? '📋' }} {{ $status_type ?? 'Order Update' }}</h1>
        </div>
        
        <div class="content">
            @if(isset($status_type))
                @php
                    $bannerClass = '';
                    $titleClass = '';
                    if (str_contains($status_type, 'Complete') || str_contains($status_type, 'Confirmed') || str_contains($status_type, 'Submitted')) {
                        $bannerClass = 'success';
                        $titleClass = 'success';
                    } elseif (str_contains($status_type, 'Denied') || str_contains($status_type, 'Back')) {
                        $bannerClass = 'error';
                        $titleClass = 'error';
                    } else {
                        $bannerClass = '';
                        $titleClass = '';
                    }
                @endphp
                
                <div class="status-banner {{ $bannerClass }}">
                    <h2 class="status-title {{ $titleClass }}">{{ $status_type }}</h2>
                    <p style="margin: 0; font-size: 1rem;">Order #{{ $order_id }} has been updated</p>
                </div>
            @endif
            
            <p>Hello {{ $provider_name ?? 'Provider' }},</p>
            
            @if(isset($status_type))
                @if(str_contains($status_type, 'IVR Verification Complete'))
                    <p>Great news! The IVR verification for your order has been completed successfully. Your order is now ready for the next steps in the fulfillment process.</p>
                @elseif(str_contains($status_type, 'IVR Sent Back'))
                    <p>We need to let you know that your order's IVR has been sent back for revision. Please review the details below and take the necessary action.</p>
                @elseif(str_contains($status_type, 'Order Submitted to Manufacturer'))
                    <p>Your order has been successfully submitted to the manufacturer and is now in their queue for processing.</p>
                @elseif(str_contains($status_type, 'Order Confirmed by Manufacturer'))
                    <p>Excellent news! The manufacturer has confirmed your order and it's now in production.</p>
                @elseif(str_contains($status_type, 'Order Denied'))
                    <p>We regret to inform you that your order has been denied. Please review the reason provided below.</p>
                @else
                    <p>Your order status has been updated. Please review the details below.</p>
                @endif
            @else
                <p>Your order has been updated. Please review the details below.</p>
            @endif
            
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#{{ $order_id }}</span>
                </div>
                @if(isset($manufacturer_name))
                <div class="info-row">
                    <span class="info-label">Manufacturer:</span>
                    <span class="info-value">{{ $manufacturer_name }}</span>
                </div>
                @endif
                @if(isset($admin_name))
                <div class="info-row">
                    <span class="info-label">Updated by:</span>
                    <span class="info-value">{{ $admin_name }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Update Time:</span>
                    <span class="info-value">{{ now()->format('F j, Y \a\t g:i A T') }}</span>
                </div>
            </div>
            
            @if(isset($denial_reason))
            <div class="reason-box">
                <div class="reason-header">❌ Denial Reason:</div>
                {{ $denial_reason }}
            </div>
            @endif
            
            @if(!empty($comments))
            <div class="comments-box">
                <div class="comments-header">💬 Additional Notes:</div>
                {{ $comments }}
            </div>
            @endif
            
            @if(isset($order_link))
            <div class="button-container">
                <a href="{{ $order_link }}" class="button">View Order Details</a>
            </div>
            @endif
            
            <p>If you have any questions about this update, please don't hesitate to reach out to our support team.</p>
        </div>
        
        <div class="footer">
            <p>MSC Wound Care Portal | Medical Solutions Company<br>
            Questions? Contact our support team<br>
            Notification sent at {{ now()->format('F j, Y \a\t g:i A T') }}</p>
        </div>
    </div>
</body>
</html>