<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review IVR Document - Order #{{ $orderNumber }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .document-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .order-details {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-details h2 {
            color: #0066cc;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0066cc;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #212529;
            font-size: 16px;
        }
        
        .pdf-viewer {
            padding: 0;
            background: white;
            position: relative;
            min-height: 600px;
        }
        
        .pdf-container {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: 0 0 12px 12px;
        }
        
        .pdf-fallback {
            padding: 40px;
            text-align: center;
            color: #6c757d;
        }
        
        .pdf-fallback h3 {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .pdf-download {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            margin: 10px;
        }
        
        .pdf-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .action-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
            position: sticky;
            top: 20px;
        }
        
        .action-panel h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .action-panel .description {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 16px 40px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 140px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-deny {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-deny:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .response-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }
        
        .response-form.show {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #0066cc;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-display {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .status-display.approved {
            background: #e8f5e8;
            border-color: #28a745;
        }
        
        .status-display.denied {
            background: #f8e6e6;
            border-color: #dc3545;
        }
        
        .status-display h4 {
            margin-bottom: 10px;
            color: #0066cc;
        }
        
        .status-display.approved h4 {
            color: #28a745;
        }
        
        .status-display.denied h4 {
            color: #dc3545;
        }
        
        .expires-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        
        .expires-warning strong {
            color: #856404;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
            }
            
            .pdf-container {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏥 IVR Document Review</h1>
        <div class="subtitle">Order #{{ $orderNumber }} - {{ $manufacturerName }}</div>
    </div>
    
    <div class="container">
        <div class="document-section">
            <div class="order-details">
                <h2>📋 Order Details</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Order Number</div>
                        <div class="detail-value">{{ $orderNumber }}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Manufacturer</div>
                        <div class="detail-value">{{ $manufacturerName }}</div>
                    </div>
                    @if(isset($orderDetails['patient_id']))
                        <div class="detail-item">
                            <div class="detail-label">Patient ID</div>
                            <div class="detail-value">{{ $orderDetails['patient_id'] }}</div>
                        </div>
                    @endif
                    @if(isset($orderDetails['product_name']))
                        <div class="detail-item">
                            <div class="detail-label">Product</div>
                            <div class="detail-value">{{ $orderDetails['product_name'] }}</div>
                        </div>
                    @endif
                    @if(isset($orderDetails['quantity']))
                        <div class="detail-item">
                            <div class="detail-label">Quantity</div>
                            <div class="detail-value">{{ $orderDetails['quantity'] }}</div>
                        </div>
                    @endif
                    @if(isset($orderDetails['provider_name']))
                        <div class="detail-item">
                            <div class="detail-label">Provider</div>
                            <div class="detail-value">{{ $orderDetails['provider_name'] }}</div>
                        </div>
                    @endif
                    @if(isset($orderDetails['facility_name']))
                        <div class="detail-item">
                            <div class="detail-label">Facility</div>
                            <div class="detail-value">{{ $orderDetails['facility_name'] }}</div>
                        </div>
                    @endif
                    <div class="detail-item">
                        <div class="detail-label">Submission Date</div>
                        <div class="detail-value">{{ $submission->created_at->format('F j, Y g:i A') }}</div>
                    </div>
                </div>
            </div>
            
            <div class="pdf-viewer">
                @if($pdfUrl)
                    <iframe src="{{ $pdfUrl }}" class="pdf-container" title="IVR Document - {{ $pdfFilename }}"></iframe>
                @else
                    <div class="pdf-fallback">
                        <h3>📄 Document Not Available</h3>
                        <p>The PDF document is not currently available for viewing.</p>
                        @if($pdfFilename)
                            <p>Original filename: {{ $pdfFilename }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        
        <div class="action-panel">
            @if($hasResponded)
                <div class="status-display {{ $response }}">
                    <h4>
                        {{ $response === 'approved' ? '✅ APPROVED' : '❌ DENIED' }}
                    </h4>
                    <p>This order was {{ $response }} {{ $responseTime }}.</p>
                </div>
            @elseif($submission->isExpired())
                <div class="status-display expired">
                    <h4>⏰ EXPIRED</h4>
                    <p>This response request has expired.</p>
                </div>
            @else
                <h3>Please Review and Respond</h3>
                <div class="description">
                    <p>Please review the attached IVR document and select your response below.</p>
                    <p><strong>Your decision will be final and cannot be changed.</strong></p>
                </div>
                
                <div class="action-buttons">
                    <a href="{{ $approveUrl }}" class="btn btn-approve" onclick="return confirm('Are you sure you want to APPROVE this IVR?')">
                        ✅ APPROVE
                    </a>
                    <a href="{{ $denyUrl }}" class="btn btn-deny" onclick="return confirm('Are you sure you want to DENY this IVR?')">
                        ❌ DENY
                    </a>
                </div>
                
                @if($submission->expires_at)
                    <div class="expires-warning">
                        <strong>⏰ This request expires on {{ $submission->expires_at->format('F j, Y \a\t g:i A') }}</strong>
                    </div>
                @endif
            @endif
        </div>
    </div>
    
    <div class="footer">
        <p><strong>MSC Wound Care Platform</strong></p>
        <p>If you have questions about this order, please contact MSC support.</p>
    </div>
    
    <script>
        // Add any interactive functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh status every 30 seconds if still pending
            @if($isPending)
                setInterval(function() {
                    // Could add AJAX polling here if needed
                }, 30000);
            @endif
        });
    </script>
</body>
</html> 