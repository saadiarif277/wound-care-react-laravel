<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailgunCommand extends Command
{
    protected $signature = 'mail:test {to : Recipient email} {--subject=Test from MSC : Subject line} {--from= : From address override}';

    protected $description = 'Send a test email using the configured mailer (Mailgun if set)';

    public function handle(): int
    {
        $to = $this->argument('to');
        $subject = $this->option('subject') ?: 'Test from MSC';
        $from = $this->option('from');

        Mail::raw('This is a test email from MSC Wound Care Portal.', function ($message) use ($to, $subject, $from) {
            $message->to($to)->subject($subject);
            if ($from) {
                $message->from($from);
            }
        });

        $this->info("Dispatched test email to {$to} using mailer: ".config('mail.default'));
        return self::SUCCESS;
    }
}
