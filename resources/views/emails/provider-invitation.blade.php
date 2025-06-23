<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Invitation - MSC Wound Care</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1925c3 0%, #c71719 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px 20px;
        }
        .invitation-text {
            font-size: 16px;
            margin-bottom: 25px;
            color: #555;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #1925c3 0%, #c71719 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
            transition: transform 0.2s ease;
        }
        .cta-button:hover {
            transform: translateY(-2px);
        }
        .details {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e9ecef;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .expiry-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #856404;
        }
        .security-notice {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #0c5460;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 20px 15px;
            }
            .header {
                padding: 20px 15px;
            }
            .detail-row {
                flex-direction: column;
                margin-bottom: 15px;
            }
            .detail-label {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">MSC</div>
            <h1>Provider Invitation</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Wound Care Portal Access</p>
        </div>

        <div class="content">
            <div class="invitation-text">
                <p>Hello,</p>

                <p>You have been invited by <strong>{{ $inviterName }}</strong> to join the MSC Wound Care Portal as a healthcare provider.</p>

                <p>This invitation will allow you to:</p>
                <ul style="margin: 15px 0; padding-left: 20px;">
                    <li>Submit product requests for your patients</li>
                    <li>Track order status and delivery information</li>
                    <li>Access clinical resources and product information</li>
                    <li>Manage patient documentation and insurance verification</li>
                </ul>
            </div>

            <div style="text-align: center;">
                <a href="{{ $invitationUrl }}" class="cta-button">
                    Accept Invitation & Create Account
                </a>
            </div>

            <div class="expiry-notice">
                <strong>⏰ Important:</strong> This invitation expires on {{ $expiresAt }}. Please accept the invitation before this date to ensure access to the portal.
            </div>

            <div class="security-notice">
                <strong>🔒 Security Notice:</strong> This invitation link is unique to your email address and should not be shared with others. If you did not expect this invitation, please contact our support team.
            </div>

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Invited By:</span>
                    <span class="detail-value">{{ $inviterName }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Invitation Expires:</span>
                    <span class="detail-value">{{ $expiresAt }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Portal Access:</span>
                    <span class="detail-value">MSC Wound Care Provider Portal</span>
                </div>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <p style="font-size: 14px; color: #666; margin-bottom: 10px;">
                    <strong>Need Help?</strong>
                </p>
                <p style="font-size: 14px; color: #666; margin: 5px 0;">
                    • For technical support: <a href="mailto:support@mscwoundcare.com" style="color: #1925c3;">support@mscwoundcare.com</a>
                </p>
                <p style="font-size: 14px; color: #666; margin: 5px 0;">
                    • For account questions: <a href="mailto:accounts@mscwoundcare.com" style="color: #1925c3;">accounts@mscwoundcare.com</a>
                </p>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                <strong>MSC Wound Care</strong><br>
                Healthcare Distribution & Clinical Support
            </p>
            <p style="margin: 0; font-size: 11px; opacity: 0.7;">
                This email was sent to you because you were invited to join the MSC Wound Care Portal.<br>
                If you have any questions, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>
