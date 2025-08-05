<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Request from {{ $provider_name }}</title>
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
        .alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .alert-icon {
            color: #f59e0b;
            font-size: 1.2rem;
            margin-right: 8px;
        }
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
            width: 120px;
            color: #4a5568;
            margin-right: 10px;
        }
        .info-value {
            color: #2d3748;
        }
        .comment-box {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            font-style: italic;
            line-height: 1.7;
        }
        .comment-header {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            font-style: normal;
        }
        .footer {
            background: #f8fafc;
            color: #64748b;
            padding: 20px;
            text-align: center;
            font-size: 0.875rem;
        }
        .priority-high {
            color: #dc2626;
            font-weight: 600;
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
            <h1>🆘 Support Request</h1>
        </div>
        
        <div class="content">
            <div class="alert">
                <span class="alert-icon">⚠️</span>
                <strong>New help request received from a provider.</strong>
            </div>
            
            <p>A provider has submitted a support request through the MSC Wound Care Portal. Please review the details below and respond promptly.</p>
            
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Provider:</span>
                    <span class="info-value">{{ $provider_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $provider_email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Request Time:</span>
                    <span class="info-value">{{ now()->format('F j, Y \a\t g:i A T') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Priority:</span>
                    <span class="info-value priority-high">High</span>
                </div>
            </div>
            
            <div class="comment-box">
                <div class="comment-header">📝 Provider's Message:</div>
                {{ $comment }}
            </div>
            
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Review the provider's request details above</li>
                <li>Respond directly to <strong>{{ $provider_email }}</strong> (this email is configured to reply to the provider)</li>
                <li>Escalate to development team if technical assistance is needed</li>
                <li>Update internal support documentation if this reveals a common issue</li>
            </ul>
            
            <p><em>This is an automated notification from the MSC Wound Care Portal. Please do not reply to this email address - use the provider's email above instead.</em></p>
        </div>
        
        <div class="footer">
            <p>MSC Wound Care Portal | Medical Solutions Company<br>
            Notification sent at {{ now()->format('F j, Y \a\t g:i A T') }}</p>
        </div>
    </div>
</body>
</html>
