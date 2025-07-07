<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IVR Approval Required - Order #{{ $orderNumber }}</title>
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
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header .order-number {
            font-size: 18px;
            margin-top: 10px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 25px;
        }
        .order-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #0066cc;
        }
        .order-info h3 {
            margin: 0 0 15px 0;
            color: #0066cc;
            font-size: 18px;
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
        .attachment-notice {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: center;
            border: 2px solid #2196f3;
        }
        .attachment-notice h4 {
            margin: 0 0 10px 0;
            color: #1565c0;
            font-size: 16px;
        }
        .attachment-notice p {
            margin: 0;
            color: #1976d2;
        }
        .action-buttons {
            text-align: center;
            margin: 35px 0;
            padding: 20px;
            background: #fafafa;
            border-radius: 8px;
        }
        .action-buttons h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
        }
        .btn {
            display: inline-block;
            padding: 16px 45px;
            margin: 0 10px 10px 10px;
            text-decoration: none;
            color: white;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            min-width: 120px;
            text-align: center;
        }
        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-deny {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        .btn-view {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            margin-top: 10px;
            font-size: 14px;
            padding: 12px 30px;
        }
        .fallback-links {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
            font-size: 14px;
            color: #856404;
        }
        .fallback-links h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        .fallback-links a {
            color: #0066cc;
            word-break: break-all;
        }
        .footer {
            background: #f8f9fa;
            padding: 25px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .footer p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 14px;
        }
        .expires-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .expires-warning strong {
            color: #856404;
        }
        
        /* Mobile responsiveness */
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 0;
            }
            .content {
                padding: 20px;
            }
            .btn {
                display: block;
                margin: 10px 0;
                padding: 16px 20px;
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
        <div class="header">
            <h1>🏥 IVR Document for Review</h1>
            <div class="order-number">Order #{{ $orderNumber }}</div>
        </div>
        
        <div class="content">
            <div class="greeting">
                <strong>Dear {{ $manufacturerName }} Team,</strong>
            </div>
            
            <p>Please review the attached IVR (Insurance Verification Request) document for the following order. Your approval is required to proceed with processing.</p>
            
            <div class="order-info">
                <h3>📋 Order Details</h3>
                <div class="info-row">
                    <div class="info-label">Order Number:</div>
                    <div class="info-value"><strong>{{ $orderNumber }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Patient ID:</div>
                    <div class="info-value">{{ $patientInitials }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Product:</div>
                    <div class="info-value">{{ $productName }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Quantity:</div>
                    <div class="info-value">{{ $quantity }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Submission Date:</div>
                    <div class="info-value">{{ $submissionDate }}</div>
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
            
            <div class="attachment-notice">
                <h4>📎 IVR Document Attached</h4>
                <p>Please review the attached PDF document before making your decision.</p>
            </div>
            
            <div class="action-buttons">
                <h3>Please select your response:</h3>
                <a href="{{ $approveUrl }}" class="btn btn-approve">✅ APPROVE</a>
                <a href="{{ $denyUrl }}" class="btn btn-deny">❌ DENY</a>
                <br>
                <a href="{{ $viewUrl }}" class="btn btn-view">👁️ View in Browser</a>
            </div>
            
            <div class="expires-warning">
                <strong>⏰ Important:</strong> This request expires in 72 hours ({{ $expiresAt }}). 
                Please respond promptly to avoid delays in order processing.
            </div>
            
            <div class="fallback-links">
                <h4>📱 Can't see the buttons?</h4>
                <p>Copy and paste these links into your browser:</p>
                <p><strong>Approve:</strong> <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>
                <p><strong>Deny:</strong> <a href="{{ $denyUrl }}">{{ $denyUrl }}</a></p>
                <p><strong>View:</strong> <a href="{{ $viewUrl }}">{{ $viewUrl }}</a></p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>MSC Wound Care Platform</strong></p>
            <p>If you need additional information or have questions, please reply to this email.</p>
            <p>This is an automated message from the MSC platform.</p>
        </div>
    </div>
</body>
</html> 