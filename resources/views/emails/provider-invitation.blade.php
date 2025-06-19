<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to Join MSC Wound Care Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1925c3 0%, #c71719 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #1925c3 0%, #c71719 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }
        .button:hover {
            opacity: 0.9;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .benefits {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .benefits h3 {
            color: #1925c3;
            margin-top: 0;
        }
        .benefits ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to MSC Wound Care Portal</h1>
            <p>You've been invited to join our healthcare provider network</p>
        </div>
        
        <div class="content">
            <h2>Hello!</h2>
            
            <p>{{ $inviterName }} has invited you to join the MSC Wound Care Portal, where you can streamline your wound care product ordering and patient management.</p>
            
            <div class="benefits">
                <h3>As a member, you'll be able to:</h3>
                <ul>
                    <li>Submit wound care product requests online</li>
                    <li>Track order status in real-time</li>
                    <li>Access patient documentation securely</li>
                    <li>Manage insurance verification efficiently</li>
                    <li>Collaborate with your care team</li>
                </ul>
            </div>
            
            <p>To get started, simply click the button below to create your account:</p>
            
            <div style="text-align: center;">
                <a href="{{ $invitationUrl }}" class="button">Accept Invitation</a>
            </div>
            
            <div class="warning">
                <strong>Important:</strong> This invitation will expire on {{ $expiresAt }}. 
                Please complete your registration before this date.
            </div>
            
            <h3>What happens next?</h3>
            <ol>
                <li>Click the "Accept Invitation" button above</li>
                <li>Create your secure password</li>
                <li>Complete your profile information</li>
                <li>Start submitting wound care orders immediately</li>
            </ol>
            
            <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
            
            <p>We look forward to having you as part of the MSC Wound Care network!</p>
            
            <p>Best regards,<br>
            The MSC Wound Care Team</p>
        </div>
        
        <div class="footer">
            <p>This invitation was sent to you by {{ $inviterName }}.</p>
            <p>If you believe you received this email in error, please ignore it.</p>
            <p>&copy; {{ date('Y') }} MSC Wound Care. All rights reserved.</p>
            <p>
                <a href="{{ url('/') }}" style="color: #1925c3;">Visit our website</a> | 
                <a href="{{ url('/privacy') }}" style="color: #1925c3;">Privacy Policy</a>
            </p>
        </div>
    </div>
</body>
</html>