<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error - MSC Wound Care</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 50px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #dc3545, #fd7e14, #dc3545);
        }
        
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out;
        }
        
        h1 {
            color: #dc3545;
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .help-info {
            background: #e2e3e5;
            border: 1px solid #d6d8db;
            color: #383d41;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }
        
        .help-info h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .help-info ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        
        .help-info li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .contact-info {
            background: #cce5ff;
            border: 1px solid #99d3ff;
            color: #004085;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
        }
        
        .contact-info h3 {
            margin-bottom: 10px;
            color: #0066cc;
        }
        
        .contact-info a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 600;
        }
        
        .contact-info a:hover {
            text-decoration: underline;
        }
        
        .actions {
            margin-top: 30px;
        }
        
        .close-btn {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            margin: 0 10px;
        }
        
        .close-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .retry-btn {
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .retry-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
        
        .footer strong {
            color: #0066cc;
        }
        
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .error-icon {
                font-size: 60px;
            }
            
            .close-btn, .retry-btn {
                display: block;
                margin: 10px 0;
                width: 100%;
            }
            
            .help-info {
                text-align: center;
            }
            
            .help-info ul {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        
        <h1>Unable to Process Request</h1>
        
        <div class="error-message">
            <strong>{{ $message }}</strong>
        </div>
        
        <div class="help-info">
            <h3>🔍 Common Reasons:</h3>
            <ul>
                <li><strong>Expired Link:</strong> The response link has expired (typically after 72 hours)</li>
                <li><strong>Already Responded:</strong> This order has already been approved or denied</li>
                <li><strong>Invalid Link:</strong> The link may be corrupted or incomplete</li>
                <li><strong>System Issue:</strong> Temporary technical difficulties</li>
            </ul>
            
            <h3>📧 What to Do:</h3>
            <ul>
                <li>Check if you received a newer email with a fresh link</li>
                <li>Contact MSC support if you need to change your previous response</li>
                <li>Verify the complete link was copied if you pasted it manually</li>
            </ul>
        </div>
        
        <div class="contact-info">
            <h3>📞 Need Help?</h3>
            <p>
                Contact MSC Support:<br>
                <a href="mailto:{{ $supportEmail ?? 'support@mscwoundcare.com' }}">
                    {{ $supportEmail ?? 'support@mscwoundcare.com' }}
                </a>
            </p>
            <p style="margin-top: 10px; font-size: 14px;">
                Please include the order number and any error details when contacting support.
            </p>
        </div>
        
        <div class="actions">
            <button class="close-btn" onclick="window.close()">Close Window</button>
            <a href="javascript:location.reload()" class="retry-btn">Retry</a>
        </div>
        
        <div class="footer">
            <p><strong>MSC Wound Care Platform</strong></p>
            <p>Error occurred on {{ now()->format('F j, Y \a\t g:i A') }}</p>
        </div>
    </div>
    
    <script>
        // Handle cases where window.close() doesn't work
        document.querySelector('.close-btn').addEventListener('click', function() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        });
    </script>
</body>
</html> 