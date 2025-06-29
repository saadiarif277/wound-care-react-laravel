<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $organizationName;
    public $invitationUrl;
    public $expiresAt;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->organizationName = $data['organization_name'];
        $this->invitationUrl = $data['invitation_url'];
        $this->expiresAt = $data['expires_at'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Our Healthcare Platform - Complete Your Organization Setup',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-invitation',
            with: [
                'organizationName' => $this->organizationName,
                'invitationUrl' => $this->invitationUrl,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
