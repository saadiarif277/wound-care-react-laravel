# Master Email Template Layout

## Overview

The master email template provides a consistent, branded foundation for all MSC Wound Care Portal notifications. It includes:

- MSC Wound Care branding and colors
- Dark mode support
- Mobile-responsive design
- Accessibility features (WCAG 2.1 AA)
- Professional styling

## Design Specifications

### Color Palette
```css
:root {
    --msc-blue: #0033A0;           /* Primary brand blue */
    --msc-red: #DC143C;            /* Secondary brand red */
    --msc-dark-blue: #002270;      /* Darker blue for gradients */
    --msc-light-blue: #E6EBF5;     /* Light blue for backgrounds */
    --msc-light-red: #FFEEF0;      /* Light red for error states */
    --text-primary: #1a1a1a;       /* Primary text color */
    --text-secondary: #666666;     /* Secondary text color */
    --text-muted: #999999;         /* Muted text color */
    --bg-white: #ffffff;           /* White background */
    --bg-light: #f8f9fa;          /* Light background */
    --border-light: #e5e7eb;      /* Light border */
}
```

### Typography
- **Font Family**: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif
- **Base Font Size**: 16px
- **Line Height**: 1.6
- **Headings**: MSC Blue (#0033A0)

### Layout Structure
1. **Header**: MSC logo with gradient blue background
2. **Content Card**: White background with rounded corners
3. **Footer**: Links, contact info, unsubscribe options

## Master Template Code

```blade
{{-- resources/views/emails/layout.blade.php --}}
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>@yield('title', 'MSC Wound Care Portal')</title>
    
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
            --msc-blue: #0033A0;
            --msc-red: #DC143C;
            --msc-dark-blue: #002270;
            --msc-light-blue: #E6EBF5;
            --msc-light-red: #FFEEF0;
            --text-primary: #1a1a1a;
            --text-secondary: #666666;
            --text-muted: #999999;
            --bg-white: #ffffff;
            --bg-light: #f8f9fa;
            --border-light: #e5e7eb;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #ffffff;
                --text-secondary: #cccccc;
                --text-muted: #999999;
                --bg-white: #1a1a1a;
                --bg-light: #2a2a2a;
                --border-light: #3a3a3a;
            }
            
            .email-body {
                background-color: #0a0a0a !important;
            }
            
            .content-card {
                background-color: var(--bg-white) !important;
                border-color: var(--border-light) !important;
            }
            
            .header-bg {
                background-color: var(--msc-dark-blue) !important;
            }
        }
        
        /* Typography */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        /* Layout */
        .email-body {
            background-color: #f5f5f5;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: transparent;
        }
        
        /* Header styles */
        .header-bg {
            background: linear-gradient(135deg, var(--msc-blue) 0%, var(--msc-dark-blue) 100%);
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
        .logo-container {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-tagline {
            color: #ffffff;
            font-size: 14px;
            margin-top: 15px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        /* Content card */
        .content-card {
            background-color: var(--bg-white);
            border: 1px solid var(--border-light);
            border-radius: 0 0 8px 8px;
            padding: 40px 30px;
            margin-top: -1px;
        }
        
        /* Typography in content */
        h1 {
            color: var(--msc-blue);
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 20px 0;
            line-height: 1.3;
        }
        
        h2 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            margin: 30px 0 15px 0;
            line-height: 1.4;
        }
        
        p {
            color: var(--text-secondary);
            margin: 0 0 16px 0;
            line-height: 1.6;
        }
        
        /* Buttons */
        .button {
            display: inline-block;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            text-align: center;
            cursor: pointer;
        }
        
        .button-primary {
            background-color: var(--msc-blue);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 51, 160, 0.2);
        }
        
        .button-primary:hover {
            background-color: var(--msc-dark-blue);
            box-shadow: 0 4px 8px rgba(0, 51, 160, 0.3);
        }
        
        .button-secondary {
            background-color: #ffffff;
            color: var(--msc-blue);
            border: 2px solid var(--msc-blue);
        }
        
        .button-secondary:hover {
            background-color: var(--msc-light-blue);
        }
        
        .button-danger {
            background-color: var(--msc-red);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(220, 20, 60, 0.2);
        }
        
        .button-danger:hover {
            background-color: #b91c1c;
            box-shadow: 0 4px 8px rgba(220, 20, 60, 0.3);
        }
        
        /* Info boxes */
        .info-box {
            background-color: var(--bg-light);
            border-left: 4px solid var(--msc-blue);
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .info-box.warning {
            border-left-color: #f59e0b;
            background-color: #fffbeb;
        }
        
        .info-box.error {
            border-left-color: var(--msc-red);
            background-color: var(--msc-light-red);
        }
        
        .info-box.success {
            border-left-color: #10b981;
            background-color: #f0fdf4;
        }
        
        /* Data table */
        .data-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 12px;
            background-color: var(--msc-light-blue);
            color: var(--msc-blue);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Status badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-denied {
            background-color: var(--msc-light-red);
            color: #991b1b;
        }
        
        .badge-delivered {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        /* Footer */
        .footer {
            padding: 30px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .footer-links {
            margin: 20px 0;
        }
        
        .footer-links a {
            color: var(--msc-blue);
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
            
            .content-card {
                padding: 30px 20px !important;
            }
            
            h1 {
                font-size: 24px !important;
            }
            
            .button {
                display: block !important;
                width: 100% !important;
                margin: 10px 0 !important;
            }
            
            .data-table {
                font-size: 14px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px;
            }
        }
        
        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border-width: 0;
        }
        
        /* Print styles */
        @media print {
            .email-body {
                background-color: #ffffff !important;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="email-body">
        <div role="article" aria-roledescription="email" aria-label="@yield('aria-label', 'MSC Wound Care Notification')">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td align="center" style="padding: 20px 10px;">
                        <div class="email-container">
                            {{-- Header --}}
                            <div class="header-bg">
                                <div class="logo-container">
                                    <img src="{{ config('app.url') }}/images/msc-logo.png" 
                                         alt="MSC Wound Care" 
                                         width="200" 
                                         style="display: block; max-width: 100%; height: auto;">
                                </div>
                                @if(isset($headerTagline))
                                    <p class="header-tagline">{{ $headerTagline }}</p>
                                @endif
                            </div>
                            
                            {{-- Main Content --}}
                            <div class="content-card">
                                @yield('content')
                            </div>
                            
                            {{-- Footer --}}
                            <div class="footer">
                                <div class="footer-links">
                                    <a href="{{ config('app.url') }}/help">Help Center</a>
                                    <span style="color: var(--border-light);">|</span>
                                    <a href="{{ config('app.url') }}/contact">Contact Us</a>
                                    <span style="color: var(--border-light);">|</span>
                                    <a href="{{ config('app.url') }}/privacy">Privacy Policy</a>
                                </div>
                                
                                <p style="margin: 15px 0 5px 0; font-weight: 600; color: var(--text-secondary);">
                                    MSC Wound Care Portal
                                </p>
                                <p style="margin: 0; font-size: 12px;">
                                    © {{ date('Y') }} MSC Wound Care. All rights reserved.
                                </p>
                                
                                @if(isset($unsubscribeToken))
                                <p style="margin: 20px 0 0 0; font-size: 12px;">
                                    <a href="{{ config('app.url') }}/unsubscribe?token={{ $unsubscribeToken }}" 
                                       style="color: var(--text-muted); text-decoration: underline;">Unsubscribe</a>
                                    <span style="color: var(--border-light); margin: 0 5px;">|</span>
                                    <a href="{{ config('app.url') }}/preferences?token={{ $preferencesToken ?? $unsubscribeToken }}" 
                                       style="color: var(--text-muted); text-decoration: underline;">Email Preferences</a>
                                </p>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        {{-- Tracking Pixel --}}
        @if(isset($trackingPixel))
            <img src="{{ $trackingPixel }}" alt="" width="1" height="1" border="0" style="display: block;">
        @endif
    </div>
</body>
</html>
```

## Template Usage

### Basic Structure
```blade
@extends('emails.layout')

@section('title', 'Your Email Title')
@section('aria-label', 'Description for screen readers')

@section('content')
    <!-- Your email content here -->
@endsection
```

### Available Components

#### Buttons
```blade
<!-- Primary Action -->
<a href="{{ $link }}" class="button button-primary">Primary Action</a>

<!-- Secondary Action -->
<a href="{{ $link }}" class="button button-secondary">Secondary Action</a>

<!-- Danger/Warning -->
<a href="{{ $link }}" class="button button-danger">Critical Action</a>
```

#### Info Boxes
```blade
<!-- Standard Info -->
<div class="info-box">
    <p>Important information here</p>
</div>

<!-- Warning -->
<div class="info-box warning">
    <p>Warning message</p>
</div>

<!-- Error -->
<div class="info-box error">
    <p>Error message</p>
</div>

<!-- Success -->
<div class="info-box success">
    <p>Success message</p>
</div>
```

#### Data Tables
```blade
<table class="data-table">
    <thead>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Order ID:</strong></td>
            <td>#{{ $order->id }}</td>
        </tr>
    </tbody>
</table>
```

#### Status Badges
```blade
<span class="badge badge-pending">Pending</span>
<span class="badge badge-approved">Approved</span>
<span class="badge badge-denied">Denied</span>
<span class="badge badge-delivered">Delivered</span>
```

## Responsive Behavior

### Mobile Adaptations (< 600px)
- Full-width layout
- Larger touch targets
- Stacked button layout
- Simplified typography hierarchy

### Dark Mode Support
- Automatically adapts to user preferences
- Maintains brand colors for key elements
- Ensures proper contrast ratios

## Accessibility Features

- Semantic HTML structure
- ARIA labels and roles
- Screen reader support
- High contrast ratios
- Focus indicators
- Alternative text for images

## Browser Support

- Apple Mail (iOS/macOS)
- Gmail (Web/Mobile)
- Outlook (2016+, Web)
- Yahoo Mail
- Thunderbird
- Mobile email clients

## File Locations

- **Master Template**: `resources/views/emails/layout.blade.php`
- **Shared Styles**: Inline CSS (for maximum compatibility)
- **Logo Assets**: `public/images/msc-logo.png`

---

**Last Updated**: August 4, 2025  
**Next**: [Order Notifications](order-notifications.md)
