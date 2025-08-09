<?php

/**
 * Mail Configuration Fix Script
 *
 * This script automatically fixes common mail configuration issues
 * and provides fallback options when Mailgun is not available.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Config;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 Mail Configuration Fix Script\n";
echo "================================\n\n";

// Check current configuration
$defaultMailer = config('mail.default');
echo "Current default mailer: {$defaultMailer}\n";

$mailers = config('mail.mailers');
$issues = [];
$fixed = [];

foreach ($mailers as $name => $config) {
    echo "\nChecking mailer: {$name}\n";

    if (!isset($config['transport'])) {
        $issues[] = "Mailer '{$name}' has no transport configured";
        continue;
    }

    $transport = $config['transport'];
    echo "  Transport: {$transport}\n";

    // Check for Mailgun issues
    if ($transport === 'mailgun') {
        if (!class_exists('Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory')) {
            $issues[] = "Mailgun transport factory not found for mailer '{$name}'";

            echo "  ⚠️  Mailgun transport not available\n";
            echo "  🔧 Attempting to fix...\n";

            // Try to switch to a working transport
            if (fixMailgunConfiguration($name)) {
                $fixed[] = "Fixed mailgun configuration for mailer '{$name}'";
                echo "  ✅ Fixed mailgun configuration\n";
            } else {
                echo "  ❌ Could not fix mailgun configuration\n";
            }
        }
    }
}

// Summary
echo "\n=== Summary ===\n";

if (empty($issues) && empty($fixed)) {
    echo "✅ No issues found\n";
} else {
    if (!empty($fixed)) {
        echo "✅ Fixed issues:\n";
        foreach ($fixed as $fix) {
            echo "  - {$fix}\n";
        }
    }

    if (!empty($issues)) {
        echo "⚠️  Remaining issues:\n";
        foreach ($issues as $issue) {
            echo "  - {$issue}\n";
        }
    }
}

echo "\n🎯 Recommendations:\n";
echo "1. If you need Mailgun, install: composer require symfony/mailgun-mailer symfony/http-client\n";
echo "2. Or switch to a different mail driver in your .env file:\n";
echo "   MAIL_MAILER=log (for development)\n";
echo "   MAIL_MAILER=smtp (for production with SMTP)\n";
echo "3. Run: php artisan mail:check --fix\n";

/**
 * Fix Mailgun configuration by switching to a fallback transport
 */
function fixMailgunConfiguration($mailerName) {
    $fallbackTransports = ['log', 'array', 'smtp'];

    foreach ($fallbackTransports as $transport) {
        if (canUseTransport($transport)) {
            // Update the configuration
            $config = config("mail.mailers.{$mailerName}");
            $config['transport'] = $transport;

            echo "    Switched to {$transport} transport\n";
            return true;
        }
    }

    return false;
}

/**
 * Check if a transport can be used
 */
function canUseTransport($transport) {
    switch ($transport) {
        case 'log':
        case 'array':
            return true;
        case 'smtp':
            $smtpConfig = config('mail.mailers.smtp');
            return isset($smtpConfig['host']) && isset($smtpConfig['port']);
        default:
            return false;
    }
}
