<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manufacturer Response - Order #{{ $orderNumber }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            padding: 30px;
            text-align: center;
            color: white;
        }
        .header.approved {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .header.denied {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header .status {
            font-size: 32px;
            margin: 10px 0;
        }
        .content {
            padding: 30px;
        }
        .order-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #0066cc;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            flex: 1;
        }
        .info-value {
            flex: 2;
            text-align: right;
            color: #212529;
        }
        .response-details {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #2196f3;
        }
        .response-details h3 {
            margin: 0 0 15px 0;
            color: #1565c0;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 0 10px;
            text-decoration: none;
            color: white;
            background: #0066cc;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
        }
        
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 0;
            }
            .content {
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
            }
            .info-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header {{ strtolower($response) }}">
            <div class="status">{{ $statusEmoji }}</div>
            <h1>Manufacturer {{ strtoupper($response) }}</h1>
            <div>Order #{{ $orderNumber }}</div>
        </div>
        
        <div class="content">
            <p><strong>The manufacturer has {{ $response }} the IVR for order #{{ $orderNumber }}.</strong></p>
            
            <div class="order-info">
                <div class="info-row">
                    <div class="info-label">Order Number:</div>
                    <div class="info-value"><strong>{{ $orderNumber }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Patient ID:</div>
                    <div class="info-value">{{ $patientId }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Manufacturer:</div>
                    <div class="info-value">{{ $manufacturerName }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Product:</div>
                    <div class="info-value">{{ $productName }}</div>
                </div>
                @if(isset($providerName))
                <div class="info-row">
                    <div class="info-label">Provider:</div>
                    <div class="info-value">{{ $providerName }}</div>
                </div>
                @endif
                @if(isset($facilityName))
                <div class="info-row">
                    <div class="info-label">Facility:</div>
                    <div class="info-value">{{ $facilityName }}</div>
                </div>
                @endif
            </div>
            
            <div class="response-details">
                <h3>📋 Response Details</h3>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value"><strong>{{ strtoupper($response) }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Response Time:</div>
                    <div class="info-value">{{ $responseTime }}</div>
                </div>
                @if(isset($responseNotes) && $responseNotes)
                <div class="info-row">
                    <div class="info-label">Notes:</div>
                    <div class="info-value">{{ $responseNotes }}</div>
                </div>
                @endif
                @if(isset($responseTimeFromSent))
                <div class="info-row">
                    <div class="info-label">Time to Respond:</div>
                    <div class="info-value">{{ $responseTimeFromSent }}</div>
                </div>
                @endif
            </div>
            
            <div class="action-buttons">
                <a href="{{ $orderDetailsUrl }}" class="btn">View Order Details</a>
            </div>
            
            @if($response === 'approved')
                <p style="color: #28a745; font-weight: 600;">✅ Next steps: The order can now proceed to fulfillment.</p>
            @else
                <p style="color: #dc3545; font-weight: 600;">❌ Next steps: Please review the order and contact the manufacturer if needed.</p>
            @endif
        </div>
        
        <div class="footer">
            <p><strong>MSC Wound Care Platform</strong></p>
            <p>This notification was automatically generated when the manufacturer responded.</p>
        </div>
    </div>
</body>
</html> 