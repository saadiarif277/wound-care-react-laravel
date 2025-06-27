<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Request - {{ $order['order_number'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #1925c3;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .section {
            background-color: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section h3 {
            color: #1925c3;
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .alert {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>New Order Request</h1>
        <p>Order #{{ $order['order_number'] }}</p>
    </div>
    
    <div class="content">
        <div class="alert">
            <strong>Important:</strong> This order contains PHI (Protected Health Information). 
            Please handle in accordance with HIPAA regulations. Full patient details are available 
            in the attached IVR document.
        </div>

        <div class="section">
            <h3>Order Information</h3>
            <table>
                <tr>
                    <th>Order Number:</th>
                    <td>{{ $order['order_number'] }}</td>
                </tr>
                <tr>
                    <th>Submitted Date:</th>
                    <td>{{ $order['submitted_date'] }}</td>
                </tr>
                <tr>
                    <th>Service Date:</th>
                    <td>{{ $order['service_date'] ?? 'TBD' }}</td>
                </tr>
                <tr>
                    <th>Patient ID:</th>
                    <td>{{ $order['patient']['display_id'] }}</td>
                </tr>
                <tr>
                    <th>IVR Status:</th>
                    <td>
                        <span class="status-badge status-{{ strtolower($order['ivr_status']) }}">
                            {{ $order['ivr_status'] }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h3>Provider Information</h3>
            <table>
                <tr>
                    <th>Name:</th>
                    <td>{{ $order['provider']['name'] }}</td>
                </tr>
                <tr>
                    <th>NPI:</th>
                    <td>{{ $order['provider']['npi'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td>{{ $order['provider']['email'] }}</td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td>{{ $order['provider']['phone'] ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h3>Facility Information</h3>
            <table>
                <tr>
                    <th>Name:</th>
                    <td>{{ $order['facility']['name'] }}</td>
                </tr>
                <tr>
                    <th>Address:</th>
                    <td>
                        {{ $order['facility']['address'] }}<br>
                        {{ $order['facility']['city'] }}, {{ $order['facility']['state'] }} {{ $order['facility']['zip'] }}
                    </td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td>{{ $order['facility']['phone'] ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h3>Requested Products</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Size</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order['products'] as $product)
                    <tr>
                        <td>{{ $product['name'] }}</td>
                        <td>{{ $product['sku'] }}</td>
                        <td>{{ $product['size'] ?? 'N/A' }}</td>
                        <td>{{ $product['quantity'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(!empty($order['notes']))
        <div class="section">
            <h3>Clinical Notes</h3>
            <p>{{ $order['notes'] }}</p>
        </div>
        @endif

        <div class="section">
            <h3>Next Steps</h3>
            <ol>
                <li>Review the attached IVR document for complete patient and insurance information</li>
                <li>Process the order according to your standard procedures</li>
                <li>Update the order status in your system</li>
                <li>Contact the provider if any additional information is needed</li>
            </ol>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated message from MSC Wound Care Portal.</p>
        <p>Please do not reply directly to this email. For questions, contact the provider listed above.</p>
        <p>&copy; {{ date('Y') }} MSC Wound Care. All rights reserved.</p>
    </div>
</body>
</html>