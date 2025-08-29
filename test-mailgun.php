<?php

/**
 * Mailgun Setup Test Script
 *
 * This script tests the Mailgun configuration and sends a test email
 * to verify that the setup is working correctly.
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Mailgun Setup Test\n";
echo "====================\n\n";

try {
    // Test 1: Check configuration
    echo "1. Checking Mailgun configuration...\n";

    $defaultMailer = config('mail.default');
    $mailgunConfig = config('services.mailgun');

    echo "   Default mailer: {$defaultMailer}\n";
    echo "   Mailgun domain: " . ($mailgunConfig['domain'] ?? 'NOT SET') . "\n";
    echo "   Mailgun secret: " . (isset($mailgunConfig['secret']) ? 'SET' : 'NOT SET') . "\n";
    echo "   Mailgun endpoint: " . ($mailgunConfig['endpoint'] ?? 'NOT SET') . "\n";
    echo "   Webhook secret: " . (isset($mailgunConfig['webhook_signing_secret']) ? 'SET' : 'NOT SET') . "\n";

    if ($defaultMailer !== 'mailgun') {
        echo "   ⚠️  Warning: Default mailer is not set to 'mailgun'\n";
    }

    if (!isset($mailgunConfig['domain']) || !isset($mailgunConfig['secret'])) {
        echo "   ❌ Mailgun configuration incomplete\n";
        exit(1);
    }

    echo "   ✅ Mailgun configuration looks good\n\n";

    // Test 2: Check if Mailgun transport is available
    echo "2. Checking Mailgun transport availability...\n";

    if (!class_exists('Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory')) {
        echo "   ❌ Mailgun transport factory not found\n";
        echo "   Please run: composer require symfony/mailgun-mailer\n";
        exit(1);
    }

    echo "   ✅ Mailgun transport is available\n\n";

    // Test 3: Test email sending (optional)
    if ($argc > 1 && $argv[1] === '--send-test') {
        echo "3. Sending test email...\n";

        $testEmail = $argv[2] ?? 'test@example.com';
        echo "   Sending test email to: {$testEmail}\n";

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from MSC Wound Care Portal using Mailgun.',
                function ($message) use ($testEmail) {
                    $message->to($testEmail)
                            ->subject('Mailgun Test - MSC Wound Care Portal');
                }
            );

            echo "   ✅ Test email sent successfully!\n";
            echo "   Check your email at: {$testEmail}\n";
        } catch (\Exception $e) {
            echo "   ❌ Failed to send test email: " . $e->getMessage() . "\n";
            exit(1);
        }
    } else {
        echo "3. Skipping test email (use --send-test email@example.com to test)\n";
    }

    echo "\n🎉 Mailgun setup verification complete!\n";
    echo "\nNext steps:\n";
    echo "1. Set your Mailgun credentials in .env file\n";
    echo "2. Configure DNS records for your domain\n";
    echo "3. Set up webhooks in Mailgun dashboard\n";
    echo "4. Test with: php test-mailgun.php --send-test your-email@example.com\n";

} catch (\Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    exit(1);
}
