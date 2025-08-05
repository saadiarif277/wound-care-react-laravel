# User Management & Authentication Templates

This document contains all email templates related to user management, authentication, and account operations in the MSC Wound Care Portal.

## Template Overview

### Available User Management Templates

1. **User Registration Welcome** - Welcome new users to the platform
2. **Password Reset Request** - Secure password reset flow
3. **Account Activation** - Activate newly created accounts
4. **Profile Update Confirmation** - Confirm profile changes
5. **Account Deactivation Notice** - Notify of account status changes
6. **Login Security Alert** - Suspicious activity notifications

## 1. User Registration Welcome Template

**File**: `resources/views/emails/user-welcome.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Welcome to MSC Wound Care Portal')
@section('aria-label', 'Welcome notification for new user registration')

@section('content')
    <h1 style="color: var(--msc-blue);">🎉 Welcome to MSC Wound Care Portal</h1>
    
    <p>Hello {{ $user->first_name }},</p>
    
    <p>Welcome to the MSC Wound Care Portal! Your account has been successfully created and you're now part of our healthcare provider network.</p>
    
    <div class="info-box success">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">Your Account Details</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Name:</td>
                <td style="padding: 8px 0;">{{ $user->first_name }} {{ $user->last_name }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Email:</td>
                <td style="padding: 8px 0;">{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Role:</td>
                <td style="padding: 8px 0;">{{ ucfirst($user->role) }}</td>
            </tr>
            @if($user->facility_id)
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Facility:</td>
                <td style="padding: 8px 0;">{{ $user->facility->name }}</td>
            </tr>
            @endif
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Account Created:</td>
                <td style="padding: 8px 0;">{{ $user->created_at->format('F j, Y g:i A') }}</td>
            </tr>
        </table>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            Complete Account Setup
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px;">
            Please complete your profile and change your temporary password.
        </p>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>Getting Started</h2>
        <div class="info-box">
            <h3 style="margin: 0 0 15px 0; font-size: 16px;">What you can do with your account:</h3>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                @if($user->role === 'provider')
                    <li>Submit and manage wound care product orders</li>
                    <li>Track order status and delivery information</li>
                    <li>Access patient insurance verification tools</li>
                    <li>Manage facility and patient information</li>
                    <li>View order history and analytics</li>
                @elseif($user->role === 'admin')
                    <li>Review and approve/deny order requests</li>
                    <li>Manage providers and facilities</li>
                    <li>Monitor system-wide order analytics</li>
                    <li>Oversee insurance verification processes</li>
                    <li>Generate reports and export data</li>
                @elseif($user->role === 'manufacturer')
                    <li>Receive and process IVR requests</li>
                    <li>Update order fulfillment status</li>
                    <li>Access order analytics and reports</li>
                    <li>Manage product catalogs and pricing</li>
                    <li>Communicate with providers and admins</li>
                @endif
            </ul>
        </div>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>Important Security Information</h2>
        <div class="info-box warning">
            <p style="margin: 0 0 10px 0; font-weight: 600;">🔐 For your security:</p>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li>Change your temporary password immediately after first login</li>
                <li>Use a strong, unique password for this account</li>
                <li>Never share your login credentials with anyone</li>
                <li>Log out completely when finished using the portal</li>
                <li>Report any suspicious activity immediately</li>
            </ul>
        </div>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Need help getting started?</strong><br>
            Contact our support team at <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a><br>
            or call (555) 123-4567 for immediate assistance.
        </p>
    </div>
@endsection
```

## 2. Password Reset Request Template

**File**: `resources/views/emails/password-reset.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Password Reset Request - MSC Wound Care')
@section('aria-label', 'Password reset request notification')

@section('content')
    <h1 style="color: var(--msc-blue);">🔐 Password Reset Request</h1>
    
    <p>Hello {{ $user->first_name }},</p>
    
    <p>We received a request to reset the password for your MSC Wound Care Portal account.</p>
    
    <div class="info-box">
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue);">Account:</td>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">Request Time:</td>
                <td>{{ now()->format('F j, Y g:i A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">IP Address:</td>
                <td>{{ $ipAddress ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue);">User Agent:</td>
                <td style="font-size: 12px;">{{ $userAgent ?? 'Unknown' }}</td>
            </tr>
        </table>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetLink }}" class="button button-primary">
            Reset Your Password
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px; color: var(--text-muted);">
            This link will expire in {{ config('auth.passwords.users.expire') }} minutes for security.
        </p>
    </div>
    
    <div class="info-box warning">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">🛡️ Security Notice</h3>
        <p style="margin: 0 0 10px 0; font-size: 14px;">
            If you did not request this password reset, please:
        </p>
        <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>Ignore this email - your password remains unchanged</li>
            <li>Consider changing your password proactively</li>
            <li>Contact our security team if you suspect unauthorized access</li>
            <li>Review your recent account activity</li>
        </ul>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>Password Requirements</h2>
        <div class="info-box">
            <p style="margin: 0 0 10px 0; font-weight: 600;">When creating your new password, ensure it:</p>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li>Is at least 8 characters long</li>
                <li>Contains at least one uppercase letter</li>
                <li>Contains at least one lowercase letter</li>
                <li>Contains at least one number</li>
                <li>Contains at least one special character (!@#$%^&*)</li>
                <li>Is not a previously used password</li>
            </ul>
        </div>
    </div>
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Having trouble?</strong><br>
            Copy and paste this link into your browser:<br>
            <span style="word-break: break-all; color: var(--msc-blue); font-family: monospace; font-size: 12px;">{{ $resetLink }}</span>
        </p>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 20px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Security concerns?</strong><br>
            Contact our security team at <a href="mailto:security@mscwoundcare.com" style="color: var(--msc-blue);">security@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 3. Account Activation Template

**File**: `resources/views/emails/account-activation.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Activate Your Account - MSC Wound Care')
@section('aria-label', 'Account activation notification')

@section('content')
    <h1 style="color: var(--msc-blue);">✅ Activate Your MSC Account</h1>
    
    <p>Hello {{ $user->first_name }},</p>
    
    <p>Your MSC Wound Care Portal account has been created! Please activate your account to begin using the platform.</p>
    
    <div class="info-box">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">Account Information</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Name:</td>
                <td style="padding: 8px 0;">{{ $user->first_name }} {{ $user->last_name }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Email:</td>
                <td style="padding: 8px 0;">{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Role:</td>
                <td style="padding: 8px 0;">{{ ucfirst($user->role) }}</td>
            </tr>
            @if($user->facility_id)
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Facility:</td>
                <td style="padding: 8px 0;">{{ $user->facility->name }}</td>
            </tr>
            @endif
        </table>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $activationLink }}" class="button button-primary">
            Activate Account Now
        </a>
        <p style="margin: 15px 0 0 0; font-size: 14px; color: var(--text-muted);">
            This activation link will expire in 48 hours.
        </p>
    </div>
    
    <div class="info-box success">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">What happens after activation:</h3>
        <ol style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>You'll be prompted to set a secure password</li>
            <li>Complete your profile with additional information</li>
            <li>Review and accept our terms of service</li>
            <li>Begin using the platform immediately</li>
        </ol>
    </div>
    
    @if($user->role === 'provider')
        <div style="margin: 30px 0;">
            <h2>Provider Account Benefits</h2>
            <div class="info-box">
                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                    <li>Streamlined wound care product ordering</li>
                    <li>Real-time order tracking and status updates</li>
                    <li>Automated insurance verification assistance</li>
                    <li>Digital documentation and record keeping</li>
                    <li>Direct communication with manufacturers</li>
                    <li>Comprehensive reporting and analytics</li>
                </ul>
            </div>
        </div>
    @endif
    
    <div class="info-box warning">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">🔐 Security Notice</h3>
        <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
            <li>Only activate your account from a trusted device</li>
            <li>Use a strong, unique password</li>
            <li>Keep your login credentials confidential</li>
            <li>Report any suspicious activity immediately</li>
        </ul>
    </div>
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Activation link not working?</strong><br>
            Copy and paste this URL into your browser:<br>
            <span style="word-break: break-all; color: var(--msc-blue); font-family: monospace; font-size: 12px;">{{ $activationLink }}</span>
        </p>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 20px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Need assistance?</strong><br>
            Contact our support team at <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 4. Profile Update Confirmation Template

**File**: `resources/views/emails/profile-updated.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Profile Updated - MSC Wound Care')
@section('aria-label', 'Profile update confirmation notification')

@section('content')
    <h1 style="color: var(--msc-blue);">📝 Profile Updated Successfully</h1>
    
    <p>Hello {{ $user->first_name }},</p>
    
    <p>Your MSC Wound Care Portal profile has been successfully updated. Here's a summary of the changes:</p>
    
    <div class="info-box success">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">Updated Information</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Update Time:</td>
                <td style="padding: 8px 0;">{{ now()->format('F j, Y g:i A T') }}</td>
            </tr>
            @if(isset($changes['name']))
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Name:</td>
                <td style="padding: 8px 0;">
                    @if(isset($changes['name']['old']))
                        <span style="text-decoration: line-through; color: var(--text-muted);">{{ $changes['name']['old'] }}</span><br>
                    @endif
                    <span style="color: var(--msc-blue); font-weight: 600;">{{ $changes['name']['new'] }}</span>
                </td>
            </tr>
            @endif
            @if(isset($changes['email']))
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Email:</td>
                <td style="padding: 8px 0;">
                    @if(isset($changes['email']['old']))
                        <span style="text-decoration: line-through; color: var(--text-muted);">{{ $changes['email']['old'] }}</span><br>
                    @endif
                    <span style="color: var(--msc-blue); font-weight: 600;">{{ $changes['email']['new'] }}</span>
                </td>
            </tr>
            @endif
            @if(isset($changes['phone']))
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Phone:</td>
                <td style="padding: 8px 0;">
                    @if(isset($changes['phone']['old']))
                        <span style="text-decoration: line-through; color: var(--text-muted);">{{ $changes['phone']['old'] }}</span><br>
                    @endif
                    <span style="color: var(--msc-blue); font-weight: 600;">{{ $changes['phone']['new'] }}</span>
                </td>
            </tr>
            @endif
            @if(isset($changes['facility']))
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 8px 0;">Facility:</td>
                <td style="padding: 8px 0;">
                    @if(isset($changes['facility']['old']))
                        <span style="text-decoration: line-through; color: var(--text-muted);">{{ $changes['facility']['old'] }}</span><br>
                    @endif
                    <span style="color: var(--msc-blue); font-weight: 600;">{{ $changes['facility']['new'] }}</span>
                </td>
            </tr>
            @endif
        </table>
    </div>
    
    @if(isset($changes['email']))
        <div class="info-box warning">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">📧 Email Address Changed</h3>
            <p style="margin: 0; font-size: 14px;">
                Your email address has been updated. Please note that:
            </p>
            <ul style="margin: 10px 0 0 0; padding-left: 20px; font-size: 14px;">
                <li>All future notifications will be sent to your new email</li>
                <li>You'll need to use the new email for login</li>
                <li>Email verification may be required for security</li>
            </ul>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $deepLink }}" class="button button-primary">
            View Updated Profile
        </a>
    </div>
    
    <div class="info-box">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">🔐 Security Information</h3>
        <table style="width: 100%; margin: 0; font-size: 14px;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-blue); padding: 4px 0;">IP Address:</td>
                <td style="padding: 4px 0;">{{ $ipAddress ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-blue); padding: 4px 0;">Browser:</td>
                <td style="padding: 4px 0; font-size: 12px;">{{ $userAgent ?? 'Unknown' }}</td>
            </tr>
        </table>
    </div>
    
    <div class="info-box warning">
        <h3 style="margin: 0 0 10px 0; font-size: 16px;">⚠️ Unauthorized Changes?</h3>
        <p style="margin: 0; font-size: 14px;">
            If you did not make these changes:
        </p>
        <ul style="margin: 10px 0 0 0; padding-left: 20px; font-size: 14px;">
            <li>Change your password immediately</li>
            <li>Review your recent account activity</li>
            <li>Contact our security team</li>
            <li>Check for any other unauthorized modifications</li>
        </ul>
    </div>
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Questions about these changes?</strong><br>
            Contact our support team at <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a><br>
            or for security concerns: <a href="mailto:security@mscwoundcare.com" style="color: var(--msc-blue);">security@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 5. Account Deactivation Notice Template

**File**: `resources/views/emails/account-deactivated.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Account Deactivated - MSC Wound Care')
@section('aria-label', 'Account deactivation notification')

@section('content')
    <h1 style="color: var(--msc-red);">🚫 Account Deactivated</h1>
    
    <p>Hello {{ $user->first_name }},</p>
    
    <p>Your MSC Wound Care Portal account has been deactivated. You will no longer be able to access the platform.</p>
    
    <div class="info-box error">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">Deactivation Details</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-red); padding: 8px 0;">Account:</td>
                <td style="padding: 8px 0;">{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Deactivated:</td>
                <td style="padding: 8px 0;">{{ now()->format('F j, Y g:i A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Reason:</td>
                <td style="padding: 8px 0;">{{ $reason ?? 'Administrative action' }}</td>
            </tr>
            @if(isset($deactivatedBy))
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Deactivated By:</td>
                <td style="padding: 8px 0;">{{ $deactivatedBy }}</td>
            </tr>
            @endif
        </table>
    </div>
    
    @if($reason)
        <div class="info-box">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">Additional Information</h3>
            <p style="margin: 0; font-size: 14px;">
                {{ $additionalInfo ?? 'Please contact support if you believe this deactivation was made in error.' }}
            </p>
        </div>
    @endif
    
    <div style="margin: 30px 0;">
        <h2>What This Means</h2>
        <div class="info-box warning">
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li>You can no longer log into the MSC Wound Care Portal</li>
                <li>All active sessions have been terminated</li>
                <li>You will not receive future notifications from the platform</li>
                <li>Your data remains secure and is preserved according to our policies</li>
                <li>Any pending orders may be affected</li>
            </ul>
        </div>
    </div>
    
    @if($canReactivate ?? false)
        <div class="info-box success">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">Account Reactivation</h3>
            <p style="margin: 0; font-size: 14px;">
                Your account can be reactivated. Please contact our support team to discuss reactivation options.
            </p>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="mailto:support@mscwoundcare.com?subject=Account%20Reactivation%20Request%20-%20{{ urlencode($user->email) }}" class="button button-secondary">
            Contact Support
        </a>
    </div>
    
    <div style="margin: 30px 0;">
        <h2>Data and Privacy</h2>
        <div class="info-box">
            <p style="margin: 0 0 10px 0; font-size: 14px;">
                <strong>Your data protection rights:</strong>
            </p>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                <li>Your personal data is handled according to our Privacy Policy</li>
                <li>Data retention follows healthcare and legal requirements</li>
                <li>You may request data deletion where legally permissible</li>
                <li>Contact our privacy team for data-related requests</li>
            </ul>
        </div>
    </div>
    
    <div style="background-color: var(--bg-light); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Questions about this deactivation?</strong><br>
            Support: <a href="mailto:support@mscwoundcare.com" style="color: var(--msc-blue);">support@mscwoundcare.com</a><br>
            Privacy: <a href="mailto:privacy@mscwoundcare.com" style="color: var(--msc-blue);">privacy@mscwoundcare.com</a>
        </p>
    </div>
@endsection
```

## 6. Login Security Alert Template

**File**: `resources/views/emails/security-alert.blade.php`

```blade
@extends('emails.layout')

@section('title', 'Security Alert - MSC Wound Care')
@section('aria-label', 'Security alert for suspicious login activity')

@section('content')
    <h1 style="color: var(--msc-red);">🛡️ Security Alert: Unusual Login Activity</h1>
    
    <p>Hello {{ $user->first_name }},</p>
    
    <p>We detected a login to your MSC Wound Care Portal account from an unusual location or device.</p>
    
    <div class="info-box error">
        <h2 style="margin: 0 0 15px 0; font-size: 18px;">Login Details</h2>
        <table style="width: 100%; margin: 0;">
            <tr>
                <td style="width: 30%; font-weight: 600; color: var(--msc-red); padding: 8px 0;">Account:</td>
                <td style="padding: 8px 0;">{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Login Time:</td>
                <td style="padding: 8px 0;">{{ $loginTime->format('F j, Y g:i A T') }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">IP Address:</td>
                <td style="padding: 8px 0;">{{ $ipAddress }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Location:</td>
                <td style="padding: 8px 0;">{{ $location ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Device:</td>
                <td style="padding: 8px 0; font-size: 12px;">{{ $userAgent }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600; color: var(--msc-red); padding: 8px 0;">Status:</td>
                <td style="padding: 8px 0;">
                    @if($loginSuccessful)
                        <span style="color: var(--msc-red); font-weight: 600;">✅ Successful Login</span>
                    @else
                        <span style="color: var(--msc-orange); font-weight: 600;">❌ Failed Login Attempt</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
    
    @if($loginSuccessful)
        <div class="info-box warning">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">⚠️ Was this you?</h3>
            <p style="margin: 0; font-size: 14px;">
                If you recognized this login activity, you can safely disregard this email. 
                If you did not authorize this login, please take immediate action.
            </p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $secureAccountLink }}" class="button button-primary" style="background-color: var(--msc-red); border-color: var(--msc-red);">
                Secure My Account
            </a>
            <p style="margin: 15px 0 0 0; font-size: 14px;">
                This will immediately log out all devices and prompt password reset.
            </p>
        </div>
    @else
        <div class="info-box success">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">✅ Login Attempt Blocked</h3>
            <p style="margin: 0; font-size: 14px;">
                The suspicious login attempt was automatically blocked. Your account remains secure.
            </p>
        </div>
    @endif
    
    <div style="margin: 30px 0;">
        <h2>Recommended Security Actions</h2>
        <div class="info-box">
            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                @if($loginSuccessful)
                    <li><strong>Change your password immediately</strong> if this wasn't you</li>
                    <li>Log out of all devices and sessions</li>
                @endif
                <li>Review your recent account activity</li>
                <li>Enable two-factor authentication if not already active</li>
                <li>Use a unique, strong password for this account</li>
                <li>Avoid using public computers for sensitive access</li>
                <li>Report any suspicious activity to our security team</li>
            </ul>
        </div>
    </div>
    
    @if(isset($recentActivity) && count($recentActivity) > 0)
        <div style="margin: 30px 0;">
            <h2>Recent Account Activity</h2>
            <div class="info-box">
                <table style="width: 100%; margin: 0; font-size: 12px;">
                    <thead>
                        <tr style="background-color: var(--bg-light);">
                            <th style="padding: 8px; text-align: left;">Time</th>
                            <th style="padding: 8px; text-align: left;">IP Address</th>
                            <th style="padding: 8px; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentActivity as $activity)
                        <tr>
                            <td style="padding: 8px;">{{ $activity['time'] }}</td>
                            <td style="padding: 8px;">{{ $activity['ip'] }}</td>
                            <td style="padding: 8px;">
                                @if($activity['successful'])
                                    <span style="color: #10b981;">Success</span>
                                @else
                                    <span style="color: var(--msc-red);">Failed</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    
    <div style="background-color: var(--msc-light-blue); padding: 20px; border-radius: 6px; margin-top: 30px;">
        <p style="margin: 0; font-size: 14px; text-align: center;">
            <strong>Security Emergency?</strong><br>
            Immediate assistance: <a href="mailto:security@mscwoundcare.com" style="color: var(--msc-blue);">security@mscwoundcare.com</a><br>
            or call our security hotline: (555) 123-HELP
        </p>
    </div>
    
    <p style="margin-top: 20px; font-size: 12px; color: var(--text-muted); text-align: center;">
        This is an automated security alert. MSC will never ask for your password via email.
    </p>
@endsection
```

## Usage Instructions

### 1. Implementation

Place templates in:
```bash
resources/views/emails/
├── user-welcome.blade.php
├── password-reset.blade.php
├── account-activation.blade.php
├── profile-updated.blade.php
├── account-deactivated.blade.php
└── security-alert.blade.php
```

### 2. Service Integration

Example usage in your `MailgunNotificationService`:

```php
// Welcome new user
public function sendWelcomeEmail(User $user, string $temporaryPassword = null)
{
    $deepLink = $this->generateDeepLink('profile-setup', $user->id);
    
    return $this->sendEmail(
        'emails.user-welcome',
        compact('user', 'deepLink', 'temporaryPassword'),
        $user->email,
        "🎉 Welcome to MSC Wound Care Portal - {$user->first_name}",
        'user-welcome'
    );
}

// Security alert
public function sendSecurityAlert(User $user, array $loginData)
{
    $secureAccountLink = $this->generateDeepLink('security-settings', $user->id);
    
    return $this->sendEmail(
        'emails.security-alert',
        array_merge($loginData, compact('user', 'secureAccountLink')),
        $user->email,
        "🛡️ Security Alert for your MSC Account",
        'security-alert'
    );
}
```

### 3. Customization Options

- **Conditional Content**: Use `@if` directives for role-specific information
- **Dynamic Data**: Pass additional arrays for complex data display
- **Branding**: Modify colors and styles in the master layout
- **Security Levels**: Adjust warning thresholds and requirements

---

**Last Updated**: August 4, 2025  
**Next**: [System & Administrative Templates](system-admin.md)
