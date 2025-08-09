# Mailgun Setup and Configuration Guide

## Prerequisites

1. **Mailgun Account**: Sign up at [mailgun.com](https://mailgun.com)
2. **Domain Setup**: Configure your sending domain
3. **Laravel Application**: MSC Wound Care Portal

## Step 1: Mailgun Account Configuration

### 1.1 Domain Setup

```bash
# Add these DNS records to your domain:
TXT @ "v=spf1 include:mailgun.org ~all"
TXT mail._domainkey "k=rsa; p=YOUR_PUBLIC_KEY"
CNAME mg.mscwoundcare.com mailgun.org
```

### 1.2 Get API Credentials

1. Navigate to Settings > API Keys
2. Copy your Private API Key
3. Note your Domain name (mg.mscwoundcare.com)

## Step 2: Laravel Configuration

### 2.1 Install Mailgun Driver

```bash
composer require symfony/mailgun-mailer
```

### 2.2 Environment Configuration

```bash
# .env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.mscwoundcare.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net
MAILGUN_WEBHOOK_SIGNING_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Optional for EU region
# MAILGUN_ENDPOINT=api.eu.mailgun.net

# Email settings
MAIL_FROM_ADDRESS=notifications@mscwoundcare.com
MAIL_FROM_NAME="MSC Wound Care Portal"
```

### 2.3 Services Configuration

```php
// config/services.php
'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    'scheme' => 'https',
    'webhook_signing_secret' => env('MAILGUN_WEBHOOK_SIGNING_SECRET'),
    'tracking_domain' => env('MAILGUN_TRACKING_DOMAIN', 'track.mscwoundcare.com'),
],
```

## Step 3: Database Setup

### 3.1 Run Migrations

```bash
php artisan migrate --path=database/migrations/2024_XX_XX_create_email_logs_table.php
php artisan migrate --path=database/migrations/2024_XX_XX_add_notification_fields.php
```

### 3.2 Seed Test Data

```bash
php artisan db:seed --class=ManufacturerEmailSeeder
```

## Step 4: Queue Configuration

### 4.1 Configure Queue Driver

```bash
# .env
QUEUE_CONNECTION=redis  # or database
```

### 4.2 Start Queue Worker

```bash
php artisan queue:work --queue=emails,default
```

## Step 5: Webhook Setup

### 5.1 Add Route

```php
// routes/api.php
Route::post('/webhooks/mailgun', [MailgunWebhookController::class, 'handle'])
    ->name('webhooks.mailgun');
```

### 5.2 Configure in Mailgun Dashboard

1. Go to Webhooks section
2. Add webhook URL: `https://your-domain.com/api/webhooks/mailgun`
3. Select events: delivered, opened, clicked, failed, complained

## Step 6: Testing

### 6.1 Test Email Sending (Artisan)

```bash
php artisan mail:test you@example.com --subject="Mailgun test"
```

### 6.2 Test Webhook Reception

```bash
# Send test webhook from Mailgun dashboard
# Check logs: tail -f storage/logs/laravel.log
```

## Step 7: DNS Verification

### 7.1 SPF Record

```env
v=spf1 include:mailgun.org ~all
```

### 7.2 DKIM Record

```env
k=rsa; p=[YOUR_PUBLIC_KEY_FROM_MAILGUN]
```

### 7.3 DMARC Record (Optional but Recommended)

```env
v=DMARC1; p=none; rua=mailto:dmarc@mscwoundcare.com
```

## Troubleshooting

### Common Issues

1. **DNS Not Propagated**: Wait 24-48 hours for DNS changes
2. **Authentication Failed**: Verify API key and domain
3. **Webhooks Not Working**: Check URL accessibility and signing key
4. **Emails in Spam**: Ensure SPF, DKIM, DMARC records are correct

### Debug Commands

```bash
# Test email configuration
php artisan config:clear
php artisan config:cache

# Check queue status
php artisan queue:failed
php artisan queue:retry all

# Monitor logs
tail -f storage/logs/laravel.log

# Test DNS records
dig TXT mg.mscwoundcare.com
dig TXT mail._domainkey.mg.mscwoundcare.com
```

## Security Considerations

1. **Webhook Signature Verification**: Always verify webhook signatures
2. **Rate Limiting**: Implement rate limiting for email sending
3. **Environment Variables**: Keep sensitive data in .env
4. **HTTPS Only**: Use HTTPS for all webhook endpoints
5. **Token Expiration**: Set reasonable expiration for deep link tokens

## Performance Optimization

1. **Queue Processing**: Use Redis for better queue performance
2. **Email Templates**: Optimize images and CSS
3. **Batch Processing**: Group similar notifications
4. **Caching**: Cache email templates and configurations
5. **Monitoring**: Set up email delivery monitoring

## Production Checklist

- [ ] DNS records configured and verified
- [ ] Mailgun domain verified
- [ ] Webhook endpoints secured with HTTPS
- [ ] Queue workers running in production
- [ ] Error monitoring configured
- [ ] Email bounce/complaint handling set up
- [ ] Rate limiting implemented
- [ ] Backup notification method configured

## Quick Setup Script

```bash
#!/bin/bash

# MSC Wound Care Portal - Email System Quick Start
echo "🚀 Setting up MSC Wound Care Email System..."

# Install dependencies
echo "📦 Installing dependencies..."
composer require symfony/mailgun-mailer

# Run migrations
echo "🗄️ Setting up database..."
php artisan migrate --path=database/migrations/2024_XX_XX_create_email_logs_table.php
php artisan migrate --path=database/migrations/2024_XX_XX_add_notification_fields.php

# Configure application
echo "⚙️ Configuring application..."
php artisan config:clear
php artisan config:cache

# Create directories
echo "📁 Creating template directories..."
mkdir -p resources/views/emails
mkdir -p app/Services/Notifications
mkdir -p app/Jobs
mkdir -p app/Http/Controllers/Webhooks

# Set up queue
echo "🔄 Setting up queue system..."
php artisan queue:table
php artisan migrate

# Test setup
echo "🧪 Testing email configuration..."
php artisan config:show mail

echo "✅ Email system setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure your .env file with Mailgun credentials"
echo "2. Deploy email templates to resources/views/emails/"
echo "3. Start queue worker: php artisan queue:work"
echo "4. Test with: php artisan tinker"
```

---

**Last Updated**: August 8, 2025  
**Next**: [Service Implementation](service-implementation.md)
