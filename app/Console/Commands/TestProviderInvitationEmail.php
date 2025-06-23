<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Users\Provider\ProviderInvitation;
use App\Mail\ProviderInvitationEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestProviderInvitationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:provider-invitation-email {email} {--organization-id=1} {--invited-by=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test provider invitation email functionality with Mailtrap';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $organizationId = $this->option('organization-id');
        $invitedBy = $this->option('invited-by');

        $this->info("Testing provider invitation email to: {$email}");
        $this->info("Organization ID: {$organizationId}");
        $this->info("Invited by user ID: {$invitedBy}");

        try {
            // Create a test invitation
            $invitation = ProviderInvitation::create([
                'email' => $email,
                'first_name' => 'Test',
                'last_name' => 'Provider',
                'invitation_token' => \Illuminate\Support\Str::random(64),
                'organization_id' => $organizationId,
                'invited_by_user_id' => $invitedBy,
                'assigned_facilities' => [],
                'assigned_roles' => ['provider'],
                'status' => 'pending',
                'expires_at' => now()->addDays(30),
                'metadata' => [
                    'invited_by_name' => 'Test Admin',
                    'organization_name' => 'Test Organization'
                ]
            ]);

            $this->info("Created test invitation with ID: {$invitation->id}");

            // Send the email
            $this->info("Sending email...");
            Mail::to($email)->send(new ProviderInvitationEmail($invitation));

            $this->info("✅ Email sent successfully!");
            $this->info("Check your Mailtrap inbox for the email.");
            $this->info("Invitation token: {$invitation->invitation_token}");

            // Update invitation status
            $invitation->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

            $this->info("Invitation status updated to 'sent'");

        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            Log::error('Test provider invitation email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }

        return 0;
    }
}
