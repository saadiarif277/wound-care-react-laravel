# Mailtrap Email Setup for Provider Invitations

This document explains how to set up and test the email flow for provider invitations using Mailtrap.

## Overview

The MSC Wound Care platform now includes a complete email flow for provider invitations using Mailtrap for development and testing. This ensures that all emails are captured and can be reviewed before going to production.

## Features

- ✅ **Professional Email Templates**: Beautiful, responsive email templates with MSC branding
- ✅ **Mailtrap Integration**: All emails captured in Mailtrap for testing
- ✅ **Provider Invitation Flow**: Complete invitation system with secure tokens
- ✅ **Email Tracking**: Logging and status tracking for all sent emails
- ✅ **Error Handling**: Comprehensive error handling and logging

## Setup Instructions

### 1. Mailtrap Configuration

#### Step 1: Create Mailtrap Account
1. Go to [mailtrap.io](https://mailtrap.io)
2. Create a free account
3. Create a new inbox for the project

#### Step 2: Get Mailtrap Credentials
1. In your Mailtrap inbox, go to "Settings" → "Integrations"
2. Select "Laravel" from the dropdown
3. Copy the SMTP credentials

#### Step 3: Update Environment Variables
Add the following to your `.env` file:

```env
# Mail Configuration
MAIL_MAILER=mailtrap
MAILTRAP_HOST=smtp.mailtrap.io
MAILTRAP_PORT=2525
MAILTRAP_ENCRYPTION=tls
MAILTRAP_USERNAME=your_mailtrap_username
MAILTRAP_PASSWORD=your_mailtrap_password

# From Address (will be overridden by Mailtrap in testing)
MAIL_FROM_ADDRESS=noreply@mscwoundcare.com
MAIL_FROM_NAME="MSC Wound Care"
```

### 2. Database Setup

Ensure the provider invitations table exists:

```bash
php artisan migrate
```

### 3. Email Template

The email template is located at:
```
resources/views/emails/provider-invitation.blade.php
```

## Testing the Email Flow

### Method 1: Using the Test Command

Run the test command to send a provider invitation email:

```bash
php artisan test:provider-invitation-email test@example.com
```

Optional parameters:
- `--organization-id=1`: Set organization ID (default: 1)
- `--invited-by=1`: Set inviting user ID (default: 1)

Example:
```bash
php artisan test:provider-invitation-email doctor@testclinic.com --organization-id=2 --invited-by=5
```

### Method 2: Through the Application

1. **Invite Providers via Admin Panel**:
   - Navigate to the admin panel
   - Go to "Organizations & Analytics"
   - Select an organization
   - Click "Invite Providers"
   - Add provider details and send invitations

2. **Check Mailtrap Inbox**:
   - Log into your Mailtrap account
   - Check the inbox for received emails
   - Verify email content and formatting

### Method 3: Programmatic Testing

You can also test programmatically in your code:

```php
use App\Models\Users\Provider\ProviderInvitation;
use App\Mail\ProviderInvitationEmail;
use Illuminate\Support\Facades\Mail;

// Create a test invitation
$invitation = ProviderInvitation::create([
    'email' => 'test@example.com',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'invitation_token' => Str::random(64),
    'organization_id' => 1,
    'invited_by_user_id' => 1,
    'status' => 'pending',
    'expires_at' => now()->addDays(30),
    'metadata' => [
        'invited_by_name' => 'Admin User',
        'organization_name' => 'Test Clinic'
    ]
]);

// Send the email
Mail::to($invitation->email)->send(new ProviderInvitationEmail($invitation));
```

## Email Template Features

### Design Elements
- **Responsive Design**: Works on desktop, tablet, and mobile
- **MSC Branding**: Uses official MSC colors and logo
- **Professional Layout**: Clean, medical industry-appropriate design
- **Call-to-Action**: Prominent "Accept Invitation" button

### Content Sections
1. **Header**: MSC branding and invitation title
2. **Introduction**: Personalized greeting and invitation details
3. **Benefits**: List of portal features and capabilities
4. **Action Button**: Clear call-to-action to accept invitation
5. **Security Notice**: Important security information
6. **Expiry Notice**: Reminder about invitation expiration
7. **Details**: Invitation metadata (inviter, expiry, etc.)
8. **Support Information**: Contact details for help
9. **Footer**: Legal and company information

### Template Variables
The email template uses the following variables:
- `$invitationUrl`: Secure invitation link
- `$inviterName`: Name of the person sending the invitation
- `$expiresAt`: Expiration date formatted for display

## Email Flow Process

### 1. Invitation Creation
```php
// In OnboardingService::inviteProviders()
$invitation = ProviderInvitation::create([
    'email' => $provider['email'],
    'first_name' => $provider['first_name'],
    'last_name' => $provider['last_name'],
    'invitation_token' => $this->generateSecureToken(),
    'organization_id' => $organizationId,
    'invited_by_user_id' => $invitedBy,
    'status' => 'pending',
    'expires_at' => now()->addDays(30)
]);
```

### 2. Email Sending
```php
// In OnboardingService::sendProviderInvitationEmail()
Mail::to($invitation->email)->send(new ProviderInvitationEmail($invitation));
```

### 3. Status Tracking
```php
// Update invitation status after sending
$invitation->update([
    'status' => 'sent',
    'sent_at' => now()
]);
```

## Error Handling

The system includes comprehensive error handling:

### Email Sending Errors
- Logs detailed error information
- Includes stack traces for debugging
- Re-throws exceptions for upstream handling

### Validation Errors
- Validates email format
- Checks for duplicate invitations
- Ensures required fields are present

### Database Errors
- Uses database transactions
- Rolls back on failure
- Logs database-specific errors

## Monitoring and Logging

### Email Logs
All email activities are logged with:
- Email address
- Invitation ID
- Success/failure status
- Error details (if applicable)
- Timestamp

### Log Locations
- Laravel logs: `storage/logs/laravel.log`
- Mailtrap dashboard: Real-time email monitoring

## Production Deployment

When deploying to production:

1. **Update Mail Configuration**:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=your-production-smtp-host
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=your-production-username
   MAIL_PASSWORD=your-production-password
   ```

2. **Update From Address**:
   ```env
   MAIL_FROM_ADDRESS=noreply@mscwoundcare.com
   MAIL_FROM_NAME="MSC Wound Care"
   ```

3. **Test Email Flow**:
   - Send test invitations
   - Verify email delivery
   - Check email formatting

## Troubleshooting

### Common Issues

1. **Emails Not Sending**:
   - Check Mailtrap credentials
   - Verify SMTP settings
   - Check Laravel logs for errors

2. **Template Not Rendering**:
   - Verify template file exists
   - Check template syntax
   - Ensure all variables are passed

3. **Invitation Links Not Working**:
   - Verify invitation tokens are generated
   - Check route configuration
   - Ensure tokens are not expired

### Debug Commands

```bash
# Test email configuration
php artisan tinker
Mail::raw('Test email', function($message) { $message->to('test@example.com')->subject('Test'); });

# Check invitation status
php artisan tinker
App\Models\Users\Provider\ProviderInvitation::where('email', 'test@example.com')->first();
```

## Security Considerations

1. **Token Security**: Invitation tokens are 64-character random strings
2. **Expiration**: Invitations expire after 30 days
3. **Rate Limiting**: Consider implementing rate limiting for invitations
4. **Email Validation**: All email addresses are validated before sending
5. **Logging**: All activities are logged for audit purposes

## Support

For issues with the email system:
- Check Laravel logs: `storage/logs/laravel.log`
- Review Mailtrap dashboard for email delivery status
- Contact development team with error details 
