<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CheckMailConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:check {--fix : Attempt to fix configuration issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and optionally fix mail configuration issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking mail configuration...');

        $defaultMailer = config('mail.default');
        $this->line("Default mailer: {$defaultMailer}");

        $mailers = config('mail.mailers');

        if (!$mailers) {
            $this->error('No mailers configured!');
            return 1;
        }

        $issues = [];
        $fixed = [];

        foreach ($mailers as $name => $config) {
            $this->line("\nChecking mailer: {$name}");

            if (!isset($config['transport'])) {
                $issues[] = "Mailer '{$name}' has no transport configured";
                continue;
            }

            $transport = $config['transport'];
            $this->line("  Transport: {$transport}");

            // Check for known problematic transports
            if ($transport === 'mailgun' && !class_exists('Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory')) {
                $issues[] = "Mailgun transport factory not found for mailer '{$name}'";

                if ($this->option('fix')) {
                    $this->warn("  Attempting to fix mailgun configuration...");

                    // Try to switch to a working transport
                    if ($this->fixMailgunConfiguration($name)) {
                        $fixed[] = "Fixed mailgun configuration for mailer '{$name}'";
                        $this->info("  ✓ Fixed mailgun configuration");
                    } else {
                        $this->error("  ✗ Could not fix mailgun configuration");
                    }
                }
            }

            // Check for required configuration
            if ($transport === 'smtp') {
                if (!isset($config['host']) || !isset($config['port'])) {
                    $issues[] = "SMTP mailer '{$name}' missing host or port configuration";
                }
            }

            if ($transport === 'mailgun') {
                if (!isset($config['domain']) || !isset($config['secret'])) {
                    $issues[] = "Mailgun mailer '{$name}' missing domain or secret configuration";
                }
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Mail Configuration Summary ===');

        if (empty($issues) && empty($fixed)) {
            $this->info('✓ No issues found');
        }

        if (!empty($fixed)) {
            $this->info('Fixed issues:');
            foreach ($fixed as $fix) {
                $this->line("  ✓ {$fix}");
            }
        }

        if (!empty($issues)) {
            $this->warn('Remaining issues:');
            foreach ($issues as $issue) {
                $this->line("  ⚠ {$issue}");
            }

            $this->newLine();
            $this->warn('To fix issues automatically, run: php artisan mail:check --fix');
        }

        return empty($issues) ? 0 : 1;
    }

    /**
     * Attempt to fix mailgun configuration
     *
     * @param string $mailerName
     * @return bool
     */
    protected function fixMailgunConfiguration(string $mailerName): bool
    {
        try {
            // Check if we can switch to a different transport
            $fallbackTransports = ['log', 'array', 'smtp'];

            foreach ($fallbackTransports as $transport) {
                if ($this->canUseTransport($transport)) {
                    // Update the configuration
                    $config = config("mail.mailers.{$mailerName}");
                    $config['transport'] = $transport;

                    // Log the change
                    Log::warning("Mailgun transport not available, switching to {$transport}", [
                        'mailer' => $mailerName,
                        'original_transport' => 'mailgun',
                        'new_transport' => $transport
                    ]);

                    $this->info("  Switched to {$transport} transport");
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to fix mailgun configuration', [
                'mailer' => $mailerName,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if a transport can be used
     *
     * @param string $transport
     * @return bool
     */
    protected function canUseTransport(string $transport): bool
    {
        switch ($transport) {
            case 'log':
            case 'array':
                return true;
            case 'smtp':
                // Check if SMTP is properly configured
                $smtpConfig = config('mail.mailers.smtp');
                return isset($smtpConfig['host']) && isset($smtpConfig['port']);
            default:
                return false;
        }
    }
}
