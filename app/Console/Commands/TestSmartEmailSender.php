<?php

namespace App\Console\Commands;

use App\Services\SmartEmailSender;
use App\Models\VerifiedSender;
use App\Models\SenderMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSmartEmailSender extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:smart-email {email?} {--type=test} {--manufacturer=} {--organization=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Smart Email Sender system with various scenarios';

    private SmartEmailSender $emailSender;

    public function __construct()
    {
        parent::__construct();
        $this->emailSender = new SmartEmailSender();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'test@example.com';
        $type = $this->option('type');
        $manufacturer = $this->option('manufacturer');
        $organization = $this->option('organization');
        $dryRun = $this->option('dry-run');

        $this->info('🧪 Smart Email Sender Test Suite');
        $this->newLine();

        // Test configuration first
        $this->testConfiguration();
        $this->newLine();

        // Show current stats
        $this->showStats();
        $this->newLine();

        // Run specific test or all tests
        switch ($type) {
            case 'config':
                $this->testConfiguration();
                break;
            case 'basic':
                $this->testBasicEmail($email, $dryRun);
                break;
            case 'manufacturer':
                $this->testManufacturerEmail($email, $manufacturer, $dryRun);
                break;
            case 'ivr':
                $this->testIvrEmail($email, $manufacturer, $organization, $dryRun);
                break;
            case 'partner':
                $this->testPartnerEmail($email, $organization, $dryRun);
                break;
            case 'all':
                $this->runAllTests($email, $dryRun);
                break;
            default:
                $this->runAllTests($email, $dryRun);
        }

        $this->newLine();
        $this->info('✅ Test suite completed');
    }

    private function testConfiguration(): void
    {
        $this->info('📋 Testing Configuration...');
        
        $result = $this->emailSender->testConfiguration();
        
        if ($result['success']) {
            $this->info('✅ Configuration is valid');
            $this->line("   Default Sender: {$result['default_sender']}");
            $this->line("   Verification Method: {$result['verification_method']}");
            $this->line("   Azure Communication: " . ($result['azure_communication_available'] ? '✅ Available' : '❌ Not configured'));
        } else {
            $this->error('❌ Configuration Error: ' . $result['message']);
        }
    }

    private function showStats(): void
    {
        $this->info('📊 Current System Stats');
        
        $stats = $this->emailSender->getSenderStats();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Senders', $stats['total_senders']],
                ['Verified Senders', $stats['verified_senders']],
                ['Active Senders', $stats['active_senders']],
                ['Azure Domain Senders', $stats['azure_domain_senders']],
                ['On Behalf Senders', $stats['on_behalf_senders']],
                ['Total Mappings', $stats['total_mappings']],
                ['Active Mappings', $stats['active_mappings']],
            ]
        );
    }

    private function testBasicEmail(string $email, bool $dryRun): void
    {
        $this->info('📧 Testing Basic Email...');
        
        if ($dryRun) {
            $this->warn('   🔍 DRY RUN - No actual email will be sent');
        }

        $result = $dryRun ? $this->simulateEmail($email, 'Basic Test Email', 'This is a test email.', []) 
                          : $this->emailSender->send(
                              $email, 
                              'Basic Test Email', 
                              'This is a test email from the Smart Email Sender.', 
                              ['test_type' => 'basic']
                          );

        $this->displayResult($result, 'Basic Email Test');
    }

    private function testManufacturerEmail(string $email, ?string $manufacturer, bool $dryRun): void
    {
        $manufacturer = $manufacturer ?? 'ACZ';
        $this->info("📦 Testing Manufacturer Email (Manufacturer: {$manufacturer})...");
        
        if ($dryRun) {
            $this->warn('   🔍 DRY RUN - No actual email will be sent');
        }

        $result = $dryRun ? $this->simulateEmail($email, "Order Update - {$manufacturer}", 'Order notification content', ['manufacturer_id' => $manufacturer, 'document_type' => 'order'])
                          : $this->emailSender->sendManufacturerEmail(
                              $manufacturer,
                              $email,
                              "Order Update - {$manufacturer}",
                              "This is a test order notification for {$manufacturer}."
                          );

        $this->displayResult($result, 'Manufacturer Email Test');
    }

    private function testIvrEmail(string $email, ?string $manufacturer, ?string $organization, bool $dryRun): void
    {
        $manufacturer = $manufacturer ?? 'Kerecis';
        $organization = $organization ?? null;
        
        $this->info("📋 Testing IVR Email (Manufacturer: {$manufacturer}" . ($organization ? ", Organization: {$organization}" : '') . ")...");
        
        if ($dryRun) {
            $this->warn('   🔍 DRY RUN - No actual email will be sent');
        }

        $result = $dryRun ? $this->simulateEmail($email, "IVR Request - {$manufacturer}", 'IVR content', ['manufacturer_id' => $manufacturer, 'document_type' => 'ivr', 'organization' => $organization])
                          : $this->emailSender->sendIvrEmail(
                              $manufacturer,
                              $email,
                              "IVR Request - {$manufacturer}",
                              "This is a test IVR notification for {$manufacturer}.",
                              $organization
                          );

        $this->displayResult($result, 'IVR Email Test');
    }

    private function testPartnerEmail(string $email, ?string $organization, bool $dryRun): void
    {
        $organization = $organization ?? 'Partner Clinic';
        $partnerEmail = 'orders@partnerclinic.com';
        
        $this->info("🤝 Testing Partner Email (Organization: {$organization})...");
        
        if ($dryRun) {
            $this->warn('   🔍 DRY RUN - No actual email will be sent');
        }

        $result = $dryRun ? $this->simulateEmail($email, 'Message from Partner Clinic', 'Partner message content', ['organization' => $organization, 'on_behalf_of' => $partnerEmail])
                          : $this->emailSender->sendOnBehalfOf(
                              $partnerEmail,
                              $email,
                              'Message from Partner Clinic',
                              "This is a test message sent on behalf of {$organization}.",
                              ['organization' => $organization]
                          );

        $this->displayResult($result, 'Partner Email Test');
    }

    private function runAllTests(string $email, bool $dryRun): void
    {
        $this->info('🔄 Running All Test Scenarios...');
        $this->newLine();

        $this->testBasicEmail($email, $dryRun);
        $this->newLine();

        $this->testManufacturerEmail($email, 'ACZ', $dryRun);
        $this->newLine();

        $this->testIvrEmail($email, 'Kerecis', null, $dryRun);
        $this->newLine();

        $this->testIvrEmail($email, 'MiMedx', 'Partner Clinic', $dryRun);
        $this->newLine();

        $this->testPartnerEmail($email, 'Partner Clinic', $dryRun);
    }

    private function simulateEmail(string $email, string $subject, string $content, array $context): array
    {
        // Simulate sender selection logic
        $sender = VerifiedSender::findBestSenderForContext($context);
        
        return [
            'success' => true,
            'sender' => $sender,
            'recipients' => [$email],
            'method' => 'simulation',
            'message_id' => 'sim_' . bin2hex(random_bytes(8)),
            'simulated' => true,
        ];
    }

    private function displayResult(array $result, string $testName): void
    {
        if ($result['success']) {
            $this->info("   ✅ {$testName} - SUCCESS");
            
            if (isset($result['sender'])) {
                $sender = $result['sender'];
                $this->line("      Sender: {$sender->display_name} <{$sender->email_address}>");
                $this->line("      Method: {$sender->verification_method}");
                $this->line("      Organization: {$sender->organization}");
            }
            
            $this->line("      Recipients: " . implode(', ', $result['recipients']));
            $this->line("      Delivery Method: " . ($result['method'] ?? 'unknown'));
            
            if (isset($result['message_id'])) {
                $this->line("      Message ID: {$result['message_id']}");
            }

            if (isset($result['simulated']) && $result['simulated']) {
                $this->warn("      🔍 SIMULATED - No actual email sent");
            }
        } else {
            $this->error("   ❌ {$testName} - FAILED");
            $this->error("      Error: " . ($result['error'] ?? 'Unknown error'));
        }
    }
}
