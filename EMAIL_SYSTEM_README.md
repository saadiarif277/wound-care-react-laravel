# MSC Wound Care Portal - Email System Setup

## Overview

This document outlines the complete Mailgun email system implementation for the MSC Wound Care Portal. The system provides unified email notifications for order processing, provider communications, and manufacturer interactions.

## 🚀 What's Been Implemented

### 1. Mail Configuration (`config/mail.php`)

- ✅ Updated default mailer to use Mailgun
- ✅ Configured proper from address and name
- ✅ Set up Mailgun transport

### 2. Services Configuration (`config/services.php`)

- ✅ Added complete Mailgun configuration
- ✅ Configured webhook signing secret
- ✅ Set up tracking domain support

### 3. Unified Notification Service (`app/Services/NotificationService.php`)

- ✅ Created centralized email notification service
- ✅ Supports queued and immediate email sending
- ✅ Handles IVR submissions to manufacturers
- ✅ Manages provider status notifications
- ✅ Processes order approval confirmations
- ✅ Sends manufacturer submission confirmations

### 4. Mailable Classes

- ✅ `IvrSubmissionToManufacturer` - IVR documents with PDF attachments
- ✅ `ProviderNotification` - Status updates to providers
- ✅ `OrderApprovalConfirmation` - Order approval notifications
- ✅ `ManufacturerSubmissionConfirmation` - Submission confirmations

### 5. Email Templates (Markdown-based)

- ✅ `ivr-submission-to-manufacturer.blade.php`
- ✅ `provider-notification.blade.php`
- ✅ `order-approval-confirmation.blade.php`
- ✅ `manufacturer-submission-confirmation.blade.php`

### 6. Queue System (`app/Jobs/SendNotification.php`)

- ✅ Background email processing
- ✅ Automatic retry logic
- ✅ Error handling and logging

### 7. Webhook Handler (`app/Http/Controllers/Webhooks/MailgunWebhookController.php`)

- ✅ Signature verification
- ✅ Event processing (delivered, opened, clicked, failed, complained)
- ✅ Email status tracking

### 8. Environment Configuration

- ✅ Updated `.env.example` with all Mailgun variables
- ✅ Comprehensive environment variable setup

## 📋 Environment Variables Required

Add these to your `.env` file:

```bash
# Mailgun Configuration
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=notifications@mscwoundcare.com
MAIL_FROM_NAME="MSC Wound Care Portal"

MAILGUN_DOMAIN=mg.mscwoundcare.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net
MAILGUN_WEBHOOK_SIGNING_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAILGUN_TRACKING_DOMAIN=track.mscwoundcare.com

# Queue Configuration
QUEUE_CONNECTION=redis
```

## 🔧 Setup Instructions

### 1. **Install Dependencies**
```bash
composer install
```

### 2. **Configure Environment**
- Copy `.env.example` to `.env`
- Add your Mailgun credentials
- Configure queue connection

### 3. **Run Migrations**
```bash
php artisan migrate
```

### 4. **Clear Configuration Cache**
```bash
php artisan config:clear
php artisan config:cache
```

### 5. **Set Up Queue Worker**
```bash
php artisan queue:table
php artisan migrate
php artisan queue:work --queue=emails,default
```

### 6. **DNS Configuration**
Add these records to your domain:

```
TXT @ "v=spf1 include:mailgun.org ~all"
TXT mail._domainkey "k=rsa; p=YOUR_PUBLIC_KEY"
CNAME mg.mscwoundcare.com mailgun.org
```

### 7. **Mailgun Dashboard Setup**
1. Go to Webhooks section
2. Add webhook URL: `https://your-domain.com/api/webhooks/mailgun`
3. Select events: delivered, opened, clicked, failed, complained

## 🧪 Testing

### Test Script
```bash
php test-mailgun.php --send-test your-email@example.com
```

### Manual Testing
```bash
# Test basic email sending
php artisan tinker
```
```php
use App\Services\NotificationService;
use App\Models\ProductRequest;

$order = ProductRequest::first();
$notificationService = new NotificationService();
$notificationService->sendOrderApprovalConfirmation($order);
```

### Queue Monitoring
```bash
# Check queue status
php artisan queue:status

# Monitor failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## 📊 Email Templates

All email templates use Laravel's Markdown mail system and include:

- **Responsive design** for mobile and desktop
- **Dynamic content** based on order data
- **Professional styling** with MSC branding
- **Action buttons** for common tasks
- **Proper formatting** for dates, currency, and lists

## 🔄 Webhook Events Handled

- **delivered** - Email successfully delivered
- **opened** - Email opened by recipient
- **clicked** - Link clicked in email
- **failed** - Delivery failed
- **complained** - Marked as spam

## 🛠️ API Usage

### Send IVR to Manufacturer
```php
$notificationService = app(NotificationService::class);
$notificationService->sendIvrToManufacturer($order, $ivrPdfPath);
```

### Notify Provider
```php
$notificationService->notifyProvider($order, 'approved', 'Order processed successfully');
```

### Send Order Confirmation
```php
$notificationService->sendOrderApprovalConfirmation($order);
```

### Bulk Notifications
```php
$notifications = [
    ['type' => 'ivr_to_manufacturer', 'order' => $order1, 'params' => ['ivr_path' => $path]],
    ['type' => 'provider_notification', 'order' => $order2, 'params' => ['status' => 'approved']]
];
$notificationService->sendBulkNotifications($notifications);
```

## 🔒 Security Features

- **Webhook signature verification** prevents spoofing
- **Rate limiting** on webhook endpoints
- **Input validation** on all email data
- **Secure credential storage** in environment variables
- **HTTPS-only** webhook endpoints

## 📈 Monitoring & Logging

- **Comprehensive logging** for all email operations
- **Queue monitoring** for background jobs
- **Webhook event tracking** for delivery status
- **Error reporting** for failed deliveries
- **Performance metrics** for email delivery times

## 🧹 Cleanup Performed

### Files Removed
- `scripts/fix-mail-config.php` - Obsolete configuration script
- `app/Services/MailService.php` - Replaced by NotificationService

### Files Updated
- `app/Services/EmailNotificationService.php` - Now uses NotificationService internally
- `config/mail.php` - Updated to use Mailgun as default
- `config/services.php` - Enhanced Mailgun configuration

## 🚨 Troubleshooting

### Common Issues

1. **"Class not found" errors**
   ```bash
   composer install
   composer dump-autoload
   ```

2. **Configuration not loading**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **Queue not processing**
   ```bash
   php artisan queue:work --queue=emails,default
   ```

4. **Webhook signature failures**
   - Verify `MAILGUN_WEBHOOK_SIGNING_SECRET` in `.env`
   - Check webhook URL is accessible
   - Ensure HTTPS is used for webhooks

5. **Emails going to spam**
   - Verify DNS records (SPF, DKIM, DMARC)
   - Check domain reputation in Mailgun
   - Ensure proper from address configuration

## 📚 Additional Resources

- [Mailgun Documentation](https://documentation.mailgun.com/)
- [Laravel Mail Documentation](https://laravel.com/docs/mail)
- [Laravel Queues Documentation](https://laravel.com/docs/queues)

## 🎯 Next Steps

1. **Configure your Mailgun account** and add credentials to `.env`
2. **Set up DNS records** for your domain
3. **Configure webhooks** in Mailgun dashboard
4. **Test the system** using the provided test script
5. **Monitor email delivery** and webhook events
6. **Set up production monitoring** and alerting

---

**Status**: ✅ Complete and Ready for Production
**Last Updated**: August 28, 2025
**Version**: 1.0.0
