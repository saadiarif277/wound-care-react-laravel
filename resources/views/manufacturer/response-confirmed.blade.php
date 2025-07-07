<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Response Received - MSC Wound Care</title>
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
            background: linear-gradient(90deg, #0066cc, #28a745, #0066cc);
        }
        
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounceIn 1s ease-out;
        }
        
        .approved { 
            color: #28a745;
            text-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
        }
        
        .denied { 
            color: #dc3545;
            text-shadow: 0 0 20px rgba(220, 53, 69, 0.3);
        }
        
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .status-text {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-text.approved {
            color: #28a745;
        }
        
        .status-text.denied {
            color: #dc3545;
        }
        
        .order-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            border-left: 4px solid #0066cc;
        }
        
        .order-info h3 {
            color: #0066cc;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .manufacturer-name {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .description {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 16px;
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
        
        .back-btn {
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
        
        .back-btn:hover {
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
        
        .auto-close {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px 20px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 3px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0066cc, #28a745);
            width: 0%;
            animation: progressBar 5s linear forwards;
        }
        
        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }
            50% {
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        @keyframes progressBar {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .success-icon {
                font-size: 60px;
            }
            
            .close-btn, .back-btn {
                display: block;
                margin: 10px 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon {{ $action === 'approve' ? 'approved' : 'denied' }}">
            @if($action === 'approve')
                ✅
            @else
                ❌
            @endif
        </div>
        
        <h1>Response Received</h1>
        
        <div class="status-text {{ $action === 'approve' ? 'approved' : 'denied' }}">
            {{ $action === 'approve' ? 'APPROVED' : 'DENIED' }}
        </div>
        
        <div class="order-info">
            <h3>Order Details</h3>
            <div class="order-number">Order #{{ $orderNumber }}</div>
            <div class="manufacturer-name">{{ $manufacturerName }}</div>
        </div>
        
        <div class="description">
            <p>
                <strong>Thank you for your response!</strong><br>
                You have <strong>{{ $action === 'approve' ? 'APPROVED' : 'DENIED' }}</strong> 
                the IVR for Order #{{ $orderNumber }}.
            </p>
            <p>The MSC team has been notified of your decision and will process accordingly.</p>
        </div>
        
        <div class="actions">
            <button class="close-btn" onclick="window.close()">Close Window</button>
            <a href="javascript:history.back()" class="back-btn">Go Back</a>
        </div>
        
        <div class="auto-close">
            <div>🕐 This window will automatically close in 5 seconds</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>MSC Wound Care Platform</strong></p>
            <p>Response recorded on {{ now()->format('F j, Y \a\t g:i A') }}</p>
            @if(isset($submissionId))
                <p style="font-size: 12px; color: #999;">Submission ID: {{ $submissionId }}</p>
            @endif
        </div>
    </div>
    
    <script>
        // Auto-close after 5 seconds
        setTimeout(() => {
            window.close();
        }, 5000);
        
        // Handle cases where window.close() doesn't work
        setTimeout(() => {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                document.body.innerHTML = '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;"><h2>Thank you!</h2><p>Your response has been recorded. You may now close this window.</p></div>';
            }
        }, 5500);
    </script>
</body>
</html> 